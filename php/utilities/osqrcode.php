<?php
error_reporting(E_ERROR);

require_once (__DIR__. '/../3rdparty/phpqrcode.php');
require_once (__DIR__. '/../commonutils.php');


class OsQrcode {
	public static function createQrCodeForLink($text) {
		QRcode::png($text);
	}
	
	public static function createUserLgin() {
		if(session_id() == '') {
			session_start();
		}
		$userid = $_SESSION['userid'];
		$pdo = DbUtils::openDbAndReturnPdoStatic();

		$serverurl = CommonUtils::getConfigValue($pdo, 'serverurl', null);
		if (is_null($serverurl)) {
			header('Content-Type: image/png');
			readfile(__DIR__. '/../../img/oops.png');
			return;
		}
		
		$lastchar = substr($serverurl, -1);
		if ($lastchar == "/") {
			$serverurl = substr($serverurl, 0, strlen($serverurl) - 1);
		}
		
		$sql = "SELECT userpassword as value FROM %user% WHERE id=?";
		$passhash = CommonUtils::getFirstSqlQuery($pdo, $sql, array($userid),null);
		if (is_null($passhash)) {
			header('Content-Type: image/png');
			readfile(__DIR__. '/../../img/oops.png');
			return;
		} else {
			$text = $serverurl . "/nfclogin.php?a=" . $passhash . "_" . $userid . "&v=2.9.12";
			QRcode::png($text);
		}
	}
}

$cmd = $_GET["cmd"];
$arg = $_GET["arg"];

switch($cmd) {
	case "link":
		OsQrcode::createQrCodeForLink($arg);
		break;
	case "userlogin":
		OsQrcode::createUserLgin();
		break;
	default:
		break;
}

