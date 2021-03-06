<?php
namespace map;

class Module extends \Module
{
    public $moduleName = "map";
    public $moduleTitle = "Map";

    function getContent()
    {
        \AppRoot::debug("Module(".$this->moduleName.")->getContent()");
        $arguments = [];
        foreach (explode(",", \Tools::REQUEST("arguments")) as $arg) {
            if (strlen(trim($arg)) > 0)
                $arguments[] = $arg;
        }

        $map = null;
        if (\Tools::REQUEST("section")) {
            $map = \map\model\Map::findByURL(\Tools::REQUEST("section"));
            if ($map) {
                $_GET["chainid"] = $map->id;
                $view = new \map\view\Map();
                $action = (count($arguments)>0)?array_shift($arguments):null;
                $method = ($action) ? "get".ucfirst($action) : "Overview";
                if (!method_exists($view, $method)) {
                    $method = "getOverview";
                    if ($action)
                        array_unshift($arguments, $action);
                }
                return $view->$method($map, $arguments);
            }
            $view = parent::getView();
            if ($view)
                return $view;
        }

        // Geen map gekozen. Pak eerste map
        \AppRoot::debug("No map found, select first available map");
        if (count(\User::getUSER()->getAvailibleChains()) > 0) {
            foreach (\User::getUSER()->getAvailibleChains() as $chain) {
                \AppRoot::redirect("map/".$chain->getURL());
            }
        }

        // Geen map gevonden..
        $tpl = \SmartyTools::getSmarty();
        return $tpl->fetch("map/map/nomap");
    }

    function doMaintenance()
    {
        $console = new \map\console\Map();
        $console->cleanupSignatures();
        $console->cleanupWormholes();
        return true;
    }
}