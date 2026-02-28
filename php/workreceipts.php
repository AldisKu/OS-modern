<?php

class Workreceipts {
	public static function getNextWorkReceiptId($pdo) {
		$sql = "SELECT value from %work% WHERE item=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array('workid'));
		
		$workid = 0;
		if (count($result) == 0) {
			$sql = "INSERT INTO %work% (item,value,signature) VALUES(?,?,?)";
			CommonUtils::execSql($pdo, $sql, array("workid",0,null));
		} else {
			$workid = $result[0]['value'];
		}
		
		$nextid = intval($workid) + 1;
		
		$sql = "UPDATE %work% SET value=? WHERE item=?";
		CommonUtils::execSql($pdo, $sql, array($nextid,'workid'));
		
		return $nextid;
	}
	
	public static function resetWorkReceiptId($pdo) {
		$sql = "UPDATE %queue% SET printjobid=?";
		CommonUtils::execSql($pdo, $sql, array(null));
		
		$sql = "DELETE FROM %work% WHERE item=?";
		CommonUtils::execSql($pdo, $sql, array('workid'));
	}
	
	public static function createCancelWorkReceipt($pdo,$queueid) {
		$sql = "SELECT printjobid FROM %queue% WHERE id=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($queueid));
		if (count($result) == 1) {
			try {
				$workid = Workreceipts::getNextWorkReceiptId($pdo);
				
				$printjobid = $result[0]["printjobid"];
				if (!is_null($printjobid)) {
					$sql = "SELECT content,printer FROM %printjobs% WHERE id=?";
					$row = CommonUtils::getRowSqlObject($pdo, $sql, array($printjobid));
					$origJobJson = $row->content;
					$printer = $row->printer;
					$origJob = json_decode($origJobJson, true);

					$refworkid = $origJob["workid"];
					$table = $origJob["table"];
					$time = $origJob["time"];
					$products = $origJob["products"];
                                        
                                        if(session_id() == '') {
                                                session_start();
                                        }
                                        $userid = $_SESSION['userid'];
                                        $username = $_SESSION['currentuser'];
                                        $sql = "SELECT COALESCE(fullname,username) as fullname FROM %user% WHERE id=?";
                                        $fullname = CommonUtils::fetchSqlAll($pdo, $sql, array($userid))[0]['fullname'];

					$sql = "SELECT PN.name as productname,productid,price FROM %queue% Q,%prodnames% PN WHERE Q.id=? AND Q.prodnameid=PN.id ";
					$res = CommonUtils::fetchSqlAll($pdo, $sql, array($queueid));
					if (count($res) > 0) {
						$longname = $res[0]["productname"];
						$prodid = $res[0]["productid"];
						$price = $res[0]["price"];
						$sql = "select %extras%.name as name,%extras%.id FROM %queueextras%,%extras% WHERE extraid=%extras%.id AND queueid=?";
						$extrares = CommonUtils::fetchSqlAll($pdo, $sql, array($queueid));
						$extrasArr = array();
						foreach($extrares as $e) {
							$extrasArr[] = $e["name"];
						}
						$extrasStr = implode(",",$extrasArr);
						$sql = "select kind,category from %products%,%prodtype% where %products%.category=%prodtype%.id AND %products%.id=?";
						$reskind = CommonUtils::fetchSqlAll($pdo, $sql, array($prodid));
						$kind = $reskind[0]["kind"];
						$kindStr = "Speise";
						if ($kind == 1) {
							$kindStr = "Getränk";
						}
                                             
						$cancelJob = array(
						    "workid" => $workid,
						    "refworkid" => $refworkid,
                                                    "table" => $table,
                                                    "userid" => $userid, "username" => $username, "fullname" => $fullname,
						    "time" => $time,
						    "longname" => $longname,
						    "kind" => $kind,
						    "type" => $kindStr,
						    "price" => $price,
                                                    "products" => $products,
						    "extras" => $extrasStr);

						$cancelJobJson = json_encode($cancelJob);

						$printInsertSql = "INSERT INTO `%printjobs%` (`id` , `content`,`type`,`printer`) VALUES ( NULL,?,?,?)";
						$stmt = $pdo->prepare(DbUtils::substTableAlias($printInsertSql));
						$type = PrintQueue::$CANCELFOOD;
						if ($kind == 1) {
							$type = PrintQueue::$CANCELDRINK;
						}
						$stmt->execute(array($cancelJobJson,$type,$printer));

						$idOfWorkJob = $pdo->lastInsertId();

						CommonUtils::log($pdo,"QUEUE","Create cancel work job with id=$idOfWorkJob for tableid $table of kind $kind for printer=$printer");
						
					}
				}
			} catch (Exception $ex) {
				$msg = $ex->getMessage();
                                error_log("Error in creation of cancel print job: $msg");
				return;
			}
		}
	}
}
