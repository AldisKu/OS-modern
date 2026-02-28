<?php

class Checks {
	private function checkwriteaccessconfigfolder() {
		return (is_writable("../php"));
	}

	private function checkwriteaccessconfigfile() {
		if (file_exists("../php/config.php")) {
			return (is_writable("../php/config.php"));
		} else {
			return (is_writable("../php"));
		}
	}

	private function checkwriteaccessprivkey() {
		if (file_exists("../php/privkey.pem")) {
			return (is_writable("../php/privkey.pem"));
		} else {
			return (is_writable("../php"));
		}
	}

	private function checkwriteaccesspubkey() {
		if (file_exists("../php/cert.pem")) {
			return (is_writable("../php/cert.pem"));
		} else {
			return (is_writable("../php"));
		}
	}
	
	private function checkwritecustomerfolder() {
		return (is_writable("../customer"));
	}

	private function checkwritespeisekarte() {
		if (file_exists("../customer/speisekarte.txt")) {
			return (is_writable("../customer/speisekarte.txt"));
		} else {
			return (is_writable("../customer"));
		}
	}

	function checkWriteAccess() {
		$retArray = array(
				"configfolder" => $this->checkwriteaccessconfigfolder(),
				"configfile" => $this->checkwriteaccessconfigfile(),
				"customerfolder" => $this->checkwritecustomerfolder(),
				"speisekarte" => $this->checkwritespeisekarte()
		);
		echo json_encode($retArray);
	}
	
	function checkWriteAccessNoJson() {
		return(array(
				"configfolder" => $this->checkwriteaccessconfigfolder(),
				"configfile" => $this->checkwriteaccessconfigfile(),
				"customerfolder" => $this->checkwritecustomerfolder(),
				"speisekarte" => $this->checkwritespeisekarte(),
				"privkey" => $this->checkwriteaccessprivkey(),
				"pubkey" => $this->checkwriteaccesspubkey()
		));
	}

}

class Basedb {
	var $prefix = "";
	var $timezone = "Europe/Berlin";
	
	function setPrefix($pre) {
		$this->prefix = $pre;
	}
	
	function setTimeZone($zone) {
		$this->timezone = $zone;
	}
	
        public static function sendInProgressMsg($text,$isAppend,$doInProgressEvents) {
                if (!$doInProgressEvents) {
                        return;
                }
                if ($isAppend) {
                        echo "#";
                }
                echo json_encode(array("status" => "inprogress","msg" => $text));
                flush();
                ob_flush();
        }
	function doSQL($pdo,$sql) {
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function doSQLcatch($pdo,$sql) {
		try {
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
		} catch (Exception $e) {
			// nothing - table not present or whatever...
		}
	}
	
	function resolveTablenamesInSqlString($sqlString) {
		return DbUtils::substTableAlias($sqlString);
	}
	
	public static function getAllColsOfATable($pdo,$tablename,$excludeArr) {
		$dbname = DbUtils::getDbName();
		
		$sql = "SELECT `COLUMN_NAME` as colname
			FROM `INFORMATION_SCHEMA`.`COLUMNS` 
			WHERE `TABLE_SCHEMA`='$dbname' 
			    AND `TABLE_NAME`='$tablename'";
		$result = CommonUtils::fetchSqlAll($pdo, $sql,null);
		$cols = array();
		foreach($result as $r) {
			$colname = $r["colname"];
			if (!in_array($colname, $excludeArr)) {
				$cols[] = $r["colname"];
			}
		}
		return $cols;
	}
	
	
	function dropTables($pdo) {
		$this->doSQLcatch($pdo, "DROP TABLE `%hsout%`");
		$this->doSQLcatch($pdo, "DROP TABLE `%hsin%`");
		$this->doSQLcatch($pdo, "DROP TABLE `%comments%`");
		$this->doSQLcatch($pdo, "DROP TABLE `%reservations%`");
		$this->doSQLcatch($pdo, "DROP TABLE `%work%`");
		$this->doSQLcatch($pdo, "DROP TABLE `%hist%`");
		$this->doSQLcatch($pdo, "DROP TABLE `%histprod%`");
		$this->doSQLcatch($pdo, "DROP TABLE `%histconfig%`");
		$this->doSQLcatch($pdo, "DROP TABLE `%histuser%`");
		$this->doSQLcatch($pdo, "DROP TABLE `%histactions%`");

		$this->doSQLcatch($pdo, "drop TABLE `%taskhist%`");
		$this->doSQLcatch($pdo, "drop TABLE `%tasks%`");
		$this->doSQLcatch($pdo, "drop TABLE `%queueextras%`");
		$this->doSQLcatch($pdo, "drop TABLE `%extrasprods%`");
		$this->doSQLcatch($pdo, "drop TABLE `%extras%`");
		
		$this->doSQLcatch($pdo, "drop TABLE `%billproducts%`");
		$this->doSQLcatch($pdo, "drop TABLE `%recordsqueue%`");
		$this->doSQLcatch($pdo, "drop TABLE `%records%`");
		$this->doSQLcatch($pdo, "drop TABLE `%times%`");
		$this->doSQLcatch($pdo, "drop TABLE `%queue%`");
                $this->doSQLcatch($pdo, "drop TABLE `%prodnames%`");
                $this->doSQLcatch($pdo, "drop TABLE `%orders%`");
		$this->doSQLcatch($pdo, "drop TABLE `%vouchers%`");
		$this->doSQLcatch($pdo, "drop TABLE `%printjobs%`");
		$this->doSQLcatch($pdo, "drop TABLE `%customerlog%`");
		$this->doSQLcatch($pdo, "drop TABLE `%bill%`");
		$this->doSQLcatch($pdo, "drop TABLE `%ratings%`");

                $this->doSQLcatch($pdo, "drop TABLE `%liveorders%`");
                $this->doSQLcatch($pdo, "drop TABLE `%user%`");
		$this->doSQLcatch($pdo, "drop TABLE `%roles%`");
		$this->doSQLcatch($pdo, "drop TABLE `%counting%`");
		$this->doSQLcatch($pdo, "drop TABLE `%closing%`");
		$this->doSQLcatch($pdo, "drop TABLE `%config%`");
		$this->doSQLcatch($pdo, "drop TABLE `%operations%`");
		$this->doSQLcatch($pdo, "drop TABLE `%terminals%`");
		$this->doSQLcatch($pdo, "drop TABLE `%tsevalues%`");
		$this->doSQLcatch($pdo, "drop TABLE `%products%`");
		$this->doSQLcatch($pdo, "drop TABLE `%prodimages%`");
		$this->doSQLcatch($pdo, "drop TABLE `%prodtype%`");
		$this->doSQLcatch($pdo, "drop TABLE `%pricelevel%`");
		$this->doSQLcatch($pdo, "drop TABLE `%tablepos%`");
		$this->doSQLcatch($pdo, "drop TABLE `%tablemaps%`");
		$this->doSQLcatch($pdo, "drop TABLE `%resttables%`");
		$this->doSQLcatch($pdo, "drop TABLE `%room%`");
		$this->doSQLcatch($pdo, "drop TABLE `%payment%`");
		
		$this->doSQLcatch($pdo, "drop TABLE `%groupcustomer%`");
		$this->doSQLcatch($pdo, "drop TABLE `%vacations%`");
		$this->doSQLcatch($pdo, "drop TABLE `%groups%`");
		$this->doSQLcatch($pdo, "drop TABLE `%customers%`");
		
		$this->doSQLcatch($pdo, "drop TABLE `%logo%`");
		$this->doSQLcatch($pdo, "drop TABLE `%log%`");
                
                $this->doSQLcatch($pdo, "drop TABLE `%performance%`");
                $this->doSQLcatch($pdo, "drop TABLE `%usedfeatures%`");
	}

	
	function createCustomerLogTable($pdo) {
		$sql = "
		CREATE TABLE `%customerlog%` (
		`id` INT (3) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`date` DATETIME NULL,
		`customerid` INT(10) NULL,
		`billid` INT(10) NULL,
		`action` INT(2) NULL,
		`userid` INT(10) NULL,
		`remark` VARCHAR ( 500 ) NULL,
		FOREIGN KEY (billid) REFERENCES %bill%(id),
		FOREIGN KEY (customerid) REFERENCES %customers%(id),
		FOREIGN KEY (userid) REFERENCES %user%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createLogTable($pdo) {
		$sql = "
		CREATE TABLE `%log%` (
		`id` INT (3) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`date` DATETIME NULL,
		`component` VARCHAR ( 20 ) NULL,
		`message` VARCHAR ( 500 ) NULL
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
        
        function createPerformanceTable($pdo) {
		$sql = "
		CREATE TABLE `%performance%` (
		`id` INT (3) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`perfdate` DATE NULL,
		`task` INT NULL,
                `queuesize` INT NULL,
                `tse` INT NULL,
                `numberofsamples` INT NULL,
                `isclientaction` INT NULL,
                `islocalhost` INT NULL,
		`duration` INT NULL
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
        function createUsedFeaturesTable($pdo) {
		$sql = "
		CREATE TABLE `%usedfeatures%` (
		`id` INT (3) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`usedate` DATE NULL,
		`feature` INT NULL,
		`count` INT
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createPaymentTable($pdo) {
		$sql = "
		CREATE TABLE `%payment%` (
		`id` INT (3) NOT NULL UNIQUE,
		`name` VARCHAR ( 20 ) NOT NULL,
		`name_en` VARCHAR ( 20 ) NOT NULL,
		`name_esp` VARCHAR ( 20 ) NOT NULL
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createUserTable($pdo)
	{
		$sql = "
		CREATE TABLE `%user%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`username` VARCHAR ( 150 ) NOT NULL,
		`userpassword` VARCHAR ( 150 ) NOT NULL,
		`is_admin` INT (1) NOT NULL,
		`right_waiter` INT (1) NOT NULL,
		`right_kitchen` INT (1) NOT NULL,
		`right_bar` INT (1) NOT NULL,
		`right_supply` INT (1) NOT NULL,
		`right_paydesk` INT (1) NOT NULL,
		`right_statistics` INT (1) NOT NULL,
		`right_bill` INT (1) NOT NULL,
		`right_products` INT (1) NOT NULL,
		`right_manager` INT (1) NOT NULL,
		`right_closing` INT (1) NOT NULL,
		`right_reservation` INT (1) NOT NULL,
		`right_rating` INT (1) NOT NULL,
		`right_changeprice` INT (1) NOT NULL,
		`right_customers` INT (1) NOT NULL,
		`lastmodule` VARCHAR ( 30 ) NULL,
		`ordervolume` INT (2) NULL,
		`language` INT (2) NULL,
		`receiptprinter` INT (1) NULL,
		`roombtnsize` INT(1) NULL,
		`tablebtnsize` INT(1) NULL,
		`prodbtnsize` INT(1) NULL,
		`prefertablemap` INT(1) NULL,
		`keeptypelevel` INT(1) NOT NULL,
		`extrasapplybtnpos` INT(1) NOT NULL,
		`active` INT (2) NOT NULL
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$this->doSQL($pdo,$sql);
	}
	
	function createRolesTable($pdo)
	{
		try {
			$sql = "
			CREATE TABLE %roles% (
			id INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			name VARCHAR ( 150 ) NOT NULL,
			is_admin INT (1) NOT NULL,
			right_waiter INT (1) NOT NULL,
			right_kitchen INT (1) NOT NULL,
			right_bar INT (1) NOT NULL,
			right_supply INT (1) NOT NULL,
			right_paydesk INT (1) NOT NULL,
			right_statistics INT (1) NOT NULL,
			right_bill INT (1) NOT NULL,
			right_products INT (1) NOT NULL,
			right_manager INT (1) NOT NULL,
			right_closing INT (1) NOT NULL,
			right_dash INT (1) NOT NULL,
			right_reservation INT (1) NOT NULL,
			right_rating INT (1) NOT NULL,
			right_changeprice INT (1) NOT NULL,
			right_customers INT (1) NOT NULL
			) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
			";
			$this->doSQL($pdo,$sql);
		} catch (Exception $ex) {
			// table may exists due to other installations
		}
	}
	
	function createRoomTable($pdo)
	{
		$sql = "
		CREATE TABLE `%room%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`roomname` VARCHAR ( 150 ) NOT NULL,
		`abbreviation` VARCHAR (10) NULL,
		`printer` INT(2) NULL,
		`removed` INT(2) NULL,
		`sorting` INT(2) NULL
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createTableMapsTable($pdo)
	{
		$sql = "
		CREATE TABLE `%tablemaps%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`roomid` INT (10) NOT NULL,
		`img` MEDIUMBLOB,
		`sizex` INT(4) NULL,
		`sizey` INT(4) NULL,
		FOREIGN KEY (roomid) REFERENCES %room%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createTablePosTable($pdo)
	{
		$sql = "
		CREATE TABLE `%tablepos%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`tableid` INT (10) NOT NULL,
		`x` INT(4) NULL,
		`y` INT(4) NULL,
		FOREIGN KEY (tableid) REFERENCES %resttables%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createRestTables($pdo)
	{
		$sql = "
		CREATE TABLE `%resttables%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`tableno` VARCHAR ( 150 ) NOT NULL,
		`roomid` INT ( 10 ) NOT NULL,
		`removed` INT(2) NULL,
		`sorting` INT(2) NULL,
		FOREIGN KEY (roomid) REFERENCES %room%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createConfigTable($pdo) {
		$sql = "
		CREATE TABLE `%config%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`name` VARCHAR ( 1000 ) ,
		`setting` VARCHAR ( 10000 )
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createLogoTable($pdo) {
		$sql = "
		CREATE TABLE `%logo%` (
		`id` INT (2) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`name` VARCHAR ( 100 ) ,
		`setting` BLOB
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createProdTypeTable($pdo)
	{
		$sql = "
		CREATE TABLE `%prodtype%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`name` VARCHAR ( 150 ) NOT NULL,
		`usekitchen` INT(1) NOT NULL,
		`usesupplydesk` INT(1) NOT NULL,
		`kind` INT(2) NOT NULL,
		`printer` INT(2) NULL,
		`sorting` INT(2) NULL,
		`reference` INT (10) NULL,
		`removed` INT(1) NULL,
		FOREIGN KEY (reference) REFERENCES %prodtype%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$this->doSQL($pdo,$sql);
	}
	
	function createExtrasTable($pdo) {
		$sql = "
		CREATE TABLE `%extras%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`name` VARCHAR ( 150 ) NOT NULL,
		`price` " . DECIMALSMALL .  " NOT NULL,
		`removed` INT(1) NULL
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$this->doSQL($pdo,$sql);
	}
	
	function createExtrasprodsTable($pdo) {
		$sql = "
		CREATE TABLE `%extrasprods%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`extraid` INT (10) NOT NULL,
		`prodid` INT (10) NOT NULL,
		FOREIGN KEY (extraid) REFERENCES %extras%(id),
		FOREIGN KEY (prodid) REFERENCES %products%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$this->doSQL($pdo,$sql);
	}
	
	function createQueueExtrasTable($pdo) {
		$sql = "
		CREATE TABLE `%queueextras%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`queueid` INT (10) NOT NULL,
		`extraid` INT (10) NOT NULL,
		`name` VARCHAR (200) NOT NULL,
		FOREIGN KEY (extraid) REFERENCES %extras%(id),
		FOREIGN KEY (queueid) REFERENCES %queue%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$this->doSQL($pdo,$sql);	
	}
	
	function createProductTable($pdo)
	{
		$sql = "
		CREATE TABLE `%products%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`shortname` VARCHAR ( 150 ) NOT NULL,
		`longname` VARCHAR ( 150 ) NOT NULL,
		`priceA` " . DECIMALSMALL . " NULL,
		`priceB` " . DECIMALSMALL . "  NULL,
		`priceC` " .DECIMALSMALL . " NULL,
		`tax` " . DECIMALSMALL . " NULL,
		`taxaustria` INT(1) NULL,
		`amount` INT(5) NULL,
		`category` INT(3) NULL,
		`favorite` INT(1) NULL,
		`sorting` INT(2) NULL,
		`available` INT(2) NOT NULL,
		`audio` VARCHAR ( 150 ) NULL,
		`removed` INT(1) NULL,
		FOREIGN KEY (category) REFERENCES %prodtype%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$this->doSQL($pdo,$sql);
	}
	
	function createPriceLevelTable($pdo) {
		$sql = "
		CREATE TABLE `%pricelevel%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`name` VARCHAR ( 1000 ) ,
		`info` VARCHAR ( 1000 ),
		`info_en` VARCHAR ( 1000 ) ,
		`info_esp` VARCHAR ( 1000 )
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createClosingTable($pdo) {
		$sql = "
		CREATE TABLE `%closing%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`closingdate` DATETIME NOT NULL ,
		`billcount` INT(5) NOT NULL ,
		`billsum` " . DECIMALBIG . " NOT NULL ,
		`signature` blob NULL,
		`remark` VARCHAR ( 1000 )
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createRatingsTable($pdo) {
		$sql = "
		CREATE TABLE `%ratings%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`date` DATETIME NOT NULL ,
		`service` INT(2) NULL,
		`kitchen` INT(2) NULL,
		`remark` VARCHAR ( 200 ) NULL
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createBillTable($pdo)
	{
		$sql = "
		CREATE TABLE `%bill%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`billdate` DATETIME NOT NULL ,
		`brutto` " . DECIMALMIDDLE . " NULL,
		`netto` " . DECIMALMIDDLE . " NULL,
		`prevbrutto` " . DECIMALBIG . " NULL,
		`prevnetto` " . DECIMALBIG . " NULL,
		`tableid` VARCHAR ( 150 ) NOT NULL,
		`closingid` INT(4) NULL,
		`status` VARCHAR(2) NULL,
		`paymentid` INT(2) NULL,
		`userid` INT(3) NULL,
		`ref` INT(10) NULL,
		`tax` " . DECIMALSMALL . " NULL,
		`host` INT(2) NULL,
		`reason` VARCHAR ( 150 ) NULL,
		`reservationid` VARCHAR( 30 ) NULL,
		`guestinfo` VARCHAR( 30 ) NULL,
		`intguestid` INT(10) NULL,
		`intguestpaid` INT(2) NULL,
		`signature`blob NULL,
		FOREIGN KEY (closingid) REFERENCES %closing%(id),
		FOREIGN KEY (paymentid) REFERENCES %payment%(id),
		FOREIGN KEY (userid) REFERENCES %user%(id),
		FOREIGN KEY (ref) REFERENCES %bill%(id),
		FOREIGN KEY (intguestid) REFERENCES %customers%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createCustomersTable($pdo)
	{
		$sql = "
		CREATE TABLE `%customers%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`name` VARCHAR(50) NULL,
		`address` VARCHAR(200) NULL,
		`email` VARCHAR(50) NULL,
		`phone` VARCHAR(30) NULL,
		`mobil` VARCHAR(30) NULL,
		`www` VARCHAR(50) NULL,
		`remark` VARCHAR(500) NULL,
		`created` DATETIME NULL,
		`lastmodified` DATETIME NULL
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createGroupsTable($pdo)
	{
		$sql = "
		CREATE TABLE `%groups%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`name` VARCHAR(50) NULL,
		`remark` VARCHAR(200) NULL,
		`created` DATETIME NULL
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createGroupCustomerTable($pdo)
	{
		$sql = "
		CREATE TABLE `%groupcustomer%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`groupid` INT(10) NOT NULL,
		`customerid` INT(10) NOT NULL,
		FOREIGN KEY (groupid) REFERENCES `%groups%`(id),
		FOREIGN KEY (customerid) REFERENCES %customers%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createVacationsTable($pdo)
	{
		$sql = "
		CREATE TABLE `%vacations%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`customerid` INT(10) NOT NULL,
		`checkin` DATE NULL,
		`checkout` DATE NULL,
		`room` VARCHAR(50) NULL,
		`remark` VARCHAR(200) NULL,
		FOREIGN KEY (customerid) REFERENCES %customers%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	/*
	 * Create the queue table:
	 * 	action: P=Pay, S=Storno
	 */
	function createQueueTable($pdo)
	{
		$sql = "
		CREATE TABLE `%queue%` (
		`id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`tablenr` INT( 3 ) NULL ,
		`productid` INT( 10 ) NULL ,
		`pricelevel` INT( 3 ) NOT NULL ,
		`price` " . DECIMALSMALL . " NOT NULL,
		`tax` " . DECIMALSMALL . " NOT NULL,
		`taxaustria` INT(1) NULL,
		`productname` VARCHAR( 150 ) NULL,
		`ordertime` DATETIME NULL ,
		`orderuser` INT(10) NOT NULL ,	
		`anoption` VARCHAR( 150 ) NULL ,
		`pricechanged` INT(1) NULL ,
		`togo` INT(1) NULL ,
		`readytime` DATETIME NULL,
		`delivertime` DATETIME NULL,
		`paidtime` DATETIME NULL,
		`billid` INT(10),
		`toremove` INT(3) NOT NULL,
		`cooking` INT(10) NULL,
		`workprinted` INT(2) NOT NULL,
		`isclosed` INT(1) NULL,
		FOREIGN KEY (tablenr) REFERENCES %resttables%(id),
		FOREIGN KEY (pricelevel) REFERENCES %pricelevel%(id),
		FOREIGN KEY (productid) REFERENCES %products%(id),
		FOREIGN KEY (billid) REFERENCES %bill%(id),
		FOREIGN KEY (cooking) REFERENCES %user%(id),
		FOREIGN KEY (orderuser) REFERENCES %user%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$this->doSQL($pdo,$sql);
	}
	
	function createBillProductsTable($pdo) {
		$sql = "
		CREATE TABLE `%billproducts%` (
		`queueid` INT( 10 ) NOT NULL,
		`billid` INT(10) NOT NULL,
		FOREIGN KEY (queueid) REFERENCES %queue%(id),
		FOREIGN KEY (billid) REFERENCES %bill%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$this->doSQL($pdo,$sql);
	}
	
	function createVouchersTable($pdo) {
		$sql = "
		CREATE TABLE `%vouchers%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`creationdate` DATETIME NULL,
		`name` VARCHAR( 150 ) NULL,
		`redeemdate` DATETIME NULL,
		`creatorid` INT NULL,
		`redeemerid` INT NULL,
		`removed` INT NULL,
		FOREIGN KEY (creatorid) REFERENCES %user%(id),
		FOREIGN KEY (redeemerid) REFERENCES %user%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$this->doSQL($pdo,$sql);
	}
        
        function createOrdersTable($pdo) {
		$sql = "
		CREATE TABLE `%orders%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`creationdate` DATETIME NULL,		
		`creatorid` INT NULL,
                `name` VARCHAR( 150 ) NULL,
                `street` VARCHAR( 150 ) NULL,
                `housenumber` VARCHAR( 10 ) NULL,
		`postalcode` VARCHAR( 50 ) NULL,
                `city` VARCHAR( 50 ) NULL,
                `phone` VARCHAR( 50 ) NULL,
                `remark` VARCHAR( 200 ) NULL,
                `customerid` INT NULL,
		`status` INT NULL,
		FOREIGN KEY (customerid) REFERENCES %customers%(id),
		FOREIGN KEY (creatorid) REFERENCES %user%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$this->doSQL($pdo,$sql);
	}
        
        function createProdnamesTable($pdo) {
		$sql = "
		CREATE TABLE `%prodnames%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`name` VARCHAR(200) NULL ,
                INDEX iprodnames (name)
		) CHARACTER SET utf8 COLLATE utf8_bin ENGINE = InnoDb ;
		";
		$this->doSQL($pdo,$sql);
	}
	
	function createProdimagesTable($pdo) {
		$sql = "
		CREATE TABLE `%prodimages%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`imgh` MEDIUMBLOB,
		`imgm` MEDIUMBLOB,
		`imgl` MEDIUMBLOB
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		try {
			$this->doSQL($pdo,$sql);
		} catch (Exception $ex) {
			// do nothing - the table may already exist (previous installation etc.
		}
	}
	
	function createHistTables($pdo) {
		$sql = "
		CREATE TABLE `%hist%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`date` DATETIME NOT NULL ,
		`action` INT ( 2 ) NOT NULL,
		`refid` INT (10) NULL
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb
		";
		$this->doSQL($pdo,$sql);
	
		$sql = "
		CREATE TABLE `%histprod%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`prodid` INT (10) NOT NULL,
		`shortname` VARCHAR ( 150 ) NOT NULL,
		`longname` VARCHAR ( 150 ) NOT NULL,
		`priceA` " . DECIMALSMALL . " NULL,
		`priceB` " . DECIMALSMALL . " NULL,
		`priceC` " . DECIMALSMALL . " NULL,
		`tax` " . DECIMALSMALL . " NULL,
		`taxaustria` INT(1) NULL,
		`sorting` INT(2) NULL,
		`available` INT(2) NOT NULL,
		`favorite` INT(1) NULL,
		`audio` VARCHAR ( 150 ) NULL,
		`extras` VARCHAR ( 300 ) NULL,
		FOREIGN KEY (prodid) REFERENCES %products%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb
		";
		$this->doSQL($pdo,$sql);
	
		$sql = "
		CREATE TABLE `%histconfig%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`configid` INT (10) ,
		`setting` VARCHAR ( 10000 ),
		FOREIGN KEY (configid) REFERENCES %config%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb
		";
		$this->doSQL($pdo,$sql);
	
		$sql = "
		CREATE TABLE `%histuser%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`userid` INT (10) ,
		`username` VARCHAR ( 150 ) NOT NULL,
		`is_admin` INT (1) NOT NULL,
		`right_waiter` INT (1) NOT NULL,
		`right_kitchen` INT (1) NOT NULL,
		`right_bar` INT (1) NOT NULL,
		`right_supply` INT (1) NOT NULL,
		`right_paydesk` INT (1) NOT NULL,
		`right_statistics` INT (1) NOT NULL,
		`right_bill` INT (1) NOT NULL,
		`right_products` INT (1) NOT NULL,
		`right_manager` INT (1) NOT NULL,
		`right_closing` INT (1) NOT NULL,
		`right_reservation` INT (1) NOT NULL,
		`right_rating` INT (1) NOT NULL,
		`right_changeprice` INT (1) NOT NULL,
		`right_customers` INT (1) NOT NULL,
		`active` INT (2) NOT NULL,
		FOREIGN KEY (userid) REFERENCES %user%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb
		";
		$this->doSQL($pdo,$sql);
	
		$sql = "
		CREATE TABLE `%histactions%` (
		`id` INT (3) NOT NULL,
		`name` VARCHAR ( 20 ) NOT NULL,
		`description` VARCHAR ( 150 ) NULL
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb
		";
		$this->doSQL($pdo,$sql);
	}
	
	function createPrintJobsTable($pdo) {
		$sql = "
		CREATE TABLE `%printjobs%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`content` TEXT NOT NULL ,
		`type` INT (2) NOT NULL ,
		`printer` INT(2) NULL
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createWorkTable($pdo) {
		$sql = "
		CREATE TABLE `%work%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`item` VARCHAR ( 20 ) NOT NULL ,
		`value` VARCHAR ( 20 ) NOT NULL ,
		`signature` blob NULL
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createRecordsQueueTable($pdo) {
		$sql = "
		CREATE TABLE `%recordsqueue%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`recordid` INT (10) NOT NULL,
		`queueid` INT (10) NOT NULL,
		FOREIGN KEY (recordid) REFERENCES %records%(id),
		FOREIGN KEY (queueid) REFERENCES %queue%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createRecordsTable($pdo) {
		$sql = "
		CREATE TABLE `%records%` (
		`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`date` DATETIME NOT NULL ,
		`userid` INT (10) NULL,
		`tableid` INT (10) NULL,
		`action` INT (3) NOT NULL,
		FOREIGN KEY (userid) REFERENCES %user%(id),
		FOREIGN KEY (tableid) REFERENCES %resttables%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createTimesTable($pdo) {
		$sql = "
		CREATE TABLE `%times%` (
			`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			`date` DATETIME NOT NULL ,
			`userid` INT (10) NULL,
			`action` INT(1) NULL,
			`comment` VARCHAR(200) NULL,
			FOREIGN KEY (userid) REFERENCES %user%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function createTasksTable($pdo) {
		$sql = "
		CREATE TABLE `%tasks%` (
			`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			`submitdate` DATETIME NOT NULL ,
			`lastdate` DATETIME NULL ,
			`submitter` INT (10) NULL,
			`owner` INT (10) NULL,
			`status` INT(1) NULL,
			`prio` INT(1) NULL,
			`summary` VARCHAR(100) NULL,
			`description` VARCHAR(500) NULL,
			`productid` INT( 10 ) NULL ,
			FOREIGN KEY (submitter) REFERENCES %user%(id),
			FOREIGN KEY (owner) REFERENCES %user%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}

	function createTseValuesTable($pdo) {
		$sql = "
		CREATE TABLE `%tsevalues%` (
			`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			`textvalue` TEXT NULL
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	function createTerminalsTable($pdo) {
		$sql = "
		CREATE TABLE `%terminals%` (
			`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			`ipaddress` VARCHAR(300) NULL,
			`useragent` VARCHAR(300) NULL,
			`browser` VARCHAR(300) NULL,
			`version` VARCHAR(300) NULL,
			`platform` VARCHAR(300) NULL
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	public function createOperationsTable($pdo) {
		$sql = "
		CREATE TABLE `%operations%` (
			`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			`typerange` INT NULL,
			`bonid` INT NULL,
			`processtype` INT(1) NULL,
			`handledintable` INT(1) NULL,
			`signtxt` VARCHAR(120) NULL DEFAULT NULL,
			`logtime` INT(5) NULL DEFAULT NULL,
			`trans` INT(5) NULL DEFAULT NULL,
			`sigcounter` INT(5) NULL DEFAULT NULL,
			`tsesignature` VARCHAR(140) NULL DEFAULT NULL,
			`pubkey` INT(2) NULL DEFAULT NULL,
			`sigalg` INT(2) NULL DEFAULT NULL,
			`serialno` INT(2) NULL DEFAULT NULL,
			`certificate` INT(2) NULL DEFAULT NULL,
			`tseerror` INT(2) NULL DEFAULT '1',
			`terminalid` INT NULL DEFAULT NULL,
			
			FOREIGN KEY(pubkey) REFERENCES %tsevalues% (id),
			FOREIGN KEY(sigalg) REFERENCES %tsevalues% (id),
			FOREIGN KEY(terminalid) REFERENCES %terminals% (id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	public function createCountingTable($pdo) {
		$sql = "
		CREATE TABLE `%counting%` (
			`id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
			`clsid` INT NULL DEFAULT NULL,
			`value` INT NULL DEFAULT NULL,
			`count` INT NULL DEFAULT NULL,
			`iscoin` INT NULL DEFAULT NULL,
			FOREIGN KEY(clsid) REFERENCES %closing%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
        
        public function deduplicateQueueNames($pdo) {                
                $sql = "SELECT DISTINCT (CAST(productname AS CHAR CHARACTER SET utf8) COLLATE utf8_bin) as prodname FROM %queue%";
                $result = CommonUtils::fetchSqlAll($pdo, $sql);
                $sql = "INSERT INTO %prodnames% (name) VALUES(?)";
                foreach($result as $aProdName) {
                        CommonUtils::execSql($pdo, $sql, array($aProdName["prodname"]));
                }
                $sql = "UPDATE %queue% Q, %prodnames% PN SET Q.prodnameid=PN.id WHERE (CAST(Q.productname AS CHAR CHARACTER SET utf8) COLLATE utf8_bin)=PN.name";
                CommonUtils::execSql($pdo, $sql, null);
        }
        
	
	function createTaskHistTable($pdo) {
		$sql = "
		CREATE TABLE `%taskhist%` (
			`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			`date` DATETIME NOT NULL ,
			`taskid` INT(10) NOT NULL ,
			`userid` INT (10) NULL,
			`action` INT(1) NOT NULL,
			`fields` VARCHAR(100) NULL,
			FOREIGN KEY (userid) REFERENCES %user%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
        function createLiveOrdersTable($pdo) {
                $sql = "
		CREATE TABLE `%liveorders%` (
			`id` INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
			`userid` INT NULL,
			`prodname` VARCHAR(100) NULL,
                        `price` DECIMAL(15,2) NULL,
			FOREIGN KEY (userid) REFERENCES %user%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = MEMORY;
		";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
                
        }
        
	function signLastBillid($pdo) {
		$sql = "SELECT MAX(id) as maxbillid FROM %bill%";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		$row =$stmt->fetchObject();
		if ($row != null) {
			$lastBillId = $row->maxbillid;
		} else {
			$lastBillId = 0;
		}

		$lastBillId = intval($lastBillId);
		$signature = md5("B($lastBillId)");
		
		$sql = "SELECT id FROM %work% WHERE item=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array("lastbillid"));
		if ($stmt->rowCount() > 0) {
			$sql = "UPDATE %work% SET value=?, signature=? WHERE item=?";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($lastBillId,$signature,"lastbillid"));
		} else {
			$sql = "INSERT INTO `%work%` (`id` , `item`,`value`,`signature`) VALUES ( NULL,?,?,?)";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array("lastbillid",$lastBillId,$signature));
		}
	}
	
	function initTableOrder($pdo) {
		$maxNoOfRoom = array();
		$sql = "SELECT id,roomid FROM %resttables% WHERE removed is null";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		$sql = "UPDATE %resttables% SET sorting=? WHERE id=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		foreach ($result as $r) {
			$tableid = $r["id"];
			$roomid = $r["roomid"];
			if (!key_exists($roomid, $maxNoOfRoom)) {
				$maxNoOfRoom[$roomid] = 0;
			}
			$nextSort = $maxNoOfRoom[$roomid] + 1;
			$maxNoOfRoom[$roomid] = $nextSort;
			$stmt->execute(array($nextSort,$tableid));
		}
		$sql = "UPDATE %resttables% SET active='1' WHERE active is null";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		$sql = "UPDATE %resttables% SET allowoutorder='1' WHERE allowoutorder is null";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		$sql = "UPDATE %resttables% SET name=tableno WHERE name is null";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
	function initRoomOrder($pdo) {
		$sql = "SELECT id FROM %room% WHERE removed IS NULL ORDER BY id";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		$counter = 1;
		$sql = "UPDATE %room% SET sorting=? WHERE id=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		foreach($result as $aRoom) {
			$stmt->execute(array($counter,$aRoom["id"]));
			$counter++;
		}
	}
	
	function createHsinTable($pdo) {
		$sql = "
		CREATE TABLE `%hsin%` (
		`id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`reservationid` VARCHAR( 30 ) NULL ,
		`billid` INT(10) NULL,
		`date` VARCHAR( 10 ) NULL,
		`time` VARCHAR( 5 ) NULL,
		`number` INT(10)  NULL,
		`prodid` INT(10)  NULL,
		`prodname` VARCHAR( 100 ) NULL,
		`tax` VARCHAR( 50 ) NULL,
		`brutto` " . DECIMALSMALL .  " NOT NULL,
		`total` " . DECIMALSMALL .  " NOT NULL,
		`currency` VARCHAR( 5 ) NULL,
		`waiterid` VARCHAR( 20 ) NULL,
		`waitername` VARCHAR( 20 ) NULL
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$this->doSQL($pdo,$sql);
	}

	function createHsoutTable($pdo) {
		$sql = "
		CREATE TABLE `%hsout%` (
		`id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`reservationid` VARCHAR( 50 ) NULL ,
		`object` VARCHAR( 50 ) NULL ,
		`guest` VARCHAR( 100 ) NULL
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$this->doSQL($pdo,$sql);
	}
	
	function createCommentsTable($pdo) {
		$sql = "
		CREATE TABLE `%comments%` (
		`id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY ,
		`comment` VARCHAR( 150 ) NULL ,
		`prodid` INT (10) NULL ,
		`sorting` INT(2) NULL,
		`active` INT (2) NOT NULL,
		FOREIGN KEY (prodid) REFERENCES %products%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$this->doSQL($pdo,$sql);
	}
	
	function createReservationsTable($pdo) {
		$sql = "
		CREATE TABLE `%reservations%` (
		`id` INT( 10 ) NOT NULL AUTO_INCREMENT PRIMARY KEY,
		`creator` INT (10) NOT NULL,
		`creationdate` DATETIME NOT NULL,
		`scheduledate` DATETIME NOT NULL,
		`name` VARCHAR( 100 ) NULL,
		`email` VARCHAR( 100 ) NULL,
		`starttime` INT (3) NOT NULL,
		`duration` INT (3) NOT NULL,
		`persons` INT (10) NULL,
		`phone` VARCHAR( 40 ) NULL,
		`remark` VARCHAR( 400 ) NULL,
		FOREIGN KEY (creator) REFERENCES %user%(id)
		) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb ;
		";
		$this->doSQL($pdo,$sql);
	}
	
	function createEmptyTables($pdo)
	{
		$this->createLogTable($pdo);
		$this->createPaymentTable($pdo);
		$this->createUserTable($pdo);
		$this->createRoomTable($pdo);
		$this->createRestTables($pdo);
		$this->createTableMapsTable($pdo);
		$this->createTablePosTable($pdo);
		$this->createConfigTable($pdo);
		$this->createProdTypeTable($pdo);
		$this->createProductTable($pdo);
		$this->createPriceLevelTable($pdo);
		$this->createClosingTable($pdo);
		$this->createRatingsTable($pdo);
		$this->createCustomersTable($pdo);
		$this->createGroupsTable($pdo);
		$this->createGroupCustomerTable($pdo);
		$this->createVacationsTable($pdo);
		$this->createBillTable($pdo);
		$this->createQueueTable($pdo);
		$this->createBillProductsTable($pdo);
		$this->createHistTables($pdo);
		$this->createPrintJobsTable($pdo);
		$this->createWorkTable($pdo);
		$this->createCommentsTable($pdo);
		$this->createHsinTable($pdo);
		$this->createHsoutTable($pdo);
		$this->createReservationsTable($pdo);
		$this->createLogoTable($pdo);
		$this->createExtrasTable($pdo);
		$this->createExtrasprodsTable($pdo);
		$this->createQueueExtrasTable($pdo);
	}
	
	private function createContentInPaymentTable($pdo) {
		$sql = "INSERT INTO %payment% (id,name,name_en,name_esp) VALUES (?,?,?,?)";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));

		$stmt->execute(array('1', 'Barzahlung', 'Cash', 'Contado'));
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));

		$stmt->execute(array('2', 'EC-Kartenzahlung','Electr. purse (EC)','Pago con tarjeta EC'));
		$stmt->execute(array('3', 'Kreditkartenzahlung','Credit card','Tarjeta de credito'));
		$stmt->execute(array('4', 'Rechnung','bill','Factura'));
		$stmt->execute(array('5', 'Ueberweisung','Bank transfer','Transferencia'));
		$stmt->execute(array('6', 'Lastschrift','Debit','Cargo en cuenta'));
		$stmt->execute(array('7', 'Hotelzimmer','Hotel room','Habitación'));
		$stmt->execute(array('8', 'Gast','Guest','Cliente'));
	}
	
	public function defineHistActions ($pdo) {
		$sql = "INSERT INTO %histactions% (id,name,description) VALUES (?,?,?)";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));

		$stmt->execute(array('1', 'ProdInit', 'Initiales Befuellen der Produkttabelle'));
		$stmt->execute(array('2', 'ConfigInit', 'Initiales Befuellen der Konfigurationstabelle'));
		$stmt->execute(array('3', 'UserInit', 'Initiales Befuellen der Benutzertabelle'));
		$stmt->execute(array('4', 'ProdChange', 'Modifikation der Produktdaten'));
		$stmt->execute(array('5', 'ProdCreation', 'Neues Produkt'));
		$stmt->execute(array('6', 'ConfigChange', 'Modifikation der Konfiguration'));
		$stmt->execute(array('7', 'UserCreation', 'Neuer Benutzer'));
		$stmt->execute(array('8', 'UserChange', 'Modifikation eines Benutzers'));
	}
	
	function readConfigTableAndSendToHist($pdo) {
		$sql_query = "SELECT * FROM %config%";

		$sql_insert_histconfig = "INSERT INTO %histconfig% (id,configid,setting) VALUES (NULL,?,?)";

		$stmt_query = $pdo->prepare(DbUtils::substTableAlias($sql_query));
		$stmt_insert_histconfig = $pdo->prepare(DbUtils::substTableAlias($sql_insert_histconfig));

		$stmt_query->execute();
		$result = $stmt_query->fetchAll();
		foreach($result as $row){
			$stmt_insert_histconfig->execute(array($row['id'],$row['setting']));
			$newRefIdForHist = $pdo->lastInsertId();
			$this->insertIntoHist($pdo, '2', $newRefIdForHist);
		}
	}
	
	private function insertIntoHist($pdo,$action,$refIdForHist) {
		date_default_timezone_set($this->timezone);
		$currentTime = date('Y-m-d H:i:s');

		$sql_insert_hist = "INSERT INTO %hist% (id,date,action,refid) VALUES (NULL,?,?,?)";
		$stmt_insert_hist = $pdo->prepare(DbUtils::substTableAlias($sql_insert_hist));
		$stmt_insert_hist->execute(array($currentTime, $action, $refIdForHist));
	}
	
	function createAndIntializeTables($pdo,$decpoint, $billlanguage, $currency, $timezone, $doInProgressEvents) {
		$this->setTimeZone($timezone);

                self::sendInProgressMsg("Entferne alte Tabellen", true,$doInProgressEvents);
		$this->dropTables($pdo);

                self::sendInProgressMsg("Erstelle neue leere Tabellen", true,$doInProgressEvents);
		$this->createEmptyTables($pdo);
		
                self::sendInProgressMsg("Zahlungswege definieren", true,$doInProgressEvents);
		$this->createContentInPaymentTable($pdo);
		$this->defineHistActions($pdo);

		$rect = Version::getDefaultCustomRecTemplate();
		$foodtemplate = Version::getDefaultWorkTemplateFood();
		$drinktemplate = Version::getDefaultWorkTemplateDrinks();

		$printpass = md5("123");

                self::sendInProgressMsg("Konfigurationsdaten einfügen", true,$doInProgressEvents);
		$this->doSQL($pdo, "INSERT INTO `%pricelevel%` (`id` , `name`,`info`,`info_en`,`info_esp`) VALUES ('1', 'A', 'Normale Preisstufe', 'Normal', 'Normal')");
		$this->doSQL($pdo, "INSERT INTO `%pricelevel%` (`id` , `name`,`info`,`info_en`,`info_esp`) VALUES ('2', 'B', 'Wochenendtarif', 'Weekend prices','Tarifa del fin de semana')");
		$this->doSQL($pdo, "INSERT INTO `%pricelevel%` (`id` , `name`,`info`,`info_en`,`info_esp`) VALUES ('3', 'C', 'Happy Hour', 'Happy Hour','Happy Hour')");

		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'pricelevel', '1')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'tax', '19.0')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'togotax', '7.0')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'taxaustrianormal', '20.0')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'taxaustriaerm1', '10.0')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'taxaustriaerm2', '13.0')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'taxaustriaspecial', '19.0')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'stornocode', '123')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'printpass', '$printpass')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'companyinfo', 'Musterrestaurant\nBeispielstrasse 123\n12345 Musterort')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'rectemplate', '$rect')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'foodtemplate', '$foodtemplate')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'drinktemplate', '$drinktemplate')");
		$resTxt = 'Vielen Dank für Ihre Reservierung am DATUM um ZEIT Uhr für ANZAHL Personen.\n\nWir freuen uns auf Ihren Besuch!\n\nBETRIEBSINFO';
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'reservationnote', '$resTxt')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'serverurl', '')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'email', '')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'receiveremail', '')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'payprinttype', 's')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'billlanguage', $billlanguage)");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'currency', '$currency')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'receiptfontsize', '12')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'version', '1.3.0')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'paymentconfig', '0')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'remoteaccesscode', null)");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'decpoint', '$decpoint')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'timezone', '$timezone')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'webimpressum', 'Musterrestaurant\nBeispielstrasse 123\n12345 Musterort')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'cancelunpaidcode', '')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'bigfontworkreceipt', '0')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'prominentsearch', '0')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'groupworkitems', '1')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'addreceipttoprinter', null)");

		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'smtphost', '')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'smtpauth', '1')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'smtpuser', '')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'smtppass', '')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'smtpsecure', '1')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'smtpport', '587')");

		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'discount1', '50')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'discount2', '20')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'discount3', '10')");

		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'austria', '0')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'paydeskid', 'OrderSprinter-1')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'aeskey', '0102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f20')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'certificatesn', '1234567')");

		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'digigopaysetready', '1')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'waitergopayprint', '0')");

		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'oneprodworkreceipts', '0')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'digiprintwork', '0')");

		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'printandqueuejobs', '0')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'cashenabled', '1')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'beepcooked', '0')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'hotelinterface', '0')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'hsinfile', '')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'hsoutfile', '')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'hscurrency', 'EUR')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'hs3refresh', '60')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'memorylimit', '256')");
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'restaurantmode', '1')");

		// prepare for later inconsistency check if version is obsolete
		date_default_timezone_set($timezone);
		$installDate = date('Y-m-d H:i:s');
		$this->doSQL($pdo, "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL , 'installdate', '$installDate')");

                self::sendInProgressMsg("Konfiguration versionieren", true,$doInProgressEvents);
		$this->readConfigTableAndSendToHist($pdo);
		return;
	}
	
	public static function changeInitialConfig($pdo,$restaurantmode,$dsfinvk_name,$dsfinvk_street,$dsfinvk_postalcode,$dsfinvk_city,$dsfinvk_country,$dsfinvk_stnr,$dsfinvk_ustid,
                $cat1name,$cat2name,$prodlistname,$cat1viewname,$cat2viewname,$deskviewname,
                $paydeskid,$companyinfo,$defaultview,$stornocode,$printpass,$remoteaccesscode) {
                
		$sql = "UPDATE `%config%` SET setting=? WHERE name=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($restaurantmode,"restaurantmode"));
		
		$stmt->execute(array($dsfinvk_name,"dsfinvk_name"));
		$stmt->execute(array($dsfinvk_street,"dsfinvk_street"));
		$stmt->execute(array($dsfinvk_postalcode,"dsfinvk_postalcode"));
		$stmt->execute(array($dsfinvk_city,"dsfinvk_city"));
		$stmt->execute(array($dsfinvk_country,"dsfinvk_country"));
		$stmt->execute(array($dsfinvk_stnr,"dsfinvk_stnr"));
		$stmt->execute(array($dsfinvk_ustid,"dsfinvk_ustid"));
                $stmt->execute(array($cat1name,"cat1name"));
                $stmt->execute(array($cat2name,"cat2name"));
                $stmt->execute(array($prodlistname,"prodlistname"));
                $stmt->execute(array($cat1viewname,"cat1viewname"));
                $stmt->execute(array($cat2viewname,"cat2viewname"));
                $stmt->execute(array($deskviewname,"deskviewname"));
		$stmt->execute(array($paydeskid,"paydeskid"));
		
		$stmt->execute(array($companyinfo,"companyinfo"));
		$stmt->execute(array($companyinfo,"webimpressum"));
		$stmt->execute(array($defaultview,"defaultview"));
		$stmt->execute(array($stornocode,"stornocode"));
		$stmt->execute(array($printpass,"printpass"));
		if ($restaurantmode == 1) {
				$stmt->execute(array(0,"allprodstoreceipt"));
		} else {
				$stmt->execute(array(1,"allprodstoreceipt"));
		}
		if (!is_null($remoteaccesscode)) {
			$stmt->execute(array(md5($remoteaccesscode),"remoteaccesscode"));
		}
		
		$sql = "UPDATE %histconfig% H,%config% C SET H.setting=? where C.name=? and C.id=H.configid";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($restaurantmode,"restaurantmode"));
		$stmt->execute(array($dsfinvk_name,"dsfinvk_name"));
		$stmt->execute(array($dsfinvk_street,"dsfinvk_street"));
		$stmt->execute(array($dsfinvk_postalcode,"dsfinvk_postalcode"));
		$stmt->execute(array($dsfinvk_city,"dsfinvk_city"));
		$stmt->execute(array($dsfinvk_country,"dsfinvk_country"));
		$stmt->execute(array($dsfinvk_stnr,"dsfinvk_stnr"));
		$stmt->execute(array($dsfinvk_ustid,"dsfinvk_ustid"));
		$stmt->execute(array($paydeskid,"paydeskid"));
		$stmt->execute(array($companyinfo,"companyinfo"));
		$stmt->execute(array($companyinfo,"webimpressum"));
		$stmt->execute(array($defaultview,"defaultview"));
		$stmt->execute(array($stornocode,"stornocode"));
		$stmt->execute(array($printpass,"printpass"));
                
		if ($restaurantmode == 1) {
				$stmt->execute(array(0,"allprodstoreceipt"));
		} else {
				$stmt->execute(array(1,"allprodstoreceipt"));
		}

		if (!is_null($remoteaccesscode)) {
			$stmt->execute(array(md5($remoteaccesscode),"remoteaccesscode"));
		}
	}
	
	public static function loadSampleCusHtmlTemplate($pdo) {
		$file = "../customer/closingtemplate.txt";
		$content = file_get_contents($file);
		if ($content === false) {
			$content = "Keine Vorlage vordefiniert";
		}
		return $content;
	}
	public static function loadSampleProdImages($pdo) {
		$sql = "UPDATE %products% SET prodimageid=?";
		CommonUtils::execSql($pdo, $sql, array(null));
		
		$sql = "DELETE FROM %prodimages%";
		CommonUtils::execSql($pdo, $sql, null);
		
		$sql = "ALTER TABLE %prodimages% AUTO_INCREMENT = 1";
		CommonUtils::execSql($pdo, $sql, null);
		
		$file = "../customer/prodimages.txt";
		
		$handle = fopen ($file, "r");
		$sql = "INSERT INTO %prodimages% (keyname,imgh,imgm,imgl) VALUES(?,?,?,?)";
		while (!feof($handle)) {
			$textline = trim(fgets($handle));
			if ($textline != "") {
				$parts = explode(';', $textline);
				CommonUtils::execSql($pdo, $sql, array($parts[1],$parts[2],$parts[3],$parts[4]));
			}
		}
		
		fclose ($handle);
	}
	
	public function sortProdTypes($pdo) {
		$orderedTypeIds = array();
		$sql = "SELECT id,COALESCE(reference,0) as reference,removed FROM %prodtype% WHERE removed is null ORDER by id";
		$alltypes = CommonUtils::fetchSqlAll($pdo, $sql, null);
		foreach($alltypes as $aType) {
			$id = $aType["id"];
			$sql = "UPDATE %prodtype% SET sorting=? WHERE id=?";
			if (!array_key_exists($id, $orderedTypeIds)) {
				$brothersAndMe = self::getAllTypesOfSameParent($alltypes, $aType);
				$sort = 1;
				foreach($brothersAndMe as $brotherid) {
					CommonUtils::execSql($pdo, $sql, array($sort,$brotherid));
					$orderedTypeIds[] = $brotherid;
					$sort++;
				}
			}
		}
	}
	
	private static function getAllTypesOfSameParent($alltypes,$keyType) {
		$brothers = array();
		foreach($alltypes as $t) {
			if ($t['reference'] == $keyType['reference']) {
				$brothers[] = $t['id'];
			}
		}
		return $brothers;
	}
	
	public function createOrUpdateSN($pdo,$val) {
		$hist = new HistFiller();
		$hist->updateConfigInHist($pdo,'sn', $val);
	}
	public function createOrUpdateUID($pdo,$version=null) {
		$timestamp = time();
		if (is_null($version)) {
			$version = CommonUtils::getConfigValue($pdo, 'version', '0.0.0');
		}
		$r = rand(1000,9999);
		$sn = $version . '-' . $timestamp . '-' . $r;
		$hist = new HistFiller();
		$hist->updateConfigInHist($pdo,'uid', $sn);
	}
}
