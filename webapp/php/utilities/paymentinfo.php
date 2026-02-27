<?php

/*
 * Click nbfs://nbhost/SystemFileSystem/Templates/Licenses/license-default.txt to change this license
 * Click nbfs://nbhost/SystemFileSystem/Templates/Scripting/PHPClass.php to edit this template
 */

/**
 * Description of paymentinfo
 *
 * @author stefan
 */
class Paymentinfo {
        public static $PAYMENT_TYPE_Bar = 0;
        public static $PAYMENT_TYPE_Unbar = 1;
        public static $PAYMENT_TYPE_AVRechnung = 2;
        
        public function getPaymentTypeAsString($pdo,$paymentid, bool $allowOnlyBarAndUnbar):string {
                // REM* paymenttype: 0: Bar, 1: Unbar, 2: AVRechnung
                $sql = "SELECT paymenttype FROM %payment% WHERE id=?";
                $r = CommonUtils::fetchSqlAll($pdo, $sql, array($paymentid));
                try {
                        $payType = $r[0]['paymenttype'];
                        $mapping = array("0" => "Bar","1" => "Unbar", "2" => "AVRechnung");
                        if ($allowOnlyBarAndUnbar) {
                                // REM* for TSE signature
                                $mapping = array("0" => "Bar","1" => "Unbar", "2" => "Unbar");
                        }
                        return $mapping[$payType];
                } catch (Exception $ex) {
                        error_log("Paymenttype of payment with id $paymentid cannot be found: " . $ex->getMessage());
                        return "";
                }
        }
        
        // REM* This method is extraxced from bill.php, but needs to be adapted to reflect the defintion of allowed in payment table
        public function getPossiblePayments($pdo):array {
                $payments = array();
                $sumupforcard = CommonUtils::getConfigValue($pdo, 'sumupforcard', 0);
                if ($sumupforcard == 0) {
                        $nonAllowedPayments = array();
                        $showpayment2 = CommonUtils::getConfigValue($pdo, 'showpayment2', 0);
                        $showpayment3 = CommonUtils::getConfigValue($pdo, 'showpayment3', 0);
                        if ($showpayment2 == 0) {
                                $nonAllowedPayments[] = 2;
                        }
                        if ($showpayment3 == 0) {
                                $nonAllowedPayments[] = 3;
                        }

                        $lang = $_SESSION['language'];
                        $names = array("name","name_en","name_esp");
                        $name = $names[$lang];

                        $sql = "SELECT id,$name as name FROM %payment% WHERE (isallowed='1' AND (paymenttype=? OR paymenttype=?)) ";
                        if (count($nonAllowedPayments) > 0) {
                                $sql = "SELECT id,$name as name FROM %payment% WHERE (isallowed='1' AND (paymenttype=? OR paymenttype=?)) AND id NOT IN (" . implode(',',$nonAllowedPayments) . ")";
                        }
                        $payments = CommonUtils::fetchSqlAll($pdo, $sql, array(self::$PAYMENT_TYPE_Bar,self::$PAYMENT_TYPE_Unbar));
                }
                return $payments;
        }
        
        public function getAllPayments($pdo,int $lang):array {
                $paymentnameitem = "name";
		if ($lang == 1) {
			$paymentnameitem = "name_en";
		} else if ($lang == 2) {
			$paymentnameitem = "name_esp";
		}
                $sql = "SELECT id,$paymentnameitem as payname,paymenttype,isallowed FROM %payment%";
                return CommonUtils::fetchSqlAll($pdo, $sql);
        }
}
