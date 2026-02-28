<?php

require_once ('dbutils.php');
require_once ('admin.php');
require_once ('queuecontent.php');
require_once ('products.php');
require_once ('roomtables.php');
require_once ('reports.php');
require_once ('bill.php');
require_once ('vouchermanager.php');
require_once ('rksv.php');
require_once ('closing.php');
require_once ('printqueue.php');
require_once ('feedback.php');
require_once ('reservation.php');
require_once ('rating.php');
require_once ('customers.php');
require_once ('commonutils.php');
require_once ('updater.php');
require_once ('workreceipts.php');
require_once ('ordersmanagement.php');
require_once ('utilities/Logger.php');
require_once ('utilities/roles.php');
require_once ('utilities/permissions.php');
require_once ('utilities/basetemplater.php');
require_once ('utilities/clostemplater.php');
require_once ('utilities/demodata.php');
require_once ('utilities/servercom.php');
require_once ('utilities/tse.php');
require_once ('utilities/signat.php');
require_once ('utilities/operations.php');
require_once ('utilities/layouter.php');
require_once ('utilities/terminals.php');
require_once ('utilities/vouchers.php');
require_once ('utilities/orders.php');
require_once ('utilities/preview.php');
require_once ('utilities/performance.php');
require_once ('utilities/usedfeatures.php');
require_once ('utilities/paymentinfo.php');
require_once ('utilities/fiskalysignresponse.php');
require_once ('guestsync.php');
require_once ('timetracking.php');
require_once ('tasks.php');
require_once ('pickup.php');

$module = $_GET["module"];
$command = $_GET["command"];

Logger::logcmd($module,$command,"");

$plugins = havePlugins();

if(session_id() == '') {
	ini_set('session.gc_maxlifetime',65535);
	session_set_cookie_params(65535);
}

			
if (defined('IS_INSTALLMODE')) {
	$pdo = DbUtils::openDbAndReturnPdoStatic();
	$memlimit = CommonUtils::getConfigValue($pdo, "memorylimit", '256');
	if ($memlimit != "-1") {
		$memlimit = $memlimit . 'M';
	}
	ini_set('memory_limit',$memlimit);
	$pdo = null;
}

defined('ISDEMO') || define ('ISDEMO', false);
if ($module == 'admin') {
	$adminModule = new Admin();
	$adminModule->handleCommand($command);
} else if ($module == 'queue') {
	$queueContent = new QueueContent();
	$queueContent->handleCommand($command);
} else if ($module == 'products') {
	$products = new Products();
	$products->handleCommand($command);
} else if ($module == 'roomtables') {
	$roomtables = new Roomtables();
	$roomtables->handleCommand($command);
} else if ($module == 'reports') {
	$reports = new Reports();
	$reports->handleCommand($command);
} else if ($module == 'bill') {
	$reports = new Bill();
	$reports->handleCommand($command);
} else if ($module == 'closing') {
	$closingModule = new Closing();
	$closingModule->handleCommand($command);
} else if ($module == 'printqueue') {
	$printQueue = new PrintQueue();
	$printQueue->handleCommand($command);
} else if ($module == 'feedback') {
	$feedback = new Feedback();
	$feedback->handleCommand($command);
} else if ($module == 'reservation') {
	$reservation = new Reservation();
	$reservation->handleCommand($command);
} else if ($module == 'rating') {
	$rating = new Rating();
	$rating->handleCommand($command);
} else if ($module == 'customers') {
	$rating = new Customers();
	$rating->handleCommand($command);
} else if ($module == 'updater') {
	$updater = new Updater();
	$updater->handleCommand($command);
} else if ($module == 'guestsync') {
	Guestsync::handleCommand($command);
} else if ($module == 'timetracking') {
	Timetracking::handleCommand($command);
} else if ($module == 'tasks') {
	Tasks::handleCommand($command);
} else if ($module == 'pickup') {
	Pickup::handleCommand($command);
} else if ($module == 'demodata') {
	Demodata::handleCommand($command);
} else if ($module == 'tse') {
	Tse::handleCommand($command);
} else if ($module == 'signat') {
	SignAT::handleCommand($command);
} else if ($module == 'vouchers') {
	Vouchermanager::handleCommand($command);
} else if ($module == 'orders') {
        OrdersManagement::handleCommand($command);
} else if ($module == 'preview') {
        Preview::handleCommand($command);
}


function havePlugins() {
    if (file_exists("../plugins")) {
	if (file_exists("../plugins/config.json")) {
		$content = file_get_contents("../plugins/config.json");
	
		$pluginconfig = json_decode($content);

		$props = get_object_vars($pluginconfig);
		$keys = array_keys($props);
		foreach ($keys as $aKey) {
			$aPluginConfig = $pluginconfig->$aKey;
			$className = $aPluginConfig->PluginClass;
			if (file_exists("../plugins/$className.php")) {
				require_once "../plugins/$className.php";
			} else {
				error_log("Plugin class file not found: ../plugins/$className.php");
			}
		}
		CommonUtils::setPluginConfig($pluginconfig);
		return $pluginconfig;
	    
	}
    }
    return null;
}