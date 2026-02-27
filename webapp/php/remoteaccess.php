<?php
require_once ('dbutils.php');
require_once ('admin.php');
require_once ('roomtables.php');
require_once ('globals.php');
require_once ('reports.php');



class RemoteAccess {
	
	static public function getOpenTables($admin,$pdo) {
		$sql = "SELECT id FROM %room% WHERE removed is null";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		$result = $stmt->fetchAll();
		
		$noOfOpenTables = 0;
		$priceOfOpenTables = 0.0;
		foreach($result as $room) {
			$res = Roomtables::getUnpaidTablesCore($pdo, $room['id']);
			$noOfOpenTables += count($res);
			foreach ($res as $table) {
				$priceOfOpenTables += $table["pricesum"];
			}
		}
		echo json_encode(array("status" => OK, "opentables" => $noOfOpenTables, "sum" => $priceOfOpenTables));
	}
	
	static private function is_integerable( $v ){
		return is_numeric($v) && $v*1 == (int)($v*1);
	}
	
	static public function getLastClosings($admin,$pdo,$number) {
		if (self::is_integerable($number)) {
			$sql = "SELECT closingdate,billcount,billsum FROM %closing% ORDER BY closingdate DESC limit $number";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();

			$result = $stmt->fetchAll();
	
			if ($stmt->rowCount() == 0) {
				echo json_encode(array("status" => ERROR_NO_CLOSING));
			} else {
				echo json_encode(array("status" => OK, "closings" => $result ));
			}
		}
	}
	
	static public function getVersion($admin,$pdo) {
		$version = $admin->getConfigItemsAsString($pdo, "version");
		echo json_encode(array("status" => OK, "version" => $version));
	}
	
	static public function getReport($admin,$pdo) {
		$reports = new Reports();
		echo json_encode($reports->getStatsCore($pdo));
	}
	
	static public function sendLoginMessage($admin,$pdo,$msg) {
		self::saveInWorkTable($pdo,"loginmessage", $msg);
	}

	static public function sendWaiterMessage($admin,$pdo,$msg) {
		self::saveInWorkTable($pdo,"waitermessage", $msg);
	}
	
	static private function getMessage($admin,$pdo,$messageitem) {
		$sql = "SELECT value FROM %work% WHERE item=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($messageitem));
		$row = $stmt->fetchObject();
		if ($stmt->rowCount() > 0) {
			echo json_encode(array("status" => OK, "message" => $row->value));
		} else {
			echo json_encode(array("status" => NO_CONTENT));
		}
	}
	static public function getLoginMessage($admin,$pdo) {
		self::getMessage($admin, $pdo, 'loginmessage');
	}
	
	static public function getWaiterMessage($admin,$pdo) {
		self::getMessage($admin, $pdo, 'waitermessage');
	}
	
	static private function saveInWorkTable($pdo,$item,$value) {
		$sql = "SELECT id FROM %work% WHERE item=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($item));
		$row = $stmt->fetchObject();
		
		if ($stmt->rowCount() > 0) {
			$sql = "UPDATE %work% SET value=? WHERE item=?";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($value,$item));
			echo json_encode(array("status" => OK, "message" => "updated" ));
		} else {
			$sql = "INSERT INTO `%work%` (`id`,`item`,`value`,`signature`) VALUES(NULL,?,?,?)";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($item,$value,null));
			echo json_encode(array("status" => OK, "message" => "created"));
		}
	}
}

$command = $_GET["command"];
$authCode = $_POST['remoteaccesscode'];
$admin = new Admin();
$pdo = DbUtils::openDbAndReturnPdoStatic();

if (!checkForCorrectCode($authCode,$admin,$pdo)) {
	echo json_encode(array("status" => ERROR_NOT_AUTHOTRIZED));
	return;
}
if ($command == 'ping') {
	echo json_encode("OK");
} else if ($command == 'getOpenTables') {
	RemoteAccess::getOpenTables($admin,$pdo);
} else if ($command == 'getLastClosings') {
	RemoteAccess::getLastClosings($admin,$pdo,$_POST["number"]);
} else if ($command == 'getVersion') {
	RemoteAccess::getVersion($admin,$pdo);
} else if ($command == 'getReport') {
	RemoteAccess::getReport($admin,$pdo);
} else if ($command == 'sendLoginMessage') {
	RemoteAccess::sendLoginMessage($admin,$pdo,$_POST["message"]);
} else if ($command == 'getLoginMessage') {
	RemoteAccess::getLoginMessage($admin,$pdo);
} else if ($command == 'sendWaiterMessage') {
	RemoteAccess::sendWaiterMessage($admin,$pdo,$_POST["message"]);
} else if ($command == 'getWaiterMessage') {
	RemoteAccess::getWaiterMessage($admin,$pdo);
}

function checkForCorrectCode($authCode,$admin,$pdo) {
	$admin->getConfigItemsAsString($pdo,"remoteaccesscode");
	$codehash = $admin->getConfigItemsAsString($pdo,"remoteaccesscode");

	if (is_null($codehash) || ($codehash == "")) {
		// no remote access at all
		return false;
	}
	$receivedCode = md5($authCode);
	if ($codehash == $receivedCode) {
		return true;
	} else {
		return false;
	}
}
?>