<?php

require_once ('dbutils.php');
require_once ('commonutils.php');
require_once ('admin.php');

class Pickup {
	public static function handleCommand($command) {
		if (!in_array($command, array("getjobs","getmodus","declarepickready","declarepicknotready","deleteallcompletedjobs","getSessionStatusAndAuthRequirement"))) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_COMMAND_NOT_FOUND, "msg" => ERROR_COMMAND_NOT_FOUND_MSG));
			return false;
		}
		
		if(session_id() == '') {
			session_start();
		}
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		if($command == 'getmodus') {
			$ret = self::getmodus($pdo);
		} else if ($command == "getjobs") {
			$ret = self::getjobs($pdo);
		} else if ($command == "declarepickready") {
			$ret = self::declarepickready($pdo,$_POST["id"]);
		} else if ($command == "declarepicknotready") {
			$ret = self::declarepicknotready($pdo,$_POST["id"]);
		} else if ($command == "deleteallcompletedjobs") {
			$ret = self::deleteallcompletedjobs($pdo);
		} else if ($command == "getSessionStatusAndAuthRequirement") {
                        $ret = self::getSessionStatusAndAuthRequirement($pdo);
                }
		echo json_encode($ret);
	}
	
        private static function getSessionStatusAndAuthRequirement($pdo) {
                $pickupsnoauth = CommonUtils::getConfigValue($pdo, 'pickupsnoauth', 0);
                if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
                        return array("status" => "OK","msg" => array("loggedin" => 0,"pickupsnoauth" => $pickupsnoauth));
                } else {
                        return array("status" => "OK","msg" => array("loggedin" => 1,"pickupsnoauth" => $pickupsnoauth));
                }
        }
	private static function hasRightToRequestIncompleteJobs() {
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			return false;
		} else {
			return $_SESSION['right_extendedpickup'];
		}
	}
	private static function hasRightToRequestCompleteJobs() {
                $pdo = DbUtils::openDbAndReturnPdoStatic();
                $pickupsnoauth = CommonUtils::getConfigValue($pdo, "pickupsnoauth", 0);
                if ($pickupsnoauth == 1) {
                        return true;
                }
                
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			return false;
		} else {
			return ($_SESSION['right_pickups']);
		}
	}
	
	private static function getmodus($pdo) {
		if (self::hasRightToRequestIncompleteJobs()) {
			return array("status" => "OK","msg" => 2);
		}
		if (self::hasRightToRequestCompleteJobs()) {
			return array("status" => "OK","msg" => 1);
		}
		return array("status" => "OK","msg" => 0);
	}
	
	private static function getjobsoftype($pdo,$pickready) {
		if ($pickready == false) {
			$where = " pickready is null ";
			$limit = "";
		} else {
			$where = " pickready = '1' ";
			$showpickno = CommonUtils::getConfigValue($pdo, "showpickupsno", 20);
			$limit = " LIMIT $showpickno";
		}
		$sql = "SELECT id,content FROM %printjobs% WHERE type=? AND $where ORDER BY id DESC $limit";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array(7));
		$outNumbers = array();
		for ($i=count($result)-1;$i>=0;$i--) {
			$entry = $result[$i];
			$entryDecoded = json_decode($entry["content"],true);
			$outNumbers[] = array("id" => $entry["id"], "workid" => $entryDecoded["workid"]);
		}
		return $outNumbers;
	}
	
	private static function getjobs($pdo) {
		$incompletes = array();
		$completes = array();
		if (self::hasRightToRequestIncompleteJobs()) {
			$incompletes = self::getjobsoftype($pdo, false);
			$completes = self::getjobsoftype($pdo, true);
		} else if (self::hasRightToRequestCompleteJobs()) {
			$completes = self::getjobsoftype($pdo, true);
		}
                UsedFeatures::noteUsedFeature($pdo, UsedFeatures::$Pickup);
		return array("status" => "OK","msg" => array("incompletes" => $incompletes,"completes" => $completes));
	}
	
	private static function declarepickready($pdo,$id) {
		if (self::hasRightToRequestIncompleteJobs()) {
			$sql = "UPDATE %printjobs% SET pickready=? WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array(1,$id));
		}
		return self::getjobs($pdo);
	}
	
	private static function declarepicknotready($pdo,$id) {
		if (self::hasRightToRequestIncompleteJobs()) {
			$sql = "UPDATE %printjobs% SET pickready = null WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array($id));
		}
		return self::getjobs($pdo);
	}
	
	private static function deleteallcompletedjobs($pdo) {
		if (self::hasRightToRequestIncompleteJobs()) {
			$sql = "DELETE FROM %printjobs% WHERE type=? AND pickready=?";
			CommonUtils::execSql($pdo, $sql, array(7,1));
		}
		return self::getjobs($pdo);
	}
}
