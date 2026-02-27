<?php
require_once (__DIR__. '/../dbutils.php');
require_once (__DIR__. '/../globals.php');

class HistFiller {
	var $dbutils;
        
	function __construct() {
		$this->dbutils = new DbUtils();
	}

	public function defineHistActions () {
		$pdo = $this->dbutils->openDbAndReturnPdo();
		$sql = "INSERT INTO %histactions% (id,name,description) VALUES (?,?,?)";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		
		$stmt->execute(array('1', 'ProdInit', 'Initiales Befuellen der Produkttabelle'));
		$stmt->execute(array('2', 'ConfigInit', 'Initiales Befuellen der Konfigurationstabelle'));
		$stmt->execute(array('3', 'UserInit', 'Initiales Befuellen der Benutzertabelle'));
		$stmt->execute(array('4', 'ProdChange', 'Modifikation der Produktdaten'));
		$stmt->execute(array('5', 'ProdCreation', 'Neues Produkt'));
		$stmt->execute(array('6', 'ConfigChange', 'Modifikation der Konfiguration'));
		$stmt->execute(array('7', 'UserCreation', 'Neuer Benutzer'));
		$stmt->execute(array('8', 'UserChange', 'Modifikation eines Benutzers'));
		$stmt->execute(array('9', 'DbSave', 'Komplettsicherung der Datenbank'));
		$stmt->execute(array('10','DbRestore', 'Wiederherstellung der Datenbank aus einer Sicherungskopie'));
	}
	
        private static function getColNamesForHistTable($tableDescr) {
            $cols = array();
            foreach($tableDescr as $aCol) {
                if ($aCol["hist"] == 1) {
                    $cols[] = $aCol["col"];
                }
            }
            return $cols;
        }
        
        private static function getColNamesForUserHistTable() {
		$allUserCols = DbUtils::$userCols;
                $perms = CommonUtils::getPermissions(null);
		foreach ($perms as $aPerm) {
			$allUserCols[] = array("col" => $aPerm['name'],"hist" => 1);
		}
		return self::getColNamesForHistTable($allUserCols);
        }
        
	public static function readUserTableAndSendToHist($pdo) {
            $sql = "SELECT * FROM %user% WHERE active='1'";
            $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
            $stmt->execute(array());
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach($result as $aUser) {
                self::createUserInHist($pdo, $aUser["id"]);
            }
	}
        public static function createUserInHist($pdo,$userid) {
            $pdo->beginTransaction();
            self::updateOrCreateUserInHist($pdo,$userid,'7');
            $pdo->commit();
        }
        public static function updateUserInHist($pdo,$userid) {
            $pdo->beginTransaction();
            self::updateOrCreateUserInHist($pdo,$userid,'8');
            $pdo->commit();
        }
        private static function updateOrCreateUserInHist($pdo,$userid,$histaction) {
            self::updateOrCreateUserEntryInHist($pdo, $userid, $histaction, self::getColNamesForUserHistTable(), 'userid', 'user','histuser',null,null);
        }
        
        public static function readAllProdsAndFillHistByDb($pdo) {
            $sql = "SELECT id FROM %products% WHERE removed is null";
            $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
            $stmt->execute(array());
            $result = $stmt->fetchAll();
            foreach($result as $anElement) {
                self::createProdInHist($pdo, $anElement["id"]);
            }
        }
        private static function getColNamesForProdHistTable() {
            return self::getColNamesForHistTable(DbUtils::$prodCols);
        }
        public static function createProdInHist($pdo,$prodid) {
            self::updateOrCreateProdInHist($pdo,$prodid,'5');
        }
        public static function updateProdInHist($pdo,$prodid) {
            self::updateOrCreateProdInHist($pdo,$prodid,'4');
        }
	
        private static function getExtrasList($pdo,$prodid) {
            $sql = "SELECT GROUP_CONCAT(%extras%.name) as extraslist FROM %extras%,%extrasprods% WHERE %extrasprods%.prodid=? AND %extrasprods%.extraid=%extras%.id";
            $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
            $stmt->execute(array($prodid));
            $row =$stmt->fetchObject();
            return $row->extraslist;
        }
        private static function updateOrCreateProdInHist($pdo,$prodid,$histaction) {
            $extras = self::getExtrasList($pdo, $prodid);
	    $extraCol = (is_null($extras) ? null : 'extras');
            self::updateOrCreateEntryInHist($pdo, $prodid, $histaction, self::getColNamesForProdHistTable(), 'prodid', 'products', 'histprod',$extraCol,$extras);
        }
        
	private static function updateOrCreateUserEntryInHist($pdo,$id,$histaction,$colsInSourceTable,$idInHist,$sourcetable, $histtable,$extraCol,$extraVal) {
            if (!is_null($extraVal)) {
		if (strlen($extraVal) > 299) {
		    $extraVal = substr($extraVal, 0, 299);
		}
	    }
	    
            $sql = "SELECT * from %". $sourcetable . "%,%roles% WHERE %user%.id=? AND %user%.roleid=%roles%.id ";
            $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
            $stmt->execute(array($id));
            $row = $stmt->fetchObject();
            
            $cols = $colsInSourceTable;
            array_splice($cols, 0, 1, $idInHist);
            $valuesStr = implode(",", $cols);
            $quests = array();
            $vals = array();
            
            foreach($colsInSourceTable as $aHistCol) {
		    if ($aHistCol == "id") {
			  $vals[] = $id;  
		    } else {
			$vals[] = $row->$aHistCol;
		    }
                $quests[] = "?";
            }
            
            $sql_insert_hist = "INSERT INTO %". $histtable . "% (id," . $valuesStr . ") VALUES(NULL," . implode(",",$quests) . ")";
            $stmt_insert_hist = $pdo->prepare(DbUtils::substTableAlias($sql_insert_hist));
            $stmt_insert_hist->execute($vals);
            $newRefIdForHist = $pdo->lastInsertId();
            
            if (!is_null($extraCol)) {
                $sql = "UPDATE %". $histtable . "% SET " . $extraCol . "=? WHERE id=?";
                $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		try {
			if (strlen($extraVal) > 300) {
				$extraVal = substr($extraVal, 0, 300);
			}
			$stmt->execute(array($extraVal,$newRefIdForHist));
		} catch (Exception $ex) {
		}
            }
            
            self::insertIntoHist($pdo, $histaction, $newRefIdForHist);  
        }
	
        private static function updateOrCreateEntryInHist($pdo,$id,$histaction,$colsInSourceTable,$idInHist,$sourcetable, $histtable,$extraCol,$extraVal) {
            if (!is_null($extraVal)) {
		if (strlen($extraVal) > 299) {
		    $extraVal = substr($extraVal, 0, 299);
		}
	    }
	    
            $sql = "SELECT * from %". $sourcetable . "% WHERE id=?";
            $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
            $stmt->execute(array($id));
            $row = $stmt->fetchObject();
            
            $cols = $colsInSourceTable;
            array_splice($cols, 0, 1, $idInHist);
            $valuesStr = implode(",", $cols);
            $quests = array();
            $vals = array();
            
            foreach($colsInSourceTable as $aHistCol) {
                $vals[] = $row->$aHistCol;
                $quests[] = "?";
            }
            
            $sql_insert_hist = "INSERT INTO %". $histtable . "% (id," . $valuesStr . ") VALUES(NULL," . implode(",",$quests) . ")";
            $stmt_insert_hist = $pdo->prepare(DbUtils::substTableAlias($sql_insert_hist));
            $stmt_insert_hist->execute($vals);
            $newRefIdForHist = $pdo->lastInsertId();
            
            if (!is_null($extraCol)) {
                $sql = "UPDATE %". $histtable . "% SET " . $extraCol . "=? WHERE id=?";
                $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		try {
			if (strlen($extraVal) > 300) {
				$extraVal = substr($extraVal, 0, 300);
			}
			$stmt->execute(array($extraVal,$newRefIdForHist));
		} catch (Exception $ex) {
		}
            }
            
            self::insertIntoHist($pdo, $histaction, $newRefIdForHist);  
        }
	
	public static function insertSaveHistEntry($pdo) {
		self::insertIntoHist($pdo, 9, null);
	}
	
	public static function insertRestoreHistEntry($pdo) {
		self::insertIntoHist($pdo, 10, null);
	}
	
	
	public function updateConfigInHist($pdo,$theItem, $theValue) {
		$sql = "SELECT id FROM %config% WHERE name=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($theItem));
		if (count($result) == 0) {
			$sql = "INSERT INTO %config% (name,setting) VALUES(?,?)";
			CommonUtils::execSql($pdo, $sql, array($theItem,$theValue));
			$idInConfig = $pdo->lastInsertId();
			$action = 2;
		} else {
			$sql = "UPDATE %config% SET setting=? WHERE name=?";
			CommonUtils::execSql($pdo, $sql, array($theValue,$theItem));
			$idInConfig = $result[0]["id"];
			$action = 6;
		}
		$sql = "INSERT INTO %histconfig% (configid,setting) VALUES (?,?)";
		CommonUtils::execSql($pdo, $sql, array($idInConfig,$theValue));
		$newRefIdForHist = $pdo->lastInsertId();
		self::insertIntoHist($pdo, $action, $newRefIdForHist);
	}
	

	/*
	 * Read the complete config table and fill in these values to the histtable
	*/
	public function readConfigTableAndSendToHist() {
		$sql_query = "SELECT * FROM %config%";
	
		$sql_insert_histconfig = "INSERT INTO %histconfig% (id,configid,setting) VALUES (
		NULL,?,?)";
	
		$pdo = $this->dbutils->openDbAndReturnPdo();
		$pdo->beginTransaction();
	
		$stmt_query = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql_query));
		$stmt_insert_histconfig = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql_insert_histconfig));
	
		$stmt_query->execute();
		$result = $stmt_query->fetchAll();
		foreach($result as $row){
			$stmt_insert_histconfig->execute(array($row['id'],$row['setting']));
			$newRefIdForHist = $pdo->lastInsertId();
			$this->insertIntoHist($pdo, '2', $newRefIdForHist);
		}
		$pdo->commit();
	}
	
	private static function insertIntoHist($pdo,$action,$refIdForHist) {
		date_default_timezone_set(DbUtils::getTimeZone());
		$currentTime = date('Y-m-d H:i:s');
		$sql = "INSERT INTO %hist% (date,action,refid) VALUES (?,?,?)";
		CommonUtils::execSql($pdo, $sql, array($currentTime, $action, $refIdForHist));
	}
}