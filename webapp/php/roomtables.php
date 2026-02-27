<?php
// Datenbank-Verbindungsparameter
require_once ('dbutils.php');
require_once ('commonutils.php');
require_once ('queuecontent.php');
// require_once ('content.php');

class Roomtables {
	var $dbutils;
	
	function __construct() {
		$this->dbutils = new DbUtils();
	}

	function handleCommand($command) {
		if(session_id() == '') {
			session_start();
			if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_NOT_AUTHOTRIZED, "msg" => ERROR_NOT_AUTHOTRIZED_MSG));
				return;
			}
		}
		
		header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
		
		if($command == 'showAllRooms') {
			$this->showAllRooms();
		} else if ($command == 'getRooms') {
			$this->getRooms(); // only rooms!
		} else if ($command == 'showAllRoomsAndTablesWithUnpaidItems') {
			$this->showAllRoomsAndTablesWithUnpaidItems();
		} else if ($command == 'getUnpaidTables') {
			$this->getUnpaidTables($_GET['roomid']);
		} else if ($command == 'getRoomfield') {
			$this->getRoomfield();
		} else if ($command == 'getRoomfieldAlsoInactive') {
			$this->getRoomfieldAlsoInactive();
		} else if ($command == 'setRoomInfo') {
			if (ISDEMO) {
					echo json_encode(array("status" => "ERROR","msg" => "Vorgang im Demo-System nicht erlaubt", "code" => "FORBIDDEN"));
					return;
			} else {
					if (self::hasCurrentUserAdminRights()) {
							$this->setRoomInfo($_POST['rooms'],$_POST['togoworkprinter']);
					}
			}
		} else if ($command == 'createTableCodes') {
			if (ISDEMO) {
					echo json_encode(array("status" => "ERROR","msg" => "Vorgang im Demo-System nicht erlaubt", "code" => "FORBIDDEN"));
					return;
			} else {
					self::createTableCodes();
			}
		} else if ($command == 'tableqrcodes') {
			self::tableqrcodes();
		}
	}
	
	private static function hasCurrentUserAdminRights() {
		if(session_id() == '') {
			session_start();
		}
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			return false;
		} else {
			return ($_SESSION['is_admin']);
		}
	}
	
	private static function createTableCodes() {
		if (!self::hasCurrentUserAdminRights()) {
			echo json_encode(array("status" => "ERROR","msg" => "Benutzerrechte nicht ausreichend"));
			return;
		}
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$sql = "SELECT id FROM %resttables% WHERE removed is null AND (code is NULL OR code='')";
			$activeTables = CommonUtils::fetchSqlAll($pdo, $sql);
			$updateSql = "UPDATE %resttables% SET code=? WHERE id=?";
			foreach($activeTables as $table) {
				$tableid = $table["id"];
				$uniqid = md5(uniqid());
				CommonUtils::execSql($pdo, $updateSql, array($uniqid,$tableid));
			}
			echo json_encode(array("status" => "OK"));
		} catch (Exception $ex) {
			echo json_encode(array("status" => "ERROR","msg" => "Datenbank nicht erreichbar"));
		}
	}
	
	function showAllRooms() {
		$pdo = DbUtils::openRepliDb();
		$roomtables = $this->getAllTablesAndRooms($pdo);
		echo json_encode($roomtables);
	}

	public static function getUnpaidTablesCore($pdo,$roomid) {
		$userarea = self::getUserArea($pdo);
		$areaWhere = " ";
		if (!is_null($userarea)) {
			$area = intval($userarea);
			$areaWhere = " AND R.area='$area' ";
		}

		$filterForOrderUser = "";
		$hasUserRightToPayAllOrders = CommonUtils::canUserPayAllOrders($pdo);
		if (!$hasUserRightToPayAllOrders) {
			$filterForOrderUser = " AND Q.orderuser='" . CommonUtils::getUserId() . "' ";
		}
		
		$tablesSql = "SELECT id,tableno as name FROM %resttables% R WHERE R.roomid=? AND removed is null $areaWhere ORDER BY sorting";
		$tablesArr = CommonUtils::fetchSqlAll($pdo,$tablesSql,array($roomid));

		$priceSumSql = CommonUtils::getUnpaidPriceSumSqlIgnoreOpenGuests();

		$tableresult = array();
		foreach($tablesArr as $aTable) {
			$tableid = $aTable['id'];
			
			$sql = "SELECT $priceSumSql as sumprice,count(Q.price) as prodcount 
			FROM %queue% Q
			INNER JOIN %products% ON Q.productid = %products%.id
			INNER JOIN %pricelevel% ON Q.pricelevel = %pricelevel%.id 
			LEFT JOIN %bill% B ON Q.billid=B.id 
			WHERE tablenr = ? AND paidtime is null AND toremove = '0' $filterForOrderUser 
			AND isclosed is null";
			
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($tableid));
			
			$row = $stmt->fetchObject();
			if ($row != null) {
				$prodcount = $row->prodcount;
				$sumprice = $row->sumprice;
				if ($prodcount > 0) {
					$aTableEntry = array("id" => $tableid,"name" => $aTable["name"], "pricesum" => $sumprice,"prodcount" => $prodcount);
					$tableresult[] = $aTableEntry;
				}
			}
		}
		return($tableresult);
	}
	
	function getUnpaidTables($roomid) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$queue = new QueueContent();
		$numberOfUnpaidProducts = $queue->numberOfUnpaidProductsForTable($pdo,null);

		$priceAndProdCountTakeAway = $this->getUnpaidSumOfTakeAway($pdo);
		echo json_encode(array(
			"status" => "OK", 
			"tables" => self::getUnpaidTablesCore($pdo,$roomid), 
			"takeawayunpaidprodcount" => $numberOfUnpaidProducts,
			"takeawayprice" => $priceAndProdCountTakeAway['pricesum'],
			"prodcounttakeaway" => $priceAndProdCountTakeAway['prodcount']));	
	}
	
	function showAllRoomsAndTablesWithUnpaidItems() {
		$pdo = DbUtils::openRepliDb();
		$roomtables = $this->getAllTablesAndRooms($pdo);
		for ($i=0;$i<count($roomtables);$i++) {
			$tablesArr = $roomtables[$i]["tables"];
			$newtablesArr = array();
			for ($j=0;$j<count($tablesArr);$j++) {
				$tableentry = $tablesArr[$j];
				$tableid = $tableentry["id"];
				if ($this->hasTableUnpaidItems($pdo,$tableid)) {
					$newtablesArr[] = $tableentry;
				}
			}
			$roomtables[$i]["tables"] = $newtablesArr;
		}
		echo json_encode($roomtables);
	}
	
	function hasTableUnpaidItems($pdo,$tableid) {
		$sql = "SELECT %queue%.id as id,longname,%queue%.price as price,%pricelevel%.name as pricelevelname,%products%.id as prodid
		FROM %queue%
		INNER JOIN %products% ON %queue%.productid = %products%.id
		INNER JOIN %pricelevel% ON %queue%.pricelevel = %pricelevel%.id LEFT JOIN %bill% B ON %queue%.billid=B.id 
		WHERE tablenr = $tableid AND toremove = '0' AND (paidtime is null AND B.paymentid <> ?)  
		ORDER BY ordertime;";

		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array(DbUtils::$PAYMENT_GUEST));
		$count = $stmt->rowCount();
		if ($count > 0) {
			return true;
		} else {
			return false;
		}
	}
	
	/*
	 * get only the rooms (for paydesk, because tables are dynamic due to their pay status)
	 */
	function getRooms() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$userarea = self::getUserArea($pdo);
		
		$result = $this->getAllRoomsWithActiveTables($pdo);
		
		$roomArr = array();
		
		foreach($result as $row) {
			$tablesToLookAt = $this->hasUserResponsibleTablesInRoom($pdo, $row['id'], $userarea);
			if (!$tablesToLookAt) {
				continue;
			}
			
			$roomEntry = array("id" => $row['id'], "name" => $row['roomname']);
			$roomArr[] = $roomEntry;
		}
		
		$priceAndProdCountTakeAway = $this->getUnpaidSumOfTakeAway($pdo);
		
		echo json_encode(array("roomstables" => $roomArr,
			"takeawayprice" => $priceAndProdCountTakeAway['pricesum'],
			"prodcounttakeaway" => $priceAndProdCountTakeAway['prodcount']));
	}
	
	private function getUnpaidSumOfTakeAway($pdo) {

		$filterForOrderUser = "";
		$hasUserRightToPayAllOrders = CommonUtils::canUserPayAllOrders($pdo);
		if (!$hasUserRightToPayAllOrders) {
			$filterForOrderUser = " AND Q.orderuser='" . CommonUtils::getUserId() . "' ";
		}


		$priceSumSql = CommonUtils::getUnpaidPriceSumSqlIgnoreOpenGuests();

		$sql = "SELECT $priceSumSql as pricesum,count(Q.price) as prodcount FROM %queue% Q ";
		$sql .= " LEFT JOIN %bill% B ON Q.billid=B.id ";
		$sql .= " WHERE Q.tablenr is null AND isclosed is null $filterForOrderUser";
		$result = CommonUtils::fetchSqlAll($pdo, $sql);
		if (count($result) > 0) {
				return array("pricesum" => $result[0]['pricesum'],"prodcount" => $result[0]['prodcount']);
		} else {
				return array("pricesum" => 0.0,"prodcount" => 0);
		}
	}
	
	private function hasUserResponsibleTablesInRoom($pdo,$roomid,$userarea) {
		if (is_null($userarea)) {
			return true;
		}
		$sql = "SELECT count(id) as countid FROM %resttables% R WHERE R.roomid=? AND R.area=?";
		$row = CommonUtils::getRowSqlObject($pdo, $sql, array($roomid,$userarea));
		$countid = $row->countid;
		if ($countid > 0) {
			return true;
		} else {
			return false;
		}
	}
	
	private static function getUserArea($pdo) {
		if(session_id() == '') {
			session_start();
		}
		$userid = $_SESSION['userid'];
		$sql = "SELECT area FROM %user% WHERE id=?";
		$row = CommonUtils::getRowSqlObject($pdo, $sql, array($userid));
		return $row->area;
	}
	
	private static function getTimesFromArray($tableid,$reservations) {
		foreach($reservations as $res) {
			if ($res["tableid"] == $tableid) {
				return $res["times"];
			}
		}
		return '';
	}
	
	private static function sqlForEndTime() {
		$sqlEndTimeStamp = 'ADDTIME(CONCAT(starttime,":",starttimemin,":00"),CONCAT(duration,":",durationmins,":00"))';
		$sqlEndHour = 'HOUR(' . $sqlEndTimeStamp . ') as endhour';
		$sqlEndMin = 'MINUTE(' . $sqlEndTimeStamp . ') as endmin';
		$sqlEndTime = "$sqlEndHour,$sqlEndMin";
		return $sqlEndTime;
	}
	
	private function getAllRoomsWithActiveTables($pdo):array {
			$sql = "select R.id as id,roomname,MIN(T.id) as mintid FROM %room% R
					LEFT JOIN %resttables% T ON R.id=T.roomid
					WHERE R.removed is null 
						AND T.active='1' AND (T.removed is null OR T.removed='0') 
					GROUP BY T.roomid
					HAVING mintid is not null
					ORDER BY R.sorting";
			return CommonUtils::fetchSqlAll($pdo, $sql);
	}
	
	private function getAllTablesAndRooms($pdo)
	{
		$sqlEndTimeStamp = 'ADDTIME(CONCAT(starttime,":",starttimemin,":00"),CONCAT(duration,":",durationmins,":00"))';
		$sqlEndHour = 'HOUR(' . $sqlEndTimeStamp . ') ';
		$sqlEndMin = 'LPAD(MINUTE(' . $sqlEndTimeStamp . '),2,0)';
		
		$sql = "SELECT tableid,GROUP_CONCAT(DISTINCT CONCAT(starttime,':',LPAD(starttimemin,2,0),'-',($sqlEndHour),':',($sqlEndMin)) ORDER BY starttime) as times from %reservations% R ";
		//$sql = "SELECT tableid,GROUP_CONCAT(DISTINCT CONCAT(starttime,':00-',(starttime+duration),':00') ORDER BY starttime) as times from %reservations% R ";
		$sql .= "WHERE DATE(scheduledate)=CURDATE() AND (HOUR(NOW())-1) <= starttime GROUP BY tableid";
		$reservations = CommonUtils::fetchSqlAll($pdo, $sql);

		$userarea = self::getUserArea($pdo);
		
		$queue = new QueueContent();
		
		$dbresult = $this->getAllRoomsWithActiveTables($pdo);

		$arrayOfRooms = array();
		
		$showprepinwaiter = CommonUtils::getConfigValue($pdo, 'showprepinwaiter', 1);
		$workflowconfigfood = CommonUtils::getConfigValue($pdo, 'workflowconfig', 0);
		$workflowconfigdrinks = CommonUtils::getConfigValue($pdo, 'workflowconfigdrinks', 0);
		$queryprodForTableView = false;
		if (($showprepinwaiter == 1) && (($workflowconfigfood == 0) || ($workflowconfigfood == 1) || ($workflowconfigdrinks == 0) || ($workflowconfigdrinks == 1))) {
			$queryprodForTableView = true;
		}

		foreach($dbresult as $zeile) {
			$roomid = $zeile['id'];
			
			$tablesToLookAt = $this->hasUserResponsibleTablesInRoom($pdo, $roomid, $userarea);
			if (!$tablesToLookAt) {
				continue;
			}
			
			$tablesArray = array();
			
			$areaWhere = " ";
			if (!is_null($userarea)) {
				$area = intval($userarea);
				$areaWhere = " AND R.area='$area' ";
			}
			
			$sql = "SELECT R.id as id,R.tableno as name,R.sorting as sorting FROM %resttables% R WHERE R.removed is null AND active='1' AND  R.roomid=? $areaWhere ORDER BY R.sorting";
			$tablesArray = CommonUtils::fetchSqlAll($pdo, $sql, array($roomid));
			
			// maybe a filtering is needed - this here will be later be used in desktop view -> Paydesk
			$filterForOrderUser = "";
			$hasUserRightToPayAllOrders = CommonUtils::canUserPayAllOrders($pdo);
			if (!$hasUserRightToPayAllOrders) {
				$filterForOrderUser = " AND Q.orderuser='" . CommonUtils::getUserId() . "' ";
			}

			$sql = "SELECT 
					Q.tablenr,
					SUM(Q.price) as pricesum
					FROM %queue% Q
					LEFT JOIN %bill% B ON Q.billid=B.id 
					WHERE Q.toremove='0' $filterForOrderUser AND Q.paidtime is null AND Q.isclosed is null AND Q.billid is null AND (B.paymentid is null OR B.paymentid <> '8') AND Q.tablenr IN
					(SELECT R.id as id FROM %resttables% R WHERE R.removed is null AND active='1' AND  R.roomid=? ORDER BY R.sorting) 
					GROUP BY Q.tablenr";
			$tablesWithPrices = CommonUtils::fetchSqlAll($pdo, $sql, array($roomid));
			
			$assocPriceArr = array();
			foreach ($tablesWithPrices as $aTableWithPrice) {
					$tableid = intval($aTableWithPrice['tablenr']);
					$pricesum = $aTableWithPrice['pricesum'];
					$assocPriceArr[$tableid] = $pricesum;
			}
			foreach($tablesArray as &$aTableEntry) {
					$tableid = intval($aTableEntry['id']);
					if (array_key_exists($tableid, $assocPriceArr)) {
							$aTableEntry['pricesum'] = $assocPriceArr[$tableid];
					} else {
							$aTableEntry['pricesum'] = '0.00';
					}
			}

			
 			foreach ($tablesArray as &$tableEntry) {
				$resTxt = self::getTimesFromArray($tableEntry["id"], $reservations);
				
				$arrayOfProdsAndIdsOfATable = array("prods" => array(), "ids" => '');
				if ($queryprodForTableView) {
					$arrayOfProdsAndIdsOfATable = $queue->getAllPreparedProductsForTableidAsArray($pdo,$tableEntry['id']);
				}
 				$arrayOfProdsOfATable = $arrayOfProdsAndIdsOfATable['prods'];
 				$numberOfProductsTotalToServe = $queue->numberOfProductsForTableNotDelivered($pdo,$tableEntry['id']);
				$numberOfUnpaidProducts = $queue->numberOfUnpaidProductsForTable($pdo,$tableEntry['id']);
 				$numberOfReadyProducts = count($arrayOfProdsOfATable);
 				$queueids = $this->getIdsFromProdList($arrayOfProdsOfATable);
 				
				$tableEntry['unpaidprodcount'] = $numberOfUnpaidProducts;
 				$tableEntry['prodcount'] = $numberOfProductsTotalToServe;
 				$tableEntry['prodready'] = $numberOfReadyProducts;
 				$tableEntry['readyQueueIds'] = $queueids;
				$tableEntry['reservations'] = $resTxt;
 			}
			
			$aRoomEntry = array ("id" => $roomid, "name" => $zeile['roomname'], "tables" => $tablesArray);
			
			$arrayOfRooms[] = $aRoomEntry;
		}
		
		$priceTakeAway = $this->getUnpaidSumOfTakeAway($pdo);
		
		
		$arrayOfProdsAndIdsOfATable = array("prods" => array(), "ids" => '');
		if ($showprepinwaiter == 1) {
			$arrayOfProdsAndIdsOfATable = $queue->getAllPreparedProductsForTableidAsArray($pdo,null);
		}
		$arrayOfProdsOfATable = $arrayOfProdsAndIdsOfATable['prods'];
		$numberOfUnpaidTablesTogo = $queue->numberOfUnpaidProductsForTable($pdo,null);
		$numberOfProductsTotalToServe = $queue->numberOfProductsForTableNotDelivered($pdo,null);
		$numberOfReadyProducts = count($arrayOfProdsOfATable);
		$queueids = $this->getIdsFromProdList($arrayOfProdsOfATable);
		
		return array("roomstables" => $arrayOfRooms, "takeawayprice" => $priceTakeAway, 
				"takeawayunpaidprodcount" => $numberOfUnpaidTablesTogo,
				"takeawayprodcount" => $numberOfProductsTotalToServe,
				"takeawayprodready" => $numberOfReadyProducts,
				"takeawayReadyQueueIds" => $queueids
		);
	}

	function getIdsFromProdList($arrayOfProdsOfATable) {
		$idArr = array();
		if (!is_null($arrayOfProdsOfATable) && (count($arrayOfProdsOfATable) > 0)) {
			foreach($arrayOfProdsOfATable as $queueEntry) {
				$idArr[] = $queueEntry["id"];
			}
			return $idArr;
		} else {
			return array();
		}
	}

	
	function setRoomInfo($roomsAsJson,$togoworkprinter) {
		$rooms = json_decode($roomsAsJson, true);
		
		$pdo = DbUtils::openDbAndReturnPdoStatic();

		$pdo->beginTransaction();
		
		try {
			
			
			$sql = "UPDATE %resttables% SET removed=1";
			CommonUtils::execSql($pdo, $sql, null);
			
			$sql = "UPDATE %room% SET removed=1";
			CommonUtils::execSql($pdo, $sql, null);
			
			foreach($rooms as $aRoom) {
				$roomid = $aRoom["roomid"];
				$printer = $aRoom["printer"];
				if ($printer == 0) {
					$printer = null;
				}
				$name = trim($aRoom["name"]);
				$sorting = trim($aRoom["sorting"]);
				$abbreviation = trim($aRoom["abbreviation"]);
				
				if (!is_numeric($roomid)) {
					$sql = "INSERT INTO %room% (roomname,abbreviation,printer,sorting) VALUES(?,?,?,?)";
					CommonUtils::execSql($pdo, $sql, array($name,$abbreviation,$printer,$sorting));
					$roomid = $pdo->lastInsertId();
				} else {
					$sql = "UPDATE %room% SET removed=?,roomname=?,abbreviation=?,printer=?,sorting=? WHERE id=?";
					CommonUtils::execSql($pdo, $sql, array(null,$name,$abbreviation,$printer,$sorting,$roomid));
				}
				if (isset($aRoom["tables"])) {
					$tables = $aRoom["tables"];

					foreach($tables as $t) {
						$tableid = $t["id"];
						$tablename = $t["tablename"];
						$name = $t["name"];
						$code = "";
						if (isset($t["code"])) {
							$code = $t["code"];
						}
						$area = $t["area"];
						if ($area == 0) {
							$area = null;
						}
						$sorting = $t["sorting"];
						$active = $t["active"];
						$allowoutorder = $t["allowoutorder"];

						if (!is_numeric($tableid)) {
							$sql = "INSERT INTO %resttables% (tableno,roomid,code,name,area,active,allowoutorder,sorting) VALUES(?,?,?,?,?,?,?,?)";
							CommonUtils::execSql($pdo, $sql, array($tablename,$roomid,$code,$name,$area,$active,$allowoutorder,$sorting));
						} else {
							if (self::hasTableChanged($pdo, $t)) {
								$sql = "INSERT INTO %resttables% (tableno,roomid,code,name,area,active,allowoutorder,sorting) VALUES(?,?,?,?,?,?,?,?)";
								CommonUtils::execSql($pdo, $sql, array($tablename,$roomid,$code,$name,$area,$active,$allowoutorder,$sorting));
								//$newTableId = $pdo->lastInsertId();
								$sql = "UPDATE %resttables% SET removed=? WHERE id=?";
								CommonUtils::execSql($pdo, $sql, array(1,$t["id"]));
							} else {
								$sql = "UPDATE %resttables% SET removed=?,tableno=?,roomid=?,code=?,name=?,area=?,active=?,allowoutorder=?,sorting=? WHERE id=?";
								CommonUtils::execSql($pdo, $sql, array(null,$tablename,$roomid,$code,$name,$area,$active,$allowoutorder,$sorting,$tableid));
							}
						}
					}
				}
			}
			
			$sql = "select %tablepos%.id as posid,%resttables%.removed FROM %tablepos%,%resttables% WHERE %resttables%.removed is not null AND %resttables%.id=%tablepos%.tableid";
			$result = CommonUtils::fetchSqlAll($pdo, $sql, null);
			foreach($result as $r) {
				$sql = "DELETE FROM %tablepos% WHERE id=?";
				CommonUtils::execSql($pdo, $sql, array($r["posid"]));
			};
			$sql = "select %tablemaps%.id as posid,%room%.removed FROM %tablemaps%,%room% WHERE %room%.removed is not null AND %room%.id=%tablemaps%.roomid";
			$result = CommonUtils::fetchSqlAll($pdo, $sql, null);
			foreach($result as $r) {
				$sql = "DELETE FROM %tablemaps% WHERE id=?";
				CommonUtils::execSql($pdo, $sql, array($r["posid"]));
			}
			
			$hist = new HistFiller();
			$hist->updateConfigInHist($pdo, "togoworkprinter", $togoworkprinter);
			
			$sql = "SELECT R.id as resid FROM %reservations% R,%resttables% T WHERE R.tableid=T.id AND T.removed is not null";
			$allReservIds = CommonUtils::fetchSqlAll($pdo, $sql, null);
			$sql = "DELETE FROM %reservations% WHERE id=?";
			foreach($allReservIds as $resid) {
				CommonUtils::execSql($pdo, $sql, array($resid["resid"]));
			}
			
			$pdo->commit();
		} catch (Exception $ex) {
			echo json_encode(array("status" => "ERROR","msg" => $ex->getMessage()));
			$pdo->rollBack();
			return;
		}
		$this->getRoomfieldAlsoInactive($pdo);
	}
	
	private static function hasTableChanged($pdo,$t) {
		$fieldsThatIndicateChange = array(
		    array("colfrombrowser" => "tablename","dbcol" => "tableno"),
		    array("colfrombrowser" => "name","dbcol" => "name"),
		    array("colfrombrowser" => "sorting","dbcol" => "sorting"),
		    array("colfrombrowser" => "active","dbcol" => "active"),
		    array("colfrombrowser" => "allowoutorder","dbcol" => "allowoutorder"),
		);
		
		$dbcols = array();
		foreach($fieldsThatIndicateChange as $f) {
			$el = $f["dbcol"];
			$getfield = "COALESCE(" . $el . ",'') as " . $el;
			$dbcols[] = $getfield;
		}
		$sql = "SELECT " . implode(",",$dbcols) . " FROM %resttables% WHERE id=?";
		$res = CommonUtils::fetchSqlAll($pdo, $sql, array($t["id"]));
		$datarow = $res[0];
		$changed = false;
		
		foreach($fieldsThatIndicateChange as $f) {
			$fieldValueInDb = $datarow[$f["dbcol"]];
			$destProp = $t[$f["colfrombrowser"]];
			if ($fieldValueInDb != $destProp) {
				$changed = true;
				break;
			}
		}
		
		return $changed;
	}
	
	function getRoomfieldAlsoInactive($pdo = null) {
		if (is_null($pdo)) {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
		}
		$this->getRoomfieldCore($pdo, true);
	}
	
	function getRoomfield($pdo = null) {
		if (is_null($pdo)) {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
		}
		$this->getRoomfieldCore($pdo, false);
	}
	
	function getRoomfieldCore($pdo,$includeInActiveTables) {
		
		$sql = "SELECT id,roomname,COALESCE(abbreviation,'') as abbreviation,COALESCE(printer,0) as printer,sorting FROM %room% WHERE removed is null ORDER BY 'sorting'";
                $result = CommonUtils::fetchSqlAll($pdo, $sql);
		$numberOfRooms = count($result);

		$maxTables = 0;
		$roomArr = array();
		
		$where = "removed is null AND active='1'";
		if ($includeInActiveTables) {
			$where = "removed is null";
		}
		
		foreach($result as $row) {
			$roomid = $row['id'];
			$roomname = $row['roomname'];
			$abbreviation = $row['abbreviation'];
			$printer = $row['printer'];
			if (gettype($row['sorting']) == "string") {
					$roomsorting = intval($row['sorting']);
			} else {
					$roomsorting = $row['sorting'];
			}
			
			$sql = "SELECT id,tableno,COALESCE(code,'') as code,COALESCE(name,'') as name,COALESCE(allowoutorder,0) as allowoutorder,COALESCE(sorting,1) as sorting,COALESCE(active,1) as active,COALESCE(area,0) as area FROM %resttables% WHERE roomid=? AND $where ORDER BY 'sorting'";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($row['id']));
			$numberOfTables = $stmt->rowCount();
			$maxTables = ($maxTables < $numberOfTables ? $numberOfTables : $maxTables);
			$tableresult = $stmt->fetchAll();
			
			$tableArr = array();
			foreach($tableresult as $aTable) {
				$tableArr[] = array("id" => $aTable['id'], "tablename" => $aTable['tableno'],"name" => $aTable['name'],"code" => $aTable['code'],"area" => $aTable['area'],"allowoutorder" => $aTable['allowoutorder'],"active" => $aTable['active'],"sorting" => $aTable['sorting']);
			}
			$roomArr[] = array("roomid" => $roomid, "roomname" => $roomname, "abbreviation" => $abbreviation, "printer" => $printer, "sorting" => $roomsorting, "tables" => $tableArr, "noOfTables" => $numberOfTables);
		}
		
		$togoworkprinter = CommonUtils::getConfigValue($pdo, "togoworkprinter", 0);
		
		echo json_encode(array("status" => "OK", "noOfRooms" => $numberOfRooms, "maxTables" => $maxTables, "roomfield" => $roomArr, "togoworkprinter" => $togoworkprinter));
	}
	
	public static function getTablesForGuestsystem($pdo) {
		$sql = "SELECT id,name,COALESCE(code,'') as code FROM %resttables% WHERE removed is null AND active=? AND allowoutorder=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array(1,1));
		return $result;
	}
	
	private static function createSingleQRCode($guesturl,$tablename,$tableid,$code,$addOnText,$guestqrsize,$guestqrfontsize,$version) {
		$arg = $guesturl . '/index.php?code=' . $code . "_" . $tableid . "_" . $version;
		$txt = '<div style="width:' . $guestqrsize . 'px;text-align:center;">';
		$txt .= 'Tisch: ' . $tablename . '<br>';
		if (!is_null($code) && ($code != '')) {
			$txt .= '<img src="utilities/osqrcode.php?cmd=link&arg=' . $arg . '" style="width:' . $guestqrsize . 'px;" /><br>';
		} else {
			$txt .= '<p><b>Tischcode wurde noch nicht zugewiesen</b><br>';
		}
		$txt .= '<p><span style="font-size:' . $guestqrfontsize . 'px;">' . $addOnText . '</span>';
		$txt .= '</div>';
		return $txt;
	}
	
	private static function createQrCodeForTables($pdo,$guesturl,$addOnText,$guestqrsize,$guestqrfontsize,$version) {
		$maxCols = 1;//round(500.0/($guestqrsize + 20));
                $guestqrsize = 500;
		$allTables = self::getTablesForGuestsystem($pdo);
		$txt = '<table class="qrcodes">';
		$col = 0;
		foreach($allTables as $aTable) {
			$code = $aTable['code'];
			$tableid = $aTable['id'];
			$tablename = $aTable['name'];
			if ($col == 0) {
				$txt .= "<tr>";
			}
			$txt .= '<td>' . self::createSingleQRCode($guesturl, $tablename, $tableid, $code, $addOnText, $guestqrsize, $guestqrfontsize,$version);
			$col++;
			if ($col == $maxCols) {
				$col = 0;
				$txt .= "</tr>";
			}
		}
		$txt .= "</table>";
		return $txt;
	}
	
	private static function tableqrcodes() {
		header( "Expires: Mon, 20 Dec 1998 01:00:00 GMT" );
		header( "Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT" );
		header( "Cache-Control: no-cache, must-revalidate" );
		header( "Content-Type: text/html; charset=utf8" );
		
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		
		$version = CommonUtils::getConfigValue($pdo, 'version', "0.0.0");
		$version = str_replace(".","",$version); 
		
		$guestUrl = CommonUtils::getConfigValue($pdo, 'guesturl', '');
		
		if (CommonUtils::strEndsWith($guestUrl, "/")) {
			$guestUrl = substr($guestUrl, 0, strlen($guestUrl) - 1);
		}
		
		if (CommonUtils::strEndsWith($guestUrl, "/index.php")) {
			$guestUrl = substr($guestUrl, 0, strlen($guestUrl) - strlen("/index.php"));
		}
		
		$guestqrtext = CommonUtils::getConfigValue($pdo, 'guestqrtext', '');
		$guestqrsize = CommonUtils::getConfigValue($pdo, 'guestqrsize', '');
		if (($guestqrsize < 20) || ($guestqrsize > 500)) {
			$guestqrsize = 150;
		}
		$guestqrfontsize = CommonUtils::getConfigValue($pdo, 'guestqrfontsize', '');
		if (($guestqrfontsize < 5) || ($guestqrfontsize > 50)) {
			$guestqrfontsize = 15;
		}
		if (is_null($guestUrl) || ($guestUrl == '')) {
			echo "Gastbestell-URL noch nicht konfiguriert";
			return;
		}
		
		$txt = "<html><head>";
		$txt .= "<title>Tisch QR-Codes für die Gastbestellung</title>";
		$txt .= '<meta http-equiv="content-type" content="text/html; charset=utf-8">';
		$txt .= '<link rel="stylesheet" type="text/css" href="../css/bestformat.css?v=2.9.12">';
		$txt .= "</head>";
		$txt .= "<body>";
		$txt .= "<h1>Tisch QR-Codes für die Gastbestellung</h1><p>";
		
		$txt .= self::createQrCodeForTables($pdo,$guestUrl,$guestqrtext,$guestqrsize,$guestqrfontsize,$version);
		$txt .= "</body></html>";
		echo $txt;
	}
        
	public static function getWorkload($pdo) {
			$sqlTables = "select count(id) as numberoftables from %resttables% where removed is null or removed='0'";
			$r = CommonUtils::fetchSqlAll($pdo, $sqlTables);
			$numberOfTables = intval($r[0]['numberoftables']);
			if ($numberOfTables == 0) {
					return 0;
			}
			
			$sqlUnpaidTables = "SELECT DISTINCT(tablenr) FROM %queue% where paidtime is null AND toremove='0'";
			$r = CommonUtils::fetchSqlAll($pdo, $sqlUnpaidTables);
			$unpaidTables = count($r);
			
			return round($unpaidTables * 100.0 / $numberOfTables);
	}
}
