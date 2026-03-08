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
require_once ('workreceipts.php');
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

$logFile = "/tmp/modernapi.log";
function redactSensitive($data) {
	if (!is_array($data)) {
		return $data;
	}
	$redactKeys = array("password", "pass", "passwd", "pin");
	$clean = array();
	foreach ($data as $k => $v) {
		$lk = strtolower($k);
		if (in_array($lk, $redactKeys)) {
			$clean[$k] = "***";
		} else if (is_array($v)) {
			$clean[$k] = redactSensitive($v);
		} else {
			$clean[$k] = $v;
		}
	}
	return $clean;
}
function logApi($cmd, $request, $response) {
	global $logFile;
	$entry = array(
		"ts" => date('c'),
		"ip" => $_SERVER['REMOTE_ADDR'] ?? "",
		"cmd" => $cmd,
		"request" => redactSensitive($request),
		"response" => $response
	);
	@file_put_contents($logFile, json_encode($entry) . "\n", FILE_APPEND);
}

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
$requestForLog = !empty($input) ? $input : (count($_POST) ? $_POST : $_GET);

if ($cmd == "login") {
	$admin = new Admin();
	$userid = $_POST["userid"] ?? "";
	$password = $_POST["password"] ?? "";
	$modus = $_POST["modus"] ?? 0;
	$time = $_POST["time"] ?? time();
	$response = captureJson(function() use ($admin, $userid, $password, $modus, $time) {
		$admin->tryAuthenticate($userid, $password, $modus, $time);
	});
	echo json_encode($response);
	logApi($cmd, $requestForLog, $response);
	return;
}

if ($cmd == "logout") {
	if (session_id() == '') {
		session_start();
	}
	session_destroy();
	$response = array("status" => "OK");
	echo json_encode($response);
	logApi($cmd, $requestForLog, $response);
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
	$response = array("status" => "OK", "loggedIn" => $isLoggedIn, "user" => $user);
	echo json_encode($response);
	logApi($cmd, $requestForLog, $response);
	return;
}

if ($cmd == "state") {
	$pdo = DbUtils::openDbAndReturnPdoStatic();
	$queue = CommonUtils::fetchSqlAll($pdo, "SELECT MAX(id) as maxid, MAX(ordertime) as maxorder FROM %queue%", null);
	$bill = CommonUtils::fetchSqlAll($pdo, "SELECT MAX(id) as maxid, MAX(billdate) as maxdate FROM %bill%", null);
	$records = CommonUtils::fetchSqlAll($pdo, "SELECT MAX(id) as maxid, MAX(date) as maxdate FROM %records%", null);
	$queueMaxId = $queue[0]["maxid"] ?? 0;
	$queueMaxOrder = $queue[0]["maxorder"] ?? '';
	$billMaxId = $bill[0]["maxid"] ?? 0;
	$billMaxDate = $bill[0]["maxdate"] ?? '';
	$recMaxId = $records[0]["maxid"] ?? 0;
	$recMaxDate = $records[0]["maxdate"] ?? '';

	$version = hash("sha256", $queueMaxId . "|" . $queueMaxOrder . "|" . $billMaxId . "|" . $billMaxDate . "|" . $recMaxId . "|" . $recMaxDate);
	$response = array(
		"status" => "OK",
		"version" => $version
	);
	echo json_encode($response);
	logApi($cmd, $requestForLog, $response);
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
	$response = array(
		"status" => "OK",
		"server_ip" => $serverAddr,
		"host" => $host,
		"broker_ws" => $brokerWs,
		"broker_http" => $brokerHttp
	);
	echo json_encode($response);
	logApi($cmd, $requestForLog, $response);
	return;
}

if ($cmd == "printer_status") {
	$admin = new Admin();
	$response = captureJson(function() use ($admin) {
		$admin->isPrinterServerActive();
	});
	echo json_encode($response);
	logApi($cmd, $requestForLog, $response);
	return;
}

if (!requireLogin()) {
	return;
}

switch ($cmd) {
	case "users":
		$admin = new Admin();
		$response = captureJson(function() use ($admin) {
			$admin->getUserList();
		});
		echo json_encode($response);
		logApi($cmd, $requestForLog, $response);
		return;
	case "menu_items":
		$admin = new Admin();
		$response = captureJson(function() use ($admin) {
			$admin->getJsonMenuItemsAndVersion();
		});
		echo json_encode($response);
		logApi($cmd, $requestForLog, $response);
		return;
	case "payments":
		$admin = new Admin();
		$response = captureJson(function() use ($admin) {
			$admin->getPayments();
		});
		echo json_encode($response);
		logApi($cmd, $requestForLog, $response);
		return;
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

		$response = array(
			"status" => "OK",
			"user" => sessionUserInfo(),
			"config" => $config,
			"menu" => $menu,
			"rooms" => $rooms
		);
		echo json_encode($response);
		logApi($cmd, $requestForLog, $response);
		return;

	case "refresh_tables":
		$roomtables = new Roomtables();
		$response = array("status" => "OK", "rooms" => captureJson(function() use ($roomtables) {
			$roomtables->showAllRooms();
		}));
		echo json_encode($response);
		logApi($cmd, $requestForLog, $response);
		return;

	case "refresh_menu":
		$products = new Products();
		$response = array("status" => "OK", "menu" => captureJson(function() use ($products) {
			$products->handleCommand("getAllTypesAndAvailProds");
		}));
		echo json_encode($response);
		logApi($cmd, $requestForLog, $response);
		return;

	case "order":
		$userrights = new Userrights();
		if (!$userrights->hasCurrentUserRight('right_waiter')) {
			$response = array("status" => "ERROR","msg" => "Benutzerrechte nicht ausreichend!");
			echo json_encode($response);
			logApi($cmd, $requestForLog, $response);
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
		logApi($cmd, $requestForLog, $result);
		return;

	case "paydesk_items":
		$queue = new QueueContent();
		$_GET["tableid"] = $_POST["tableid"] ?? 0;
		$response = captureJson(function() use ($queue) {
			$queue->handleCommand("getJsonProductsOfTableToPay");
		});
		echo json_encode($response);
		logApi($cmd, $requestForLog, $response);
		return;

	case "paydesk_pay":
		$queue = new QueueContent();
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$ids = $_POST["ids"] ?? "";
		$tableid = $_POST["tableid"] ?? 0;
		$paymentid = $_POST["paymentid"] ?? 1;
		$declareready = $_POST["declareready"] ?? 0;
		$host = $_POST["host"] ?? 0;
		$reservationid = $_POST["reservationid"] ?? "";
		$guestinfo = $_POST["guestinfo"] ?? "";
		$intguestid = $_POST["intguestid"] ?? "";
		$tip = $_POST["tip"] ?? null;
		$camefromordering = $_POST["camefromordering"] ?? 0;
		$response = captureJson(function() use ($queue, $pdo, $ids, $tableid, $paymentid, $declareready, $host, $reservationid, $guestinfo, $intguestid, $tip, $camefromordering) {
			$queue->declarePaidCreateBillReturnBillId($pdo,$ids,$tableid,$paymentid,$declareready,$host,QueueContent::$INTERNAL_CALL_NO,$reservationid,$guestinfo,$intguestid,null,null,$tip,$camefromordering);
		});
		echo json_encode($response);
		logApi($cmd, $requestForLog, $response);
		return;

	case "change_table":
		$queue = new QueueContent();
		$fromTableId = $_POST["fromTableId"] ?? 0;
		$toTableId = $_POST["toTableId"] ?? 0;
		$fromIsTogo = ($fromTableId === 0 || $fromTableId === "0");
		$toIsTogo = ($toTableId === 0 || $toTableId === "0");
		if ($toIsTogo) {
			$toTableId = null;
		}
		$queueids = $_POST["queueids"] ?? "";
		$response = captureJson(function() use ($queue, $fromTableId, $toTableId, $queueids) {
			$queue->changeTable($fromTableId, $toTableId, $queueids);
		});
		if (!empty($queueids)) {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$ids = explode(",", $queueids);
			$ids = array_filter($ids, 'strlen');
			if (count($ids) > 0) {
				$placeholders = implode(",", array_fill(0, count($ids), "?"));
				if ($toIsTogo) {
					$sql = "UPDATE %queue% SET togo=1 WHERE id IN($placeholders)";
					CommonUtils::execSql($pdo, $sql, $ids);
				} else if ($fromIsTogo) {
					$sql = "UPDATE %queue% SET togo=0 WHERE id IN($placeholders)";
					CommonUtils::execSql($pdo, $sql, $ids);
				}
			}
		}
		echo json_encode($response);
		logApi($cmd, $requestForLog, $response);
		return;

	case "table_open_items":
		$queue = new QueueContent();
		$_GET["tableId"] = $_POST["tableid"] ?? 0;
		$response = captureJson(function() use ($queue) {
			$queue->handleCommand("getProdsForTableChange");
		});
		echo json_encode($response);
		logApi($cmd, $requestForLog, $response);
		return;
	case "table_notdelivered":
		$queue = new QueueContent();
		$_GET["tableid"] = $_POST["tableid"] ?? 0;
		$response = captureJson(function() use ($queue) {
			$queue->handleCommand("getJsonLongNamesOfProdsForTableNotDelivered");
		});
		echo json_encode($response);
		logApi($cmd, $requestForLog, $response);
		return;
	case "remove_product":
		$queue = new QueueContent();
		$queueid = $_POST["queueid"] ?? 0;
		$isPaid = $_POST["isPaid"] ?? 0;
		$isCooking = $_POST["isCooking"] ?? 0;
		$isReady = $_POST["isReady"] ?? 0;
		$response = $queue->removeProductFromQueue($queueid, $isPaid, $isCooking, $isReady);
		echo json_encode($response);
		logApi($cmd, $requestForLog, $response);
		return;

	case "table_records":
		$queue = new QueueContent();
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$tableid = $_POST["tableid"] ?? 0;
		$response = captureJson(function() use ($queue, $pdo, $tableid) {
			$queue->getRecords($pdo, $tableid);
		});
		echo json_encode($response);
		logApi($cmd, $requestForLog, $response);
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
