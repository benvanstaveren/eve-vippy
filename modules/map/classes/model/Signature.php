<?php
namespace map\model;

class Signature extends \Model
{
    public $id;
    public $solarSystemID;
    public $authGroupID;
    public $sigID;
    public $sigTypeID;
    public $whTypeID;
    public $sigInfo;
    public $signalStrength;
    public $scanDate;
    public $scannedBy;
    public $updateDate;
    public $updateBy;
    public $deleted = false;

    private $_solarsystem;
    private $_signatureType;
    private $_wormholeType;
    private $_scannedUser;
    private $_updatedUser;


    /**
     * -- DO NOT CALL DIRECTLY --
     * Use \map\controller\Signature()->storeSignature() instead!!!
     */
    function store()
    {
        $this->updateDate = date("Y-m-d H:i:s");
        if (\User::getUSER())
            $this->updateBy = \User::getUSER()->id;

        if (!$this->scanDate) {
            $this->scanDate = date("Y-m-d H:i:s");
            if (\User::getUSER())
                $this->scannedBy = \User::getUSER()->id;
        }

        parent::store();
    }

    function delete()
    {
        $this->deleted = true;
        $this->store();

        /* Als deze sig een wormhole was, verwijder die wormhole! */

        /*
         * even niet meer doen.. werkt irritant als iemand een foutje maakt!
         *
        $nameParts = explode(" ", $this->sigInfo);
        $nameParts = explode("-", $nameParts[0]);
        $sigName = (isset($nameParts[1]))?$nameParts[1]:$nameParts[0];

        foreach (\map\model\Map::findAll(["authgroupid" => $this->authGroupID]) as $map)
        {
            $wormhole = \map\model\Wormhole::findOne(["chainid" => $map->id, "name" => $sigName]);
            if ($wormhole) {
                $connection = $wormhole->getConnectionTo($this->solarSystemID);
                if ($connection) {
                    if (!$connection->normalgates)
                        $connection->delete();
                }
            }
        }
        */
    }

    /**
     * Get solar system
     * @return SolarSystem
     */
    function getSolarSystem()
    {
        if ($this->_solarsystem === null)
            $this->_solarsystem = new \map\model\SolarSystem($this->solarSystemID);

        return $this->_solarsystem;
    }

    /**
     * Get wormhole type
     * @return \map\model\SignatureType|null
     */
    function getSignatureType()
    {
        if ($this->_signatureType === null && $this->sigTypeID) {
            $this->_signatureType = \map\model\SignatureType::findById($this->sigTypeID);
        }

        return $this->_signatureType;
    }

    /**
     * Is wormhole?
     * @return bool
     */
    function isWormhole()
    {
        if ($this->getSignatureType()) {
            if ($this->getSignatureType()->name == "wh")
                return true;
        }
        return false;
    }

    /**
     * Get wormhole type
     * @return \map\model\WormholeType|null
     */
    function getWormholeType()
    {
        if ($this->_wormholeType === null) {
            if ($this->isWormhole())
                $this->_wormholeType = new \map\model\WormholeType($this->whTypeID);
        }
        return $this->_wormholeType;
    }

    /**
     * Get user die deze signature als eerste getscant heeft
     * @return \users\model\User
     */
    function getScannedByUser()
    {
        if ($this->_scannedUser === null)
            $this->_scannedUser = new \users\model\User($this->scannedBy);

        return $this->_scannedUser;
    }

    /**
     * Get user die deze signature als laatste heeft bijgewerkt
     * @return \users\model\User
     */
    function getUpdatedByUser()
    {
        if ($this->_updatedUser === null)
            $this->_updatedUser = new \users\model\User($this->updateBy);

        return $this->_updatedUser;
    }


    /**
     * find all signatures
     * @param array $conditions
     * @param array $orderby
     * @param string|null $class
     * @return \map\model\Signature[]
     */
    public static function findAll($conditions=[], $orderby=["sigid"], $class=null)
    {
        return parent::findAll($conditions, $orderby, $class);
    }

    /**
     * Find signature by wh-names
     * @param $solarSystemID
     * @param $name
     * @return \map\model\Signature|null
     */
    public static function findWormholeSigByName($solarSystemID, $authGroupID, $name)
    {
        if ($result = \MySQL::getDB()->getRow("select *
                                               from   map_signature
                                               where  solarsystemid = ? and authgroupid = ?
                                               and    siginfo like ? and deleted = 0"
                                , [$solarSystemID, $authGroupID, $name."%"]))
        {
            $sig = new \map\model\Signature();
            $sig->load($result);
            return $sig;
        }
        return null;
    }
}