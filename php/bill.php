<?php
// Datenbank-Verbindungsparameter
require_once ('dbutils.php');
require_once ('commonutils.php');
require_once ('admin.php');
require_once ('reports.php');
require_once ('utilities/pdfexport.php');
require_once ('3rdparty/phpexcel/classes/PHPExcel.php');
require_once ('3rdparty/phpqrcode.php');

define('FPDF_FONTPATH','3rdparty/fpdf/font/');

define ('DO_EXCEL',1);
define ('DO_CSV',2);

class Bill {
	var $dbutils;
	var $t;

	private $P_SUM = array("Summe:","Sum:","Todo:");
	private $P_TOTAL = array("Total","Total","Total");
	private $P_MWST = array("MwSt","Tax","IVA");
	private $P_NETTO = array("Netto","Net","Neto");
	private $P_BRUTTO = array("Brutto","Gross","Bruto");
	private $P_ID = array("Id:","Id:","Id:");
	private $P_TABLE = array("Tisch:","Table:","Mesa:");
	private $P_WAITER = array("Es bediente Sie:", "Waiter:", "Camarero:");
	private $P_NO = array("Anz.", "No.", "Nú.");
	private $P_DESCR = array("Beschreibung","Description","Descripción");
	private $P_PRICE = array("Preis","Price","Precio");
	
	private static $daynamesStartSunday = array(
		array("Sonntag","Sunday","Domingo"),
		array("Montag","Monday","Lunes"),
		array("Dienstag","Tuesday","Martes"),
		array("Mittwoch","Wednesday","Miércoles"),
		array("Donnerstag","Thursday","Jueves"),
		array("Freitag","Friday","Viernes"),
		array("Samstag","Saturday","Sábado")	
	);
	
	public static $CASHTYPE_Privatentnahme = array("value" => 1,"name" => "Privatentnahme");
	public static $CASHTYPE_Privateinlage = array("value" => 2,"name" => "Privateinlage");
	public static $CASHTYPE_Geldtransit = array("value" => 3,"name" => "Geldtransit");
	private static $CASHTYPE_Lohnzahlung = array("value" => 4,"name" => "Lohnzahlung");
	public static $CASHTYPE_Einzahlung = array("value" => 5,"name" => "Einzahlung");
	public static $CASHTYPE_Auszahlung = array("value" => 6,"name" => "Auszahlung");
	public static $CASHTYPE_TrinkgeldAN = array("value" => 7,"name" => "Trinkgeld an Arb.nehmer");
	public static $CASHTYPE_TrinkgeldAG = array("value" => 8,"name" => "Trinkgeld an Arb.geber");
	public static $CASHTYPE_TrinkgeldBoth = array("value" => 9,"name" => "Trinkgeld");
        public static $CASHTYPE_DifferenzSollIst = array("value" => 10,"name" => "DifferenzSollIst");
        
	private static $TSE_FORMAT_QR_TEXT = 1;
	private static $TSE_FORMAT_ASSOC_ARRAY = 2;

	function __construct() {
		$this->dbutils = new DbUtils();
		require_once 'translations.php';
	}
	
	function handleCommand($command) {
		$cmdsThatNeedAdminOrManagerRights = array('exportCsv','exportAllCsv','exportAllExcel','exportXlsx','exportPdfReport','exportPdfReportClosPeriod',
		    'exportPdfSummary','exportPdfSummaryClosPeriod','exportCsvSummaryClosPeriod');
		if (in_array($command, $cmdsThatNeedAdminOrManagerRights)) {
			if (!$this->hasCurrentUserAdminOrManagerRights()) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_BILL_NOT_AUTHOTRIZED, "msg" => ERROR_BILL_NOT_AUTHOTRIZED_MSG));
				return;
			}
		}
		
		if ($command == 'billqrcode') {
			return self::outputBillQrCode($_GET["billid"]);
		}
		if ($command == 'exportCsv') {
			$this->exportCsv($_GET['startMonth'],$_GET['startYear'],$_GET['endMonth'],$_GET['endYear'],DO_CSV);
			return;
		}
		if ($command == 'exportAllCsv') {
			$this->exportAllCsvOrExcel($_GET['startMonth'],$_GET['startYear'],$_GET['endMonth'],$_GET['endYear'],DO_CSV);
			return;
		}
		if ($command == 'exportAllExcel') {
			$this->exportAllCsvOrExcel($_GET['startMonth'],$_GET['startYear'],$_GET['endMonth'],$_GET['endYear'],DO_EXCEL);	
			return;
		}
		if ($command == 'exportXlsx') {
			$this->exportCsv($_GET['startMonth'],$_GET['startYear'],$_GET['endMonth'],$_GET['endYear'],DO_EXCEL);
			return;
		}
		if ($command == 'exportPdfReport') {
			$this->exportPdfReport($_GET['startMonth'],$_GET['startYear'],$_GET['endMonth'],$_GET['endYear'],null,null);
			return;
		}
		if ($command == 'exportPdfReportClosPeriod') {
			$this->exportPdfReport(0,0,0,0,$_GET['closidstart'],$_GET['closidend']);
			return;
		}
		if ($command == 'exportPdfSummary') {
			$this->exportPdfSummary($_GET['startMonth'],$_GET['startYear'],$_GET['endMonth'],$_GET['endYear']);
			return;
		}
		
		if ($command == 'exportPdfSummaryClosPeriod') {
			$this->exportPdfSummaryClosPeriod($_GET['closidstart'],$_GET['closidend']);
			return;
		}
		if ($command == 'exportCsvSummaryClosPeriod') {
			$this->exportCsvSummaryClosPeriod($_GET['closidstart'],$_GET['closidend']);
			return;
		}
		if ($command == 'autoBackupPdfSummary') {
			$this->autoBackupPdfSummary($_POST['remoteaccesscode']);
			return;
		}
		
		if ($command == 'exportCsvOfClosing') {
			if ($this->hasCurrentUserAdminOrManagerRights()) {
				$this->exportCsvOfClosing($_GET['closingid'],DO_CSV);
			} else {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_MANAGER_NOT_AUTHOTRIZED, "msg" => ERROR_MANAGER_NOT_AUTHOTRIZED_MSG));
			}
			return;
		}
		if ($command == 'exportXlsxOfClosing') {
			if ($this->hasCurrentUserAdminOrManagerRights()) {
				$this->exportCsvOfClosing($_GET['closingid'],DO_EXCEL);
			} else {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_MANAGER_NOT_AUTHOTRIZED, "msg" => ERROR_MANAGER_NOT_AUTHOTRIZED_MSG));
			}
			return;
		}
		if ($command == 'doCashAction') {
			if ($this->hasCurrentUserPaydeskRights()) {
			    $remark = "";
			    $cashtype = 1;
			    if(isset($_POST["remark"])) {
				    $remark = $_POST['remark'];
			    }
			    if(isset($_POST["cashtype"])) {
				    $cashtype = $_POST['cashtype'];
			    }
			    self::doCashAction($_POST['money'],$remark,$cashtype);
			} else {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_PAYDESK_NOT_AUTHOTRIZED, "msg" => ERROR_PAYDESK_NOT_AUTHOTRIZED_MSG));
			}
			return;
		} else if ($command == 'getCashOverviewOfUser') {
			if ($this->hasCurrentUserPaydeskRights()) {
				$this->getCashOverviewOfUser();
			} else {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_PAYDESK_NOT_AUTHOTRIZED, "msg" => ERROR_PAYDESK_NOT_AUTHOTRIZED_MSG));	
			}
			return;
		} else if ($command == 'createpaidguestbill') {
                        if ($this->hasCurrentUserPaydeskRights()) {
                                $billids_array_str = $_GET['billids'];
                                $paymentId = $_GET['paymentid'];
                                self::createPaidGuestBill($billids_array_str, $paymentId);
                        } else {
                                echo json_encode(array("status" => "ERROR", "code" => ERROR_PAYDESK_NOT_AUTHOTRIZED, "msg" => ERROR_PAYDESK_NOT_AUTHOTRIZED_MSG));
                        }
                        return;
                }
		
		if ($command == 'changeBillHost') {
			if ($this->hasCurrentUserPaydeskRights()) {
				$pdo = DbUtils::openDbAndReturnPdoStatic();
				$this->changeBillHost($pdo,$_POST['billid'],$_POST['isNowHost']);
			} else {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_PAYDESK_NOT_AUTHOTRIZED, "msg" => ERROR_PAYDESK_NOT_AUTHOTRIZED_MSG));
			}
			return;
		}
                if ($command == 'changepayment') {
			if ($this->hasCurrentUserPaydeskRights()) {
				$pdo = DbUtils::openDbAndReturnPdoStatic();
				$this->changepayment($pdo,$_POST['billid'],$_POST['newpaymentid']);
			} else {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_PAYDESK_NOT_AUTHOTRIZED, "msg" => ERROR_PAYDESK_NOT_AUTHOTRIZED_MSG));
			}
			return;
		}
		if ($command == 'initaustriareceipt') {
			if ($this->hasCurrentUserAdminOrManagerRights()) {
				$pdo = DbUtils::openDbAndReturnPdoStatic();
				$status = $this->initaustriareceipt($pdo);
				echo json_encode($status);
				return;
			} else {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_MANAGER_NOT_AUTHOTRIZED, "msg" => ERROR_MANAGER_NOT_AUTHOTRIZED_MSG));
			}
			return;
		}
		
		if ($command == 'initCardPayment') {
			if ($this->hasCurrentUserPaydeskRights()) {
				$pdo = DbUtils::openDbAndReturnPdoStatic();
				$retVal = $this->initCardPayment($pdo,$_POST['billid']);
				echo json_encode($retVal);
				return;
			} else {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_PAYDESK_NOT_AUTHOTRIZED, "msg" => ERROR_PAYDESK_NOT_AUTHOTRIZED_MSG));
				return false;
			}
			return;
		}
		
		if ($command == 'cancelCardPayment') {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$retVal = $this->cancelCardPayment($pdo,$_POST['billid'],$_POST['randvalue']);
			echo json_encode($retVal);
			return;
		}
                
                
		if ($this->hasCurrentUserBillRights()) {
			if ($command == 'getLastBillsWithoutContent') {
                                $userid = 0;
                                if (isset($_GET['showonlymybills']) && ($_GET['showonlymybills'] == 1)) {
                                        $userid = $this->getUserId();
                                }
                                $this->getLastBillsWithContent($_GET['day'],$_GET['month'],$_GET['year'],false,$userid);
                        } else if ($command == 'getBillContentForBillsView') {
                                echo json_encode($this->getBillContentForBillsView($_GET['id']));
			} else if ($command == 'cancelBill') {
				$pdo = DbUtils::openDbAndReturnPdoStatic();
				$guestAssignedAndPaid = self::isBillAssignedToGuestAndPaid($pdo, $_POST['billid']);
				if (!is_null($guestAssignedAndPaid)) {
					$msg = "Rechnung ist dem Gast '$guestAssignedAndPaid' zugewiesen und als bezahlt deklariert. Bezahlstatus muss vor einem Bonstorno in der Gastansicht geändert werden.";
					echo json_encode(array("status" => "ERROR", "code" => ERROR_BILL_GUEST_ASSIGNED_AND_PAID, "msg" => $msg, "customer" => $guestAssignedAndPaid));
					return;
				}
					$cancelTip = 0;
					if (isset($_POST['canceltip'])) {
							$cancelTip = $_POST['canceltip'];
					}
				$this->cancelBill($pdo,$_POST['billid'],$_POST['stornocode'],$_POST['reason'],true,true,true,$_POST['removeproducts'],null,$cancelTip);
				return;
			} else if ($command == 'austriazeroreceipt') {
                                $pdo = DbUtils::openDbAndReturnPdoStatic();
                                echo json_encode($this->createZeroReceipt($pdo));
                        }
		} else {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_BILL_NOT_AUTHOTRIZED, "msg" => ERROR_BILL_NOT_AUTHOTRIZED_MSG));
		}
	}

	private function hasCurrentUserBillRights() {
		session_start();
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			return false;
		} else {
			return ($_SESSION['right_bill']);
		}
	}
	
	private function hasCurrentUserPaydeskRights() {
		session_start();
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			return false;
		} else {
			return ($_SESSION['right_paydesk']);
		}
	}
	
	private function hasCurrentUserAdminOrManagerRights() {
		session_start();
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			return false;
		} else {
			return ($_SESSION['right_manager'] || $_SESSION['is_admin']);
		}
	}
        
	// This method is for bills which are not from type CASH! -> do the rest also in other method
	public static function signOrdersBill($pdo,$billid,$isCancellation) {
		$sign = "";
		if ($isCancellation) {
			$sign = "0-";
		}

		$sqlNormTax = "SELECT CAST(ROUND($sign COALESCE(SUM(price),'0.00'),2) as DECIMAL(12,2)) as sumpriceforcertaintax FROM %queue% Q,%billproducts% BP WHERE BP.billid=? AND Q.id=BP.queueid and Q.taxkey in ('1','11','21')";
		$resultNormTax = CommonUtils::fetchSqlAll($pdo, $sqlNormTax, array($billid));
		$normTaxSum = $resultNormTax[0]['sumpriceforcertaintax'];

		$sqlErmTax = "SELECT CAST(ROUND($sign COALESCE(SUM(price),'0.00'),2) as DECIMAL(12,2)) as sumpriceforcertaintax FROM %queue% Q,%billproducts% BP WHERE BP.billid=? AND Q.id=BP.queueid and Q.taxkey in ('2','12','22')";
		$resultErmTax = CommonUtils::fetchSqlAll($pdo, $sqlErmTax, array($billid));
		$ermTaxSum = $resultErmTax[0]['sumpriceforcertaintax'];
		$sqlNullTax = "SELECT CAST(ROUND($sign COALESCE(SUM(price),'0.00'),2) as DECIMAL(12,2)) as sumpriceforcertaintax FROM %queue% Q,%billproducts% BP WHERE BP.billid=? AND Q.id=BP.queueid and Q.taxkey in ('5')";
		$resultNullTax = CommonUtils::fetchSqlAll($pdo, $sqlNullTax, array($billid));
		$nullTaxSum = $resultNullTax[0]['sumpriceforcertaintax'];
		
                $operationLabel = "Beleg";
		$sql = "SELECT brutto,paymentid FROM %bill% WHERE id=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($billid))[0];
		$brutto = $result["brutto"];
		$paymentid = $result["paymentid"];
                
                $paymentSign = (new Paymentinfo())->getPaymentTypeAsString($pdo, $paymentid, false);
		
                $signStr = $operationLabel . "^" . implode('_',array($normTaxSum,$ermTaxSum,'0.00','0.00',$nullTaxSum)) . "^";
                
		$currency = CommonUtils::getConfigValue($pdo, 'currency', 'EUR');
		$curSign = "";
		if (!in_array(strtoupper($currency), array("EURO","EUR","E","€"))) {
			$curSign = ":" . $currency;
		}
		
		$signStr .= $brutto . ":" . $paymentSign . $curSign;
		return self::signValueByTseAndUpdateBill($pdo, $billid, $signStr);
	}
	
	private static function signValueByTseAndUpdateBill($pdo,$billid,$valueToSign) {
		$tseAnswer = TSE::sendNormalBillToTSE($pdo, $valueToSign);
		if ($tseAnswer["status"] != "OK") {
			return(array("status" => "ERROR","msg" => "TSE-Signierung fehlgeschlagen. Vorgang konnte nicht ausgeführt werden."));
		} else {
			$logtime = 0;
			$trans = 0;
			$tseSignature = '';
			$pubkeyRef = null;
			$sigalgRef = null;
			$sigcounter = 0;
			$serialNoRef = null;
			$certificateRef = null;
				
			if ($tseAnswer["usetse"] == DbUtils::$TSE_OK) {
				$logtime = $tseAnswer["logtime"];
				$trans = $tseAnswer["trans"];
				$sigcounter = $tseAnswer["sigcounter"];
				$tseSignature = $tseAnswer["signature"];
				$sigalgRef = CommonUtils::referenceValueInTseValuesTable($pdo, $tseAnswer["sigalg"]);
				$pubkeyRef = CommonUtils::referenceValueInTseValuesTable($pdo, $tseAnswer["publickey"]);
				$serialNoRef = CommonUtils::referenceValueInTseValuesTable($pdo, $tseAnswer["serialno"]);
				$certificateRef = CommonUtils::referenceValueInTseValuesTable($pdo, $tseAnswer["certificate"]);
			}

			$opid = Operations::createOperation(
				$pdo, 
				DbUtils::$PROCESSTYPE_BELEG, 
				DbUtils::$OPERATION_IN_BILL_TABLE,
				$logtime,
				$trans,
				$valueToSign,
				$tseSignature,
				$pubkeyRef,
				$sigalgRef,
				$serialNoRef,
				$certificateRef,
				$sigcounter,
				$tseAnswer["usetse"]);

			$sql = "UPDATE %bill% SET opid=?,logtime=? WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array($opid,$logtime, $billid));
			
			return array("status" => "OK");
		}
	}
	
	public static function isBillPaidByCard($pdo,$billid) {
		$sql = "SELECT paymentid FROM %bill% WHERE id=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($billid));
		if (count($result) > 0) {
			$paymentid = $result[0]["paymentid"];
			if (($paymentid == DbUtils::$PAYMENT_EC) || ($paymentid == DbUtils::$PAYMENT_KREDIT)) {
				return true;
			} else {
				return false;
			}
		}
		return false;
	}
	
	public function initCardPayment($pdo,$billid) {
		$randval = md5(rand(0,  getrandmax()));
		$sql = "INSERT INTO %work% (item,value,signature) VALUES(?,?,?)";
		CommonUtils::execSql($pdo, $sql, array("sumuphash",$billid,$randval));
		return array("status" => "OK","msg" => $randval);
	}
	
	public function cancelCardPayment($pdo,$billid,$randVal) {
		$sql = "SELECT count(id) as countid FROM %work% WHERE item=? AND value=? AND signature=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array("sumuphash",$billid,$randVal));
		$countid = $result[0]["countid"];
		if ($countid != 1) {
			return array("status" => "ERROR","msg" => "Keine Authorisierung zum Stornieren der Rechnung");
		} else {
			
			$failuretext = CommonUtils::getConfigValue($pdo, "sumupfailuretext", "");
			$ok = $this->cancelBill($pdo, $billid, '', $failuretext, true, false, false, 0,null,1);
			
			if ($ok) {
				$sql = "DELETE FROM %work% WHERE item=? AND value=? AND signature=?";
				CommonUtils::execSql($pdo, $sql, array("sumuphash",$billid,$randVal));
				return array("status" => "OK");
			} else {
				return array("status" => "ERROR","msg" => "Stornierung fehlgeschlagen");
			}
		}
	}
	
	
	function billIsCancelled($pdo,$billid) {
		$sql = "SELECT status FROM %bill% WHERE id=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($billid));
		$row = $stmt->fetchObject();
		$status = $row->status;
		$ret = false;
		if (($status == "x") || ($status == "s")) {
			$ret = true;
		}
		return $ret;
	}
	
	private function createBillOverallInfo($billid,$billuid,$thetimedate,$tablename,$currency,$brutto,$netto,$username,$fullname,$userid,$printer,$host,$masterData,$tseInfo,?int $austria=null, ?Fiskalysignresponse $fiskalyResponse=null) {
		$thetimedate_arr = explode ( ' ', $thetimedate );
		$thedate = $thetimedate_arr[0];
		$datearr = explode ( '-', $thedate );
		$day = sprintf("%02s", $datearr[2]);
		$month = sprintf("%02s", $datearr[1]);
		$year = sprintf("%04s", $datearr[0]);
		$thetime = $thetimedate_arr[1];
		$thetimearr = explode ( ':', $thetime );
		$hour = $thetimearr[0];
		$min = $thetimearr[1];
		$thetimedate = "$day.$month.$year $hour:$min";

		$hosttext = "";
		if ($host == 1) {
			$hosttext = $masterData["hosttext"];
		}
			
		$billoverallinfo = array(
				"id" => $billid,
				"billuid" => $billuid,
				"billdate" => $thetimedate,
				"billday" => $day,
				"billmonth" => $month,
				"billyear" => $year,
				"billhour" => $hour,
				"billmin" => $min,
				"brutto" => $brutto,
                                "bruttowithcurrency" => $brutto . " " . $currency,
                                "currency" => $currency,
				"netto" => $netto,
				"table" => $tablename,
				"username" => $username,
                                "fullname" => $fullname,
				"userid" => $userid,
				"printer" => $printer,
				"host" => $host,
				"sn" => $masterData["sn"],
				"uid" => $masterData["uid"],
				"version" => $masterData["version"],
				"companyinfo" => $masterData["companyinfo"],
				"systemid" => $masterData["systemid"],
		    
				"dsfinvk_name" => $masterData["dsfinvk_name"],
				"dsfinvk_street" => $masterData["dsfinvk_street"],
				"dsfinvk_postalcode" => $masterData["dsfinvk_postalcode"],
				"dsfinvk_city" => $masterData["dsfinvk_city"],
				"dsfinvk_country" => $masterData["dsfinvk_country"],
				"dsfinvk_stnr" => $masterData["dsfinvk_stnr"],
				"dsfinvk_ustid" => $masterData["dsfinvk_ustid"],
		    
				"template" => $masterData["template"],
				"hospitality" => $hosttext
		);
                if (($austria == 1) && (!is_null($fiskalyResponse))) {
                        $billoverallinfo['austria'] = 1;
                        $billoverallinfo['fiskalysigned'] = ($fiskalyResponse->isSigned() ? 1:0);
                        $billoverallinfo['fiskalyreceiptnumber'] = $fiskalyResponse->getReceiptNumber();
                        $billoverallinfo['fiskalytimesignature'] = $fiskalyResponse->getTimeSignature();
                        $billoverallinfo['fiskalycashregserialno'] = $fiskalyResponse->getCashRegSerNo();
                        $billoverallinfo['fiskalyqrcode'] = $fiskalyResponse->getQrCode();
                }
       
		if (is_null($tseInfo)) {
			$billoverallinfo["tsestatus"] = 0;
		} else {
			$billoverallinfo["tsestatus"] = $tseInfo["tsestatus"];
			$billoverallinfo["tseserialno"] = $tseInfo["tseserialno"];
			$billoverallinfo["transnumber"] = $tseInfo["transnumber"];
			$billoverallinfo["sigcounter"] = $tseInfo["sigcounter"];
			$billoverallinfo["startlogtime"] = $tseInfo["startlogtime"];
			$billoverallinfo["logtime"] = $tseInfo["logtime"];
			$billoverallinfo["sigalg"] = $tseInfo["sigalg"];
			$billoverallinfo["logtimeformat"] = $tseInfo["logtimeformat"];
			$billoverallinfo["tsesignature"] = $tseInfo["tsesignature"];
			$billoverallinfo["pubkey"] = $tseInfo["pubkey"];
		}

		return $billoverallinfo;
	}
		
	private function createBillTranslations($language) {
		$billtranslations = array(
					"sum" => $this->P_SUM[$language],
					"total" => $this->P_TOTAL[$language],
					"mwst" => $this->P_MWST[$language],
					"netto" => $this->P_NETTO[$language],
					"brutto" => $this->P_BRUTTO[$language],
					"id" => $this->P_ID[$language],
					"table" => $this->P_TABLE[$language],
					"waiter" => $this->P_WAITER[$language],
					"no" => $this->P_NO[$language],
					"descr" => $this->P_DESCR[$language],
					"price" => $this->P_PRICE[$language]
			);
		return $billtranslations;
	}
	
	public function getCashBillTicket($pdo,$billid) {
		$readableCashType = self::getTranslateSqlCashTypeToReadable();
                $currency = CommonUtils::getConfigValue($pdo, 'currency', '');
                
                $decPoint = "(SELECT setting from %config% where name='decpoint')";
                $sqlBrutto = "REPLACE(brutto,'.',$decPoint)";
                $sqlNetto = "REPLACE(netto,'.',$decPoint)";
		$sql = "SELECT CONCAT('V-',billuid) as billuid,username,COALESCE(fullname,username) as fullname,U.id as userid,billdate,$sqlBrutto as brutto,$sqlNetto as netto,reason,$readableCashType FROM %user% U,%bill% B WHERE B.userid=U.id and B.id=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($billid));
		$billuid = $result[0]['billuid'];
		$username = $result[0]['username'];
                $fullname = $result[0]['fullname'];
		$userid = $result[0]['userid'];
		$thetimedate = $result[0]['billdate'];
		$brutto = $result[0]['brutto'];
		$netto = $result[0]['netto'];;
		$masterData = self::getBillMasterDataAtBillTime($pdo,$billid,'cashtemplate');
		$tseInfo = self::getTseInfoOfBill($pdo, $billid, self::$TSE_FORMAT_ASSOC_ARRAY);
		$ticket = $this->createBillOverallInfo($billuid,$billuid,$thetimedate,'',$currency,$brutto,$netto,$username,$fullname,$userid,'',0,$masterData,$tseInfo,null,null);
		$ticket['reason'] = $result[0]['reason'];
		$ticket['cashtype'] = $result[0]['cashtype'];

		return $ticket;
	}
	
        public static function getBillUidSqlPart() {
                $billuidSql = "(CASE WHEN B.status='c' THEN CONCAT('V-',B.billuid) "
                        . "WHEN B.paymentid='" . DbUtils::$PAYMENT_GUEST . "' THEN CONCAT('G-',B.billuid) "
                        . "ELSE B.billuid "
                        . "END) ";
                return $billuidSql;
        }
        
	public function getBillWithIdAsTicket($pdo,$billid) {
		set_time_limit(120);

		$commonUtils = new CommonUtils();
		$correct = $commonUtils->verifyBill($pdo, $billid);
		if (!$correct) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_INCONSISTENT_DB, "msg" => ERROR_INCONSISTENT_DB_MSG));
			return;
		}
		
		$decpoint = CommonUtils::getConfigValue($pdo, 'decpoint', ".");
                $currency = CommonUtils::getConfigValue($pdo, 'currency', '');
                $austria = CommonUtils::getConfigValue($pdo, 'austria', 0);
                $fiskalySignResponse = new Fiskalysignresponse();
                $fiskalySignResponse->fetchValuesFromDb($pdo, $billid);
		
		
		$sql = "SELECT tableid,status FROM %bill% WHERE id=?";
		$row = CommonUtils::getRowSqlObject($pdo, $sql,array($billid));
		if ($row->status == 'c') {
			return $this->getCashBillTicket($pdo,$billid);
		}
		
		$sql = "SELECT count(id) as countid FROM %queue% WHERE billid=?";
		$qrow = CommonUtils::getRowSqlObject($pdo, $sql, array($billid));
                
                $billuidSql = self::getBillUidSqlPart();
		$tableid = $row->tableid;
                
		if ($qrow->countid == 0) {
			if ($tableid == 0) {
				$sql = "SELECT DISTINCT opid,$billuidSql as billuid,billdate,brutto,CAST(ROUND(netto,2) as DECIMAL(12,2)) as netto,'-' as tablename,username,COALESCE(fullname,username) as fullname,%user%.id as userid,host,COALESCE(B.status,'') as status,guestinfo,paymentid,printextras FROM %bill% B,%user% WHERE B.id=? AND userid=%user%.id AND tableid='0' ";
			} else {
				$sql = "SELECT DISTINCT opid,$billuidSql as billuid,billdate,brutto,CAST(ROUND(netto,2) as DECIMAL(12,2)) as netto,tableno as tablename,username,COALESCE(fullname,username) as fullname,%user%.id as userid,host,COALESCE(B.status,'') as status,guestinfo,paymentid,printextras FROM %bill% B,%user%,%resttables% WHERE B.id=? AND userid=%user%.id AND tableid=%resttables%.id ";
			}
		} else {
			if ($tableid == 0) {
				$sql = "SELECT DISTINCT opid,$billuidSql as billuid,billdate,brutto,CAST(ROUND(netto,2) as DECIMAL(12,2)) as netto,'-' as tablename,username,COALESCE(fullname,username) as fullname,%user%.id as userid,host,COALESCE(B.status,'') as status,guestinfo,paymentid,printextras FROM %bill% B,%user%,%queue% WHERE B.id=? AND B.id=%queue%.billid AND userid=%user%.id AND tableid='0' ";
			} else {
				$sql = "SELECT DISTINCT opid,$billuidSql as billuid,billdate,brutto,CAST(ROUND(netto,2) as DECIMAL(12,2)) as netto,tableno as tablename,username,COALESCE(fullname,username) as fullname,%user%.id as userid,host,COALESCE(B.status,'') as status,guestinfo,paymentid,printextras FROM %bill% B,%user%,%resttables%,%queue% WHERE B.id=? AND B.id=%queue%.billid AND userid=%user%.id AND tableid=%resttables%.id ";
			}
		}
		$row = CommonUtils::getRowSqlObject($pdo, $sql, array($billid));

		$status = $row->status;
		$sign = ($status == "s" ? "-" : "");
		
		if ($tableid != 0) {
			$sql = "SELECT abbreviation FROM %room%,%resttables% WHERE %resttables%.id=? AND %resttables%.roomid=%room%.id";
			$trow = CommonUtils::getRowSqlObject($pdo, $sql, array($tableid));
			if (is_null($trow->abbreviation) || ($trow->abbreviation == '')) {
				$tablename = $row->tablename;
			} else {
				$tablename = $trow->abbreviation . "-" . $row->tablename;
			}
		} else {
			$tablename = "-";
		}
		
		if ($row == null) {
			echo json_encode(array("job" => array()));
			return;
		} else {
			if (is_null($row->host)) {
				$host = 0; // default
			} else {
				$host = $row->host;
			}
			
			$tseInfo = self::getTseInfoOfBill($pdo, $billid, self::$TSE_FORMAT_ASSOC_ARRAY);
		
			$thetimedate = $row->billdate;
			$printextrasOfReceipt = $row->printextras;
			$billuid = $row->billuid;
			$printExtras = false;
			if ($printextrasOfReceipt == 1) {
				$printExtras = true;
			}
			$masterData = self::getBillMasterDataAtBillTime($pdo,$billid,'rectemplate');
			// str_replace(".",$decpoint,$netto)
			$overallBrutto = str_replace(".",$decpoint,$row->brutto);
			$overallNetto = str_replace(".",$decpoint,$row->netto);
			
			$billoverallinfo = $this->createBillOverallInfo($billid,$billuid,$thetimedate,$tablename,$currency,$overallBrutto,$overallNetto,$row->username,$row->fullname,$row->userid,0,$host,$masterData,$tseInfo,$austria,$fiskalySignResponse);

			// rem* guestinfo
			if (is_null($row->guestinfo)) {
				$billoverallinfo["guestinfo"] = '';
			} else {
				$billoverallinfo["guestinfo"] = $row->guestinfo;
			}
			
                        $billlanguage = $masterData["billlanguage"];
                        $paymentid = $row->paymentid;
                        $billoverallinfo["payment"] = Admin::getPaymentwayName($pdo,$paymentid,$billlanguage);
			$billoverallinfo["paymentid"] = $paymentid;
		}

		$result = self::getBillProductsInCumulatedOrder($pdo, $billid);
	
		$prodarray = array();
		foreach($result as $zeile) {
			$productname = $zeile['productname'];
			if ($zeile["togo"] == 1) {
				$productname = "To-Go: " . $productname;
			}
			$pricelevel = $zeile['pricelevelname'];
			if ($pricelevel != "A") {
				$productname .= " (" . $pricelevel . ")";
			}
			$prodarray[] = array("count" => $zeile['count'],
					"productname" => $productname,
					"pricelevel" => $zeile['pricelevelname'],
					"price" => $sign . str_replace(".",$decpoint,$zeile['price']),
                                        "netto" => $sign . str_replace(".",$decpoint,$zeile['netto']),
					"total" => $sign . str_replace(".",$decpoint,$zeile['total']),
                                        "totalnetto" => $sign . str_replace(".",$decpoint,$zeile['totalnetto'])
			);
			
			if ($printExtras) {
				$extrasConcatStr = $zeile['concatstr'];
				if (!is_null($extrasConcatStr)) {
					$singleExtras = explode(',', $extrasConcatStr);
					
					$printextraprice = CommonUtils::getConfigValue($pdo, 'printextraprice', 1);
										
					$sql = "SELECT name FROM %extras% WHERE id=?";
					if ($printextraprice == 1) {
						$decpoint = CommonUtils::getConfigValue($pdo, 'decpoint', ';');
						if (($decpoint != '.') && ($decpoint != ',')) {
							$decpoint = ',';
						}
						$sql = "SELECT CONCAT(name,' (',REPLACE(price,'.','$decpoint'),')') as name FROM %extras% WHERE id=?";
					}
					
					
					foreach ($singleExtras as $aSingleExtra) {
						$singleExtraParts = explode('-', $aSingleExtra);
						$amount = $singleExtraParts[0];
						$singleExtraId = $singleExtraParts[1];
						$extraNameRes = CommonUtils::fetchSqlAll($pdo, $sql, array($singleExtraId));
						if (count($extraNameRes) > 0) {
							$extraText = $amount . "x " . $extraNameRes[0]["name"];
							$prodarray[] = array("count" => ' ',
								"productname" => ' + ' . $extraText,
								"pricelevel" => $zeile['pricelevelname'],
								"price" => ' ',
                                                                "netto" => ' ',
								"total" => ' ',
                                                                "totalnetto" => ' '
								);
						}
					}
				}
			}
		}

		$taxSql = "tax";
		$mwstSql = "concat('$sign',round(sum(price) - sum(price / (1.0 + tax/100.0)),2))";
		$nettoSql = "concat('$sign',round(sum(price / (1.0 + tax/100.0)),2))";
		$bruttoSql = "concat('$sign',sum(price))";
		if (($decpoint == '.') || ($decpoint == ',')) {
			// REPLACE("XYZ FGH XYZ", "X", "M")
			$taxSql = "REPLACE($taxSql,'.','$decpoint')";
			$mwstSql = "REPLACE($mwstSql,'.','$decpoint')";
			$nettoSql = "REPLACE($nettoSql,'.','$decpoint')";
			$bruttoSql = "REPLACE($bruttoSql,'.','$decpoint')";
		}
		
		$sql = "select $taxSql as tax,$mwstSql as mwst, $nettoSql as netto, $bruttoSql as brutto FROM %queue%,%billproducts% WHERE %billproducts%.billid=? AND %billproducts%.queueid=%queue%.id group by tax ORDER BY tax";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($billid));
		
		$billoverallinfo["products"] = $prodarray;
		$billoverallinfo["taxes"] = $result;
		return $billoverallinfo;
	}
	
	public static function setPositionsOfProductsOnBillProductsTable($pdo,$billid) {
		$billarticles = self::getBillProductsInCumulatedOrder($pdo, $billid);
		$pos = 1;
		foreach ($billarticles as $billentry) {
			$queueids = $billentry["queueids"];
			$queueidsArr = explode(',', $queueids);
			$sql = "UPDATE %billproducts% BP SET position=? WHERE billid=? AND queueid=?";
			foreach($queueidsArr as $aQueueid) {
				CommonUtils::execSql($pdo, $sql, array($pos,$billid,$aQueueid));
			}
			$pos++;
		}
	}
	
	private static function getBillProductsInCumulatedOrder($pdo,$billid) {
		$billprintextrasSql = CommonUtils::fetchSqlAll($pdo, "SELECT printextras FROM %bill% WHERE id=?", array($billid));
		$printExtras = $billprintextrasSql[0]["printextras"];
		$unit = CommonUtils::caseOfSqlUnitSelection($pdo);
		$sql = "select CONCAT($unit,PN.name) as productname,price,CAST(ROUND(Q.price / (1.0 + Q.tax/100.0),2) as DECIMAL(12,2)) as netto,(count(PN.name) * price) as total,"
                        . "CAST(ROUND((count(PN.name) * (Q.price / (1.0 + Q.tax/100.0))),2) as DECIMAL(12,2)) as totalnetto,"
                        . "%pricelevel%.name as pricelevelname,togo,count(PN.name) as count,%prodtype%.kind as kind,GROUP_CONCAT(Q.id) as queueids ";
		$sql .= " FROM %queue% Q,%prodnames% PN,%pricelevel%,%billproducts%,%prodtype%,%products% WHERE PN.id=Q.prodnameid AND %billproducts%.billid=? AND %billproducts%.queueid=Q.id ";
		$sql .= " AND Q.pricelevel = %pricelevel%.id AND Q.productid = %products%.id AND %products%.category = %prodtype%.id ";
		$sql .= " GROUP BY kind, CONCAT($unit,PN.name),price,pricelevelname,togo ";
		$sql .= " ORDER BY kind, CONCAT($unit,PN.name),price,pricelevelname,togo ";

		if ($printExtras == 1) {
			$sql = "SELECT 
				CONCAT($unit,PN.name) as productname,
				price,
                                CAST(ROUND(Q.price / (1.0 + Q.tax/100.0),2) as DECIMAL(12,2)) as netto,
				(count(PN.name) * price) as total,
                                CAST(ROUND((count(PN.name) * (Q.price / (1.0 + Q.tax/100.0))),2) as DECIMAL(12,2)) as totalnetto,
				PL.name as pricelevelname,
				togo,
				count(PN.name) as count,
				PT.kind as kind,
				GROUP_CONCAT(Q.id) as queueids,
				(
				    SELECT GROUP_CONCAT(CONCAT(amount,'-',extraid) ORDER BY extraid)
				    FROM
					%queueextras% QE
				    WHERE
					Q.id=QE.queueid
				) as concatstr
			     FROM
				%queue% Q,
                                %prodnames% PN,
				%pricelevel% PL,
				%billproducts% BP,
				%prodtype% PT,
				%products% P
			     WHERE
                               Q.prodnameid=PN.id AND
			       BP.billid=? AND 
			       BP.queueid=Q.id AND 
			       Q.pricelevel = PL.id AND 
			       Q.productid = P.id AND 
			       P.category = PT.id 
			     GROUP BY 
				kind,
				CONCAT($unit,PN.name),
				price,
                                CAST(ROUND(Q.price / (1.0 + Q.tax/100.0),2) as DECIMAL(12,2)) ,
				pricelevelname,
				togo,
				concatstr 
			    ORDER BY kind, CONCAT($unit,PN.name),price,pricelevelname,togo 
			";
		
		}
		return CommonUtils::fetchSqlAll($pdo, $sql, array($billid));
	}
	
	/**
	 * get the content of a bill (to be used for printserver etc.)
	 * 
	 * @param int $billid
	 */
	function getBillWithId($pdo,$billid,$language,$printer,$includeGuestInfo = false,$includePayment = false) {
		set_time_limit(120);

		$commonUtils = new CommonUtils();
		$correct = $commonUtils->verifyBill($pdo, $billid);
		if (!$correct) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_INCONSISTENT_DB, "msg" => ERROR_INCONSISTENT_DB_MSG));
			return;
		}
                $austria = CommonUtils::getConfigValue($pdo, 'austria', 0);
                $fiskalySignResponse = new Fiskalysignresponse();
                $fiskalySignResponse->fetchValuesFromDb($pdo, $billid);
		
		
		$sql = "SELECT tableid,status FROM %bill% WHERE id=?";
		$row = CommonUtils::getRowSqlObject($pdo, $sql,array($billid));
		if ($row->status == 'c') {
			return $this->getCashBill($pdo,$billid,$language,$printer);
		}

		$sql = "SELECT count(id) as countid FROM %queue% WHERE billid=?";
		$qrow = CommonUtils::getRowSqlObject($pdo, $sql, array($billid));
		
                $billuidSql = self::getBillUidSqlPart("billuid");
                
		$tableid = $row->tableid;
		if ($qrow->countid == 0) {
			if ($tableid == 0) {
				$sql = "SELECT DISTINCT $billuidSql as billuid,billdate,brutto,CAST(ROUND(netto,2) as DECIMAL(12,2)) as netto,'-' as tablename,username,COALESCE(fullname,username) as fullname,%user%.id as userid,host,COALESCE(B.status,'') as status,guestinfo,paymentid,printextras FROM %bill% B,%user% WHERE B.id=? AND userid=%user%.id AND tableid='0' ";
			} else {
				$sql = "SELECT DISTINCT $billuidSql as billuid,billdate,brutto,CAST(ROUND(netto,2) as DECIMAL(12,2)) as netto,tableno as tablename,username,COALESCE(fullname,username) as fullname,%user%.id as userid,host,COALESCE(B.status,'') as status,guestinfo,paymentid,printextras FROM %bill% B,%user%,%resttables% WHERE B.id=? AND userid=%user%.id AND tableid=%resttables%.id ";
			}
		} else {
                        $paidWhereClause = " (paidtime IS NOT NULL OR B.paymentid='" . DbUtils::$PAYMENT_GUEST ."') ";
			if ($tableid == 0) {
				$sql = "SELECT DISTINCT $billuidSql as billuid,billdate,brutto,CAST(ROUND(netto,2) as DECIMAL(12,2)) as netto,'-' as tablename,username,COALESCE(fullname,username) as fullname,%user%.id as userid,host,COALESCE(B.status,'') as status,guestinfo,paymentid,printextras FROM %bill% B,%user%,%queue% WHERE B.id=? AND B.id=%queue%.billid AND userid=%user%.id AND tableid='0' AND $paidWhereClause ";
			} else {
				$sql = "SELECT DISTINCT $billuidSql as billuid,billdate,brutto,CAST(ROUND(netto,2) as DECIMAL(12,2)) as netto,tableno as tablename,username,COALESCE(fullname,username) as fullname,%user%.id as userid,host,COALESCE(B.status,'') as status,guestinfo,paymentid,printextras FROM %bill% B,%user%,%resttables%,%queue% WHERE B.id=? AND B.id=%queue%.billid AND userid=%user%.id AND tableid=%resttables%.id AND $paidWhereClause ";
			}
		}
		$row = CommonUtils::getRowSqlObject($pdo, $sql, array($billid));
		
		$status = $row->status;
		$sign = ($status == "s" ? "-" : "");
		
		if ($tableid != 0) {
			$sql = "SELECT abbreviation FROM %room%,%resttables% WHERE %resttables%.id=? AND %resttables%.roomid=%room%.id";
			$trow = CommonUtils::getRowSqlObject($pdo, $sql, array($tableid));
			if (is_null($trow->abbreviation) || ($trow->abbreviation == '')) {
				$tablename = $row->tablename;
			} else {
				$tablename = $trow->abbreviation . "-" . $row->tablename;
			}
		} else {
			$tablename = "-";
		}
		
		if ($row == null) {
			echo json_encode(array("billoverallinfo" => array()));
			return;
		} else {
			$tseInfo = self::getTseInfoOfBill($pdo, $billid, self::$TSE_FORMAT_ASSOC_ARRAY);
			if (is_null($row->host)) {
				$host = 0; // default
			} else {
				$host = $row->host;
			}
			$thetimedate = $row->billdate;
			$printextrasOfReceipt = $row->printextras;
			$billuid = $row->billuid;
			$printExtras = false;
			if ($printextrasOfReceipt == 1) {
				$printExtras = true;
			}
			$masterData = self::getBillMasterDataAtBillTime($pdo,$billid,'rectemplate');
			if (is_null($masterData["companyinfo"])) {
				$masterData["companyinfo"] = "";
			}
                        $currency = CommonUtils::getConfigValue($pdo, 'currency', '');
			$billoverallinfo = $this->createBillOverallInfo($billid,$billuid,$thetimedate,$tablename,$currency,$row->brutto,$row->netto,$row->username,$row->fullname,$row->userid,$printer,$host,$masterData,$tseInfo,$austria,$fiskalySignResponse);

			if ($includeGuestInfo) {
				if (is_null($row->guestinfo)) {
					$billoverallinfo["guestinfo"] = '';
				} else {
					$billoverallinfo["guestinfo"] = $row->guestinfo;
				}
			}
			
			if ($includePayment) {
				$col = "name";
				if ($language == 1) {
					$col = "name_en";
				} else if ($language == 2) {
					$col = "name_esp";
				}
				$paymentid = $row->paymentid;
                                $billoverallinfo['payment'] = Admin::getPaymentwayName($pdo, $paymentid, $language);
			}

			$billtranslations = $this->createBillTranslations($language);
		}
		
		$result = self::getBillProductsInCumulatedOrder($pdo,$billid);
		
	
		$prodarray = array();
		foreach($result as $zeile) {
			$productname = $zeile['productname'];
			if ($zeile["togo"] == 1) {
				$productname = "To-Go: " . $productname;
			}
			$prodarray[] = array("count" => $zeile['count'],
					"productname" => $productname,
					"pricelevel" => $zeile['pricelevelname'],
					"price" => $sign . $zeile['price']
			);
			if ($printExtras) {
				$extrasConcatStr = $zeile['concatstr'];
				if (!is_null($extrasConcatStr)) {
					$singleExtras = explode(',', $extrasConcatStr);
					
					$printextraprice = CommonUtils::getConfigValue($pdo, 'printextraprice', 1);
										
					$sql = "SELECT name FROM %extras% WHERE id=?";
					if ($printextraprice == 1) {
						$decpoint = CommonUtils::getConfigValue($pdo, 'decpoint', ';');
						if (($decpoint != '.') && ($decpoint != ',')) {
							$decpoint = ',';
						}
						$sql = "SELECT CONCAT(name,' (',REPLACE(price,'.','$decpoint'),')') as name FROM %extras% WHERE id=?";
					}
					
					
					foreach ($singleExtras as $aSingleExtra) {
						$singleExtraParts = explode('-', $aSingleExtra);
						$amount = $singleExtraParts[0];
						$singleExtraId = $singleExtraParts[1];
						$extraNameRes = CommonUtils::fetchSqlAll($pdo, $sql, array($singleExtraId));
						if (count($extraNameRes) > 0) {
							$extraText = $amount . "x " . $extraNameRes[0]["name"];
							$prodarray[] = array("count" => 0,
								"productname" => ' + ' . $extraText,
								"pricelevel" => $zeile['pricelevelname'],
								"price" => 0
								);
						}
					}
				}
			}
		}

		
		$sql = "select tax,concat('$sign',round(sum(price) - sum(price / (1.0 + tax/100.0)),2)) as mwst, concat('$sign',round(sum(price / (1.0 + tax/100.0)),2)) as netto, concat('$sign',sum(price)) as brutto FROM %queue%,%billproducts% WHERE %billproducts%.billid=? AND %billproducts%.queueid=%queue%.id group by tax ORDER BY tax";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($billid));
		$result = $stmt->fetchAll(PDO::FETCH_OBJ);
		
		$out = array("billoverallinfo" => $billoverallinfo,"translations" => $billtranslations,"products" => $prodarray, "taxes" => $result);
		return $out;
	}

	private static function getTseInfoOfBill($pdo,$billid,$tseFormat) {
		date_default_timezone_set(DbUtils::getTimeZone());
		
		$sql = "SELECT opid FROM %bill% WHERE id=?";
		$res = CommonUtils::fetchSqlAll($pdo, $sql, array($billid));
		$opid = $res[0]["opid"];
		if (is_null($opid)) {
			return null;
		}
		
		$tsestatus = "(SELECT O.tseerror FROM %operations% O WHERE O.id=B.opid ) as tsestatus";
		$tseserialnoSql = "(SELECT T.textvalue FROM %operations% O INNER JOIN %tsevalues% T ON O.serialno=T.id WHERE O.id=B.opid ) as serialno";
		$sigalgSql = "(SELECT T.textvalue FROM %operations% O INNER JOIN %tsevalues% T ON O.sigalg=T.id WHERE O.id=B.opid ) as sigalg";
		$pubkeySql = "(SELECT T.textvalue FROM %operations% O INNER JOIN %tsevalues% T ON O.pubkey=T.id WHERE O.id=B.opid ) as pubkey";
		$logtimeSql = "(SELECT O.logtime FROM %operations% O WHERE O.id=B.opid ) as logtime";
		$tsesignatureSql = "(SELECT O.tsesignature FROM %operations% O WHERE O.id=B.opid ) as tsesignature";
		$transSql = "(SELECT O.trans FROM %operations% O WHERE O.id=B.opid ) as trans";
		$sigcounterSql = "(SELECT O.sigcounter FROM %operations% O WHERE O.id=B.opid ) as sigcounter";
		$signtxtSql = "(SELECT O.signtxt FROM %operations% O WHERE O.id=B.opid ) as signtxt";

		$sql = "SELECT status,$tsestatus,$tseserialnoSql,$tsesignatureSql,$transSql,$sigalgSql,$pubkeySql,$logtimeSql,$sigcounterSql,$signtxtSql ";
		$sql .= " FROM %bill% B ";
		$sql .= " WHERE B.id=?";

		$billProperties = CommonUtils::fetchSqlAll($pdo, $sql, array($billid));
		if (count($billProperties) == 0) {
			return "";
		}
		if (!isset($billProperties[0]["tsesignature"])) {
			return null;
		}
		if ($billProperties[0]["tsestatus"] != 1) {
                        if ($tseFormat == self::$TSE_FORMAT_QR_TEXT) {
                                return null;
                        } else {
                        
                                $tseInfo = array(
                                    "tsestatus" => $billProperties[0]["tsestatus"],
                                    "serialno" => '',
                                    "tseserialno" => '',
                                    "transnumber" => '',
                                    "sigcounter" => '',
                                    "startlogtime" => '',
                                    "logtime" => '',
                                    "sigalg" => '',
                                    "logtimeformat" => '',
                                    "tsesignature" => '',
                                    "pubkey" => ''
                                );
                                return $tseInfo;
                        }
		}
		$tseSignature = $billProperties[0]["tsesignature"];
		if (is_null($tseSignature) || ($tseSignature == "")) {
			return null;
		}

		$qrCodeVersion = "V0";
		$serialNoOfPOS = CommonUtils::getConfigValue($pdo, 'sn', "ORD1");
		$tseserialno = $billProperties[0]["serialno"];
		$processType = "Kassenbeleg-V1";
		$processData = $billProperties[0]["signtxt"];
		$transNumber = $billProperties[0]["trans"];
		$sigcounter = $billProperties[0]["sigcounter"];
		$logtime = self::epochTimeToDSfinTime($billProperties[0]["logtime"]);
		$sigAlg = $billProperties[0]["sigalg"];
		$logTimeFormat = "unixTime";
		$pubkey = $billProperties[0]["pubkey"];
		
		$billstatus = $billProperties[0]["status"];
		if ($billstatus == 'c') {
			$startlogtime = $logtime;
		} else {
			$startlogtime = self::getFirstLogTimeOfBill($pdo, $billid);
		}
		
		if ($tseFormat == self::$TSE_FORMAT_QR_TEXT) {
			$tseInfo = array(
			    $qrCodeVersion,
			    $serialNoOfPOS,
			    $processType,
			    $processData,
			    $transNumber,
			    $sigcounter,
			    $startlogtime,
			    $logtime,
			    $sigAlg,
			    $logTimeFormat,
			    $tseSignature,
			    $pubkey
			);

			return implode(';',$tseInfo);
		} else {
			$tseInfo = array(
                            "tsestatus" => 1,
			    "serialno" => $serialNoOfPOS,
			    "tseserialno" => $tseserialno,
			    "transnumber" => $transNumber,
			    "sigcounter" => $sigcounter,
			    "startlogtime" => $startlogtime,
			    "logtime" => $logtime,
			    "sigalg" => $sigAlg,
			    "logtimeformat" => $logTimeFormat,
			    "tsesignature" => $tseSignature,
			    "pubkey" => $pubkey
			);
			return $tseInfo;
		}
	}
	
	public static function getFirstLogTimeOfBill($pdo,$billid) {
		$sql = "SELECT MIN(Q.logtime) as starttime FROM %queue% Q,%billproducts% BP WHERE BP.billid=? AND BP.queueid=Q.id";
		$startlogRes = CommonUtils::fetchSqlAll($pdo, $sql, array($billid));
		if (count($startlogRes) == 0) {
			return "";
		}
		$startlogtime = self::epochTimeToDSfinTime($startlogRes[0]["starttime"]);
		return $startlogtime;
	}
	
	public static function outputBillQrCode($billid) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$tseInfo = self::getTseInfoOfBill($pdo, $billid,self::$TSE_FORMAT_QR_TEXT);
		if (!is_null($tseInfo) && ($tseInfo != "")) {
			QRcode::png($tseInfo);
		} else {
			CommonUtils::outputWideEmptyImage();
		}
	}
	public static function outputBillFiskalyQrCode($billid) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
                $sql = "SELECT fiskalyqrcode FROM %bill% WHERE id=?";
                $res = CommonUtils::fetchSqlAll($pdo, $sql, array($billid));
                if (count($res) == 1) {
                        $fiskalyQrCode = $res[0]['fiskalyqrcode'];
                } else {
                        $fiskalyQrCode = '';
                }
                QRCode::png($fiskalyQrCode);
	}
	private static function epochTimeToDSfinTime($epoch) {
		$datePart = date("Y-m-d",$epoch);
		$timePart = date("H:i:s",$epoch);
		return $datePart . "T" . $timePart. ".000";
	}
	
	private static function getBillMasterDataAtBillTime($pdo,$billid,$templatename) {
		$sql = "SELECT billdate FROM %bill% WHERE id=?";
		$res = CommonUtils::fetchSqlAll($pdo, $sql, array($billid));
		$billdate = $res[0]["billdate"];
		return CommonUtils::getMasterDataAtCertainDateTime($pdo, $billdate, $templatename);
	}
	
	private function getCashBill($pdo,$billid,$language,$printer) {
                $decpoint = CommonUtils::getConfigValue($pdo, 'decpoint', '.');
		$sql = "SELECT (CASE "
                        . "WHEN cashtype=? THEN 'TrinkgeldAG' "
                        . "WHEN cashtype=? THEN 'TrinkgeldAN' "
                        . "WHEN cashtype=? THEN 'Trinkgeld' "
                        . "WHEN cashtype=? THEN 'Geldtransit' "
                        . "WHEN cashtype=? THEN 'DiffSollIst' "
                        . "ELSE 'Barein-/auslage' END) as cashtype,"
                        . "billdate,CAST(ROUND(brutto,2) as DECIMAL(12,2)) as brutto,CAST(ROUND(netto,2) as DECIMAL(12,2)) as netto,CAST(ROUND(brutto-netto,2) as DECIMAL(12,2)) as mwst,CAST(ROUND(tax,2) as DECIMAL(12,2)) as tax,username,COALESCE(fullname,username) as fullname,userid,reason FROM %bill% B, %user% U WHERE B.id=? AND B.userid=U.id";
                $result = CommonUtils::fetchSqlAll($pdo, $sql, array(
                    self::$CASHTYPE_TrinkgeldAG['value'],
                    self::$CASHTYPE_TrinkgeldAN['value'],
                    self::$CASHTYPE_TrinkgeldBoth['value'],
                    self::$CASHTYPE_Geldtransit['value'],
                    self::$CASHTYPE_DifferenzSollIst['value'],
                    $billid));
                $r = $result[0];
		
		$brutto = $r["brutto"];
		$netto = $r["netto"];
                $mwst = str_replace('.', $decpoint, $r["mwst"]);
                $tax = str_replace('.', $decpoint, $r["tax"]);
		
		$masterData = self::getBillMasterDataAtBillTime($pdo,$billid,'cashtemplate');
		$tseInfo = self::getTseInfoOfBill($pdo, $billid, self::$TSE_FORMAT_ASSOC_ARRAY);
                $currency = CommonUtils::getConfigValue($pdo, 'currency', '');
		$billoverallinfo = $this->createBillOverallInfo($billid,"V-" .$billid,$r["billdate"],' ',$currency,$brutto,$netto,$r["username"],$r['fullname'],$r["userid"],1,0,$masterData,$tseInfo,null,null);
		$billoverallinfo["guestinfo"] = '';
		
		$billtranslations = $this->createBillTranslations($language);
		
		$prods = array();
		$prods[] = array(
		    "productname" => $r["cashtype"],
		    "price" => $r["brutto"],
		    "pricelevel" => "A",
		    "count" => 1
		    );
		$reason = $r["reason"];
		if (!is_null($reason)) {
			$prods[] = array(
			    "productname" => "($reason)",
			    "price" => 0,
			    "pricelevel" => "A",
			    "count" => 0
			);
		}
		
		$taxes = array(
		    array("tax"=> $tax,"mwst" => $mwst, "netto" => $netto,"brutto" => $brutto)
		);
		
		$out = array("billoverallinfo" => $billoverallinfo,"translations" => $billtranslations,"products" => $prods, "taxes" => $taxes);
		return $out;
	}
	
	public function getAustriaTaxes($pdo,$billid) {
	    $sql = "select tax,IF(taxaustria is not null, taxaustria, 0) as taxaustria,concat('',round(sum(price) - sum(price / (1.0 + tax/100.0)),2)) as mwst, concat('',round(sum(price / (1.0 + tax/100.0)),2)) as netto, concat('',sum(price)) as brutto FROM %queue%,%billproducts% WHERE %billproducts%.billid=? AND %billproducts%.queueid=%queue%.id group by tax ORDER BY taxaustria";
	    $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
	    $stmt->execute(array($billid));
	    $result = $stmt->fetchAll(PDO::FETCH_OBJ);
	    return $result;
	}
	
	private static function doCashAction($money,$remark,$cashtype) {
		date_default_timezone_set(DbUtils::getTimeZone());
		$currentTime = date('Y-m-d H:i:s');

		$userId = self::getUserIdStatic();
		$result = self::doCashActionCore(null,$money, $remark, $currentTime,$userId,$cashtype,DbUtils::$PAYMENT_BAR,ALLOW_TRANSACTIONS,true);
		echo json_encode($result);
	}
	
        private static function setPrevValuesInBill($pdo,int $lastId) {
                if ($lastId > 1) {
                        $sql = "SELECT brutto,prevbrutto,netto,prevnetto FROM %bill% WHERE id=?";

                        $rPrev = CommonUtils::fetchSqlAll($pdo, $sql,array($lastId-1));
                        $prevVals = array("brutto" => $rPrev[0]['brutto'],"prevbrutto" => $rPrev[0]['prevbrutto'],"netto" => $rPrev[0]['netto'],"prevnetto" => $rPrev[0]['prevnetto']);

                } else {
                        $prevVals = array("brutto" => 0,"prevbrutto" => 0,"netto" => 0,"prevnetto" => 0);
                }
                $sql = "UPDATE %bill% SET prevbrutto=?,prevnetto=? WHERE id=?";
                CommonUtils::execSql($pdo, $sql, array($prevVals['brutto'] + $prevVals['prevbrutto'],$prevVals['netto'] + $prevVals['prevnetto'],$lastId));
        }
        

	public static function cashActionCoreTask($pdo,int $userId, $datetime, float $money, string $remark, int $cashtype, int $paymentid, int $allowOwnTransactions) : array {
		if (trim($money) == '') {
			$money = '0.00';
		}
		
		$isowner = 0;
		if ($cashtype == self::$CASHTYPE_TrinkgeldBoth["value"]) {
			$sql = "SELECT COALESCE(isowner,'0') as isowner FROM %user% WHERE id=?";
			$result = CommonUtils::fetchSqlAll($pdo, $sql, array($userId));
			$isowner = $result[0]['isowner'];
			if ($isowner == 1) {
				$cashtype = self::$CASHTYPE_TrinkgeldAG["value"];
			} else {
				$cashtype = self::$CASHTYPE_TrinkgeldAN["value"];
			}
		}
		
		$moneyFloat = floatval($money);
		$tax = 0.0;
		$netto = $moneyFloat;
		if (($cashtype == self::$CASHTYPE_TrinkgeldAG["value"]) || ($cashtype == self::$CASHTYPE_Auszahlung["value"])) {
			$tax = CommonUtils::getConfigValue($pdo, 'tax', 19.0);
			$netto = $moneyFloat / (1 + $tax/100.0);
		}
		
		CommonUtils::log($pdo, "QUEUE", "Cash action with money '$money' at billtime '$datetime' with cashtype '$cashtype' of payment " . $paymentid);
		if ($allowOwnTransactions == ALLOW_TRANSACTIONS) {
			CommonUtils::setTransactionSerializable($pdo);
			$pdo->beginTransaction();
		}
		
		$nextbillid = self::testForNewBillIdAndUpdateWorkTable($pdo);
		if ($nextbillid < 0) {
			if ($allowOwnTransactions == ALLOW_TRANSACTIONS) {
				$pdo->rollBack();
				CommonUtils::resetTransactionIsolationLevel($pdo);
			}
			return(array("status" => "ERROR", "code" => ERROR_INCONSISTENT_DB, "msg" => ERROR_INCONSISTENT_DB_MSG));
		}

		CommonUtils::log($pdo, "QUEUE", "Calc bill signature for cash money '$money' at billtime '$datetime'");
		$signature = CommonUtils::calcSignatureForBill($datetime, $money, $money, $userId);

		$sql = "SELECT (COALESCE(MAX(billuid),0)) as maxbilluid FROM %bill%";
		$maxuidRes = CommonUtils::fetchSqlAll($pdo, $sql);
		$maxBilluid = $maxuidRes[0]["maxbilluid"];
		$nextBilluid = intval($maxBilluid) + 1;

		$sql = "INSERT INTO `%bill%` (`id` ,`billuid` , `billdate`,`brutto`,`netto`,`tax`,`tableid`, `status`, `paymentid`,`cashtype`,`userid`,`ref`,`reason`,`signature`,`needsebonupload`) 
				VALUES (?,?,?,?,ROUND(?,6),?,?,'c',?,?,?,?,?,?,?)";
		CommonUtils::execSql($pdo, $sql, array($nextbillid,$nextBilluid,$datetime,$money,$netto,$tax,-1,$paymentid,$cashtype,$userId,NULL,$remark,$signature,0));

		$lastId = $pdo->lastInsertId();
		self::setPrevValuesInBill($pdo,$lastId);

		$moneyTwoDigits = number_format($money, 2, '.', '');

		$payType = (new Paymentinfo())->getPaymentTypeAsString($pdo, $paymentid, true);

		if (($cashtype == self::$CASHTYPE_Privateinlage["value"]) || ($cashtype == self::$CASHTYPE_Privatentnahme["value"])) {


			$signStr = "Beleg^" . implode('_',array('0.00','0.00','0.00','0.00',$moneyTwoDigits)) . "^" . $moneyTwoDigits . ":" . $payType;
		} else if ($cashtype == self::$CASHTYPE_Geldtransit["value"]) {
			$signStr = "Beleg^" . implode('_',array('0.00','0.00','0.00','0.00',$moneyTwoDigits)) . "^" . $moneyTwoDigits . ":" . $payType;
		} else if (($cashtype == self::$CASHTYPE_Einzahlung["value"]) ||  ($cashtype == self::$CASHTYPE_Auszahlung["value"])) {

			$signStr = "Beleg^" . implode('_',array($moneyTwoDigits,'0.00','0.00','0.00','0.00')) . "^" . $moneyTwoDigits . ":" . $payType;
		} else if ($cashtype == self::$CASHTYPE_TrinkgeldAN["value"]) {

			$signStr = "Beleg^" . implode('_',array('0.00','0.00','0.00','0.00',$moneyTwoDigits)) . "^" . $moneyTwoDigits . ":" . $payType;
		} else if ($cashtype == self::$CASHTYPE_TrinkgeldAG["value"]) {


			$signStr = "Beleg^" . implode('_',array($moneyTwoDigits,'0.00','0.00','0.00','0.00')) . "^" . $moneyTwoDigits . ":" . $payType;
		} else if ($cashtype == self::$CASHTYPE_DifferenzSollIst["value"]) {
			$signStr = "Beleg^" . implode('_',array('0.00','0.00','0.00','0.00',$moneyTwoDigits)) . "^" . $moneyTwoDigits . ":" . $payType;
		}

		$status = self::signValueByTseAndUpdateBill($pdo, $lastId, $signStr);
		if ($status["status"] != "OK") {
			if ($allowOwnTransactions == ALLOW_TRANSACTIONS) {
				$pdo->rollBack();
				CommonUtils::resetTransactionIsolationLevel($pdo);
			}
		}
		if ($allowOwnTransactions == ALLOW_TRANSACTIONS) {
			$pdo->commit();
			CommonUtils::resetTransactionIsolationLevel($pdo);
		}
		$status["billid"] = $lastId;
		return $status;
	}

	public static function doCashActionCore($pdo,$money,$remark, $datetime,$userId,$cashtype,$paymentid,int $allowOwnTransactions,$checkForPermissions) {
		date_default_timezone_set(DbUtils::getTimeZone());
		
		if (is_null($pdo)) {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
		}
		$userMayDoCashOps = Permissions::canUserDoCashOps($pdo, $userId);
		if ($cashtype == self::$CASHTYPE_TrinkgeldBoth["value"]) {
			$userMayDoCashOps = true;
		}
		if (!$userMayDoCashOps && $checkForPermissions) {
			return array("status" => "ERROR","msg" => "Fehlendes Recht für Barein-/auslage");
		}
		
		if (!is_numeric($money)) {
			return array("status" => "ERROR","msg" => "Wert nicht numerisch");
		}

		$status = self::cashActionCoreTask($pdo,$userId, $datetime, $money, $remark, $cashtype, $paymentid, $allowOwnTransactions);

		if ($status["status"] != "OK") {
			return $status;
		}

		$lastId = $status["billid"];

		$printcash = CommonUtils::getConfigValue($pdo, 'printcash', 0);
		if ($printcash == 1) {
			if(session_id() == '') {
					session_start();
			}
			$printer = $_SESSION['receiptprinter'];
			PrintQueue::internalQueueReceiptPrintjob($pdo, $lastId, $printer);
		}
		
		return(array("status" => "OK","billid" => $lastId));
	}
	
	/*
	 * User may ask what money he should have in his pocket by serving the guests. If the inserts and 
	 * take outs are in in his waiter paydesk then this value is of interest, too. Return both.
	 */
	function getCashOverviewOfUser() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$userId = $this->getUserId();
		
		$userMayDoCashOps = Permissions::canUserDoCashOps($pdo, $userId);
		if (!$userMayDoCashOps) {
			echo json_encode(array("status" => "ERROR", "cashperpayments" => 0,"total" => 0,"onlycash" => 0,"msg" => "Fehlende Rechte"));
			return;
		}
		
		$sql = "SELECT right_cashop FROM %roles% R,%user% U WHERE U.roleid=R.id AND U.id=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($userId));
		$cashopPermission = $result[0]['right_cashop'];
		if ($cashopPermission == 0) {
			return array("status" => "ERROR","msg" => "Fehlendes Recht für Barein-/auslage");
		}
		
		if(session_id() == '') {
			session_start();
		}
		$lang = $_SESSION['language'];
                $allPayments = (new Paymentinfo())->getAllPayments($pdo, $lang);
                
		$cashPerPayments = array();
                // Iteratioon over all payments -> these values are shown e.g. in the Einnahmen in payment view
                foreach($allPayments as $aPayment) {
                        $paymentname = $aPayment["payname"];
                        $paymentid = $aPayment["id"];
			
			$onlyCashByGuests = 0.0;
			$sql = "SELECT sum(brutto) as sumtotal FROM %bill% WHERE closingid is null AND status is null AND paymentid=? AND userid=?";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($paymentid,$userId));
			$row =$stmt->fetchObject();
			if ($row != null) {
				if ($row->sumtotal != null) {
					$onlyCashByGuests = $row->sumtotal;
				}
			}
			if ($onlyCashByGuests != '0.00') {
				$cashPerPayments[] = array("payment" => $paymentname,"value" => $onlyCashByGuests);
			}
		}
		
		$cashByGuestsAndInsertTakeOut = 0.0;
		$sql = "SELECT sum(brutto) as sumtotal FROM %bill% WHERE closingid is null AND paymentid IN (SELECT id FROM %payment% WHERE paymenttype=?) AND userid=? AND (status is null OR status ='c')";
		$res = CommonUtils::fetchSqlAll($pdo, $sql, array(Paymentinfo::$PAYMENT_TYPE_Bar,$userId));
		if (count($res) > 0) {
			$cashVal = $res[0]["sumtotal"];
			if (!is_null($cashVal)) {
				$cashByGuestsAndInsertTakeOut = $cashVal;
			}
		}
				
		$onlyCash = 0.0;
		$sql = "SELECT sum(brutto) as sumtotal FROM %bill% WHERE closingid is null AND paymentid IN (SELECT id FROM %payment% WHERE paymenttype=?) AND userid=? AND status ='c' AND (cashtype <> ? AND cashtype <> ? AND cashtype <> ?)";
		$res = CommonUtils::fetchSqlAll($pdo, $sql, array(Paymentinfo::$PAYMENT_TYPE_Bar,$userId, Bill::$CASHTYPE_TrinkgeldAG["value"],Bill::$CASHTYPE_TrinkgeldAN["value"],Bill::$CASHTYPE_TrinkgeldBoth["value"]));
		if (count($res) > 0) {
			$cashVal = $res[0]["sumtotal"];
			if (!is_null($cashVal)) {
				$onlyCash = $cashVal;
			}
		}
		
		echo json_encode(array("status" => "OK", "cashperpayments" => $cashPerPayments,"total" => $cashByGuestsAndInsertTakeOut,"onlycash" => $onlyCash));
	}
	
        public static function getBillEbonInfoForUserLink($pdo,$billid) {
                $res = CommonUtils::fetchSqlAll($pdo, "SELECT ebonref FROM %bill% WHERE id=?",array($billid));
                if (count($res) > 0) {
                        return $res[0]["ebonref"];
                } else {
                        return "";
                }
                
        }

        private function getBillContentForBillsView($billId) {
        	try {
				$pdo = DbUtils::openDbAndReturnPdoStatic();
						
				$commonUtils = new CommonUtils();
				if (!$commonUtils->verifyBill($pdo, $billId)) {
					echo json_encode(array("status" => "ERROR", "code" => ERROR_INCONSISTENT_DB, "msg" => ERROR_INCONSISTENT_DB_MSG));
					return;
				}
				$billDbInfo = $this->getBillInfoFromDb($pdo, $billId, "", "", false,0);

				if(count($billDbInfo) != 1) {
						return array("status" => "ERROR","msg" => "Keinen passenden Kassenbon gefunden");
				}
				$billDb = $billDbInfo[0];

				$l = CommonUtils::getConfigValue($pdo, 'billlanguage', 0);

				$billDetails = $this->createBillArrayOutOfSqlData($pdo, $billDb);

				$ebonUrl = CommonUtils::getPureEbonUrl($pdo);
				$billDetails["ebonurl"] = $ebonUrl;
				$billDetails["ebonref"] = $billDb['ebonref'];
				$billDetails["billcontent"] = $this->getBillWithId($pdo,$billId,$l,0,true,false);

				$hosthtml = file_get_contents("../customer/bon-bewirtungsvorlage.html");
				$payments = (new Paymentinfo())->getPossiblePayments($pdo);

				$billinfo = array("bill" => $billDetails,"hosthtml" => $hosthtml,"payments" => $payments);

				return array("status" => "OK", "code" => OK, "msg" => $billinfo);
			} catch (Exception $ex) {
					return array("status" => "OK","msg" => $ex->getMessage());
			}
        }
        
        private function getBillInfoFromDb($pdo,int $billId,string $startDate,string $endDate, bool $usePeriod, int $userid):array {
                $billuidSql = self::getBillUidSqlPart();
                $isGuestBillSql = "(CASE WHEN B.paymentid='" . DbUtils::$PAYMENT_GUEST ."' THEN '1' ELSE '0' END)";
                $isCashBillSql = "(CASE WHEN B.status='c' THEN '1' ELSE '0' END)";
                $isTipBill = "(CASE WHEN B.status='c' AND (B.cashtype='" . Bill::$CASHTYPE_TrinkgeldAG["value"] . "' OR B.cashtype='" . Bill::$CASHTYPE_TrinkgeldAN["value"] . "') THEN '1' ELSE '0' END)";

                $ebonSql = "COALESCE(B.ebonref,'') as ebonref";
                
                $billlanguage = CommonUtils::getConfigValue($pdo, 'billlanguage', 1);
                $paymentColName = array("name","name_en","name_esp")[$billlanguage];
                $paymentName = "(CASE WHEN B.paymentid=" . DbUtils::$PAYMENT_GUEST . " THEN null ELSE P.$paymentColName END) as paymentname";
                $userSql = "COALESCE(B.userid,0) as userid";
                
                $whenClause = " B.id=?";
                $whenArray = array($billId,$billId);
                if ($userid > 0) {
                        $whenClause .= " AND userid=? ";
                        $whenArray = array($billId,$userid,$billId,$userid);
                }
                if ($usePeriod) {
                        $whenClause = " (billdate >= ? AND billdate <= ?) ";
                        $whenArray = array($startDate,$endDate,$startDate,$endDate);
                        if ($userid > 0) {
                                $whenClause .= " AND userid=? ";
                                $whenArray = array($startDate,$endDate,$userid,$startDate,$endDate,$userid);
                        }
                }
                
                
		$sql  = "SELECT B.id,$billuidSql as billuid,$userSql,$isGuestBillSql as isguestbill,$isCashBillSql as iscashbill,$isTipBill as istipbill,billdate,brutto,tableid,closingid,paymentid,status,host,COALESCE(B.tip,'0.00') as tip,$paymentName,$ebonSql FROM %bill% B,%payment% P WHERE B.paymentid=P.id AND tableid >= '0' AND $whenClause  ";
		$sql .= "UNION ";
		$sql .= "SELECT B.id,$billuidSql as billuid,$userSql,$isGuestBillSql as isguestbill,$isCashBillSql as iscashbill,$isTipBill as istipbill,billdate,brutto,tableid,closingid,paymentid,status,host,COALESCE(B.tip,'0.00') as tip,$paymentName,$ebonSql FROM %bill% B,%payment% P WHERE B.paymentid=P.id AND status='c' AND $whenClause ";
		$sql .= "ORDER BY id DESC,billdate DESC";
		return CommonUtils::fetchSqlAll($pdo, $sql,$whenArray);
        }
        
        private function createBillArrayOutOfSqlData($pdo,array $billDbInfo):array {
                date_default_timezone_set(DbUtils::getTimeZone());
                $commonUtils = new CommonUtils();
                $theId = $billDbInfo['id'];
                $date = new DateTime($billDbInfo['billdate']);
                $shortdate = $date->format('H:i');
                $closingID = $billDbInfo['closingid'];
                $isClosed = (is_null($closingID) || ($billDbInfo['paymentid'] == DBUtils::$PAYMENT_GUEST) ? 0 : 1);

                $host = 0;
                $tablename = "-";
                $tableid = $billDbInfo['tableid'];
                if (!is_null($tableid) && is_string($tableid)) {
                        $tableid = intval($tableid);
                }
                if (!is_null($tableid) && ($tableid > 0)) {
                        // for cash operations (not tips) the tableid is typically -1
                        $tablename = $commonUtils->getTableNameFromId($pdo, $tableid);
                }

                if ($this->billIsCancelled($pdo, $theId)) {
                        $isClosed = 1;
                }

                $billDetails = array("id" => $theId,
                    "billuid" => $billDbInfo['billuid'],
                    "longdate" => $billDbInfo['billdate'],
                    "shortdate" => $shortdate,
                    "brutto" => $billDbInfo['brutto'],
                    "tablename" => $tablename,
                    "isClosed" => $isClosed,
                    "isguestbill" => $billDbInfo['isguestbill'],
                    "iscashbill" => $billDbInfo['iscashbill'],
                    "istipbill" => $billDbInfo['istipbill'],
                    "host" => $host,
                    "tip" => $billDbInfo['tip'],
                    "paymentid" => $billDbInfo['paymentid'],
                    "paymentname" => $billDbInfo['paymentname']
                );
                return $billDetails;
        }
        
	function getLastBillsWithContent($day,$month,$year,$withContent=true,int $userid=0) {		
		date_default_timezone_set(DbUtils::getTimeZone());
		$startDate = "$year-$month-$day 00:00:00";
		$endDate = "$year-$month-$day 23:59:59";
		
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		
                $l = CommonUtils::getConfigValue($pdo, 'billlanguage', 0);
		
		$commonUtils = new CommonUtils();

                $result = $this->getBillInfoFromDb($pdo, 0, $startDate, $endDate, true,$userid);
		$resultarray = array();
		foreach($result as $zeile) {
			$theId = $zeile['id'];
			if (!$commonUtils->verifyBill($pdo, $theId)) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_INCONSISTENT_DB, "msg" => ERROR_INCONSISTENT_DB_MSG));
				return;
			}
                        
                        $arr = $this->createBillArrayOutOfSqlData($pdo, $zeile);
			
                        if ($withContent) {
                                $ebonUrl = CommonUtils::getPureEbonUrl($pdo);
                                $arr["billcontent"] = $this->getBillWithId($pdo,$theId,$l,0,true,false);
                                $arr["ebonurl"] = $ebonUrl;
                                $arr["ebonref"] = $zeile['ebonref'];
                        }
                        
			$resultarray[] = $arr;
		}

		$hosthtml = file_get_contents("../customer/bon-bewirtungsvorlage.html");
                
                $payments = (new Paymentinfo())->getPossiblePayments($pdo);
		
		ob_start();
		echo json_encode(array("status" => "OK", "code" => OK, "msg" => $resultarray, "hosthtml" => $hosthtml,"payments" => $payments));
		ob_end_flush();
	}

	private function getUserId():int {
		if(session_id() == '') {
			session_start();
		}
                $userid = $_SESSION['userid'];
                if (is_string($userid)) {
                        return intval($userid);
                } else {
                        return $userid;
                }
	}

	private static function getUserIdStatic() {
		if(session_id() == '') {
			session_start();
		}
		return $_SESSION['userid'];
	}
	/**
	 * Test if it is allowed to insert new bill as storno bill or if manipulation has happened
	 * 
	 * Returns (-1) in case of an error, a positive return value is the new id, (which is already updated in work table)
	 */
	private static function testForNewBillIdAndUpdateWorkTable($pdo) {
		$sql = "SELECT MAX(id) as maxbillid FROM %bill%";
		$res = CommonUtils::fetchSqlAll($pdo, $sql);
		$maxbillid = 0;
		if (count($res) > 0) {
			$maxbillid = $res[0]["maxbillid"];
			if (is_null($maxbillid)) {
				$maxbillid = 0;
			}
		}
		$nextbillid = intval($maxbillid) + 1;
		$commonUtils = new CommonUtils();
		if (!$commonUtils->verifyLastBillId($pdo, $nextbillid)) {
			return (-1);
		} else {
			$commonUtils->setLastBillIdInWorkTable($pdo, $nextbillid);
			return $nextbillid;
		}
	}
	
	private function initaustriareceipt($pdo) {
		date_default_timezone_set(DbUtils::getTimeZone());
		$currentTime = date('Y-m-d H:i:s');
		
		$pdo->beginTransaction();
		
		try {
			// calculate next bill id
			$sql = "SELECT MAX(id) as maxid FROM %bill%";
			$row = CommonUtils::getRowSqlObject($pdo, $sql);
			$maxbillid = $row->maxid;
			$nextbillid = 1;
			$newprevbrutto = 0;
			$newprevnetto = 0;
			if (!is_null($maxbillid)) {
				$nextbillid = intval($maxbillid) + 1;

				$sql = "SELECT brutto,CAST(ROUND(netto,2) as DECIMAL(12,2)) as netto,prevbrutto,prevnetto FROM %bill% WHERE id=?";
				$row = CommonUtils::getRowSqlObject($pdo, $sql, array(intval($maxbillid)));
				$newprevbrutto = $row->prevbrutto ;
				$newprevnetto = $row->prevnetto;
			}

			$commonUtils = new CommonUtils();
			$commonUtils->setLastBillIdInWorkTable($pdo, $nextbillid);

			$tableid = 0;
			if(session_id() == '') {
				session_start();
			}
			$userid = $_SESSION['userid'];
			$signature = CommonUtils::calcSignatureForBill($currentTime, '0.00', '0.00', $userid);

			$nextBillIds = self::getNextBillIds($pdo);
			$nextBilluid = $nextBillIds["nextbilluid"];
			$sql = "INSERT INTO `%bill%` (`id` ,`billuid`, `billdate`,`brutto`,`netto`,`prevbrutto`,`prevnetto`,`tableid`,`paymentid`,`userid`,`ref`,`tax`,`host`,`reservationid`,`guestinfo`,`intguestid`,`signature`,`reason`) VALUES (?,?,?,?,?,?,?,?,?,?,NULL,NULL,?,?,?,?,?,?)";
			CommonUtils::execSql($pdo, $sql, array($nextbillid,$nextBilluid,$currentTime,'0.00', '0.00',$newprevbrutto,$newprevnetto,$tableid,1,$userid,0,null,null,null,$signature,'STARTBELEG'));

			CommonUtils::log($pdo, "QUEUE", "Created bill STARTBELEG with id=$nextbillid from user $userid");
			
			Rksv::doStartBeleg($pdo, $nextbillid, $currentTime);
			
		} catch (Exception $ex) {
			$pdo->rollBack();
			return array("status" => "ERROR", "msg" => $ex->getMessage());
		}
		$pdo->commit();
		
		return array("status" => "OK");
	}
	
	private function changeBillHost($pdo,$billid,$isNowHost) {
		$sql = "SELECT host,closingid,paymentid FROM  %bill% WHERE id=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($billid));
		$row = $stmt->fetchObject();
		if ($row->host != $isNowHost) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_BILL_NOT_WO_HOST, "msg" => ERROR_BILL_NOT_WO_HOST_MSG));
			return;
		}
		if (!is_null($row->closingid)) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_BILL_ALREADY_CLOSED, "msg" => ERROR_BILL_ALREADY_CLOSED_MSG));
			return;
		}
                if ($row->paymentid == DbUtils::$PAYMENT_GUEST) {
                        echo json_encode(array("status" => "ERROR","msg" => 'Umwandlung von Lieferbons in Bewirtungsbelege verboten'));
			return;
                }

                $updatedHost = 1-$isNowHost;
                $updatedPaymentid = $row->paymentid;
                
		$pdo->beginTransaction();
		
                $status = $this->cancelAndRecreateBill($pdo, $billid, $updatedPaymentid, $updatedHost, false);
                if ($status["status"] !== "OK") {
                        $pdo->rollBack();
                        echo json_encode($status);
                        return;
                }
		
		$pdo->commit();
		
		echo json_encode(array("status" => "OK", "code" => OK));
	}
        
        private function cancelAndRecreateBill($pdo,$billid,$updatedPaymentid,$updatedHost,$recreateTips) {
                try {
                        $sql = "SELECT queueid FROM %billproducts% WHERE billid=?";
                        $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
                        $stmt->execute(array($billid));
                        $idsOfBill = $stmt->fetchAll();
                        $ids = array();
                        foreach($idsOfBill as $anId) {
                                $ids[] = $anId["queueid"];
                        }

                        $sql = "SELECT brutto,netto,tableid,paymentid,tax,reservationid,guestinfo,intguestid,intguestpaid,tip,billidoftip FROM %bill% WHERE id=?";
                        $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
                        $stmt->execute(array($billid));
                        $row = $stmt->fetchObject();

                        $tipinfo = null;
                        if (is_null($row->billidoftip)) {
                                $sql = "SELECT brutto,netto,tax FROM %bill% WHERE id=?";
                                $result = CommonUtils::fetchSqlAll($pdo, $sql, array($row->billidoftip));
                                $tipinfo = array("brutto" => $result[0]['brutto'],"netto" => $result[0]['netto'],"tax" => $result[0]['tax']);
                        }
                        $ok = $this->cancelBill($pdo, $billid, "", "OrderSprinter-Bewirtungseigenschaft", false, false, false, 0,null,($recreateTips ? 1 : 0));
                        if (!$ok) {
                                return(array("status" => "ERROR", "code" => ERROR_BILL_CANCEL_IMOSSIBLE, "msg" => ERROR_BILL_CANCEL_IMOSSIBLE_MSG));
                        }

                        $status = $this->recreateBill($pdo, 
                                $ids,
                                $row->brutto,
                                $row->netto, 
                                $row->tableid, 
                                $updatedPaymentid, 
                                $row->tax, 
                                $updatedHost,
                                $row->reservationid,
                                $row->guestinfo,
                                $row->intguestid,
                                $row->intguestpaid,
                                $recreateTips ? $row->tip : null);
                        if ($status["status"] != "OK") {
                                return ($status);
                        }
                } catch (Exception $ex) {
                        return array("status" => "ERROR","msg" => "Exception: " . $ex->getMessage());
                }
                return array("status" => "OK");
        }
        
        private function changepayment($pdo,$billid,$newpaymentid) {
                $sumupforcard = CommonUtils::getConfigValue($pdo, 'sumupforcard', 0);
                if ($sumupforcard == 1) {
                        echo json_encode(array("status" => "ERROR","msg" => 'Bei aktivierter Sumup-Anbindung ist diese Aktion nicht möglich.'));
			return;
                }
                $allowedPayment = 1;
                if ($newpaymentid > 3) {
                        echo json_encode(array("status" => "ERROR","msg" => 'Falscher neuer Zahlungsweg'));
			return;
                }
                if ($newpaymentid == 2) {
                        $allowedPayment = CommonUtils::getConfigValue($pdo, 'showpayment2', 0);
                } else if ($newpaymentid == 3) {
                        $allowedPayment = CommonUtils::getConfigValue($pdo, 'showpayment3', 0);
                }
                if ($allowedPayment == 0) {
                        echo json_encode(array("status" => "ERROR","msg" => 'Neuer Zahlungsweg nicht in der Liste der erlaubten Zahlungswege'));
			return;
                }
                $sql = "SELECT host,closingid,host,paymentid FROM  %bill% WHERE id=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($billid));
		$row = $stmt->fetchObject();
                
                if (!is_null($row->closingid)) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_BILL_ALREADY_CLOSED, "msg" => ERROR_BILL_ALREADY_CLOSED_MSG));
			return;
		}
                if ($row->paymentid > 3) {
                        echo json_encode(array("status" => "ERROR","msg" => 'Neuausstellung unmöglich'));
			return;
                }
                
                
                $updatedHost = $row->host;
                $updatedPaymentid = $newpaymentid;
                
		$pdo->beginTransaction();
		
		$status = $this->cancelAndRecreateBill($pdo, $billid, $updatedPaymentid, $updatedHost, true);
		if ($status["status"] !== "OK") {
				$pdo->rollBack();
				echo json_encode($status);
				return;
		}
		
		$pdo->commit();
		
		echo json_encode(array("status" => "OK", "code" => OK));
                
        }

		private static function createPaidGuestBill($billids_array_str,$paymentId) {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			CommonUtils::setTransactionSerializable($pdo);

			$result = CommonUtils::executeSqlTransactionQueryBlockWithRetries(
				$pdo,
				function($pdo) use ($billids_array_str, $paymentId) {
					return self::createPaidGuestBillBlock($pdo, $billids_array_str, $paymentId);
				},
				5,
				200
			);

			if ($result["status"] == "OK") {
				echo json_encode(array("status" => "OK", "msg" => $result["msg"], "cusid" => $result["cusid"]));
			} else {
				echo json_encode(array("status" => "ERROR", "msg" => $result["msg"]));
			}
			CommonUtils::resetTransactionIsolationLevel($pdo);
		}
	
        private static function createPaidGuestBillBlock($pdo,$billids_array_str,$paymentId) {
			$billids_array = explode(',', $billids_array_str);
			$in  = str_repeat('?,', count($billids_array) - 1) . '?';
			$sql = "select intguestid,guestinfo FROM %bill% WHERE id in ($in) LIMIT 1";
			$intguestid_res = CommonUtils::fetchSqlAll($pdo, $sql, $billids_array);
			$intguestid = $intguestid_res[0]['intguestid'];
			$guestinfo = $intguestid_res[0]['guestinfo'];
			$sql = "SELECT queueid FROM %billproducts% WHERE billid in ($in)";
			$queueids_res = CommonUtils::fetchSqlAll($pdo, $sql, $billids_array);
			$queueids = array();
			foreach($queueids_res as $aQueueId) {
					$queueids[] = $aQueueId['queueid'];
			}
			$queueidsstr = implode(',',$queueids);
			$queue = new QueueContent();
			$billid = $queue->declarePaidCreateBillReturnBillId($pdo, $queueidsstr, 0, $paymentId, 1, 0, true, '', $guestinfo, $intguestid,null,null,0);
			$recprinter = $_SESSION['receiptprinter'];
			PrintQueue::internalQueueReceiptPrintjob($pdo, $billid, $recprinter);
			
			foreach($billids_array as $billid) {
					$sql = "UPDATE %bill% SET intguestpaid=? WHERE id=?";
					CommonUtils::execSql($pdo, $sql, array(1,$billid));
			}
			return(array("status" => "OK","cusid" => $intguestid, "msg" => "Bon mit ID " . $billid . " erstellt"));
        }
        
	function recreateBill($pdo,$ids_array,$brutto,$netto,$tableid,$paymentId,$tax,$host,$reservationid,$guestinfo,$intguestid,$intguestpaid,$tip) {

		$userid = $this->getUserId();

		date_default_timezone_set(DbUtils::getTimeZone());
		$currentTime = date('Y-m-d H:i:s');
	
		$billid = (-1);
			
		$sql = "SELECT id from %bill% ORDER BY id DESC";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		$row = $stmt->fetchObject();
		$billid = intval($row->id)+1;
		$commonUtils = new CommonUtils();
		$commonUtils->setLastBillIdInWorkTable($pdo, $billid);

		if (is_null($tableid)) {
			$tableid = 0;
		}

		$signature = CommonUtils::calcSignatureForBill($currentTime, $brutto, $netto, $userid);
			
		$nextBillIds = self::getNextBillIds($pdo);
		$nextBilluid = $nextBillIds["nextbilluid"];
		$billInsertSql = "INSERT INTO `%bill%` (`id` , `billuid`,`billdate`,`brutto`,`netto`,`tableid`,`paymentid`,`userid`,`ref`,`tax`,`host`,`reservationid`,`guestinfo`,`intguestid`,`intguestpaid`,`signature`,`tip`) VALUES (?,?,?,?,?,?,?,?,NULL,?,?,?,?,?,?,?,?)";
		CommonUtils::execSql($pdo, $billInsertSql, array($billid,$nextBilluid,$currentTime,$brutto,$netto,$tableid,$paymentId,$userid,$tax,$host,$reservationid,$guestinfo,$intguestid,$intguestpaid,$signature,$tip));
		$newBillId = $pdo->lastInsertId();
		
		for ($i=0;$i<count($ids_array);$i++) {
			$queueid = $ids_array[$i];
			
			$updateSql = "UPDATE %queue% SET paidtime=?, billid=? WHERE id=?";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($updateSql));
			$stmt->execute(array($currentTime,$billid,$queueid));
			
			$billProdsSql = "INSERT INTO `%billproducts%` (`queueid`,`billid`) VALUES ( ?,?)";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($billProdsSql));
			$stmt->execute(array($queueid,$billid));
		}
		
		self::setPositionsOfProductsOnBillProductsTable($pdo, $newBillId);
		
		$status = self::signOrdersBill($pdo,$newBillId,false);
		if ($status["status"] != "OK") {
			return $status;
		}
		
		if (!is_null($tip)) {
			$cashopStatus = Bill::doCashActionCore($pdo,$tip, '', $currentTime, $userid, Bill::$CASHTYPE_TrinkgeldBoth["value"],$paymentId,DENY_TRANSACTIONS,true);

			if ($cashopStatus["status"] == "OK") {
				$cashBillId = $cashopStatus["billid"];
				$sql = "UPDATE %bill% SET billidoftip=? WHERE id=?";
				CommonUtils::execSql($pdo, $sql, array($cashBillId,$billid));
			} else {
				error_log("RecreateBill could not recreate a tip bill");
				return array("status" => "ERROR","msg" => "Trinkgeldbon liess sich nicht erstellen");
			}
		}
                                        
		Hotelinterface::insertIntoHsin($pdo, $newBillId);
		
		return array("status" => "OK");
	}
	
	/*
	 * Cancel a bill - set all queue items to not paid and drop the bill entry
	 * Public: because it is called by demodata
	 */
	public function cancelBill($pdo,$billid,$stornocode,$reason,$doOwnTransaction,$doEcho,$checkStornoCode,$removeproducts = 0,$dateTime = null,$cancelTip=1) {
		date_default_timezone_set(DbUtils::getTimeZone());
		$currentTime = date('Y-m-d H:i:s');
		if (!is_null($dateTime)) {
			$currentTime = $dateTime;
		}
		
		$stornocodeInDb = CommonUtils::getConfigValue($pdo, 'stornocode', null);
		
		if (is_null($stornocodeInDb) && $checkStornoCode) {
			if ($doEcho) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_BILL_NOT_STORNO_CODE, "msg" => ERROR_BILL_NOT_STORNO_CODE_MSG));
			}
			return false;
		}

		if ($checkStornoCode) {
			if ($stornocode != $stornocodeInDb) {
				if ($doEcho) {
					echo json_encode(array("status" => "ERROR", "code" => ERROR_BILL_WRONG_STORNO_CODE, "msg" => ERROR_BILL_WRONG_STORNO_CODE_MSG));
				}
				return false;
			}
		}
		
		if (!is_numeric($billid)) {
			if ($doEcho) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_BILL_WRONG_NUMERIC_VALUE, "msg" => ERROR_BILL_WRONG_NUMERIC_VALUE_MSG));
			}
			return false;
		}
		
		if ($doOwnTransaction) {
			CommonUtils::setTransactionSerializable($pdo);
			$pdo->beginTransaction();
		}
		
		$sql = "SELECT brutto,netto,tax,tableid,closingid,status,paymentid,reservationid,guestinfo,intguestid,intguestpaid,tip,billidoftip FROM %bill% WHERE id=?";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array($billid));
		$row =$stmt->fetchObject();
		$closingId = null;
		if ($row != null) {
			$closingId = $row->closingid;
			
			$brutto = $row->brutto;
			$netto = $row->netto;
			$tax = $row->tax;
			$tableid = $row->tableid;
			$status = $row->status;
			$paymentid = $row->paymentid;
			$reservationid = $row->reservationid;
			$guestinfo = $row->guestinfo;
			$intguestpaid = $row->intguestpaid;
			$billidoftip = $row->billidoftip;
			if ($cancelTip == 1) {
					$tip = $row->tip;
			} else {
					$tip = null;
			}
		}
		
		if (!is_null($closingId) || ($status == 's') || ($status == 'x')) {
			if ($doOwnTransaction) {
				$pdo->rollBack();
                                CommonUtils::resetTransactionIsolationLevel($pdo);
			}
			if ($doEcho) {
				if (($status == 's') || ($status == 'x')) {
					echo json_encode(array("status" => "ERROR", "code" => ERROR_BILL_ALREADY_CANCELLED, "msg" => ERROR_BILL_ALREADY_CANCELLED_MSG));
				} else {
					echo json_encode(array("status" => "ERROR", "code" => ERROR_BILL_ALREADY_CLOSED, "msg" => ERROR_BILL_ALREADY_CLOSED_MSG));
				}
			}
			return false;
		}
		
		if (!is_null($intguestpaid)) {
			if ($doOwnTransaction) {
				$pdo->rollBack();
                                CommonUtils::resetTransactionIsolationLevel($pdo);
			}
			if ($doEcho) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_BILL_CUSTOMER_PAID, "msg" => ERROR_BILL_CUSTOMER_PAID_MSG));
			}
			return false;
		}
		
		$commonUtils = new CommonUtils();
		$correct = $commonUtils->verifyBill($pdo, $billid);
		if (!$correct) {
			if ($doOwnTransaction) {
				$pdo->rollBack();
                                CommonUtils::resetTransactionIsolationLevel($pdo);
			}
			if ($doEcho) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_INCONSISTENT_DB, "msg" => ERROR_INCONSISTENT_DB_MSG));
			}
			return false;
		}
		
		$nextbillid = self::testForNewBillIdAndUpdateWorkTable($pdo);
		if ($nextbillid < 0) {
			if ($doEcho) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_INCONSISTENT_DB, "msg" => ERROR_INCONSISTENT_DB_MSG));
			}
			if ($doOwnTransaction) {
				$pdo->rollBack();
				CommonUtils::resetTransactionIsolationLevel($pdo);
			}
			return false;
		}
		
		$sql = "SELECT id,ordertype,voucherid FROM %queue% WHERE billid=?";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array($billid));
		$result = $stmt->fetchAll();
		$queueIdArray = array();
		
		foreach($result as $row) {
			if ($removeproducts == 1) {
				Workreceipts::createCancelWorkReceipt($pdo, $row['id']);
			}
			$queueIdArray[] = $row['id'];
			
			$ordertype = $row["ordertype"];
			
			if (($removeproducts == 1) || ($ordertype == DbUtils::$ORDERTYPE_1ZweckEinl)) {
				$sql = "UPDATE %queue% SET toremove='1',paidtime=null,billid=null WHERE id=?";
				CommonUtils::execSql($pdo, $sql, array($row["id"]));
				vouchers::handleRemoveQueueItem($pdo, $row['id']);
				
				$signRemoval = QueueContent::signRemovalOfQueueItem($pdo, $row['id']);
				if ($signRemoval["status"] != "OK") {
					if ($doOwnTransaction) {
						$pdo->rollBack();
						CommonUtils::resetTransactionIsolationLevel($pdo);
					}

					if ($doEcho) {
						echo json_encode(array("status" => "ERROR","msg" => "TSE-Signierung fehlgeschlagen: " . $signRemoval["msg"]));
					}
					return false;
				}
			}
		}
		
		if ($removeproducts == 0) {
			$sql = "UPDATE %queue% SET paidtime=null,billid=null WHERE billid=?";
			CommonUtils::execSql($pdo, $sql, array($billid));
		}
		
		$userIdOfStornoUser = $this->getUserId();
		$stornval = 0.0 - floatval($brutto);
		$stornonettoval = 0.0 - floatval($netto);

		$signature = CommonUtils::calcSignatureForBill($currentTime, $stornval, $stornonettoval, $userIdOfStornoUser);
		
		$nextBillIds = self::getNextBillIds($pdo);
		$nextBilluid = $nextBillIds["nextbilluid"];
                
		if (!is_null($tip)) {
				$tip = 0-$tip;
		}
			
		$sql = "INSERT INTO `%bill%` (`id` , `billuid`, `billdate`,`brutto`,`netto`,`tax`,`tableid`, `status`, `paymentid`,`userid`,`ref`,`host`,`reservationid`,`guestinfo`,`signature`,`tip`) VALUES (?,?,?,?,?,?,?, 's', ?,?,?,?,?,?,?,?)";
		CommonUtils::execSql($pdo, $sql, array($nextbillid,$nextBilluid,$currentTime,$stornval,$stornonettoval,$tax,$tableid,$paymentid,$userIdOfStornoUser,$billid,0,$reservationid,$guestinfo,$signature,$tip));
		$refIdOfStornoEntry = $pdo->lastInsertId();
		
		$sql = "SELECT brutto,netto,prevbrutto,prevnetto FROM %bill% WHERE id=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($refIdOfStornoEntry-1));
		$row =$stmt->fetchObject();
		$sql = "UPDATE %bill% set prevbrutto=?,prevnetto=? WHERE id=?";
		CommonUtils::execSql($pdo, $sql, array($row->brutto + $row->prevbrutto + $stornval,$row->netto + $row->prevnetto + $stornonettoval,$refIdOfStornoEntry));
		
		$sql = "UPDATE %bill% SET status='x', closingid=null, ref=?, intguestid=?,intguestpaid=? WHERE id=?";
		CommonUtils::execSql($pdo, $sql, array($refIdOfStornoEntry,null,null,$billid));

		if (!is_null($reason) && ($reason != "")) {
			$sql = "UPDATE %bill% SET reason=? WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array($reason,$billid));
		}
		
		foreach ($queueIdArray as $aQueueid) {
			$billProdsSql = "INSERT INTO `%billproducts%` (`queueid` , `billid`) VALUES ( ?,?)";
			CommonUtils::execSql($pdo, $billProdsSql, array($aQueueid,$refIdOfStornoEntry));
		}

		self::setPositionsOfProductsOnBillProductsTable($pdo, $refIdOfStornoEntry);
		
		$status = self::signOrdersBill($pdo,$refIdOfStornoEntry,true);
		if ($status["status"] != "OK") {
			if ($doOwnTransaction) {
				$pdo->rollBack();
				CommonUtils::resetTransactionIsolationLevel($pdo);
				echo json_encode(array("status" => "ERROR", "msg" => "Rechnung kann nicht storniert werden? Ist die TSE korrekt eingebunden?"));
			}
			return false;
		}
		
		if ($tableid == 0) {
			$tableid = null;
		}
		$recordaction = T_BILLSTORNO;
		if ($removeproducts) {
			$recordaction = T_BILLSTORNOREMOVE;
		}
		if ($paymentid == DbUtils::$PAYMENT_GUEST) {
			$recordaction = T_BILLORDERBILLSTORNO;
			if ($removeproducts) {
				$recordaction = T_BILLORDERBILLSTORNOREMOVE;
			}
		}
		$sql = "INSERT INTO %records% (date,userid,tableid,action) VALUES(?,?,?,?)";
		CommonUtils::execSql($pdo, $sql, array($currentTime,$userIdOfStornoUser,$tableid,$recordaction));
		$recordid = $pdo->lastInsertId();
		foreach ($queueIdArray as $aQueueid) {
			$sql = "INSERT INTO %recordsqueue% (recordid,queueid) VALUES(?,?)";
			CommonUtils::execSql($pdo, $sql, array($recordid,$aQueueid));
		}
		
		if (($cancelTip == 1) && (!is_null($billidoftip))) {
			$sql = "SELECT userid FROM %bill% WHERE id=?";
			$r = CommonUtils::fetchSqlAll($pdo, $sql, array($billidoftip));
			$userIdWhoGotTipInitially = $r[0]["userid"];
			
			$cashopStatus = Bill::doCashActionCore($pdo,$tip, '', $currentTime, $userIdWhoGotTipInitially, Bill::$CASHTYPE_TrinkgeldBoth["value"],$paymentid,DENY_TRANSACTIONS,true);
			if ($cashopStatus["status"] != "OK") {
				if ($doOwnTransaction) {
					$pdo->rollBack();
					CommonUtils::resetTransactionIsolationLevel($pdo);
					if ($doEcho) {
							echo json_encode(array("status" => "ERROR", "msg" => "Rechnung kann nicht storniert werden? Ist die TSE korrekt eingebunden?"));
					}
				}
				return false;
			} else {
				if (is_null($tableid)) {
					$tableid = 0;
				}
				$sql = "UPDATE %bill% SET ref=?,tableid=? WHERE id=?";
				CommonUtils::execSql($pdo, $sql, array($billidoftip,$tableid,$cashopStatus["billid"]));
				$sql = "UPDATE %bill% SET ref=? WHERE id=?";
				CommonUtils::execSql($pdo, $sql, array($cashopStatus["billid"],$billidoftip));
			}
		}
		
		
		$austriaEnabled = CommonUtils::getConfigValue($pdo, "austria", 0);
		$austriabind = CommonUtils::getConfigValue($pdo, 'austriabind', 0);
		if (($austriaEnabled == 1) && ($austriabind == Rksv::FISKALY_SIGN_AT)) {
			$rksv = new Rksv();
			$standardV1 = $rksv->createStandardV1Schema($pdo,$queueIdArray,Rksv::RECEIPT_TYPE_CANCELLATION);
			$signRequest = SignAT::signReceipt($pdo, $standardV1);
			if ($signRequest['status'] == 'OK') {
				$fiskalysignresponse = $signRequest['msg'];
				$fiskalysignresponse->saveIntoBill($pdo,$nextbillid);
			} else {
				error_log("Cancellation failed with Fiskaly Sign AT: " . $signRequest['msg']);
			}
		}

		Hotelinterface::insertIntoHsin($pdo,$refIdOfStornoEntry);
		
		if ($doOwnTransaction) {
			$pdo->commit();
			CommonUtils::resetTransactionIsolationLevel($pdo);
		}
		
		if ($doEcho) {
			echo json_encode(array("status" => "OK", "code" => OK));
		}
		return true;
	}
        
	public function createZeroReceipt($pdo):array {
		date_default_timezone_set(DbUtils::getTimeZone());
		$currentTime = date('Y-m-d H:i:s');
		$userId = $this->getUserId();
		$austriaEnabled = CommonUtils::getConfigValue($pdo, "austria", 0);
		$austriabind = CommonUtils::getConfigValue($pdo, 'austriabind', 0);
		if (($austriaEnabled != 1) || ($austriabind != Rksv::FISKALY_SIGN_AT)) {
			return array("status" => "ERROR","msg" => "Österreich-Modus deaktiviert oder Fiskaly Sign AT nicht ausgewählt");
		}

		$pdo->beginTransaction();
		
		$nextbillid = self::testForNewBillIdAndUpdateWorkTable($pdo);
		if ($nextbillid < 0) {
			$pdo->rollBack();
			return array("status" => "ERROR", "code" => ERROR_INCONSISTENT_DB, "msg" => ERROR_INCONSISTENT_DB_MSG);
		}
		
		$signature = CommonUtils::calcSignatureForBill($currentTime, 0.0, 0.0, $userId);
		
		$nextBillIds = self::getNextBillIds($pdo);
		$nextBilluid = $nextBillIds["nextbilluid"];
			
		$sql = "INSERT INTO `%bill%` (`id` , `billuid`, `billdate`,`brutto`,`netto`,`tax`,`tableid`, `status`, `paymentid`,`userid`,`ref`,`host`,`reservationid`,`guestinfo`,`signature`,`tip`) VALUES (?,?,?,?,?,?,?,null, ?,?,?,?,?,?,?,?)";
		CommonUtils::execSql($pdo, $sql, array($nextbillid,$nextBilluid,$currentTime,0.0,0.0,0.0,0,1,$userId,null,0,null,null,$signature,0.0));
                
                $rksv = new Rksv();
                $standardV1 = $rksv->createStandardV1Schema($pdo,array(),Rksv::RECEIPT_TYPE_NORMAL);
                $signRequest = SignAT::signReceipt($pdo, $standardV1);
                if ($signRequest['status'] == 'OK') {
                        $fiskalysignresponse = $signRequest['msg'];
                        $fiskalysignresponse->saveIntoBill($pdo,$nextbillid);
                } else {
                        error_log("Signing Nullbeleg failed with Fiskaly Sign AT: " . $signRequest['msg']);
                }
                
		$pdo->commit();
		
		return array("status" => "OK","msg" => "Nullbeleg wurde erstellt");
	}
	
	private function autoBackupPdfSummary($remoteaccesscode) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		
		$code = CommonUtils::getConfigValue($pdo, 'remoteaccesscode', null);
		
		if (is_null($code)) {
			echo "No remote access code available - backup not allowed";
			return;
		}
		
		if (is_null($code) || (trim($code) == "")) {
			echo "No remote access code set - backup not allowed";
			return;
		}
		
		if ($code != md5($remoteaccesscode)) {
			echo "Wrong remote access code used - backup not allowed";
			return;
		}
		$pdo = null;
		
		date_default_timezone_set(DbUtils::getTimeZone());
		$currentYear = date('Y');
		$currentMonth = date('n');
		
		$this->exportPdfSummary(1, $currentYear, $currentMonth, $currentYear);
	}
	
	private function exportPdfReport($startMonth,$startYear,$endMonth,$endYear,$closidstart = null,$closidend = null) {
		$pdfExport = new PdfExport();
		$lang = 0;
		if(isset($_GET["lang"])) {
			$lang = $_GET['lang'];
		}
		$pdfExport->exportPdfReport($lang,$startMonth,$startYear,$endMonth,$endYear,$closidstart,$closidend);
	}
	private function exportPdfSummary($startMonth,$startYear,$endMonth,$endYear) {
		$pdfExport = new PdfExport();
		$lang = 0;
		if(isset($_GET["lang"])) {
			$lang = $_GET['lang'];
		}
		$pdfExport->exportPdfSummary($lang,$startMonth,$startYear,$endMonth,$endYear);
	}
	
	private function exportPdfSummaryClosPeriod($closidstart,$closidend) {
		$pdfExport = new PdfExport();
		$lang = 0;
		if(isset($_GET["lang"])) {
			$lang = $_GET['lang'];
		}
		$pdfExport->exportPdfSummaryClosPeriod($lang,$closidstart,$closidend);
	}
	private function exportCsvSummaryClosPeriod($closidstart,$closidend) {
		$pdfExport = new PdfExport();
		$lang = 0;
		if(isset($_GET["lang"])) {
			$lang = $_GET['lang'];
		}
		$pdfExport->exportCsvSummaryClosPeriod($lang,$closidstart,$closidend);
	}
	
	private function exportAllCsvOrExcel($startMonth,$startYear,$endMonth,$endYear,$exportFormat) {
		set_time_limit(60*5);
		if(session_id() == '') {
			session_start();
		}
		$l = $_SESSION['language'];
		
		$commonUtils = new CommonUtils();
		$currency = $commonUtils->getCurrency();
		
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$decpoint = CommonUtils::getConfigValue($pdo, 'decpoint', '.');
		
		if ($startMonth < 10) {
			$startMonth = "0" . $startMonth;
		}
		if ($endMonth < 10) {
			$endMonth = "0" . $endMonth;
		}
		$startDate = $startYear . "-" . $startMonth . "-01 00:00:00";
		$endDate = $endYear . "-" . $endMonth . "-01";
		$lastdayOfMonth = date("t", strtotime($endDate));
		$endDate = $endYear . "-" . $endMonth . "-" . $lastdayOfMonth . " 23:59:59";
		
		$objPHPExcel = new PHPExcel();
		
		PHPExcel_Settings::setZipClass(PHPExcel_Settings::PCLZIP);
		$locale = 'De';
		if ($l == 1) {
			$locale = 'En';
		} else if ($l == 2) {
			$locale = 'Es';
		}
		$validLocale = PHPExcel_Settings::setLocale($locale);
		
		$objPHPExcel->getProperties()
		->setCreator("OrderSprinter")
		->setLastModifiedBy($_SESSION['currentuser'])
		->setTitle("OrderSprinter Umsatzdatenexport")
		->setSubject("OrderSprinter Umsatzdatenexport")
		->setDescription("Umsätze")
		->setKeywords("OrderSprinter Umsätze")
		->setCategory("OrderSprinter Datenexport");
		
		$objWorksheet = $objPHPExcel->getActiveSheet();
		
		$allcells = array();
		
		$firstRow = array(
				'Bestellzeitpunkt',
				'Abrechnungszeitpunkt',
				'Tag der Bestellung',
				$this->t['ID'][$l],
				$this->t['Tablename'][$l],
				'Produktpreis' . " ($currency)",
				$this->t['Brutto'][$l] ."($currency)",
                                "Brutto abgerechnet ($currency)",
				$this->t['Netto'][$l] . "($currency)",
                                "Netto abgerechnet ($currency)",
				'Umsatzsteuer (%)',
				$this->t['host'][$l],
				$this->t['Ref'][$l],
				$this->t['State'][$l],
				'Bestell-ID',
				'Produkt',
				'Produkt-ID',
				'Barcode',
				$this->t['PayWay'][$l],
				$this->t['reason'][$l],
				'Abrechnungsbenutzer',
				'Abrechnungsbenutzer-ID',
                                'Tags',
				$this->t['ClosDate'][$l],
				$this->t['ClosRemark'][$l],
				$this->t['ClosId'][$l]
			);
		
		$lineLength = count($firstRow);
		
		$allcells[] = $firstRow;
		
		$payment_lang = array("name","name_en","name_esp");
		$payment_col = $payment_lang[$l];

		$unit = CommonUtils::caseOfSqlUnitSelection($pdo);
		
		$cashReadable = self::getTranslateSqlCashTypeToReadable();
                $billuidSql = self::getBillUidSqlPart();
		
		$sql = "
			SELECT * FROM (
			SELECT B.billdate as billdate,$billuidSql as billid,
				ordertime,DATE_FORMAT(ordertime,'%w') as orderdayofweek,
			       IF(tableid > '0',(SELECT tableno FROM %resttables% WHERE id=tableid),'') as tablename,
			       price as productprice,
			       (IF(B.status='s',-1,1) * price) as brutto,
                               (CASE WHEN B.paymentid='" . DbUtils::$PAYMENT_GUEST . "' THEN REPLACE('0.00','.','$decpoint') ELSE (IF(B.status='s',-1,1) * price) END) as realbrutto,
			       (IF(B.status='s',-1,1) * Q.price / (1 + Q.tax * 0.01)) as netto,
                               (CASE WHEN B.paymentid='" . DbUtils::$PAYMENT_GUEST . "' THEN REPLACE('0.00','.','$decpoint') ELSE (IF(B.status='s',-1,1) * Q.price / (1 + Q.tax * 0.01)) END) as realnetto,
			       Q.tax as tax,
			       (IF(B.host = '1','x','-')) as host,
			       COALESCE(B.ref,'') as reference,
			       B.status as status,
                               '' as cashtype,
			       Q.id as queueid,
			       CONCAT($unit,PN.name) as productname,
			       productid,
			       COALESCE((SELECT barcode from %products% PR WHERE PR.id=productid),'') as barcode,
			       P.$payment_col as payment,
			       COALESCE(B.reason,'') as reason, 
			       U.username,U.id as userid,C.closingdate as closingdate, COALESCE(C.remark,'') as remark,
			       B.closingid as clsid,
                               REPLACE(COALESCE(PROD.tags,''),'\n',' ') as tags 
			       from %billproducts% BP,%queue% Q,%prodnames% PN,%bill% B,%payment% P,%user% U,%closing% C,%products% PROD 
			       WHERE Q.prodnameid=PN.id AND BP.queueid=Q.id AND BP.billid=B.id AND B.closingid is not null AND B.paymentid=P.id 
			       AND U.id=B.userid AND B.closingid=C.id   
                               AND Q.productid=PROD.id 
			       AND B.billdate >= ? AND B.billdate <= ? 
			UNION ALL 
			SELECT '' as billdate, '' as billid,
				ordertime,DATE_FORMAT(ordertime,'%w') as orderdayofweek,
				IF(tablenr > '0',(SELECT tableno FROM %resttables% WHERE id=tablenr),'') as tablename,
				price as productprice,
				'0.00' as brutto,
                                '0.00' as realbrutto,
				'0.00' as netto,
                                '0.00' as realnetto,
				Q.tax as tax,
				'-' as host,
				'' as reference,
				'd' as status,
				'' as cashtype,
				Q.id as queueid,
				CONCAT($unit,PN.name) as productname,
				productid,
				COALESCE((SELECT barcode from %products% PR WHERE PR.id=productid),'') as barcode,
				'' as payment,
				'' as reason,
				'' as username,'' as userid,
				'' as closingdate, '' as remark,
				Q.clsid,
                                '' as tags 
				FROM %queue% Q, %prodnames% PN 
				WHERE Q.toremove = '1' 
                                AND Q.prodnameid = PN.id 
				AND ordertime >= ? AND ordertime <= ? 
				AND paidtime is null
			UNION ALL 
			SELECT B.billdate as billdate,$billuidSql as billid,B.billdate as ordertime,
				DATE_FORMAT(B.billdate,'%w') as orderdayofweek,
				'' as tablename, 
				'0.00' as productprice,
				B.brutto as brutto,B.brutto as realbrutto,B.netto as netto,B.netto as realnetto,
				'0.00' as tax,
				'' as host,
				'' as reference,
				status,
				$cashReadable,
				'' as queueid,
				'Einlage' as productname,
			       '' as productid,
			       '' as barcode,
			       'Barzahlung' as payment, COALESCE(B.reason,'') as reason, U.username, U.id as userid,C.closingdate as closingdate,COALESCE(C.remark,'') as remark,
			       closingid,
                               '' as tags  
			       FROM %bill% B,%user% U,%closing% C WHERE B.status='c' AND B.closingid is not null AND B.userid=U.id AND B.closingid=C.id 
			       AND B.billdate >= ? AND B.billdate <= ?  
			) a ORDER BY ordertime,billid
		";

		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($startDate,$endDate,$startDate,$endDate,$startDate,$endDate));

		foreach($result as $z) {
			set_time_limit(60*5);
			
			$prodprice = $z['productprice'];
			$brutto = $z['brutto'];
                        if (is_string($brutto)) {
                                $brutto = doubleval($brutto);
                        }
			$netto = $z['netto'];
                        $realbrutto = $z['realbrutto'];	
                        if (is_string($realbrutto)) {
                                $realbrutto = doubleval($realbrutto);
                        }
                        $realnetto = $z['realnetto'];
			$tax = $z['tax'];
			if ($exportFormat == DO_CSV) {
				$prodprice = number_format($prodprice, 2, $decpoint, '');
				$brutto = number_format($brutto, 2, $decpoint, '');
				$netto = str_replace(".",$decpoint,$netto);
                                $realbrutto = number_format($realbrutto, 2, $decpoint, '');
				$realnetto = str_replace(".",$decpoint,$realnetto);
				if ($tax != '') {
					$tax = number_format($tax, 2, $decpoint, '');
				}
			}
			
			$status = $z['status'];
			
			$cat = 'Produktverkauf';
			if ($status == 'x') {
				$cat = $this->t["laterCancelled"][$l];
			} else if ($status == 's') {
				$cat = $this->t["storno"][$l];
			} else if ($status == 'c') {
                                $cat = $this->t["cashact"][$l] . " " . $z['cashtype'];
			} else if ($status == 'd') {
				$cat = 'Storno vor Abrechnung';
			}
			
			$line = array(
			    $z['ordertime'],
			    $z['billdate'],
			    self::$daynamesStartSunday[intval($z['orderdayofweek'])][$l],
			    $z['billid'],$z['tablename'],
			    $prodprice,$brutto,$realbrutto,$netto,$realnetto,
			    $tax,
			    $z['host'],
			    $z['reference'],
			    $cat,
			    $z['queueid'],
			    $z['productname'],
			    $z['productid'],
			    $z['barcode'],
			    $z['payment'],
			    $z['reason'],
			    $z['username'],$z['userid'],
                            $z['tags'],
			    $z['closingdate'],$z['remark'],$z['clsid']);

			$allcells[] = $line;
		}
		
		$objWorksheet->fromArray(
				$allcells,  // The data to set
				NULL,        // Array values with this value will not be set
				'A1'         // Top left coordinate of the worksheet range where
		);
		
		$lastChar = chr(ord('A') + $lineLength - 1);
		$range = "A1:$lastChar" . "1";
		$objWorksheet->getStyle($range)->getFill()
		->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
		->getStartColor()->setARGB('FFadf6aa');
		
		$range = "A2:" . $lastChar . count($allcells);
		$objWorksheet->getStyle($range)->getFill()
		->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
		->getStartColor()->setARGB('FFd6edf8');
		
		if ($exportFormat == DO_EXCEL) {
			$formatCodeBrutto = "0.00";
			$formatCodeNetto = "0.0000";
			for ($i=1;$i<count($allcells);$i++) {
				$aVal = $objWorksheet->getCell('F' . ($i+1)) ->getValue();
				$objWorksheet->getCell('F' . ($i+1)) ->setValueExplicit($aVal,PHPExcel_Cell_DataType::TYPE_NUMERIC);
				$objWorksheet->getStyle('F' . ($i+1))->getNumberFormat()->setFormatCode($formatCodeBrutto);
				
				$aVal = $objWorksheet->getCell('G' . ($i+1)) ->getValue();
				$objWorksheet->getCell('G' . ($i+1)) ->setValueExplicit($aVal,PHPExcel_Cell_DataType::TYPE_NUMERIC);
				$objWorksheet->getStyle('G' . ($i+1))->getNumberFormat()->setFormatCode($formatCodeBrutto);

				$aVal = $objWorksheet->getCell('H' . ($i+1)) ->getValue();
				$objWorksheet->getCell('H' . ($i+1)) ->setValueExplicit($aVal,PHPExcel_Cell_DataType::TYPE_NUMERIC);
				$objWorksheet->getStyle('H' . ($i+1))->getNumberFormat()->setFormatCode($formatCodeNetto);
			}
		}
		
		if ($exportFormat == DO_CSV) {
			header("Content-type: text/x-csv");
			header("Content-Disposition: attachment; filename=\"ordersprinter-datenexport.csv\"");
			header("Cache-Control: max-age=0");
			$objWriter = new PHPExcel_Writer_CSV($objPHPExcel);
			$objWriter->setDelimiter(';');
		} else {
			header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
			header("Content-Disposition: attachment; filename=\"ordersprinter-datenexport.xls\"");
			header("Cache-Control: max-age=0");
			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		}

		$objWriter->save("php://output");
		
		$objPHPExcel->disconnectWorksheets();
		unset($objPHPExcel);
	}
        
	private function exportCsv($startMonth,$startYear,$endMonth,$endYear,$exportType) {
		$this->exportCsv_bin($startMonth,$startYear,$endMonth,$endYear,null,$exportType);
	}
	
	/*
	 * Method to export data of a special closing
	 */
	private function exportCsvOfClosing($closingid,$exportFormat) {
		$this->exportCsv_bin(null,null,null,null,$closingid,$exportFormat);
	}
	
	private function exportCsv_bin($startMonth,$startYear,$endMonth,$endYear,$onlyClosingId,$exportFormat) {
		if(session_id() == '') {
			session_start();
		}
		$l = $_SESSION['language'];
		
		$commonUtils = new CommonUtils();
		$currency = $commonUtils->getCurrency();
		
		$decpoint = ".";

		$formatCode = "0.00";
		
		if ($onlyClosingId == null) {
			if ($startMonth < 10) {
				$startMonth = "0" . $startMonth;
			}
			if ($endMonth < 10) {
				$endMonth = "0" . $endMonth;
			}
			$startDate = $startYear . "-" . $startMonth . "-01 00:00:00";
			$endDate = $endYear . "-" . $endMonth . "-01";
			$lastdayOfMonth = date("t", strtotime($endDate));
			$endDate = $endYear . "-" . $endMonth . "-" . $lastdayOfMonth . " 23:59:59";
		}
		
		$objPHPExcel = new PHPExcel();
		
		PHPExcel_Settings::setZipClass(PHPExcel_Settings::PCLZIP);
		$locale = 'De';
		if ($l == 1) {
			$locale = 'En';
		} else if ($l == 2) {
			$locale = 'Es';
		}
		$validLocale = PHPExcel_Settings::setLocale($locale);
		
		$objPHPExcel->getProperties()
		->setCreator("OrderSprinter")
		->setLastModifiedBy($_SESSION['currentuser'])
		->setTitle("OrderSprinter Umsatzdatenexport")
		->setSubject("OrderSprinter Umsatzdatenexport")
		->setDescription("Umsätze")
		->setKeywords("OrderSprinter Umsätze")
		->setCategory("OrderSprinter Datenexport");
		
		$objWorksheet = $objPHPExcel->getActiveSheet();
		
		
		
		$allcells = array();
		
		$firstRow = array(
				$this->t['ID'][$l],
				$this->t['Date'][$l],
				$this->t['Brutto'][$l] ."($currency)",
				$this->t['Netto'][$l] . "($currency)",
				$this->t['Tablename'][$l],
				$this->t['State'][$l],
				$this->t['Ref'][$l],
				$this->t['host'][$l],
				$this->t['reason'][$l],
				$this->t['Userid'][$l],
				$this->t['User'][$l]);
		
		if ($onlyClosingId == null) {
			$firstRow[] = $this->t['ClosId'][$l];
			$firstRow[] = $this->t['ClosDate'][$l];
			$firstRow[] = $this->t['PayWay'][$l];
			$firstRow[] = $this->t['ClosRemark'][$l];
		} else {
			$firstRow[] = $this->t['PayWay'][$l];
		}
		
		$lineLength = count($firstRow);
		
		$allcells[] = $firstRow;
		
		$billIdsForThatClosing = array();
		
		$payment_lang = array("name","name_en","name_esp");
		$payment_col = $payment_lang[$l];
		
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$cashtypeReadableSql = self::getTranslateSqlCashTypeToReadable();
		$sql = "SELECT DISTINCT %bill%.id as dbbillid,(CASE WHEN %bill%.status='c' THEN CONCAT('V-',%bill%.billuid) ELSE %bill%.billuid END) as id,IF(tableid > '0',(SELECT tableno FROM %resttables% WHERE id=tableid),'') as tablename,%bill%.signature,billdate,brutto,CAST(ROUND(netto,2) as DECIMAL(12,2)) as netto,IF(tax is not null, tax, '0.00') as tax,status,closingdate,remark,%bill%.host,%bill%.closingid,%payment%.$payment_col as payway,userid,ref,username,IF(%bill%.reason is not null,reason,'') as reason,$cashtypeReadableSql FROM %bill%,%closing%,%payment%,%user% ";
		$sql .= "WHERE closingid is not null AND %bill%.closingid=%closing%.id ";
		$sql .= " AND %bill%.paymentid=%payment%.id AND %bill%.paymentid <> ?";
		if ($onlyClosingId == null) {
			$sql .= " AND %bill%.billdate BETWEEN ? AND ? ";
		} else {
			$sql .= " AND closingid=? ";
		}
		$sql .= " AND %bill%.userid = %user%.id ";
		$sql .= "ORDER BY billdate";

		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		
		if ($onlyClosingId == null) {
			$stmt->execute(array(DbUtils::$PAYMENT_GUEST,$startDate,$endDate));
		} else {
			$stmt->execute(array(DbUtils::$PAYMENT_GUEST,$onlyClosingId));
		}
		
		$dbresult = $stmt->fetchAll();
		foreach($dbresult as $zeile) {
			$billid = $zeile['id'];
			$tablename = $zeile['tablename'];
			$billdate = $zeile['billdate'];
				
			$brutto_orig = $zeile['brutto'];
			$netto_orig = $zeile['netto'];
			$tax_orig = $zeile['tax'];
				
			$brutto = str_replace(".",$decpoint,$brutto_orig);
			$netto = str_replace(".",$decpoint,$netto_orig);
			$tax = str_replace(".",$decpoint,$tax_orig);
			$signature = $zeile['signature'];
			$dbstatus = $zeile['status'];
			$status = $zeile['status'];
			if ($status == 'x') {
				$status = $this->t["laterCancelled"][$l];
			} else if ($status == 's') {
				$status = $this->t["storno"][$l];
			} else if ($status == 'c') {
				$status = $zeile['cashtype'];
			} else {
				$status = "";
			}
			$ref = ($zeile['ref'] == null ? "" : $zeile['ref']);
			$userid = $zeile['userid'];
			$username = $zeile['username'];
			$closingid = $zeile['closingid'];
			$closingdate = $zeile['closingdate'];
			$remark = $zeile['remark'];
			$paymentname = $zeile['payway'];
			$host = ($zeile['host'] == 1 ? "x" : "-");
			$reason = $zeile['reason'];

			if (!CommonUtils::verifyBillByValues(null,$billdate, $brutto_orig, $netto_orig, $userid, $signature,$dbstatus)) {
				echo "Inconsistent Data Base Content!\n";
				return;
			}
				
			if ($billid == null) {
				$billid = "-";
			}
				
			if ($onlyClosingId == null) {
				$line = array($billid , $billdate, $brutto, $netto, $tablename, $status, $ref, $host, $reason, $userid,$username , $closingid, $closingdate, $paymentname, $remark);
			} else {
				$line = array($billid , $billdate, $brutto, $netto, $tablename, $status, $ref, $host, $reason, $userid,$username , $paymentname);
			}
			$allcells[] = $line;
		}


		$objWorksheet->fromArray(
				$allcells,  // The data to set
				NULL,        // Array values with this value will not be set
				'A1'         // Top left coordinate of the worksheet range where
		);
		
		$lastChar = chr(ord('A') + $lineLength - 1);
		$range = "A1:$lastChar" . "1";
		$objWorksheet->getStyle($range)->getFill()
		->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
		->getStartColor()->setARGB('FFadf6aa');
		
		$range = "A2:" . $lastChar . count($allcells);
		$objWorksheet->getStyle($range)->getFill()
		->setFillType(PHPExcel_Style_Fill::FILL_SOLID)
		->getStartColor()->setARGB('FFd6edf8');
		
		for ($i=1;$i<count($allcells);$i++) {
			$aVal = $objWorksheet->getCell('C' . ($i+1)) ->getValue();
			$objWorksheet->getCell('C' . ($i+1)) ->setValueExplicit($aVal,PHPExcel_Cell_DataType::TYPE_NUMERIC);
			$objWorksheet->getStyle('C' . ($i+1))->getNumberFormat()->setFormatCode($formatCode);
			
			$aVal = $objWorksheet->getCell('D' . ($i+1)) ->getValue();
			$objWorksheet->getCell('D' . ($i+1)) ->setValueExplicit($aVal,PHPExcel_Cell_DataType::TYPE_NUMERIC);
			$objWorksheet->getStyle('D' . ($i+1))->getNumberFormat()->setFormatCode($formatCode);
		}		
		
		if ($exportFormat == DO_CSV) {
			header("Content-type: text/x-csv");
			header("Content-Disposition: attachment; filename=\"ordersprinter-datenexport.csv\"");
			header("Cache-Control: max-age=0");
			$objWriter = new PHPExcel_Writer_CSV($objPHPExcel);
			$objWriter->setDelimiter(';');
		} else {
			header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
			header("Content-Disposition: attachment; filename=\"ordersprinter-datenexport.xls\"");
			header("Cache-Control: max-age=0");
			$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, 'Excel5');
		}

		$objWriter->save("php://output");
		
		$objPHPExcel->disconnectWorksheets();
		unset($objPHPExcel);
	}
	
	public static function getTranslateSqlCashTypeToReadable() {
		$sql = "(IF(cashtype is null,'',CASE 
                        WHEN cashtype='" . self::$CASHTYPE_Privatentnahme['value'] . "' THEN 'Pr.entn./einl.' 
                        WHEN cashtype='" . self::$CASHTYPE_Privateinlage['value'] . "' THEN 'Pr.einl.' 
			WHEN cashtype='" . self::$CASHTYPE_Geldtransit['value'] . "' THEN 'Geldtransit' 
                        WHEN cashtype='" . self::$CASHTYPE_Lohnzahlung['value'] . "' THEN 'Lohnzahlung' 
			WHEN cashtype='" . self::$CASHTYPE_Einzahlung['value'] . "' THEN 'Einzahlung' 
                        WHEN cashtype='" . self::$CASHTYPE_Auszahlung['value'] . "' THEN 'Auszahlung' 
                        WHEN cashtype='" . self::$CASHTYPE_TrinkgeldAN['value'] . "' THEN 'TG an AN' 
                        WHEN cashtype='" . self::$CASHTYPE_TrinkgeldAG['value'] . "' THEN 'TG an AG' 
                        WHEN cashtype='" . self::$CASHTYPE_DifferenzSollIst['value'] . "' THEN 'DiffSollIst' 
                        ELSE '' END)) as cashtype";
		return $sql;
	}
	private static function isBillAssignedToGuestAndPaid($pdo,$billid) {
		$sql = "SELECT paymentid,intguestid,COALESCE(intguestpaid,0) as intguestpaid,C.name as customername FROM %bill% B LEFT JOIN %customers% C ON B.intguestid=C.id WHERE B.id=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($billid));
		if (count($result) > 0) {
			$entry = $result[0];
			
			if ($entry["intguestpaid"] == 1) {
				return $entry["customername"];
			}
		}
		return null;
	}
	
	public static function getNextBillIds($pdo) {
		$sql = "SELECT MAX(id) as maxid FROM %bill%";
		$maxIdRes = CommonUtils::fetchSqlAll($pdo, $sql);
		$maxId = $maxIdRes[0]["maxid"];

		$sql = "SELECT (COALESCE(MAX(billuid),0)) as maxbilluid FROM %bill% WHERE (status is null OR status <> 'c') AND paymentid <> ?";
		$maxuidRes = CommonUtils::fetchSqlAll($pdo, $sql,array(DbUtils::$PAYMENT_GUEST));
		$maxBilluid = $maxuidRes[0]["maxbilluid"];
		$nextBilluid = intval($maxBilluid) + 1;
		return array("maxid" => $maxId,"nextbilluid" => $nextBilluid);
	}
}