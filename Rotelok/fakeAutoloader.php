<?php
require_once __DIR__ . "/xhprof/iXHProfRuns.php";
require_once __DIR__ . "/xhprof/XHProfRuns_Default.php";
require_once __DIR__ . "/xhprof/XHProfLib.php";
require_once __DIR__ . "/xhprof/XHProfHTML.php";
require_once __DIR__ . "/xhprof/Database/Abstraction.php";
require_once __DIR__ . "/xhprof/Database/DriverMysqli.php";
require_once __DIR__ . "/xhprof/Database/DriverPdo.php";
require_once __DIR__ . "/../xhprof_lib/config.php";
require __DIR__ . "/../xhprof_lib/utils/common.php";

$XHProfLib =  new \Rotelok\xhprof\XHProfLib();
$XHProfHTML = new \Rotelok\xhprof\XHProfHTML();
