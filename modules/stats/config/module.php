<?php
// DEFAULT CONFIGURATION
$config = array();
$config["name"] = "Statistics";
$config["url"] = "stats/leaderboard";
$config["public"] = true;
$config["enabled"] = (\User::getUSER() && \User::getUSER()->isAuthorized()) ? true : false;

$config["submenu"][] = [
    "type" => "link",
    "name" => "Leaderboard",
    "url" => "stats/leaderboard"
];

if (\User::getUSER() && \User::getUSER()->isAdmin())
{
	$config["submenu"][] = [
        "type" => "link",
        "name"	=> "Statistics",
        "url" => "stats/statistics"
    ];
}

// SET CONFIG
foreach ($config as $var => $val) {
	\AppRoot::config("stats".$var, $val);
}
?>