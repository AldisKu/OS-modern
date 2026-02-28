<?php

require_once ('dbutils.php');
require_once ('commonutils.php');
require_once ('queuecontent.php');

class Timetracking {

	private static $ACTION_COME = 1;
	private static $ACTION_GO = 2;
	
	private static $rights = array(
	    "come"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("timetracking","timemanager")),
	    "go"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("timetracking","timemanager")),
	    "getusers"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("timetracking","timemanager")),
	    "getoverview"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("timetracking","timemanager")),
	    "delentry"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("timemanager")),
	    "newadmincome"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("timemanager")),
	    "newadmingo"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("timemanager")),
	    "exportTimesCsv"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("timemanager"))
	);

	public static function handleCommand($command) {

		$pdo = DbUtils::openDbAndReturnPdoStatic();

		if (!Permissions::checkRights($command, self::$rights)) {
			return false;
		}
		
		$ret = array("status" => "ERROR", "msg" => "Falsches Kommando");

		if ($command == 'come') {
			$ret = self::come($pdo,$_POST['comment']);
		} else if ($command == 'go') {
			$ret = self::go($pdo,$_POST['comment']);
		} else if ($command == 'getusers') {
			$ret = self::getusers($pdo);
		} else if ($command == 'getoverview') {
			$ret = self::getoverview($pdo,$_POST['userid'],$_POST['startday'],$_POST['startmonth'],$_POST['startyear'],$_POST['endday'],$_POST['endmonth'],$_POST['endyear']);
		} else if ($command == 'delentry') {
			$ret = self::delentry($pdo,$_POST['id']);
		} else if ($command == 'newadmincome') {
			$ret = self::newadmincome($pdo,$_POST['day'],$_POST['month'],$_POST['year'],$_POST['userid'],$_POST['time'],$_POST['comment']);
		} else if ($command == 'newadmingo') {
			$ret = self::newadmingo($pdo,$_POST['day'],$_POST['month'],$_POST['year'],$_POST['userid'],$_POST['time'],$_POST['comment']);
		} else if ($command == 'exportTimesCsv') {
			self::exportTimesCsv($pdo,$_GET['startMonth'],$_GET['startYear'],$_GET['endMonth'],$_GET['endYear']);
			return;
		}
		echo json_encode($ret);
	}

	private static function come($pdo,$comment) {
		$minBeforeCome = intval(CommonUtils::getConfigValue($pdo, 'minbeforecome', 0));
		date_default_timezone_set(DbUtils::getTimeZone());
		$now = date('Y-m-d H:i:s', strtotime("-$minBeforeCome minutes"));
		return self::insertAction($pdo, self::$ACTION_COME, $comment,$now);
	}
	private static function go($pdo,$comment) {
		$minAfterGo = intval(CommonUtils::getConfigValue($pdo, 'minaftergo', 0));
		date_default_timezone_set(DbUtils::getTimeZone());
		$now = date('Y-m-d H:i:s', strtotime("+$minAfterGo minutes"));
		return self::insertAction($pdo, self::$ACTION_GO, $comment,$now);
	}
	
	private static function insertAction($pdo,$action,$comment,$dateTimeToRegister) {
		try {
			if (is_null($comment)) {
				$comment = "";
			}
			$userid = $_SESSION['userid'];
			$sql = "INSERT INTO %times% (date,userid,action,comment) VALUES(?,?,?,?)";
			CommonUtils::execSql($pdo, $sql, array($dateTimeToRegister,$userid,$action,$comment));
			return array("status" => "OK");
		} catch (Exception $ex) {
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
	}
	
	private static function getusers($pdo) {
		$ownuserid = $_SESSION['userid'];
		$username = $_SESSION['currentuser'];

		if ($_SESSION['right_timemanager']) {
			$sql = "SELECT id,username FROM %user% WHERE active=?";
			$users = CommonUtils::fetchSqlAll($pdo, $sql, array(1));
			$mayManageTimes = 1;
		} else {
			$sql = "SELECT id,username FROM %user% WHERE active=? AND id=?";
			$users = CommonUtils::fetchSqlAll($pdo, $sql, array(1,$ownuserid));
			$mayManageTimes = 0;
		}
		
		return array("status" => "OK","msg" => array("ownuserid" => $ownuserid, "users" => $users, "username" => $username, "maymanageusers" => $mayManageTimes));
	}
	
	private static function getoverview($pdo,$userid,$startday,$startmonth,$startyear,$endday,$endmonth,$endyear) {
		$timesOfSelectedUser = self::getoverviewofuser($pdo, $userid, $startday, $startmonth, $startyear, $endday, $endmonth, $endyear);
		
		if ($_SESSION['right_timemanager']) {
			$sql = "SELECT id,username FROM %user% WHERE active=?";
			$users = CommonUtils::fetchSqlAll($pdo, $sql, array(1));
			$workTimes = array();
			foreach($users as $aUser) {
				$uid = $aUser["id"];
				$uname = $aUser["username"];
				$times = self::getoverviewofuser($pdo, $uid, $startday, $startmonth, $startyear, $endday, $endmonth, $endyear);
				if (($times["status"] == "OK") && ($times["msg"]["totalduration"]["status"] == "OK")) {
					$workMinutesOfUser = $times["msg"]["totalduration"]["minutes"];
					$workTimes[] = array("userid" => $uid,"username" => $uname,"minutes" => $workMinutesOfUser);
				} else {
					$workTimes[] = array("userid" => $uid,"username" => $uname,"minutes" => (-1));
				}
			}
			$timesOfSelectedUser["allusertimes"] = $workTimes;
		}
		return $timesOfSelectedUser;
	}
	private static function getoverviewofuser($pdo,$userid,$startday,$startmonth,$startyear,$endday,$endmonth,$endyear) {
		$ownuserid = $_SESSION['userid'];
		
		if ($ownuserid != $userid) {
			if (!$_SESSION['right_timemanager']) {
				return array("status" => "ERROR", "code" => ERROR_NOT_AUTHOTRIZED, "msg" => ERROR_NOT_AUTHOTRIZED_MSG);
			}
		}
		
		$sday = sprintf("%02s", $startday);
		$smonth = sprintf("%02s", $startmonth);
		$startDate = "$startyear-$smonth-$sday";
		
		$eday = sprintf("%02s", $endday);
		$emonth = sprintf("%02s", $endmonth);
		$endDate = "$endyear-$emonth-$eday";
		
		$begin = new DateTime( $startDate );
		$end = new DateTime( $endDate );
		
		$interval = DateInterval::createFromDateString('1 day');
		$end->add($interval);
		$period = new DatePeriod($begin, $interval, $end);

		$sql = "SELECT id,date,TIME(date) as thetime,HOUR(date) as timehour,MINUTE(date) as timemin,action,comment FROM %times% WHERE userid=? AND DATE(date)=? ORDER BY date";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		
		$totalWorkTime = 0;
		$timesCalcOk = true;
		$times = array();
		foreach ( $period as $dt ) {
			$aDate = $dt->format( "Y-m-d\n" );
			$stmt->execute(array($userid,$aDate));
			$timesOfDate = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$workMinutes = self::calcWorkTime($timesOfDate);
			if ($workMinutes["status"] == "OK") {
				$totalWorkTime += $workMinutes["minutes"];
			} else {
				$timesCalcOk = false;
			}
			
			$daynameno = date('N', strtotime($aDate));
			
			$times[] = array("dayno" => $daynameno, "date" => $aDate, "timesofdate" => $timesOfDate,"workminutes" => $workMinutes);
		}
		if ($timesCalcOk) {
			$totalWorkMinutes = array("status" => "OK","minutes" => $totalWorkTime);
		} else {
			$totalWorkMinutes = array("status" => "ERROR","minutes" => $totalWorkTime);
		}
		return array("status" => "OK","msg" => array("times" => $times, "totalduration" => $totalWorkMinutes));
	}
	
	private static function calcWorkTime($timesOfDay) {
		$workDuration = 0;
		
		if (count($timesOfDay) == 0) {
			return array("status" => "OK","minutes" => 0);
		}
		
		$currentEntry = $timesOfDay[0];
		$entryStatus = $currentEntry['action'];
		if ($entryStatus == self::$ACTION_COME) {
			$waitstatus = self::$ACTION_COME;
		} else {
			$lastCome = 0;
			$waitstatus = self::$ACTION_GO;
		}
		
		for ($i=0;$i<count($timesOfDay);$i++) {
			$currentEntry = $timesOfDay[$i];
			$entryStatus = $currentEntry['action'];
			$hour = intval($currentEntry['timehour']);
			$min = intval($currentEntry['timemin']);
			$minuteOfDay = ($hour * 60 + $min);
			
			if ($entryStatus != $waitstatus) {
				return array("status" => "ERROR","minutes" => $workDuration);
			} else {
				if ($entryStatus == self::$ACTION_COME) {
					$lastCome = $minuteOfDay;
					$waitstatus = self::$ACTION_GO;
				} else {
					$workDuration += $minuteOfDay - $lastCome;
					$waitstatus = self::$ACTION_COME;
				}
				if (($i+1) == count($timesOfDay)) {
					if ($waitstatus == self::$ACTION_GO) {
						$workDuration += (24*60 - $minuteOfDay);
						return array("status" => "OK","minutes" => $workDuration);
					}
				}
			}
		}
		return array("status" => "OK","minutes" => $workDuration);
	}
	
	private static function delentry($pdo,$timeid) {
		try {
			$sql = "DELETE FROM %times% WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array($timeid));
			return array("status" => "OK");
		} catch (Exception $ex) {
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
	}
	
	private static function newadmincome($pdo,$day,$month,$year,$userid,$time,$comment) {
		return self::createAdminComeGoEntry($pdo, $day, $month, $year, $userid, $time, $comment, self::$ACTION_COME);
	}
	private static function newadmingo($pdo,$day,$month,$year,$userid,$time,$comment) {
		return self::createAdminComeGoEntry($pdo, $day, $month, $year, $userid, $time, $comment, self::$ACTION_GO);
	}
	private static function parseTime($timeStr) {
		$parts = explode(':',$timeStr);
		if (count($parts) != 2) {
			return array(false,0,0);
		}
		if (!is_numeric($parts[0]) || !is_numeric($parts[1])) {
			return array(false,0,0);
		}
		$hour = sprintf("%02s", $parts[0]);
		$min = sprintf("%02s", $parts[1]);
		return array(true,$hour,$min);
	}
	private static function createAdminComeGoEntry($pdo,$day,$month,$year,$userid,$time,$comment,$action) {
		$timevalues = self::parseTime($time);
		if (!$timevalues[0]) {
			return array("status" => "ERROR","msg" => "Falsches Uhrzeitformat");
		}
		try {
			$day = sprintf("%02s", $day);
			$month = sprintf("%02s", $month);
			$hour = $timevalues[1];
			$min = $timevalues[2];
			$date = "$year-$month-$day $hour:$min:00";
			
			$sql = "INSERT INTO %times% (date,userid,action,comment) VALUES(?,?,?,?)";
			CommonUtils::execSql($pdo, $sql, array($date,$userid,$action,$comment));
			return array("status" => "OK");
		} catch (Exception $ex) {
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
	}
	
	private static function exportTimesCsv($pdo,$startMonth,$startYear,$endMonth,$endYear) {
		$startMonth = sprintf("%02s", $startMonth);
		$endMonth = sprintf("%02s", $endMonth);
		$startDate = $startYear . "-" . $startMonth . "-01";
		$endDate = $endYear . "-" . $endMonth . "-01";
		$lastdayOfMonth = date("t", strtotime($endDate));
		$endDate = $endYear . "-" . $endMonth . "-" . $lastdayOfMonth;
		
		$sql = "SELECT date,userid,username,IF(action=1,'Kommen','Gehen') as action,comment FROM %times% t,%user% u WHERE t.userid=u.id AND DATE(date) >= ? AND DATE(date) <= ? ORDER BY date";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($startDate,$endDate));
		
		$txt = "Zeitstempel;Benutzer-ID;Benutzername;Aktion;Kommentar\n";
		foreach($result as $r) {
			$txt .= $r['date'] . ";" . $r['userid'] .';"' . $r['username'] . '";"' . $r['action'] . '";"' . $r['comment'] . '";' . "\n";
		}
		
		header("Content-type: text/x-csv");
		header("Content-Disposition: attachment; filename=\"ordersprinter-zeiterfassung.csv\"");
		header("Cache-Control: max-age=0");
		echo $txt;
	}
}
