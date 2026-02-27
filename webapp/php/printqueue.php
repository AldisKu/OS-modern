<?php
require_once ('config.php');
require_once ('globals.php');
require_once ('dbutils.php');
require_once ('bill.php');
require_once ('closing.php');
require_once ('admin.php');

defined('LAST_REC_TEMPLATE') || define ( 'LAST_REC_TEMPLATE','0' );
defined('LAST_CLS_TEMPLATE') || define ( 'LAST_CLS_TEMPLATE','0' );
defined('NEED_PASS') || define ( 'NEED_PASS',true );

class PrintQueue {
	var $dbutils;
	var $userrights;
	var $admin;
	var $instance = null;
	
	private static $FOOD = 1;
	private static $DRINK = 2;
	private static $RECEIPT = 3;
	private static $CLOSING = 4;
	public static $CANCELFOOD = 5;
	public static $CANCELDRINK = 6;
	public static $PICKUP = 7;
	public static $FOOD_PRINTSERVER = 0;
	public static $DRINK_PRINTSERVER = 1;
        
        private static $KIND_FOOD = 0;
        private static $KIND_DRINKS = 1;
	
	function __construct() {
		$this->dbutils = new DbUtils();
		$this->userrights = new Userrights();
		$this->admin = new Admin();
	}
	
        // for test purposes it might be easier to allow also GET args for secrets
        private function getDataFromPostOrGet(string $name,string $default):string {
                $val = $default;
                if (isset($_POST[$name])) {
                        $val = $_POST[$name];
                } else if (isset ($_GET[$name])) {
                        $val = $_GET[$name];
                }
                return $val;
        }
        
	function handleCommand($command) {
		$fl = null;
		if (isset($_GET['fl'])) {
			$fl = intval($_GET['fl']);
		}
		if (isset($_GET['instance'])) {
			$this->instance = $_GET['instance'];
		}
		$printersize = new Printersizes($_GET);
		
                $pass = $this->getDataFromPostOrGet('pass', '');
		
		// these command are only allowed for user with waiter rights
		if ($command == 'getNextTicketJobs') {
			try {
				$this->getNextTicketJobs($pass,$printersize,$fl);
			} catch (Exception $ex) {
				echo $ex->getMessage();
			}
		} else if ($command == 'getNextReceiptPrintJobs') {
			if(isset($_GET['printers'])) {
				$this->getNextReceiptPrintJobs($pass,$_GET['language'],$_GET['printers'],$fl);
			} else {
				$this->getNextReceiptPrintJobs($pass,$_GET['language'],"1,2,3,4,5,6",$fl);
			}
		} else if ($command == 'getNextClosingPrintJobs') {
			$this->getNextClosingPrintJobs($pass,$_GET['language'],$fl);
		} else if ($command == 'getNextFoodWorkPrintJobs') {
			if (isset($_GET['printer'])) {
				$this->getNextFoodWorkPrintJobs($_GET['printer'],$pass,$fl);
			} else {
				$this->getNextFoodWorkPrintJobs(null,$pass,$fl);
			}
		} else if ($command == 'getNextDrinkWorkPrintJobs') {
			if (isset($_GET['printer'])) {
				$this->getNextDrinkWorkPrintJobs($_GET['printer'],$pass,$fl);
			} else {
				$this->getNextDrinkWorkPrintJobs(null,$pass,$fl);
			}
		} else if ($command == 'getNextPickupPrintJobs') {
			if (isset($_GET['printer'])) {
				$this->getNextPickupPrintPrintJobs($_GET['printer'],$pass,$fl);
			} else {
				$this->getNextPickupPrintPrintJobs(null,$pass,$fl);
			}
		} else if ($command == 'getNextCancelFoodWorkPrintJobs') {
			if (isset($_GET['printer'])) {
				$this->getNextCancelFoodWorkPrintJobs($_GET['printer'],$pass,$fl);
			} else {
				$this->getNextCancelFoodWorkPrintJobs(null,$pass,$fl);
			}
		} else if ($command == 'getNextCancelDrinkWorkPrintJobs') {
			if (isset($_GET['printer'])) {
				$this->getNextCancelDrinkWorkPrintJobs($_GET['printer'],$pass,$fl);
			} else {
				$this->getNextCancelDrinkWorkPrintJobs(null,$pass,$fl);
			}
			
			
		} else if ($command == 'deletePrintJob') {
                        $jobId = $this->getDataFromPostOrGet('id', '');
			$this->deletePrintJob($pass,$jobId);
		} else if ($command == 'queueReceiptPrintJob') {
			if (isset($_POST['useaddrecprinter'])) {
			    $this->queueReceiptPrintJob($_POST['billid'],$_POST['useaddrecprinter']);
			} else {
			    $this->queueReceiptPrintJob($_POST['billid'],0);
			}
		} else if ($command == 'queueClosingSummary') {
			$this->queueClosingSummary($_GET['closingid']);
		} else if ($command == 'testConnection') {
			$this->testConnection($pass);
		} else if ($command == 'getReceiptConfig') {
			$this->getReceiptConfig($fl);
		} else if ($command == 'getTicketPng') {
			$this->getTicketPng($_GET["imgtype"],$_GET["addinfo"]);
		} else if ($command == 'getLogoAsPng') {
			$this->getLogoAsPng();
		} else if ($command == 'getLogoAsPngWithAlphaChannel') {
			$this->getLogoAsPngWithAlphaChannel();
                } else if ($command == 'getNiceLogoAsPngWithAlphaChannel') {
			$this->getNiceLogoAsPngWithAlphaChannel();
                } else if ($command == 'getSroomTitleImgAsPngWithAlphaChannel') {
                        $this->getSroomTitleImgAsPngWithAlphaChannel();
                } else if ($command == 'getBestLogoAsPngWithAlphaChannel') {
			$this->getBestLogoAsPngWithAlphaChannel();
		} else if ($command == 'getLogoAsWbmp') {
			$this->getLogoAsWbmp();
		} else if ($command == 'getPrintJobOverview') {
			$pdo = DbUtils::openRepliDb();
			$this->getPrintJobOverview($pdo);
		} else if ($command == 'clearprintjobs') {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$this->clearprintjobs($pdo);
                } else if ($command == 'createtestprintjobs') {
                        $pdo = DbUtils::openDbAndReturnPdoStatic();
                        $this->createtestprintjobs($pdo);
		} else if ($command == 'batchReceiptPrintJob') {
			$this->batchReceiptPrintJob($_POST['start'],$_POST['end']);
		} else if ($command == 'getLastLog') {
			$this->getLastLog($pass);
		} else if ($command == 'deleteSpooledPrintJob') {
			$this->deleteSpooledPrintJob($_POST['id']);
		} else if ($command == 'reprintworkreceipt') {
			$this->reprintworkreceipt($_GET["id"]);
		} else {
			echo "Kommando nicht erkannt!";
		}
	}
	
	private function checkForPrinterInstance($pdo,$rectype) {
		if (!is_null($this->instance)) {
			$assignedInstance = CommonUtils::getConfigValue($pdo, $rectype . "prinstance", 1);
			if ($this->instance == $assignedInstance) {
				return true;
			} else {
				return false;
			}
		} else {
			return true;
		}
	}
	
	private function saveLastPrintServerAccess($pdo) {
	    date_default_timezone_set(DbUtils::getTimeZoneDb($pdo));
	    $date = new DateTime();
	    $unixTimeStamp = $date->getTimestamp();
	    $sql = "SELECT count(id) as countid FROM %work% WHERE item=?";
	    $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
	    $stmt->execute(array('lastprtserveraccess'));
	    $row = $stmt->fetchObject();
	    if ($row->countid == 0) {
		$sql = "INSERT INTO %work% (item,value,signature) VALUES(?,?,?)";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array('lastprtserveraccess',$unixTimeStamp,null));
	    } else {
		$sql = "UPDATE %work% SET value=? WHERE item=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($unixTimeStamp,'lastprtserveraccess'));
	    }
	}
	
	function testConnection($md5pass) {
		header( "Expires: Mon, 20 Dec 1998 01:00:00 GMT" );
		header( "Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT" );
		header( "Cache-Control: no-cache, must-revalidate" );
		header( "Pragma: no-cache" );
		header( "Content-Type: text/html; charset=utf8" );
		
		$isCorrect = $this->isPasswordCorrect(null,$md5pass,true);
		if ($isCorrect) {
			echo "ok";
		}
	}
	
	/*
	 * Insert a "work" (food or drink) job into the printjob queue. The POS Print Server will
	 * pick these jobs and delete them after successful printing
	 */
	public static function queueWorkPrintJob($pdo,$table,$timestamp,$prods,int $kind,$printer,$username,$userid,$isTogo,string $orderOption) {
		$workId = Workreceipts::getNextWorkReceiptId($pdo);

                $sql = "SELECT COALESCE(fullname,username) as fullname FROM %user% WHERE id=?";
                $fullname = CommonUtils::fetchSqlAll($pdo, $sql, array($userid))[0]['fullname'];
                
                $puretable = $table;
                $tablefullname = $table . " ($fullname)";
                $tableusername = $table . " ($username)";
                
		$content = json_encode(array(
                    "workid" => $workId,
                    "table" => $tableusername, "puretable" => $puretable, "tablefullname" => $tablefullname,
                    "userid" => $userid, "username" => $username, "fullname" => $fullname,
                    "time" => $timestamp,
                    "orderoption" => $orderOption,
                    "products" => $prods));

		$printInsertSql = "INSERT INTO `%printjobs%` (`content`,`type`,`printer`) VALUES (?,?,?)";
		CommonUtils::execSql($pdo, $printInsertSql, array($content,intval($kind) + 1,$printer));
		
		$idOfWorkJob = $pdo->lastInsertId();
		
		$sql = "UPDATE %queue% SET printjobid=? WHERE id=?";
		foreach($prods as $aProd) {
			$queueid = $aProd["id"];
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($idOfWorkJob,$queueid));
			if (isset($aProd["allqueueids"])) {
				foreach($aProd["allqueueids"] as $aQueueId) {
					$stmt->execute(array($idOfWorkJob,$aQueueId));
				}
			}
		}

		CommonUtils::log($pdo,"QUEUE","Create work job with id=$idOfWorkJob for tableid $table from user $username of kind $kind for printer=$printer");
		
                $pickupconfigitem = "printpickups";
                if ($kind == self::$KIND_DRINKS) {
                        $pickupconfigitem = "printpickupsdrinks";
                }
                $printAPickupReceipt = false;
                $printpickups = CommonUtils::getConfigValue($pdo, $pickupconfigitem, 0);
                if ($printpickups == 1) {
                        $printAPickupReceipt = true;
                } else if (($printpickups == 2) && ($isTogo)) {
                        $printAPickupReceipt = true;
                }
                if ($printAPickupReceipt) {
                        $sql = "INSERT INTO `%printjobs%` (`content`,`type`,`printer`) VALUES (?,?,?)";
                        CommonUtils::execSql($pdo, $sql, array($content,self::$PICKUP,1));
                }     
	}

	function getPrintJobOverview($pdo) {
		if (!($this->userrights->hasCurrentUserRight('right_manager')) &&
				!($this->userrights->hasCurrentUserRight('is_admin'))
		) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_DB_PRIVS_MISSING, "msg" => ERROR_DB_PRIVS_MISSING_MSG));
			return;
		}
		
                $billuidSql = Bill::getBillUidSqlPart();
		$jobs = array();
		for ($printer=1;$printer<7;$printer++) {
			$sql = "SELECT J.id as id,$billuidSql as billid,billdate,brutto,";
			$sql .= "CASE ";
			$sql .= " WHEN tableid='-1' THEN '---' ";
			$sql .= " WHEN tableid='0' THEN 'To-Go' ";
			$sql .= " ELSE (SELECT tableno FROM %resttables% WHERE id=B.tableid) ";
			$sql .= "END as tablename,";
			$sql .= "type FROM %printjobs% J,%bill% B WHERE printer=? AND type=? AND content=B.id ";
			
			$resultBills = CommonUtils::fetchSqlAll($pdo, $sql, array($printer,self::$RECEIPT));
			$sql = "SELECT %printjobs%.id as id,%closing%.id as closingid,type,closingdate FROM %printjobs%,%closing% WHERE printer=? AND type = '" . self::$CLOSING . "' AND content=%closing%.id";
			$resultClosings = CommonUtils::fetchSqlAll($pdo, $sql, array($printer));
			
			$result = array_merge($resultBills,$resultClosings);
			$jobs[] = array("printer" => $printer, "count" => count($result),"jobs" => $result);
		}
		
		$sql = "SELECT %printjobs%.id as id,content FROM %printjobs% WHERE type=? AND removed is null";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array(self::$FOOD));
		$result = $stmt->fetchAll();
		$works = array();
		foreach($result as $r) {
			$works[] = array("id" => $r['id'],"content" => json_decode($r['content']));
		}
		$jobs[] = array("printer" => 7, "count" => count($result),"jobs" => $works);
		$stmt->execute(array(self::$DRINK));
		$result = $stmt->fetchAll();
		$works = array();
		foreach($result as $r) {
			$works[] = array("id" => $r['id'],"content" => json_decode($r['content']));
		}
		$jobs[] = array("printer" => 8, "count" => count($result),"jobs" => $works);
		$stmt->execute(array(self::$PICKUP));
		$result = $stmt->fetchAll();
		$works = array();
		foreach($result as $r) {
			$works[] = array("id" => $r['id'],"content" => json_decode($r['content']));
		}
		$jobs[] = array("printer" => 9, "count" => count($result),"jobs" => $works);
		
		
		$sql = "SELECT %printjobs%.id as id,content FROM %printjobs% WHERE removed is not null";
		$printedWorkReceips = CommonUtils::fetchSqlAll($pdo, $sql);
		$printedWorkReceiptsInJson = array();
		foreach ($printedWorkReceips as $printedWorkReceipt) {
			$printjobentry = array("id" => $printedWorkReceipt['id'],"content" => json_decode($printedWorkReceipt['content']));
			$printedWorkReceiptsInJson[] = $printjobentry;
		}
		
		echo json_encode(array("status" => "OK", "msg" => $jobs, "printedworkreceips" => $printedWorkReceiptsInJson));
	}
	
        private function createTestArticleWithKind(string $thetime,int $kind,int $printer):array {
                $typename = "Speise";
                if ($kind == KIND_DRINKS) {
                        $typename = "Getr.";
                }
                $articlename = $typename . "artikel/Drucker $printer";
                return array(
                    "id" => "1",
                    "longname" => "1x $articlename",
                    "singleprod" => $articlename,
                    "price" => "12,34",
                    "togo" => "",
                    "option" => "Testbemerkung",
                    "extras" => array(array("name" => "1x Extra 1"),array("name" => "2x Extra 2")),
                    "ordertime" => $thetime,
                    "kind" => $kind,
                    "printer" => $printer,
                    "allqueueids" => array()
                    );
        }
        private function createTestPickupArticle(string $thetime):array {
                $articlename = "Artikel Abholbon";
                return array(
                    "id" => "1",
                    "longname" => "1x $articlename",
                    "singleprod" => $articlename,
                    "price" => "12,34",
                    "togo" => "",
                    "option" => "Testbemerkung",
                    "extras" => array(array("name" => "1x Extra 1"),array("name" => "2x Extra 2")),
                    "ordertime" => $thetime,
                    "kind" => KIND_FOOD,
                    "printer" => 1,
                    "allqueueids" => array()
                    );
        }
        private function createTestWorkJob(int $workid,$userid,string $username,int $kind,int $printer,string $thetime) {
                $anArticle = $this->createTestArticleWithKind($thetime, $kind, $printer);
                
                return array(
                    "workid" => $workid,
                    "table" => "Beispieltisch 1 ($username)",
                    "puretable" => "Beispieltisch 1",
                    "tablefullname" => "Beispieltisch 1 ($username)",
                    "userid" => $userid,
                    "username" => $username,
                    "fullname" => $username,
                    "time" => $thetime,
                    "products" => array($anArticle)
                    );
        }
        private function createTestPickupJob(int $workid,$userid,string $username,string $thetime) {
                $anArticle = $this->createTestPickupArticle($thetime);
                
                return array(
                    "workid" => $workid,
                    "table" => "Beispieltisch 1 ($username)",
                    "puretable" => "Beispieltisch 1",
                    "tablefullname" => "Beispieltisch 1 ($username)",
                    "userid" => $userid,
                    "username" => $username,
                    "fullname" => $username,
                    "time" => $thetime,
                    "products" => array($anArticle)
                    );
        }
        private function createtestprintjobs($pdo) {
                if (!($this->userrights->hasCurrentUserRight('right_manager')) &&
                        !($this->userrights->hasCurrentUserRight('is_admin'))
                ) {
                        echo json_encode(array("status" => "ERROR", "code" => ERROR_DB_PRIVS_MISSING, "msg" => ERROR_DB_PRIVS_MISSING_MSG));
                        return;
                }
                if(session_id() == '') {
				session_start();
			}
                $userid = $_SESSION['userid'];
		$username = $_SESSION['currentuser'];
                $date = new DateTimeImmutable();
                $thetime = $date->format('H:i');
                $workId = Workreceipts::getNextWorkReceiptId($pdo);
                if (is_string($workId)) {
                        $workId = intval($workId);
                }
                // {"workid":1,"table":"Tisch 11 (Charlie Chef)","puretable":"Tisch 11","tablefullname":"Tisch 11 (Bodo Boss)","userid":"4","username":"Charlie Chef","fullname":"Bodo Boss",
                // "time":"09:38","products":[{"id":"19","longname":"1x Schnitzel","singleprod":"Schnitzel","price":"4,00","togo":"","option":"","extras":[],"ordertime":"09:38","kind":0,"printer":1,"allqueueids":["19"]}]}
                
                $workJobFoodPrinter1 = json_encode($this->createTestWorkJob($workId,$userid, $username, KIND_FOOD, 1, $thetime));
                $workJobFoodPrinter2 = json_encode($this->createTestWorkJob($workId+1,$userid, $username, KIND_FOOD, 2, $thetime));
                $workJobFoodPrinter3 = json_encode($this->createTestWorkJob($workId+2,$userid, $username, KIND_FOOD, 3, $thetime));
                $workJobFoodPrinter4 = json_encode($this->createTestWorkJob($workId+3,$userid, $username, KIND_FOOD, 4, $thetime));
                $workJobDrinkPrinter1 = json_encode($this->createTestWorkJob($workId+4,$userid, $username, KIND_DRINKS, 1, $thetime));
                $workJobDrinkPrinter2 = json_encode($this->createTestWorkJob($workId+5,$userid, $username, KIND_DRINKS, 2, $thetime));
                $workJobDrinkPrinter3 = json_encode($this->createTestWorkJob($workId+6,$userid, $username, KIND_DRINKS, 3, $thetime));
                $workJobDrinkPrinter4 = json_encode($this->createTestWorkJob($workId+7,$userid, $username, KIND_DRINKS, 4, $thetime));
                $pickupJob = json_encode($this->createTestPickupJob($workId+8, $userid, $username, $thetime));
                $sql = "INSERT INTO %printjobs% (content,type,printer,removed,pickready) VALUES(?,?,?,?,?)";
                CommonUtils::execSql($pdo, $sql, array($workJobFoodPrinter1,1,1,null,null));
                CommonUtils::execSql($pdo, $sql, array($workJobFoodPrinter2,1,2,null,null));
                CommonUtils::execSql($pdo, $sql, array($workJobFoodPrinter3,1,3,null,null));
                CommonUtils::execSql($pdo, $sql, array($workJobFoodPrinter4,1,4,null,null));
                CommonUtils::execSql($pdo, $sql, array($workJobDrinkPrinter1,2,1,null,null));
                CommonUtils::execSql($pdo, $sql, array($workJobDrinkPrinter2,2,2,null,null));
                CommonUtils::execSql($pdo, $sql, array($workJobDrinkPrinter3,2,3,null,null));
                CommonUtils::execSql($pdo, $sql, array($workJobDrinkPrinter4,2,4,null,null));
                CommonUtils::execSql($pdo, $sql, array($pickupJob,7,1,null,null));
                
                $sql = "UPDATE %work% SET value=? WHERE item=?";
		CommonUtils::execSql($pdo, $sql, array($workId+8,'workid'));
                
                $this->getPrintJobOverview($pdo);
        }
	function clearprintjobs($pdo) {
	    if (!($this->userrights->hasCurrentUserRight('right_manager')) &&
				!($this->userrights->hasCurrentUserRight('is_admin'))
		) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_DB_PRIVS_MISSING, "msg" => ERROR_DB_PRIVS_MISSING_MSG));
			return;
		}
	    $sql = "DELETE FROM %printjobs% WHERE (type <> ?) AND (type <> ?)";
	    CommonUtils::execSql($pdo, $sql, array(self::$FOOD,self::$DRINK));
	   
	    $sql = "UPDATE %printjobs% SET removed=? WHERE (type = ?) OR (type = ?)";
	    CommonUtils::execSql($pdo, $sql, array(1,self::$FOOD,self::$DRINK));
	    
	    $this->getPrintJobOverview($pdo);
	}
	
        // $start and $end are billuids!
	function batchReceiptPrintJob($start,$end) {
	    try {
		$start = intval($start);
		$end = intval($end);
	    } catch (Exception $ex) {
		echo json_encode(array("status" => "ERROR", "code" => NUMBERFORMAT_ERROR, "msg" => NUMBERFORMAT_ERROR_MSG));
		return;
	    }
	    if(!($this->userrights->hasCurrentUserRight('right_bill'))) {
		echo json_encode(array("status" => "ERROR", "code" => ERROR_BILL_NOT_AUTHOTRIZED, "msg" => ERROR_BILL_NOT_AUTHOTRIZED_MSG));
	    } else {
		if ($start > $end) {
		    $tmp = $end;
		    $end = $start;
		    $start = $tmp;
		}
		if(session_id() == '') {
		    session_start();
		}

                $pdo = DbUtils::openDbAndReturnPdoStatic();
                $sql = "SELECT id FROM %bill% WHERE billuid>=? AND billuid<=? AND (status is null or ((status is not null) AND (cashtype is null)))";
                $jobsToPrint = CommonUtils::fetchSqlAll($pdo, $sql, array($start,$end));
                
                $printer = $_SESSION['receiptprinter'];
                
                foreach ($jobsToPrint as $aJob) {
                        $printInsertSql = "INSERT INTO `%printjobs%` (`id` , `content`,`type`,`printer`) VALUES ( NULL,?,?,?)";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($printInsertSql));
			$stmt->execute(array($aJob['id'],self::$RECEIPT,$printer));
                }
                
		echo json_encode(array("status" => "OK"));
	    }
	}
	
	function checkForUserRightManagerAdmin() {
		if (!($this->userrights->hasCurrentUserRight('right_manager')) &&
				!($this->userrights->hasCurrentUserRight('is_admin'))
		) {
			echo "Benutzerrechte nicht ausreichend!";
			return false;
		} else {
			return true;
		}
	}
	
	function reprintworkreceipt($printjobid) {
		if ($this->checkForUserRightManagerAdmin()) {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			
			$sql = "UPDATE %printjobs% SET removed=? WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array(null,$printjobid));
			echo json_encode(array("status" => "OK"));
		}
	}
	
	function deleteSpooledPrintJob($printjobid) {		
		if ($this->checkForUserRightManagerAdmin()) {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$sql = "DELETE FROM %printjobs% WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array($printjobid));
			echo json_encode(array("status" => "OK"));
		}
	}
	
	public static function internalQueueReceiptPrintjob($pdo,$billid,$recprinter) {
		try {
			CommonUtils::log($pdo, "PRINTQUEUE", "Insert bill with id=$billid for printer=$recprinter into queue.");

			$printInsertSql = "INSERT INTO `%printjobs%` (`content`,`type`,`printer`) VALUES (?,?,?)";
			CommonUtils::execSql($pdo, $printInsertSql, array((string)($billid),self::$RECEIPT,$recprinter));
			
			$addPrinter = CommonUtils::getConfigValue($pdo, 'addreceipttoprinter', null);
			if (!is_null($addPrinter)) {
				CommonUtils::execSql($pdo, $printInsertSql, array((string)($billid),self::$RECEIPT,$addPrinter));
			}
			
		} catch (Exception $ex) {
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
		return array("status" => "OK");
	}
	
	function queueReceiptPrintJob($billid,$useaddrecprinter) {
		// waiter, or manager, bill, admin rights required
		if (!($this->userrights->hasCurrentUserRight('right_paydesk')) &&
				!($this->userrights->hasCurrentUserRight('right_manager')) &&
				!($this->userrights->hasCurrentUserRight('right_bill')) &&
				!($this->userrights->hasCurrentUserRight('right_waiter')) &&
				!($this->userrights->hasCurrentUserRight('is_admin'))
		) {
			echo "Benutzerrechte nicht ausreichend!";
			return false;
		} else {
			// PAY_PRINT_TYPE = 3 means printing as paydesk print -> choose the printer
			// (print type is misused also for selection of printer)
			if(session_id() == '') {
				session_start();
			}
			$printer = $_SESSION['receiptprinter'];
			
			// now get receipt info from bill table
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$doubleReceipt = CommonUtils::getConfigValue($pdo, 'doublereceipt', 0);
			$isBillPaidByCard = Bill::isBillPaidByCard($pdo,$billid);
			$noOfInsert = 1;
			if ($isBillPaidByCard && ($doubleReceipt == 1)) {
				$noOfInsert = 2;
			}
			CommonUtils::log($pdo, "PRINTQUEUE", "Insert bill $noOfInsert x with id=$billid for printer=$printer into queue.");
			
			$sql = "SELECT setting FROM %config% WHERE name=?";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array("addreceipttoprinter"));
			$row = $stmt->fetchObject();
			$addprinter = $row->setting;
			
			$printInsertSql = "INSERT INTO `%printjobs%` (`id` , `content`,`type`,`printer`) VALUES ( NULL,?,?,?)";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($printInsertSql));
			for ($times=0;$times < $noOfInsert; $times++) {
				$stmt->execute(array((string)($billid),self::$RECEIPT,$printer));
			}

			if (!is_null($addprinter) && ($addprinter != "") && ($useaddrecprinter == 1)) {
			    $stmt->execute(array((string)($billid),self::$RECEIPT,$addprinter));
			}
			
			echo json_encode("OK");
		}
	}
	
	public function queueClosingSummary($closingid) {
		if (!($this->userrights->hasCurrentUserRight('right_paydesk')) &&
				!($this->userrights->hasCurrentUserRight('right_manager')) &&
				!($this->userrights->hasCurrentUserRight('right_bill')) &&
				!($this->userrights->hasCurrentUserRight('right_closing')) &&
				!($this->userrights->hasCurrentUserRight('right_waiter')) &&
				!($this->userrights->hasCurrentUserRight('is_admin'))
		) {
			echo "Benutzerrechte nicht ausreichend!";
			return false;
		} else {
			if(session_id() == '') {
				session_start();
			}
			$printer = $_SESSION['receiptprinter'];
			
			$pdo = $this->dbutils->openDbAndReturnPdo();
			
			CommonUtils::log($pdo, "PRINTQUEUE", "Insert closing with id=$closingid for printer=$printer into queue.");
			
			$printInsertSql = "INSERT INTO `%printjobs%` (`id` , `content`,`type`,`printer`) VALUES ( NULL,?,?,?)";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($printInsertSql));
			$stmt->execute(array((string)($closingid),self::$CLOSING,$printer));
			echo json_encode("OK");
		}
	}
	
	function getBigFontWorkReceiptSetting($pdo) {
		$sql = "SELECT setting FROM %config% WHERE name=?";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array("bigfontworkreceipt"));
		$row =$stmt->fetchObject();
		return $row->setting;
	}
	
	function isPasswordCorrect($pdo,$pass,$verbose) {
		if (is_null($pdo)) {
		    $pdo = DbUtils::openDbAndReturnPdoStatic();
		}
		$sql = "SELECT setting FROM %config% WHERE name=?";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array("printpass"));
		$row =$stmt->fetchObject();

		if ($row != null) {
			$passInDb = $row->setting;
			if ($passInDb != null) {
				// plain comparison
				if ($pass == $passInDb) {
					return true;
				} else {
					if ($verbose) {
						echo "Error: Falscher Printpass!";
					}
				}
			} else {
				if ($verbose) {
					echo "Error: kein Printpass in DB gesetzt!";
				}
			}
		}
		if ($verbose) {
			echo "Error: DB konnte nicht abgefragt werden!";
		}
		return false;
	}
	
	private function getTicketPng($imgtype,$addinfo) {
		if ($imgtype == 1) {
			$this->getLogoAsPng();
		} else if ($imgtype == 2) {
			Bill::outputBillQrCode($addinfo);
		} else if ($imgtype == 3) {
			Admin::getwaiterphotoforprint($addinfo);
		} else if ($imgtype == 4) {
			Bill::outputBillFiskalyQrCode($addinfo);
		}
	}
	
	function getLogoAsPng() {
                $pdo = DbUtils::openRepliDb();
		$this->getLogoAsPngCore($pdo,false,'logoimg');
	}
	
	function getLogoAsPngWithAlphaChannel() {
                $pdo = DbUtils::openRepliDb();
		$this->getLogoAsPngCore($pdo,true,'logoimg');
	}
        private function getNiceLogoAsPngWithAlphaChannel() {
                $pdo = DbUtils::openRepliDb();
                $this->getLogoAsPngCore($pdo,true,'nicelogoimg');
        }
        private function getSroomTitleImgAsPngWithAlphaChannel() {
                $pdo = DbUtils::openRepliDb();
                $this->getLogoAsPngCore($pdo,true,'sroomtitleimg');
        }
        private function getBestLogoAsPngWithAlphaChannel() {
                $pdo = DbUtils::openRepliDb();
                $sql = "SELECT id FROM %logo% WHERE name=? AND setting is not null";
                $result = CommonUtils::fetchSqlAll($pdo, $sql, array('nicelogoimg'));
                if (count($result) === 0) {
                        $this->getLogoAsPngCore($pdo,true,'logoimg');
                } else {
                        $this->getLogoAsPngCore($pdo,true,'nicelogoimg');
                }
        }
	
	private function getLogoAsPngCore($pdo,$saveAlphaChannel,$logoname) {
		$sendEmptyImageInsteadOfNone = false;
		if (isset($_GET["style"])) {
			if ($_GET["style"] == "always") {
				$sendEmptyImageInsteadOfNone = true;
			}
		}
		
		//header("Content-Disposition: attachment; filename=logo.png");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Pragma: no-cache");
		header("Expires: Mon, 20 Dec 1998 01:00:00 GMT" );
		header('Content-Type: ' . image_type_to_mime_type(IMAGETYPE_PNG));

		$sql = "SELECT setting from %logo% WHERE name=?";
		
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array($logoname));
		$row = $stmt->fetchObject();

		if ($stmt->rowCount() > 0) {
			$img = $row->setting;
			if (is_null($img)) {
				if ($sendEmptyImageInsteadOfNone) {
					CommonUtils::outputEmptyImage();
				}
			} else {
				$php_img = imagecreatefromstring($img);		

				$colorAlpha = null;
				imagesavealpha($php_img, true);
				if ($saveAlphaChannel) {
					$colorAlpha = imagecolorallocatealpha($php_img, 0, 0, 0, 127);
					imagepng($php_img, NULL);
					imagecolordeallocate($php_img,$colorAlpha);
				} else {
					
					$width = imagesx($php_img);
					$height = imagesy($php_img);
					$bgImg = imagecreatetruecolor($width, $height);
					$white = imagecolorallocate($bgImg, 255, 255, 255);
					imagefill($bgImg, 0, 0, $white);
				
					imagecopyresampled(
						$bgImg, $php_img,
						0, 0, 0, 0,
						$width, $height,
						$width, $height);
					imagepng($bgImg, NULL);
					
					imagecolordeallocate($bgImg,$white);
					imagedestroy($bgImg);
				}

				imagedestroy($php_img);
			}	
			
		} else {
			if ($sendEmptyImageInsteadOfNone) {
				CommonUtils::outputEmptyImage();
			}
		}
	}
	
	function getLogoAsWbmp() {
		$pdo = DbUtils::openRepliDb();
		$genInfo = $this->admin->getGeneralConfigItems(false,$pdo);
	
		header("Content-Disposition: attachment; filename=logo.wbmp");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Pragma: no-cache");
		header("Expires: Mon, 20 Dec 1998 01:00:00 GMT" );
		header('Content-Type: ' . image_type_to_mime_type(IMAGETYPE_WBMP));
	
		$logourl = $genInfo["logourl"];
		$img = file_get_contents("../" . $logourl);
		$php_img = imagecreatefromstring($img);
		
		$foreground_color = imagecolorallocate($php_img, 255, 0, 0);
		imagewbmp($php_img, NULL, $foreground_color);

		imagedestroy($php_img);
	}
	
	function getReceiptConfig($fl = null) {
		$pdo = DbUtils::openRepliDb();
		
		$decpoint = CommonUtils::getConfigValue($pdo, "decpoint", ",");
		$billlanguage = CommonUtils::getConfigValue($pdo, "billlanguage", 0);
		$version = CommonUtils::getConfigValue($pdo, "version", "0");
		$currency = CommonUtils::getConfigValue($pdo, "currency", "Euro");
		$companyinfo = CommonUtils::getConfigValue($pdo, "companyinfo", "");

		$retArray = array("decpoint" => $decpoint,
				"billlanguage" => $billlanguage,
				"version" => $version,
				"currency" => $currency,
				"companyinfo" => $companyinfo
		);
		
		if (!is_null($fl) && ($fl >= 12)) {
			$retArray["pollbills"] = CommonUtils::getConfigValue($pdo, "pollbills", 2);
			$retArray["pollworksf"] = CommonUtils::getConfigValue($pdo, "pollworksf", 2);
			$retArray["pollworksd"] = CommonUtils::getConfigValue($pdo, "pollworksd", 2);
			$retArray["pollclosings"] = CommonUtils::getConfigValue($pdo, "pollclosings", 2);
		}
		
		if (!is_null($fl) && ($fl >= 13)) {
			$retArray["pollpickups"] = CommonUtils::getConfigValue($pdo, "pollpickups", 2);
		}
		
		echo json_encode($retArray);
	}
	
	function getNextClosingPrintJobs($md5pass,$language,$fl=0) {
		$pdo = $this->dbutils->openDbAndReturnPdo();	    
		$isCorrect = $this->isPasswordCorrect($pdo,$md5pass,false);
		if ($isCorrect) {
			ob_start();
			
			$this->saveLastPrintServerAccess($pdo);
			
			$closing = new Closing();
			$sql = "SELECT id,content,type,printer FROM %printjobs% WHERE type=? ORDER BY id";
				
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array(4));
				
			$result = $stmt->fetchAll();
			$closingarray = array();
			foreach($result as $aClos) {
				$jobid = $aClos['id'];
				$closid = $aClos["content"];
				$printer = $aClos["printer"];
				
				if (!is_null($this->instance)) {
					if (!$this->checkForPrinterInstance($pdo, "k" . $printer)) {
						continue;
					}
				}
				
                                $theClosing = $closing->getASingleClosing($pdo, $closid, true);
				$aClosing = array("id" => $jobid,"closing" => $theClosing, "printer" => $printer);
				$closingarray[] = $aClosing;
			}
			echo json_encode($closingarray);
			ob_end_flush();
		} else {
			echo json_encode(array());
		}
	}
	
	function getTemplate($pdo,$templatekey,$closingid) {
		return CommonUtils::getConfigValueAtClosingTime($pdo, $templatekey, "", $closingid);
	}
	
	function getLastLog ($md5pass) {
	    $pdo = DbUtils::openRepliDb();
	    $isCorrect = $this->isPasswordCorrect($pdo,$md5pass,false);
	    if ($isCorrect) {
		echo json_encode(CommonUtils::getLastLog($pdo));
	    } else {
		echo json_encode("Log file from server unavaible due to wrong printcode");
	    }
	}
	
	private function getNextClosingTicketJobs($pdo,$printersizes) {	
		$closing = new Closing();
		$sqlreceiptprinters = "select substring(name,2,1) as printer from %config% where name like 'k%prinstance' AND setting=?";
		$billsql = "select J.id as jobid, content, printer from %printjobs% J left join %bill% B ON J.content=B.id where type=? and printer in ($sqlreceiptprinters)";
		$result = CommonUtils::fetchSqlAll($pdo, $billsql, array(self::$CLOSING,$this->instance));

		$tickets = array();
		foreach ($result as $r) {
			$jobid = $r['jobid'];
			$closid = $r["content"];
			$printer = $r["printer"];
                        $theClosing = $closing->getASingleClosing($pdo, $closid, true);
			
			$template = $theClosing["template"];
                        if (LAST_CLS_TEMPLATE == 1) {
				$template = CommonUtils::getConfigValue($pdo, "clstemplate", $template);
			}
			$osversion = $theClosing["version"];
			$label = DbUtils::$OSLABEL . "-" . DbUtils::$OSVERSLABEL;
			$line = "\n{TAB:}\n{-:links:$label $osversion}\n";
			$template .= $line;
			$printersize = $printersizes->getPrinterSize(Printersizes::$JOBTYPE_RECEIPT,$printer);
			$aTicket = Layouter::layoutTicket($template, array($theClosing),$printersize,false);
			$tickets[] = array(
			    "id" => $jobid, 
			    "tickettype" => "C",
			    "printer" => $r["printer"],
			    "entries" => $aTicket["printer"]);
		}
		return $tickets;
	}
	
        private static function strToBytes($text) {
                $prbytes = array();
                for ($i=0;$i<strlen($text);$i++) {
                        $prbytes[] = strval(ord(strval($text[$i])));
                }
                return $prbytes;
        }
        
	private function getNextReceiptTicketJobs($pdo,$printersizes) {	
		$bill = new Bill();
		$sqlreceiptprinters = "select substring(name,2,1) as printer from %config% where name like 'k%prinstance' AND setting=?";
		$billsql = "select J.id as jobid, content, printer from %printjobs% J left join %bill% B ON J.content=B.id where type=? and printer in ($sqlreceiptprinters) ";
                $billsql .= "AND content <> ?";
		$result = CommonUtils::fetchSqlAll($pdo, $billsql, array(self::$RECEIPT,$this->instance,-1));

		$tickets = array();
		foreach ($result as $r) {
			$aBill = $bill->getBillWithIdAsTicket($pdo, $r["content"]);
			$template = $aBill["template"];
			
			if (LAST_REC_TEMPLATE == 1) {
				$template = CommonUtils::getConfigValue($pdo, "rectemplate", $template);
			}
			
			$osversion = $aBill["version"];
			$printer = $r["printer"];
			$label = DbUtils::$OSLABEL . "-" . DbUtils::$OSVERSLABEL;
			$line = "\n{TAB:}\n{-:links:$label $osversion}\n";
			$template .= $line;
			$printersize = $printersizes->getPrinterSize(Printersizes::$JOBTYPE_RECEIPT,$printer);
			$aTicket = Layouter::layoutTicket($template, array($aBill),$printersize,false);
                        
                        if ( isset($aBill['paymentid']) && (($aBill['paymentid'] == DbUtils::$PAYMENT_GUEST) || ($aBill['paymentid'] == DbUtils::$PAYMENT_HOTEL))) {
                                $prbytes = self::strToBytes("Lieferbon - keine Rechnung\n\n");
                                $ticketEntryOrderToGuest = new TicketEntry("bytes",$prbytes);
                                array_unshift($aTicket["printer"],$ticketEntryOrderToGuest);
                        }
                        if (($aBill['tsestatus'] != DbUtils::$NO_TSE) && ($aBill['tsestatus'] != DbUtils::$TSE_OK)) {
                                $prbytes = self::strToBytes("TSE-Fehlfunktion\n\n");
                                $ticketEntryOrderToGuest = new TicketEntry("bytes",$prbytes);
                                array_unshift($aTicket["printer"],$ticketEntryOrderToGuest);
                        }
			$tickets[] = array(
			    "id" => $r["jobid"], 
			    "tickettype" => "R",
			    "printer" => $r["printer"],
			    "entries" => $aTicket["printer"]
                        );
		}
		return $tickets;
	}
	private function getNextWorkTicketJobs($pdo,$printersizes,$workJobType) {
		$templatekey = "foodtemplate";
		$theType = self::$FOOD;
		if ($workJobType === 'd') {
		    $templatekey = "drinktemplate";
		    $theType = self::$DRINK;
		}
		$template = $this->getTemplate($pdo, $templatekey,null);
		
		$sqlworkprinters = "select substring(name,2,1) as printer from %config% where name like '$workJobType%prinstance' AND setting=?";
		$workjobssql = "select J.id as jobid, content, printer from %printjobs% J where type=? and printer in ($sqlworkprinters) AND removed is null ORDER BY id";
		$workJobsResult = CommonUtils::fetchSqlAll($pdo, $workjobssql, array($theType,$this->instance));
		CommonUtils::log($pdo,"PRINTQUEUE", "getNextWorkPrintJobs: retrieve " . count($workJobsResult) . " jobs");
		
		$tickets = $this->getWorkTicketsCore($pdo,$template,$printersizes,$workJobsResult,strtoupper($workJobType),'getNextWorkPrintJobs');
		return $tickets;
	}
	
	private function getNextCancelTicketJobs($pdo,$printersizes,$workJobType) {
		$theType = self::$CANCELFOOD;
		if ($workJobType === 'd') {
		    $theType = self::$CANCELDRINK;
		}
		$template = $this->getTemplate($pdo, 'canceltemplate',null);
		
		$sqlworkprinters = "select substring(name,2,1) as printer from %config% where name like '$workJobType%prinstance' AND setting=?";
		$workjobssql = "select J.id as jobid, content, printer from %printjobs% J where type=? and printer in ($sqlworkprinters) AND removed is null ORDER BY id";
		$workJobsResult = CommonUtils::fetchSqlAll($pdo, $workjobssql, array($theType,$this->instance));
		CommonUtils::log($pdo,"PRINTQUEUE", "getNextCancelPrintJobs: retrieve " . count($workJobsResult) . " jobs");
		
		$tickets = $this->getWorkTicketsCore($pdo,$template,$printersizes,$workJobsResult,"C" . strtoupper($workJobType),'getNextCancelPrintJobs');
		return $tickets;
	}
	private function getNextPickupTicketJobs($pdo,$printersizes) {
		$template = $this->getTemplate($pdo, 'pickuptemplate',null);
		$theType = self::$PICKUP;
		
		$sqlworkprinters = "select substring(name,2,1) as printer from %config% where name like 'p%prinstance' AND setting=?";
		$workjobssql = "select J.id as jobid, content, printer from %printjobs% J where type=? and printer in ($sqlworkprinters) AND removed is null ORDER BY id";
		$workJobsResult = CommonUtils::fetchSqlAll($pdo, $workjobssql, array($theType,$this->instance));
		CommonUtils::log($pdo,"PRINTQUEUE", "getNextpickupPrintJobs: retrieve " . count($workJobsResult) . " jobs");
		
		$tickets = $this->getWorkTicketsCore($pdo,$template,$printersizes,$workJobsResult,'P','getNextPickupPrintJobs');
		return $tickets;
	}
	
	private function getWorkTicketsCore($pdo,$template,$printersizes,$workJobs,$workJobType,$msgLabel) {
		$jobPrinterType = Printersizes::$JOBTYPE_FOOD;
		if (strtolower($workJobType) === 'd') {
		    $jobPrinterType = Printersizes::$JOBTYPE_DRINK;
		} else if (strtolower($workJobType) === 'p') {
		    $jobPrinterType = Printersizes::$JOBTYPE_PICKUP;
		}
		
		$tickets = array();
		foreach($workJobs as $aWorkJob) {
			$aWorkInstance = (array) json_decode($aWorkJob["content"],true); // is in json format
			$jobid = $aWorkJob["jobid"];
			$printer = $aWorkJob["printer"];
                        $orderoption = "";
                        if (array_key_exists("orderoption", $aWorkInstance)) {
                               $orderoption =  $aWorkInstance["orderoption"];
                        }
                        
			$printersize = $printersizes->getPrinterSize($jobPrinterType,$printer);
			
			CommonUtils::log($pdo,"PRINTQUEUE", "$msgLabel: collect work receipt with id=$jobid for printer=$printer");

			$aTicket = Layouter::layoutTicket($template, array($aWorkInstance),$printersize,false);
			$tickets[] = array(
			    "id" => $jobid, 
			    "tickettype" => strtoupper($workJobType),
			    "printer" => $printer,
			    "entries" => $aTicket["printer"]);
		}
		return $tickets;
	}
	
	private function getNextTicketJobs($md5pass,$printersizes,$fl) {	
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$isCorrect = $this->isPasswordCorrect($pdo,$md5pass,false);
		if ($isCorrect || !(NEED_PASS)) {
			CommonUtils::log($pdo,"PRINTQUEUE", "getTickets: fl=$fl");
			
			ob_start();
			
			$rectickets = $this->getNextReceiptTicketJobs($pdo, $printersizes);
			$foodTickets = $this->getNextWorkTicketJobs($pdo, $printersizes, 'f');
			$drinkTickets = $this->getNextWorkTicketJobs($pdo, $printersizes, 'd');
			$cancelFoodTickets = $this->getNextCancelTicketJobs($pdo, $printersizes, 'f');
			$cancelDrinkTickets = $this->getNextCancelTicketJobs($pdo, $printersizes, 'd');
			$pickupTickets = $this->getNextPickupTicketJobs($pdo,$printersizes);
			$clsTickets = $this->getNextClosingTicketJobs($pdo, $printersizes);
			
			$tickets = array_merge($rectickets,$foodTickets,$drinkTickets,$cancelFoodTickets,$cancelDrinkTickets,$pickupTickets,$clsTickets);

			$this->saveLastPrintServerAccess($pdo);
			$ticketsInJson = json_encode($tickets);
			if (!$ticketsInJson) {
					$jsonError = json_last_error();
					error_log("Json Error during encoding of tickets according to json_last_error(): " . CommonUtils::jsonEncodeErrorToTxt($jsonError));
					if ($jsonError == JSON_ERROR_UTF8) {
							error_log("Sanitizing UTF8 string");
							$ticketsCleanUtf8 = CommonUtils::utf8ize($tickets);
							$ticketsInJson = json_encode($ticketsCleanUtf8);
					}
			}
			echo $ticketsInJson;

			ob_end_flush();
			CommonUtils::log($pdo,"PRINTQUEUE", "getNextTicketJobs: sent data to caller");
		} else {
			CommonUtils::log($pdo,"PRINTQUEUE", "getNextTicketJobs: Wrong printcode");
			echo json_encode(array());
		}
	}
	
	function getNextReceiptPrintJobs($md5pass,$language,$printers,$fl) {
		$pdo = $this->dbutils->openDbAndReturnPdo();
		$isCorrect = $this->isPasswordCorrect($pdo,$md5pass,false);
		if ($isCorrect) {
			CommonUtils::log($pdo,"PRINTQUEUE", "getReceipts: p=$printers, fl=$fl");
			
			ob_start();
			$printersArr = explode ( ',', $printers );
			
			$this->saveLastPrintServerAccess($pdo);
			
			if (intval($language) > 2) {
				$genInfo = $this->admin->getGeneralConfigItems(false,$pdo);
				$language = $genInfo["billlanguage"];
			}

			$bill = new Bill();
			
			$sql = "SELECT id,content,type,printer FROM %printjobs% WHERE type=? ORDER BY id";
			
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array(3));
			
			$result = $stmt->fetchAll();
			
			CommonUtils::log($pdo,"PRINTQUEUE", "getNextReceiptPrintJobs: retrieve " . count($result) . " jobs");
			
			$billarray = array();
			foreach($result as $aBill) {
				$printJobId = $aBill['id'];
				$aBillId = $aBill["content"];
				$printer = $aBill["printer"];
				
				$sql = "SELECT closingid FROM %bill% WHERE id=?";
				$r = CommonUtils::fetchSqlAll($pdo, $sql, array($aBillId));
				if (count($r) > 0) {
					$template = $this->getTemplate($pdo, "rectemplate",$r[0]["closingid"]);
				} else {
					$template = $this->getTemplate($pdo, "rectemplate",null);
				}
				
				CommonUtils::log($pdo,"PRINTQUEUE", "getNextReceiptPrintJobs: collect bill with id=$aBillId for printer=$printer");
				
				if (in_array($printer, $printersArr)) {
					
					if (!$this->checkForPrinterInstance($pdo, "k" . $printer)) {
						continue;
					}
			
					if (is_null($fl)) {
						$receiptJob = array("id" => $printJobId,"bill" => $bill->getBillWithId($pdo,$aBillId,$language,$printer));
					} else if ($fl >= 9) {
						$hosttext = CommonUtils::getConfigValue($pdo, 'hosttext', '');
						$receiptJob = array("id" => $printJobId,"bill" => $bill->getBillWithId($pdo,$aBillId,$language,$printer,true,true), "template" => $template, "hosttext" => $hosttext);
					} else if ($fl >= 6) {
						$receiptJob = array("id" => $printJobId,"bill" => $bill->getBillWithId($pdo,$aBillId,$language,$printer,true,true), "template" => $template);
					} else if ($fl >= 4) {
						$receiptJob = array("id" => $printJobId,"bill" => $bill->getBillWithId($pdo,$aBillId,$language,$printer,true), "template" => $template);
					} else if ($fl >= 1) {
						$receiptJob = array("id" => $printJobId,"bill" => $bill->getBillWithId($pdo,$aBillId,$language,$printer), "template" => $template);
					}
					
					if ($fl < 15) {
						unset($receiptJob["bill"]["billoverallinfo"]["sn"]);
						unset($receiptJob["bill"]["billoverallinfo"]["uid"]);
						unset($receiptJob["bill"]["billoverallinfo"]["version"]);
						unset($receiptJob["bill"]["billoverallinfo"]["companyinfo"]);
						unset($receiptJob["bill"]["billoverallinfo"]["systemid"]);
					}
					$billarray[] = $receiptJob;
				}
			}
			echo json_encode($billarray);
			ob_end_flush();
			CommonUtils::log($pdo,"PRINTQUEUE", "getNextReceiptPrintJobs: sent data to caller");
		} else {
			CommonUtils::log($pdo,"PRINTQUEUE", "getNextReceiptPrintJobs: Wrong printcode");
			echo json_encode(array());
		}
	}
	
	function getNextPickupPrintPrintJobs($printer,$md5pass,$fl) {
		$pdo = $this->dbutils->openDbAndReturnPdo();
		$isCorrect = $this->isPasswordCorrect($pdo,$md5pass,false);
		if ($isCorrect) {
			CommonUtils::log($pdo,"PRINTQUEUE", "getNextPickupPrintPrintJobs: type = " . self::$PICKUP . " , printer = $printer, fl= $fl");
			if (!$this->checkForPrinterInstance($pdo, "p" . $printer)) {
				echo json_encode(array());
				return;
			}
			$this->saveLastPrintServerAccess($pdo);
			$template = $this->getTemplate($pdo, "pickuptemplate",null);
			
			if (is_null($printer)) {
				$sql = "SELECT id,content,type FROM %printjobs% WHERE type=? AND removed is null ORDER BY id";
				$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
				$stmt->execute(array(self::$PICKUP));
			} else {
				$sql = "SELECT id,content,type FROM %printjobs% WHERE type=? AND removed is null AND printer=? ORDER BY id";
				$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
				$stmt->execute(array(self::$PICKUP,$printer));
			}
			
			$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
			CommonUtils::log($pdo,"PRINTQUEUE", "getNextPickupPrintPrintJobs: retrieve " . count($result) . " jobs");
			
			$workarray = array();
			foreach($result as $aWorkJob) {
				$aWork = (array) json_decode($aWorkJob["content"]); // is in json format
				$ps = (array) ($aWork["products"]);
				foreach($ps as $p) {
					if (isset($p->singleprod)) {
						unset($p->singleprod);
					}
					if (isset($p->allqueueids)) {
						unset($p->allqueueids);
					}
				}
				
				CommonUtils::log($pdo,"PRINTQUEUE", "getNextPickupPrintPrintJobs: collect pickupreceipt with id=" . $aWorkJob["id"] . " for printer=$printer");
				
				$workid = $aWork["workid"];
				unset($aWork["workid"]);
				
				$workarray[] = array("workid" => $workid,"id" => $aWorkJob["id"],"content" => $aWork, "bigfontworkreceipt" => 0, "template" => $template);

			}
			CommonUtils::log($pdo,"PRINTQUEUE", "getNextPickupPrintPrintJobs: sent data to caller");
			echo json_encode($workarray);
			
		} else {
			CommonUtils::log($pdo,"PRINTQUEUE", "getNextPickupPrintPrintJobs: wrong printcode");
			echo json_encode(array());
		}
	}
	
	function getNextFoodWorkPrintJobs($printer,$md5pass,$fl) {
		$this->getNextWorkPrintJobs($md5pass,self::$FOOD,$printer,$fl,"getNextFoodWorkPrintJobs");
	}
	
	function getNextDrinkWorkPrintJobs($printer,$md5pass,$fl) {
		$this->getNextWorkPrintJobs($md5pass,self::$DRINK,$printer,$fl,"getNextDrinkWorkPrintJobs");
	}
	
	function getNextWorkPrintJobs($md5pass,$theType,$printer,$fl,$logmsg) {
		$pdo = $this->dbutils->openDbAndReturnPdo();
		$isCorrect = $this->isPasswordCorrect($pdo,$md5pass,false);

		if ($isCorrect) {
			CommonUtils::log($pdo,"PRINTQUEUE", "$logmsg: type = $theType, printer = $printer, fl= $fl");
			
			$checkType = "f";
			if ($theType == 2) {
				$checkType = "d";
			}
			if (!$this->checkForPrinterInstance($pdo, $checkType . $printer)) {
				echo json_encode(array());
				return;
			}
			
			$this->saveLastPrintServerAccess($pdo);
			
			$bigFontWorkReceipt = $this->getBigFontWorkReceiptSetting($pdo);
			$templatekey = "foodtemplate";
			if ($theType === 2) {
			    $templatekey = "drinktemplate";
			}
			$template = $this->getTemplate($pdo, $templatekey,null);
			
			if (is_null($printer)) {
				$sql = "SELECT id,content,type FROM %printjobs% WHERE type=? AND removed is null ORDER BY id";
				$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
				$stmt->execute(array($theType));
			} else {
				$sql = "SELECT id,content,type FROM %printjobs% WHERE type=? AND removed is null AND printer=? ORDER BY id";
				$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
				$stmt->execute(array($theType,$printer));
			}
			
			$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
			CommonUtils::log($pdo,"PRINTQUEUE", "getNextWorkPrintJobs: retrieve " . count($result) . " jobs");
				
			$workarray = array();
			foreach($result as $aWorkJob) {
				$aWork = (array) json_decode($aWorkJob["content"]); // is in json format
				$ps = (array) ($aWork["products"]);
				foreach($ps as $p) {
					if (isset($p->singleprod)) {
						unset($p->singleprod);
					}
					if (isset($p->allqueueids)) {
						unset($p->allqueueids);
					}
				}
				
				CommonUtils::log($pdo,"PRINTQUEUE", "getNextWorkPrintJobs: collect work receipt with id=" . $aWorkJob["id"] . " for printer=$printer");
				
				$workid = $aWork["workid"];
				unset($aWork["workid"]);
				
				if  (($fl >= 5) &&  ($fl <= 6)) {
					$prods = (array) ($aWork["products"]);
					$targetProds = array();
					foreach ($prods as $aProd) {
						$theArrProd = (array) $aProd;
						
						$newTargetProd = array("id" => $theArrProd["id"],
						    "longname" => $theArrProd["longname"],
						    "option" => $theArrProd["option"],
						    "price" => $theArrProd["price"],
						    "extras" => $theArrProd["extras"],
						    "ordertime" => $theArrProd["ordertime"],
						    "kind" => $theArrProd["kind"],
						    "printer" => $theArrProd["printer"],
						    );
						$targetProds[] = $newTargetProd;
					}
					$aWork["products"] = $targetProds;
				} else if ($fl < 5) {
					$prods = (array) ($aWork["products"]);
					$targetProds = array();
					foreach ($prods as $aProd) {
						$theArrProd = (array) $aProd;

						$newTargetProd = array("id" => $theArrProd["id"],
						    "longname" => $theArrProd["longname"],
						    "option" => $theArrProd["option"],
						    "extras" => $theArrProd["extras"],
						    "ordertime" => $theArrProd["ordertime"],
						    "kind" => $theArrProd["kind"],
						    "printer" => $theArrProd["printer"],
						    );
						$targetProds[] = $newTargetProd;
					}
					$aWork["products"] = $targetProds;
				}
				if ($fl >= 2) {
					if ($fl >= 10) {
						$workarray[] = array("workid" => $workid,"id" => $aWorkJob["id"],"content" => $aWork, "bigfontworkreceipt" => intval($bigFontWorkReceipt), "template" => $template);
					} else {
						$workarray[] = array("id" => $aWorkJob["id"],"content" => $aWork, "bigfontworkreceipt" => intval($bigFontWorkReceipt), "template" => $template);
					}
				} else {
					// default without template
					$workarray[] = array("id" => $aWorkJob["id"],"content" => $aWork, "bigfontworkreceipt" => intval($bigFontWorkReceipt));
				}
			}
			CommonUtils::log($pdo,"PRINTQUEUE", "getNextWorkPrintJobs: sent data to caller");
			echo json_encode($workarray);
		} else {
			CommonUtils::log($pdo,"PRINTQUEUE", "getNextWorkPrintJobs: wrong printcode");
			echo json_encode(array());
		}
	}
	
	function getNextCancelFoodWorkPrintJobs($printer,$md5pass,$fl) {
		$this->getNextCancelWorkPrintJobs($md5pass,self::$CANCELFOOD,$printer,$fl);
	}
	
	function getNextCancelDrinkWorkPrintJobs($printer,$md5pass,$fl) {
		$this->getNextCancelWorkPrintJobs($md5pass,self::$CANCELDRINK,$printer,$fl);
	}
	
	function getNextCancelWorkPrintJobs($md5pass,$theType,$printer,$fl) {
		$pdo = $this->dbutils->openDbAndReturnPdo();
		$isCorrect = $this->isPasswordCorrect($pdo,$md5pass,false);

		if ($isCorrect) {
			CommonUtils::log($pdo,"PRINTQUEUE", "getNextCancelWorkPrintJobs: type = $theType, printer = $printer, fl= $fl");
			
			$checkType = "f";
			if ($theType == 6) {
				$checkType = "d";
			}
			if (!$this->checkForPrinterInstance($pdo, $checkType . $printer)) {
				echo json_encode(array());
				return;
			}
			
			$this->saveLastPrintServerAccess($pdo);
			
			$templatekey = "canceltemplate";

			$template = $this->getTemplate($pdo, $templatekey,null);
    
			if (is_null($printer)) {
				$sql = "SELECT id,content,type FROM %printjobs% WHERE type=? AND removed is null ORDER BY id";
				$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
				$stmt->execute(array($theType));
			} else {
				$sql = "SELECT id,content,type FROM %printjobs% WHERE type=? AND printer=? AND removed is null ORDER BY id";
				$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
				$stmt->execute(array($theType,$printer));
			}
			
			$result = $stmt->fetchAll();
			CommonUtils::log($pdo,"PRINTQUEUE", "getNextCancelWorkPrintJobs: retrieve " . count($result) . " jobs");
				
			$workarray = array();
			foreach($result as $aWorkJob) {
				$aWork = (array) json_decode($aWorkJob["content"],true); // is in json format
				CommonUtils::log($pdo,"PRINTQUEUE", "getNextCancelWorkPrintJobs: collect work receipt with id=" . $aWorkJob["id"] . " for printer=$printer");
				
				$workid = $aWork["workid"];
				unset($aWork["workid"]);

				$showType = self::$FOOD_PRINTSERVER;
				if ($theType == self::$CANCELDRINK) {
					$showType = self::$DRINK_PRINTSERVER;
				}
				$workarray[] = array("workid" => $workid,
				    "id" => $aWorkJob["id"],
				    "refworkid" => $aWork["refworkid"],
				    "longname" => $aWork["longname"],
				    "kind" => $showType,
				    "table" => $aWork["table"],
				    "time" => $aWork["time"],
				    "price" => $aWork["price"],
				    "extras" => $aWork["extras"],
				    "template" => $template);				
			}
			CommonUtils::log($pdo,"PRINTQUEUE", "getNextWorkPrintJobs: sent data to caller");
			echo json_encode($workarray);
		} else {
			CommonUtils::log($pdo,"PRINTQUEUE", "getNextWorkPrintJobs: wrong printcode");
			echo json_encode(array());
		}
	}
	
	function deletePrintJob($pass,$id) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$isCorrect = $this->isPasswordCorrect($pdo,$pass,false);
		if ($isCorrect) {
			$pdo = $this->dbutils->openDbAndReturnPdo();
			$this->saveLastPrintServerAccess($pdo);
			
			$sql = "SELECT type FROM %printjobs% WHERE id=?";
			$result = CommonUtils::fetchSqlAll($pdo, $sql, array($id));
			if (count($result) > 0) {
				$type = $result[0]['type'];
				if (($type == self::$FOOD) || ($type == self::$DRINK) || ($type == self::$CANCELFOOD) || ($type == self::$CANCELDRINK) || ($type == self::$PICKUP)) {
					$sql = "UPDATE %printjobs% SET removed=? WHERE id=?";
					CommonUtils::execSql($pdo, $sql, array(1,$id));
				} else {
					$sql = "DELETE FROM %printjobs% WHERE id=?";
					CommonUtils::execSql($pdo, $sql, array($id));
				}
			}

			echo json_encode(array("status" => "OK", "code" => OK, "msg" => "Druckauftrag erfolgreich gelöscht."));
		} else {
			CommonUtils::log($pdo,"PRINTQUEUE", "deletePrintJob: wrong printcode");
			echo json_encode(array("status" => "ERROR", "code" => ERROR_NOT_AUTHOTRIZED, "msg" => ERROR_NOT_AUTHOTRIZED_MSG));
		}
	}
}

class Printersizes {
	public static $JOBTYPE_RECEIPT = 1;
	public static $JOBTYPE_FOOD = 2;
	public static $JOBTYPE_DRINK = 3;
	public static $JOBTYPE_PICKUP = 4;
	
	private $receiptPrinterSizes = array();
	private $foodPrinterSizes = array();
	private $drinkPrinterSizes = array();
	private $pickupPrinterSizes = array();
	
	private $defaultSize = 32;
	
	function __construct($urlgetParam) {
		if (isset($urlgetParam['printersizes'])) {
			$printersizesParameter = $_GET['printersizes'];
			$printerParts = explode('-',$printersizesParameter);
			foreach($printerParts as $aPrinterSequence) {
				$printerSequence = explode('_',$aPrinterSequence);
				$pureSizes = array_slice($printerSequence,1,count($printerSequence)-1);
				switch(strtoupper($printerSequence[0])) {
					case "R":
						$this->receiptPrinterSizes = $pureSizes;
						break;
					case "F":
						$this->foodPrinterSizes = $pureSizes;
						break;
					case "D":
						$this->drinkPrinterSizes = $pureSizes;
						break;
					case "P":
						$this->pickupPrinterSizes = $pureSizes;
						break;
				}
			}
		}
	}
	
	public function getPrinterSize($jobtype,$printer) {
		$defaultSizes = array(32,32,32,32,32,32);
		$sizeArrayToUse = $defaultSizes;
		switch ($jobtype) {
			case self::$JOBTYPE_RECEIPT:
				$sizeArrayToUse = $this->receiptPrinterSizes;
				break;
			case self::$JOBTYPE_FOOD:
				$sizeArrayToUse = $this->foodPrinterSizes;
				break;
			case self::$JOBTYPE_DRINK:
				$sizeArrayToUse = $this->drinkPrinterSizes;
				break;
			case self::$JOBTYPE_PICKUP:
				$sizeArrayToUse = $this->pickupPrinterSizes;
				break;
			default:
				$sizeArrayToUse = $defaultSizes;
				break;
		}
		
		$printer = intval($printer)-1;
		if (count($sizeArrayToUse) >= $printer) {
			return $sizeArrayToUse[$printer];
		} else {
			return $this->defaultSize;
		}
	}
}
