<?php
namespace esi;

class Module extends \Module
{
    public $moduleName = "esi";
    public $moduleTitle = "esi";

    /**
     * Mag de user deze module zien?
     * @param array $arguments
     * @return bool
     */
    function isAuthorized($arguments=[])
    {
        return true;
    }

    function doMaintenance()
    {
        // Oude fleets opruimen
        \AppRoot::doCliOutput("Clean up old fleets");
        $cleanupDate = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-2,date("Y")));
        \MySQL::getDB()->doQuery("delete from esi_fleet where active = 0 and (lastupdate < ? or lastupdate is null)", [$cleanupDate]);

        // Log opruimen
        \AppRoot::doCliOutput("Clean up CREST log");
        $cleanupDate = date("Y-m-d H:i:s", mktime(date("H"),date("i"),date("s"),date("m"),date("d")-14,date("Y")));
        \MySQL::getDB()->doQuery("delete from esi_log where requestdate < ?", [$cleanupDate]);
    }
}