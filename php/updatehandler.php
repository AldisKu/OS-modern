<?php

require_once ('dbutils.php');
require_once ('commonutils.php');
require_once ('updater.php');

$command = $_GET["command"];

$updater = new Updater();
$updater->handleCommand($command);
