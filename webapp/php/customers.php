<?php
// Datenbank-Verbindungsparameter
require_once ('dbutils.php');
require_once ('commonutils.php');
require_once ('admin.php');
require_once ('reports.php');
require_once ('utilities/pdfexport.php');
require_once ('utilities/Emailer.php');
require_once ('3rdparty/phpexcel/classes/PHPExcel.php');

class Customers {
	
	private static $rights = array(
		"createNewCustomer"	    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"deleteCustomer"	    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"changeCustomer"	    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"getCustomers"		    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"createNewGroup"	    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"getGroups"		    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"changeGroup"		    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"deleteGroup"		    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"getVacations"		    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"newVacation"		    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"delVacation"		    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"getCustomersForReserv"	    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("reservation")),
		"getBills"		    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"pay"			    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"unpay"			    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"printbill"		    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"payallbills"		    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"printallbills"		    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"emailGroup"		    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"exportLog"		    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"getPaymentsForGuest"	    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"reportbills"		    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"reportcustomerbills"	    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"payallguests"		    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"assigncustomerstogroup"    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"assigncustomerstonewgroup" => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
		"removefromgroup"	    => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers")),
                "exportguests"              => array("loggedin" => 1, "isadmin" => 0, "rights" => array("customers"))
	    );
	
	public static $CANCEL = 0;
	public static $PAY = 1;
	
	private static $CUS_BILL_ID = array("Bon-ID","Bill ID","ID");
	private static $CUS_OVERVIEW = array("Übersicht über alle unbezahlten Gästerechnungen","Overview of unpaid customer bills","Resumen de las facturas non-pagadas de huéspedes");
	private static $CUS_CREATED = array("Erstellt","Created","Creado");
	private static $CUS_DATE = array("Datum","Date","Fecha");
	private static $CUS_BILL_SUM = array("Betrag","Sum","Suma");
	private static $CUS_SUM = array("Summe","Sum","Suma");
	private static $CUS_NO_BILLS = array("Keine offenen Rechnungen","No unpaid bills","No hay ninguna factura non-pagada");
	
	private static function checkRights($command) {
	    if(session_id() == '') {
	    	session_start();
	    }
	    if (!array_key_exists($command, self::$rights)) {
		echo json_encode(array("status" => "ERROR", "code" => ERROR_COMMAND_NOT_FOUND, "msg" => ERROR_COMMAND_NOT_FOUND_MSG));
		return false;
	    }
	    $cmdRights = self::$rights[$command];
	    if ($cmdRights["loggedin"] == 1) {
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_NOT_AUTHOTRIZED, "msg" => ERROR_NOT_AUTHOTRIZED_MSG));
			return false;
		}
	    }
	    if ($cmdRights["isadmin"] == 1) {
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_NOT_AUTHOTRIZED, "msg" => ERROR_NOT_AUTHOTRIZED_MSG));
			return false;
		} else {
		    if ($_SESSION['is_admin'] == 0) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_COMMAND_NOT_ADMIN, "msg" => ERROR_COMMAND_NOT_ADMIN_MSG));
			return false;
		    }
		}
	    }
	    if (!is_null($cmdRights["rights"])) {
		foreach($cmdRights["rights"] as $aRight) {
		    if ($aRight == 'customers') {
				if ($_SESSION['right_customers'] == 1) {
				    return true;
				}
		    }
		    if ($aRight == 'reservation') {
			    if ($_SESSION['right_reservation'] == 1) {
				    return true;
			    }
		    }
		}
		echo json_encode(array("status" => "ERROR", "code" => ERROR_NOT_AUTHOTRIZED, "msg" => ERROR_NOT_AUTHOTRIZED_MSG));
		return false;
	    }
	    return true;
	}
	
	function __construct() {
		//
	}
	
	function handleCommand($command) {    
		if (($command == "reportbills") || ($command == "reportcustomerbills") || ($command == "exportguests")) {
                        if(session_id() == '') {
                                session_start();
                        }
			
			
			if (!isset($_SESSION['right_customers'])) {
				echo "ERROR: no sufficient rights";
				return;
			}
			if (!$_SESSION['right_customers']) {
				echo "ERROR: no sufficient rights";
				return;
			}
			
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			self::$command($pdo);
			return;
		}
                
                $pdo = DbUtils::openDbAndReturnPdoStatic();
		
                if (isset($_POST['remoteaccesscode'])) {
                        $code = CommonUtils::getConfigValue($pdo, 'remoteaccesscode', null);
                        if (is_null($code) || ($code == '')) {
                                echo "Remote access code is not set";
				return;
                        }
                        if ($code != md5($_POST['remoteaccesscode'])) {
				echo "Wrong remote access code used";
				return;
			}
                } else {
                        if(session_id() == '') {
                                session_start();
                        }
                        if (!self::checkRights($command)) {
                                return false;
                        }
                }

		
		if ($command == 'createNewCustomer') {
			echo json_encode($this->createNewCustomer($pdo,$_POST['name'],$_POST['email'],$_POST['addr'],$_POST['remark'],$_POST['phone'],$_POST['mobil'],$_POST['www'],$_POST['hello'],$_POST['regards'],$_POST['checkin'],$_POST['checkout'],$_POST['room'],$_POST['permanentguest']));
		} else if ($command == 'deleteCustomer') {
			echo json_encode($this->deleteCustomer($pdo,$_POST['id']));
		} else if ($command == 'changeCustomer') {
			echo json_encode($this->changeCustomer($pdo,$_POST["id"],$_POST['name'],$_POST['email'],$_POST['addr'],$_POST['remark'],$_POST['phone'],$_POST['mobil'],$_POST['www'],$_POST['hello'],$_POST['regards'],$_POST['permanent'],$_POST["groups"]));
		} else if ($command == 'getCustomers') {
			echo json_encode(self::getCustomers($pdo,$_POST['search'],$_POST['remark'],$_POST['address'],$_POST['date'],$_POST['onlyopenbills']));
		} else if ($command == 'createNewGroup') {
			echo json_encode($this->createNewGroup($pdo,$_POST['name'],$_POST['remark']));
		} else if ($command == 'getGroups') {
			echo json_encode($this->getGroups($pdo,$_POST['search']));
		} else if ($command == 'changeGroup') {
			echo json_encode($this->changeGroup($pdo,$_POST["id"],$_POST['name'],$_POST['remark']));
		} else if ($command == 'deleteGroup') {
			echo json_encode($this->deleteGroup($pdo,$_POST['id']));
		} else if ($command == 'getCustomersForReserv') {
			echo json_encode($this->getCustomersForReserv($pdo,$_POST['search']));
		} else if ($command == 'getVacations') {
			echo json_encode($this->getVacations($pdo,$_GET['cusid']));
		} else if ($command == 'newVacation') {
			echo json_encode($this->newVacation($pdo,$_POST['id'],$_POST['checkin'],$_POST['checkout'],$_POST['room'],$_POST['remark']));
		} else if ($command == 'delVacation') {
			echo json_encode($this->delVacation($pdo,$_POST['id']));
		} else if ($command == 'getBills') {
			echo json_encode(self::getAllBills($pdo,$_GET['cusid']));
		} else if ($command == 'pay') {
			echo json_encode($this->pay($pdo,$_POST['id'],$_SESSION['userid'],$_POST['paymentid']));
		} else if ($command == 'unpay') {
			echo json_encode($this->unpay($pdo,$_POST['id'],$_SESSION['userid'],$_POST["code"],$_POST["remark"]));
		} else if ($command == 'printbill') {
			echo json_encode($this->printBill($pdo,$_POST['id']));
		} else if ($command == 'payallbills') {
			echo json_encode($this->payallbills($pdo,$_POST['id'],$_SESSION['userid'],$_POST['paymentid']));
		} else if ($command == 'printallbills') {
			echo json_encode($this->printallbills($pdo,$_POST['id']));
		} else if ($command == 'emailGroup') {
			echo json_encode($this->emailGroup($pdo,$_POST['groupid'],$_POST["subject"],$_POST["bcc"],$_POST["text"]));
		} else if ($command == 'getPaymentsForGuest') {
			echo json_encode($this->getPaymentsForGuest($pdo));
		} else if ($command == 'exportLog') {
			self::exportLog($pdo);
		} else if ($command == 'payallguests') {
			echo json_encode(self::payallguests($pdo,$_POST['paymentid'],$_SESSION['userid']));
		} else if ($command == 'assigncustomerstogroup') {
			echo json_encode(self::assigncustomerstogroup($pdo,$_POST['customers'],$_POST['groupid']));
		} else if ($command == 'assigncustomerstonewgroup') {
			echo json_encode(self::assigncustomerstonewgroup($pdo,$_POST['customers'],$_POST['groupname']));
		} else if ($command == 'removefromgroup') {
			echo json_encode(self::removefromgroup($pdo,$_POST['customers'],$_POST['groupid']));    
		} else {
			echo "Command not supported.";
		}
	}

	// for internal request
	private function hasCurrentUserCustomersRights() {
		session_start();
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			// no user logged in
			return false;
		} else {
			return ($_SESSION['right_customers']);
		}
	}

	public function createNewCustomer($pdo,$name,$email,$addr,$remark,$phone,$mobil,$www,$hello,$regards,$checkin,$checkout,$room,$permanentguest) {
		date_default_timezone_set(DbUtils::getTimeZone());
		$currentTime = date('Y-m-d H:i:s');
		
		if ($checkin == '') {
			$checkin = null;
		}
		if ($checkout == '') {
			$checkout = null;
		}
		if ($room == '') {
			$room = null;
		}
		if ($hello == '') {
			$hello = null;
		}
		if ($regards == '') {
			$regards = null;
		}
		
		try {
			$pdo->beginTransaction();
			$sql = "SELECT count(id) as countid from %customers% WHERE name=?";
			$row = CommonUtils::getRowSqlObject($pdo, $sql, array($name));
			$number = $row->countid;
			if ($row->countid > 0) {
				$code = 1;	
			} else {
				$code = 0;
			}

			$sql = "INSERT INTO %customers% (name,email,address,remark,phone,mobil,www,hello,regards,created,lastmodified,permanent) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)";		
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($name,$email,$addr,$remark,$phone,$mobil,$www,$hello,$regards,$currentTime,$currentTime,$permanentguest));
			$cusid = $pdo->lastInsertId();
			self::addVacationsCore($pdo,$cusid,$checkin,$checkout,$room,null);

			$pdo->commit();
			return array("status" => "OK","code" => $code, "value" => $number,"customerid" => $cusid);
		} catch (Exception $e) {
			$pdo->rollBack();
			return array("status" => "ERROR","msg" => $e->getMessage());
		}
	}
	
	private static function addVacationsCore($pdo,$cusid,$checkin,$checkout,$room,$remark) {
		if ($checkin == '') {
			$checkin = null;
		}
		if ($checkout == '') {
			$checkout = null;
		}
		if (!is_null($checkin) || !is_null($checkout)) {
			$sql = "INSERT INTO %vacations% (customerid,checkin,checkout,room,remark) VALUES(?,?,?,?,?)";
			CommonUtils::execSql($pdo, $sql, array($cusid,$checkin,$checkout,$room,$remark));
		}
	}
	
	private function createNewGroup($pdo,$name,$remark) {
		date_default_timezone_set(DbUtils::getTimeZone());
		$currentTime = date('Y-m-d H:i:s');
		
		try {
			$sql = "SELECT count(id) as countid from `%groups%` WHERE name=?";
			$row = CommonUtils::getRowSqlObject($pdo, $sql, array($name));
			if ($row->countid > 0) {
				return array("status" => "ERROR","msg" => "Gruppenname existiert bereits","code" => 1);
			}
			$sql = "INSERT INTO `%groups%` (name,remark,created) VALUES(?,?,?)";
			CommonUtils::execSql($pdo, $sql, array($name,$remark,$currentTime));
			$groupId = $pdo->lastInsertId();
			return array("status" => "OK","groupid" => $groupId);
		} catch (Exception $e) {
			return array("status" => "ERROR","msg" => $e->getMessage(),"code" => 2);
		}
	}
	
	private function changeCustomer($pdo,$id,$name,$email,$addr,$remark,$phone,$mobil,$www,$hello,$regards,$permanent,$groups) {
		date_default_timezone_set(DbUtils::getTimeZone());
		$currentTime = date('Y-m-d H:i:s');

		try {
			$sql = "UPDATE %customers% SET name=?,email=?,address=?, remark=?, phone=?, mobil=?, www=?,hello=?,regards=?,lastmodified=?,permanent=? WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array($name,$email,$addr,$remark,$phone,$mobil,$www,$hello,$regards,$currentTime,$permanent,$id));
			
			$sql = "DELETE FROM %groupcustomer% WHERE customerid=?";
			CommonUtils::execSql($pdo, $sql, array($id));
			if ($groups != '') {
				foreach($groups as $aGroupId) {
					$sql = "INSERT INTO %groupcustomer% (customerid,groupid) VALUES(?,?)";
					CommonUtils::execSql($pdo, $sql, array($id,$aGroupId));
				}
			}
			
			return array("status" => "OK");
		} catch (Exception $e) {
			return array("status" => "ERROR","msg" => $e->getMessage());
		}
	}
	
	private function newVacation($pdo,$cusid,$checkin,$checkout,$room,$remark) {
		try {
			self::addVacationsCore($pdo,$cusid,$checkin,$checkout,$room,$remark);
			return array("status" => "OK","cusid" => $cusid);
		} catch (Exception $e) {
			return array("status" => "ERROR","msg" => $e->getMessage());
		}
	}
	
	private function delVacation($pdo,$id) {
		try {
			$pdo->beginTransaction();
			$sql = "SELECT count(id) as countid FROM %vacations% WHERE id=?";
			$row = CommonUtils::getRowSqlObject($pdo, $sql, array($id));
			if ($row->countid != 1) {
				$pdo->rollBack();
				return array("status" => "ERROR","msg" => "Vacations entry not found");
			} else {
				$sql = "SELECT customerid FROM %vacations% WHERE id=?";
				$row = CommonUtils::getRowSqlObject($pdo, $sql, array($id));
				$cusid = $row->customerid;
			
				$sql = "DELETE FROM %vacations% WHERE id=?";
				CommonUtils::execSql($pdo, $sql, array($id));
				$pdo->commit();
				return array("status" => "OK","cusid" => $cusid);
			}
		} catch (Exception $ex) {
			$pdo->rollBack();
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
	}
	
	public static function payOrUnpay($pdo,$id,$userid,$paymentid, $value,$doTransaction,$remark=null) {
		if (is_null($paymentid)) {
			$paymentid = 8;
		}
		try {
			if ($doTransaction) {
				$pdo->beginTransaction();
			}
			$sql = "SELECT count(id) as countid FROM %bill% WHERE id=?";
			$row = CommonUtils::getRowSqlObject($pdo, $sql, array($id));
			if ($row->countid != 1) {
				if ($doTransaction) {
					$pdo->rollBack();
				}
				return array("status" => "ERROR","msg" => "Bill with id=$id not found");
			} else {
				$sql = "SELECT intguestid FROM %bill% WHERE id=? AND closingid is null";
				$result = CommonUtils::fetchSqlAll($pdo, $sql, array($id));
				if (count($result) == 0) {
					if ($doTransaction) {
						$pdo->rollBack();
					}
					return array("status" => "ERROR","msg" => "Unclosed ill with id=$id not found");
				}
				$cusid = $result[0]["intguestid"];

				$sql = "UPDATE %bill% SET intguestpaid=?,paymentid=? WHERE id=?";
				CommonUtils::execSql($pdo, $sql, array($value,$paymentid,$id));
	
				QueueContent::sendBillToQRK($pdo, $value, $id);
				
				date_default_timezone_set(DbUtils::getTimeZone());
				$currentTime = date('Y-m-d H:i:s');
				$sql = "INSERT INTO %customerlog% (date,action,customerid,userid,billid,remark) VALUES(?,?,?,?,?,?)";
				$action = (is_null($value) ? self::$CANCEL : self::$PAY);
				CommonUtils::execSql($pdo, $sql, array($currentTime,$action,$cusid,$userid,$id,$remark));
				
				if ($doTransaction) {
					$pdo->commit();
				}
				return array("status" => "OK","cusid" => $cusid);
			}
		} catch (Exception $ex) {
			if ($doTransaction) {
				$pdo->rollBack();
			}
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
	}
	
	private static function payallguests($pdo,$userid,$paymentid) {
		$paymentname = self::getPaymentName($pdo,$paymentid);
		
		$pdo->beginTransaction();

		$allGuestWithUnpaidBills = self::getCustomers($pdo, '', '', '', '', true);
		foreach($allGuestWithUnpaidBills["msg"] as $g) {
			$customerid = $g["id"];
			$billsOfGuest = self::getUnpaidBills($pdo, $customerid)["msg"]["bills"];
			foreach ($billsOfGuest as $b) {
				$billid = $b["id"];
				$ret = self::payOrUnpay($pdo, $billid, $userid, $paymentid, 1, false,$paymentname);

				if ($ret["status"] != "OK") {
					$pdo->rollBack();
					return $ret;
				}
			}
		}
		$pdo->commit();
		return array("status" => "OK");
	}
	
	private function pay($pdo,$id,$userid,$paymentid) {
		$paymentname = self::getPaymentName($pdo,$paymentid);
		return (self::payOrUnpay($pdo, $id, $userid, $paymentid, 1,true,$paymentname));
	}
	private function unpay($pdo,$id,$userid,$code,$remark) {
		$stornocode = CommonUtils::getConfigValue($pdo, 'cancelguestcode', null);
		if (is_null($stornocode)) {
			return array("status" => "ERROR","msg" => "Es wurde noch kein Stornocode in der Administration festgelegt");
		}
		if ($stornocode != $code) {
			return array("status" => "ERROR","msg" => "Falscher Stornocode");
		}
		return (self::payOrUnpay($pdo, $id, $userid, null, null,true,$remark));
	}
	
	private function printBill($pdo,$billid) {
		if(session_id() == '') {
			session_start();
		}
		$printer = $_SESSION['receiptprinter'];

		// now get receipt info from bill table
		CommonUtils::log($pdo, "PRINTQUEUE", "Insert bill with id=$billid for printer=$printer into queue for customer.");

		$printInsertSql = "INSERT INTO `%printjobs%` (`id` , `content`,`type`,`printer`) VALUES ( NULL,?,?,?)";
		CommonUtils::execSql($pdo, $printInsertSql, array((string)($billid),'3',$printer));
		return array("status" => "OK");
	}
	
	private function printallbills($pdo,$cusid) {
		$sql = "SELECT id FROM %bill% WHERE intguestid=? AND paymentid=? AND (intguestpaid is null OR intguestpaid='0')";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($cusid,DbUtils::$PAYMENT_GUEST));
		foreach($result as $aBill) {
			$this->printBill($pdo, $aBill["id"]);
		}
		return array("status" => "OK");
	}
	
	private function payallbills($pdo,$cusid,$userid,$paymentid) {
		try {
			$pdo->beginTransaction();
			$paymentname = self::getPaymentName($pdo,$paymentid);
			$sql = "SELECT id FROM %bill% WHERE intguestpaid is null AND intguestid=?";
			$result = CommonUtils::fetchSqlAll($pdo, $sql, array($cusid));
			foreach($result as $aBill) {
				$ok = self::payOrUnpay($pdo, $aBill["id"], $userid, $paymentid, 1,false,$paymentname);
				if ($ok["status"] != "OK") {
					$pdo->rollBack();
					return $ok;
				}
			}
			$pdo->commit();
			return array("status" => "OK","cusid" => $cusid);
		} catch (Exception $ex) {
			$pdo->rollBack();
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
	}
	private function deleteCustomer($pdo,$id) {
		try {
                        $sql = "SELECT count(id) as countid FROM %bill% WHERE intguestid=?";
			$row = CommonUtils::getRowSqlObject($pdo, $sql, array($id));
			if ($row->countid > 0) {
				return array("status" => "ERROR","msg" => "Der Gast hat zugewiesene Rechnungen");
			}
			
			$pdo->beginTransaction();
			$sql = "DELETE FROM %groupcustomer% WHERE customerid=?";
			CommonUtils::execSql($pdo, $sql, array($id));
			
			$sql = "DELETE FROM %vacations% WHERE customerid=?";
			CommonUtils::execSql($pdo, $sql, array($id));
			
			$sql = "DELETE FROM %customers% WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array($id));
			$pdo->commit();
			
			return array("status" => "OK");
		} catch (Exception $e) {
			$pdo->rollBack();
			return array("status" => "ERROR","msg" => $e->getMessage());
		}
	}
	
	private function getCustomersForReserv($pdo,$search) {
		if ($search == '') {
			return array("status" => "OK","msg" => array());
		}
		
		$s = '%' . $search . '%';
		try {
			$allcustomers = array();
			
			$sql = "SELECT id,name,email,address,remark,phone,mobil FROM %customers% WHERE (name like ?) OR (mobil like ?) OR (phone like ?) ORDER BY name";
			$result = CommonUtils::fetchSqlAll($pdo, $sql, array($s,$s,$s));
			
			foreach($result as $aCustomer) {
				$id = $aCustomer["id"];
				$name = $aCustomer["name"];
				$email = $aCustomer["email"];
				$phone = $aCustomer["phone"];
				$mobil = $aCustomer["mobil"];
				
				$finalPhone = "";
				$summary = $aCustomer["name"];
				if ($phone != "") {
					$summary .= " - " . $phone;
					$finalPhone = $phone;
				}
				if ($mobil != "") {
					$summary .= " - " . $mobil;
					$finalPhone = $mobil;
				}
				if ($email != "") {
					$summary .= " - " . $email;
				}
				
				$allcustomers[] = array("summary" => $summary,"id" => $id,"name" => $name,"email" => $email,"phone" => $finalPhone);
			}
			
			return array("status" => "OK","msg" => $allcustomers);
			
		} catch (Exception $ex) {
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
	}
	
	private static function isCustomerPresent($pdo,$cusid,$date) {
		if ($date == '') {
			return true;
		}
		$sql = "SELECT count(id) as countid FROM %vacations% WHERE customerid=? AND (checkin is not null OR checkout is not null) AND (COALESCE(checkin,'$date') <= ? AND COALESCE(checkout,'$date') >= ?)";
		$row = CommonUtils::getRowSqlObject($pdo, $sql, array($cusid,$date,$date));
		if ($row->countid == 0) {
			return false;
		} else {
			return true;
		}
	}
	private static function getCustomers($pdo,$search,$remark,$address,$date,$onlyOpenBills) {
		$s = '%' . $search . '%';
		$r = '%' . $remark . '%';
		$a = '%' . $address . '%';
		try {
			$allcustomers = array();
			
			$sql = "SELECT %customers%.id as id,name,email,address,remark,phone,mobil,www,COALESCE(permanent,0) as permanent,COALESCE(hello,'') as hello,COALESCE(regards,'') as regards,DATE_FORMAT(created,'%e %b %Y - %k:%i') as created,DATE_FORMAT(lastmodified,'%e %b %Y - %k:%i') as lastmodified ";
			$sql .= "FROM %customers% WHERE ((name like ?) OR (email like ?)) AND (remark like ?) AND (address like ?) ORDER BY name";

			$result = CommonUtils::fetchSqlAll($pdo, $sql, array($s,$s,$r,$a));

			foreach ($result as $aCustomer) {
				if (!self::isCustomerPresent($pdo,$aCustomer["id"],$date)) {
					continue;
				}
				
				$sql = "SELECT groupid,name FROM %groupcustomer%,`%groups%` WHERE groupid=`%groups%`.id AND customerid=? ORDER BY name";
				$assgroups = CommonUtils::fetchSqlAll($pdo, $sql, array($aCustomer["id"]));
		
				if ($onlyOpenBills == 1) {
					$sql = "SELECT count(id) as countid FROM %bill% WHERE intguestid=? AND intguestpaid is null AND paymentid=?";
					$row = CommonUtils::getRowSqlObject($pdo, $sql, array($aCustomer["id"],DbUtils::$PAYMENT_GUEST));
					if ($row->countid == 0) {
						continue;
					}
				}
				
				$sql = "SELECT SUM(brutto) as openbillsum FROM %bill% WHERE paymentid=? AND intguestid=? AND intguestpaid is null";
				$row = CommonUtils::getRowSqlObject($pdo, $sql, array(DbUtils::$PAYMENT_GUEST,$aCustomer["id"]));
				
				$aCust = array("id" => $aCustomer["id"],
				    "name" => $aCustomer["name"],
				    "email" => $aCustomer["email"],
				    "address" => $aCustomer["address"],
				    "remark" => $aCustomer["remark"],
				    "phone" => $aCustomer["phone"],
				    "mobil" => $aCustomer["mobil"],
				    "www" => $aCustomer["www"],
                                    "permanent" => $aCustomer["permanent"],
				    "hello" => $aCustomer["hello"],
				    "regards" => $aCustomer["regards"],
				    "created" => $aCustomer["created"],
				    "lastmodified" => $aCustomer["lastmodified"],
				    "groups" => $assgroups,
				    "openbillsum" => $row->openbillsum
				);
				$allcustomers[] = $aCust;	
			}
                        
                        UsedFeatures::noteUsedFeature($pdo, UsedFeatures::$Customers);

			return array("status" => "OK","msg" => $allcustomers);
		} catch (Exception $ex) {
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
	}
	
	private function getVacations($pdo,$cusid) {
		$sql = "SELECT id,COALESCE(checkin,'') as checkin,COALESCE(checkout,'') as checkout,COALESCE(room,'') as room,COALESCE(remark,'') as remark FROM %vacations% WHERE customerid=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($cusid));
		return array("status" => "OK","msg" => array("vacations" => $result,"cusid" => $cusid));
	}
	
	private static function getAllBills($pdo,$cusid) {
		return self::getBills($pdo,$cusid,true);
	}
	private static function getUnpaidBills($pdo,$cusid) {
		return self::getBills($pdo,$cusid,false);
	}
	
	private static function getBills($pdo,$cusid,$includePaidBills) {
		$where = "";
		if (!$includePaidBills) {
			$where = " AND (intguestpaid IS NULL OR intguestpaid='0') ";
		}
                $billuidSql = Bill::getBillUidSqlPart();
		$sql = "SELECT id,$billuidSql as billuid,billdate,brutto,COALESCE(guestinfo,'') as guestinfo,COALESCE(intguestpaid,'0') as paid,'0' as closed FROM %bill% B WHERE paymentid=? AND intguestid=? AND (intguestpaid is null OR intguestpaid='0') $where";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array(DbUtils::$PAYMENT_GUEST,$cusid));
		return array("status" => "OK","msg" => array("bills" => $result,"cusid" => $cusid));
	}
	
	private function getGroups($pdo,$search) {
		$s = '%' . $search . '%';
		try {
			$sql = "SELECT id,name,remark,DATE_FORMAT(created,'%e %b %Y - %k:%i') as created FROM `%groups%` WHERE name like ? ORDER BY name";
			$resultFiltered = CommonUtils::fetchSqlAll($pdo, $sql, array($s));
			
			$resultFoundGroups = array();
			foreach ($resultFiltered as $aFilteredGroup) {
				$groupid = $aFilteredGroup["id"];
				$sql = "SELECT %customers%.name as name from %customers%,%groupcustomer% WHERE %groupcustomer%.groupid=? AND %groupcustomer%.customerid=%customers%.id ORDER by name";
				$customersInGroup = CommonUtils::fetchSqlAll($pdo, $sql, array($groupid));
				$resultFoundGroups[] = array(
				    "id" => $groupid,
				    "name" => $aFilteredGroup["name"],
				    "remark" => $aFilteredGroup["remark"],
				    "created" => $aFilteredGroup["created"],
				    "customers" => $customersInGroup);
			}
			
			$sql = "SELECT id,name FROM `%groups%` ORDER BY name";
			$resultAll = CommonUtils::fetchSqlAll($pdo, $sql);

			$result = array("filtered" => $resultFoundGroups,"all" => $resultAll);
			return array("status" => "OK","msg" => $result);
		} catch (Exception $ex) {
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
	}
	
	private function changeGroup($pdo,$id,$name,$remark) {
		try {
			$sql = "UPDATE `%groups%` SET name=?,remark=? WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array($name,$remark,$id));
			return array("status" => "OK");
		} catch (Exception $e) {
			return array("status" => "ERROR","msg" => $e->getMessage());
		}
	}
	private function deleteGroup($pdo,$id) {
		try {
			$sql = "DELETE FROM %groupcustomer% WHERE groupid=?";
			CommonUtils::execSql($pdo, $sql, array($id));
			
			$sql = "DELETE FROM `%groups%` WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array($id));

			return array("status" => "OK");
		} catch (Exception $e) {
			return array("status" => "ERROR","msg" => $e->getMessage());
		}
	}
	
	private function emailGroup($pdo,$grpid,$subject,$bcc,$text) {
		$sql = "SELECT %customers%.name as name,COALESCE(%customers%.hello,'') as hello,COALESCE(%customers%.regards,'') as regards ,%customers%.email as email from %customers%,%groupcustomer% WHERE %groupcustomer%.groupid=? AND %groupcustomer%.customerid=%customers%.id ORDER by name";
		$customersInGroup = CommonUtils::fetchSqlAll($pdo, $sql, array($grpid));
		
		$emails = array();
		foreach ($customersInGroup as $aCustomer) {
			$email = $aCustomer["email"];
			if (!is_null($email)) {
				$email = trim($email);
				if ($email != '') {
					$emails[] = array("email" => $email,"hello" => $aCustomer["hello"],"regards" => $aCustomer["regards"],"name" => $aCustomer["name"]);
				}
			}
		}
		
		$ok = true;
		foreach($emails as $anEmailEntry) {
			$anEmail = $anEmailEntry["email"];
			
			$name = $anEmailEntry["name"];
			$hello = $anEmailEntry["hello"];
			$regards = $anEmailEntry["regards"];
			$textToSend = str_replace("{NAME}",$name,$text);
			$textToSend = str_replace("{ANREDE}",$hello,$textToSend);
			$textToSend = str_replace("{GRUSS}",$regards,$textToSend);
			if (!Emailer::sendEmail($pdo, $textToSend, $anEmail, $subject,$bcc)) {
				$ok = false;
			}
		}
		
		if ($ok) {
			return array("status" => "OK");
		} else {
			return array("status" => "ERROR","msg" => 'Not all emails could be sent');
		}
	}
	
	public static function exportLogOfOneClosing($pdo,$closingid) {
		self::exportLog($pdo,null,null,$closingid);
	}
	public static function exportLog($pdo,$startDate = null, $endDate = null,$closingid=null) {
		header("Content-type: text/x-csv");
		header("Content-Disposition: attachment; filename=Gastbezahlungen.csv");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Pragma: no-cache");
		header("Expires: 0");
		
		echo("Datum; Gast-ID; Gastname; Bon-ID; Aktion; Benutzer-ID; Benutzername; Stornobemerkung oder Zahlungsweg; Tageserfassung-ID\n");
		
		$sql = "SELECT %customerlog%.date as date,CASE WHEN action=0 THEN 'Bezahlung storniert' WHEN action=1 THEN 'bezahlt' ELSE 'undefiniert' END as action,%customerlog%.clsid as clsid, ";
		$sql .= " %customerlog%.billid as billid,%customerlog%.remark as remark, userid, username, customerid, %user%.username as username, %customers%.name as customername  ";
		$sql .= " FROM %customerlog%,%user%,%customers% ";
		$sql .= " WHERE (userid=%user%.id AND customerid=%customers%.id) ";

		$where = '';
		
		if (is_null($closingid)) {
			if (is_null($startDate) && (!is_null($endDate))) {
				$where = " AND (date <= '$endDate') ";
			} else if (!is_null($startDate) && (!is_null($endDate))) {
				$where = " AND (date <= '$endDate') AND (date >= '$startDate') ";
			}
		} else {
			$where = " AND clsid=?";
		}
		$sql .= $where;
		$sql .= " ORDER BY %customerlog%.date";
		
		if (is_null($closingid)) {
			$result = CommonUtils::fetchSqlAll($pdo, $sql, null);
		} else {
			$result = CommonUtils::fetchSqlAll($pdo, $sql, array($closingid));
		}
		
		foreach($result as $aLog) {
			$clsidTxt = "";
			if (intval($aLog["clsid"]) != 0) {
				$clsidTxt = $aLog["clsid"];
			}
			echo $aLog["date"] . ";";
			echo $aLog["customerid"] . ";";
			echo self::quoteForCsv($aLog["customername"]) . ";";
			echo $aLog["billid"] . ";";
			echo $aLog["action"] . ";";
			echo $aLog["userid"] . ";";
			echo self::quoteForCsv($aLog["username"]) . ";";
			echo self::quoteForCsv($aLog["remark"]) . ";";
			echo $clsidTxt . ";";
			echo "\r\n";
		}
	}
	
	public static function quoteForCsv($txt) {
		$txt = str_replace("\"","\"\"",$txt);
		return '"' . $txt . '"';
	}
	
	
	private function getPaymentsForGuest($pdo) {
		if(session_id() == '') {
			session_start();
		}

		$where = " WHERE (id <= 3)";
		
		$lang = $_SESSION['language'];
		$sql = "SELECT id,name FROM %payment% $where";
		if ($lang == 1) {
			$sql = "SELECT id,name_en as name FROM %payment% $where";
		} else if ($lang == 2) {
			$sql = "SELECT id,name_esp as name FROM %payment% $where";
		}
		
		$result = CommonUtils::fetchSqlAll($pdo, $sql, null);
		
		return array("status" => "OK","msg" => $result);
	}
	
	private static function getPaymentName ($pdo,$paymentid) {
		if(session_id() == '') {
			session_start();
		}

		$lang = $_SESSION['language'];
		$sql = "SELECT id,name FROM %payment% WHERE id=?";
		if ($lang == 1) {
			$sql = "SELECT id,name_en as name FROM %payment% WHERE id=?";
		} else if ($lang == 2) {
			$sql = "SELECT id,name_esp as name FROM %payment% WHERE id=?";
		}
		
		$row = CommonUtils::getRowSqlObject($pdo, $sql, array($paymentid));
		return $row->name;
	}
	
	private static function getUnpaidSumOfCustomer($pdo,$cusid,$decpoint) {
		$sql = "SELECT sum(brutto) AS sumbrutto FROM %bill% WHERE intguestid=? AND (intguestpaid IS NULL OR intguestpaid='0') AND paymentid=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($cusid, DbUtils::$PAYMENT_GUEST));
		if (count($result) == 0) {
			return "0.00";
		} else {
			return number_format($result[0]["sumbrutto"], 2, $decpoint, '');
		}
	}
	
	private static function getReportCoreOfOneCustomer($pdo,$customerid,$lang) {
		$decpoint = CommonUtils::getConfigValue($pdo,"decpoint",",");
		$currency = CommonUtils::getConfigValue($pdo,"currency","Euro");
		
		$sql = "SELECT name FROM %customers% WHERE id=?";
		$res = CommonUtils::fetchSqlAll($pdo, $sql, array($customerid));
		if (count($res) != 1) {
			return "";
		}
		$customername = $res[0]["name"];

		$billsOfGuest = self::getUnpaidBills($pdo, $customerid)["msg"]["bills"];
		$billcount = count($billsOfGuest) + 2;

		$txt = "<tr>";
		$txt .= "<td rowspan='$billcount' class='name'>$customername";

		$txt .= "<td class='header'>" . self::$CUS_BILL_ID[$lang] . "<td class='header'>" . self::$CUS_DATE[$lang] . "<td class='header'>" . self::$CUS_BILL_SUM[$lang] . " ($currency)</tr>";

		foreach($billsOfGuest as $aBill) {
			$txt .= "<tr><td>" . $aBill["id"] . "<td>" . $aBill["billdate"];
			$txt .= "<td>" . number_format( $aBill["brutto"], 2, $decpoint, '') . "</tr>";
		}
		$guestsum = self::getUnpaidSumOfCustomer($pdo, $customerid, $decpoint);
		$txt .= "<tr><td colspan=2 class='sum sumheader'>" . self::$CUS_SUM[$lang] . "<td class='sum sumvalue'>$guestsum</tr>";
		return $txt;
	}
	private static function reportcustomerbills($pdo) {
		$customerid = $_GET["cusid"];
		if(session_id() == '') {
			session_start();
		}
		$lang = $_SESSION['language'];
		
		$txt = "<html>" . self::headerOfHtmlPage($lang) . "<body>";
		$txt .= self::getGuestReportTitlePart($lang);
		$txt .= "<p><table class='guestreport'>";
		
		$txt .= self::getReportCoreOfOneCustomer($pdo, $customerid, $lang);
		
		$txt .= "</table>";
		$txt .= self::getFooter($pdo, $lang);
		$txt .= "</body></html>";
		echo $txt;
	}
	private static function reportbills($pdo) {
		if(session_id() == '') {
			session_start();
		}
		$lang = $_SESSION['language'];
		
		$allGuestWithUnpaidBills = self::getCustomers($pdo, '', '', '', '', true);
		
		if ($allGuestWithUnpaidBills["status"] != "OK") {
			echo "Error: " . $allGuestWithUnpaidBills["msg"];
			return;
		}
		
		$txt = "<html>" . self::headerOfHtmlPage($lang) . "<body>";
		$txt .= self::getGuestReportTitlePart($lang);
		$txt .= "<p><table class='guestreport'>";
		
		if (count($allGuestWithUnpaidBills["msg"]) === 0) {
			$txt .= "<tr><td class='center'>" . self::$CUS_NO_BILLS[$lang] . "</tr>";
		} else {
			foreach($allGuestWithUnpaidBills["msg"] as $aGuest) {
				$customerid = $aGuest["id"];
				$txt .= self::getReportCoreOfOneCustomer($pdo, $customerid, $lang);
			}
		}
		$txt .= "</table>";
		$txt .= self::getFooter($pdo, $lang);
		$txt .= "</body></html>";
		echo $txt;
	}
	
        private static function exportguests($pdo) {
                $allowguestexport = CommonUtils::getConfigValue($pdo, 'allowguestexport', 0);
                if ($allowguestexport == 0) {
                        echo "Keine Berechtigung - no permission";
                        return;
                }
                
                $sql = "DESCRIBE `%customers%`";
                $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
                $stmt->execute();
                $fields = $stmt->fetchAll(PDO::FETCH_COLUMN);


                $fieldstr = implode(",",$fields);
                $sql = "SELECT $fieldstr from `%customers%`";

                $result = CommonUtils::fetchSqlAll($pdo, $sql);
                $tableContent = array();
                foreach($result as $row) {
                        $fieldContent = array();
                        foreach($fields as $field) {
                                $aFieldEntry = $row[$field];
                                $fieldContent[] = $aFieldEntry;
                        }
                        $tableContent[] = $fieldContent;
                }
                $tableStructureAndContent = array("fields" => $fields,"content" => $tableContent);
                $jsonStructure = json_encode($tableStructureAndContent);
                
                ob_start();
                header("Pragma: public");
                header("Expires: 0");
                header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                header("Cache-Control: public");
                header("Content-Description: File Transfer");
                header("Content-type: application/octet-stream");
                header("Content-Disposition: attachment; filename=\"guestexport.json\"");
                header("Content-Transfer-Encoding: binary");
                header("Content-Length: ". strlen($jsonStructure));

                echo $jsonStructure;
                ob_end_flush();
        }
        
	private static function headerOfHtmlPage($lang) {
		$txt = "<head>";
		$txt .= "<title>" . self::$CUS_OVERVIEW[$lang] . "</title>";
		$txt .= '<meta http-equiv="content-type" content="text/html; charset=utf-8">';
		$txt .= '<link rel="stylesheet" type="text/css" href="../css/guestreport.css?v=2.9.12">';
		$txt .= "</head>";
		return $txt;
	}
	
	private static function getGuestReportTitlePart($lang) {
		date_default_timezone_set(DbUtils::getTimeZone());
		$currentTime = date('Y-m-d H:i');
		
		$txt = "<div class='title'>"; 
		$txt .= "<h1>" . self::$CUS_OVERVIEW[$lang] . "</h1>";
		$txt .= "<p>" . self::$CUS_CREATED[$lang] . ": " . $currentTime;
		$txt .= "</div>";
		
		return $txt;
	}
	private static function getFooter($pdo,$lang) {
		$version = CommonUtils::getConfigValue($pdo,"version","");
		$txt = "<div class='footer'>"; 
		$txt .= "<p>OrderSprinter $version";
		$txt .= "</div>";
		return $txt;
	}
	
	private static function assigncustomerstogroup($pdo,$customers,$groupid) {
		try {
			foreach($customers as $aCusId) {
				$sql = "SELECT count(id) as countid FROM %groupcustomer% WHERE customerid=? and groupid=?";
				$res = CommonUtils::fetchSqlAll($pdo, $sql, array($aCusId,$groupid));
				if ($res[0]["countid"] == 0) {
					$sql = "INSERT INTO %groupcustomer% (customerid,groupid) VALUES(?,?)";
					CommonUtils::execSql($pdo, $sql, array($aCusId,$groupid));
				}
			}
			return array("status" => "OK");
		} catch (Exception $ex) {
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
	}
	
	private static function removefromgroup($pdo,$customers,$groupid) {
		try {
			foreach($customers as $aCusId) {
				$sql = "DELETE FROM %groupcustomer% WHERE customerid=? AND groupid=?";
				CommonUtils::execSql($pdo, $sql, array($aCusId,$groupid));
			}
			return array("status" => "OK");
		} catch (Exception $ex) {
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
	}
	
	private static function assigncustomerstonewgroup($pdo,$customers,$groupname) {
		$result = self::createNewGroup($pdo,$groupname,'');
		if ($result["status"] != "OK") {
			return $result;
		} else {
			$groupid = $result["groupid"];
			return self::assigncustomerstogroup($pdo, $customers, $groupid);
		}
	}
}
