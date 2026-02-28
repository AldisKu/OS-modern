<?php

class Demodata {
	public static function insertdemodata() {
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			date_default_timezone_set(DbUtils::getTimeZone());
			$queue = new QueueContent();
			$today = date('Y-m-d');
			$yesterdayDT = new DateTime('yesterday');
			$yesterday = $yesterdayDT->format('Y-m-d');
			$previousmonth = date("Y-m", strtotime("first day of previous month")) . "-01";
			$secondDayOfPreviosMonth = date("Y-m", strtotime("first day of previous month")) . "-02";
			$thirdDayOfPreviosMonth = date("Y-m", strtotime("first day of previous month")) . "-03";

			// reateNewCustomer($pdo,$name,$email,$addr,$remark,$phone,$mobil,$www,$hello,$regards,$checkin,$checkout,$room)
			$customersModule = new Customers();
			$customersModule->createNewCustomer($pdo, "Max Mustermann", "max@nospam.de", '', '', '', '', '', 'Herr', '', $previousmonth, null, "1",0);
			$customersModule->createNewCustomer($pdo, "Silke Musterfrau", "silke@nospam.de", '', '', '', '', '', 'Frau', '', $previousmonth, null, "2",0);
			$customersModule->createNewCustomer($pdo, "Donald Duck", "donald@nospam.de", '', '', '', '', '', 'Herr', '', $yesterday, null, "3",0);
			$customersModule->createNewCustomer($pdo, "Harry Potter", "harry@nospam.de", '', '', '', '', '', 'Herr', '', $yesterday, null, "4",0);
			$customersModule->createNewCustomer($pdo, "Jack Smith", "jack@nospam.de", '', '', '', '', '', 'Mr', '', $previousmonth, $yesterday, "5",0);
			
			$sql = "SELECT id,priceA as price FROM %products% WHERE available='1' AND ((unit is NULL) OR unit='0') AND removed is null ORDER BY id LIMIT 40";
			$prods = CommonUtils::fetchSqlAll($pdo, $sql);
			if (count($prods) == 0) {
				echo json_encode(array("status" => "ERROR","msg" => "Keine bestellbaren Produkte vorhanden"));
				return;
			}
			
			$sql = "SELECT id from %resttables% WHERE active='1' AND removed is null ORDER BY roomid,sorting LIMIT 18";
			$tablesCore = CommonUtils::fetchSqlAll($pdo, $sql);
			if (count($tablesCore) == 0) {
				echo json_encode(array("status" => "ERROR","msg" => "Keine aktiven Tische angelegt"));
				return;
			}
			
			$sql = "SELECT id FROM %user% WHERE active=1 ORDER BY id";
			$users = CommonUtils::fetchSqlAll($pdo, $sql);
			if (count($users) == 0) {
				echo json_encode(array("status" => "ERROR","msg" => "Keine aktiven Benutzer angelegt"));
				return;
			}
			
			$tables = self::createDemoForADate($pdo, $previousmonth, $users, $prods, $tablesCore,$queue);
			
			$tables = self::createSomeBills($pdo, $tables, $users, $secondDayOfPreviosMonth);
			
			// TODO: cash insert!
			Bill::doCashActionCore($pdo,123.45, "Bareinlage 1", $previousmonth . " 18:31:00", $users[0]["id"],1,DbUtils::$PAYMENT_BAR,DENY_TRANSACTIONS,false);
			if (count($users) > 1) {
				Bill::doCashActionCore($pdo,234.56, "Bareinlage 2", $previousmonth . " 18:42:00", $users[1]["id"],1,DbUtils::$PAYMENT_BAR,DENY_TRANSACTIONS,false);
			}
			
			$billModule = new Bill();
			$ok = $billModule->cancelBill($pdo, $tables[0]["billid"], "", "Demo-Storno", false, false, false, 0, $thirdDayOfPreviosMonth . " 22:10:00",1);
			
			$queueids = $tables[0]["queueids"];
			if (count($queueids) > 4) {
				$queueidsToBill = array($tables[0]["queueids"][0],$tables[0]["queueids"][1],$tables[0]["queueids"][2]);
				self::createBillOfTable($pdo, $queue, implode(',', $queueidsToBill), $tables[0]["id"], 1, $users[0]["id"], $secondDayOfPreviosMonth . " 22:10:00");
			}
			if (count($tables) > 2) {
				$ok = $billModule->cancelBill($pdo, $tables[2]["billid"], "", "Demo-Storno", false, false, false, 1, $thirdDayOfPreviosMonth . " 22:12:20",1);
			}
			
			$queueidsOfTogo = self::createDemoForADateAndTogo($pdo,$secondDayOfPreviosMonth . " 22:10:00",$users,$prods,$queue);
			$guestbillid = self::createBillOfTable($pdo, new QueueContent(), implode(',', $queueidsOfTogo), 0, 1, $users[0]["id"], $secondDayOfPreviosMonth . " 22:10:05");
			$customers = new Customers();
			
			$sql = "SELECT id,name FROM %customers% ORDER BY id LIMIT 4";
			$cusres = CommonUtils::fetchSqlAll($pdo, $sql);
			$cust = $cusres[0];
			$cust2 = $cusres[1];
			
			$queueidsOfGuest = implode(',',self::createDemoForGuest($pdo, $secondDayOfPreviosMonth . " 22:13:00",$users,$prods,1,$queue));
			if (!is_null($queueidsOfGuest)) {
				$billToIgnore = $queue->declarePaidCreateBillReturnBillId($pdo,$queueidsOfGuest,$tables[0]["id"],8,1,0,QueueContent::$INTERNAL_CALL_YES,'',$cust["name"],$cust["id"],$users[0]["id"],$secondDayOfPreviosMonth . " 22:14:00",null,0);
			}
			$queueidsOfGuest = implode(',',self::createDemoForGuest($pdo, $secondDayOfPreviosMonth . " 22:14:00",$users,$prods,2,$queue));
			if (!is_null($queueidsOfGuest)) {
				$billid = $queue->declarePaidCreateBillReturnBillId($pdo,$queueidsOfGuest,$tables[0]["id"],8,1,0,QueueContent::$INTERNAL_CALL_YES,'',$cust2["name"],$cust2["id"],$users[0]["id"],$secondDayOfPreviosMonth . " 22:15:00",null,0);
				$customers->payOrUnpay($pdo, $billid, $users[0]["id"], 1, 1, false, "Demo Gast-Bezahlung");
			}
			$dateOfClosing = $thirdDayOfPreviosMonth . " 23:55:00";
			$sql = "SELECT closingdate FROM %closing% ORDER BY closingdate DESC LIMIT 1";
			$result = CommonUtils::fetchSqlAll($pdo, $sql);
			if (count($result) > 0) {
				$dateOfClosing = $result[0]["closingdate"];
			}
			
			$closing = new Closing();
			$result = $closing->createClosingCore($pdo, "Tageserfassung letzten Monat", 0, $dateOfClosing, false,0,0,0,0.0);
			
			$sql = "SELECT closingdate FROM %closing% WHERE DATE(closingdate) >= DATE(NOW() - INTERVAL 2 DAY)";
			$result = CommonUtils::fetchSqlAll($pdo, $sql);
			if (count($result) == 0) {

				$tables = self::createDemoForADate($pdo, $yesterday, $users, $prods, $tablesCore,$queue);
				$tables = self::createSomeBills($pdo, $tables, $users, $yesterday);

				// TODO: cash insert!
				Bill::doCashActionCore($pdo,200.45, "Bareinlage 1", $today . " 18:30:00", $users[0]["id"],1,DbUtils::$PAYMENT_BAR,DENY_TRANSACTIONS,false);
				if (count($users) > 1) {
					Bill::doCashActionCore($pdo,400, "Bareinlage 2", $today . " 18:35:00", $users[1]["id"],1,DbUtils::$PAYMENT_BAR,DENY_TRANSACTIONS,false);
				}
				$result = $closing->createClosingCore($pdo, "Tageserfassung diesen Monat", 0, $yesterday . " 22:00:00", false,0,0,0,0.0);
				
				$tables = self::createDemoForADate($pdo, $today, $users, $prods, $tablesCore,$queue);
				$tables = self::createSomeBills($pdo, $tables, $users, $today);
			}
			$queueidsOfGuest = implode(',',self::createDemoForGuest($pdo, $today . " 22:13:00",$users,$prods,2,$queue));
			if (!is_null($queueidsOfGuest)) {
				$queue->declarePaidCreateBillReturnBillId($pdo,$queueidsOfGuest,$tables[0]["id"],8,1,0,QueueContent::$INTERNAL_CALL_YES,'',$cust["name"],$cust["id"],$users[0]["id"],$today . " 22:14:00",null,0);
			}
			
			echo json_encode(array("status" => "OK"));
		} catch (Exception $ex) {
			echo json_encode(array("status" => "ERROR","msg" => $ex->getMessage()));
		}
	}
	
	private static function createDemoForGuest($pdo,$dateTime,$users,$prods,$prodStartIndex,$queue) {
		if (count($prods) > (intval($prodStartIndex) + 4)) {
			$prodArr = array(array(
			    "changedPrice" => "NO",
			    "extras" => "",
			    "option" => "",
			    "price" => $prods[$prodStartIndex]["price"],
			    "prodid" => $prods[$prodStartIndex]["id"],
			    "togo" => 0,
			    "unit" => 0,
			    "unitamount" => 1));
		
			$prodArr[] = array(
			    "changedPrice" => "NO",
			    "extras" => "",
			    "option" => "",
			    "price" => $prods[$prodStartIndex + 3]["price"],
			    "prodid" => $prods[$prodStartIndex + 3]["id"],
			    "togo" => 0,
			    "unit" => 0,
			    "unitamount" => 1);
		
			$userid = $users[0]["id"];
			$ret = $queue->addProductListToQueueCore($pdo, $dateTime, null, $prodArr, 0, 's', $userid,0);
			return $ret['queueids'];
		} else {
			$ret = null;
		}
	}
	private static function createDemoForADateAndTogo($pdo,$dateTime,$users,$prods,$queue) {
		$prodArr = array(array(
		    "changedPrice" => "NO",
		    "extras" => "",
		    "option" => "",
		    "price" => $prods[0]["price"],
		    "prodid" => $prods[0]["id"],
		    "togo" => 0,
		    "unit" => 0,
		    "unitamount" => 1));
		if (count($prods) > 1) {
			$prodArr[] = array(
			    "changedPrice" => "NO",
			    "extras" => "",
			    "option" => "",
			    "price" => $prods[1]["price"],
			    "prodid" => $prods[2]["id"],
			    "togo" => 0,
			    "unit" => 0,
			    "unitamount" => 1);
		}
		$userid = $users[0]["id"];
		$ret = $queue->addProductListToQueueCore($pdo, $dateTime, null, $prodArr, 0, 's', $userid,0);
		return $ret['queueids'];
	}
	private static function createDemoForADate($pdo,$dateStr,$users,$prods,$tables,$queue) {
		$prodindex = 0;
		$userindex = 0;
		for ($hour = 0; $hour < 18; $hour++, $hour++) {
			$tableindex = 0;
			while ($tableindex < count($tables)) {
				$tableid = $tables[$tableindex]["id"];
				$prod = $prods[$prodindex];

				$prodElement = array(
				    "changedPrice" => "NO",
				    "extras" => "",
				    "option" => "",
				    "price" => $prod["price"],
				    "prodid" => $prod["id"],
				    "togo" => 0,
				    "unit" => 0,
				    "unitamount" => 1);
				$prodArr = array($prodElement);
				$userid = $users[$userindex]["id"];
				$time = $dateStr . " " . self::leadingzeronumber($hour) . ":05:20";

				$ret = $queue->addProductListToQueueCore($pdo, $time, $tableid, $prodArr, 0, 's', $userid,0);
				if ($ret["status"] != "OK") {
					echo json_encode($ret);
					return;
				}
				if (!isset($tables[$tableindex]["queueids"])) {
					$tables[$tableindex]["queueids"] = [];
				}
				$tables[$tableindex]["queueids"] = array_merge($tables[$tableindex]["queueids"], $ret['queueids']);
				$prodindex = ($prodindex + 1) % count($prods);
				$userindex = ($userindex + 1) % count($users);
				$tableindex += 2;
			}
		}
		return $tables;
	}

	private static function createSomeBills($pdo,$tables,$users,$date) {
		$queue = new QueueContent;
		$tables[0]["billid"] = self::createBillOfTable($pdo, $queue, implode(',', $tables[0]["queueids"]), $tables[0]["id"], 1, $users[0 % count($users)]["id"], $date . " 19:14:15");

		if ((count($tables) >= 3) && (isset($tables[2]["queueids"]))) {
			$tables[2]["billid"] = self::createBillOfTable($pdo, $queue, implode(',', $tables[2]["queueids"]), $tables[2]["id"], 2, $users[1 % count($users)]["id"], $date . " 19:14:25");
		}
		if ((count($tables) >= 5) && (isset($tables[4]["queueids"]))) {
			$tables[4]["billid"] = self::createBillOfTable($pdo, $queue, implode(',', $tables[4]["queueids"]), $tables[4]["id"], 3, $users[2 % count($users)]["id"], $date . " 19:15:15");
		}
		if ((count($tables) >= 7) && (isset($tables[6]["queueids"]))) {
			$tables[6]["billid"] = self::createBillOfTable($pdo, $queue, implode(',', $tables[6]["queueids"]), $tables[6]["id"], 1, $users[3 % count($users)]["id"], $date . " 20:15:15");
		}
		if ((count($tables) >= 8) && (isset($tables[7]["queueids"]))) {
			$tables[7]["billid"] = self::createBillOfTable($pdo, $queue, implode(',', $tables[7]["queueids"]), $tables[7]["id"], 1, $users[4 % count($users)]["id"], $date . " 20:15:15");
		}
		if ((count($tables) >= 10) && (isset($tables[9]["queueids"]))) {
			$tables[9]["billid"] = self::createBillOfTable($pdo, $queue, implode(',', $tables[9]["queueids"]), $tables[9]["id"], 1, $users[5 % count($users)]["id"], $date . " 21:10:00");
		}

		return $tables;
	}

	private static function createBillOfTable($pdo,$queue,$tablequeueids,$tableid,$paymentid,$userid,$datetime) {
		$billid = $queue->declarePaidCreateBillReturnBillId($pdo,$tablequeueids,$tableid,$paymentid,1,0,QueueContent::$INTERNAL_CALL_YES,'','','',$userid,$datetime,null,0);
		return $billid;
	}
	
	private static function leadingzeronumber($number) {
		$number = intval($number);
		if ($number < 10) {
			$number = "0" . $number;
		}
		return $number;
	}
	
	public static function handleCommand($command) {
		
		if ($command == 'insertdemodata') {
			if (isset($_GET['remoteaccesscode']) && !ISDEMO) {
		
				$pdo = DButils::openDbAndReturnPdoStatic();
				$code = CommonUtils::getConfigValue($pdo, 'remoteaccesscode', null);
				if (is_null($code) || ($code !== md5($_GET['remoteaccesscode']))) {
					// is not ok
					echo json_encode(array("status" => "ERROR","msg" => "Fernzugriffscode nicht gesetzt oder falsch", "code" => "FORBIDDEN"));
					return false;
				}
			} else if (!self::checkRights($command)) {
				return false;
			}
			if (ISDEMO) {
					echo json_encode(array("status" => "ERROR","msg" => "Vorgang im Demo-System nicht erlaubt", "code" => "FORBIDDEN"));
			} else {
				
				if (isset($_GET['userid'])) {
					if (session_id() == '') {
						session_start();
					}
					$_SESSION['userid'] = $_GET['userid'];
				}
				
				self::insertdemodata();
			}
		}
	}

	private static function checkRights($command) {
		if (session_id() == '') {
			session_start();
		}
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_NOT_AUTHOTRIZED, "msg" => ERROR_NOT_AUTHOTRIZED_MSG));
			return false;
		}
		
		if ($command == 'insertdemodata') {
			if ($_SESSION['is_admin'] == false) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_COMMAND_NOT_ADMIN, "msg" => ERROR_COMMAND_NOT_ADMIN_MSG));
				return false;
			} else {
				return true;
			}
		}
		return false;
	}

}
