<?php

class Version {
	
        public static $NO_INPROGRESS_EVENTS = false;
        public static $DO_INPROGRESS_EVENTS = true;
        
        private static function sendInProgressMsg($text,$isAppend) {
                if ($isAppend) {
                        echo "#";
                }
                echo json_encode(array("status" => "inprogress","msg" => $text));
                flush();
                ob_flush();
        }

	public static function updateVersion($pdo,$version) {
		self::insertOrUpdateConfigItem($pdo, 'version', $version);
	}

	public static function insertOrUpdateConfigItem($pdo,$item,$value) {
		$hist = new HistFiller();
		$hist->updateConfigInHist($pdo, $item, $value);
	}
	
	public static function execSql($pdo,$sql) {
	    $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
	    $stmt->execute();
	}
	
	public static function execSqlWithParam($pdo,$sql,$param) {
	    $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
	    $stmt->execute($param);
	}

	public static function insertIntRow($pdo,$table,$rowToInsert,$afterRow) {
		self::insertTypeRow($pdo, $table, $rowToInsert, $afterRow, 'INT(1) NULL');
	}
	
	public static function insertTypeRow($pdo,$table,$rowToInsert,$afterRow,$type) {
		$sql = "SHOW COLUMNS FROM $table LIKE '$rowToInsert'";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		$result = $stmt->fetchAll();
		if (count($result) == 0) {	
			$sql = "ALTER TABLE $table ADD $rowToInsert $type AFTER $afterRow";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
		}
	}

	public static function getDefaultCustomRecTemplate() {
		return file_get_contents(__DIR__. '/../../customer/rectemplate.txt');
	}
	private static function getDefaultCustomCashTemplate() {
		return file_get_contents(__DIR__. '/../../customer/cashtemplate.txt');
	}
	private static function getDefaultCustomClsTemplate() {
		return file_get_contents(__DIR__. '/../../customer/clstemplate.txt');
	}
	public static function getDefaultCustomFoodTemplate() {
		$template = file_get_contents(__DIR__. '/../../customer/generalworktemplate.txt');
                return str_replace("KATEGORIE", "Speisen", $template);
	}
	public static function getDefaultCustomDrinkTemplate() {
		$template = file_get_contents(__DIR__. '/../../customer/generalworktemplate.txt');
                return str_replace("KATEGORIE", "Getränke", $template);
	}
	private static function getDefaultCustomCancelTemplate() {
		return file_get_contents(__DIR__. '/../../customer/canceltemplate.txt');
	}
	private static function getDefaultCustomPickupTemplate() {
		return file_get_contents(__DIR__. '/../../customer/pickuptemplate.txt');
	}
        private static function getShowroomGeneralCss() {
		return file_get_contents(__DIR__. '/../../customer/showroomgeneral.css');
	}
        private static function getShowroomPrivacy() {
		return file_get_contents(__DIR__. '/../../customer/showroomprivacy.txt');
	}
        
        private static function substitutePrintFeature($pdo) {
                $sql = "SELECT setting FROM %config% WHERE name=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array('printandqueuejobs'));
		if (count($result) > 0) {
				$setting = $result[0]['setting'];
				if ($setting == 1) {
						self::insertOrUpdateConfigItem($pdo, 'printandqueuejobs', 0);
						self::insertOrUpdateConfigItem($pdo, 'digiprintwork', 1);
				}
			}
        }
	
	public static function getDefaultWorkTemplateFood() {
	    $rect = "SS:Speisen\n\ni_ID:v\nt:v\nz:v\n";
	    $rect .= "\n";
	    $rect .= "START_WORK\n";
	    $rect .= "f:-;\n";
	    $rect .= "N:v;\ns:  ;b:v;\n";
	    $rect .= "e:v\n";
	    $rect .= "END_WORK\n";
	    $rect .= "f:-";
	    return $rect;
	}
	
	public static function getDefaultWorkTemplateDrinks() {
	    $rect = "SS:Getränke\n\ni_ID:v\nt:v\nz:v\n";
	    $rect .= "\n";
	    $rect .= "START_WORK\n";
	    $rect .= "f:-;\n";
	    $rect .= "N:v;\ns:  ;b:v;\n";
	    $rect .= "e:v\n";
	    $rect .= "END_WORK\n";
	    $rect .= "f:-";
	    return $rect;
	}
	
	public static function getDefaultPickupTemplate() {
	    $rect = "S:Abholbon\n\nII_ID:v\n";
	    $rect .= "START_WORK\n";
	    $rect .= "f:-;\n";
	    $rect .= "N:v;\ns:  ;b:v;\n";
	    $rect .= "e:v\n";
	    $rect .= "END_WORK\n";
	    $rect .= "f:-";
	    return $rect;
	}
	
	public static function genSampleHostText() {
		$hosttext = "\n\nAngaben zum Nachweis der Höhe\nund der betrieblichen\nVeranlassung von\nBewirtungsaufwendungen\n(Par. 4 Abs. 5 Ziff. 2 EStG)\n\n";
		$hosttext .= "Tag der Bewirtung:\n\n\n";
		$hosttext .=  "Ort der Bewirtung:\n\n\n";
		$hosttext .=  "Bewirtete Person(en):\n\n\n\n\n\n";
		$hosttext .=  "Anlass der Bewirtung:\n\n\n\n\n\n\n";
		$hosttext .=  "Ort, Datum        Unterschrift\n\n";
		return $hosttext;
	}

	public static function getDefaultCancelWorkTemplate() {
		$rect = "SS: Stornierung\n\n";
		$rect .= "s:zu stornieren ;n:v\n";
		$rect .= "s:  ID: ;i:v\n";
		$rect .= "s:  Tisch: ;t:v\n";
		$rect .= "s:  Zeit: ;z:v\n";
		$rect .= "s:  Extras: ;e:v\n";
		$rect .= "s:  Preis: ;p:v\n";
		$rect .= "s:  Typ: ;k:v\n";
		$rect .= "s:  zu storn. Arb.bon: ;q:v\n";
		return $rect;
	}
	
	public static function getDefaultClosTemplate($pdo) {
		$r = Basedb::loadSampleCusHtmlTemplate($pdo);
		return $r;
	}
	
	private static function updateNettoValuesOfBill($pdo) {
		$sql = "SELECT %bill%.id as billid,IF(status='s',-1,1)*ROUND(SUM(price/(1 + %queue%.tax/100.0)),6) as netto FROM %queue%,%billproducts%,%bill% WHERE %billproducts%.billid=%bill%.id AND %billproducts%.queueid=%queue%.id AND (status is null OR status=? OR status=?) GROUP by billid";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array('x','s'));
		$result = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		$sql = "UPDATE %bill% SET netto=? WHERE id=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		foreach($result as $r) {
			$stmt->execute(array($r["netto"],$r["billid"]));
		}
	}
	
	private static function addHistClsReference($pdo) {
		$sql = "UPDATE %hist% SET clsid=(SELECT(SELECT MIN(id) FROM %closing% WHERE `date` < closingdate) AS clsidval)";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
	}
	
        private static function getLang($pdo,$prefix) {
                $lang = 0;
                $sql = "SELECT setting FROM %config% WHERE name=?";
                $stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
                $stmt->execute(array('billlanguage'));
                $r = $stmt->fetchAll(PDO::FETCH_ASSOC);
                if (count($r) > 0) {
                        $lang = intval($r[0]["setting"]);
                }
                return $lang;
        }
        
        public static function createTablesAndUpdateUntilVersion($pdo,$basedb,$version) {
		$basedb->dropTables($pdo);
		
		$basedb->createAndIntializeTables($pdo,'.',0,'Euro',  DbUtils::getTimeZone(),false);
		
		self::runUpdateProcess($pdo, TAB_PREFIX, MYSQL_DB, $version,false,Version::$NO_INPROGRESS_EVENTS);
	}
        
	public static function completeImportProcess($pdo) {
		$ok = self::runUpdateProcess($pdo, TAB_PREFIX, MYSQL_DB, null,false,self::$NO_INPROGRESS_EVENTS);
		if ($ok["status"] != "OK") {
			echo json_encode($ok);
			return;
		}

		$sql = "SELECT name FROM %config% WHERE name=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array('timezone'));
		if (count($result) == 0) {
			$timezone = DbUtils::getTimeZone();
                        self::insertOrUpdateConfigItem($pdo, 'timezone', $timezone);
		}
		
		echo json_encode(array("status" => "OK"));
                $_SESSION = array();
	}
	
	public static function upd_1300_1301($pdo, $prefix, $dbname) {
		try {
			$basedb = new BaseDb(); $basedb->createCustomerLogTable($pdo);

			self::insertOrUpdateConfigItem($pdo, 'cancelguestcode', '');

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1301_1302($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'returntoorder', '1');

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1302_1303($pdo, $prefix, $dbname) {
		try {

			$sql = "SHOW COLUMNS FROM %customers% LIKE 'hello'";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			$result = $stmt->fetchAll();
			if (count($result) == 0) {
				self::execSql($pdo, "ALTER TABLE %customers% ADD hello VARCHAR(100) NULL AFTER www");
				self::execSql($pdo, "ALTER TABLE %customers% ADD regards VARCHAR(100) NULL AFTER hello");
				self::execSql($pdo, "OPTIMIZE TABLE %customers%");
			}

			self::insertOrUpdateConfigItem($pdo, 'rksvserver', '');

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1303_1304($pdo, $prefix, $dbname) {
		try {
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1304_1305($pdo, $prefix, $dbname) {
		try {
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1305_1306($pdo, $prefix, $dbname) {
		try {
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1306_1307($pdo, $prefix, $dbname) {
		try {

			self::insertOrUpdateConfigItem($pdo, 'updateurl', 'http://www.ordersprinter.de/update');

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1307_1308($pdo, $prefix, $dbname) {
		try {

			$sql = "SHOW COLUMNS FROM %user% LIKE 'mobiletheme'";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			$result = $stmt->fetchAll();
			if (count($result) == 0) {
				$sql = "ALTER TABLE %user% ADD mobiletheme INT(2) NULL AFTER language";
				$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
				$stmt->execute();
			}

			self::insertOrUpdateConfigItem($pdo, 'discountname1', '');
			self::insertOrUpdateConfigItem($pdo, 'discountname2', '');
			self::insertOrUpdateConfigItem($pdo, 'discountname3', '');

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1308_1309($pdo, $prefix, $dbname) {
		try {

			self::execSql($pdo, "ALTER TABLE %products% ADD unit INT(2) NULL AFTER priceC");
			self::execSql($pdo, "ALTER TABLE %histprod% ADD unit INT(2) NULL AFTER priceC");


			self::execSql($pdo, "ALTER TABLE %products% ADD days VARCHAR(20) NULL AFTER unit");
			self::execSql($pdo, "ALTER TABLE %histprod% ADD days VARCHAR(20) NULL AFTER unit");

			self::execSql($pdo, "ALTER TABLE %user% ADD failedlogins VARCHAR(20) NULL AFTER extrasapplybtnpos");

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1309_1310($pdo, $prefix, $dbname) {
		try {

			$sql = "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL,?,?)";
			$stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
			$stmt->execute(array('closshowci', '1'));
			$stmt->execute(array('closshowpaytaxes', '1'));
			$stmt->execute(array('closshowprods', '1'));

			$sql = "SELECT setting FROM %config% WHERE name=?";
			$stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
			$stmt->execute(array("paymentconfig"));
			$row = $stmt->fetchObject();
			$paymentconfig = $row->setting;
			$sql = "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL,?,?)";
			$stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
			if ($paymentconfig == 0) {
				$stmt->execute(array('showpayment2', '1'));
				$stmt->execute(array('showpayment3', '1'));
				$stmt->execute(array('showpayment4', '1'));
				$stmt->execute(array('showpayment5', '1'));
				$stmt->execute(array('showpayment6', '1'));
				$stmt->execute(array('showpayment7', '1'));
				$stmt->execute(array('showpayment8', '1'));
			} else {
				$stmt->execute(array('showpayment2', '1'));
				$stmt->execute(array('showpayment3', '0'));
				$stmt->execute(array('showpayment4', '0'));
				$stmt->execute(array('showpayment5', '0'));
				$stmt->execute(array('showpayment6', '0'));
				$stmt->execute(array('showpayment7', '0'));
				$stmt->execute(array('showpayment8', '0'));
			}

			self::execSql($pdo, "ALTER TABLE %extras% ADD sorting INT(2) NULL AFTER price");

			$sql = "SELECT id FROM %extras% WHERE removed is null";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			$result = $stmt->fetchAll();
			$pos = 1;
			$sql = "UPDATE %extras% SET sorting=? WHERE id=?";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			foreach ($result as $extraid) {
				$stmt->execute(array($pos, $extraid["id"]));
				$pos++;
			}

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1310_1311($pdo, $prefix, $dbname) {
		try {
			$hosttext = self::genSampleHostText();
			self::insertOrUpdateConfigItem($pdo, 'hosttext', $hosttext);

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1311_1312($pdo, $prefix, $dbname) {
		try {
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1312_1313($pdo, $prefix, $dbname) {
		try {
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1313_1314($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'k1prinstance', '1');
			self::insertOrUpdateConfigItem($pdo, 'k2prinstance', '1');
			self::insertOrUpdateConfigItem($pdo, 'k3prinstance', '1');
			self::insertOrUpdateConfigItem($pdo, 'k4prinstance', '1');
			self::insertOrUpdateConfigItem($pdo, 'k5prinstance', '1');
			self::insertOrUpdateConfigItem($pdo, 'k6prinstance', '1');
			self::insertOrUpdateConfigItem($pdo, 'd1prinstance', '1');
			self::insertOrUpdateConfigItem($pdo, 'd2prinstance', '1');
			self::insertOrUpdateConfigItem($pdo, 'f1prinstance', '1');
			self::insertOrUpdateConfigItem($pdo, 'f2prinstance', '1');
			
			self::insertOrUpdateConfigItem($pdo, 'dashslot1', '1');
			self::insertOrUpdateConfigItem($pdo, 'dashslot2', '2');
			self::insertOrUpdateConfigItem($pdo, 'dashslot3', '3');
			
			
			$sql = "SHOW COLUMNS FROM %user% LIKE 'right_dash'";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			$result = $stmt->fetchAll();
			if (count($result) == 0) {
				self::execSql($pdo, "ALTER TABLE %user% ADD right_dash INT(2) NULL AFTER right_closing");
				self::execSql($pdo, "ALTER TABLE %histuser% ADD right_dash INT(2) NULL AFTER right_closing");

				self::execSqlWithParam($pdo, "UPDATE %user% SET right_dash=?", array(0));
				self::execSqlWithParam($pdo, "UPDATE %histuser% SET right_dash=?", array(0));

				self::execSqlWithParam($pdo, "UPDATE %user% SET right_dash=? WHERE right_manager=? OR is_admin=?", array(1, 1, 1));
				self::execSqlWithParam($pdo, "UPDATE %histuser% SET right_dash=? WHERE right_manager=? OR is_admin=?", array(1, 1, 1));

				self::execSql($pdo, "OPTIMIZE TABLE %user%");
				self::execSql($pdo, "OPTIMIZE TABLE %histuser%");
			}

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1314_1315($pdo, $prefix, $dbname) {
		try {
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1315_1316($pdo, $prefix, $dbname) {
		try {
			$sql = "UPDATE %config% SET name=? WHERE name=?";
			$stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
			$stmt->execute(array('groupworkitemsf', 'groupworkitems'));

			$sql = "SELECT setting FROM %config% WHERE name=?";
			$stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
			$stmt->execute(array('groupworkitemsf'));
			$row = $stmt->fetchObject();
			$groupworkitemsf = $row->setting;

			$sql = "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL,?,?)";
			$stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
			$stmt->execute(array('groupworkitemsd', $groupworkitemsf));

			$sql = "UPDATE %config% SET name=? WHERE name=?";
			$stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
			$stmt->execute(array('oneprodworkrecf', 'oneprodworkreceipts'));

			$sql = "SELECT setting FROM %config% WHERE name=?";
			$stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
			$stmt->execute(array('oneprodworkrecf'));
			$row = $stmt->fetchObject();
			$oneprodworkrecf = $row->setting;

			$sql = "INSERT INTO `%config%` (`id` , `name`, `setting`) VALUES (NULL,?,?)";
			$stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
			$stmt->execute(array('oneprodworkrecd', $oneprodworkrecf));

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1316_1317($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'dblog', '1');

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1317_1318($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'startprodsearch', '3');

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1318_1319($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'priceinlist', '0');

			$basedb = new BaseDb(); $basedb->createProdimagesTable($pdo);

			$sql = "SHOW COLUMNS FROM %products% LIKE 'prodimageid'";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			$result = $stmt->fetchAll();
			if (count($result) == 0) {
				self::execSql($pdo, "ALTER TABLE %products% ADD prodimageid INT(10) NULL AFTER audio");
				self::execSql($pdo, "OPTIMIZE TABLE %products%");

				self::execSql($pdo, "ALTER TABLE %histprod% ADD prodimageid INT(10) NULL AFTER audio");
				self::execSql($pdo, "OPTIMIZE TABLE %histprod%");
			}

			$sql = "SHOW COLUMNS FROM %user% LIKE 'preferimgdesk'";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			$result = $stmt->fetchAll();
			if (count($result) == 0) {
				$sql = "ALTER TABLE %user% ADD preferimgdesk INT(1) NULL AFTER prefertablemap";
				$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
				$stmt->execute();
				$sql = "ALTER TABLE %user% ADD preferimgmobile INT(1) NULL AFTER preferimgdesk";
				$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
				$stmt->execute();
			}

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1319_1320($pdo, $prefix, $dbname) {
		try {
			

			$sql = "SHOW COLUMNS FROM %user% LIKE 'showplusminus'";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			$result = $stmt->fetchAll();
			if (count($result) == 0) {
				$sql = "ALTER TABLE %user% ADD showplusminus INT(1) NULL AFTER preferimgmobile";
				$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
				$stmt->execute();

				$sql = "UPDATE %user% SET showplusminus=?";
				$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
				$stmt->execute(array(1));
			}

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1320_1321($pdo, $prefix, $dbname) {
		try {
			
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1321_1322($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'tmpdir', '');
			self::insertOrUpdateConfigItem($pdo, 'ftphost', '');
			self::insertOrUpdateConfigItem($pdo, 'ftpuser', '');
			self::insertOrUpdateConfigItem($pdo, 'ftppass', '');
			
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1322_1323($pdo, $prefix, $dbname) {
		try {
			

			self::insertIntRow($pdo, "%printjobs%", "removed", "printer");
			self::insertIntRow($pdo, "%queue%", "printjobid", "workprinted");

			$cancelTemplate = self::getDefaultCancelWorkTemplate();
			self::insertOrUpdateConfigItem($pdo, 'canceltemplate', $cancelTemplate);

			$sql = "SHOW COLUMNS FROM %user% LIKE 'right_waiter'";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			$result = $stmt->fetchAll();
			if (count($result) > 0) {

				$basedb = new BaseDb(); $basedb->createRolesTable($pdo);
				try {
					$sql = "DELETE FROM %roles%";
					$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
					$stmt->execute();
				} catch (Exception $ex) {
				}

				$sql = "ALTER TABLE %user% ADD roleid INT (10) NULL AFTER active";
				$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
				$stmt->execute();

				$existingRights = array(
				    "is_admin",
				    "right_waiter",
				    "right_kitchen",
				    "right_bar",
				    "right_supply",
				    "right_paydesk",
				    "right_statistics",
				    "right_bill",
				    "right_products",
				    "right_manager",
				    "right_closing",
				    "right_dash",
				    "right_reservation",
				    "right_rating",
				    "right_changeprice",
				    "right_customers"
				);

				$rightInStr = implode(",", $existingRights);
				$sql = "SELECT DISTINCT $rightInStr FROM %user% WHERE active='1'";
				$stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
				$stmt->execute();
				$allDistinctPermutations = $stmt->fetchAll(PDO::FETCH_ASSOC);

				$i = 1;
				foreach ($allDistinctPermutations as $aPerm) {

					$addOnToName = "";
					if ($aPerm["is_admin"] == 1) {
						$addOnToName = " (Admin)";
					} else if ($aPerm["right_manager"] == 1) {
						$addOnToName = " (Verwaltung)";
					}

					$sql = "INSERT INTO %roles% (name,$rightInStr) VALUES('Rolle $i $addOnToName',?,?,?,?,? ,?,?,?,?,? , ?,?,?,?,?,  ?)";
					$params = array(
					    $aPerm["is_admin"],
					    $aPerm["right_waiter"],
					    $aPerm["right_kitchen"],
					    $aPerm["right_bar"],
					    $aPerm["right_supply"],
					    $aPerm["right_paydesk"],
					    $aPerm["right_statistics"],
					    $aPerm["right_bill"],
					    $aPerm["right_products"],
					    $aPerm["right_manager"],
					    $aPerm["right_closing"],
					    $aPerm["right_dash"],
					    $aPerm["right_reservation"],
					    $aPerm["right_rating"],
					    $aPerm["right_changeprice"],
					    $aPerm["right_customers"]
					);

					$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
					$stmt->execute($params);

					$newroleid = $pdo->lastInsertId();

					$where = "is_admin=? AND ";
					$where .= "right_waiter=? AND ";
					$where .= "right_kitchen=? AND ";
					$where .= "right_bar=? AND ";
					$where .= "right_supply=? AND ";
					$where .= "right_paydesk=? AND ";
					$where .= "right_statistics=? AND ";
					$where .= "right_bill=? AND ";
					$where .= "right_products=? AND ";
					$where .= "right_manager=? AND ";
					$where .= "right_closing=? AND ";
					$where .= "right_dash=? AND ";
					$where .= "right_reservation=? AND ";
					$where .= "right_rating=? AND ";
					$where .= "right_changeprice=? AND ";
					$where .= "right_customers=?";

					$sql = "SELECT id FROM %user% WHERE $where AND active=1";
					$stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
					$stmt->execute($params);
					$allUserIdsOfThatRole = $stmt->fetchAll(PDO::FETCH_ASSOC);

					foreach ($allUserIdsOfThatRole as $u) {
						$sql = "UPDATE %user% SET roleid=? WHERE id=?";
						$stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
						$stmt->execute(array($newroleid, $u["id"]));
					}

					$i++;
				}

				foreach ($existingRights as $r) {
					$sql = "ALTER TABLE %user% DROP COLUMN " . $r;
					$stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
					$stmt->execute();
				}

				$sql = "UPDATE %user% SET roleid=? WHERE active='0'";
				$stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
				$stmt->execute(array(null));

				$sql = "OPTIMIZE TABLE %user%";
				$stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
				$stmt->execute();
			}

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1323_1324($pdo, $prefix, $dbname) {
		try {
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1324_1325($pdo, $prefix, $dbname) {
		try {
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1325_1326($pdo, $prefix, $dbname) {
		return array(true);
	}

	public static function upd_1326_1400($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'guestjobprint', '1');

			$sql = "SHOW COLUMNS FROM %products% LIKE 'display'";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			$result = $stmt->fetchAll();
			if (count($result) == 0) {
				self::execSql($pdo, "ALTER TABLE %products% ADD display VARCHAR(3) NULL AFTER prodimageid");
				self::execSql($pdo, "OPTIMIZE TABLE %products%");

				self::execSql($pdo, "ALTER TABLE %histprod% ADD display VARCHAR(3) NULL AFTER prodimageid");
				self::execSql($pdo, "OPTIMIZE TABLE %histprod%");
			}

			self::insertOrUpdateConfigItem($pdo, 'guesturl', '');
			self::insertOrUpdateConfigItem($pdo, 'guestcode', '');
			self::insertOrUpdateConfigItem($pdo, 'dailycode', '');

			self::insertIntRow($pdo, "%user%", "preferfixbtns", "preferimgmobile");

			$sql = "ALTER TABLE %queue% MODIFY orderuser INT(10) NULL";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();

			self::insertTypeRow($pdo, '%resttables%', 'code', 'roomid', 'VARCHAR ( 200 ) NULL');
			self::insertTypeRow($pdo, '%resttables%', 'name', 'code', 'VARCHAR ( 50 ) NULL');
			self::insertTypeRow($pdo, '%resttables%', 'active', 'name', 'INT(1) NULL');
			self::insertTypeRow($pdo, '%resttables%', 'allowoutorder', 'active', 'INT(1) NULL');

			$sql = "UPDATE %resttables% SET name=tableno";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			$basedb = new BaseDb(); $basedb->initTableOrder($pdo);
			$basedb = new BaseDb(); $basedb->initRoomOrder($pdo);

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1400_1401($pdo, $prefix, $dbname) {
		try {

			$sql = "ALTER TABLE %bill% MODIFY netto DECIMAL (17,6) NULL";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();

			self::updateNettoValuesOfBill($pdo);

			$sql = "OPTIMIZE TABLE %bill%";
			$stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
			$stmt->execute();

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1401_1402($pdo, $prefix, $dbname) {
		return array(true);
	}

	public static function upd_1402_1403($pdo, $prefix, $dbname) {
		try {
			$sql = "ALTER TABLE %queue% ADD INDEX tqueue (tablenr)";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			$sql = "ALTER TABLE %queue% ADD INDEX pqueue (productid)";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			$sql = "ALTER TABLE %queue% ADD INDEX bqueue (billid)";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1403_1404($pdo, $prefix, $dbname) {
		return array(true);
	}

	public static function upd_1404_1405($pdo, $prefix, $dbname) {
		return array(true);
	}

	public static function upd_1405_1406($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'askdaycode', '1');
			self::insertOrUpdateConfigItem($pdo, 'asktablecode', '1');
			self::insertOrUpdateConfigItem($pdo, 'guesttimeout', '5');
			self::insertOrUpdateConfigItem($pdo, 'showdaycode', '0');
			
			$sql = "ALTER TABLE %products% ADD INDEX pcatindex (category)";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			$sql = "OPTIMIZE TABLE %products%";
			$stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
			$stmt->execute();

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}

	public static function upd_1406_1407($pdo, $prefix, $dbname) {
		try {
			$basedb = new BaseDb(); $basedb->sortProdTypes($pdo);

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1407_1408($pdo, $prefix, $dbname) {
		try {
			$basedb = new BaseDb(); $basedb->sortProdTypes($pdo);
			$basedb->createRecordsTable($pdo);
			$basedb->createRecordsQueueTable($pdo);
			
			$sql = "ALTER TABLE %user% ADD tablesaftersend INT(1) NULL AFTER keeptypelevel";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			$sql = "UPDATE %user% SET tablesaftersend=?";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array(1));

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1408_1409($pdo, $prefix, $dbname) {
		return array(true);
	}
	
	public static function upd_1409_1410($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_1410_1411($pdo, $prefix, $dbname) {
		return array(true);	
	}
	public static function upd_1411_1412($pdo, $prefix, $dbname) {
		return array(true);	
	}
	public static function upd_1412_1413($pdo, $prefix, $dbname) {
		return array(true);	
	}
	public static function upd_1413_1414($pdo, $prefix, $dbname) {
		return array(true);	
	}
	public static function upd_1414_1415($pdo, $prefix, $dbname) {
		return array(true);	
	}
	public static function upd_1415_1416($pdo, $prefix, $dbname) {
		return array(true);	
	}
	public static function upd_1416_1417($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'forceprint', '0');
			self::insertOrUpdateConfigItem($pdo, 'printextras', '0');
			
			$sql = "ALTER TABLE %queueextras% ADD INDEX tqueueextras (queueid)";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			
			$sql = "ALTER TABLE %bill% ADD printextras INT(1) NULL AFTER intguestpaid";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	public static function upd_1417_1418($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'beepordered', '0');
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1418_1500($pdo, $prefix, $dbname) {
		try {
			self::execSql($pdo, "ALTER TABLE %roles% ADD right_timetracking INT(1) NULL AFTER right_dash");
			self::execSql($pdo, "ALTER TABLE %roles% ADD right_timemanager INT(1) NULL AFTER right_timetracking");
			self::execSql($pdo, "ALTER TABLE %histuser% ADD right_timetracking INT(1) NULL AFTER right_dash");
			self::execSql($pdo, "ALTER TABLE %histuser% ADD right_timemanager INT(1) NULL AFTER right_timetracking");

			self::execSqlWithParam($pdo, "UPDATE %roles% SET right_timetracking=?,right_timemanager=?", array(1,0));
			self::execSqlWithParam($pdo, "UPDATE %histuser% SET right_timetracking=?,right_timemanager=?", array(1,0));
			self::execSqlWithParam($pdo, "UPDATE %roles% SET right_timemanager=? WHERE right_manager=? OR is_admin=?", array(1, 1, 1));
			self::execSqlWithParam($pdo, "UPDATE %histuser% SET right_timemanager=? WHERE right_manager=? OR is_admin=?", array(1, 1, 1));

			self::execSql($pdo, "OPTIMIZE TABLE %roles%");
			self::execSql($pdo, "OPTIMIZE TABLE %histuser%");
			
			$basedb = new BaseDb(); $basedb->createTimesTable($pdo);
			self::insertOrUpdateConfigItem($pdo, 'minbeforecome', '0');
			self::insertOrUpdateConfigItem($pdo, 'minaftergo', '0');
			self::execSql($pdo, "ALTER TABLE %config% MODIFY name VARCHAR(30) NULL");
			$sql = "ALTER TABLE %config% ADD INDEX tconfig (name)";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			
			self::execSql($pdo, "ALTER TABLE %prodimages% ADD keyname VARCHAR(30) NULL AFTER id");
			self::execSql($pdo, "UPDATE %prodimages% set keyname=CONCAT('Bild_',id)");
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1500_1501($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'defaultview', '0');
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1501_1502($pdo, $prefix, $dbname) {
		try {
			self::execSql($pdo, "ALTER TABLE %roles% ADD right_tasks INT(1) NULL AFTER right_timetracking");
			self::execSql($pdo, "ALTER TABLE %roles% ADD right_tasksmanagement INT(1) NULL AFTER right_tasks");
			self::execSql($pdo, "ALTER TABLE %histuser% ADD right_tasks INT(1) NULL AFTER right_timetracking");
			self::execSql($pdo, "ALTER TABLE %histuser% ADD right_tasksmanagement INT(1) NULL AFTER right_tasks");

			self::execSqlWithParam($pdo, "UPDATE %roles% SET right_tasks=?,right_tasksmanagement=?", array(0,0));
			self::execSqlWithParam($pdo, "UPDATE %histuser% SET right_tasks=?,right_tasksmanagement=?", array(0,0));
			self::execSqlWithParam($pdo, "UPDATE %roles% SET right_tasksmanagement=? WHERE right_manager=? OR is_admin=?", array(1, 1, 1));
			self::execSqlWithParam($pdo, "UPDATE %histuser% SET right_tasksmanagement=? WHERE right_manager=? OR is_admin=?", array(1, 1, 1));

			self::execSql($pdo, "OPTIMIZE TABLE %roles%");
			self::execSql($pdo, "OPTIMIZE TABLE %histuser%");

			$basedb = new BaseDb(); $basedb->createTasksTable($pdo);
			$basedb = new BaseDb(); $basedb->createTaskHistTable($pdo);
			
			self::execSql($pdo, "ALTER TABLE %resttables% ADD area INT(1) NULL AFTER name");
			self::execSql($pdo, "OPTIMIZE TABLE %resttables%");
			self::execSql($pdo, "ALTER TABLE %user% ADD area INT(1) NULL AFTER roleid");
			self::execSql($pdo, "OPTIMIZE TABLE %user%");
			self::execSql($pdo, "ALTER TABLE %histuser% ADD area INT(1) NULL AFTER right_customers");
			self::execSql($pdo, "OPTIMIZE TABLE %histuser%");
			
			self::insertOrUpdateConfigItem($pdo, 'taskallassign', '0');
			self::insertOrUpdateConfigItem($pdo, 'taskifempty', 0);
			self::insertOrUpdateConfigItem($pdo, 'taskownerempty', 0);

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1502_1503($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'showtogo', '1');
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1503_1504($pdo, $prefix, $dbname) {
		try {
			$sql = "ALTER TABLE %printjobs% ADD INDEX tprintjobs (type,removed,printer)";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			self::insertOrUpdateConfigItem($pdo, 'showprepinwaiter', '1');
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1504_1505($pdo, $prefix, $dbname) {
		return array(true);
	}
	
	public static function upd_1505_1506($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'pollbills', '2');
			self::insertOrUpdateConfigItem($pdo, 'pollworksf', '2');
			self::insertOrUpdateConfigItem($pdo, 'pollworksd', '2');
			self::insertOrUpdateConfigItem($pdo, 'pollclosings', '2');
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1506_1507($pdo, $prefix, $dbname) {
		try {
			$closTemplate = self::getDefaultClosTemplate($pdo);
			self::insertOrUpdateConfigItem($pdo, 'clostemplate', $closTemplate);
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1507_1508($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'printpickups', 0);
			self::insertOrUpdateConfigItem($pdo, 'pollpickups', 2);
			$pickuptemplate = self::getDefaultPickupTemplate();
			self::insertOrUpdateConfigItem($pdo, 'pickuptemplate', $pickuptemplate);
			self::insertOrUpdateConfigItem($pdo, 'p1prinstance', 1);
			self::insertOrUpdateConfigItem($pdo, 'showpickupsno', 20);
			
			self::execSql($pdo, "ALTER TABLE %roles% ADD right_pickups INT(1) NULL AFTER right_customers");
			self::execSql($pdo, "ALTER TABLE %histuser% ADD right_pickups INT(1) NULL AFTER right_customers");

			self::execSqlWithParam($pdo, "UPDATE %roles% SET right_pickups=?", array(0));
			self::execSqlWithParam($pdo, "UPDATE %histuser% SET right_pickups=?", array(0));

			self::execSql($pdo, "OPTIMIZE TABLE %roles%");
			self::execSql($pdo, "OPTIMIZE TABLE %histuser%");
			self::execSql($pdo, "ALTER TABLE %printjobs% ADD pickready INT(1) NULL AFTER removed");
			
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1508_1509($pdo, $prefix, $dbname) {
		return array(true);
	}
	
	public static function upd_1509_1510($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'togoworkprinter', 0);
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1510_1511($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'showhostprint', 1);
			self::insertOrUpdateConfigItem($pdo, 'oneclickcooked', 0);
			self::insertOrUpdateConfigItem($pdo, 'showpickupdelbtn', 1);
			self::insertOrUpdateConfigItem($pdo, 'showpickhelp', 1);
			self::insertOrUpdateConfigItem($pdo, 'showpayments', 1);
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1511_1512($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'cbirdfolder', '');
			self::execSql($pdo, "ALTER TABLE %user% ADD calcpref INT(2) NULL AFTER extrasapplybtnpos");
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1512_1513($pdo, $prefix, $dbname) {
		try {
			self::execSql($pdo, "ALTER TABLE %prodtype% ADD fixbind INT(1) NULL AFTER printer");
			self::execSql($pdo, "UPDATE %prodtype% SET fixbind='0'");
			
			self::insertOrUpdateConfigItem($pdo, 'd3prinstance', '1');
			self::insertOrUpdateConfigItem($pdo, 'd4prinstance', '1');
			self::insertOrUpdateConfigItem($pdo, 'f3prinstance', '1');
			self::insertOrUpdateConfigItem($pdo, 'f4prinstance', '1');
			
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1513_1514($pdo, $prefix, $dbname) {
		try {
			$sql = "UPDATE %work% SET signature=? WHERE item=?";
			self::execSqlWithParam($pdo, $sql, array("","privkey"));
			self::execSqlWithParam($pdo, $sql, array("","cert"));
			self::execSqlWithParam($pdo, $sql, array("","lastbillid"));
			self::execSql($pdo, "UPDATE %work% SET signature=''");
			self::execSql($pdo, "ALTER TABLE %work% MODIFY signature VARCHAR(50) NULL");
			CommonUtils::setMd5OfLastBillidInWorkTable($pdo);
			self::execSql($pdo, "UPDATE %bill% SET signature=''");
			self::execSql($pdo, "ALTER TABLE %bill% MODIFY signature VARCHAR(50) NULL");
			CommonUtils::calcSignaturesForAllBills($pdo);
			self::execSql($pdo, "UPDATE %closing% SET signature=''");
			self::execSql($pdo, "ALTER TABLE %closing% MODIFY signature VARCHAR(50) NULL");
			Closing::signAllClosings($pdo);
			
			self::insertOrUpdateConfigItem($pdo, 'billprintjobs', '2');
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1514_1515($pdo, $prefix, $dbname) {
		return array(true);
	}
	
	public static function upd_1515_1516($pdo, $prefix, $dbname) {
		return array(true);
	}
	
	public static function upd_1516_1517($pdo, $prefix, $dbname) {
		return array(true);
	}
	
	public static function upd_1517_1518($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'payprinttype', 's');

			self::execSql($pdo, "ALTER TABLE %products% ADD barcode VARCHAR(25) NULL AFTER priceC");
			self::execSql($pdo, "ALTER TABLE %histprod% ADD barcode VARCHAR(25) NULL AFTER priceC");
			self::insertOrUpdateConfigItem($pdo, 'usebarcode', '0');
			
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1518_1519($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'sumupforcard', '0');
			self::insertOrUpdateConfigItem($pdo, 'affiliatekey', '');
			self::insertOrUpdateConfigItem($pdo, 'appid', '');
			self::insertOrUpdateConfigItem($pdo, 'sumupfailuretext', 'Fehlgeschlagene Kartenzahlung');

			$sql = "UPDATE `%payment%` SET name=?, name_en=?, name_esp=? WHERE id=?";
			self::execSqlWithParam($pdo, $sql, array("Kartenzahlung","Card Payment","Pago con tarjeta",2));
			
			$sql = "UPDATE `%bill%` SET paymentid=? WHERE paymentid=?";
			self::execSqlWithParam($pdo, $sql, array(2,3));
			
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1519_1520($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'printcash', '0');
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1520_1521($pdo, $prefix, $dbname) {
		try {
			$sql = "UPDATE %queue% SET toremove=?";
			self::execSqlWithParam($pdo, $sql, array(0));
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1521_1522($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'showerrorlog', '1');
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1522_1523($pdo, $prefix, $dbname) {
		try {
			self::execSql($pdo, "ALTER TABLE %queue% ADD clsid INT(1) NULL AFTER isclosed");
			self::execSql($pdo, "ALTER TABLE %queue% ADD FOREIGN KEY(clsid) REFERENCES %closing% (id)");
			
			$sql = "SELECT max(id) as maxid FROM %queue%";
			$stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
			$stmt->execute();
			$r = $stmt->fetchAll(PDO::FETCH_ASSOC);
			$maxid = 0;
			if (count($r) > 0) {
				$maxid = $r[0]["maxid"];
				if (is_null($maxid)) {
					$maxid = 0;
				}
			}
			$sql = "INSERT INTO %work% (item,value) VALUES(?,?)";
			$stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
			$stmt->execute(array("indexunclosedqueue",$maxid));
			
			self::execSql($pdo, "ALTER TABLE %work% MODIFY value VARCHAR(100) NULL");
			
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1523_1524($pdo, $prefix, $dbname) {
		try {
			self::execSql($pdo, "ALTER TABLE %queue% ADD unit INT(1) NULL AFTER price");
			self::execSql($pdo, "ALTER TABLE %queue% ADD unitamount decimal(15,2) NULL AFTER unit");
			self::execSql($pdo, "UPDATE %queue% SET unitamount='1'");
			$sql = "UPDATE %queue% SET unit=(SELECT COALESCE(unit,0) FROM %products% P WHERE productid=P.id)";
			self::execSql($pdo, $sql);
			self::execSql($pdo, "OPTIMIZE TABLE %queue%");
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1524_1525($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'showtransferbtns', '1');
			
			self::execSql($pdo, "ALTER TABLE %reservations% ADD tableid INT(10) NULL AFTER phone");
			$sql = "ALTER TABLE %reservations% ADD INDEX tresdate (scheduledate)";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			
			$sql = "ALTER TABLE %logo% MODIFY setting MEDIUMBLOB NULL";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
			
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1525_1526($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'logolocation', '1');
			self::execSql($pdo, "ALTER TABLE %queueextras% ADD amount INT(3) NULL AFTER extraid");
			self::execSql($pdo, "ALTER TABLE %extras% ADD maxamount INT(3) NULL AFTER price");
			self::execSql($pdo, "UPDATE %extras% SET maxamount='1'");
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1526_1527($pdo, $prefix, $dbname) {
		try {
			self::execSql($pdo, "UPDATE %queueextras% SET amount='1' where amount is null");
			
			self::insertOrUpdateConfigItem($pdo, 'doublereceipt', '0');
			
			$sql = "UPDATE `%payment%` SET name=?, name_en=?, name_esp=? WHERE id=?";
			self::execSqlWithParam($pdo, $sql, array('EC-Kartenzahlung','Electr. purse (EC)','Pago con tarjeta EC',2));
			self::insertOrUpdateConfigItem($pdo, 'austriabind', '0');
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1527_1528($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'printextraprice', '1');
			
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1528_1529($pdo, $prefix, $dbname) {
		try {
			self::execSql($pdo, "ALTER TABLE %customerlog% ADD clsid INT(1) NULL AFTER `userid`");
			self::execSql($pdo, "ALTER TABLE %customerlog% ADD FOREIGN KEY(clsid) REFERENCES %closing% (id)");
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1529_1530($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'turbo', '5');
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1530_1531($pdo, $prefix, $dbname) {
		return array(true);
	}
	
	public static function upd_1531_1532($pdo, $prefix, $dbname) {
		return array(true);
	}
	
	public static function upd_1532_1533($pdo, $prefix, $dbname) {
		return array(true);
	}
	
	public static function upd_1533_1600($pdo, $prefix, $dbname) {
		try {
			self::execSql($pdo, "ALTER TABLE %reservations% ADD starttimemin INT(3) NULL AFTER `starttime`");
			self::execSql($pdo, "UPDATE %reservations% SET starttimemin='0'");
			self::execSql($pdo, "ALTER TABLE %reservations% ADD durationmins INT(3) NULL AFTER `duration`");
			self::execSql($pdo, "UPDATE %reservations% SET durationmins='0'");
			self::insertOrUpdateConfigItem($pdo, 'guestqrtext', 'Gastbestellung');
			self::insertOrUpdateConfigItem($pdo, 'guestqrfontsize', '15');
			self::insertOrUpdateConfigItem($pdo, 'guestqrsize', '150');
			self::insertOrUpdateConfigItem($pdo, 'reservationitem', "<b>{Name}</b>\n{Start-Stunde}:{Start-Minute}-{Ende-Stunde}:{Ende-Minute}\nDauer: {Dauer-Stunden}:{Dauer-Minuten}\n{Personen} Personen\n{Bemerkung}");
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1600_1601($pdo, $prefix, $dbname) {
		return array(true);
	}
	
	public static function upd_1601_1602($pdo, $prefix, $dbname) {
		try {
			self::execSql($pdo, "ALTER TABLE %user% ADD quickcash INT(2) NULL DEFAULT '0' AFTER area");
			self::execSql($pdo, "ALTER TABLE %histuser% ADD quickcash INT(2) NULL DEFAULT '0' AFTER area");
			self::execSql($pdo, "UPDATE %user% SET quickcash='0'");
			self::execSql($pdo, "UPDATE %histuser% SET quickcash='0'");
			self::execSql($pdo, "UPDATE %config% SET setting=(1-setting) WHERE name='cashenabled'");
			self::execSql($pdo, "UPDATE %histconfig% SET setting=(1-setting) WHERE configid=(SELECT id from %config% WHERE name='cashenabled')");
			
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1602_1603($pdo, $prefix, $dbname) {
		return array(true);
	}
	
	public static function upd_1603_1604($pdo, $prefix, $dbname) {
		return array(true);
	}
	
	public static function upd_1604_1605($pdo, $prefix, $dbname) {
		try {
			self::execSql($pdo, "ALTER TABLE %reservations% ADD restype INT(1) NULL DEFAULT '0' AFTER remark");
			self::execSql($pdo, "UPDATE %reservations% SET restype='0'");
			
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1605_1606($pdo, $prefix, $dbname) {
		
		try {
			$basedb = new BaseDb();
			$basedb->createOrUpdateUID($pdo,'2.9.12');
			$basedb->createOrUpdateSN($pdo,'ORD1');
			self::insertOrUpdateConfigItem($pdo, 'systemid', 1);
			
			self::execSql($pdo, "ALTER TABLE %hist% ADD clsid INT(1) NULL DEFAULT NULL AFTER refid");
			self::execSql($pdo, "ALTER TABLE %hist% ADD FOREIGN KEY(clsid) REFERENCES %closing% (id)");
			self::execSql($pdo, "ALTER TABLE %closing% ADD INDEX dclosing (closingdate)");
			self::addHistClsReference($pdo);
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1606_1607($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'dsfinvk_name', '');
			self::insertOrUpdateConfigItem($pdo, 'dsfinvk_street', '');
			self::insertOrUpdateConfigItem($pdo, 'dsfinvk_postalcode', '');
			self::insertOrUpdateConfigItem($pdo, 'dsfinvk_city', '');
			self::insertOrUpdateConfigItem($pdo, 'dsfinvk_country', '');
			self::insertOrUpdateConfigItem($pdo, 'dsfinvk_stnr', '');
			self::insertOrUpdateConfigItem($pdo, 'dsfinvk_ustid', '');
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1607_1608($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_1608_1609($pdo, $prefix, $dbname) {
		return array(true);
	}
	
	public static function upd_1609_1610($pdo, $prefix, $dbname) {
		try {
			self::execSql($pdo, "ALTER TABLE %bill% ADD cashtype INT(1) NULL DEFAULT NULL AFTER paymentid");
			self::execSql($pdo, "UPDATE %bill% SET cashtype=(IF(status='c',(IF(brutto<='0.00',1,2)),null))");
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_1610_2000($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'usetse', '0');
			self::insertOrUpdateConfigItem($pdo, 'tseurl', 'http://localhost:8000');
			self::insertOrUpdateConfigItem($pdo, 'tsepass', '123');
			self::insertOrUpdateConfigItem($pdo, 'tsepin', '1, 2, 3, 4, 5');
			self::insertOrUpdateConfigItem($pdo, 'tsepuk', '1, 2, 3, 4, 5, 6');
			
			$basedb = new BaseDb(); $basedb->createTseValuesTable($pdo);
			self::execSql($pdo, "ALTER TABLE %bill% ADD `logtime` INT(5) NULL DEFAULT NULL AFTER signature");
			self::execSql($pdo, "ALTER TABLE %queue% ADD `logtime` INT(5) NULL DEFAULT NULL AFTER clsid");
			self::execSql($pdo, "ALTER TABLE %queue% ADD `cancellogtime` INT(5) NULL DEFAULT NULL AFTER logtime");
			
			self::execSql($pdo, "ALTER TABLE %queue% ADD pricetype INT(1) NULL AFTER `tax`");
			self::execSql($pdo, "UPDATE %queue% SET pricetype='0'");
			self::execSql($pdo, "ALTER TABLE %bill% ADD billuid INT(1) NULL AFTER `id`");
			self::execSql($pdo, "UPDATE %bill% SET billuid=id");
			
			$basedb = new BaseDb(); 
			$basedb->createTerminalsTable($pdo);
			$basedb->createOperationsTable($pdo);
			self::execSql($pdo, "ALTER TABLE %bill% ADD opid INT(10) NULL DEFAULT NULL AFTER logtime");
			self::execSql($pdo, "ALTER TABLE %bill% ADD FOREIGN KEY(opid) REFERENCES %operations% (id)");
			self::execSql($pdo, "ALTER TABLE %queue% ADD opidok INT(10) NULL DEFAULT NULL AFTER cancellogtime");
			self::execSql($pdo, "ALTER TABLE %queue% ADD FOREIGN KEY(opidok) REFERENCES %operations% (id)");
			self::execSql($pdo, "ALTER TABLE %queue% ADD opidcancel INT(10) NULL DEFAULT NULL AFTER opidok");
			self::execSql($pdo, "ALTER TABLE %queue% ADD poszeile INT NULL DEFAULT NULL AFTER opidcancel");
			self::execSql($pdo, "UPDATE %queue% SET poszeile='0'");
			self::execSql($pdo, "ALTER TABLE %queue% ADD FOREIGN KEY(opidcancel) REFERENCES %operations% (id)");
		
			$rectemplate = self::getDefaultCustomRecTemplate();
			$clstemplate = self::getDefaultCustomClsTemplate();
			$cashtemplate = self::getDefaultCustomCashTemplate();
			$foodTemplate = self::getDefaultCustomFoodTemplate();
			$drinkTemplate = self::getDefaultCustomDrinkTemplate();
			$cancelTemplate = self::getDefaultCustomCancelTemplate();
			$pickupTemplate = self::getDefaultCustomPickupTemplate();
			self::insertOrUpdateConfigItem($pdo, 'rectemplate', $rectemplate);
			self::insertOrUpdateConfigItem($pdo, 'clstemplate', $clstemplate);
			self::insertOrUpdateConfigItem($pdo, 'cashtemplate', $cashtemplate);
			self::insertOrUpdateConfigItem($pdo, 'foodtemplate', $foodTemplate);
			self::insertOrUpdateConfigItem($pdo, 'drinktemplate', $drinkTemplate);
			self::insertOrUpdateConfigItem($pdo, 'canceltemplate', $cancelTemplate);
			self::insertOrUpdateConfigItem($pdo, 'pickuptemplate', $pickupTemplate);
			
			self::execSql($pdo, "ALTER TABLE %billproducts% ADD position INT NULL DEFAULT NULL AFTER billid");
			
			self::execSql($pdo, "ALTER TABLE %user% ADD `photo` MEDIUMBLOB");
			self::execSql($pdo, "DELETE FROM %printjobs%");
			
			self::insertOrUpdateConfigItem($pdo, 'coins', '1,2,5,10,20,50,100,200');
			self::insertOrUpdateConfigItem($pdo, 'notes', '5,10,20,50,100,200,500');
			self::insertOrUpdateConfigItem($pdo, 'coinvalname', 'Cent');
			self::insertOrUpdateConfigItem($pdo, 'notevalname', 'Euro');
			$basedb->createCountingTable($pdo);
			self::execSql($pdo, "ALTER TABLE %closing% ADD counting INT NULL");
			self::execSql($pdo, "UPDATE %closing% SET counting='0'");
			self::execSql($pdo, "ALTER TABLE %closing% ADD counted DECIMAL (19,2) NULL");
			self::execSql($pdo, "UPDATE %closing% SET counted='0.0'");
			Closing::createdCountedValuesForClosing($pdo);
			self::execSql($pdo, "ALTER TABLE %queue% ADD taxkey INT NULL DEFAULT NULL AFTER tax");
			
			$sqlTaxChange = "(CASE ";
			$sqlTaxChange .= " WHEN ROUND(tax)='19' THEN '1'";
			$sqlTaxChange .= " WHEN ROUND(tax)='7' THEN '2'";
			$sqlTaxChange .= " WHEN ROUND(tax)='0' THEN '5'";
			$sqlTaxChange .= " WHEN ROUND(tax)='16' THEN '21'";
			$sqlTaxChange .= " WHEN ROUND(tax)='5' THEN '22'";
			$sqlTaxChange .= " ELSE '1' END)";
			$sql = "UPDATE %queue% SET taxkey = $sqlTaxChange";
			self::execSql($pdo, $sql);
			
			// Food: erm. Steuersatz -> needs to be put 5%, Drinks: allg. Steuersatz -> needs to be put to 16%
			self::execSql($pdo, "ALTER TABLE %products% MODIFY tax INT NULL DEFAULT NULL");
			self::execSql($pdo, "UPDATE %products% P, %prodtype% T SET P.tax='2' WHERE P.category=T.id AND T.kind='0'");
			self::execSql($pdo, "UPDATE %products% P, %prodtype% T SET P.tax='1' WHERE P.category=T.id AND T.kind='1'");
			self::execSql($pdo, "ALTER TABLE %products% ADD togotax INT NULL DEFAULT NULL AFTER tax");
			self::execSql($pdo, "UPDATE %products% SET togotax='2'");
			self::execSql($pdo, "UPDATE %products% P, %prodtype% T SET P.togotax='1' WHERE P.category=T.id AND T.kind='1'");
			self::execSql($pdo, "ALTER TABLE %histprod% MODIFY tax INT NULL DEFAULT NULL");
			self::execSql($pdo, "ALTER TABLE %histprod% ADD togotax INT NULL DEFAULT NULL AFTER tax");
			//self::insertOrUpdateConfigItem($pdo, 'tax', '16.0');
			//self::insertOrUpdateConfigItem($pdo, 'togotax', '5.0');
			
			self::execSql($pdo, "ALTER TABLE %closing% ADD opid INT(10) NULL DEFAULT NULL ");
			self::execSql($pdo, "ALTER TABLE %closing% ADD FOREIGN KEY(opid) REFERENCES %operations% (id)");
			
			self::execSql($pdo, "ALTER TABLE %closing% ADD COLUMN dsfinvkversion VARCHAR(5) DEFAULT NULL");
			self::execSql($pdo, "ALTER TABLE %closing% ADD COLUMN dsfinvk_name VARCHAR(100) DEFAULT NULL");
			self::execSql($pdo, "ALTER TABLE %closing% ADD COLUMN dsfinvk_street VARCHAR(100) DEFAULT NULL");
			self::execSql($pdo, "ALTER TABLE %closing% ADD COLUMN dsfinvk_postalcode VARCHAR(100) DEFAULT NULL");
			self::execSql($pdo, "ALTER TABLE %closing% ADD COLUMN dsfinvk_city VARCHAR(100) DEFAULT NULL");
			self::execSql($pdo, "ALTER TABLE %closing% ADD COLUMN dsfinvk_country VARCHAR(100) DEFAULT NULL");
			self::execSql($pdo, "ALTER TABLE %closing% ADD COLUMN dsfinvk_stnr VARCHAR(100) DEFAULT NULL");
			self::execSql($pdo, "ALTER TABLE %closing% ADD COLUMN dsfinvk_ustid VARCHAR(100) DEFAULT NULL");
			self::execSql($pdo, "ALTER TABLE %closing% ADD COLUMN terminalid INT DEFAULT NULL");
			self::execSql($pdo, "ALTER TABLE %closing% ADD COLUMN version VARCHAR(30) DEFAULT NULL");
			self::execSql($pdo, "ALTER TABLE %closing% ADD COLUMN taxset1 DECIMAL (15,2) DEFAULT NULL");
			self::execSql($pdo, "ALTER TABLE %closing% ADD COLUMN taxset2 DECIMAL (15,2) DEFAULT NULL");
			self::execSql($pdo, "UPDATE %closing% C SET C.taxset1=(SELECT setting FROM %config% WHERE name='tax')");
			self::execSql($pdo, "UPDATE %closing% C SET C.taxset2=(SELECT setting FROM %config% WHERE name='togotax')");
			self::execSql($pdo, "ALTER TABLE %closing% ADD COLUMN cashsum DECIMAL (17,2) DEFAULT NULL");
			
			self::insertOrUpdateConfigItem($pdo, 'dsfinvkversion', '2.2');
			
			$basedb->createVouchersTable($pdo);
			self::execSql($pdo, "ALTER TABLE %queue% ADD ordertype INT NULL DEFAULT NULL");
			self::execSql($pdo, "UPDATE %queue% SET ordertype='" . DbUtils::$ORDERTYPE_PRODUCT . "'");
			self::execSql($pdo, "ALTER TABLE %queue% ADD voucherid INT NULL DEFAULT NULL");
			self::execSql($pdo, "UPDATE %queue% SET voucherid=null");
			self::execSql($pdo, "ALTER TABLE %queue% ADD FOREIGN KEY(voucherid) REFERENCES %vouchers% (id)");
			
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_2000_2001($pdo, $prefix, $dbname) {
		try {
			self::execSql($pdo, "ALTER TABLE %user% MODIFY mobiletheme INT(2) NULL DEFAULT '8'");

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	
	public static function upd_2001_2002($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_2002_2003($pdo, $prefix, $dbname) {
		try {
			self::execSql($pdo, "ALTER TABLE %operations% ADD clsid INT NULL");
			self::execSql($pdo, "UPDATE %operations% O,%bill% B,%closing% C SET O.clsid=C.id where B.opid=O.id AND B.closingid=C.id");
			self::execSql($pdo, "UPDATE %operations% O,%closing% C SET O.clsid=C.id WHERE C.opid=O.id");
			self::execSql($pdo, "UPDATE %operations% O,%queue% Q SET O.clsid=Q.clsid WHERE Q.opidok=O.id");
			self::execSql($pdo, "UPDATE %operations% O,%queue% Q SET O.clsid=Q.clsid WHERE Q.opidcancel=O.id");
			return array(true);
			
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	public static function upd_2003_2004($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_2004_2005($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'kitchenextrasize', 0);
			self::insertOrUpdateConfigItem($pdo, 'kitchenoptionsize', 0);
			return array(true);
			
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	public static function upd_2005_2006($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'updateurl', 'https://www.ordersprinter.de/update');
			
			$rectemplate = self::getDefaultCustomRecTemplate();
			self::insertOrUpdateConfigItem($pdo, 'rectemplate', $rectemplate);
			
			return array(true);
			
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	public static function upd_2006_2007($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_2007_2008($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'guesttheme', 8);
			return array(true);
			
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
	public static function upd_2008_2009($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_2009_2010($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_2010_2011($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_2011_2012($pdo, $prefix, $dbname) {
		try {
			self::execSql($pdo, "ALTER TABLE %roles% ADD right_cashop INT NULL AFTER right_pickups");
			self::execSql($pdo, "ALTER TABLE %histuser% ADD right_cashop INT NULL AFTER right_pickups");

			self::execSqlWithParam($pdo, "UPDATE %roles% SET right_cashop=?", array(1));
			self::execSqlWithParam($pdo, "UPDATE %histuser% SET right_cashop=?", array(1));

			self::execSql($pdo, "OPTIMIZE TABLE %histuser%");
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2012_2013($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2013_2014($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2014_2015($pdo, $prefix, $dbname) {
                try {
			self::insertOrUpdateConfigItem($pdo, 'guestarticleconfirm', 1);
			return array(true);
			
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2015_2016($pdo, $prefix, $dbname) {
                try {
			$basedb = new BaseDb(); $basedb->createOrdersTable($pdo);
                        self::execSql($pdo, "ALTER TABLE %queue% ADD orderid INT NULL");
                        self::execSql($pdo, "ALTER TABLE %queue% ADD FOREIGN KEY(orderid) REFERENCES %orders% (id)");
                        self::execSql($pdo, "UPDATE %queue% SET orderid = null");
                        self::insertOrUpdateConfigItem($pdo, 'enableorders', 1);
                        self::execSql($pdo, "ALTER TABLE %histuser% ADD right_delivery INT NULL");
                        self::execSql($pdo, "UPDATE %histuser% SET right_delivery='1'");
                        self::execSql($pdo, "ALTER TABLE %roles% ADD right_delivery INT NULL");
                        self::execSql($pdo, "UPDATE %roles% SET right_delivery='1' WHERE right_rating <> '1'");
                        self::execSql($pdo, "ALTER TABLE %bill% ADD tip DECIMAL(6,2) NULL");
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2016_2017($pdo, $prefix, $dbname) {
                try {
                        self::execSql($pdo, "ALTER TABLE %orders% ADD email VARCHAR(200) NULL");
                        self::execSql($pdo, "UPDATE %orders% SET email=null");
                        self::execSql($pdo, "ALTER TABLE %orders% ADD sendemail INT NULL");
                        self::execSql($pdo, "UPDATE %orders% SET sendemail='0'");
                        self::insertOrUpdateConfigItem($pdo, 'orderemailtext', "Lieber Herr/Frau {NAME},\n\nIhre Bestellung vom {DATUM} um {UHRZEIT} wurde zubereitet und kann abgeholt werden.\n\n{BETRIEBSINFO}\n");
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2017_2018($pdo, $prefix, $dbname) {
                try {
                        $basedb = new BaseDb(); 
                        $basedb->createProdnamesTable($pdo);
                        self::execSql($pdo, "ALTER TABLE %queue% ADD prodnameid INT NULL");
                        self::execSql($pdo, "ALTER TABLE %queue% ADD FOREIGN KEY(prodnameid) REFERENCES %prodnames% (id)");
                        $basedb->deduplicateQueueNames($pdo);
                        self::execSql($pdo, "ALTER TABLE %queue% DROP COLUMN productname");
                        self::execSql($pdo, "OPTIMIZE TABLE %queue%");
                        self::execSql($pdo, "ALTER TABLE %histuser% ADD right_customersview INT NULL");
                        self::execSql($pdo, "UPDATE %histuser% SET right_customersview='1'");
                        self::execSql($pdo, "ALTER TABLE %roles% ADD right_customersview INT NULL");
                        self::execSql($pdo, "UPDATE %roles% SET right_customersview='1' WHERE right_rating <> '1'");
                        $basedb->createLiveOrdersTable($pdo);
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        
        public static function upd_2018_2019($pdo, $prefix, $dbname) {
                try {
                        $lang = self::getLang($pdo, $prefix);
                        
                        self::insertOrUpdateConfigItem($pdo, 'cat1name', array("Speisen","Food","Alimentos")[$lang]);
                        self::insertOrUpdateConfigItem($pdo, 'cat2name', array("Getränke","Drinks","Bebidas")[$lang]);
                        self::insertOrUpdateConfigItem($pdo, 'prodlistname', array("Speisekarte","Menu","Menú")[$lang]);
                        self::insertOrUpdateConfigItem($pdo, 'cat1viewname', array("Küche","Kitchen","Cocina")[$lang]);
                        self::insertOrUpdateConfigItem($pdo, 'cat2viewname', array("Bar","Bar","Bar")[$lang]);
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2019_2020($pdo, $prefix, $dbname) {
                try {
                        $sql = "SELECT setting FROM %config% WHERE name=?";
                        $stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
			$stmt->execute(array('allprodstoreceipt'));
			$r = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        if (count($r) == 0) {
                                self::insertOrUpdateConfigItem($pdo, 'allprodstoreceipt', 0);
                        }
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2020_2021($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2021_2022($pdo, $prefix, $dbname) {
                try {
                        self::execSql($pdo, "UPDATE %bill% SET brutto='0.00',netto='0.00' WHERE brutto is null");
                        
                        $lang = self::getLang($pdo, $prefix);
                        $desktopviename = array("Kellneransicht","Waiter's View","Vista de camareros")[$lang];
                        $sql = "SELECT setting FROM %config% WHERE name=?";
                        $stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
			$stmt->execute(array('deskviewname'));
			$r = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        if (count($r) === 0) {
                                self::insertOrUpdateConfigItem($pdo, 'deskviewname', $desktopviename);
                        } else {
                                $thevalue = $r[0]["setting"];
                                if (is_null($thevalue)) {
                                        self::insertOrUpdateConfigItem($pdo, 'deskviewname', $desktopviename);
                                }
                        }

			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2022_2023($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2023_2024($pdo, $prefix, $dbname) {
                try {
                        $sql = "UPDATE %roles% SET right_customersview='0' WHERE is_admin='0' AND right_manager='0' AND right_waiter='0' AND right_paydesk='0'";
                        self::execSql($pdo,$sql);
                        return array(true);
                } catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2024_2025($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2025_2026($pdo, $prefix, $dbname) {
                try {
                        self::insertOrUpdateConfigItem($pdo, 'mostsoldasfavs', 0);
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2026_2027($pdo, $prefix, $dbname) {
                try {
                        self::insertOrUpdateConfigItem($pdo, 'allowguestexport', 1);
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2027_2028($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2028_2029($pdo, $prefix, $dbname) {
                try {
                        self::execSql($pdo, "ALTER TABLE %customers% ADD permanent INT NULL");
                        self::execSql($pdo, "UPDATE %customers% SET permanent='0'");
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2029_2100($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2100_2101($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2101_2102($pdo, $prefix, $dbname) {
                try {
                        $basedb = new BaseDb(); 
                        $basedb->createPerformanceTable($pdo);
                        $basedb->createUsedFeaturesTable($pdo);
                        
                        self::insertOrUpdateConfigItem($pdo, 'publishlocation', 0);
                        self::insertOrUpdateConfigItem($pdo, 'publishperformance', 0);
                        self::insertOrUpdateConfigItem($pdo, 'publishfeatures', 0);
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2102_2103($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2103_2200($pdo, $prefix, $dbname) {
                try {
                        self::execSql($pdo, "ALTER TABLE %queue% ADD phase INT NULL");
                        self::execSql($pdo, "UPDATE %queue% SET phase='0'");
                        self::insertOrUpdateConfigItem($pdo, 'showphases', 0);
                        
                        self::execSql($pdo, "ALTER TABLE %closing% ADD tipbar decimal(15,2)");
			self::execSql($pdo, "ALTER TABLE %closing% ADD tipunbar decimal(15,2)");
                        self::execSql($pdo, "ALTER TABLE %closing% ADD saleswithoutcash decimal(15,2)");
                        self::execSql($pdo, "UPDATE %closing% SET tipbar=null,tipunbar=null,saleswithoutcash=null");
                        self::insertOrUpdateConfigItem($pdo, 'bartransitbeforecls', 0);
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2200_2300($pdo, $prefix, $dbname) {
                try {
                        self::execSql($pdo, "ALTER TABLE %user% ADD fullname VARCHAR(50) DEFAULT ''");
                        self::execSql($pdo, "UPDATE %user% SET fullname=username");
                        self::execSql($pdo, "ALTER TABLE %user% ADD isowner INT NULL DEFAULT '0'");
                        self::execSql($pdo, "UPDATE %user% SET isowner='0'");
                        self::execSql($pdo, "ALTER TABLE %histuser% ADD fullname VARCHAR(50) DEFAULT ''");
                        self::execSql($pdo, "UPDATE %histuser% SET fullname=username");
                        self::execSql($pdo, "ALTER TABLE %histuser% ADD isowner INT NULL DEFAULT '0'");
                        self::execSql($pdo, "UPDATE %histuser% SET isowner='0'");
                        
                        self::execSql($pdo, "ALTER TABLE %bill% ADD billidoftip INT NULL DEFAULT null");
                        self::execSql($pdo, "UPDATE %bill% SET billidoftip=null");
                        self::execSql($pdo, "ALTER TABLE %closing% ADD salestotal decimal(15,2) DEFAULT null");
                        
                        self::insertOrUpdateConfigItem($pdo, 'closshowci', 1);
                        self::insertOrUpdateConfigItem($pdo, 'closshowpaytaxes', 1);
                        self::insertOrUpdateConfigItem($pdo, 'closshowprods', 1);

                        Closing::recalcSalesOfClosings($pdo);
                        
                        $clstemplate = self::getDefaultCustomClsTemplate();
			self::insertOrUpdateConfigItem($pdo, 'clstemplate', $clstemplate);
                        
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2300_2301($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2301_2302($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2302_2303($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2303_2304($pdo, $prefix, $dbname) {
                try {
                        self::insertOrUpdateConfigItem($pdo, 'allowminuscheapest', 0);
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2304_2305($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2305_2306($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2306_2307($pdo, $prefix, $dbname) {
                try {
                        self::execSql($pdo, "ALTER TABLE %queue% ADD workprinter INT NULL DEFAULT '1'");
                        self::execSql($pdo, "UPDATE %queue% SET workprinter='1'");
                        self::insertOrUpdateConfigItem($pdo, 'showkitsel', 0);
                        self::insertOrUpdateConfigItem($pdo, 'showbarsel', 0);
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2307_2308($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2308_2309($pdo, $prefix, $dbname) {
                try {
                        self::execSql($pdo, "ALTER TABLE %performance% ADD INDEX perfdateindex (perfdate)");
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2309_2310($pdo, $prefix, $dbname) {
                return array(true);
	}
        
        public static function upd_2310_2311($pdo, $prefix, $dbname) {
                try {
                        self::insertOrUpdateConfigItem($pdo, 'needcrinbarcode', 0);
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2311_2400($pdo, $prefix, $dbname) {
                try {
                        self::insertOrUpdateConfigItem($pdo, 'handleamount', 0);
                        self::insertOrUpdateConfigItem($pdo, 'guestshowsoldprods', 0);
                        
                        self::insertOrUpdateConfigItem($pdo, 'sroomurl', '');
                        self::insertOrUpdateConfigItem($pdo, 'sroomcode', '');
                        
                        self::insertOrUpdateConfigItem($pdo, 'sroomprivacy', '');
                        self::insertOrUpdateConfigItem($pdo, 'sroomcss', self::getShowroomGeneralCss());
                        self::insertOrUpdateConfigItem($pdo, 'sroomprivacy', self::getShowroomPrivacy());
                        $impressum = CommonUtils::getConfigValue($pdo, 'companyinfo', '');
                        self::insertOrUpdateConfigItem($pdo, 'sroomimpressum', $impressum);
                        self::insertOrUpdateConfigItem($pdo, 'sroomabout', "Hier kann ein wenig über daas Café erzählt werden.\n\nAußerdem kann man hier die Öffnungszeiten bekanntmachen.");
                        self::insertOrUpdateConfigItem($pdo, 'sroomnews', 'Gibt es aktuelle Neuigkeiten? Dann gehören die hier hinein!');
                        self::insertOrUpdateConfigItem($pdo, 'sroomfood', "Unsere Köche bereiten Ihnen das leckerste Essen zu!\n\nProbieren Sie!!");
                        self::insertOrUpdateConfigItem($pdo, 'sroomdrinks', "Neben den üblichen Getränken bieten wir eine außergewöhnliche Auswahl an Cocktails an!");
                        self::insertOrUpdateConfigItem($pdo, 'sroomprodview', 0);
                        self::insertOrUpdateConfigItem($pdo, 'sroomshowworkload', 1);
                        self::insertOrUpdateConfigItem($pdo, 'sroomutilization', "Der weiße Kreis zeigt aktuelle Auslastung des Cafés an.\nBei hoher Auslastung sind die Wartezeiten erwartungsgemäß etwas länger.");
                        $sroomtitle = '';
                        if (strlen($impressum) > 0) {
                                $sroomtitle = explode("\n", $impressum)[0];
                        }
                        self::insertOrUpdateConfigItem($pdo, 'sroomtitle', $sroomtitle);
                        self::execSql($pdo, "ALTER TABLE %logo% MODIFY setting longblob NULL");
                        self::execSql($pdo, "ALTER TABLE %prodimages% MODIFY imgh longblob NULL");
                        
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        
        public static function upd_2400_2401($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2401_2402($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2402_2403($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2403_2404($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2404_2405($pdo, $prefix, $dbname) {
                try {
                        self::execSql($pdo, "ALTER TABLE %liveorders% ADD tableid INT NULL");
                        self::execSql($pdo, "ALTER TABLE %liveorders% ADD FOREIGN KEY(tableid) REFERENCES %resttables% (id)");
                        self::insertOrUpdateConfigItem($pdo, 'showtableforcustomer', 0);
                        return array(true);
                }catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2405_2406($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2406_2407($pdo, $prefix, $dbname) {
                try {
                        self::insertOrUpdateConfigItem($pdo, 'sroomonlygarticles', 1);
                        return array(true);
                }catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2407_2408($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2408_2409($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2409_2410($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2410_2411($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2411_2500($pdo, $prefix, $dbname) {
                try {
					self::insertOrUpdateConfigItem($pdo, 'commentinpaydesk', 0);
						
					self::execSql($pdo, "ALTER TABLE %products% ADD tags TEXT NULL");
					self::execSql($pdo, "ALTER TABLE %histprod% ADD tags TEXT NULL");
					self::execSql($pdo, "ALTER TABLE %products% ADD description TEXT NULL");
					self::execSql($pdo, "ALTER TABLE %histprod% ADD description TEXT NULL");
					self::execSql($pdo, "ALTER TABLE %products% ADD nameonworkrec VARCHAR(30) NULL");
					self::execSql($pdo, "ALTER TABLE %histprod% ADD nameonworkrec VARCHAR(30) NULL");
						
					self::execSql($pdo, "ALTER TABLE %products% DROP days");
					self::execSql($pdo, "ALTER TABLE %histprod% DROP days");
					self::execSql($pdo, "ALTER TABLE %products% ADD monday INT NULL");
					self::execSql($pdo, "ALTER TABLE %histprod% ADD monday INT NULL");
					self::execSql($pdo, "ALTER TABLE %products% ADD tuesday INT NULL");
					self::execSql($pdo, "ALTER TABLE %histprod% ADD tuesday INT NULL");
					self::execSql($pdo, "ALTER TABLE %products% ADD wednesday INT NULL");
					self::execSql($pdo, "ALTER TABLE %histprod% ADD wednesday INT NULL");
					self::execSql($pdo, "ALTER TABLE %products% ADD thursday INT NULL");
					self::execSql($pdo, "ALTER TABLE %histprod% ADD thursday INT NULL");
					self::execSql($pdo, "ALTER TABLE %products% ADD friday INT NULL");
					self::execSql($pdo, "ALTER TABLE %histprod% ADD friday INT NULL");
					self::execSql($pdo, "ALTER TABLE %products% ADD saturday INT NULL");
					self::execSql($pdo, "ALTER TABLE %histprod% ADD saturday INT NULL");
					self::execSql($pdo, "ALTER TABLE %products% ADD sunday INT NULL");
					self::execSql($pdo, "ALTER TABLE %histprod% ADD sunday INT NULL");

					self::insertOrUpdateConfigItem($pdo, 'sroomcss', self::getShowroomGeneralCss());

					self::insertOrUpdateConfigItem($pdo, 'ebonurl', '');
					self::insertOrUpdateConfigItem($pdo, 'eboncode', '');
					self::execSql($pdo, "ALTER TABLE %bill% ADD needsebonupload INT(1) NULL");
					self::execSql($pdo, "UPDATE %bill% SET needsebonupload='0'");
					self::execSql($pdo, "ALTER TABLE %bill% ADD ebonref VARCHAR(50) NULL");
					self::execSql($pdo, "UPDATE %bill% SET ebonref=''");
                        
                        return array(true);
                }catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        
        public static function upd_2500_2501($pdo, $prefix, $dbname) {
                try {
                        self::execSql($pdo, "UPDATE %products% SET monday='1'");
                        self::execSql($pdo, "UPDATE %products% SET tuesday='1'");
                        self::execSql($pdo, "UPDATE %products% SET wednesday='1'");
                        self::execSql($pdo, "UPDATE %products% SET thursday='1'");
                        self::execSql($pdo, "UPDATE %products% SET friday='1'");
                        self::execSql($pdo, "UPDATE %products% SET saturday='1'");
                        self::execSql($pdo, "UPDATE %products% SET sunday='1'");
                        
                        self::execSql($pdo, "UPDATE %histprod% SET monday='1'");
                        self::execSql($pdo, "UPDATE %histprod% SET tuesday='1'");
                        self::execSql($pdo, "UPDATE %histprod% SET wednesday='1'");
                        self::execSql($pdo, "UPDATE %histprod% SET thursday='1'");
                        self::execSql($pdo, "UPDATE %histprod% SET friday='1'");
                        self::execSql($pdo, "UPDATE %histprod% SET saturday='1'");
                        self::execSql($pdo, "UPDATE %histprod% SET sunday='1'");
                        
                        return array(true);
                }catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        
        public static function upd_2501_2502($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2502_2503($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2503_2504($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2504_2505($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2505_2506($pdo, $prefix, $dbname) {
                try {
                        self::execSql($pdo, "update %products% set description='' where description is null");
                        return array(true);
                }catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2506_2507($pdo, $prefix, $dbname) {
                try {
                        self::insertOrUpdateConfigItem($pdo, 'perftoinflux', 0);
                        self::insertOrUpdateConfigItem($pdo, 'salestoinflux', 0);
                        self::insertOrUpdateConfigItem($pdo, 'influxurl', '');
                        self::insertOrUpdateConfigItem($pdo, 'influxbucket', '');
                        self::insertOrUpdateConfigItem($pdo, 'influxorg', '');
                        self::insertOrUpdateConfigItem($pdo, 'influxtoken', '');
                        self::insertOrUpdateConfigItem($pdo, 'influxupdfreq', '0');
                        self::insertOrUpdateConfigItem($pdo, 'influxperflabel', 'performance');
                        self::insertOrUpdateConfigItem($pdo, 'influxsaleslabel', 'sales');
                        self::insertOrUpdateConfigItem($pdo, 'influxtablelabel', 'opentables');
                        self::insertOrUpdateConfigItem($pdo, 'influxsoldlabel', 'soldarticles');
                        self::execSql($pdo, "ALTER TABLE %performance% ADD issenttoinflux INT(1) NULL");
                        self::execSql($pdo, "UPDATE %performance% SET issenttoinflux='0'");
                        self::execSql($pdo, "ALTER TABLE %performance% ADD perfdatetime DATETIME NULL");
                        self::execSql($pdo, "UPDATE %performance% SET perfdatetime=TIMESTAMP(perfdate)");
                        return array(true);
                }catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2507_2600($pdo, $prefix, $dbname) {
                try {
                        self::insertOrUpdateConfigItem($pdo, 'showreceiptinpaydesk', 1);
                        return array(true);
                }catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        
        public static function upd_2600_2700($pdo, $prefix, $dbname) {
                try {
                        $workflowconfig = CommonUtils::getConfigValue($pdo, 'workflowconfig', 0);
                        self::insertOrUpdateConfigItem($pdo, 'workflowconfigdrinks', $workflowconfig);
                        return array(true);
                }catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2700_2701($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2701_2702($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2702_2703($pdo, $prefix, $dbname) {
                try {
                        self::execSql($pdo, "ALTER TABLE %user% ADD sorting INT(2) NULL");
                        $sql = "SELECT U.id as id,
                                COALESCE(U.fullname,'') as fullname,
                                COALESCE(U.isowner,'0') as isowner,
                                is_admin,right_manager,
                                COALESCE(U.area,'0') as tablearea
                                FROM %user% U ,%roles% RO
                                WHERE active='1' AND U.roleid=RO.id ORDER BY is_admin,right_manager,right_waiter DESC,right_kitchen DESC,right_bar DESC,right_paydesk DESC,right_bill DESC,right_supply DESC,right_tasks DESC,right_tasksmanagement DESC,username";
                        $stmt = $pdo->prepare(DbUtils::substTableAliasCore($sql, $prefix));
                        $stmt->execute();
                        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        $i = 0;
                        foreach ($result as $aUserEntry) {
                                $sql = "UPDATE %user% U SET U.sorting=? WHERE id=?";
                                self::execSqlWithParam($pdo, $sql, array($i,$aUserEntry['id']));
                                $i++;
                        }
                        return array(true);
                }catch (PDOException $e) {
                        return array(false,$e->getMessage());
                }
	}
        
        public static function upd_2703_2704($pdo, $prefix, $dbname) {
                return array(true);
	}
        
        public static function upd_2704_2705($pdo, $prefix, $dbname) {
                try {
                        self::execSql($pdo, "ALTER TABLE `%payment%` ADD paymenttype INT NOT NULL,ADD isallowed INT NOT NULL");
                        $sql =  "UPDATE `%payment%` SET paymenttype=?,isallowed=? WHERE name=?";
                        self::execSqlWithParam($pdo, $sql, array(0,1,'Barzahlung'));
                        self::execSqlWithParam($pdo, $sql, array(1,1,'EC-Kartenzahlung'));
                        self::execSqlWithParam($pdo, $sql, array(1,1,'Kreditkartenzahlung'));
                        self::execSqlWithParam($pdo, $sql, array(1,0,'Rechnung'));
                        self::execSqlWithParam($pdo, $sql, array(1,0,'Überweisung'));
                        self::execSqlWithParam($pdo, $sql, array(1,0,'Lastschrift'));
                        self::execSqlWithParam($pdo, $sql, array(2,1,'Hotelzimmer'));
                        self::execSqlWithParam($pdo, $sql, array(2,1,'Gast'));
                        
                        return array(true);
                }catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        
        public static function upd_2705_2706($pdo, $prefix, $dbname) {
                try {
                        self::insertOrUpdateConfigItem($pdo, 'showonlymyjobscheck', 0);
                        self::execSql($pdo, "ALTER TABLE %user% ADD showonlymyjobs INT NOT NULL DEFAULT '0'");
                        self::execSql($pdo, "UPDATE %user% SET showonlymyjobs='0'");
                        
                        self::insertOrUpdateConfigItem($pdo, 'pickupsnoauth', 0);
                        
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2706_2800($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2800_2801($pdo, $prefix, $dbname) {
                try {
                        self::insertOrUpdateConfigItem($pdo, 'usesignat', 0);
                        
                        self::insertOrUpdateConfigItem($pdo, 'fiskalyapikey', "");
                        self::insertOrUpdateConfigItem($pdo, 'fiskalyapisecret', "");
                        
                        
                        self::insertOrUpdateConfigItem($pdo, 'fonparticipantid', "");
                        self::insertOrUpdateConfigItem($pdo, 'fonuserid', "");
                        self::insertOrUpdateConfigItem($pdo, 'fonuserpin', "");
                        
                        
                        self::insertOrUpdateConfigItem($pdo, 'rksvscuid', "");
                        self::insertOrUpdateConfigItem($pdo, 'rksvcashregid', "");
                        self::insertOrUpdateConfigItem($pdo, 'rksvleinumber', "ATU00000001");
                        self::insertOrUpdateConfigItem($pdo, 'rksvleiname', "");
                        
                        self::execSql($pdo, "ALTER TABLE %work% MODIFY value TEXT NULL");
                                
                        self::execSql($pdo, "ALTER TABLE %bill% "
                                . "ADD fiskalyreceiptnumber INT NULL DEFAULT NULL,"
                                . "ADD fiskalytimesignature DATETIME NULL DEFAULT NULL,"
                                . "ADD fiskalyregserno VARCHAR(50) NULL DEFAULT NULL,"
                                . "ADD fiskalyqrcode TEXT NULL DEFAULT NULL,"
                                . "ADD fiskalysigned INT NULL DEFAULT NULL");
        
			return array(true);
		} catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2801_2802($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2802_2803($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2803_2804($pdo, $prefix, $dbname) {
                try {
                        self::insertOrUpdateConfigItem($pdo, 'showartcommentmobile', 1);
                        self::insertOrUpdateConfigItem($pdo, 'showordercommentmob', 1);
                        return array(true);
                } catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2804_2805($pdo, $prefix, $dbname) {
                try {
                        self::insertOrUpdateConfigItem($pdo, 'cancelinpaydesk', '');
                        return array(true);
                } catch (PDOException $e) {
			return array(false,$e->getMessage());
		}
	}
        public static function upd_2805_2806($pdo, $prefix, $dbname) {
                try {
                        self::execSql($pdo, "ALTER TABLE %products% ADD purchasingprice DECIMAL (19,2) NULL AFTER priceC");
                        self::execSql($pdo, "ALTER TABLE %histprod% ADD purchasingprice DECIMAL (19,2) NULL AFTER priceC");
                        self::insertTypeRow($pdo, '%queue%', 'profit', 'price', 'DECIMAL (19,2) NULL');
                        self::execSql($pdo, "UPDATE %queue% SET profit=null");
                        self::insertTypeRow($pdo, '%extras%', 'purchasingprice', 'price', 'DECIMAL (19,2) NULL');
                        self::execSql($pdo, "UPDATE %extras% SET purchasingprice=null");
                        return array(true);
                } catch (Exception $ex) {
                        return array(false,$e->getMessage());
                }
	}
        public static function upd_2806_2807($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2807_2808($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2808_2809($pdo, $prefix, $dbname) {
                try {
                        self::insertOrUpdateConfigItem($pdo, 'printpickupsdrinks', 1);
                        self::execSql($pdo,"update %config% as C1,(select setting from %config% where name='printpickups') as C2 SET C1.setting=C2.setting where C1.name='printpickupsdrinks'");
                        self::execSql($pdo,"ALTER TABLE %queue% ADD waitforprint INT NULL DEFAULT NULL",null);
                        self::insertOrUpdateConfigItem($pdo, 'delaydigiworkprint', 1);
                        return array(true);
                } catch (Exception $ex) {
                        return array(false,$e->getMessage());
                }
	}
        public static function upd_2809_2810($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2810_2811($pdo, $prefix, $dbname) {
                try {
                        self::execSql($pdo, "ALTER TABLE %user% ADD articletag VARCHAR(50) NULL");
			self::execSql($pdo, "OPTIMIZE TABLE %user%");
			self::execSql($pdo, "ALTER TABLE %histuser% ADD articletag VARCHAR(50) NULL");
			self::execSql($pdo, "OPTIMIZE TABLE %histuser%");
                        return array(true);
                } catch (Exception $ex) {
                        return array(false,$e->getMessage());
                }
	}
        public static function upd_2811_2812($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2812_2813($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2813_2814($pdo, $prefix, $dbname) {
                try {
                        self::insertOrUpdateConfigItem($pdo, 'kitchenshoworderuser', 0);
                        self::substitutePrintFeature($pdo);
                        return array(true);
                } catch (Exception $ex) {
                        return array(false,$e->getMessage());
                }
	}
        public static function upd_2814_2815($pdo, $prefix, $dbname) {
                return array(true);
	}
        public static function upd_2815_2816($pdo, $prefix, $dbname) {
                return array(true);
	}

	public static function upd_2816_2817($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'allowexceedamount', 0);
			return array(true);
		} catch (Exception $ex) {
				return array(false,$e->getMessage());
		}
	}

	public static function upd_2817_2818($pdo, $prefix, $dbname) {
		try {
			self::execSql($pdo, "ALTER TABLE %products% ADD guestmaxorder INT NULL");
			self::execSql($pdo, "UPDATE %products% SET guestmaxorder='1'");
			self::execSql($pdo, "ALTER TABLE %histprod% ADD guestmaxorder INT NULL");
			self::execSql($pdo, "UPDATE %histprod% SET guestmaxorder='1'");
			return array(true);
		} catch (Exception $ex) {
			return array(false,$e->getMessage());
	}
		}

	public static function upd_2818_2819($pdo, $prefix, $dbname) {
			return array(true);
	}

	public static function upd_2819_2820($pdo, $prefix, $dbname) {
		return array(true);
	}

	public static function upd_2820_2821($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_2821_2822($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_2822_2823($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_2823_2824($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'pollbills', 0);
			return array(true);
		} catch (Exception $ex) {
			return array(false,$e->getMessage());
		}
	}
	public static function upd_2824_2900($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_2900_2901($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_2901_2902($pdo, $prefix, $dbname) {
		try {
			// shall the prices being shown in the waiter and desktop view for the assigned articles, i.e. the unpaid, unserved list of articles
			self::insertOrUpdateConfigItem($pdo, 'priceassignedmobile', 0);
			self::insertOrUpdateConfigItem($pdo, 'priceassigneddesktop', 0);
			self::insertOrUpdateConfigItem($pdo, 'defaultphase', 0);
			return array(true);
		} catch (Exception $ex) {
			return array(false,$e->getMessage());
		}
	}
	public static function upd_2902_2903($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_2903_2904($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'invertarticlename', '');
			self::execSql($pdo,"ALTER TABLE %queue% ADD isminusarticle INT NULL DEFAULT NULL",null);
			return array(true);
		} catch (Exception $ex) {
			return array(false,$e->getMessage());
		}
	}
	public static function upd_2904_2905($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_2905_2906($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_2906_2907($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_2907_2908($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_2908_2909($pdo, $prefix, $dbname) {
		try {
			self::insertOrUpdateConfigItem($pdo, 'barcodedelimiter', '#');
			// oder muss das bei den roles sein???
			self::execSql($pdo, "ALTER TABLE %roles% ADD right_payallorders INT(2) NULL");
			self::execSql($pdo, "ALTER TABLE %histuser% ADD right_payallorders INT(2) NULL");

			self::execSqlWithParam($pdo, "UPDATE %roles% SET right_payallorders=?", array(1));
			self::execSqlWithParam($pdo, "UPDATE %histuser% SET right_payallorders=?", array(1));

			return array(true);
		} catch (Exception $ex) {
			return array(false,$e->getMessage());
		}
	}
	public static function upd_2909_2910($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_2910_2911($pdo, $prefix, $dbname) {
		return array(true);
	}
	public static function upd_2911_2912($pdo, $prefix, $dbname) {
		return array(true);
	}

	public static $updateOrder = array(
	    "1.3.0" => array("upd_1300_1301","1.3.1"),
	    "1.3.1" => array("upd_1301_1302","1.3.2"),
	    "1.3.2" => array("upd_1302_1303","1.3.3"),
	    "1.3.3" => array("upd_1303_1304","1.3.4"),
	    "1.3.4" => array("upd_1304_1305","1.3.5"),
	    "1.3.5" => array("upd_1305_1306","1.3.6"),
	    "1.3.6" => array("upd_1306_1307","1.3.7"),
	    "1.3.7" => array("upd_1307_1308","1.3.8"),
	    "1.3.8" => array("upd_1308_1309","1.3.9"),
	    "1.3.9" => array("upd_1309_1310","1.3.10"),
	    "1.3.10" => array("upd_1310_1311","1.3.11"),
	    "1.3.11" => array("upd_1311_1312","1.3.12"),
	    "1.3.12" => array("upd_1312_1313","1.3.13"),
	    "1.3.13" => array("upd_1313_1314","1.3.14"),
	    "1.3.14" => array("upd_1314_1315","1.3.15"),
	    "1.3.15" => array("upd_1315_1316","1.3.16"),
	    "1.3.16" => array("upd_1316_1317","1.3.17"),
	    "1.3.17" => array("upd_1317_1318","1.3.18"),
	    "1.3.18" => array("upd_1318_1319","1.3.19"),
	    "1.3.19" => array("upd_1319_1320","1.3.20"),
	    "1.3.20" => array("upd_1320_1321","1.3.21"),
	    "1.3.21" => array("upd_1321_1322","1.3.22"),
	    "1.3.22" => array("upd_1322_1323","1.3.23"),
	    "1.3.23" => array("upd_1323_1324","1.3.24"),
	    "1.3.24" => array("upd_1324_1325","1.3.25"),
	    "1.3.25" => array("upd_1325_1326","1.3.26"),
	    "1.3.26" => array("upd_1326_1400","1.4.0"),
	    
	    "1.4.0" => array("upd_1400_1401","1.4.1"),
	    "1.4.1" => array("upd_1401_1402","1.4.2"),
	    "1.4.2" => array("upd_1402_1403","1.4.3"),
	    "1.4.3" => array("upd_1403_1404","1.4.4"),
	    "1.4.4" => array("upd_1404_1405","1.4.5"),
	    "1.4.5" => array("upd_1405_1406","1.4.6"),
	    "1.4.6" => array("upd_1406_1407","1.4.7"),
	    "1.4.7" => array("upd_1407_1408","1.4.8"),
	    "1.4.8" => array("upd_1408_1409","1.4.9"),
	    "1.4.9" => array("upd_1409_1410","1.4.10"),
	    "1.4.10" => array("upd_1410_1411","1.4.11"),
	    "1.4.11" => array("upd_1411_1412","1.4.12"),
	    "1.4.12" => array("upd_1412_1413","1.4.13"),
	    "1.4.13" => array("upd_1413_1414","1.4.14"),
	    "1.4.14" => array("upd_1414_1415","1.4.15"),
	    "1.4.15" => array("upd_1415_1416","1.4.16"),
	    "1.4.16" => array("upd_1416_1417","1.4.17"),
	    "1.4.17" => array("upd_1417_1418","1.4.18"),
	    "1.4.18" => array("upd_1418_1500","1.5.0"),
	    "1.5.0"  => array("upd_1500_1501","1.5.1"),
	    "1.5.1"  => array("upd_1501_1502","1.5.2"),
	    "1.5.2"  => array("upd_1502_1503","1.5.3"),
	    "1.5.3"  => array("upd_1503_1504","1.5.4"),
	    "1.5.4"  => array("upd_1504_1505","1.5.5"),
	    "1.5.5"  => array("upd_1505_1506","1.5.6"),
	    "1.5.6"  => array("upd_1506_1507","1.5.7"),
	    "1.5.7"  => array("upd_1507_1508","1.5.8"),
	    "1.5.8"  => array("upd_1508_1509","1.5.9"),
	    "1.5.9"  => array("upd_1509_1510","1.5.10"),
	    "1.5.10"  => array("upd_1510_1511","1.5.11"),
	    "1.5.11"  => array("upd_1511_1512","1.5.12"),
	    "1.5.12"  => array("upd_1512_1513","1.5.13"),
	    "1.5.13"  => array("upd_1513_1514","1.5.14"),
	    "1.5.14"  => array("upd_1514_1515","1.5.15"),
	    "1.5.15"  => array("upd_1515_1516","1.5.16"),
	    "1.5.16"  => array("upd_1516_1517","1.5.17"),
	    "1.5.17"  => array("upd_1517_1518","1.5.18"),
	    "1.5.18"  => array("upd_1518_1519","1.5.19"),
	    "1.5.19"  => array("upd_1519_1520","1.5.20"),
	    "1.5.20"  => array("upd_1520_1521","1.5.21"),
	    "1.5.21"  => array("upd_1521_1522","1.5.22"),
	    "1.5.22"  => array("upd_1522_1523","1.5.23"),
	    "1.5.23"  => array("upd_1523_1524","1.5.24"),
	    "1.5.24"  => array("upd_1524_1525","1.5.25"),
	    "1.5.25"  => array("upd_1525_1526","1.5.26"),
	    "1.5.26"  => array("upd_1526_1527","1.5.27"),
	    "1.5.27"  => array("upd_1527_1528","1.5.28"),
	    "1.5.28"  => array("upd_1528_1529","1.5.29"),
	    "1.5.29"  => array("upd_1529_1530","1.5.30"),
	    "1.5.30"  => array("upd_1530_1531","1.5.31"),
	    "1.5.31"  => array("upd_1531_1532","1.5.32"),
	    "1.5.32"  => array("upd_1532_1533","1.5.33"),
	    "1.5.33"  => array("upd_1533_1600","1.6.0"),
	    "1.6.0"  => array("upd_1600_1601","1.6.1"),
	    "1.6.1"  => array("upd_1601_1602","1.6.2"),
	    "1.6.2"  => array("upd_1602_1603","1.6.3"),
	    "1.6.3"  => array("upd_1603_1604","1.6.4"),
	    "1.6.4"  => array("upd_1604_1605","1.6.5"),
	    "1.6.5"  => array("upd_1605_1606","1.6.6"),
	    "1.6.6"  => array("upd_1606_1607","1.6.7"),
	    "1.6.7"  => array("upd_1607_1608","1.6.8"),
	    "1.6.8"  => array("upd_1608_1609","1.6.9"),
	    "1.6.9"  => array("upd_1609_1610","1.6.10"),
	    "1.6.10" => array("upd_1610_2000","2.0.0"),
	    "2.0.0" => array("upd_2000_2001","2.0.1"),
	    "2.0.1" => array("upd_2001_2002","2.0.2"),
	    "2.0.2" => array("upd_2002_2003","2.0.3"),
	    "2.0.3" => array("upd_2003_2004","2.0.4"),
	    "2.0.4" => array("upd_2004_2005","2.0.5"),
	    "2.0.5" => array("upd_2005_2006","2.0.6"),
	    "2.0.6" => array("upd_2006_2007","2.0.7"),
	    "2.0.7" => array("upd_2007_2008","2.0.8"),
	    "2.0.8" => array("upd_2008_2009","2.0.9"),
	    "2.0.9" => array("upd_2009_2010","2.0.10"),
	    "2.0.10" => array("upd_2010_2011","2.0.11"),
	    "2.0.11" => array("upd_2011_2012","2.0.12"),
            "2.0.12" => array("upd_2012_2013","2.0.13"),
            "2.0.13" => array("upd_2013_2014","2.0.14"),
            "2.0.14" => array("upd_2014_2015","2.0.15"),
            "2.0.15" => array("upd_2015_2016","2.0.16"),
            "2.0.16" => array("upd_2016_2017","2.0.17"),
            "2.0.17" => array("upd_2017_2018","2.0.18"),
            "2.0.18" => array("upd_2018_2019","2.0.19"),
            "2.0.19" => array("upd_2019_2020","2.0.20"),
            "2.0.20" => array("upd_2020_2021","2.0.21"),
            "2.0.21" => array("upd_2021_2022","2.0.22"),
            "2.0.22" => array("upd_2022_2023","2.0.23"),
            "2.0.23" => array("upd_2023_2024","2.0.24"),
            "2.0.24" => array("upd_2024_2025","2.0.25"),
            "2.0.25" => array("upd_2025_2026","2.0.26"),
            "2.0.26" => array("upd_2026_2027","2.0.27"),
            "2.0.27" => array("upd_2027_2028","2.0.28"),
            "2.0.28" => array("upd_2028_2029","2.0.29"),
            "2.0.29" => array("upd_2029_2100","2.1.0"),
            "2.1.0" => array("upd_2100_2101","2.1.1"),
            "2.1.1" => array("upd_2101_2102","2.1.2"),
            "2.1.2" => array("upd_2102_2103","2.1.3"),
            
            "2.1.3" => array("upd_2103_2200","2.2.0"),
            "2.2.0" => array("upd_2200_2300","2.3.0"),
            "2.3.0" => array("upd_2300_2301","2.3.1"),
            "2.3.1" => array("upd_2301_2302","2.3.2"),
            "2.3.2" => array("upd_2302_2303","2.3.3"),
            "2.3.3" => array("upd_2303_2304","2.3.4"),
            "2.3.4" => array("upd_2304_2305","2.3.5"),
            "2.3.5" => array("upd_2305_2306","2.3.6"),
            "2.3.6" => array("upd_2306_2307","2.3.7"),
            "2.3.7" => array("upd_2307_2308","2.3.8"),
            "2.3.8" => array("upd_2308_2309","2.3.9"),
            "2.3.9" => array("upd_2309_2310","2.3.10"),
            "2.3.10" => array("upd_2310_2311","2.3.11"),
            "2.3.11" => array("upd_2311_2400","2.4.0"),
            "2.4.0" => array("upd_2400_2401","2.4.1"),
            "2.4.1" => array("upd_2401_2402","2.4.2"),
            "2.4.2" => array("upd_2402_2403","2.4.3"),
            "2.4.3" => array("upd_2403_2404","2.4.4"),
            "2.4.4" => array("upd_2404_2405","2.4.5"),
            "2.4.5" => array("upd_2405_2406","2.4.6"),
            "2.4.6" => array("upd_2406_2407","2.4.7"),
            "2.4.7" => array("upd_2407_2408","2.4.8"),
            "2.4.8" => array("upd_2408_2409","2.4.9"),
            "2.4.9" => array("upd_2409_2410","2.4.10"),
            "2.4.10" => array("upd_2410_2411","2.4.11"),
            "2.4.11" => array("upd_2411_2500","2.5.0"),
            "2.5.0" => array("upd_2500_2501","2.5.1"),
            "2.5.1" => array("upd_2501_2502","2.5.2"),
            "2.5.2" => array("upd_2502_2503","2.5.3"),
            "2.5.3" => array("upd_2503_2504","2.5.4"),
            "2.5.4" => array("upd_2504_2505","2.5.5"),
            "2.5.5" => array("upd_2505_2506","2.5.6"),
            "2.5.6" => array("upd_2506_2507","2.5.7"),
            "2.5.7" => array("upd_2507_2600","2.6.0"),
            "2.6.0" => array("upd_2600_2700","2.7.0"),
            "2.7.0" => array("upd_2700_2701","2.7.1"),
            "2.7.1" => array("upd_2701_2702","2.7.2"),
            "2.7.2" => array("upd_2702_2703","2.7.3"),
            "2.7.3" => array("upd_2703_2704","2.7.4"),
            "2.7.4" => array("upd_2704_2705","2.7.5"),
            "2.7.5" => array("upd_2705_2706","2.7.6"),
            "2.7.6" => array("upd_2706_2800","2.8.0"),
            "2.8.0" => array("upd_2800_2801","2.8.1"),
            "2.8.1" => array("upd_2801_2802","2.8.2"),
            "2.8.2" => array("upd_2802_2803","2.8.3"),
            "2.8.3" => array("upd_2803_2804","2.8.4"),
            "2.8.4" => array("upd_2804_2805","2.8.5"),
            "2.8.5" => array("upd_2805_2806","2.8.6"),
            "2.8.6" => array("upd_2806_2807","2.8.7"),
            "2.8.7" => array("upd_2807_2808","2.8.8"),
            "2.8.8" => array("upd_2808_2809","2.8.9"),
            "2.8.9" => array("upd_2809_2810","2.8.10"),
            "2.8.10" => array("upd_2810_2811","2.8.11"),
            "2.8.11" => array("upd_2811_2812","2.8.12"),
            "2.8.12" => array("upd_2812_2813","2.8.13"),
            "2.8.13" => array("upd_2813_2814","2.8.14"),
            "2.8.14" => array("upd_2814_2815","2.8.15"),
            "2.8.15" => array("upd_2815_2816","2.8.16"),
			"2.8.16" => array("upd_2816_2817","2.8.17"),
			"2.8.17" => array("upd_2817_2818","2.8.18"),
			"2.8.18" => array("upd_2818_2819","2.8.19"),
			"2.8.19" => array("upd_2819_2820","2.8.20"),
			"2.8.20" => array("upd_2820_2821","2.8.21"),
			"2.8.21" => array("upd_2821_2822","2.8.22"),
			"2.8.22" => array("upd_2822_2823","2.8.23"),
			"2.8.23" => array("upd_2823_2824","2.8.24"),
			"2.8.24" => array("upd_2824_2900","2.9.0"),
			"2.9.0" => array("upd_2900_2901","2.9.1"),
			"2.9.1" => array("upd_2901_2902","2.9.2"),
			"2.9.2" => array("upd_2902_2903","2.9.3"),
			"2.9.3" => array("upd_2903_2904","2.9.4"),
			"2.9.4" => array("upd_2904_2905","2.9.5"),
			"2.9.5" => array("upd_2905_2906","2.9.6"),
			"2.9.6" => array("upd_2906_2907","2.9.7"),
			"2.9.7" => array("upd_2907_2908","2.9.8"),
			"2.9.8" => array("upd_2908_2909","2.9.9"),
			"2.9.9" => array("upd_2909_2910","2.9.10"),
			"2.9.10" => array("upd_2910_2911","2.9.11"),
			"2.9.11" => array("upd_2911_2912","2.9.12")
	);
	
	public static function runUpdateProcess($pdo,$prefix, $dbname, $untilVersion,$checkValidVersion, $doInprogressEvents) {
		$curversion = CommonUtils::getConfigValue($pdo, "version", "0");
		
		if ($checkValidVersion && !array_key_exists($curversion, self::$updateOrder) && ($curversion != "2.9.12")) {
			return array("status" => "ERROR","msg" => "Versionsupdate von Quellversion $curversion nicht möglich.");
		}
		
		$params = [$pdo, $prefix, $dbname];
		$updateCompleted = false;
		while (!$updateCompleted) {
			$curversion = CommonUtils::getConfigValue($pdo, "version", "0");
			
			if ($curversion == $untilVersion) {
				// final version reached
                                error_log('Final version reached in update process');
				break;
			}
			
			if (!array_key_exists($curversion, self::$updateOrder)) {
                                error_log("Stopping update process for $curversion because no callback found");
				$updateCompleted = true;
				break;
			}

                        if ($doInprogressEvents) {
                                self::sendInProgressMsg("Version " . $curversion . " updaten", true);
                        }
			$updContext = self::$updateOrder[$curversion];
			$updFct = $updContext[0];
			set_time_limit(60*60);
                        error_log("Calling method $updFct to update db");
			$ret = call_user_func_array(["Version", $updFct], $params);
			if (!$ret[0]) {
                                error_log("Error in update process steps in $updFct");
				return array("status" => "ERROR","msg" => $ret[1]);
			}
			self::updateVersion($pdo, $updContext[1]);
		}
                error_log('Version update completed');
		return array("status" => "OK");
	}
}
