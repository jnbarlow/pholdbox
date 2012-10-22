<?php

require("config/config.php");
require("RCProcessor.php");
require("PholdBoxBaseObj.php");
require("Event.php");
require("Model.php");
require("PhORM.php");

//look for site specific configs and merge them.
if(isset($SYSTEM[$_SERVER["SERVER_NAME"]]))
{
	$SYSTEM = array_merge($SYSTEM, $SYSTEM[$_SERVER["SERVER_NAME"]]);
}

session_start();

$VERSION = "1.0 beta";

$RCProcessor = new system\RCProcessor();
$GLOBALS["rc"] = $RCProcessor->getCollection();
$GLOBALS["rc"]["PB_VERSION"] = $VERSION;

$SYSTEM["debugger"]["startTime"] = microtime(true);
$SYSTEM["debugger"]["showDebugger"] = true;
$event = new system\Event($SYSTEM["default_layout"]);
if(array_key_exists("event", $GLOBALS["rc"]))
{
	$event->processEvent($GLOBALS["rc"]["event"]);
}
else
{	
	$event->processEvent($SYSTEM["default_event"]);
}
$SYSTEM["debugger"]["endTime"] = microtime(true);
if($SYSTEM["debugger"]["showDebugger"])
{
	$event->renderDebugger();
}
?>
