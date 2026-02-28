<?php

require_once ('dbutils.php');

defined('T_ORDER') || define ('T_ORDER', 0);
defined('T_BILL') || define ('T_BILL', 1);
defined('T_REMOVE') || define ('T_REMOVE', 2);
defined('T_BILLSTORNO') || define ('T_BILLSTORNO', 3);
defined('T_BILLSTORNOREMOVE') || define ('T_BILLSTORNOREMOVE', 4);
defined('T_FROM_TABLE') || define ('T_FROM_TABLE', 5);
defined('T_TO_TABLE') || define ('T_TO_TABLE', 6);
defined('T_BILLORDERBILL') || define ('T_BILLORDERBILL', 7);
defined('T_BILLORDERBILLSTORNO') || define ('T_BILLORDERBILLSTORNO', 8);
defined('T_BILLORDERBILLSTORNOREMOVE') || define ('T_BILLORDERBILLSTORNOREMOVE', 9);

defined('RANGE_ORDER') || define ('RANGE_ORDER', 1);
defined('RANGE_BILL') || define ('RANGE_BILL', 2);
defined('RANGE_CLOSING') || define ('RANGE_CLOSING', 3);

defined('ALLOW_TRANSACTIONS') || define ('ALLOW_TRANSACTIONS',3);
defined('DENY_TRANSACTIONS') || define ('DENY_TRANSACTIONS',4);

defined('KIND_FOOD') || define ('KIND_FOOD',0);
defined('KIND_DRINKS') || define ('KIND_DRINKS',1);

class CommonUtils {
	var $dbutils;
	private static $plugins = null;
	
	function __construct() {
		$this->dbutils = new DbUtils();
	}
	
	public static function setPluginConfig($plugins) {
	    self::$plugins = $plugins;
	}
	
	public static $g_units_arr = 
		array(
		    array("text" => "Stück","value" => 0,"id" => "piece"),
		    array("text" => "Eingabe","value" => 1,"id" => "piece"),
		    array("text" => "kg","value" => 2,"id" => "kg"),
		    array("text" => "gr","value" => 3,"id" => "gr"),
		    array("text" => "mg","value" => 4,"id" => "mg"),
		    array("text" => "l","value" => 5,"id" => "l"),
		    array("text" => "ml","value" => 6,"id" => "ml"),
		    array("text" => "m","value" => 7,"id" => "m"),
		    array("text" => "EinzweckgutscheinKauf","value" => 8,"id" => "EGK"),
		    array("text" => "EinzweckgutscheinEinl","value" => 9,"id" => "EGE"),
                    array("text" => "h","value" => 10,"id" => "h")
		);
	
	public static function g_units_export_arr() {
		return array();
	}

        public static function getPermissions($pdo) {
                if (!is_null($pdo)) {
                        $cat1viewname = self::getConfigValue($pdo, "cat1viewname", "Küche");
                        $cat2viewname = self::getConfigValue($pdo, "cat2viewname", "Bar");
                } else {
                        $cat1viewname = "Küche";
                        $cat2viewname = "Bar";
                }
                
                $ret = array(
                        array('name' => 'is_admin', 'flag' => 'normal', 'rolename' => array("Administrator", "Administrator", "Administrador")),
                        array('name' => 'right_waiter', 'flag' => 'normal', 'view' => 'waiter.html', 'rolename' => array("Kellner", "Waiter", "Camarero")), // waiterdesktop.php
                        array('name' => 'right_kitchen', 'flag' => 'normal', 'view' => 'kitchen.html', 'menu' => array($cat1viewname,$cat1viewname,$cat1viewname), 'rolename' => array($cat1viewname,$cat1viewname,$cat1viewname)),
                        array('name' => 'right_bar', 'flag' => 'normal', 'view' => 'bar.html', 'menu' => array($cat2viewname,$cat2viewname,$cat2viewname), 'rolename' => array($cat2viewname,$cat2viewname,$cat2viewname)),
                        array('name' => 'right_supply', 'flag' => 'normal', 'view' => 'supplydesk.html', 'menu' => array("Bereitstellung", "Supply desk", "Preparado"), 'rolename' => array("Bereitstellung", "Supplydesk", "Preparados")),
                        array('name' => 'right_paydesk', 'flag' => 'normal', 'view' => 'paydesk.html', 'rolename' => array("Kasse", "Paydesk", "Caja")),
                        array('name' => 'right_statistics', 'flag' => 'normal', 'view' => 'reports.html', 'menu' => array("Statistik", "Statistics", "Estadisticas"), 'rolename' => array("Statistik", "Statistics", "Estadisticas")),
                        array('name' => 'right_bill', 'flag' => 'normal', 'view' => 'bill.html', 'menu' => array("Kassenbons", "Receipts", "Tiques"), 'rolename' => array("Kassenbons", "Receipts", "Tiques")),
                        array('name' => 'right_products', 'flag' => 'normal', 'view' => 'products.html', 'menu' => array("Artikel", "Articles", "Productos"), 'rolename' => array("Artikel", "Articles", "Productos")),
                        array('name' => 'right_reservation', 'flag' => 'normal', 'view' => 'reservation.html', 'menu' => array("Reservierung", "Reservation", "Reserva"), 'rolename' => array("Reservierung", "Reservation", "Reserva")),
                        array('name' => 'right_changeprice', 'flag' => 'normal', 'rolename' => array("Preisänderung während Bestellung", "Change price during ordering", "Modificar precio durante ordenar")),
                        array('name' => 'right_customers', 'flag' => 'normal', 'view' => 'customers.html', 'menu' => array("Gäste", "Guests", "Clientes"), 'rolename' => array("Gäste", "Guests", "Clientes")),
                        array('name' => 'right_pickups', 'flag' => 'normal', 'view' => 'pickups.html', 'menu' => array("Abholanzeige", "Pickup display", "Vista de recogidos"), 'rolename' => array("Abholanzeige", "Pickup Display", "Vista de Recogido")),
                        array('name' => 'right_closing', 'flag' => 'normal', 'view' => 'manager.html', 'rolename' => array("Tageserfassung", "Closing", "Cerrar día")),
						array('name' => 'right_payallorders', 'flag' => 'normal', 'view' => 'manager.html', 'rolename' => array("Abrechnung Bestellungen anderer Kellner", "Payment all orders", "Pagamiento de todos los pedidos")),
                        array('name' => 'right_dash', 'flag' => 'normal', 'view' => 'dash.php', 'menu' => array("Dashboard", "Dashboard", "Dashboard"), 'rolename' => array("Dashboard", "Dashboard", "Dashboard")),
                        array('name' => 'right_timetracking', 'flag' => 'normal', 'view' => 'timetracking.html', 'menu' => array("Zeiterfassung", "Time tracking", "Tiempos"), 'rolename' => array("Zeiterfassung", "Time Tracking", "Tiempos de Trabajo")),
                        array('name' => 'right_timemanager', 'flag' => 'normal', 'rolename' => array("Zeitmanagement", "Time management", "Administrar Tiempos de Trabajo")),
                        array('name' => 'right_tasks', 'flag' => 'normal', 'view' => 'tasks.html', 'menu' => array("Aufgaben", "Tasks", "Tareas"), 'rolename' => array("Aufgaben", "Tasks", "Tareas")),
                        array('name' => 'right_tasksmanagement', 'flag' => 'normal', 'rolename' => array("Aufgabenmanagement", "Tasks management", "Administrar tareas")),
                        array('name' => 'right_delivery', 'flag' => 'normal', 'view' => 'ordersview.php', 'menu' => array("Lieferaufträge", "Delivery orders", "Órdenes de entrega"), 'rolename' => array("Lieferaufträge", "Delivery orders", "Órdenes de entrega")),
                        array('name' => 'right_customersview', 'flag' => 'normal', 'view' => 'customersview.php', 'menu' => array("Kundenansicht", "Customer view", "Vista para clientes"), 'rolename' => array("Kundenansicht", "Customer view", "Vista para clientes")),
                        array('name' => 'right_manager', 'flag' => 'normal', 'view' => 'manager.html', 'rolename' => array("Administration", "Administration", "Administración")),
                        array('name' => 'right_rating', 'flag' => 'special', 'view' => 'rating.html', 'menu' => array("Bewertung", "Rating", "Valoración"), 'rolename' => array("Bewertung", "Rating", "Valoración")),
                        array('name' => 'right_cashop', 'flag' => 'normal', 'rolename' => array("Barein-/auslage", "Cash operations", "Insertar dinero"))
                );
                return $ret;
        }

        function verifyLastBillId($pdo,$nextIdToUse) {
		if ($nextIdToUse == 1) {
			return true;
		}
		if (is_null($pdo)) {
			$pdo = $this->dbutils->openDbAndReturnPdo();
		}
		$nextIdToUse = intval($nextIdToUse);
		$sql = "SELECT value,signature FROM %work% WHERE item=?";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array("lastbillid"));
		$row =$stmt->fetchObject();
		$lastBillid = intval($row->value);
		$lastBillInc = $lastBillid+1;
		
		if ($lastBillInc != $nextIdToUse) {
			return false;
		} else {
			$sql = "SELECT id FROM %bill% WHERE id=?";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($nextIdToUse));
			if ($stmt->rowCount() > 0) {
				return false;
			} else {
				// is there a gap or does the previous id exist?
				$sql = "SELECT id FROM %bill% WHERE id=?";
				$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
				$stmt->execute(array($nextIdToUse - 1));
				if ($stmt->rowCount() != 1) {
					return false;
				} else {
					return true;
				}
			}
			
		}
	}

	
	function getKeyFromWorkTable($pdo,$key) {
		$sql = "SELECT signature FROM %work% WHERE item=?";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array($key));
		$row =$stmt->fetchObject();
		return($row->signature);
	}
	
        public static function insertOrUpdateValueInWorkTable($pdo,string $item,string $value) {
                $sql = "SELECT value FROM %work% where item=?";
		$r = self::fetchSqlAll($pdo, $sql, array($item));
                if (count($r) > 0) {
                        $sql = "UPDATE %work% SET value=? WHERE item=?";
			self::execSql($pdo, $sql, array($value,$item));
                } else {
                      $sql = "INSERT INTO %work% (item,value) VALUES(?,?)";
                      self::execSql($pdo, $sql, array($item,$value));  
                }
        }
        
        
        
	public static function setMd5OfLastBillidInWorkTable($pdo) {
		$sql = "SELECT value FROM %work% where item=?";
		$r = self::fetchSqlAll($pdo, $sql, array("lastbillid"));
		if (count($r) > 0) {
			$maxid = $r[0]["value"];
			$signature = md5("B($maxid)");
			$sql = "UPDATE %work% SET signature=? WHERE item=?";
			self::execSql($pdo, $sql, array($signature,"lastbillid"));
		}
	}
        
        private static function setValueInWorkTable($pdo,$item,$value) {
                $sql = "SELECT value FROM %work% where item=?";
		$r = self::fetchSqlAll($pdo, $sql, array($item));
                if (count($r) == 0) {
                        $sql = "INSERT INTO %work% (item,value) VALUES(?,?)";
                        self::execSql($pdo, $sql, array($item,$value));
                } else {
                        $sql = "UPDATE %work% SET value=? WHERE item=?";
                        self::execSql($pdo, $sql, array($value,$item));
                }
        }
        private static function getValueFromWorkTable($pdo,$item,$default) {
                $sql = "SELECT value FROM %work% where item=?";
		$r = self::fetchSqlAll($pdo, $sql, array($item));
                if (count($r) == 0) {
                        return $default;
                } else {
                        return $r[0]['value'];
                }
        }
        
        public static function setSendToShowRoomNecessary($pdo,bool $isNecessary) {#
                if ($isNecessary) {
                        self::setValueInWorkTable($pdo, 'updatesroom', 1);
                } else {
                        self::setValueInWorkTable($pdo, 'updatesroom', 0);
                }
        }
        public static function isSendToShowroomNecessary($pdo):bool {
                $isNecessary = self::getValueFromWorkTable($pdo, 'updatesroom', 0);
                if ($isNecessary == 0) {
                        return false;
                } else {
                        return true;
                }
        }
	
	function setLastBillIdInWorkTable($pdo,$lastBillId) {
		if (is_null($pdo)) {
			$pdo = $this->dbutils->openDbAndReturnPdo();
		}
		
		$signature = md5("B($lastBillId)");
		$sql = "UPDATE %work% SET value=?, signature=? WHERE item=?";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array($lastBillId,$signature,"lastbillid"));
	}
	
	function verifyBill($pdo,$id) {
		if (is_null($pdo)) {
			$pdo = $this->dbutils->openDbAndReturnPdo();
		}

		$sql = "SELECT billdate,brutto,ROUND(netto,2) as netto,userid,IF(tax is not null, tax, '0.00') as tax,signature,status FROM %bill% WHERE id=?";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array($id));
		$row = $stmt->fetchObject();
		
		$billdate =  $row->billdate;
		$brutto = $row->brutto;
		$netto = $row->netto;
		$userid = $row->userid;
		$signature = $row->signature;
		$status = $row->status;

		return(self::verifyBillByValues($pdo,$billdate, $brutto, $netto, $userid, $signature, $status));
	}
	
	public static function verifyBillByValues($pdo,$billdate,$brutto,$netto,$userid,$signature,$status) {
		if ($status == "c") {
			return true;
		}
		
		if (is_null($signature)) {
			return false;
		}
		if (is_null($pdo)) {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
		}

		$brutto = number_format($brutto, 2, ".", '');
		$netto = number_format($netto, 2, ".", '');
		
		$data = "D($billdate)B($brutto)N($netto)T(0)U($userid)";
		$md5OfData = md5($data);
		if ($signature != $md5OfData) {
			return false;
		} else {
			return true;
		}
	}
	
	public static function calcSignaturesForAllBills($pdo) {
		$sql = "SELECT id,billdate,brutto,netto,userid FROM %bill%";
		$r = CommonUtils::fetchSqlAll($pdo, $sql);
		$sql = "UPDATE %bill% SET signature=? WHERE id=?";
		foreach($r as $b) {
			$bruttostr = number_format($b["brutto"], 2, ".", '');
			$nettostr = number_format($b["netto"], 2, ".", '');
			$theTime = $b["billdate"];
			$userid = $b["userid"];
			$data = md5("D($theTime)B($bruttostr)N($nettostr)T(0)U($userid)");
			CommonUtils::execSql($pdo, $sql, array($data,$b["id"]));
		}
	}
		
	public static function calcSignatureForBill($theTime,$brutto,$netto,$userid) {
		// now calculate the signature for the bill entry
		$bruttostr = number_format($brutto, 2, ".", '');
		$nettostr = number_format($netto, 2, ".", '');
		$data = "D($theTime)B($bruttostr)N($nettostr)T(0)U($userid)";
		$signature = md5($data);
		return $signature;
	}
	
	function createGridTableWithSqrtSizeOfButtons ($inputArray) {
		// create a table that is optimal (sqrt-like size)
		$numberOfIcons = count($inputArray); 
		if ($numberOfIcons == 0) {
			// no items to display
			return;
		}
		$numberOfCols = ceil(sqrt($numberOfIcons));
		$porcentageWidth = floor(100/$numberOfCols);
		
		echo '<table class=gridtable>';
		$colcounter = 0;
		for ($index=0;$index<$numberOfIcons;$index++) {
			if ($colcounter == 0) {
				echo "<tr><td>";
			}
			$anEntry = $inputArray[$index];
			$textOfButton = $anEntry["textOfButton"]; #
			$onClickMethod = $anEntry["onClickMethod"]; // With parameters!
			
			$button = '<input type="button" value="' . $textOfButton . '"';			
			$button = $button . ' onclick="' . $onClickMethod . '"';
			$button = $button . ' style="height: 50px; width:' . $porcentageWidth . '%; font-size:20px; background-color:#b3b3c9" />';
			echo $button;
			$colcounter++;
			if ($colcounter == $numberOfCols) {
				$colcounter = 0;
				echo "</tr>";
			}
		}
		echo "</tr>";
		echo "</table>";
	}
	
	
	function createGridTableWithSqrtSizeOfStyleButtons($inputArray) {
		$this->createGridTableWithSqrtSizeOfStyleButtonsAndHeader($inputArray,'','dummy');
	}
	
	function getTableNameFromId($pdo,$tableid) {
		if (is_null($tableid) || ($tableid == 0)) {
			return "-"; // togo
		}
		$sql = "SELECT tableno FROM %resttables% WHERE id=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($tableid));
		$row = $stmt->fetchObject();
		return $row->tableno;
	}
	
	function getCurrentPriceLevel($pdo) {
                $sql = "select PL.id as plid,PL.name as plname from %pricelevel% PL,%config% C where C.name='pricelevel' AND C.setting=PL.id";
                $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		$row = $stmt->fetchObject();
		$pricelevelid = $row->plid;
                $pricelevelname = $row->plname;
		
		return (array("id" => $pricelevelid, "name" => $pricelevelname));
	}
	
	function createGridTableWithSqrtSizeOfStyleButtonsAndHeader ($inputArray,$headline,$headercolor) {
		// create a table that is optimal (sqrt-like size)
		$numberOfIcons = count($inputArray);
		if ($numberOfIcons == 0) {
			// no items to display
			return;
		}
		$numberOfCols = ceil(sqrt($numberOfIcons));
		$porcentageWidth = floor(100.0/$numberOfCols);
	
		echo '<table class=gridtable>';
		
		// Headline
		if ($headline <> '') {
			echo '<tr><th style="background-color:#' . $headercolor . '">' . $headline . '</th>';
		}
		
		$colcounter = 0;
		for ($index=0;$index<$numberOfIcons;$index++) {
			if ($colcounter == 0) {
				echo "<tr><td>";
			}
			$anEntry = $inputArray[$index];
			$textOfButton = $anEntry["textOfButton"]; #
			$onClickMethod = $anEntry["onClickMethod"]; // With parameters!
			$style = $anEntry["style"];
				
			$button = '<input type="button" value="' . $textOfButton . '"';
			$button = $button . ' onclick="' . $onClickMethod . '"';
			$button = $button . ' style="' . $style . '; width:' . $porcentageWidth . '%;" />';
			echo $button;
			$colcounter++;
			if ($colcounter == $numberOfCols) {
				$colcounter = 0;
				echo "</tr>";
			}
		}
		echo "</tr>";
		echo "</table>";
	}
	
	function getCurrency() {
		$pdo = $this->dbutils->openDbAndReturnPdo();
		
		$sql = "SELECT setting from %config% where name='currency'";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute();
		$row =$stmt->fetchObject();
		if ($row != null) {
			return $row->setting;
		} else {
			return "Euro";
		}
	}
	
        private static function checkForSqlArgTypes($argsarr) {
                if (is_null($argsarr)) {
                        return true;
                } else {
                        if (gettype($argsarr) != "array") {
                                return false;
                        } else {
                                foreach($argsarr as $aVal) {
                                        $valtype = gettype($aVal); 
                                        if (($valtype != "NULL") && ($valtype != "integer") && ($valtype != "double") && ($valtype != "string")) {
                                                return false;
                                        }
                                }
                                return true;
                        }
                }
        }
        
	public static function getRowSqlObject($pdo,$sql,$params = null) {
                if (!self::checkForSqlArgTypes($params)) {
                        error_log("SQL shall be called with parameters of wrong type. SQL query: " . $sql);
                        return null;
                } else {
                        $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
                        if (is_null($params)) {
                                $stmt->execute();
                        } else {
                                $stmt->execute($params);
                        }
                        return ($stmt->fetchObject());
                }
	}
	public static function fetchSqlAll($pdo,$sql,$params = null) {
                if (!self::checkForSqlArgTypes($params)) {
                        error_log("SQL shall be called with parameters of wrong type. SQL query: " . $sql);
                        return array();
                } else {
                        $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
                        if (is_null($params)) {
                                $stmt->execute();
                        } else {
                                $stmt->execute($params);
                        }
                        return ($stmt->fetchAll(PDO::FETCH_ASSOC));
                }
	}
        
	public static function execSql($pdo,$sql,$params) {
                if (!self::checkForSqlArgTypes($params)) {
                        error_log("SQL shall be called with parameters of wrong type. SQL query: " . $sql);
                } else {
                        $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
                        if (is_null($params)) {
                                $stmt->execute();
                        } else {
                                $stmt->execute($params);
                        }
                }
	}
	

	public static function executeSqlTransactionQueryBlockWithRetries($pdo,callable $callback, int $maxRetries = 5, int $initialDelayMillis = 200) {
		$retries = 0;
		$delay = $initialDelayMillis;
		while (true) {
			try {
				$pdo->beginTransaction();
				$result = $callback($pdo);
				$pdo->commit();
				return $result;
			} catch (PDOException $e) {
				if ($pdo->inTransaction()) {
					$pdo->rollBack();
				}

				$isDeadlock = $e->getCode() == '40001'; // SQLSTATE code for serialization failure

				if ($isDeadlock && $retries < $maxRetries) {
					$retries++;
					error_log("Deadlock detected. Retrying transaction (attempt $retries of $maxRetries). Error: " . $e->getMessage(). " Retry in $delay milliseconds.");
					usleep($delay * 1000); // Convert milliseconds to microseconds
					$delay *= 2; // Exponential backoff
					continue; // Retry the transaction
				} else {
					throw $e; // Rethrow if it's not a serialization failure or max retries reached
				}
			}
		}
		throw new Exception("Max retries reached for SQL query execution.");	
	}


	public static function getConfigValueStmt($pdo, $stmt, $item, $default) {
		if (gettype($item) != "string") {
			error_log("getconfig shall be called with parameters of wrong type!");
			return null;
		} else {
			$stmt->execute(array($item));
			$row = $stmt->fetchObject();
			if ($row->countid == 0) {
					return $default;
			} else {
					return self::getExistingTableValue($pdo, '%config%','name',$item,'setting');
			}
		}
	}

        private static function getExistingTableValue($pdo,string $table,string $itemcol,string $item,string $valuecol):string {
	    $sql = "SELECT $valuecol as setting FROM $table WHERE $itemcol=?";
	    $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
	    $stmt->execute(array($item));
	    $row = $stmt->fetchObject();
            $value = $row->setting;
            if (!is_string($value)) {
                    return strval($value);
            } else {
                    return $value;
            }
	}
        
        private static function getTableValue($pdo, string $table, string $itemcol, string $valuecol, string $item, ?string $default) {
                $sql = "SELECT count(id) as countid FROM $table WHERE $itemcol=?";
                $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
                $stmt->execute(array($item));
                $row = $stmt->fetchObject();
                if ($row->countid == 0) {
                        return $default;
                } else {
                        return self::getExistingTableValue($pdo, $table,$itemcol,$item,$valuecol);
                }
        }

        public static function getConfigValue($pdo,string $item,?string $default) {
                return self::getTableValue($pdo, '%config%', 'name', 'setting', $item, $default);
	}
        public static function getWorkValue($pdo,string $item,?string $default) {
                return self::getTableValue($pdo, '%work%', 'item', 'value', $item, $default);
	}
	
        public static function updateConfigValueIfNeeded($pdo,$item,$value) {
                $savedConfigValue = CommonUtils::getConfigValue($pdo, $item, '');
                if ($savedConfigValue != $value) {
                        $hist = new HistFiller();
                        $hist->updateConfigInHist($pdo, $item, $value);
                }
        }
        
	public static function getConfigValueAtClosingTime($pdo,$item,$default,$closingid) {
		if (is_null($closingid)) {
			return self::getConfigValue($pdo, $item, $default);
		} else {
			$sql1 = "SELECT MAX(H.id) from %hist% H,%histconfig% C,%config% CO WHERE H.refid=C.id AND (H.action=2 OR H.action=6) AND C.configid=CO.id AND CO.name=? AND H.clsid <= ?";
			$sql = "SELECT setting FROM %histconfig% HCO,%hist% H WHERE H.refid=HCO.id AND H.id=($sql1)";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($item,$closingid));
			$r = $stmt->fetchAll(PDO::FETCH_ASSOC);
			if (count($r) == 0) {
				return $default;
			} else {
				return $r[0]["setting"];
			}
		}
	}
	
	public static function getConfigValueAtDateTime($pdo,$item,$default,$datetime) {
		$sql1 = "SELECT MAX(HC.id) as hcid FROM %hist% H, %histconfig% HC, %config% C WHERE date < ? and H.refid=HC.id AND HC.configid=C.id AND (H.action=2 OR H.action=6) AND C.name=?";
		$sql = "SELECT setting FROM %histconfig% where id=($sql1)";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($datetime,$item));
			$r = $stmt->fetchAll(PDO::FETCH_ASSOC);
			if (count($r) == 0) {
				return $default;
			} else {
				return $r[0]["setting"];
			}
	}
        
        public static function setTransactionSerializable($pdo) {
                CommonUtils::execSql($pdo, 'SET TRANSACTION ISOLATION LEVEL SERIALIZABLE',null);
        }
        public static function resetTransactionIsolationLevel($pdo) {
                CommonUtils::execSql($pdo, 'SET TRANSACTION ISOLATION LEVEL REPEATABLE READ',null);
        }
	
	public static function callPlugin($pdo,$fct,$condition) {
	    try {
		if (!is_null(self::$plugins)) {
		    if (array_key_exists($fct,self::$plugins)) {
			$plugin = self::$plugins->$fct;
			if (($plugin->execution) === $condition) {
			    $cls = $plugin->PluginClass;
			    $fct=$plugin->PluginFct;
			    $call = "Plugin\\$cls::$fct";
			    call_user_func($call,$pdo);
			    return true;
			}
		    }
		}
	    } catch(Exception $e) { }
	    return false;
	}
	
	public static function log($pdo, $component, $message) {
		$dblog = self::getConfigValue($pdo, "dblog", 1);
		if ($dblog == 1) {
			date_default_timezone_set(DbUtils::getTimeZoneDb($pdo));
			$currentTime = date('Y-m-d H:i:s');
			$sql = "INSERT INTO %log% (date,component,message) VALUES(?,?,?)";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($currentTime, $component, $message));
		}
	}

	public static function getLog($pdo) {
	    $sql = "SELECT date,component,message FROM %log%";
	    $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
	    $stmt->execute();
	    $result = $stmt->fetchAll();
	    $txt = "";
	    foreach ($result as $aLogLine) {
		$txt .= $aLogLine["date"] . ";" . $aLogLine["component"] . ";" . $aLogLine["message"] . "\n";
	    }
	    return $txt;
	}
	
	public static function getLastLog($pdo) {
	    $sql = "SELECT date,component,message FROM %log% WHERE DATE_SUB(NOW(),INTERVAL 2 HOUR) <= date";
	    $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
	    $stmt->execute();
	    $result = $stmt->fetchAll();
	    $txt = "";
	    foreach ($result as $aLogLine) {
		$txt .= $aLogLine["date"] . ";" . $aLogLine["component"] . ";" . $aLogLine["message"] . "\n";
	    }
	    return $txt;
	}
	
	public static function keepOnlyLastLog($pdo) {
	    $sql = "DELETE FROM %log% WHERE DATE_SUB(NOW(),INTERVAL 2 HOUR) > date";
	    $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
	    $stmt->execute();
	}
	
	public static function strEndsWith($haystack, $needle)
	{
		return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
	}
	
	public static function startsWith($aText, $needle)
        {
                return $needle === "" || strpos($aText, $needle) === 0;
        }
        public static function endsWith($aText, $needle) {
                $length = strlen($needle);
                if (!$length) {
                        return true;
                }
                return substr($aText, -$length) === $needle;
        }

        public static function isUnitOfAmountTypeNotPieceNotVoucher(int $unit):bool {
                if ((($unit > 1) && ($unit < 8)) || ($unit == 10)) {
                        return true;
                } else {
                        return false;
                }
        }
        
        public static function isUnitOfAmountTypeNotVoucher(int $unit): bool {
                if (($unit < 8) || ($unit == 10)) {
                        return true;
                } else {
                        return false;
                }
        }
        
	public static function caseOfSqlUnitSelection($pdo) {
		$decpoint = htmlspecialchars(CommonUtils::getConfigValue($pdo, "decpoint", "."));
		$unit = "CASE ";
		foreach(CommonUtils::$g_units_arr as $aUnit) {
                       
                        if (self::isUnitOfAmountTypeNotPieceNotVoucher($aUnit["value"])) {
				$unit .=  " WHEN Q.unit='" . $aUnit["value"] . "' THEN CONCAT(REPLACE(unitamount,'.','$decpoint'),'" . $aUnit["text"] . "',' ') "; 
			}
		}
		$unit .= " ELSE '' ";
		$unit .= "END";
		return $unit;
	}
	
	public static function scaleImg($fn,$maxDim) {
		list($width, $height, $type, $attr) = getimagesize($fn);
		$size = getimagesize($fn);
		$ratio = $size[0] / $size[1]; // width/height
		if ($ratio > 1) {
			$width = $maxDim;
			$height = $maxDim / $ratio;
		} else {
			$width = $maxDim * $ratio;
			$height = $maxDim;
		}
		$src = imagecreatefromstring(file_get_contents($fn));
                if ($src === false) {
                        error_log("imagecreatefromstring failed");
                }
		$dst = imagecreatetruecolor($width, $height);
                if ($dst === false) {
                        error_log("imagecreatetruecolor failed");
                }
		$enableAlphaStatus = imagealphablending($dst, false);
                if ($enableAlphaStatus === false) {
                        error_log("imagecreatetruecolor failed");
                }
		$imgSaveAlphaStatus = imagesavealpha($dst, true);
                if ($imgSaveAlphaStatus === false) {
                        error_log("imagesavealpha failed");
                }
		$transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
                if ($transparent === false) {
                        error_log("imagecolorallocatealpha failed");
                }
		$rectStatus = imagefilledrectangle($dst, 0, 0, $width, $height, $transparent);
                if (!$rectStatus) {
                        error_log("Fill Rectangle in image failed");
                }
		$resampleStatus = imagecopyresampled($dst, $src, 0, 0, 0, 0, $width, $height, $size[0], $size[1]);
                if (!$resampleStatus) {
                        error_log("Resampling of image failed");
                }
		imagedestroy($src);
		ob_start();
		imagepng($dst); // adjust format as needed
		$imagedata = ob_get_contents();
		ob_end_clean();
		imagedestroy($dst);
		return array("img" => $imagedata,"w" => $width,"h" => $height);
	}

	public static function getFirstSqlQuery($pdo,$sql,$params,$default) {
		$result = self::fetchSqlAll($pdo, $sql, $params);
		if (count($result) > 0) {
			return $result[0]["value"];
		} else {
			return $default;
		}
	}
	
	public static function canMasterDataBeChanged($pdo) {
		$sql = "SELECT COUNT(id) as countid FROM %queue% WHERE isclosed is null OR isclosed='0'";
		$res = CommonUtils::fetchSqlAll($pdo, $sql);
		if (intval($res[0]["countid"]) > 0) {
			return false;
		} else {
			return true;
		}
	}
	
	public static function checkRights($command,$rights) {
		if (session_id() == '') {
			session_start();
		}
		if (!array_key_exists($command, $rights)) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_COMMAND_NOT_FOUND, "msg" => ERROR_COMMAND_NOT_FOUND_MSG));
			return false;
		}
		$cmdRights = $rights[$command];
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
				if ($_SESSION['is_admin'] == false) {
					echo json_encode(array("status" => "ERROR", "code" => ERROR_COMMAND_NOT_ADMIN, "msg" => ERROR_COMMAND_NOT_ADMIN_MSG));
					return false;
				}
			}
		}
		if (!is_null($cmdRights["rights"])) {
			foreach ($cmdRights["rights"] as $aRight) {
				if ($aRight == 'manager_or_admin') {
					if (($_SESSION['is_admin']) || ($_SESSION['right_manager'])) {
						return true;
					}
				} else if ($aRight == 'dash') {
					if ($_SESSION['right_dash']) {
						return true;
					}
				}
			}
			echo json_encode(array("status" => "ERROR", "code" => ERROR_NOT_AUTHOTRIZED, "msg" => ERROR_NOT_AUTHOTRIZED_MSG));
			return false;
		}
		return true;
	}
	
	public static function base64_encode_url($string) {
		return rtrim( strtr( base64_encode( $string ), '+/', '-_'), '=');
	}
	public static function base64_decode_url($string) {
		return base64_decode(str_replace(['-', '_'], ['+', '/'], $string));
	}

	public static function referenceValueInTseValuesTable($pdo,$tsevalue) {
		$sql = "SELECT id FROM %tsevalues% WHERE textvalue=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($tsevalue));
		if (count($result) == 0) {
			$sql = "INSERT INTO %tsevalues% (textvalue) VALUES(?)";
			CommonUtils::execSql($pdo, $sql, array($tsevalue));
			return $pdo->lastInsertId();
		} else {
			return $result[0]["id"];
		}
	}
	
	public static function outputEmptyImage() {
		header("Content-Type: image/png");
		$my_img = imagecreate( 1,1 );
		$background = imagecolorallocate( $my_img, 255, 255, 255 );
		$black = imagecolorallocate($my_img, 0, 0, 0);
		imagecolortransparent($my_img, $black);
		imagepng( $my_img );
		imagecolordeallocate( $my_img, $background );
		imagecolordeallocate( $my_img, $black );
		imagedestroy( $my_img );
	}
	
	public static function outputWideEmptyImage() {
		header("Content-Type: image/png");
		$my_img = imagecreate( 1000,10 );
		$background = imagecolorallocate( $my_img, 255, 255, 255 );
		$black = imagecolorallocate($my_img, 0, 0, 0);
		imagecolortransparent($my_img, $black);
		imagepng( $my_img );
		imagecolordeallocate( $my_img, $background );
		imagecolordeallocate( $my_img, $black );
		imagedestroy( $my_img );
	}
	

	public static function getMasterDataAtCertainDateTime($pdo,$thedatetime,$templatename) {
		$sql = "SELECT H.date from %hist% H, %histconfig% HC, %config% C WHERE HC.configid=C.id AND C.name='guesttheme' and H.refid=HC.id AND H.action='2' ORDER BY H.date";
		$resut = CommonUtils::fetchSqlAll($pdo, $sql);
		$dateOf2_0_0 = $resut[0]["date"];
                
		$sql = "SELECT H.date from %hist% H, %histconfig% HC, %config% C "
                        . "WHERE HC.configid=C.id AND C.name='version' and HC.setting='2.3.0' and H.refid=HC.id AND H.refid=HC.id "
                        . "and (H.action='2' or H.action='6')";
		$resut = CommonUtils::fetchSqlAll($pdo, $sql);
		$dateOf2_3_0 = $resut[0]["date"];
                
                date_default_timezone_set(DbUtils::getTimeZoneDb($pdo));
                if (is_null($dateOf2_3_0)) {
			$dateOf2_3_0 = date('Y-m-d H:i:s');
                }
                if (is_null($dateOf2_0_0)) {
                        $dateOf2_0_0 = date('Y-m-d H:i:s');
                }
                
		$systemParams = array(
		    array("companyinfo","2010-01-01 00:00:00"),
		    array("hosttext","2010-01-01 00:00:00"),
		    array("uid",$dateOf2_0_0),
		    array("sn",$dateOf2_0_0),
		    array("systemid",$dateOf2_0_0),
		    array($templatename,$dateOf2_3_0),
		    array("cashtemplate",$dateOf2_0_0),
		    array("coinvalname",$dateOf2_0_0),
		    array("notevalname",$dateOf2_0_0),
                    array("billlanguage",$dateOf2_0_0),
		    
		    array("dsfinvk_name",$dateOf2_0_0),
		    array("dsfinvk_street",$dateOf2_0_0),
		    array("dsfinvk_postalcode",$dateOf2_0_0),
		    array("dsfinvk_city",$dateOf2_0_0),
		    array("dsfinvk_country",$dateOf2_0_0),
		    array("dsfinvk_stnr",$dateOf2_0_0),
		    array("dsfinvk_ustid",$dateOf2_0_0),
		    
		    array("version","2010-01-01 00:00:00"));
		
		$sql = "SELECT setting FROM %histconfig% HC where id=(";
		$sql .= "SELECT MAX(HC.id) as maxid from %hist% H, %histconfig% HC, %config% C WHERE HC.configid=C.id AND C.name=? and H.refid=HC.id";
		$sql .= "  AND (H.action='2' OR H.action='6') ";
		$sql .= "  AND H.date <= GREATEST(?,?)";
		$sql .= ")";
		
		$out = array();
		
		foreach ($systemParams as $aParam) {
			$theParamName = $aParam[0];
			$minDate = $aParam[1];
			$settingResult = CommonUtils::fetchSqlAll($pdo, $sql, array($theParamName,$thedatetime,$minDate));

			if (($theParamName != 'version') && (is_null($settingResult) || count($settingResult) == 0)) {
				$settingEntry = ['setting' => CommonUtils::getConfigValue($pdo, $theParamName, null)];
				$settingResult = array($settingEntry);
				error_log("History broken, master data not available! Take current value for $theParamName as fallback");
			}

			if ($theParamName == $templatename) {
				$out['template'] = $settingResult[0]["setting"];
			} else if (($theParamName == 'version') && (count($settingResult) == 0)) {
			} else {
				if (count($settingResult) == 0) {
					$out[$theParamName] = null;
				} else {
					$out[$theParamName] = $settingResult[0]["setting"];
				}
			}
		}
		
		$out = self::repairMissingConfig($pdo,$out,"sn");
		$out = self::repairMissingConfig($pdo,$out,"uid");
		$out = self::repairMissingConfig($pdo,$out,"systemid");
		$out = self::repairMissingConfig($pdo,$out,"hosttext");
		
		return $out;
	}
	
	private static function repairMissingConfig($pdo,array $inputArr, string $item):array {
		if (is_null($inputArr[$item])) {
			$inputArr[$item] = CommonUtils::getConfigValue($pdo,$item,'');
		}
		return $inputArr;
	}

	public static function outTransImage() {
		$name = '../img/trans.png';
		$fp = fopen($name, 'rb');

		header("Content-Type: image/png");
		header("Content-Length: " . filesize($name));

		fpassthru($fp);
	}
	
	public static function getTaxesArray($pdo) {
		$normaltax = CommonUtils::getConfigValue($pdo, 'tax', 19.00);
		$togotax = CommonUtils::getConfigValue($pdo, 'togotax', 7.00);
		$taxes = array(
		    array("key" => 1, "value" => $normaltax, "name" => "Allgemeiner Steuersatz (§ 12 Abs. 1 UStG)"),
		    array("key" => 2, "value" => $togotax, "name" => "Ermäßigter Steuersatz (§ 12 Abs. 2 UStG)"),
		    array("key" => 3, "value" => 10.70, "name" => "Durchschnittsatz (§ 24 Abs. 1 Nr. 3 UStG) übrige Fälle"),
		    array("key" => 4, "value" => 5.50, "name" => "Durchschnittsatz (§ 24 Abs. 1 Nr. 1 UStG)"),
		    array("key" => 5, "value" => 0.00, "name" => "Nicht Steuerbar"),
		    array("key" => 6, "value" => 0.00, "name" => "Umsatzsteuerfrei"),
		    array("key" => 7, "value" => 0.00, "name" => "UmsatzsteuerNichtErmittelbar"),
		    array("key" => 11, "value" => 19.00, "name" => "Historischer allgemeiner Steuersatz (§ 12 Abs. 1 UStG)"),
		    array("key" => 12, "value" => 7.00, "name" => "Historischer ermäßigter Steuersatz (§ 12 Abs. 2 UStG)"),
		    array("key" => 21, "value" => 16.00, "name" => "Historischer allgemeiner Steuersatz (§ 12 Abs. 1 UStG)"),
		    array("key" => 22, "value" => 5.00, "name" => "Historischer ermäßigter Steuersatz (§ 12 Abs. 2 UStG)"),
		);
		return $taxes;
	}
	
	public static function getTaxFromKey($pdo,$taxkey) {
		$taxes = self::getTaxesArray($pdo);
		foreach($taxes as $t) {
			if ($taxkey == $t["key"]) {
				return $t["value"];
			}
		}
		return 0.0;
	}
	
	public static function getTaxDescriptionFromKey($pdo,$taxkey) {
		$taxes = self::getTaxesArray($pdo);
		foreach($taxes as $t) {
			if ($taxkey == $t["key"]) {
				return $t["name"];
			}
		}
		return 0.0;
	}
	
	public static function getCurrencyAsIsoVal($pdo) {
		$currency = self::getConfigValue($pdo, 'currency', 'Euro');
		if (in_array(strtoupper($currency), array("EURO","EUR","E","€"))) {
			return "EUR";
		}
		return $currency;
	}
        
        public static function isTableExists($pdo,$tablename) {
                $tablenameResolved = DbUtils::substTableAlias($tablename);
                $sql = "SELECT * FROM information_schema.tables ";
                $sql .= "WHERE table_schema = '" . MYSQL_DB . "' "; 
                $sql .= "AND table_name = '" . $tablenameResolved . "' LIMIT 1";
                try {
                        $result = self::fetchSqlAll($pdo, $sql);
                        if (count($result) > 0) {
                                return true;
                        } else {
                                return false;
                        }
                } catch (Exception $ex) {
                        error_log("Table exists check returns error for SQL: $sql: " . $ex->getMessage());
                }
        }
        public static function areAllTableExisting($pdo,$tablenamearray) {
                $sql = "SELECT * FROM information_schema.tables ";
                $sql .= "WHERE table_schema = '" . MYSQL_DB . "' "; 
                $tarr = array();
                foreach($tablenamearray as $aTablename) {
                        $tablenameResolved = DbUtils::substTableAlias($aTablename);
                        $tarr[] = "table_name='" . $tablenameResolved . "'";
                }
                $tarrconditions = implode(" OR ",$tarr);
                $sql .= "AND (";
                $sql .= $tarrconditions;
                $sql .=")";
                $result = self::fetchSqlAll($pdo, $sql);
                if (count($result) === count($tablenamearray)) {
                        return true;
                } else {
                        return false;
                }
        }

		public static function canUserPayAllOrders($pdo) {
			$userid = self::getUserId();
			$sql = "select right_payallorders from %user% U,%roles% R where U.id=? AND U.roleid=R.id";
			$res = CommonUtils::fetchSqlAll($pdo, $sql, array($userid));
			if (count($res) == 1) {
				$rightPayAllOrders = $res[0]['right_payallorders'];
				if ($rightPayAllOrders == 1) {
					return true;
				} else {
					return false;
				}
			} else {
				return false;
			}
		}

        public static function getUnpaidPriceSumSqlIgnoreOpenGuests() {
			$where = "Q.toremove='0' AND Q.paidtime is null AND Q.isclosed is null "
							. "AND Q.billid is null AND (B.paymentid is null OR B.paymentid <> '" . DbUtils::$PAYMENT_GUEST . "')";
			$priceSumSql = "COALESCE(SUM(IF($where,Q.price,0.00)),0.00)";
			return $priceSumSql;
        }
        public static function getUnpaidPriceSumSqlWithOpenGuestBillsAsUnpaid() {
                $where = "Q.toremove='0' AND Q.paidtime is null "
                                . "AND (Q.isclosed is null AND Q.billid is null OR (B.paymentid is not null AND B.paymentid = '" . DbUtils::$PAYMENT_GUEST . "'))";
                $priceSumSql = "COALESCE(SUM(IF($where,Q.price,0.00)),0.00)";
                return $priceSumSql;
        }
        public static function getPaidPriceSumSqlWithOpenGuestBillsAsUnpaid() {
                $where = "Q.toremove='0' AND Q.paidtime is not null AND B.closingid is null "
                                . "AND (Q.billid is not null AND B.paymentid <> '" . DbUtils::$PAYMENT_GUEST . "')";
                $priceSumSql = "COALESCE(SUM(IF($where,Q.price,0.00)),0.00)";
                return $priceSumSql;
        }
        
        public static function getProdDefVariableForCurrentDay($pdo):string {
                date_default_timezone_set(DbUtils::getTimeZoneDb($pdo));
		$dayofweek = intval(date('N'));
                $prodvar = "";
                switch ($dayofweek) {
                        case 1: $prodvar = "monday";
                                break;
                        case 2: $prodvar = "tuesday";
                                break;
                        case 3: $prodvar = "wednesday";
                                break;
                        case 4: $prodvar = "thursday";
                                break;
                        case 5: $prodvar = "friday";
                                break;
                        case 6: $prodvar = "saturday";
                                break;
                        case 7: $prodvar = "sunday";
                                break;
                }
		return $prodvar;
        }
        
        public static function jsonEncodeErrorToTxt($errorInInt) {
                switch ($errorInInt) {
                        case JSON_ERROR_NONE:
                                return ' - Keine Fehler';
                        case JSON_ERROR_DEPTH:
                                return 'Maximale Stacktiefe überschritten';
                        case JSON_ERROR_STATE_MISMATCH:
                                return 'Unterlauf oder Nichtübereinstimmung der Modi';
                        case JSON_ERROR_CTRL_CHAR:
                                return 'Unerwartetes Steuerzeichen gefunden';
                        case JSON_ERROR_SYNTAX:
                                return 'Syntaxfehler, ungültiges JSON';
                        case JSON_ERROR_UTF8:
                                return 'Missgestaltete UTF-8 Zeichen, möglicherweise fehlerhaft kodiert';;
                        default:
                                return 'Unbekannter Fehler';
                }
                return '';
        }
        
        public static function utf8ize($mixed) {
                if (is_array($mixed)) {
                        foreach ($mixed as $key => $value) {
                                $mixed[$key] = self::utf8ize($value);
                        }
                } else if (is_string($mixed)) {
                        return utf8_encode($mixed);
                }
                return $mixed;
        }

        
        public static function dump($object,$msg = '') {
                ob_start(); 
                var_dump( $object );
                $contents = ob_get_contents();
                ob_end_clean();
                error_log($msg . ": " . $contents );
        }
        
        public static function getPureEbonUrl($pdo):string {
                $eBonUrl = trim(CommonUtils::getConfigValue($pdo, "ebonurl", ""));
                if (CommonUtils::endsWith($eBonUrl, "/")) {
                        $eBonUrl = substr($eBonUrl, 0, strlen($eBonUrl)-1);
                }
                return $eBonUrl;
        }
        
        public static function getUserId() {
			if(session_id() == '') {
				session_start();
			}
			return $_SESSION['userid'];
		}
		public static function truncateString(string $text,int $maxLength): string {
			if (is_null($text) || ($text == '') || (strlen($text) <= $maxLength)) {
				return $text;
			}
			return substr($text,0,$maxLength);
		}
}