<?php

class OrdersManagement {
        private static $NEW = 0;
        private static $DONE = 1;
        public static $ORDER_OF_WORKRECEIPT = 2;
        public static function handleCommand($cmd) {
                if (!self::hasCurrentUserDeliveryRights()) {
			echo json_encode(array("status" => "ERROR","msg" => "Fehlendes Benutzerrecht Lieferaufträge"));
			return;
		}
                switch ($cmd) {
                        case "getorders":
                                $pdo = DbUtils::openDbAndReturnPdoStatic();
                                $orders = self::getOrders($pdo);
                                echo json_encode($orders);
                                break;
                        case "declaredone":
                                $pdo = DbUtils::openDbAndReturnPdoStatic();
                                $orders = self::declaredone($pdo,$_GET["orderid"]);
                                echo json_encode($orders);
                                break;
                        case "declarenew":
                                $pdo = DbUtils::openDbAndReturnPdoStatic();
                                $orders = self::declarenew($pdo,$_GET["orderid"]);
                                echo json_encode($orders);
                                break;
                        default:
                                echo json_encode(array("status" => "ERROR","msg" => "Kommando nicht erkannt"));
                }
		
        }
        
        private static function hasCurrentUserDeliveryRights() {
		if (session_id() == '') {
			session_start();
		}
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			return false;
		} else {
			return ($_SESSION['right_delivery']);
		}
	}

        private static function transformFieldsToCoalesceSql($tablelabel,$fieldarr) {
                $res = array();
                foreach ($fieldarr as $f) {
                        $res[] = "COALESCE($tablelabel.$f,'') AS $f";
                }
                return implode(',',$res);
        }
        
        private static function getOrders($pdo) {
                $newOrders = self::getOrdersOfType($pdo,self::$NEW);
                $doneOrders = self::getOrdersOfType($pdo,self::$DONE);
                
                if ($newOrders["status"] != "OK") {
                        return $newOrders;
                }
                if ($doneOrders["status"] != "OK") {
                        return $doneOrders;
                }
                return array("status" => "OK","msg" => array("neworders" => $newOrders["msg"],"doneorders" => $doneOrders["msg"]));
        }
        
        private static function getOrdersOfType($pdo,$status) {
                $whereStatus = "status is null OR status=?";
                if ($status == self::$DONE) {
                        $whereStatus = "status = ?";
                }
                try {
                        $orders = array();
                        $fields = self::transformFieldsToCoalesceSql('O',array('name','street','housenumber','postalcode','city','phone','remark','email','sendemail'));
                        $sql = "SELECT O.id,O.creationdate,U.username,$fields from %orders% O,%user% U WHERE O.creatorid=U.id AND ($whereStatus) ORDER BY creationdate DESC,U.username";
                        $allorders = CommonUtils::fetchSqlAll($pdo, $sql, array($status));
                        foreach($allorders as $anOrder) {
                                $orderid = $anOrder["id"];
                                $sql = "SELECT id,ordertime FROM %queue% Q WHERE orderid=? ORDER BY ordertime";
                                $items = self::getItemsOfOrder($pdo, $orderid);
                                $orders[] = array("orderinfo" => $anOrder,"items" => $items);
                        }
                        return array("status" => "OK","msg" => $orders);
                } catch(Exception $ex) {
                        return array("status" => "ERROR","msg" => $ex->getMessage());
                }
        }
        
        private static function getItemsOfOrder($pdo,$orderid) {
                $unit = CommonUtils::caseOfSqlUnitSelection($pdo);
                $hashOfProductWithExtras = "GROUP_CONCAT(CONCAT(Q.unit,'-',Q.unitamount,'-',productid,'-',amount,'-',extraid)) ";
                $hashOfProductWithoutExtras = "CONCAT(Q.unit,'-',Q.unitamount,'-',productid,'-','x') ";
                $sql = "SELECT * FROM (
                                SELECT queueid,productid,Q.unit,Q.unitamount,CONCAT($unit,PN.name) as productname,$hashOfProductWithExtras as extrahash,GROUP_CONCAT(CONCAT(PN.name,'-',amount,'-',QE.name)) as extranamehash FROM %queueextras% QE, %queue% Q,%prodnames% PN WHERE Q.prodnameid=PN.id AND Q.id=QE.queueid AND Q.orderid=? GROUP BY queueid,amount 
                                UNION ALL
                                SELECT Q.id as queueid,Q.unit,Q.unitamount,productid,CONCAT($unit,PN.name) as productname,$hashOfProductWithoutExtras as extrahash,CONCAT(PN.name,'-','No extra') as extranamehash FROM %queue% Q,%prodnames% PN WHERE Q.prodnameid=PN.id AND Q.orderid=? AND Q.id not in (SELECT queueid FROM %queueextras% QE)
                        ) a ORDER BY a.queueid,productid";
                
                $items = CommonUtils::fetchSqlAll($pdo, $sql, array($orderid,$orderid));
                
                
                


                
                $prodarr = array();
                foreach ($items as $anItem) {
                        $hash = $anItem['extrahash'];
                        $existingKeys = array_keys($prodarr);
                        if (!in_array($hash, $existingKeys)) {
                                $prodarr[$hash] = array("amount" => 1, "item" => $anItem);
                        } else {
                                $amount = intval($prodarr[$hash]["amount"]);
                                $prodarr[$hash] = array("amount" => ($amount+1), "item" => $anItem);
                        }
                }
                
                $finalitemlist = array();
                $hashes = array_keys($prodarr);
                foreach ($hashes as $aHash) {
                        $itemEntry = $prodarr[$aHash];
                        $queueid = $itemEntry["item"]["queueid"];
                        $prodname = $itemEntry["item"]["productname"];
                        $prodamount = $itemEntry["amount"];
                        $sql = "SELECT amount,extraid,name from %queueextras% where queueid=? ORDER BY amount,extraid";
                        $extrasarr = CommonUtils::fetchSqlAll($pdo, $sql, array($queueid));
                        $finalitemlist[] = array("amount" => $prodamount,"prodname" => $prodname, "extras" => $extrasarr);
                }
                return $finalitemlist;
        }
        
        private static function declaredone($pdo,$orderid) {
                UsedFeatures::noteUsedFeature($pdo, UsedFeatures::$Orders);
                $sql = "UPDATE %orders% SET status=? WHERE id=?";
                CommonUtils::execSql($pdo, $sql, array(self::$DONE,$orderid));
                self::emailToOrderer($pdo, $orderid);
                return self::getOrders($pdo);
        }
        
        private static function emailToOrderer($pdo,$orderid) {
                $companyinfo = CommonUtils::getConfigValue($pdo, "companyinfo", "Ihr Gastgeber");
                $sql = "SELECT email,sendemail,creationdate,COALESCE(name,'') as name,DATE(creationdate) as thedate,TIME(creationdate) as thetime FROM %orders% WHERE id=?";
                $result = CommonUtils::fetchSqlAll($pdo, $sql, array($orderid));
                $sendEmail = $result[0]["sendemail"];
                if ($sendEmail == 0) {
                        return;
                }
                $receiver = $result[0]["email"];
                if (is_null($receiver) || ($receiver == "")) {
                        return;
                }
                
                $msg = CommonUtils::getConfigValue($pdo, "orderemailtext", "Lieferauftrag Update");
                $msg = str_replace("{NAME}", $result[0]["name"], $msg);
                $msg = str_replace("{DATUM}", $result[0]["thedate"], $msg);
                $msg = str_replace("{UHRZEIT}", $result[0]["thetime"], $msg);
                $msg = str_replace("{BETRIEBSINFO}", $companyinfo, $msg);
		$msg = str_replace("\n", "\r\n", $msg);

		$topictxt = "Lieferauftrag\r\n";
		
                try {
                        if (Emailer::sendEmail($pdo, $msg, $receiver, $topictxt)) {
                                return true;
                        } else {
                                return false;
                        }
                } catch (Exception $ex) {
                        error_log("Email for order not possible to send. " . $ex->getMessage());
                }
	}
        private static function declarenew($pdo,$orderid) {
                $sql = "UPDATE %orders% SET status=? WHERE id=?";
                CommonUtils::execSql($pdo, $sql, array(self::$NEW,$orderid));
                return self::getOrders($pdo);
        }
}