<?php
namespace scanning\model
{
	class Signature
	{
		public $id = 0;
		public $solarSystemID = 0;
		public $authGroupID = null;
		public $sigID = null;
		public $sigType;
		public $sigTypeID = 0;
		public $sigInfo;
		public $signalStrength;
		public $scandate = null;
		public $scannedBy = 0;
		public $updateDate;
		public $updateBy = 0;
		public $deleted = false;

		private $chain = null;
		private $solarsystem = null;
		private $wormhole = null;

		function __construct($id=false)
		{
			if ($id) {
				$this->id = $id;
				$this->load();
			}
		}

		function load($result=false)
		{
			if (!$result)
				$result = \MySQL::getDB()->getRow("SELECT * FROM map_signature WHERE id = ?", array($this->id));

			if ($result)
			{
				$this->id = $result["id"];
				$this->solarSystemID = $result["solarsystemid"];
				$this->authGroupID = $result["authgroupid"];
				$this->sigID = $result["sigid"];
				$this->sigType = $result["sigtype"];
				$this->sigTypeID = $result["typeid"];
				$this->sigInfo = $result["siginfo"];
				$this->signalStrength = $result["signalstrength"];
				$this->scandate = $result["scandate"];
				$this->scannedBy = $result["scannedby"];
				$this->updateDate = $result["updatedate"];
				$this->updateBy = $result["updateby"];
				$this->deleted = ($result["deleted"]>0)?true:false;
			}
		}

		function store($setUpdated=true)
		{
			// Is gewijzigd?
			$countInStats = false;
            if ($this->id > 0) {
                if (strlen(trim($this->sigType)) > 0) {
                    $oldsig = new \scanning\model\Signature($this->id);
                    if ($oldsig->sigType != $this->sigType)
                        $countInStats = true;
                }
            } else {
                // Het is een nieuwe
                if (strlen(trim($this->sigType)) > 0)
                    $countInStats = true;
            }

			if ($this->scandate == null) {
				$this->scandate = date("Y-m-d H:i:s");
				$this->scannedBy = \User::getUSER()->id;
			}

			$this->updateDate = date("Y-m-d H:i:s");
			$this->updateBy = \User::getUSER()->id;

			if ($this->authGroupID == null)
				$this->authGroupID = \scanning\model\Chain::getCurrentChain()->authgroupID;

            // Is het een wormhole?
            if (strtolower($this->sigType) == "wh")
            {
                // WH-type bekend? Zo niet, kijk of het af te leiden valt uit de naam van de signature.
                if ($this->sigTypeID == 0) {
                    if ($this->getChain()->getNamingScheme() != null) {
                        if (method_exists($this->getChain()->getNamingScheme(), "getWHTypeBySignatureName"))
                            $this->sigTypeID = $this->getChain()->getNamingScheme()->getWHTypeBySignatureName($this);
                    }
                }
            }

			$data = array(	"solarsystemid"	=> $this->solarSystemID,
							"authgroupid"	=> $this->authGroupID,
							"sigid"		=> strtolower($this->sigID),
							"sigtype"	=> strtolower($this->sigType),
							"typeid"	=> $this->sigTypeID,
							"siginfo"	=> $this->sigInfo,
							"signalstrength" => $this->signalStrength,
							"scandate"	=> date("Y-m-d H:i:s", strtotime($this->scandate)),
							"scannedby"	=> $this->scannedBy,
							"deleted"	=> ($this->deleted)?1:0,
							"updatedate"=> date("Y-m-d H:i:s", strtotime($this->updateDate)),
							"updateby"	=> $this->updateBy);

			if ($this->id != 0)
				$data["id"] = $this->id;

			$result = \MySQL::getDB()->updateinsert("map_signature", $data, array("id" => $this->id));
			if ($this->id == 0)
				$this->id = $result;

			if ($countInStats)
			{
				$stat = new \stats\model\Signature();
				$stat->userID = \User::getUSER()->id;
				$stat->corporationID = \User::getUSER()->getMainCharacter()->getCorporation()->id;
				$stat->signatureID = $this->id;
				$stat->chainID = \User::getSelectedChain();
				$stat->scandate = date("Y-m-d H:i:s");
				$stat->store();
			}


			// Systeem toevoegen?
            if ($this->getChain()->getSetting("create-unmapped")) {
                if (strtolower(trim($this->sigType)) == "wh" && !$this->deleted)
                    $this->addWormholeToMap();
            }


			// Check wh-nummber. Connection bijwerken.
			if ($this->sigTypeID > 0 && $this->sigTypeID != 9999)
			{
				// Parse signature name om de de juiste connectie te zoeken.
				$parts = explode(" ", $this->sigInfo);
				$parts = explode("-", $parts[0]);
				$wormholename = (count($parts) > 1) ? $parts[1] : $parts[0];
				\AppRoot::debug("UPDATE Connection Type: ".$wormholename);

				// Zoek dit wormhole
				foreach (\scanning\model\Wormhole::getWormholesByAuthgroup($this->authGroupID) as $wormhole)
				{
					if (trim(strtolower($wormhole->name)) == trim(strtolower($wormholename)))
					{
						$connection = \scanning\model\Connection::getConnectionByWormhole($this->getWormhole()->id, $wormhole->id, $wormhole->chainID);
						if ($connection != null)
						{
							if ($connection->fromWormholeID == $wormhole->id) {
								$connection->fromWHTypeID = 9999;
								$connection->toWHTypeID = $this->sigTypeID;
							} else {
								$connection->toWHTypeID = 9999;
								$connection->fromWHTypeID = $this->sigTypeID;
							}
							$connection->store(false);
						}
					}
				}
			}

			// Check open sigs.
			$controller = new \scanning\controller\Signature();
			$controller->checkOpenSignatures($this->getSolarSystem());

			return true;
		}

		function delete()
		{
            $this->deleted = true;
            return $this->store();
		}

		/**
		 * Get chain
		 * @return \scanning\model\Chain
		 */
		function getChain()
		{
			if ($this->chain == null)
				$this->chain = new \scanning\model\Chain(\User::getSelectedChain());

			return $this->chain;
		}

		/**
		 * Get solarsystem
		 * @return \scanning\model\System
		 */
		function getSolarSystem()
		{
			if ($this->solarsystem == null)
				$this->solarsystem = new \scanning\model\System($this->solarSystemID);

			return $this->solarsystem;
		}

		/**
		 * Get wormhole
		 * @return \scanning\model\Wormhole|null
		 */
		function getWormhole()
		{
			if ($this->wormhole == null)
				$this->wormhole = \scanning\model\Wormhole::getWormholeBySystemID($this->solarSystemID, \User::getSelectedChain());

			return $this->wormhole;
		}



		/**
		 * Get signatures by solarsystemid
		 * @param integer $solarSystemID
		 * @return \scanning\model\Signature[]
		 */
		public static function getSignaturesBySolarSystem($solarSystemID)
		{
			$signatures = array();
			if ($results = \MySQL::getDB()->getRows("SELECT s.*
													FROM 	map_signature s
														INNER JOIN mapwormholechains c ON c.authgroupid = s.authgroupid
													WHERE 	c.id = ?
													AND		s.solarsystemid = ?
													AND		s.deleted = 0"
									, array(\Tools::REQUEST("chainid"), $solarSystemID)))
			{
				foreach ($results as $result)
				{
					$sig = new \scanning\model\Signature();
					$sig->load($result);
					$signatures[] = $sig;
				}
			}

			return $signatures;
		}
	}
}
?>