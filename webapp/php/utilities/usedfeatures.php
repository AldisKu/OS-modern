<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of usedfeatures
 *
 * @author stefan
 */
class UsedFeatures {
        public static $Kitchen = array("id" => 1,"threshold" => 10,"abbr" => "KIT");
        public static $Bar = array("id" => 2,"threshold" => 10,"abbr" => "BAR");
        public static $Guestsystem = array("id" => 3,"threshold" => 10,"abbr" => "GUEST");
        public static $Tablemap = array("id" => 4,"threshold" => 10,"abbr" => "TMAP");
        public static $Counting = array("id" => 5,"threshold" => 1,"abbr" => "COUNT");
        public static $Customers = array("id" => 6,"threshold" => 2,"abbr" => "CUST");
        public static $Tasks = array("id" => 7,"threshold" => 4,"abbr" => "TSK");
        public static $Pickup = array("id" => 8,"threshold" => 10,"abbr" => "PICK");
        public static $Customerview = array("id" => 9,"threshold" => 20,"abbr" => "CUST");
        public static $Orders = array("id" => 10,"threshold" => 5,"abbr" => "ORDERS");
        public static $Dashboard = array("id" => 11,"threshold" => 4,"abbr" => "DASH");
        public static $Rating = array("id" => 12,"threshold" => 1,"abbr" => "RAT");
        public static $Tse = array("id" => 13,"threshold" => 10,"abbr" => "TSE");
        
        private static function getArrayOfAllFeatures() {
                $allFeatures = array(self::$Kitchen,self::$Bar,self::$Guestsystem,self::$Tablemap,self::$Counting,self::$Customers,
                    self::$Tasks,self::$Pickup,self::$Customerview,self::$Orders,self::$Dashboard,self::$Rating,self::$Tse);
                return $allFeatures;
        }
        
        public static function noteUsedFeature($pdo,$feature) {
                self::noteUsedFeatureById($pdo, $feature["id"]);
        }
        public static function noteUsedFeatureById($pdo,$featureId) {
                $sql = "INSERT INTO %usedfeatures% (usedate,feature,count) VALUES(DATE(NOW()),?,?)";
                CommonUtils::execSql($pdo, $sql, array($featureId,0));
        }
        
        public static function sumUpUsedFeatures($pdo) {
                
                $sql = "SELECT COUNT(id) as count,usedate,feature FROM %usedfeatures% WHERE count='0' GROUP BY usedate,feature";
                $result = CommonUtils::fetchSqlAll($pdo, $sql);
                foreach ($result as $usedfeature) {
                        $theUseDate = $usedfeature['usedate'];
                        $feature = $usedfeature['feature'];
                        $theCount = $usedfeature['count'];
                        $sql = "DELETE FROM %usedfeatures% WHERE usedate=? AND feature=? AND count=?";
                        CommonUtils::execSql($pdo, $sql, array($theUseDate,$feature,0));
                        
                        $sql = "SELECT id,count FROM %usedfeatures% WHERE usedate=? AND feature=? LIMIT 1";
                        $res = CommonUtils::fetchSqlAll($pdo, $sql, array($theUseDate,$feature));
                        
                        if (count($res) == 0) {
                                $sql = "INSERT INTO %usedfeatures% (usedate,feature,count) VALUES(?,?,?)";
                                CommonUtils::execSql($pdo, $sql, array($theUseDate,$feature,$theCount));
                        } else {
                                $sql = "UPDATE %usedfeatures% SET count=? WHERE usedate=? AND feature=?";
                                CommonUtils::execSql($pdo, $sql, array(intval($res[0]['count']) + $theCount,$theUseDate,$feature));
                        }
                }
        }
        
        private static function getSqlNameForFeature() {
                $allFeatures = self::getArrayOfAllFeatures();
                $sql = " (CASE ";
                foreach ($allFeatures as $f) {
                        $sql .= " WHEN feature='" . $f['id'] . "' THEN '" . $f['abbr'] . "' ";
                }
                $sql .= " ELSE '-' END) ";
                return $sql;
        }
        
        public static function getPureData($pdo) {
                $abbr = self::getSqlNameForFeature();
                $sql = "SELECT id,usedate,$abbr as feature,count FROM %usedfeatures% ORDER BY usedate DESC LIMIT 20";
                return CommonUtils::fetchSqlAll($pdo, $sql);
        }
}
