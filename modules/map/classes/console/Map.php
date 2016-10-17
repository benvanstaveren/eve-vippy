<?php
namespace map\console;

class Map
{
    /**
     * Oude signatures opruimen.
     * @return bool
     */
    function cleanupSignatures()
    {
        $cleanupDate = date("Y-m-d H:i:s", mktime(date("H")-1,date("i"),date("s"),date("m"),date("d")-14,date("Y")));
        \AppRoot::doCliOutput("Cleanup signatures older then ".$cleanupDate);
        if ($results = \MySQL::getDB()->getRows("select *
                                                 from   map_signature
                                                 where  deleted = 0
                                                 and    updatedate < ?
                                                 and    sigtypeid not in (
                                                   select id from map_signature_type where name in ('pos','citadel')
                                                 )"
                                    , array($cleanupDate)))
        {
            \AppRoot::doCliOutput(" - ".count($results)." signatures to clean up");
            foreach ($results as $result)
            {
                $signature = new \map\model\Signature();
                $signature->load($result);
                if ($signature->getSignatureType()->mayCleanup())
                    $signature->delete();
            }
        }

        $cleanupDate = date("Y-m-d H:i:s", mktime(0,0,0,date("m")-2,date("d"),date("Y")));
        \MySQL::getDB()->doQuery("DELETE FROM map_signature
                                  WHERE updatedate < ? AND deleted > 0
                                  and sigtypeid not in (select id from map_signature_type where name in ('pos','citadel'))"
                            , array($cleanupDate));
        return true;
    }

    /**
     * Oude wormholes opruimen.
     * @return bool
     */
    function cleanupWormholes()
    {
        $cleanupDate = date("Y-m-d H:i:s", mktime(date("H")-1,date("i"),date("s"),date("m"),date("d")-2,date("Y")));
        \AppRoot::doCliOutput("Cleanup wormholes older then ".$cleanupDate);
        if ($results = \MySQL::getDB()->getRows("SELECT * FROM mapwormholes WHERE adddate < ?", array($cleanupDate)))
        {
            \AppRoot::doCliOutput(" - ".count($results)." wormholes to clean up");
            foreach ($results as $result)
            {
                $wormhole = new \map\model\Wormhole();
                $wormhole->load($result);
                if (!$wormhole->isPermenant())
                    $wormhole->delete();
            }
        }

        // Oude connections opruimen
        \AppRoot::doCliOutput("Cleanup connections with missing wormholes");
        if ($results = \MySQL::getDB()->getRows("select  *
                                                from    mapwormholeconnections
                                                where   fromwormholeid not in (select id from mapwormholes)
                                                or      towormholeid not in (select id from mapwormholes)"))
        {
            \AppRoot::doCliOutput(" - ".count($results)." connections to clean up");
            foreach ($results as $result) {
                $connection = new \scanning\model\Connection();
                $connection->load($result);
                $connection->delete();
            }
        }

        return true;
    }

    function cleanupCache()
    {
        \Tools::deleteDir("documents/cache");
        \Tools::deleteDir("documents/statistics");

        \MySQL::getDB()->doQuery("truncate mapnrofjumps");
        \MySQL::getDB()->doQuery("delete from map_character_locations where lastdate < ?", [date("Y-m-d", mktime(0,0,0,date("m"),date("d")-1,date("Y")))]);
        \MYSQL::getDB()->doQuery("delete from mapwormholejumplog where jumptime < ?", [date("Y-m-d", mktime(0,0,0,date("m")-6,date("d"),date("Y")))]);

        return true;
    }
}