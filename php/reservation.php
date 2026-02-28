<?php
require_once ('dbutils.php');
require_once ('utilities/Emailer.php');

class Reservation {
	var $dbutils;
	private static $RES_TYPE_NORMAL = 0;
	private static $RES_TYPE_BLOCKDAY = 1;
	
	function __construct() {
		$this->dbutils = new DbUtils();
	}
	
	function handleCommand($command) {
		if (!$this->isUserAlreadyLoggedInForPhpAndMayReserve()) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_RES_NOT_AUTHOTRIZED, "msg" => ERROR_RES_NOT_AUTHOTRIZED_MSG));
		} else {
			switch ($command) {
				case 'createReservation':
					$pdo = DbUtils::openDbAndReturnPdoStatic();
					self::createReservation($pdo,$_POST['day'],$_POST['month'],$_POST['year'],$_POST['starthour'],$_POST['startmin'],$_POST['name'],$_POST['email'],$_POST['persons'],$_POST['durationhours'],$_POST['durationmins'],$_POST['phone'],$_POST['remark'],$_POST["tableid"],self::$RES_TYPE_NORMAL);
					break;
				case 'getReservations':
					$this->getReservations($_GET['day'],$_GET['month'],$_GET['year']);
					break;
				case 'changeReservation':
					$this->changeReservation($_POST['id'],$_POST['day'],$_POST['month'],$_POST['year'],$_POST['starthour'],$_POST['startmin'],$_POST['name'],$_POST['email'],$_POST['persons'],$_POST['durationhours'],$_POST['durationmins'],$_POST['phone'],$_POST['remark'],$_POST["tableid"]);
					break;
				case 'delReservation':
					$this->delReservation($_POST['id']);
					break;
				case 'emailConfirmReservation':
					$this->emailConfirmReservation($_POST['to'],$_POST['msg']);
					break;
				case 'reservationsAsHtml':
					$this->reservationsAsHtml($_GET['day'],$_GET['month'],$_GET['year']);
					break;
				case 'blockday':
					$pdo = DbUtils::openDbAndReturnPdoStatic();
					self::blockday($pdo,$_POST["day"],$_POST["month"],$_POST["year"],$_POST["remark"]);
					break;
				case 'releaseday':
					$pdo = DbUtils::openDbAndReturnPdoStatic();
					self::releaseday($pdo,$_POST["day"],$_POST["month"],$_POST["year"]);
					break;
				case 'getfutureblocks':
					$pdo = DbUtils::openDbAndReturnPdoStatic();
					self::getFutureBlocks($pdo);
					break;
				default:
					echo json_encode(array("status" => "OK","msg" => "Kommando nicht unterstuetzt."));
			}
		}
	}
	
	function isUserAlreadyLoggedInForPhpAndMayReserve() {
		if(session_id() == '') {
			session_start();
		}
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			return false;
		} else {
			return ($_SESSION['right_reservation']);
		}
	}
	
	private static function blockday($pdo,$day,$month,$year,$remark) {
		// REM* check, if there is already a block. In this case replace it
		$sql = "SELECT id FROM %reservations% WHERE DATE(scheduledate)=? AND restype=?";
		$resdate = new DateTime($year . '-' . $month . '-' . $day);
		$resdateTxt = $resdate->format('Y-m-d');
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($resdateTxt,self::$RES_TYPE_BLOCKDAY));
		if (count($result) > 0) {
			// REM* block entries found! User must delete it first!
			echo json_encode(array("status" => "ERROR","msg" => "Der Tag wurde bereits geblockt"));
			return;
		}
		
		return self::createReservation($pdo, $day, $month, $year, 0, 0, "", "", 0, 23, 59, "", $remark, null, self::$RES_TYPE_BLOCKDAY);
	}
	
	private static function releaseday($pdo,$day,$month,$year) {
		try {
			$sql = "DELETE FROM %reservations% WHERE DATE(scheduledate)=? AND restype=?";
			$resdate = new DateTime($year . '-' . $month . '-' . $day);
			$resdateTxt = $resdate->format('Y-m-d');
			CommonUtils::execSql($pdo, $sql, array($resdateTxt,self::$RES_TYPE_BLOCKDAY));
			echo json_encode(array("status" => "OK"));
		} catch (Exception $ex) {
			echo json_encode(array("status" => "ERROR","msg" => "Datenbank-Fehler: " . $ex->getMessage()));
		}
	}
	
	// REM* get all days that are full-day blocked
	private static function getFutureBlocks($pdo) {
		try {
			$sql = "SELECT id,DATE(scheduledate) as scheduledate,remark FROM %reservations% WHERE restype=? AND DATE(scheduledate) >= DATE_ADD(NOW(), INTERVAL -360 DAY) ORDER BY DATE(scheduledate)";
			$result = CommonUtils::fetchSqlAll($pdo, $sql, array(self::$RES_TYPE_BLOCKDAY));
			echo json_encode(array("status" => "OK","msg" => $result));
		} catch (Exception $ex) {
			echo json_encode(array("status" => "ERROR","msg" => "Datenbank-Fehler: " . $ex->getMessage()));
		}
	}
	
	private static function isBookingAllowed($pdo,$day,$month,$year) {
		$scheduledDate = "$year-$month-$day";
		$sql = "SELECT id FROM %reservations% WHERE restype=? AND DATE(scheduledate) = ?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array(self::$RES_TYPE_BLOCKDAY,$scheduledDate));
		if (count($result) > 0) {
			return false;
		} else {
			return true;
		}
	}
	
	private static function createReservation($pdo,$day,$month,$year,$start,$startmin,$name,$email,$persons,$durationhours,$durationmins,$phone,$remark,$tableid,$restype) {
		// REM* check if  booking is allowed
		if (!self::isBookingAllowed($pdo, $day, $month, $year)) {
			echo json_encode(array("status" => "ERROR","msg" => "Tag für weitere Buchungen gesperrt"));
			return;
		}
		
		$userid = $_SESSION['userid'];
		date_default_timezone_set(DbUtils::getTimeZone());
		$currentTime = date('Y-m-d H:i:s');
		$scheduledDate = "$year-$month-$day 00:00:00";
		
		// REM* check if date is in the past
		// REM* $resdate = new DateTime($year . '-' . $month . '-' . $day);
		// REM* $curDate = new DateTime(date('Y-m-d'));
		// REM* $interval = $curDate->diff($resdate);
		// REM* $daysDiff = intval($interval->format('%R%a'));
		// REM* if ($daysDiff < 0) {
			// REM* echo json_encode(array("status" => "ERROR", "msg" => "Reservierungsdatum liegt in der Vergangenheit"));
			// REM* return;
		// REM* }
		
		if ($tableid <= 0) {
			$tableid = null;
		}

		try {
			$pdo->beginTransaction();
			
			$sql = "INSERT INTO `%reservations%` (
					`id` , `creator`,`creationdate`,`scheduledate`,`name`,`email`,`starttime`,`starttimemin`,`duration`,`durationmins`,`persons`,`phone`,`remark`,`tableid`,`restype`)
					VALUES (
					NULL , ?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
			CommonUtils::execSql($pdo, $sql, array($userid,$currentTime,$scheduledDate,$name,$email,$start,$startmin,$durationhours,$durationmins,$persons,$phone,$remark,$tableid,$restype));
			
			$pdo->commit();
			echo json_encode(array("status" => "OK"));
		}
		catch (PDOException $e) {
			$pdo->rollBack();
			echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
		}
	}
	
	private function changeReservation($id,$day,$month,$year,$startHour,$startMin,$name,$email,$persons,$durationHours,$durationMins,$phone,$remark,$tableid) {
		// REM* check if  booking is allowed
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		if (!self::isBookingAllowed($pdo, $day, $month, $year)) {
			echo json_encode(array("status" => "ERROR","msg" => "Tag für weitere Buchungen gesperrt"));
			return;
		}
		$userid = $_SESSION['userid'];
		date_default_timezone_set(DbUtils::getTimeZone());
		$currentTime = date('Y-m-d H:i:s');
		$scheduledDate = "$year-$month-$day 00:00:00";

		try {
			$pdo->beginTransaction();
				
			$sql = "UPDATE `%reservations%` SET creator=?,creationdate=?,scheduledate=?,name=?,email=?,starttime=?,starttimemin=?,duration=?,durationmins=?,persons=?,phone=?,remark=?,tableid=? WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array($userid,$currentTime,$scheduledDate,$name,$email,$startHour,$startMin,$durationHours,$durationMins,$persons,$phone,$remark,$tableid,$id));
			$pdo->commit();
			echo json_encode(array("status" => "OK"));
		}
		catch (PDOException $e) {
			$pdo->rollBack();
			echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
		}
	}
	
	private function delReservation($id) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		try {
			$pdo->beginTransaction();
			$sql = "DELETE FROM `%reservations%` WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array($id));
			$pdo->commit();
			echo json_encode(array("status" => "OK"));
		}
		catch (PDOException $e) {
			$pdo->rollBack();
			echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
		}
	}
	
	private function emailConfirmReservation($toEmail,$msg) {
		// first find sender email
                $pdo = DbUtils::openDbAndReturnPdoStatic();

		$msg = str_replace("\n", "\r\n", $msg);
	
		$topictxt = "Reservierungsbestätigung\r\n";
	
		if (Emailer::sendEmail($pdo, $msg, $toEmail, $topictxt)) {
			echo json_encode("OK");
		} else {
			echo json_encode("ERROR");
		}
	}
	
	private static function getNoOfActiveRooms($pdo) {
		$sql = "SELECT COUNT(id) as countid FROM %room% WHERE removed is null";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, null);
		if (count($result) > 0) {
			return $result[0]["countid"];
		}
		return 0;
	}
	private function reservationsAsHtml($day,$month,$year) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$sql = self::getSqlForResByTime();
		$timeSortedReservations = $this->getReservationsCore($pdo,$day,$month,$year,$sql . " ORDER BY starttime,roomsorting,tablesorting");
		$numberOfActiveRooms = self::getNoOfActiveRooms($pdo);
		
		header( "Expires: Mon, 20 Dec 1998 01:00:00 GMT" );
		header( "Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT" );
		header( "Cache-Control: no-cache, must-revalidate" );
		header( "Pragma: no-cache" );
		header( "Content-Type: text/html; charset=utf8" );
		
		$txt = "<html><head>";
		$txt .= "<title>Reservierungsübersicht</title>";
		$txt .= '<meta http-equiv="content-type" content="text/html; charset=utf-8">';
		$txt .= '<link rel="stylesheet" type="text/css" href="../css/bestformat.css?v=2.9.12">';
		$txt .= "</head>";
		$txt .= "<body>";
		$txt .= "<h1>Reservierungsübersicht für $day.$month.$year</h1><p>";
		$txt .= "<table class='gridtable'>";
		$txt .= "<tr><th>Startuhrzeit<th>Dauer (Std.)<th>Gast<th>Personen<th>Platz<th>Zusatzinfo</tr>";
		
		foreach ($timeSortedReservations as $row) {
			$txt .= "<tr>";
			$txt .= "<td>" . $row['start'] . ":00";
			$txt .= "<td>" . $row['duration'];
			$txt .= "<td>" . htmlspecialchars($row['guest']);
			$txt .= "<td>" . htmlspecialchars($row['persons']);
			if ($numberOfActiveRooms > 1) {
				$txt .= "<td>" . htmlspecialchars($row['roomname']) . "/" . htmlspecialchars($row['tablename']);
			} else {
				$txt .= "<td>" . htmlspecialchars($row['tablename']);
			}
			$txt .= "<td>" . htmlspecialchars($row['remark']);
			$txt .= "</tr>";
		}
		$txt .= "</table></body></html>";
		echo $txt;
	}
	private static function getSqlForResByTime() {
		// REM* roomname and tablename only for the html output
		$sqlEndTime = self::sqlForEndTime();
		$sql = "SELECT R.id,U.username as username,creationdate,scheduledate,starttime as starthour,starttimemin as startmin,$sqlEndTime,name,email,persons,duration,durationmins,phone,remark,tableid, ";
		$sql .= "IF(tableid is null,'-1',(SELECT RO.id as roomid FROM %room% RO,%resttables% T WHERE T.id=tableid AND T.roomid=RO.id)) as roomid, ";
		$sql .= "IF(tableid is null,'-1',(SELECT RO.sorting as roomsorting FROM %room% RO,%resttables% T WHERE T.id=tableid AND T.roomid=RO.id)) as roomsorting, ";
		$sql .= "IF(tableid is null,'',(SELECT RO.roomname as roomname FROM %room% RO,%resttables% T WHERE T.id=tableid AND T.roomid=RO.id)) as roomname, ";
		$sql .= "IF(tableid is null,'-1',(SELECT T.sorting as tablesorting FROM %room% RO,%resttables% T WHERE T.id=tableid AND T.roomid=RO.id)) as tablesorting, ";
		$sql .= "IF(tableid is null,'',(SELECT T.tableno as tablename FROM %room% RO,%resttables% T WHERE T.id=tableid AND T.roomid=RO.id)) as tablename ";
		$sql .= "FROM %reservations% R,%user% U ";
		$sql .= "WHERE DATE(scheduledate)=? AND R.creator=U.id AND restype='0'";
		return $sql;
	}
	// REM* the end time may be in next day, and the minutes may also need an hiour to be increased
	private static function sqlForEndTime() {
		$sqlEndTimeStamp = 'ADDTIME(CONCAT(starttime,":",starttimemin,":00"),CONCAT(duration,":",durationmins,":00"))';
		// REM* will return something like 26:15:00 in case endtime is on next day
		$sqlEndHour = 'HOUR(' . $sqlEndTimeStamp . ') as endhour';
		$sqlEndMin = 'MINUTE(' . $sqlEndTimeStamp . ') as endmin';
		$sqlEndTime = "$sqlEndHour,$sqlEndMin";
		return $sqlEndTime;
	}
	private function getReservations($day,$month,$year) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$sqlEndTime = self::sqlForEndTime();
		
		// REM* the many sortings in the sql allow the sorting by time, room-sort and table-sort
		$sql = self::getSqlForResByTime();
		$timeSortedReservations = $this->getReservationsCore($pdo,$day,$month,$year,$sql . " ORDER BY starttime,roomsorting,tablesorting");
		
		// REM* and now by table
		$sql = "SELECT DISTINCT R.tableid as tableid,T.tableno as tablename,ROOM.id as roomid,ROOM.sorting as roomsorting,T.sorting as tablesorting FROM %reservations% R,%room% ROOM,%resttables% T ";
		$sql .= " WHERE restype='0' AND DATE(scheduledate)=? AND tableid is not null AND tableid >= '0' ";
		$sql .= " AND R.tableid = T.id AND T.roomid=ROOM.id ";
		$sql .= " ORDER BY ROOM.sorting,T.sorting ";
		$day = sprintf("%02s", $day);
		$month = sprintf("%02s", $month);
		$scheduledDate = "$year-$month-$day";
		$allTablesOfResAtThatDate = CommonUtils::fetchSqlAll($pdo, $sql, array($scheduledDate));
		$byTables = array();
		
		foreach($allTablesOfResAtThatDate as $tableRes) {
			$sql = "SELECT R.id,U.username as creator,creationdate,scheduledate,YEAR(scheduledate) as year,MONTH(scheduledate) as month, DAY(scheduledate) as day,starttime as starthour,starttimemin as startmin,name as guest,email,persons,duration as durationhours,durationmins,$sqlEndTime,";
			$sql .= " phone,remark,tableid,'" . $tableRes["roomid"] . "' as roomid ";
			$sql .= "FROM %reservations% R,%user% U ";
			$sql .= "WHERE restype='0' AND DATE(scheduledate)=? AND R.creator=U.id AND tableid=? ";
			$sql .= "ORDER BY starttime";
			$allResOfThatTable = CommonUtils::fetchSqlAll($pdo, $sql, array($scheduledDate,$tableRes["tableid"]));
			$byTables[] = array("tableid" => $tableRes["tableid"],"tablename" => $tableRes["tablename"],"roomid" => $tableRes["roomid"], "reservations" => $allResOfThatTable);
		}
		// REM* these were all reservations by table at the given date. Let's add all reservations without a table assignment
		$sql = "SELECT R.id,U.username as creator,creationdate,scheduledate,YEAR(scheduledate) as year,MONTH(scheduledate) as month, DAY(scheduledate) as day,starttime as starthour,starttimemin as startmin,name as guest,email,persons,duration as durationhours,durationmins,$sqlEndTime,";
		$sql .= " phone,remark,'-1' as tableid,'-1' as roomid ";
		$sql .= "FROM %reservations% R,%user% U ";
		$sql .= "WHERE restype='0' AND DATE(scheduledate)=? AND R.creator=U.id AND (tableid is null OR tableid='-1') ";
		$sql .= "ORDER BY starttime";
		$allResOfUndefinedTable = CommonUtils::fetchSqlAll($pdo, $sql, array($scheduledDate));
		if (count($allResOfUndefinedTable) > 0) {
			$byTables[] = array("tableid" => '-1',"tablename" => "?","roomid" => '-1', "reservations" => $allResOfUndefinedTable);
		}

		$msg = array("bytimes" => $timeSortedReservations,"bytables" => $byTables);
		
		// REM* now attach a list of rooms and tables to select for new reservations
		$tableoverview = self::gettablesoverview($pdo);
		
		echo json_encode(array("status" => "OK", "msg" => $msg,"tableoverview" => $tableoverview));
	}
	
	private function getReservationsCore($pdo,$day,$month,$year,$sql) {
		$day = sprintf("%02s", $day);
		$month = sprintf("%02s", $month);
		
		$scheduledDate = "$year-$month-$day";
		
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			
			$result = CommonUtils::fetchSqlAll($pdo, $sql, array($scheduledDate));

			$resArray = array();
	
			foreach($result as $row) {
				$resArray[] = array(
						"id" => $row['id'],
						"creator" => $row['username'],
						"creationdate" => $row['creationdate'],
						"day" => $row['day'],
						"month" => $row['month'],
						"year" => $row['year'],
						"starthour" => $row['starthour'],
						"startmin" => $row['startmin'],
						"endhour" => $row['endhour'],
						"endmin" => $row['endmin'],
						"guest" => $row['name'],
						"email" => $row['email'],
						"persons" => $row['persons'],
						"durationhours" => $row['duration'],
						"durationmins" => $row['durationmins'],
						"phone" => $row['phone'],
						"remark" => $row['remark'],
						"roomid" => $row['roomid'],
						"tableid" => $row['tableid'],
						"roomname" => $row['roomname'],
						"tablename" => $row['tablename']
						);
			}
			return $resArray;
		}
		catch (PDOException $e) {
			return array();
		}
	}
	
	private static function gettablesoverview($pdo) {
		try {
			$tableoverview = array();
			// REM* get only the rooms with not removed tables (active flag is ignored because it may be that the room is active at date for reservation)
			$sql = "SELECT R.id as roomid,R.roomname as roomname,COALESCE(R.abbreviation,'') as abbreviation from %room% R WHERE R.removed is null HAVING (SELECT COUNT(id) FROM %resttables% T WHERE T.roomid=R.id AND T.removed is null) > 0 ORDER BY sorting";

			$rooms = CommonUtils::fetchSqlAll($pdo, $sql);
			foreach($rooms as $aRoom) {
				$sql = "SELECT id,tableno as tablename FROM %resttables% WHERE roomid=? AND removed is null ORDER BY sorting";
				$tablesOfRoom = CommonUtils::fetchSqlAll($pdo, $sql, array($aRoom['roomid']));
				$tableoverview[$aRoom['roomid']] = array("roomid" => $aRoom['roomid'], "roomname" => $aRoom["roomname"],"roomabbreviation" => $aRoom["abbreviation"], "tables" => $tablesOfRoom);
			}
			return $tableoverview;
		} catch (Exception $ex) {
			return array();
		}
	}
}