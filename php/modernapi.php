<?php
require_once ('dbutils.php');
require_once ('commonutils.php');
require_once ('admin.php');
require_once ('queuecontent.php');
require_once ('products.php');
require_once ('roomtables.php');
require_once ('ordersmanagement.php');
require_once ('bill.php');
require_once ('printqueue.php');
require_once ('hotelinterface.php');
require_once ('rksv.php');
require_once ('utilities/userrights.php');
require_once ('utilities/permissions.php');
require_once ('utilities/orders.php');
require_once ('utilities/servercom.php');
require_once ('utilities/tse.php');
require_once ('utilities/signat.php');
require_once ('utilities/operations.php');
require_once ('utilities/layouter.php');
require_once ('utilities/terminals.php');
require_once ('utilities/vouchers.php');
require_once ('utilities/preview.php');
require_once ('utilities/performance.php');
require_once ('utilities/usedfeatures.php');
require_once ('utilities/paymentinfo.php');
require_once ('utilities/fiskalysignresponse.php');
require_once ('utilities/Emailer.php');
require_once ('utilities/HistFiller.php');
require_once ('tasks.php');

header("Content-Type: application/json; charset=utf-8");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$cmd = "";
if (isset($_GET["cmd"])) {
	$cmd = $_GET["cmd"];
} else if (isset($_POST["cmd"])) {
	$cmd = $_POST["cmd"];
}

$input = readJsonInput();
if (!empty($input)) {
	foreach ($input as $key => $val) {
		$_POST[$key] = $val;
	}
	if ($cmd == "" && isset($input["cmd"])) {
		$cmd = $input["cmd"];
	}
}

if ($cmd == "login") {
	$admin = new Admin();
	$userid = $_POST["userid"] ?? "";
	$password = $_POST["password"] ?? "";
	$modus = $_POST["modus"] ?? 0;
	$time = $_POST["time"] ?? time();
	$admin->tryAuthenticate($userid, $password, $modus, $time);
	return;
}

if ($cmd == "logout") {
	if (session_id() == '') {
		session_start();
	}
	session_destroy();
	echo json_encode(array("status" => "OK"));
	return;
}

if ($cmd == "session") {
	if (session_id() == '') {
		session_start();
	}
	$isLoggedIn = (isset($_SESSION['angemeldet']) && $_SESSION['angemeldet']);
	$user = null;
	if ($isLoggedIn) {
		$user = sessionUserInfo();
	}
	echo json_encode(array("status" => "OK", "loggedIn" => $isLoggedIn, "user" => $user));
	return;
}

if ($cmd == "config") {
	$serverAddr = $_SERVER['SERVER_ADDR'] ?? '127.0.0.1';
	$host = $_SERVER['HTTP_HOST'] ?? $serverAddr;
	$host = preg_replace('/:\\d+$/', '', $host);
	$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	$wsScheme = ($scheme === 'https') ? 'wss' : 'ws';
	$brokerWs = $wsScheme . '://' . $host . ':3077';
	$brokerHttp = 'http://' . $host . ':3077/event';
	echo json_encode(array(
		"status" => "OK",
		"server_ip" => $serverAddr,
		"host" => $host,
		"broker_ws" => $brokerWs,
		"broker_http" => $brokerHttp
	));
	return;
}

if (!requireLogin()) {
	return;
}

switch ($cmd) {
	case "bootstrap":
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$admin = new Admin();
		$products = new Products();
		$roomtables = new Roomtables();

		$config = captureJson(function() use ($admin, $pdo) {
			$admin->getGeneralConfigItems(false, $pdo, false);
		});
		$menu = captureJson(function() use ($products) {
			$products->handleCommand("getAllTypesAndAvailProds");
		});
		$rooms = captureJson(function() use ($roomtables) {
			$roomtables->showAllRooms();
		});

		echo json_encode(array(
			"status" => "OK",
			"user" => sessionUserInfo(),
			"config" => $config,
			"menu" => $menu,
			"rooms" => $rooms
		));
		return;

	case "refresh_tables":
		$roomtables = new Roomtables();
		echo json_encode(array("status" => "OK", "rooms" => captureJson(function() use ($roomtables) {
			$roomtables->showAllRooms();
		})));
		return;

	case "refresh_menu":
		$products = new Products();
		echo json_encode(array("status" => "OK", "menu" => captureJson(function() use ($products) {
			$products->handleCommand("getAllTypesAndAvailProds");
		})));
		return;

	case "order":
		$userrights = new Userrights();
		if (!$userrights->hasCurrentUserRight('right_waiter')) {
			echo json_encode(array("status" => "ERROR","msg" => "Benutzerrechte nicht ausreichend!"));
			return;
		}
		$queue = new QueueContent();
		$_POST["tableid"] = $_POST["tableid"] ?? 0;
		$_POST["prods"] = $_POST["prods"] ?? array();
		$_POST["print"] = $_POST["print"] ?? 0;
		$_POST["payprinttype"] = $_POST["payprinttype"] ?? "s";
		$_POST["orderoption"] = $_POST["orderoption"] ?? "";
		$result = captureJson(function() use ($queue) {
			$queue->handleCommand("addProductListToQueue");
		});
		echo json_encode($result);
		return;

	case "table_open_items":
		$queue = new QueueContent();
		$_GET["tableId"] = $_POST["tableid"] ?? 0;
		echo json_encode(captureJson(function() use ($queue) {
			$queue->handleCommand("getProdsForTableChange");
		}));
		return;

	case "table_records":
		$queue = new QueueContent();
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$tableid = $_POST["tableid"] ?? 0;
		echo json_encode(captureJson(function() use ($queue, $pdo, $tableid) {
			$queue->getRecords($pdo, $tableid);
		}));
		return;

	default:
		echo json_encode(array("status" => "ERROR", "msg" => "Unknown cmd"));
		return;
}

function readJsonInput() {
	$raw = file_get_contents("php://input");
	if (!$raw) {
		return array();
	}
	$decoded = json_decode($raw, true);
	if (is_array($decoded)) {
		return $decoded;
	}
	return array();
}

function requireLogin() {
	if (session_id() == '') {
		session_start();
	}
	if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
		echo json_encode(array("status" => "ERROR", "code" => ERROR_NOT_AUTHOTRIZED, "msg" => ERROR_NOT_AUTHOTRIZED_MSG));
		return false;
	}
	return true;
}

function captureJson($fn) {
	ob_start();
	$fn();
	$out = trim(ob_get_clean());
	$decoded = json_decode($out, true);
	if (is_null($decoded)) {
		return $out;
	}
	return $decoded;
}

function sessionUserInfo() {
	if (session_id() == '') {
		session_start();
	}
	$rights = array(
		"right_waiter" => $_SESSION['right_waiter'] ?? false,
		"right_paydesk" => $_SESSION['right_paydesk'] ?? false,
		"right_kitchen" => $_SESSION['right_kitchen'] ?? false,
		"right_bar" => $_SESSION['right_bar'] ?? false,
		"right_supply" => $_SESSION['right_supply'] ?? false,
		"right_manager" => $_SESSION['right_manager'] ?? false,
		"is_admin" => $_SESSION['is_admin'] ?? false
	);
	return array(
		"id" => $_SESSION['userid'] ?? null,
		"name" => $_SESSION['currentuser'] ?? null,
		"language" => $_SESSION['language'] ?? 0,
		"rights" => $rights
	);
}

// Plugin loading intentionally disabled here to avoid core plugin dispatch errors.
