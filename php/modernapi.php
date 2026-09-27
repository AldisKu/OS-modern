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

ini_set('display_errors', '0');
error_reporting(E_ALL);

header("Content-Type: application/json; charset=utf-8");

function tableColumnExists($pdo, $table, $col) {
	try {
		$sql = "SHOW COLUMNS FROM $table LIKE ?";
		$stmt = $pdo->prepare(Dbutils::substTableAlias($sql));
		$stmt->execute(array($col));
		return $stmt->rowCount() > 0;
	} catch (Exception $e) {
		return false;
	}
}

// True if a (non-aliased) table name exists in the current database.
function tableExistsRaw($pdo, $tableName) {
	try {
		$stmt = $pdo->prepare("SHOW TABLES LIKE ?");
		$stmt->execute(array($tableName));
		return $stmt->rowCount() > 0;
	} catch (Exception $e) {
		return false;
	}
}

// Creates/repairs the change-monitoring state table + triggers for the given
// (already prefixed) table names. Config-driven: the caller passes real table
// names derived from TAB_PREFIX, so nothing is hardcoded. Idempotent.
function installChangeTriggers($pdo, $state, $log, $tRecords, $tQueue, $tBill, $tProducts, $tProdnames, $tResttables) {
	$done = array();
	$exec = function($sql) use ($pdo, &$done) { $pdo->exec($sql); };
	try {
		// State table (fixed size, one row per scope).
		$exec("CREATE TABLE IF NOT EXISTS `$state` (scope VARCHAR(20) NOT NULL, last_change DATETIME(6) NOT NULL, PRIMARY KEY(scope)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
		$exec("INSERT INTO `$state` (scope,last_change) VALUES
			('orders','2000-01-01 00:00:00.000000'),('payments','2000-01-01 00:00:00.000000'),
			('moving','2000-01-01 00:00:00.000000'),('cancel','2000-01-01 00:00:00.000000'),
			('products','2000-01-01 00:00:00.000000'),('tables','2000-01-01 00:00:00.000000')
			ON DUPLICATE KEY UPDATE scope=scope");
		// Debug event log (append-only, off the hot path).
		$exec("CREATE TABLE IF NOT EXISTS `$log` (id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, scope VARCHAR(20) NOT NULL, event VARCHAR(10) NOT NULL, src_table VARCHAR(64) NOT NULL, ref_id BIGINT NULL, ts DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6), PRIMARY KEY(id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

		// Drop existing triggers (idempotent).
		$triggers = array(
			'trg_change_records_ai','trg_change_queue_ai','trg_change_queue_au','trg_change_bill_ai',
			'trg_change_products_ai','trg_change_products_au','trg_change_products_ad',
			'trg_change_prodnames_ai','trg_change_prodnames_au','trg_change_prodnames_ad',
			'trg_change_resttables_ai','trg_change_resttables_au','trg_change_resttables_ad'
		);
		foreach ($triggers as $t) { $exec("DROP TRIGGER IF EXISTS `$t`"); }

		// Helper to build a scope-update trigger body.
		$mk = function($name, $timing, $table, $scopeExpr, $event, $idcol) use ($state, $log) {
			return "CREATE TRIGGER `$name` AFTER $timing ON `$table` FOR EACH ROW BEGIN "
				. "DECLARE v_scope VARCHAR(20); SET v_scope = $scopeExpr; "
				. "UPDATE `$state` SET last_change = NOW(6) WHERE scope = v_scope; "
				. "INSERT INTO `$log` (scope,event,src_table,ref_id) VALUES (v_scope,'$event','$table',$idcol); END";
		};
		// records: action -> scope
		$recScope = "CASE WHEN NEW.action=0 THEN 'orders' WHEN NEW.action IN (1,7) THEN 'payments' WHEN NEW.action IN (5,6) THEN 'moving' ELSE 'cancel' END";
		$exec($mk('trg_change_records_ai','INSERT',$tRecords,$recScope,'insert','NEW.id'));
		// queue: any insert/update = orders activity (paid/cancel/move touch it too)
		$exec($mk('trg_change_queue_ai','INSERT',$tQueue,"'orders'",'insert','NEW.id'));
		$exec($mk('trg_change_queue_au','UPDATE',$tQueue,"'orders'",'update','NEW.id'));
		// bill: payment safety net
		$exec($mk('trg_change_bill_ai','INSERT',$tBill,"'payments'",'insert','NEW.id'));
		// products
		$exec($mk('trg_change_products_ai','INSERT',$tProducts,"'products'",'insert','NEW.id'));
		$exec($mk('trg_change_products_au','UPDATE',$tProducts,"'products'",'update','NEW.id'));
		$exec($mk('trg_change_products_ad','DELETE',$tProducts,"'products'",'delete','OLD.id'));
		// prodnames
		$exec($mk('trg_change_prodnames_ai','INSERT',$tProdnames,"'products'",'insert','NEW.id'));
		$exec($mk('trg_change_prodnames_au','UPDATE',$tProdnames,"'products'",'update','NEW.id'));
		$exec($mk('trg_change_prodnames_ad','DELETE',$tProdnames,"'products'",'delete','OLD.id'));
		// resttables
		$exec($mk('trg_change_resttables_ai','INSERT',$tResttables,"'tables'",'insert','NEW.id'));
		$exec($mk('trg_change_resttables_au','UPDATE',$tResttables,"'tables'",'update','NEW.id'));
		$exec($mk('trg_change_resttables_ad','DELETE',$tResttables,"'tables'",'delete','OLD.id'));

		return array("status" => "OK", "prefix_state_table" => $state, "triggers_on" => array($tRecords,$tQueue,$tBill,$tProducts,$tProdnames,$tResttables));
	} catch (Exception $e) {
		return array("status" => "ERROR", "msg" => $e->getMessage());
	}
}

function safeRoomsAndTables($pdo) {
	$roomsSql = "select R.id as id,roomname from %room% R
		left join %resttables% T on R.id=T.roomid
		where R.removed is null and T.active='1' and (T.removed is null or T.removed='0')
		group by T.roomid
		order by R.sorting";
	$rooms = CommonUtils::fetchSqlAll($pdo, $roomsSql);
	$roomstables = array();

	foreach ($rooms as $room) {
		$tablesSql = "select R.id as id,R.tableno as name,R.sorting as sorting,R.code as code
			from %resttables% R
			where R.removed is null and active='1' and R.roomid=?
			order by R.sorting";
		$tables = CommonUtils::fetchSqlAll($pdo, $tablesSql, array($room["id"]));

		$ids = array_map(function($t){ return $t["id"]; }, $tables);
		$stats = array();
		if (count($ids) > 0) {
			$placeholders = implode(",", array_fill(0, count($ids), "?"));
			$sql = "SELECT Q.tablenr,
				SUM(Q.price) as pricesum,
				COUNT(Q.id) as prodcount,
				SUM(CASE WHEN Q.isready='1' THEN 1 ELSE 0 END) as prodready,
				SUM(CASE WHEN Q.paidtime is null AND (Q.toremove is null OR Q.toremove='0') THEN 1 ELSE 0 END) as unpaidprodcount
				FROM %queue% Q
				LEFT JOIN %bill% B ON Q.billid=B.id
				WHERE Q.clsid IS NULL
				AND (Q.toremove is null OR Q.toremove='0')
				AND Q.tablenr IN ($placeholders)
				AND Q.billid is null
				AND (B.paymentid is null OR B.paymentid <> '8')
				GROUP BY Q.tablenr";
			$statsRows = CommonUtils::fetchSqlAll($pdo, $sql, $ids);
			foreach ($statsRows as $row) {
				$stats[intval($row["tablenr"])] = $row;
			}
		}

		foreach ($tables as &$t) {
			$tid = intval($t["id"]);
			$row = array_key_exists($tid, $stats) ? $stats[$tid] : null;
			$t["pricesum"] = $row ? $row["pricesum"] : "0.00";
			$t["unpaidprodcount"] = $row ? intval($row["unpaidprodcount"]) : 0;
			$t["prodcount"] = $row ? intval($row["prodcount"]) : 0;
			$t["prodready"] = $row ? intval($row["prodready"]) : 0;
			$t["readyQueueIds"] = array();
			$t["reservations"] = "";
		}

		$roomstables[] = array(
			"id" => $room["id"],
			"name" => $room["roomname"],
			"tables" => $tables
		);
	}

	$queue = new QueueContent();
	$takeawayunpaid = $queue->numberOfUnpaidProductsForTable($pdo, null);

	return array(
		"roomstables" => $roomstables,
		"takeawayprice" => array("pricesum" => "0.00", "prodcount" => "0"),
		"takeawayunpaidprodcount" => $takeawayunpaid,
		"takeawayprodcount" => 0,
		"takeawayprodready" => 0,
		"takeawayReadyQueueIds" => array()
	);
}

function attachTableCodes($pdo, $rooms) {
	$all = array();
	if (isset($rooms["roomstables"])) {
		foreach ($rooms["roomstables"] as $r) {
			foreach ($r["tables"] as $t) {
				$all[] = $t["id"];
			}
		}
	}
	if (count($all) == 0) return $rooms;
	$placeholders = implode(",", array_fill(0, count($all), "?"));
	$sql = "SELECT id, code FROM %resttables% WHERE id IN ($placeholders)";
	$rows = CommonUtils::fetchSqlAll($pdo, $sql, $all);
	$map = array();
	foreach ($rows as $row) {
		$map[intval($row["id"])] = $row["code"];
	}
	if (isset($rooms["roomstables"])) {
		foreach ($rooms["roomstables"] as &$r) {
			foreach ($r["tables"] as &$t) {
				$id = intval($t["id"]);
				if (array_key_exists($id, $map)) {
					$t["code"] = $map[$id];
				}
			}
		}
	}
	return $rooms;
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$logFile = "/var/log/ordersprinter/modernapi.log";
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
	return;
}

if ($cmd == "pricelevel_state") {
	$pdo = DbUtils::openDbAndReturnPdoStatic();
	$commUtils = new CommonUtils();
	$level = $commUtils->getCurrentPriceLevel($pdo);
	$id = $level["id"] ?? "";
	$name = $level["name"] ?? "";
	$version = hash("sha256", $id . "|" . $name);
	$response = array(
		"status" => "OK",
		"id" => $id,
		"name" => $name,
		"version" => $version
	);
	echo json_encode($response);
	return;
}

// Change-state poll for the broker. PURE READ: returns the fixed set of scope
// rows (one per logical scope) with their microsecond last_change timestamp
// (written by the DB triggers, see db/install-change-triggers.sql). No writes,
// no deletes, no parameters. The broker keeps a per-scope watermark and decides
// what changed. If the state table is missing (triggers not installed), fail
// soft so the broker can fall back to the legacy state poll.
if ($cmd == "changes") {
	$pdo = DbUtils::openDbAndReturnPdoStatic();
	$stateTable = TAB_PREFIX . "change_state";
	if (!tableExistsRaw($pdo, $stateTable)) {
		echo json_encode(array("status" => "ERROR", "code" => "NO_CHANGELOG", "scopes" => array()));
		return;
	}
	$rows = CommonUtils::fetchSqlAll($pdo, "SELECT scope, last_change FROM $stateTable", null);
	$scopes = array();
	foreach ($rows as $r) {
		// last_change is returned as an opaque fixed-width string
		// ("YYYY-MM-DD HH:MM:SS.ffffff"); the broker compares it as a string.
		$scopes[$r["scope"]] = $r["last_change"];
	}
	echo json_encode(array(
		"status" => "OK",
		"scopes" => $scopes
	));
	return;
}

// Install/repair the change-monitoring triggers using the app's REAL table
// prefix (TAB_PREFIX) — no hardcoded table names, so it works on any server
// regardless of prefix (os_ vs ordersprinter_ etc.). Idempotent: drops and
// recreates our triggers + state table. Requires admin rights.
if ($cmd == "install_triggers") {
	$userrights = new Userrights();
	if (!$userrights->hasCurrentUserRight('right_manager')) {
		echo json_encode(array("status" => "ERROR", "msg" => "Nur Manager/Administrator darf Trigger installieren"));
		return;
	}
	$pdo = DbUtils::openDbAndReturnPdoStatic();
	$P = TAB_PREFIX;                       // e.g. "ordersprinter_" or "os_"
	$state = $P . "change_state";
	$log   = $P . "change_log";
	$tRecords    = $P . "records";
	$tQueue      = $P . "queue";
	$tBill       = $P . "bill";
	$tProducts   = $P . "products";
	$tProdnames  = $P . "prodnames";
	$tResttables = $P . "resttables";

	$result = installChangeTriggers($pdo, $state, $log, $tRecords, $tQueue, $tBill, $tProducts, $tProdnames, $tResttables);
	echo json_encode($result);
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
	$clientPollMs = 120000;
	$cfgFile = __DIR__ . '/../modern/config.json';
	if (file_exists($cfgFile)) {
		$rawCfg = file_get_contents($cfgFile);
		$cfg = json_decode($rawCfg, true);
		if (is_array($cfg) && isset($cfg['client_poll_interval_ms'])) {
			$clientPollMs = intval($cfg['client_poll_interval_ms']);
		}
	}
	$response = array(
		"status" => "OK",
		"server_ip" => $serverAddr,
		"host" => $host,
		"broker_ws" => $brokerWs,
		"broker_http" => $brokerHttp,
		"client_poll_interval_ms" => $clientPollMs
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

if ($cmd == "users") {
	$admin = new Admin();
	$response = captureJson(function() use ($admin) {
		$admin->getUserList();
	});
	echo json_encode($response);
	logApi($cmd, $requestForLog, $response);
	return;
}

if (!requireLogin()) {
	return;
}

switch ($cmd) {
	case "menu_items":
		header("Cache-Control: public, max-age=86400");
		header_remove("Pragma");
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
		if (!is_array($config)) {
			$config = array(
				"discount1" => CommonUtils::getConfigValue($pdo, "discount1", 0),
				"discount2" => CommonUtils::getConfigValue($pdo, "discount2", 0),
				"discount3" => CommonUtils::getConfigValue($pdo, "discount3", 0),
				"discountname1" => CommonUtils::getConfigValue($pdo, "discountname1", ""),
				"discountname2" => CommonUtils::getConfigValue($pdo, "discountname2", ""),
				"discountname3" => CommonUtils::getConfigValue($pdo, "discountname3", ""),
				"cancelunpaidcode" => CommonUtils::getConfigValue($pdo, "cancelunpaidcode", ""),
				"showpayments" => CommonUtils::getConfigValue($pdo, "showpayments", 1),
				"showpayment2" => CommonUtils::getConfigValue($pdo, "showpayment2", 1),
				"showpayment3" => CommonUtils::getConfigValue($pdo, "showpayment3", 1),
				"showpayment4" => CommonUtils::getConfigValue($pdo, "showpayment4", 0),
				"showpayment5" => CommonUtils::getConfigValue($pdo, "showpayment5", 0),
				"showpayment6" => CommonUtils::getConfigValue($pdo, "showpayment6", 0),
				"showpayment7" => CommonUtils::getConfigValue($pdo, "showpayment7", 0),
				"showpayment8" => CommonUtils::getConfigValue($pdo, "showpayment8", 0)
			);
		}
		$userprefs = array(
			"preferimgmobile" => Admin::getUserValueAllowNull("preferimgmobile")
		);
		$menu = captureJson(function() use ($products) {
			$products->handleCommand("getAllTypesAndAvailProds");
		});
		$hasPayAll = tableColumnExists($pdo, "%roles%", "right_payallorders");
		if ($hasPayAll) {
			$rooms = captureJson(function() use ($roomtables) {
				$roomtables->showAllRooms();
			});
			$rooms = attachTableCodes($pdo, $rooms);
		} else {
			$rooms = safeRoomsAndTables($pdo);
		}

		$response = array(
			"status" => "OK",
			"user" => sessionUserInfo(),
			"config" => $config,
			"userprefs" => $userprefs,
			"menu" => $menu,
			"rooms" => $rooms
		);
		echo json_encode($response);
		logApi($cmd, $requestForLog, $response);
		return;

	case "refresh_tables":
		$roomtables = new Roomtables();
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$hasPayAll = tableColumnExists($pdo, "%roles%", "right_payallorders");
		if ($hasPayAll) {
			$rooms = captureJson(function() use ($roomtables) {
				$roomtables->showAllRooms();
			});
			$rooms = attachTableCodes($pdo, $rooms);
			$response = array("status" => "OK", "rooms" => $rooms);
		} else {
			$response = array("status" => "OK", "rooms" => safeRoomsAndTables($pdo));
		}
		echo json_encode($response);
		logApi($cmd, $requestForLog, $response);
		return;
	case "log_client":
		$level = $requestForLog["level"] ?? "INFO";
		$msg = $requestForLog["msg"] ?? "";
		$response = array("status" => "OK");
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
			// Distinguish between expired session and genuinely missing rights
			if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
				$response = array("status" => "ERROR", "code" => ERROR_NOT_AUTHOTRIZED, "msg" => ERROR_NOT_AUTHOTRIZED_MSG);
			} else {
				$response = array("status" => "ERROR", "msg" => "Benutzerrechte nicht ausreichend!");
			}
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
		// ToGo has no real table row, so its id must be stored as NULL in
		// os_records.tableid. Without this, moving an order FROM ToGo to a
		// table inserts tableid=0 and violates the os_records_ibfk_2 foreign
		// key (SQLSTATE 23000 / 1452). The $fromIsTogo flag above is already
		// captured for the togo=0 update below, so nulling here is safe.
		if ($fromIsTogo) {
			$fromTableId = null;
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
					// Moving FROM ToGo TO a real table: a ToGo order has
					// tablenr=NULL in the queue, so QueueContent::changeTable's
					// "UPDATE ... SET tablenr=? WHERE tablenr=?" never matches
					// (NULL comparison fails) and the target table is not set,
					// leaving the order orphaned (togo=0, tablenr=NULL).
					// Set both togo=0 AND the target tablenr explicitly here.
					$sql = "UPDATE %queue% SET togo=0, tablenr=? WHERE id IN($placeholders)";
					CommonUtils::execSql($pdo, $sql, array_merge(array($toTableId), $ids));
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
		ob_start();
		$response = $queue->removeProductFromQueue($queueid, $isPaid, $isCooking, $isReady);
		ob_end_clean();
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
