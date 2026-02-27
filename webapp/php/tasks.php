<?php

require_once ('dbutils.php');
require_once ('commonutils.php');
require_once ('queuecontent.php');

class Tasks {
	
	private static $rights = array(
	    "gettaskinfoforuser"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("tasks","tasksmanagement")),
	    "createtask"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("tasks","tasksmanagement")),
	    "changetask"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("tasks","tasksmanagement")),
	    "gettasks"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("tasks","tasksmanagement")),
	    "gethistory"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("tasks","tasksmanagement")),
	    "delete"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("tasksmanagement"))
	);

	private static $STATUS_NEW = 1;
	private static $STATUS_OPEN = 2;
	private static $STATUS_DONE = 3;

	private static $ACTION_CREATE = 0;
	private static $ACTION_CHANGE = 1;
	private static $ACTION_REMOVE = 2;
	
	private static $ACTION_MOD_OWNER = 'o';
	private static $ACTION_MOD_STATUS = 's';
	private static $ACTION_MOD_PRIO = 'p';
	private static $ACTION_MOD_SUMMARY = 't';
	private static $ACTION_MOD_DESCRIPTION = 'd';
	
	
	public static function handleCommand($command) {

		$pdo = DbUtils::openDbAndReturnPdoStatic();

		if (!Permissions::checkRights($command, self::$rights)) {
			return false;
		}
		
		$ret = array("status" => "ERROR", "msg" => "Falsches Kommando");

		if ($command == 'gettaskinfoforuser') {
			$ret = self::gettaskinfoforuser($pdo);
		} else if ($command == 'createtask') {
			$ret = self::createtask($pdo,$_POST['summary'],$_POST['description'],$_POST['prio'],$_POST['owner']);
		} else if ($command == 'changetask') {
			$ret = self::changetask($pdo,$_POST["id"],$_POST['summary'],$_POST['description'],$_POST['prio'],$_POST['owner'],$_POST["status"]);
		} else if ($command == 'gettasks') {
			$ret = self::gettasks($pdo,$_GET["filtercat"],$_GET["fulltext"]);
		} else if ($command == 'gethistory') {
			$ret = self::gethistory($pdo,$_GET["id"]);
		} else if ($command == 'delete') {
			$ret = self::delete($pdo,$_POST["id"]);
		}
		echo json_encode($ret);
	}

	public static function areThereTasksForMe($pdo) {
		if (is_null($pdo)) {
			return 0;
		}
		if (session_id() == '') {
			session_start();
		}
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			return 0;
		}
		if (!$_SESSION['right_tasks'] && !$_SESSION['right_tasksmanagement']) {
			return 0;
		}
		
                if (!CommonUtils::isTableExists($pdo, "%tasks%")) {
                        return 0;
                }
                
		// use intval to avoid SQL injection
		$curuser = intval($_SESSION['userid']);
		$sql = "SELECT count(id) as countid FROM %tasks% WHERE owner=? AND status != ?";
		$row = CommonUtils::getRowSqlObject($pdo, $sql, array($curuser,self::$STATUS_DONE));
		$count = $row->countid;
		if ($count > 0) {
			return 1;
		} else {
			return 0;
		}
	}
	
	private static function mayCurUserAssignToAll($pdo) {
		$userMayAssignToAll = 0;
		$isTasksManager = self::isCurrentUserTasksManagement();
		if (!$isTasksManager) {
			$taskallassign = CommonUtils::getConfigValue($pdo, "taskallassign", 0);
			if ($taskallassign == 1) {
				$userMayAssignToAll = 1;
			}
		} else {
			$userMayAssignToAll = 1;
		}
		return $userMayAssignToAll;
	}
	
	private static function gettaskinfoforuser($pdo) {
		$userMayAssignToAll = self::mayCurUserAssignToAll($pdo);
		
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			$curuser = array("id" => "-1","username" => "");
		} else {
			$curuser = array("id" => $_SESSION['userid'],"username" => $_SESSION['currentuser']);
		}
		
		return array("status" => "OK","msg" => array("taskallassign" => $userMayAssignToAll,"curuser" => $curuser));
		
	}
	
	private static function gettasks($pdo,$filtercat,$fulltext) {
		if (session_id() == '') {
			session_start();
		}
		$usermaydelete = 0;
		if ($_SESSION['right_tasksmanagement']) {
			$usermaydelete = 1;
		}
		// use intval to avoid SQL injection
		$curuser = intval($_SESSION['userid']);
		
		try {
			$sql = "SELECT T.id as id,";
			$sql .= " submitter,COALESCE((SELECT username FROM %user% UU WHERE UU.id=submitter),'System') as submittername,";
			$sql .= " (SELECT (IF( (SELECT count(id) as countid FROM %user% UU WHERE UU.id=owner AND UU.active='1') = '0','0',owner))) as owner, ";
			$sql .= " COALESCE((SELECT username FROM %user% UU WHERE UU.id=owner AND UU.active='1'),'System') as ownername,";
			$sql .= " productid,COALESCE((SELECT longname FROM %products% P WHERE productid=P.id),'') as productname, ";
			$sql .= " submitdate,lastdate,status,prio,summary,description ";
			$sql .= "FROM %tasks% T ";

			switch ($filtercat) {
				case 0:
					$where = "";
					break;
				case 1:
					$where = " WHERE owner='$curuser' ";
					break;
				case 2:
					$where = " WHERE status != '" . self::$STATUS_DONE . "' ";
					break;
				case 3:
					$where = " WHERE owner='$curuser' AND status != '" . self::$STATUS_DONE . "' ";
					break;
				case 4:
					$where = " WHERE (owner is null OR (0=(SELECT count(id) as countid FROM %user% US WHERE US.id=owner AND US.active='1'))) AND status != '" . self::$STATUS_DONE . "' ";
					break;
				default:
					$where = "";
					break;
			}
                        
                        if ($fulltext != "") {
                                if ($where != "") {
                                        $where .= " AND ";
                                } else {
                                        $where .= " WHERE ";
                                }
                                $where .= " (summary LIKE ? OR description LIKE ?)";
                                $sqlArgs = array('%' . $fulltext . '%','%' . $fulltext . '%');
                        } else {
                                $sqlArgs = null;
                        }
			$sql .= " $where ";
			$sql .= " ORDER BY prio,lastdate";

			$result = CommonUtils::fetchSqlAll($pdo, $sql, $sqlArgs);
                        
                        UsedFeatures::noteUsedFeature($pdo, UsedFeatures::$Tasks);

			return array("status" => "OK","msg" => $result,"usermaydelete" => $usermaydelete);
		} catch (Exception $ex) {
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
	}


	
	public static function createTaskForEmptyInventory($pdo,$prodid) {
		$taskifempty = CommonUtils::getConfigValue($pdo, "taskifempty", 0);
		if (($taskifempty == 0) || (is_null($taskifempty))) {
			return;
		}
		//`productid` INT( 10 ) NULL ,
		$sql = "SELECT count(id) as countid FROM %tasks% WHERE productid=? AND status != ?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($prodid,self::$STATUS_DONE));
		$opentasks = $result[0]["countid"];
		if ($opentasks == 0) {
			
			$sql = "SELECT longname FROM %products% WHERE id=?";
			$pres = CommonUtils::fetchSqlAll($pdo, $sql, array($prodid));
			if (count($pres) == 0) {
				return;
			}
			$prodname = $pres[0]["longname"];
			$owner = CommonUtils::getConfigValue($pdo, "taskownerempty", 0);
			$submitter = 0;
			$prio = 1;
			$summary = "Warenbestand '$prodname' geht zur Neige";
			$description = "Der Warenbestand  des Produkts '$prodname' geht zur Neige. Dies ist eine automatisch erzeugte Aufgabe.";
			
			self::insertNewTask($pdo, $prio, $submitter, $owner, $summary, $description, $prodid,false);
		}	
	}
	
	private static function insertNewTask($pdo,$prio,$submitter,$owner,$summary,$description,$prodid,$useTransaction) {
		date_default_timezone_set(DbUtils::getTimeZone());
		$submitdate = date('Y-m-d H:i:s');
		
		if ($submitter == 0) {
			$submitter = null;
		}
		if ($owner == 0) {
			$owner = null;
		}
		if (strlen($summary) > 100) {
			$summary = substr($summary, 0, 100);
		}
		if (strlen($description) > 500) {
			$description = substr($description,0,500);
		}
		
		if ($useTransaction) {
			$pdo->beginTransaction();
		}
		$sql = "INSERT INTO %tasks% (submitdate,lastdate,submitter,owner,prio,status,summary,description,productid) VALUES(?,?,?,?,?,?,?,?,?)";
		CommonUtils::execSql($pdo, $sql, array($submitdate,$submitdate,$submitter,$owner,$prio,self::$STATUS_NEW,$summary,$description,$prodid));
		$taskid = $pdo->lastInsertId();
		
		$sql = "INSERT INTO %taskhist% (date,taskid,userid,action,fields) VALUES(?,?,?,?,?)";
		CommonUtils::execSql($pdo, $sql, array($submitdate,$taskid,$submitter,self::$ACTION_CREATE,''));
		if ($useTransaction) {
			$pdo->commit();
		}
	}
	
	private static function isUserLoggedIn($pdo) {
		if (session_id() == '') {
			session_start();
		}
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			return false;
		} else {
			return true;
		}
	}
	private static function mayUserChangeToThisOwner($pdo,$newOwner) {
		$taskInfo = self::gettaskinfoforuser($pdo);
		
		$curid = $taskInfo["msg"]["curuser"]["id"];
		if ($curid != $newOwner) {
			if ($taskInfo["msg"]["taskallassign"] != 1) {
				return false;
			}
		}
		return true;
	}
	
	private static function createtask($pdo,$summary,$description,$prio,$owner) {
		if (!self::isUserLoggedIn($pdo)) {
			return array("status" => "ERROR","msg" => "Benutzer ist nicht eingeloggt!");
		}
		if (!self::mayUserChangeToThisOwner($pdo, $owner)) {
			return array("status" => "ERROR","msg" => "Benutzer darf keinem anderem Benutzer eine Aufgabe zuweisen!");
		}
		
		self::insertNewTask($pdo, $prio, $_SESSION['userid'], $owner, $summary, $description, null,true);

		return array("status" => "OK");
	}

	private static function changetask($pdo,$id,$summary,$description,$prio,$owner,$status) {
		if (!self::isUserLoggedIn($pdo)) {
			return array("status" => "ERROR","msg" => "Benutzer ist nicht eingeloggt!");
		}
		if (!self::mayUserChangeToThisOwner($pdo, $owner)) {
			return array("status" => "ERROR","msg" => "Benutzer darf keinem anderem Benutzer eine Aufgabe zuweisen!");
		}
		if (strlen($summary) > 100) {
			$summary = substr($summary, 0, 100);
		}
		if (strlen($description) > 500) {
			$description = substr($description,0,500);
		}
		if ($owner == 0) {
			$owner = null;
		}
		$pdo->beginTransaction();
		try {
			$sql = "SELECT username FROM %user% WHERE id=?";
			$row = CommonUtils::getRowSqlObject($pdo, $sql, array($owner));
			$ownername = $row->username;
			if (is_null($ownername)) {
				$ownername = "System";
			}
			
			$changedFields = array();
			$checkFields = array(
			    array("owner",$owner,self::$ACTION_MOD_OWNER),
			    array("status",$status,self::$ACTION_MOD_STATUS),
			    array("summary",$summary,self::$ACTION_MOD_SUMMARY),
			    array("description",$description,self::$ACTION_MOD_DESCRIPTION),
			    array("prio",$prio,self::$ACTION_MOD_PRIO)
			);
	
			foreach($checkFields as $aCheckField) {
				if (self::willFieldBeChanged($pdo, $id, $aCheckField[0], $aCheckField[1])) {
					if (($aCheckField[2] == 's') || ($aCheckField[2] == 'p')) {
						$log = $aCheckField[2] . ":" . $aCheckField[1];
					} else if ($aCheckField[2] == 'o') {
						$ownername = str_replace(",", "", $ownername);
						$log = $aCheckField[2] . ":" . $ownername;
					} else {
						$log = $aCheckField[2];
					}
					$changedFields[] = $log;
				}
			}
			date_default_timezone_set(DbUtils::getTimeZone());
			$date = date('Y-m-d H:i:s');
			$userid = $_SESSION['userid'];
			$sql = "INSERT INTO %taskhist% (date,taskid,userid,action,fields) VALUES(?,?,?,?,?)";
			CommonUtils::execSql($pdo, $sql, array($date,$id,$userid,self::$ACTION_CHANGE,join(',',$changedFields)));
			
			$sql = "UPDATE %tasks% SET lastdate=?,owner=?,status=?,summary=?,description=?,prio=? WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array($date,$owner,$status,$summary,$description,$prio,$id));
			
			$pdo->commit();
			return array("status" => "OK","taskid" => $id,"lastchange" => $date);
		} catch (Exception $ex) {
			$pdo->rollBack();
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
		
	}
	
	private static function delete($pdo,$taskid) {
		$pdo->beginTransaction();
		try {
			$sql = "DELETE FROM %taskhist% WHERE taskid=?";
			CommonUtils::execSql($pdo, $sql, array($taskid));
			
			$sql = "DELETE FROM %tasks% WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array($taskid));
			
			$pdo->commit();
			return array("status" => "OK","taskid" => $taskid);
		} catch (Exception $ex) {
			$pdo->rollBack();
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
	}
	
	
	private static function gethistory($pdo,$id) {
		$sql = "SELECT date,taskid,userid,COALESCE(username,'System') as username,action,fields FROM %taskhist% H ";
		$sql .= "LEFT JOIN %user% U ON H.userid=U.id ";
		$sql .= "WHERE taskid=? ";
		$sql .= "ORDER BY date DESC";

		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($id));
		return array("status" => "OK","msg" => $result);
	}
	
	private static function willFieldBeChanged($pdo,$taskid,$field,$newcontent) {
		$sql = "SELECT $field as val FROM %tasks% WHERE id=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($taskid));
		if (count($result) == 0) {
			return true;
		}
		$oldcontent = $result[0]["val"];
		if ($oldcontent != $newcontent) {
			return true;
		} else {
			return false;
		}
	}
	
	private static function isCurrentUserTasksManagement() {
		if (session_id() == '') {
			session_start();
		}
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			return false;
		}
		if ($_SESSION['right_tasksmanagement']) {
			return true;
		} else {
			return false;
		}
	}
}
