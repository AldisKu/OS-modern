<?php
// Datenbank-Verbindungsparameter
require_once ('dbutils.php');
require_once ('queuecontent.php');
require_once ('commonutils.php');
require_once ('utilities/userrights.php');
require_once ('utilities/HistFiller.php');
require_once ('utilities/sorter.php');
require_once ('utilities/TypeAndProducts/ProductEntry.php');
require_once ('utilities/sroomsync.php');



class Products {
	var $dbutils;
	var $queue;
	var $commonUtils;
	var $userrights;
	var $histfiller;
	var $sorter;
        
        public static $HANDLE_AMOUNT_SHOW_AND_ALLOW_ORDER = 0;
        public static $HANDLE_AMOUNT_SHOW_AND_PREVENT_ORDER = 1;
        public static $HANDLE_AMOUNT_HIDE_ARTICLE = 2;
        
	
	function __construct() {
		$this->dbutils = new DbUtils();
		$this->queue = new QueueContent();
		$this->commonUtils = new CommonUtils();
		$this->userrights = new Userrights();
		$this->histfiller = new HistFiller();
		$this->sorter = new Sorter();
	}
        
        private static function hasUserProductRights() {
            if (session_id() == '') {
                session_start();
            }
            if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
                return false;
            }
            if ($_SESSION['right_products']) {
                return true;
            } else {
                return false;
            }
        }

        private static function hasUserWaiterOrProductRights() {
                if(session_id() == '') {
			session_start();
		}
            if ($_SESSION['right_waiter']) {
                return true;
            }
            if ($_SESSION['right_products']) {
                return true;
            }
            return false;
        }

    function handleCommand($command) {	
		
		
		$cmdArray = array('showDbProducts', 'getMenuLevelUp', 'applySingleProdData', 'createExtra', 'applyExtra', 'upExtra', 'delExtra','sortup','sortdown', 'delproduct', 
		    'reassign', 'applyType', 'delType', 'getSingleProdData', 'getSingleTypeData', 'getPriceLevelInfo','setPriceLevelInfo', 'createProduct','createProdType',
		    'addGeneralComment','changeGeneralComment','delGeneralComment','upGeneralComment','downGeneralComment','sortProdAlpha','getOnlyAllProds','loadprodimage','loadfullprodimageset',
		    'deleteImageProdAssignment','cleanprodimagestable','getkeynames','assignProdImageToKey','prodimghmlexport','getAllActiveProducts','changesetofproducts','setprodproperty');
		if (in_array($command, $cmdArray)) {
			if (!($this->userrights->hasCurrentUserRight('right_products'))) {
				if ($command == 'createProdType') {
					echo json_encode(array("status" => "ERROR", "code" => ERROR_PRODUCTS_NOT_AUTHOTRIZED, "msg" => ERROR_PRODUCTS_NOT_AUTHOTRIZED_MSG));
				} else {
					echo "Benutzerrechte nicht ausreichend!";
				}
				return false;
			}
		}
		
		//$cmdsForMasterDataChange = array('applySingleProdData', 'createExtra', 'applyExtra', 'delExtra','delproduct', 'reassign', 'applyType', 'delType', 'createProduct','createProdType','changesetofproducts');
		$cmdsForMasterDataChange = array();
		if (in_array($command, $cmdsForMasterDataChange)) {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			if (!CommonUtils::canMasterDataBeChanged($pdo)) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_MASTERDATA, "msg" => "Stammdatenänderungen erfordern einen vorherigen Tagesabschluss"));
				return;
			}
			$pdo = null;
		}
		
		if($command == 'showDbProducts') {
			$this->showDbProducts();
		} else if ($command == 'getMenu') {
                    if (self::hasUserProductRights()) {
                        $this->getMenu($_GET['ref'],null);
                    } else {
                        echo json_encode(array("status" => "ERROR", "code" => ERROR_NOT_AUTHOTRIZED, "msg" => ERROR_NOT_AUTHOTRIZED_MSG));
                    }
		} else if ($command == 'getMenuLevelUp') {
			$this->getMenuLevelUp($_GET['ref']);
		} else if ($command == 'getSpeisekarte') {
			if ($this->userrights->hasCurrentUserRight('is_admin') || ($this->userrights->hasCurrentUserRight('right_manager'))) {
			    $pdo = DbUtils::openRepliDb();
			    echo json_encode($this->getSpeisekarte($pdo));
			} else {
			    echo json_encode(array("status" => "ERROR", "code" => ERROR_NOT_AUTHOTRIZED, "msg" => ERROR_NOT_AUTHOTRIZED_MSG));
			}
		} else if ($command == 'exportCsv') {
			if (($this->userrights->hasCurrentUserRight('is_admin')) 
					|| ($this->userrights->hasCurrentUserRight('right_manager'))) {
				$this->exportCsv();
			}
		} else if ($command == 'getAllTypesAndAvailProds') {
			$this->getAllTypesAndAvailProds();
		} else if ($command == 'getAllAvailProdsAlphaSorted') {
			$this->getAllAvailProdsAlphaSorted();
		} else if ($command == 'getAllExtrasAlphaSorted') {
			$this->getAllExtrasAlphaSorted();
		} else if ($command == 'getSingleProdData') {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			echo json_encode($this->getSingleProdData($pdo,$_GET['id']));
		} else if ($command == 'getSingleTypeData') {
			$this->getSingleTypeData($_GET['id']);
		} else if ($command == 'applySingleProdData') {
			$extras = null;
			if(isset($_POST['extras'])) {
				$extras = $_POST['extras'];
			}
			$ok = $this->applySingleProdData($_POST['changeExtras'],$extras,$_POST['assignextrastotype']);
                        echo json_encode($ok);
		} else if ($command == 'createExtra') {
			$this->createExtra($_POST['name'],$_POST['price'],$_POST['purchasingprice'],$_POST['maxamount']);
		} else if ($command == 'delExtra') {
			$this->delExtra($_POST['id']);
		} else if ($command == 'applyExtra') {
			$this->applyExtra($_POST['name'],$_POST['price'],$_POST['purchasingprice'],$_POST['maxamount'],$_POST['id']);
		} else if ($command == 'upExtra') {
			$this->upExtra($_POST['id']);
		} else if ($command == 'sortup') {
			$this->sortup($_POST['prodid']);
		} else if ($command == 'sortdown') {
			$this->sortdown($_POST['prodid']);
		} else if ($command == 'delproduct') {
			$this->delproduct($_POST['prodid']);
		} else if ($command == 'createProduct') {
                        $ok = $this->createProduct();
                        echo json_encode($ok);
		} else if ($command == 'reassign') {
			$this->reassign($_POST['productid'],$_POST['typeid']);
		} else if ($command == 'createProdType') {
			$this->createProdType($_POST['refid'],$_POST['name']);
		} else if ($command == 'applyType') {
			$this->applyType($_POST['id'],$_POST['name'],$_POST['kind'],$_POST['usekitchen'],$_POST['usesupply'],$_POST['printer'],$_POST['fixbind']);
		} else if ($command == 'delType') {
			$this->delType($_POST['id']);
		} else if ($command == 'getPriceLevelInfo') {
			$this->getPriceLevelInfo();
		} else if ($command == 'setPriceLevelInfo') {
			$this->setPriceLevelInfo($_POST['priceLevelId']);
		} else if ($command == 'getAudioFiles') {
			$this->getAudioFiles();
		} else if ($command == 'addGeneralComment') {
			$this->addGeneralComment($_POST['comment']);
		} else if ($command == 'getAllGeneralComments') {
			if ($this->userrights->hasCurrentUserRight('is_admin') || ($this->userrights->hasCurrentUserRight('right_waiter')) || ($this->userrights->hasCurrentUserRight('right_products'))) {
				$this->getAllGeneralComments();
			} else {
                                echo json_encode(array("status" => "ERROR", "code" => ERROR_NOT_AUTHOTRIZED, "msg" => "Fehlende Benutzerrechte"));
			}
		} else if ($command == 'changeGeneralComment') {
			$this->changeGeneralComment($_POST['id'],$_POST['comment']);
		} else if ($command == 'delGeneralComment') {
			$this->delGeneralComment($_POST['id']);
		} else if ($command == 'upGeneralComment') {
			$this->upGeneralComment($_POST['id']);
		} else if ($command == 'downGeneralComment') {
			$this->downGeneralComment($_POST['id']);
		} else if ($command == 'getAssignedExtrasOfProd') {
                    if (self::hasUserWaiterOrProductRights()) {
                                $this->getAssignedExtrasOfProd($_GET['prodid']);
                        } else {
                                $this->getMenu($_GET['ref'], null);
                        }
                } else if ($command == 'sortProdAlpha') {
			$this->sortProdAlpha($_POST['refid']);
		} else if ($command == 'getOnlyAllProds') {
			$this->getOnlyAllProds();
		} else if ($command == 'deleteImageProdAssignment') {
			$this->deleteImageProdAssignment($_POST['prodid']);
		} else if ($command == 'loadprodimage') {
			$this->loadprodimage();
		} else if ($command == 'loadfullprodimageset') {
			$this->loadfullprodimageset();
		} else if ($command == 'getprodimage') {
			if ($_GET['prodid']) {
				$size = 'h';
				if (isset($_GET['size'])) {
					$size = $_GET['size'];
				} 
				$this->getprodimage($_GET['prodid'],$size);
			} else {
				$this->getprodimage(null);
			}
		} else if ($command == 'prodimghmlexport') {
			self::exportImgHml();
		} else if ($command == 'cleanprodimagestable') {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			self::cleanProdImagesTable($pdo);
			echo json_encode(array("status" => "OK"));
		} else if ($command == 'assignProdImageToKey') {
			self::assignProdImageToKey($_POST['prodid'],$_POST['prodimageid']);
		} else if ($command == 'getkeynames') {
			self::getkeynames();
		} else if ($command == 'getAllActiveProducts') {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$allprods = self::getAllActiveProducts($pdo);
			echo json_encode($allprods);
		} else if ($command == 'changesetofproducts') {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
                        $changeSet = array();
                        $newprods = array();
                        if (isset($_POST['changeset'])) {
                                $changeSet = $_POST['changeset'];
                        }
                        if (isset($_POST['newprods'])) {
                                $newprods = $_POST['newprods'];
                        }
			$ret = self::changesetofproducts($pdo,$changeSet,$newprods);
			echo json_encode($ret);
                } else if ($command == 'setprodproperty') {
			$ret = self::setprodproperty($_GET['longname'],$_GET['property'],$_GET['value']);
			echo json_encode($ret);
		} else {
			echo "Command not supported.";
		}
	}

        
	private static $proddefs = array(
		array("id" => "id","get" => "%products%.id as id","histid" => "prodid","histget" => "prodid","histexportname" => "Produktid","isnumber" => "0"),
		array("id" => "shortname", "get" => "shortname","histid" => "shortname","histget" => "shortname","histexportname" => "Name in Bestellansicht","isnumber" => "0"),
		array("id" => "longname", "get" => "longname","histid" => "longname","histget" => "longname","histexportname" => "Produktname","isnumber" => "0"),
		array("id" => "available", "get" => "available","histid" => "available","histget" => "available","histexportname" => "","histexportname" => "Verfügbarkeit","isnumber" => "0", "exportvals" => array("default" => "Nein","1" => "Ja")),
		array("id" => "priceA", "get" => "priceA","histid" => "priceA","histget" => "priceA","histexportname" => "Preis (Stufe A)","isnumber" => "1"),
		array("id" => "priceB", "get" => "priceB","histid" => "priceB","histget" => "priceB","histexportname" => "Preis (Stufe B)","isnumber" => "1"),
		array("id" => "priceC", "get" => "priceC","histid" => "priceC","histget" => "priceC","histexportname" => "Preis (Stufe C)","isnumber" => "1"),
                array("id" => "purchasingprice", "get" => "purchasingprice","histid" => "purchasingprice","histget" => "purchasingprice","histexportname" => "Einkaufspreis","isnumber" => "1"),
		array("id" => "barcode", "get" => "barcode","histid" => "barcode","histget" => "barcode","histexportname" => "Barcode","isnumber" => "0"),
		array("id" => "unit", "get" => "COALESCE(unit,0) as unit","histid" => "unit","histget" => "unit","histexportname" => "Einheit","isnumber" => "0", "exportvals" => array(
			"default" => "Stück",
			"0" => "Stück",
			"1" => "Preiseingabe bei Bestellung",
			"2" => "Gewicht (kg)",
			"3" => "Gewicht (gr)",
			"4" => "Gewicht (mg)",
			"5" => "Volumen (l)",
			"6" => "Volumen (ml)",
			"7" => "Länge (m)",
                        "10" => "Dauer (Stunden)",
			"8" => "EinzweckgutscheinKauf",
			"9" => "EinzweckgutscheinEinl"
			)	
		),
		array("id" => "monday", "get" => "monday","histid" => "prodid","histget" => "monday","histexportname" => "Montag","isnumber" => "0", "exportvals" => array("default" => "Ja","1" => "Ja","0" => "Nein")),
		array("id" => "tuesday", "get" => "tuesday","histid" => "prodid","histget" => "tuesday","histexportname" => "Dienstag","isnumber" => "0", "exportvals" => array("default" => "Ja","1" => "Ja","0" => "Nein")),
		array("id" => "wednesday", "get" => "wednesday","histid" => "prodid","histget" => "wednesday","histexportname" => "Mittwoch","isnumber" => "0", "exportvals" => array("default" => "Ja","1" => "Ja","0" => "Nein")),
		array("id" => "thursday", "get" => "thursday","histid" => "prodid","histget" => "thursday","histexportname" => "Donnerstag","isnumber" => "0", "exportvals" => array("default" => "Ja","1" => "Ja","0" => "Nein")),
		array("id" => "friday", "get" => "friday","histid" => "prodid","histget" => "friday","histexportname" => "Freitag","isnumber" => "0", "exportvals" => array("default" => "Ja","1" => "Ja","0" => "Nein")),
		array("id" => "saturday", "get" => "saturday","histid" => "prodid","histget" => "saturday","histexportname" => "Samstag","isnumber" => "0", "exportvals" => array("default" => "Ja","1" => "Ja","0" => "Nein")),
		array("id" => "sunday", "get" => "sunday","histid" => "prodid","histget" => "sunday","histexportname" => "Sonntag","isnumber" => "0", "exportvals" => array("default" => "Ja","1" => "Ja","0" => "Nein")),	
		array("id" => "tax", "get" => "COALESCE(tax, '1') as tax","histid" => "tax","histget" => "tax","histexportname" => "Allg. Steuer","isnumber" => "1"),
		array("id" => "togotax", "get" => "COALESCE(togotax, '2') as togotax","histid" => "togotax","histget" => "togotax","histexportname" => "To-Go Steuer","isnumber" => "1"),
		array("id" => "taxaustria", "get" => "COALESCE(taxaustria, 'null') as taxaustria","histid" => "","histget" => "","histexportname" => "","isnumber" => "0"),
		array("id" => "amount", "get" => "COALESCE(amount, 'null') as amount","histid" => "","histget" => "","histexportname" => "","isnumber" => "0"),
		array("id" => "audio", "get" => "COALESCE(audio, '') as audio","histid" => "","histget" => "","histexportname" => "","isnumber" => "0"),
		array("id" => "favorite", "get" => "favorite","histid" => "prodid","histget" => "favorite","histexportname" => "Favorit","isnumber" => "0", "exportvals" => array("default" => "Nein","1" => "Ja")),
		array("id" => "type", "get" => "'p' as type","histid" => "","histget" => "","histexportname" => "","isnumber" => "0"),
		array("id" => "prodimageid", "get" => "COALESCE(prodimageid, 0) as prodimageid","histid" => "prodimageid","histget" => "prodimageid","histexportname" => "Bildnr","isnumber" => "1"),
		array("id" => "display", "get" => "COALESCE(display, 'KG') as display","histid" => "display","histget" => "display","histexportname" => "Anzeige","isnumber" => "0"),
		array("id" => "tags", "get" => "COALESCE(tags, '') as tags","histid" => "tags","histget" => "tags","histexportname" => "Tags","isnumber" => "0"),
		array("id" => "description", "get" => "COALESCE(description, '') as description","histid" => "description","histget" => "description","histexportname" => "Beschreibung","isnumber" => "0"),
		array("id" => "nameonworkrec", "get" => "COALESCE(nameonworkrec, '') as nameonworkrec","histid" => "nameonworkrec","histget" => "nameonworkrec","histexportname" => "Name auf Arbeitsbon","isnumber" => "0"),
		array("id" => "guestmaxorder", "get" => "COALESCE(guestmaxorder, '1') as guestmaxorder","histid" => "guestmaxorder","histget" => "guestmaxorder","histexportname" => "Maximalgastbestellung","isnumber" => "1")
	);
	
	public static function exportImgHml() {
		$file_name = "bilddaten.ocs";
		header("Content-type: text/x-csv");
		header("Content-Disposition: attachment; filename=$file_name");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Pragma: no-cache");
		header("Expires: 0");
		
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$sql = "SELECT id,keyname,imgh,imgm,imgl from %prodimages% order by id";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, null);
		for ($i=0;$i<count($result);$i++) {
			echo ($i+1) . ";" . $result[$i]['keyname'] . ';' . $result[$i]['imgh'] . ";" . $result[$i]['imgm'] . ";" . $result[$i]['imgl'] . "\n";
		}
	}
	function getDateValueAsBoolInterpretatedIcon($aValue) {
		if ($aValue != '0' ) {
			$imgFile = "ok.png";
		} else {
			$imgFile = "notavailable.png";
		}
		return $imgFile;
	}
	
        private function getArticletagOfUser($pdo):?array {
                $userId = CommonUtils::getUserId();
                $sql = "SELECT COALESCE(articletag,'') as articletag FROM %user% WHERE id=?";
                $res = CommonUtils::fetchSqlAll($pdo, $sql, array($userId));
                if (count($res) > 0) {
                        $articletag = $res[0]['articletag'];
						$trimmedTag = trim($articletag);
						if ($trimmedTag == "") {
							return null;
						}
                        if (CommonUtils::startsWith($articletag, "!")) {
                                $neg = true;
                                // remove first "!"
                                $tag = substr($articletag, 1);
                        } else {
                                $neg = false;
                                $tag = $articletag;
                        }
                        return array("neg" => $neg,"articletag" => $tag);
                } else {
                        return null;
                }
        }
        
	private function getAllTypesAndAvailProds() {
		$pdo = DbUtils::openRepliDb();
		$articletag = $this->getArticletagOfUser($pdo);
		$prodDayVar = CommonUtils::getProdDefVariableForCurrentDay($pdo);
                
		$pdo->beginTransaction();
                
		$handleAmount = CommonUtils::getConfigValue($pdo, 'handleamount', self::$HANDLE_AMOUNT_SHOW_AND_ALLOW_ORDER);
		
		$pricelevel = CommonUtils::getConfigValue($pdo, 'pricelevel', 1);
                $mostsoldasfavsStr = CommonUtils::getConfigValue($pdo, 'mostsoldasfavs', 0);
                try {
                        $mostsoldasfavs = intval($mostsoldasfavsStr);
                } catch (Exception $ex) {
                        $mostsoldasfavs = 0;
                }

		$priceTxt = "priceA";
		if ($pricelevel == 2) {
			$priceTxt = "priceB";
		} else if ($pricelevel == 3) {
			$priceTxt = "priceC";
		}
		
		$sql = "select id,name,reference,sorting from %prodtype% where removed is null";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, null);
		$typeArray = array();
		
		foreach($result as $row) {
			$ref = $row['reference'];
			if ($ref == null) {
				$ref = 0;
			}
			$typeArray[] = array("id" => $row['id'], "name" => $row['name'], "ref" => $ref,"sorting" => $row["sorting"]);
		}
                $handleAmountSql = "";
                if ($handleAmount == self::$HANDLE_AMOUNT_HIDE_ARTICLE) {
                        $handleAmountSql = " AND ((amount is null) OR (amount > '0')) ";
                }
                $whereArticleTag = "";
                if (!is_null($articletag)) {
                        $neg = $articletag['neg'];
                        $tag = htmlspecialchars($articletag['articletag']);
                        if ($neg) {
                                $whereArticleTag = " AND ((tags is null) || (tags NOT LIKE '%" . $tag . "%')) ";
                        } else {
                                $whereArticleTag = " AND ((tags IS NOT null) && (tags LIKE '%" . $tag . "%')) ";
                        }                        
                }
		$sql = "(select '1' as thecount,id,shortname,longname,audio,category as ref,favorite,$priceTxt as price,COALESCE(barcode, '') as barcode,"
                        . "IF(unit is not null, unit, '0') as unit,IF(tax is not null, tax, 'null') as tax,"
                        . "COALESCE(tags,'') as tags,"
                        . "IF(taxaustria is not null, taxaustria, 'null') as taxaustria,IF(amount is not null, amount, 'null') as amount,COALESCE(description,'') as description,"
                        . "COALESCE(prodimageid,0) as prodimageid,sorting ";
		$sql .= " FROM %products% where available='1' $handleAmountSql $whereArticleTag AND (removed is null OR removed='0') AND (%products%.$prodDayVar = '1') AND (display = 'KG'  OR display = 'K' OR display is null)) ";
		
                
                if ($mostsoldasfavsStr > 0) {
                        $sql .= " UNION ALL ";

                        $sql .= "(SELECT count(Q.productid) as thecount,Q.productid as id,";
                        $sql .= "P.shortname,P.longname,P.audio,P.category as ref,'1' as favorite,$priceTxt as price,COALESCE(P.barcode, '') as barcode,IF(P.unit is not null,";
                        $sql .= "P.unit, '0') as unit,IF(P.tax is not null, P.tax, 'null') as tax,COALESCE(tags,'') as tags,IF(P.taxaustria is not null, P.taxaustria, 'null') as taxaustria,";
                        $sql .= "IF(P.amount is not null, P.amount, 'null') as amount,COALESCE(description,'') as description,";
                        $sql .= "COALESCE(P.prodimageid,0) as prodimageid,P.sorting as sorting";
                        $sql .= " from %queue% Q ";
                        $sql .= "INNER JOIN %products% P ON Q.productid=P.id ";
                        $sql .= "WHERE DATE(Q.ordertime) > (NOW() - INTERVAL 30 DAY) AND P.available='1' $handleAmountSql $whereArticleTag ";
                        $sql .= " AND (P.removed is null OR P.removed='0') AND (P.$prodDayVar = '1') AND (P.display = 'KG'  OR P.display = 'K' OR P.display is null) ";
                        $sql .= "GROUP BY Q.productid ORDER BY count(Q.productid) DESC LIMIT $mostsoldasfavs)";
                }
                
                $sql = "SELECT * FROM ($sql) a ORDER BY CAST(a.sorting AS UNSIGNED)";
                $resWithDoubles = CommonUtils::fetchSqlAll($pdo, $sql, null);
                
                $cleanedResult = array();
                foreach ($resWithDoubles as $r) {
                        $prodid = $r["id"];
                        if (!in_array($prodid, $cleanedResult)) {
                             $cleanedResult[$prodid] = $r;
                        }
                }
                $result = array_values($cleanedResult);

		$prodArray = array();
		foreach($result as $row) {
			$ref = $row['ref'];
			if ($ref == null) {
				$ref = 0;
			}
			$audio = $row['audio'];
			if ($audio == null) {
				$audio = "";
			}
			$fav = $row['favorite'];
			if ($fav == null) {
				$fav = 0;
			}
			
			$sql = "SELECT DISTINCT %extras%.id AS extraid,%extras%.name AS name,%extras%.price as price,COALESCE(%extras%.maxamount,1) as maxamount,%extras%.sorting as sorting FROM %extras%,%extrasprods%
				WHERE %extrasprods%.prodid=? AND %extras%.id=%extrasprods%.extraid AND %extras%.removed is null ORDER BY sorting,name";
			
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($row['id']));
			$extras = $stmt->fetchAll(PDO::FETCH_OBJ);			
			
			$prodArray[] = array("id" => $row['id'], 
			    "name" => $row['shortname'], "longname" => $row['longname'], 
			    "audio" => $audio, 
			    "ref" => $ref, 
			    "favorite" => $fav, 
			    "price" => $row['price'],
			    "barcode" => $row['barcode'],
			    "unit" => $row['unit'],
			    "tax" => $row['tax'], 
			    "taxaustria" => $row['taxaustria'],
			    "amount" => $row['amount'],
                            "description" => $row['description'],
			    "prodimageid" => $row['prodimageid'],
			    "extras" => $extras);
		}
		$pdo->commit();
		
		$filteredTypes = self::filterUsedTypes($typeArray, $prodArray);
		usort($filteredTypes,"Products::cmptypes");
		
                $sql = "SELECT id,name,price FROM %extras% WHERE removed is null OR removed='0'";
                $extras = CommonUtils::fetchSqlAll($pdo, $sql);
                
		$retArray = array("types" => $filteredTypes, "prods" => $prodArray, "extras" => $extras);
		echo json_encode($retArray);
	}
	
	public static function cmptypes($a, $b)
	{
	    return $a['sorting'] - $b['sorting'];
	}
	
	
	private static function filterUsedTypes($types,$products) {
		$typesWithContent = array();
		foreach ($products as $p) {
			$ref = $p["ref"];
			$typeOfProd = self::getTypeOfId($types, $ref);
			if (!is_null($typeOfProd)) {
				$typesWithContent = self::declareProdTypeAndParentsInUse($types, $typeOfProd,$typesWithContent);
			}
		}
		$out = array();
		$keys = array_keys($typesWithContent);
		foreach($keys as $aKey) {
			$t = $typesWithContent[$aKey];
			$out[] = array("id" => $t["id"],"name" => $t["name"],"ref" => $t["ref"],"sorting" => $t["sorting"]);
		}
		return $out;
	}
	
	private static function getTypeOfId($alltypes,$typeid) {
		foreach($alltypes as $t) {
			if ($t["id"] == $typeid) {
				return $t;
			}
		}
		return null;
	}
	
	private static function declareProdTypeAndParentsInUse($alltypes,$aType,$typesWithContent) {
		$typeid = $aType["id"];
		$reference = $aType["ref"];
		$sorting = $aType["sorting"];
		if (!array_key_exists($typeid, $typesWithContent)) {
			$typesWithContent[$typeid] = array("id" => $typeid,"name" => $aType["name"],"ref" => $reference,"sorting" => $sorting);
			
			$parent = null;
			foreach($alltypes as $a) {
				$typeid = $a["id"];
				if ($typeid == $reference) {
					$parent = $a;
					break;
				}
			}
			if (!is_null($parent)) {
				$typesWithContent = self::declareProdTypeAndParentsInUse($alltypes,$parent,$typesWithContent);
			}
		}
		
		return $typesWithContent;
	}
	
	
	
	/*
	 * Return all available product with id and name, category
	 * (used for re-assignment to type)
	 */
	function getAllAvailProdsAlphaSorted() {
		$pdo = $this->dbutils->openDbAndReturnPdo();
		$sql = "select id,longname,category from %products% WHERE available='1' AND removed is null ORDER BY longname";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute();
		$result = $stmt->fetchAll(PDO::FETCH_OBJ);
		echo json_encode($result);
	}

	function getAllExtrasAlphaSorted() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$ret = $this->getAllExtrasAlphaSortedCore($pdo);
		echo json_encode(array("status" => "OK", "msg" => $ret));
	}
	
	function getAllExtrasAlphaSortedCore($pdo) {
                $pdo = DbUtils::openDbAndReturnPdoStatic();
		$sql = "select id,name,price,purchasingprice,COALESCE(maxamount,1) as maxamount,sorting from %extras% WHERE removed is null ORDER BY sorting,name";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute();
		$result = $stmt->fetchAll(PDO::FETCH_OBJ);
		return $result;
	}

	
	/*
	 * Return in array all products with their id and longname that have a reference to
	 * the given category.
	 * 
	 * The output is this:
	 * 		["id" => 1, "longname" => "Whatever Product"],
	 * 		["id" => 2, "longname" => "Whatever Other Product"], ...
	 */
	private function getProductsWithReferenz($pdo,$ref) {
		$prods = array();

		$sqlselecttxt = self::getSqlSearchForProducts();
		
		$sql = "SELECT $sqlselecttxt from %products% where removed is null AND category is null ORDER BY sorting";
		if ($ref > 0) {
			$sql = "SELECT $sqlselecttxt from %products% where removed is null AND category=$ref ORDER BY sorting";
		}
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		$result = $stmt->fetchAll();
		
		foreach($result as $zeile) {
			$prod_entry = array("type" => "p");
			foreach(self::$proddefs as $aProdDef) {
				$prod_entry[$aProdDef["id"]] = $zeile[$aProdDef["id"]];
			}
			$prods[] = $prod_entry;
		}

		return $prods;
	}
	
	private static function getAllSubTypes($pdo,$prodtypeid) {
		$sql = "SELECT id,reference FROM %prodtype% WHERE reference=? AND removed is null";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($prodtypeid));
		$allSubTypes = $stmt->fetchAll(PDO::FETCH_OBJ);
		$subtypeids = array();
		foreach ($allSubTypes as $aType) {
			$typeids = self::getAllSubTypes($pdo,$aType->id);
			$subtypeids = array_merge($subtypeids,$typeids);
		}
		return array_merge(array($prodtypeid),$subtypeids);
	}
	
	private static function getAllProdIdOfSameTypeAndBelow($pdo,$prodid) {
		$sql = "SELECT category FROM %products% WHERE id=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($prodid));
		$row =$stmt->fetchObject();
		$theType = $row->category;
		$allTypes = self::getAllSubTypes($pdo,$theType);
		
		$prodIds = array();
		foreach ($allTypes as $aTypeId) {
			$sql = "SELECT id FROM %products% WHERE category=? AND removed is null";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($aTypeId));
			$allProdIdsOfThisType = $stmt->fetchAll(PDO::FETCH_OBJ);
			foreach($allProdIdsOfThisType as $aProd) {
				$prodIds[] = $aProd->id;
			}
		}
		return $prodIds;
	}
	
	
	/*
	 * Return in array all types with their id and name that have a reference to
	* the given category.
	*
	* The output is this:
	* 		["id" => 1, "name" => "Meal"],
	* 		["id" => 2, "name" => "Drinks"], ...
	*/
	private function getProdTypesWithReferenz($pdo,$ref) {
		$sql = "SELECT id,name,kind,usekitchen,usesupplydesk,printer,'t' as type,fixbind,sorting from %prodtype% where removed is null AND reference is null ORDER BY sorting";
		if ($ref > 0) {
			$sql = "SELECT id,name,kind,usekitchen,usesupplydesk,printer,'t' as type,fixbind,sorting from %prodtype% where removed is null AND reference=$ref ORDER BY sorting";
		}
		$types = CommonUtils::fetchSqlAll($pdo, $sql, null);
		return $types;
	}
	
	function showDbProducts() {
		$pdo = DbUtils::openRepliDb();
		$productArray = $this->getDbProductsWithRef_json_version($pdo,0,0);
		echo json_encode($productArray);
	}

	private function getMenuLevelUp($ref) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$sql = "SELECT reference from %prodtype% where removed is null AND id=? ORDER BY sorting";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array($ref));
		$currentRef =$stmt->fetchObject();
		$this->getMenu($currentRef->reference,$pdo);
	}
	private function getMenu($ref,$pdo) {
		if (is_null($pdo)) {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
		}
		if (!is_null($ref) && ($ref>0)) {
			$sql = "SELECT id,name,kind,usekitchen,usesupplydesk,printer,'t' as type,fixbind from %prodtype% where removed is null AND id=?";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($ref));
			$currentProdType =$stmt->fetchObject();
		} else {
			$currentProdType = null;
		}
	
		if (!is_null($ref) && ($ref>0)) {
			$sql = "SELECT id,name,kind,usekitchen,usesupplydesk,printer,'t' as type,fixbind from %prodtype% where removed is null AND reference=?";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($ref));
		} else {
			$sql = "SELECT id,name,kind,usekitchen,usesupplydesk,printer,'t' as type,fixbind from %prodtype% where removed is null AND reference is null";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute();
		}
		$containedTypes = $stmt->fetchAll(PDO::FETCH_OBJ);
	
		$sqlselecttxt = self::getSqlSearchForProducts();
		if (!is_null($ref) && ($ref>0)) {
			$sql = "SELECT $sqlselecttxt from %products% where removed is null AND category=? ORDER BY sorting";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($ref));
		} else {
			$sql = "SELECT $sqlselecttxt from %products% where removed is null AND category is null ORDER BY sorting";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute();
		}
		$containedProds = $stmt->fetchAll(PDO::FETCH_OBJ);
		
		$msg = array(
                    "currentType" => $currentProdType,
                    "containedTypes" => $containedTypes,
                    "containedProds" => $containedProds,
                    "proddef" => DbUtils::$prodCols,
                    "category" => $ref);
		echo json_encode(array("status" => "OK", "msg" => $msg));
	}
	
	function readDbProducts($pdo) {
		$speisekarte = $this->readDbProductsWithRef_json_version($pdo,0,0);
		$speisekarte .= $this->readExtrasFromDb($pdo);
		return $speisekarte;
	}
	
	function readExtrasFromDb($pdo) {
		if (is_null($pdo)) {
			$pdo = DbUtils::openRepliDb();
		}
		$sql = "SELECT id,name,price,purchasingprice,maxamount,sorting FROM %extras% WHERE removed is null ORDER by sorting";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute();
		$result = $stmt->fetchAll(PDO::FETCH_OBJ);
		$extrasTxt = "";
		$decpoint = $this->getDecPoint($pdo);
		foreach ($result as $aRes) {
			$extrasTxt .= "!" . $aRes->name . " (ID:" . $aRes->id . ") # " ;
			$priceTxt = number_format($aRes->price, 2, $decpoint, '');
			$extrasTxt .= "Preis: " . $priceTxt . "; ";
                        if (!is_null($aRes->purchasingprice)) {
                                $purchasingpriceTxt = number_format($aRes->purchasingprice, 2, $decpoint, '');
                                $extrasTxt .= "Einkaufspreis: $purchasingpriceTxt; ";
                        }
			$maxamount = $aRes->maxamount;
			$extrasTxt .= "Max: " . $maxamount . "; ";

			$sql = "SELECT prodid FROM %extrasprods% WHERE extraid=?";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($aRes->id));
			$assignedProds = $stmt->fetchAll(PDO::FETCH_OBJ);
			$assProdArr = array();
			foreach ($assignedProds as $anAssProd) {
				$assProdArr[] = "(" . $anAssProd->prodid . ")";
			}
			$extrasTxt .=  "Zugewiesen: " . join(",",$assProdArr) . "\n";
		}

		return $extrasTxt;
	}
	
	private function getDecPoint($pdo) {
		$sql = "SELECT name,setting FROM %config% WHERE name=?";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array("decpoint"));
		$row = $stmt->fetchObject();
		return($row->setting);
	}
	
	public static function getSqlSearchForProducts() {
		$sqlselect = array();
		foreach(self::$proddefs as $aProdDef) {
			$sqlselect[] = $aProdDef["get"];
		}
		return (join(",",$sqlselect));
	}

	public static function getSqlSearchForHistProducts(string $tablename) {
		$sqlselect = array();
		foreach(self::$proddefs as $aProdDef) {
			$theHistId = $aProdDef["histget"];
			if ($theHistId != '') {
				$sqlselect[] = $tablename . "." . $aProdDef["histget"];
			}
		}
		return (join(",",$sqlselect));
	}
	
	public static function getHistProdExportNames() {
		$sqlselect = array();
		foreach(self::$proddefs as $aProdDef) {
			$theHistId = $aProdDef["histexportname"];
			if ($theHistId != '') {
				$sqlselect[] = $aProdDef["histexportname"];
			}
		}
		return (join(";",$sqlselect));
	}
	private function exportCsv() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		
		$decpoint = $this->getDecPoint($pdo);
		$file_name = "datenexport-produkte.csv";
		header("Content-type: text/x-csv");
		header("Content-Disposition: attachment; filename=$file_name");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Pragma: no-cache");
		header("Expires: 0");
		echo("Eintragsid; Datum ;");
		echo self::getHistProdExportNames();
		echo("; Extras/Optionen; Veränderung");
		echo("\n");
		
		$sql = "SELECT DISTINCT H.id as id,date,";
		$sql .= self::getSqlSearchForHistProducts("HP");
		$sql .= ",HP.extras,HA.description as actiondescription ";
		$sql .= " FROM %hist% H, %histprod% HP, %histactions% HA";
		$sql .= " WHERE (refid=HP.id) ";
		$sql .= " AND (action='1' OR action='4' OR action='5') ";
		$sql .= " AND (action=HA.id) ";
		$sql .= " ORDER BY date,id";
		
                $result = CommonUtils::fetchSqlAll($pdo, $sql);
		foreach($result as $zeile) {
			echo $zeile['id'] . ";" . $zeile['date'] . ";";
			foreach(self::$proddefs as $aProdDef) {
				$item = $aProdDef["histexportname"];
				$itemsql = $aProdDef["histget"];
				if ($item != "") {
					if ($itemsql == 'tax') {
						$value = ($zeile['tax']);
						if ($value == null) {
							$value = '-';
						}
						$value = str_replace(".",$decpoint,$value);
					} else if (isset($aProdDef["exportvals"])) {
						$exportvals = $aProdDef["exportvals"];
						$value = $zeile[$itemsql];
						if (isset($exportvals[$value])) {
							$value = $exportvals[$value];
						} else {
							$value = $exportvals["default"];
						}
					} else {
						$isNumber = $aProdDef["isnumber"];
						$value = $zeile[$aProdDef["histget"]];
						if ($isNumber == '1') {
							$value = str_replace(".",$decpoint,$value);
						} else {
							$value = str_replace('"','""',$value);
						}
					}
					echo $value . ";";
				}
			}
			echo $zeile['extras'] . ";" . $zeile['actiondescription'] . "\n";
		}
	}
	
        
	private function getSingleProdData($pdo,$id) {
		if (is_numeric($id)) {
                        $selectArr = array();
                        foreach(self::$proddefs as $aDef) {
                                $selectArr[] = $aDef["get"];
                        }
                        
                        $sql = "SELECT " . implode(",",$selectArr) . " FROM %products% where id=?";
                        $result = CommonUtils::fetchSqlAll($pdo, $sql, array($id));
			if (count($result) != 1) {
                                return array("status" => "ERROR","msg" => "Artikelid nicht in DB");
                        } else {
                                return array("status" => "OK","msg" => $result[0],"proddef" => DbUtils::$prodCols);
                        }
		} else {
                        return array("status" => "ERROR","msg" => "Artikelid nicht in DB"); 
                }
	}
	
	function getSingleTypeData($id) {
		if (is_numeric($id)) {
                        $pdo = DbUtils::openDbAndReturnPdoStatic();
			$sql = "SELECT id,name,usekitchen,usesupplydesk,kind,reference,printer,fixbind FROM %prodtype% WHERE removed is NULL AND id=?";
                        $result = CommonUtils::fetchSqlAll($pdo, $sql, array($id));
                        if (count($result) == 0) {
                                echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
                        } else {
                                echo json_encode(array("status" => "OK", "msg" => $result[0]));
                        }
		} else {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
		}
	}
	
	function reassign($prodid,$typeid) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$pdo->beginTransaction();
		
                try {
                        $this->sorter->resortAfterProduct($pdo, $prodid);

                        $sql = "SELECT MAX(sorting) as maxsort FROM %products% WHERE category=? AND (removed is null or removed='0')";
                        $result = CommonUtils::fetchSqlAll($pdo, $sql, array($typeid));
                        $sortingInTarget = $result[0]["maxsort"];
                        if (is_string($sortingInTarget)) {
                                $sortingInTarget = intval($sortingInTarget);
                        }

                        $sql = "UPDATE %products% SET category=?,sorting=? WHERE id=?";
                        CommonUtils::execSql($pdo, $sql, array($typeid,$sortingInTarget+1,$prodid));

                        $pdo->commit();
                        echo json_encode(array("status" => "OK"));
                } catch (Exceoption $ex) {
                        $pdo->rollBack();
                        error_log("Reassign Artikel zu Category failed: " . $ex->getMessage());
                        echo json_encode(array("status" => "ERROR"));
                }
	}
	
	private static function getMaxSortingOfExtras($pdo) {
		$sql = "SELECT max(sorting) as maxsort FROM %extras% WHERE removed is null";
		$row = CommonUtils::getRowSqlObject($pdo, $sql,null);
		$max = 0;
		if (!is_null($row) && !is_null($row->maxsort)) {
			$max = intval($row->maxsort);
		}
		return $max;
	}
	
	private function getMaxSortOfGenComment($pdo) {
		$sql = "SELECT MAX(sorting) as maxsort from %comments% WHERE prodid is null";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute();
			
		$row = $stmt->fetchObject();
		$maxSorting = 0;
		if ($row != null) {
			$maxSorting = intval($row->maxsort);
		}
		return $maxSorting;
	}
	
	private function getAssignedExtrasOfProd($prodid) {
		$ret = $this->getAssignedExtrasOfProdCore($prodid,null);
		echo json_encode(array("status" => "OK", "msg" => $ret, "prodid" => $prodid));
	}
	
	private function getAssignedExtrasOfProdCore($prodid,$pdo) {
		if (is_null($pdo)) {
			$pdo = $this->dbutils->openDbAndReturnPdo();
		}
		$sql = "SELECT DISTINCT %extras%.id AS extraid FROM %extras%,%extrasprods% 
				WHERE %extrasprods%.prodid=? AND %extras%.id=%extrasprods%.extraid AND %extras%.removed is null";
		
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array($prodid));
		$result = $stmt->fetchAll(PDO::FETCH_OBJ);
		$ids = array();
		foreach ($result as $aRes) {
			$ids[] = $aRes->extraid;
		}
		return $ids;
	}
	
        private static function getUsefulDecPrice($aValue): float {
                if (is_null($aValue)) {
                        return 0.0;
                }
                if (is_string($aValue)) {
                        $trimmedValue = trim($aValue);
                        if ($trimmedValue == '') {
                                return 0.0;
                        } else {
                                return doubleval($trimmedValue);
                        }
                } else {
                        return $aValue;
                }
        }
        
	public static function createExtraCore($pdo,$name,$price,$purchasingprice,$maxamount,$assignedProdIds) {
		if (is_null($pdo)) {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
		}

                $price = self::getUsefulDecPrice($price);
                $purchasingprice = self::getUsefulDecPrice($purchasingprice);

		try {
			$sql = "SELECT id FROM %extras% WHERE name=? AND removed is null";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($name));
			$numberOfExtras = $stmt->rowCount();

			if ($numberOfExtras > 0) {
				return ERROR_NAME_EXISTS_ALREADY;
			}

			$maxPos = self::getMaxSortingOfExtras($pdo);

			$sql = "INSERT INTO `%extras%` (`name`,`price`,`purchasingprice`,`maxamount`,`sorting`) VALUES(?,?,?,?,?)";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($name,$price,$purchasingprice,$maxamount,$maxPos+1));
			$lastExtraId = $pdo->lastInsertId();

			$sql = "DELETE FROM %extrasprods% WHERE extraid=?";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($lastExtraId));

			foreach ($assignedProdIds as $assProdId) {
				$sql = "INSERT INTO %extrasprods% (`id` , `extraid` , `prodid`) VALUES (NULL,?,?)";
				$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
				$stmt->execute(array($lastExtraId,$assProdId));

			}
			return OK;
		} catch (Exception $ex) {
			error_log("Error creating extra in DB: " . $ex->getMessage());
			exit;
		}
	}
	
	private function createExtra($name,$price,$purchasingprice,$maxamount) {
		if (intval($maxamount) < 1) {
			echo json_encode(array("status" => "ERROR", "msg" => "Anzahl zu klein.", "id" => null));
			return;
		}
		try {
                        $pdo = DbUtils::openDbAndReturnPdoStatic();
			$pdo->beginTransaction();
			
			$ret = self::createExtraCore($pdo,$name,$price,$purchasingprice,$maxamount,array());
			if ($ret == ERROR_NAME_EXISTS_ALREADY) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_NAME_EXISTS_ALREADY, "msg" => ERROR_NAME_EXISTS_ALREADY_MSG));
				$pdo->rollBack();
				return;
			}
			
			$pdo->commit();
			$this->getAllExtrasAlphaSorted();
		}
		catch (PDOException $e) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
		}
	}
	
	private function upExtra($id) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		
		$pdo->beginTransaction();
		
		$sql = "SELECT sorting FROM %extras% WHERE id=?";
		$row = CommonUtils::getRowSqlObject($pdo, $sql, array($id));
		$currentPos = intval($row->sorting);
		if ($currentPos > 1) {
			$sql = "SELECT id FROM %extras% WHERE sorting=? AND removed is NULL";
			$row = CommonUtils::getRowSqlObject($pdo, $sql, array($currentPos-1));
			if (!is_null($row) && !is_null($row->id)) {
				$idUpper = $row->id;
				
				$sql = "UPDATE %extras% SET sorting=? WHERE id=?";
				CommonUtils::execSql($pdo, $sql, array($currentPos,$idUpper));
				CommonUtils::execSql($pdo, $sql, array($currentPos-1,$id));
			}
		}
		$pdo->commit();
		$this->getAllExtrasAlphaSorted();
	}
	
	private function applyExtra($name,$price,$purchasingprice,$maxamount,$id) {
		if (intval($maxamount) < 1) {
			echo json_encode(array("status" => "ERROR", "msg" => "Anzahl zu klein.", "id" => $id));
			return;
		}
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$pdo->beginTransaction();
			
			$sql = "SELECT id FROM %extras% WHERE name=? AND id <> ? AND removed is null";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($name,$id));
			$numberOfExtras = $stmt->rowCount();
			if ($numberOfExtras > 0) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_NAME_EXISTS_ALREADY, "msg" => ERROR_NAME_EXISTS_ALREADY_MSG, "id" => $id));
				$pdo->rollBack();
				return;
			}
			
			
			$sql = "SELECT name,price,purchasingprice,maxamount,sorting FROM %extras% WHERE id=?";
			$result = CommonUtils::fetchSqlAll($pdo, $sql, array($id));
			$oldname = $result[0]['name'];
			$oldprice = $result[0]['price'];
                        $oldpurchasingprice = $result[0]['purchasingprice'];
			$oldmaxamount = $result[0]['maxamount'];
			
			if (($oldname == $name) && ($oldprice == $price) && ($oldmaxamount == $maxamount) && ($oldpurchasingprice == $purchasingprice)) {
				echo json_encode(array("status" => "ERROR", "code" => DB_NOT_CHANGED, "msg" => DB_NOT_CHANGED_MSG, "id" => $id));
				$pdo->rollBack();
				return;
			} else {
				$oldsorting = $result[0]['sorting'];

				$sql = "INSERT INTO %extras% (name,price,purchasingprice,maxamount,sorting,removed) VALUES(?,?,?,?,?,?)";
				CommonUtils::execSql($pdo, $sql, array($name,$price,$purchasingprice,$maxamount,$oldsorting,null));
				$newExtraId = $pdo->lastInsertId();
				$sql = "UPDATE %extras% SET removed = ? WHERE id=?";
				CommonUtils::execSql($pdo, $sql, array(1,$id));
				$sql = "UPDATE %extrasprods% SET extraid=? WHERE extraid=?";
				CommonUtils::execSql($pdo, $sql, array($newExtraId,$id));
			}
			
			$pdo->commit();
			$this->getAllExtrasAlphaSorted();
		}
		catch (PDOException $e) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG, "id" => $id));
		}
	}
	
	private function delExtra($id) {
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			
			$sql = "SELECT sorting FROM %extras% WHERE id=?";
			$row = CommonUtils::getRowSqlObject($pdo, $sql, array($id));
			$currentPos = $row->sorting;
			$maxPos = self::getMaxSortingOfExtras($pdo);
			
			$sql = "UPDATE %extras% SET removed='1',sorting=? WHERE id=?";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array(null,$id));
			$this->getAllExtrasAlphaSorted();
			
			// decrease all sortings, independently of the removed flag, by 1
			for ($i=$currentPos;$i<=$maxPos;$i++) {
				$sql = "UPDATE %extras% SET sorting=? WHERE sorting=?";
				CommonUtils::execSql($pdo, $sql, array($i-1,$i));
			}
                        (new SRoomSync())->sync($pdo);
			
		}
		catch (PDOException $e) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
		}
	}
	
	/**
	 * Add a new comment to the list of general comments that are not bound to a product
	 * @param string $comment
	 */
	private function addGeneralComment($comment) {
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$pdo->beginTransaction();
			
			$maxSorting = $this->getMaxSortOfGenComment($pdo);
			
			$sql = "INSERT INTO `%comments%` (`id`,`comment`,`prodid`,`active`,`sorting`) VALUES(NULL,?,NULL,1,?)";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($comment,$maxSorting+1));
			
			$pdo->commit();
			echo json_encode(array("status" => "OK"));
		}
		catch (PDOException $e) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
		}
	}
	
	private function getAllGeneralComments() {
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$sql = "SELECT id,comment,sorting FROM %comments% WHERE prodid is null ORDER BY sorting ASC";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute();
			
			$result = $stmt->fetchAll();
			$commentArray = array();
			
			foreach($result as $row) {
				$commentArray[] = array("id" => $row['id'], "comment" => $row['comment'], "sorting" => $row['sorting']);
			}
			
			echo json_encode(array("status" => "OK", "msg" => $commentArray));
		}
		catch (PDOException $e) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
		}
	}
	
	private function changeGeneralComment($id,$comment) {
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
                        CommonUtils::execSql($pdo, "UPDATE %comments% SET comment=? WHERE id=?", array($comment,$id));
			echo json_encode(array("status" => "OK"));
		}
		catch (PDOException $e) {
                        error_log("Error changing general comment: " . $e->getMessage());
			echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
		}
	}
	
	private function getSortingOfComment($pdo,$id) {
		$sql = "SELECT sorting FROM %comments% WHERE id=?";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array($id));
		$row = $stmt->fetchObject();
			
		if ($row == null) {
			return (-1);
		} else {
			return intval($row->sorting);
		}
	}
	
	private function delGeneralComment($id) {
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$pdo->beginTransaction();
			$sorting = $this->getSortingOfComment($pdo, $id);
			
			if ($sorting < 0) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
				return;
			}
			
			$sql = "DELETE FROM %comments% WHERE id=? AND prodid is null";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($id));
		
			$sql = "SELECT id,sorting FROM %comments% WHERE sorting>? AND prodid is null";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($sorting));
			
			$result = $stmt->fetchAll();

			foreach($result as $row) {
				$theId = $row['id'];
				$theSort = intval($row['sorting'])-1;
				$sql = "UPDATE %comments% SET sorting=? WHERE id=?";
				$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
				$stmt->execute(array($theSort,$theId));
			}
			$pdo->commit();
			echo json_encode(array("status" => "OK"));
		}
		catch (PDOException $e) {
                        error_log("Error deleting a general comment: " . $e->getMessage());
			echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
		}
	}
	
	private function upGeneralComment($id) {
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$pdo->beginTransaction();

			$sorting = $this->getSortingOfComment($pdo, $id);
			
			if ($sorting < 0) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
				return;
			}
			if ($sorting == 1) {
				$pdo->commit();
				echo json_encode(array("status" => "OK"));
				return;
			}
				
			$sql = "SELECT id FROM %comments% WHERE sorting=? AND prodid is null";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($sorting - 1));
			$row = $stmt->fetchObject();
			$previousId = $row->id;
		
			$sql = "UPDATE %comments% SET sorting=? WHERE id=?";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($sorting,$previousId));
			
			$sql = "UPDATE %comments% SET sorting=? WHERE id=?";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($sorting-1,$id));
			
			$pdo->commit();
			echo json_encode(array("status" => "OK"));
		}
		catch (PDOException $e) {
                        error_log("Error moving up a general comment: " . $e->getMessage());
			echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
		}
	}
	
	private function downGeneralComment($id) {
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$pdo->beginTransaction();
		
			$sorting = $this->getSortingOfComment($pdo, $id);
				
			if ($sorting < 0) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
				return;
			}
			
			$maxSorting = $this->getMaxSortOfGenComment($pdo);
			if (($maxSorting == 0) || ($maxSorting == $sorting)) {
				$pdo->commit();
				echo json_encode(array("status" => "OK"));
				return;
			}
		
			$sql = "SELECT id FROM %comments% WHERE sorting=? AND prodid is null";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($sorting + 1));
			$row = $stmt->fetchObject();
			$nextId = $row->id;
		
			$sql = "UPDATE %comments% SET sorting=? WHERE id=?";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($sorting,$nextId));
				
			$sql = "UPDATE %comments% SET sorting=? WHERE id=?";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($sorting+1,$id));
				
			$pdo->commit();
			echo json_encode(array("status" => "OK"));
		}
		catch (PDOException $e) {
                        error_log("Error moving down a general comment: " . $e->getMessage());
			echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
		}
	}
	
	function sortup($prodid) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$pdo->beginTransaction();
		$this->sorter->sortup($pdo, $prodid);
                (new SRoomSync())->sync($pdo);
		$pdo->commit();
		echo json_encode("OK");
	}
	
	function sortdown($prodid) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$pdo->beginTransaction();
		$this->sorter->sortdown($pdo, $prodid);
                (new SRoomSync())->sync($pdo);
		$pdo->commit();
		echo json_encode("OK");
	}
	
	function delproduct($prodid) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$pdo->beginTransaction();
		$this->sorter->delproduct($pdo, $prodid);
                (new SRoomSync())->sync($pdo);
		$pdo->commit();
		
		echo json_encode("OK");
	}
	
	function applySingleProdData($changeExtras,$extras,$assignextrastotype) {
                $pdo = DbUtils::openDbAndReturnPdoStatic();
                $sql = "SELECT category FROM %products% WHERE id=?";
                $res = CommonUtils::fetchSqlAll($pdo, $sql, array($_POST["id"]));
                if (count($res) < 1) {
                        return array("status" => "ERROR","msg" => "Artikel nicht in Db gefunden");
                }
                $typeId = $res[0]["category"];

		try {
			$pdo->beginTransaction();
                        
                        $prodEntry = new ProductEntry();
                        $ok = $prodEntry->createFromPostData($_POST);
                        if ($ok) {
                                $prodEntry->setCategory($typeId);
                        }
                        $id = $prodEntry->applyProductInDb($pdo);    

                        if ($assignextrastotype == 0) {
                                if ($changeExtras == 1) {
                                        $this->changeExtraAssignment($pdo, $id, $extras);
                                }
                                HistFiller::updateProdInHist($pdo,$id);
                        } else {

                                $prodids = self::getAllProdIdOfSameTypeAndBelow($pdo,$id);

                                foreach ($prodids as $aProdId) {
                                        $this->changeExtraAssignment($pdo, $aProdId, $extras);
                                        self::updateHistOnlyForExtrasOfProd($pdo, $aProdId);
                                }
                        }
                        (new SRoomSync())->sync($pdo);

                        $pdo->commit();	
                        return $this->getSingleProdData($pdo,$id);
                        
                } catch (Exception $ex) {
                        $pdo->rollBack();
                        return array("status" => "ERROR","msg" => "Artikel konnte nicht angelegt werden: " . $ex->getMessage());
                }
	}
	
	private static function updateHistOnlyForExtrasOfProd($pdo,$aProdId) {
            HistFiller::updateProdInHist($pdo, $aProdId);
	}
	
	function changeExtraAssignment($pdo,$prodid,$extras) {
		$sql = "DELETE FROM %extrasprods% WHERE prodid=?";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array($prodid));
		
		$histextra = "";
		if (!is_null($extras) && ($extras != "")) {
			$sql = "INSERT INTO %extrasprods% (`id` , `extraid` , `prodid`) VALUES (NULL,?,?)";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			foreach($extras as $anExtra) {
				$stmt->execute(array($anExtra,$prodid));
			}
		}
                (new SRoomSync())->sync($pdo);
	}

	private static function getExtrasForProd($pdo,$prodid) {
		$sql = "SELECT DISTINCT %extras%.name as extraname from %extras%,%extrasprods% where %extras%.removed is null AND %extrasprods%.extraid=%extras%.id AND %extrasprods%.prodid=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($prodid));
		
		$result = $stmt->fetchAll();
		$extraArr = array();
		if (count($result) == 0) {
			return "";
		} else {
			foreach($result as $row) {
				$extraArr[] = $row['extraname'];
			}
			return(implode(", ", $extraArr));
		}
	}
	
	function getOnlyAllProds() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$sql = "SELECT id,longname,COALESCE(prodimageid,0) as prodimageid FROM %products% WHERE removed is null ORDER BY longname";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, null);
		
		$ret = array();
		foreach($result as $aProd) {
			$chainStr = $this->getTypeHierarchy($pdo, $aProd["id"]);
			$ret[] = array("id" => $aProd["id"],"longname" => $aProd["longname"],"prodimageid" => $aProd["prodimageid"],"chain" => $chainStr);
		}
		echo json_encode(array("status" => "OK","msg" => $ret));
	}
	
	private function getTypeHierarchy($pdo,$prodid) {
		$chain = array();
		$sql = "SELECT category FROM %products% WHERE id=?";
		$row = CommonUtils::getRowSqlObject($pdo, $sql, array($prodid));
		$cat = $row->category;
		if (is_null($cat)) {
			return "";
		}
		do {
			$chain[] = $this->getNameOfProdType($pdo,$cat);
			$cat = $this->getIdOfReferencedTypeProd($pdo,$cat);
		} while (!is_null($cat));
		return $this->chainArrayToStr($chain);
	}
	
	private function getIdOfReferencedTypeProd($pdo,$currentProdTypeId) {
		$sql = "SELECT reference FROM %prodtype% WHERE id=?";
		$row = CommonUtils::getRowSqlObject($pdo, $sql, array($currentProdTypeId));
		return $row->reference;
	}
	
	private function getNameOfProdType($pdo,$prodtypeid) {
		$sql = "SELECT name FROM %prodtype% WHERE id=?";
		$row = CommonUtils::getRowSqlObject($pdo, $sql, array($prodtypeid));
		return $row->name;
	}
	
	private function chainArrayToStr($chainArr) {
		$ret = array();
		for ($i=(count($chainArr)-1);$i>=0;$i--) {
			$ret[] = $chainArr[$i];
		}
		$retStr = implode(" -> ", $ret);
		return $retStr;
	}
	
	private static function getkeynames() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$sql = "SELECT id,keyname FROM %prodimages% ORDER BY keyname";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, null);
		echo json_encode(array("status" => "OK","msg" => $result));
	}
	
	private static function assignProdImageToKey($prodid,$prodimageid) {
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$sql = "UPDATE %products% SET prodimageid=? WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array($prodimageid,$prodid));
                        (new SRoomSync())->sync($pdo);
			$ret = array("status" => "OK","prodid" => $prodid);
			echo json_encode($ret);
		} catch (Exception $ex) {
			echo json_encode(array("status" => "ERROR","msg" => $ex->getMessage()));
		}
		
	}
	public static function cleanProdImagesTable($pdo) {
		$sql = "UPDATE %products% SET prodimageid=? WHERE removed is not null";
		CommonUtils::execSql($pdo, $sql, array(null));
		
		$sql = "SELECT id FROM %prodimages%";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, null);
		foreach($result as $res) {
			$prodimageid = $res["id"];
			$sql = "SELECT id FROM %products% WHERE prodimageid=?";
			$referencingProds = CommonUtils::fetchSqlAll($pdo, $sql, array($prodimageid));
			if (count($referencingProds) == 0) {
				$sql = "DELETE FROM %prodimages% WHERE id=?";
				CommonUtils::execSql($pdo, $sql, array($prodimageid));
			}
		}
	}
	
	function getprodimage($prodid,$size='h') {	
		$imgcol = 'imgh';
		if ($size == 'm') {
			$imgcol = 'imgm';
		} else if ($size == 'l') {
			$imgcol = 'imgl';
		}
		
		if (is_null($prodid)) {
			CommonUtils::outTransImage();
			exit;
		} else {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$sql = "SELECT $imgcol as img FROM %prodimages%,%products% WHERE %products%.prodimageid=%prodimages%.id AND %products%.id=?";
			$result = CommonUtils::fetchSqlAll($pdo, $sql, array($prodid));
			if (count($result) != 1) {
				CommonUtils::outTransImage();
				exit;
			} else {
				$imagedata = base64_decode($result[0]["img"]);
			}
		}
	
		header("Content-type: image/png");
		echo $imagedata;
		exit;
	}
	
	private function deleteImageProdAssignment($prodid) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$pdo->beginTransaction();
		
		try{
			$sql = "UPDATE %products% SET prodimageid=? WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array(null,$prodid));
			HistFiller::updateProdInHist($pdo, $prodid);
			$pdo->commit();
		} catch (Exception $ex) {
			$pdo->rollBack();
		}
		echo json_encode($prodid);
	}
	function loadprodimage() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$prodid = $_GET['prodid'];
		
		if ($_FILES['imagefile']['error'] != UPLOAD_ERR_OK 
				&& is_uploaded_file($_FILES['imagefile']['tmp_name'])) { 
			echo json_encode(array("status" => "ERROR","msg" => "Kann Datei nicht laden."));
			exit();
		}
		
		if(!file_exists($_FILES['imagefile']['tmp_name'])) {
			echo json_encode(array("status" => "ERROR","msg" => "Datei existiert nicht. Bitte PHP-Variablen upload_max_filesize und post_max_size_checken."));
			exit();
		}
		
		if(!is_uploaded_file($_FILES['imagefile']['tmp_name'])) {
			echo json_encode(array("status" => "ERROR","msg" => "Datei konnte nicht hochgeladen werden."));
			exit();
		}
		
		$fn = $_FILES['imagefile']['tmp_name'];
		
		$imageh = CommonUtils::scaleImg($fn, 300)["img"];
		$imageBaseh_64 = base64_encode($imageh);
		
		$imagem = CommonUtils::scaleImg($fn, 150)["img"];
		$imageBasem_64 = base64_encode($imagem);
		
		$imagel = CommonUtils::scaleImg($fn, 80)["img"];
		$imageBasel_64 = base64_encode($imagel);
		
		$pdo->beginTransaction();
		
		try {
			$sql = "SELECT SUBSTRING(REPLACE(longname,';','_'),1,30) as longname FROM %products% WHERE id=?";
			$prodnameResult = CommonUtils::fetchSqlAll($pdo, $sql, array($prodid));
			$keyname = "Bild_" . $prodid;
			if (count($prodnameResult) > 0) {
				$keyname = $prodnameResult[0]['longname'];
			}
			
			$sql = "SELECT id FROM %prodimages% WHERE keyname=?";
			$res = CommonUtils::fetchSqlAll($pdo, $sql, array($keyname));
			if (count($res) > 0) {
				$sql = "UPDATE %prodimages% SET imgh=?,imgm=?,imgl=? WHERE keyname=?";
				CommonUtils::execSql($pdo, $sql, array($imageBaseh_64,$imageBasem_64,$imageBasel_64,$keyname));
				CommonUtils::execSql($pdo, "UPDATE %products% SET prodimageid=? WHERE id=?", array($res[0]["id"],$prodid));
			} else {
				$sql = "INSERT INTO %prodimages% (keyname,imgh,imgm,imgl) VALUES(?,?,?,?)";
				CommonUtils::execSql($pdo, $sql, array($keyname,$imageBaseh_64,$imageBasem_64,$imageBasel_64));
				$prodimageid = $pdo->lastInsertId();

				$sql = "UPDATE %products% SET prodimageid=? WHERE id=?";
				CommonUtils::execSql($pdo, $sql, array($prodimageid,$prodid));
			}

			HistFiller::updateProdInHist($pdo,$prodid);
		} catch (Exception $ex) {
			echo json_encode(array("status" => "ERROR","msg" => $ex->getMessage()));
			$pdo->rollBack();
			exit();
		}
		$pdo->commit();
		
		echo json_encode(array("status" => "OK"));
	}
	
	function loadfullprodimageset() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		
		if ($_FILES['textfile']['error'] != UPLOAD_ERR_OK 
				&& is_uploaded_file($_FILES['textfile']['tmp_name'])) { 
			echo json_encode(array("status" => "ERROR","msg" => "Kann Datei nicht laden."));
			exit();
		}
		
		if(!file_exists($_FILES['textfile']['tmp_name'])) {
			echo json_encode(array("status" => "ERROR","msg" => "Datei existiert nicht. Bitte PHP-Variablen upload_max_filesize und post_max_size_checken."));
			exit();
		}
		
		if(!is_uploaded_file($_FILES['textfile']['tmp_name'])) {
			echo json_encode(array("status" => "ERROR","msg" => "Datei konnte nicht hochgeladen werden."));
			exit();
		}
		
		$file = $_FILES['textfile']['tmp_name'];
		
		$handle = fopen ($file, "r");

		$pdo->beginTransaction();
	
		try {
			while (!feof($handle)) {
				$textline = trim(fgets($handle));
				if ($textline != "") {
					$parts = explode(';', $textline);
					
					$keyname = $parts[1];
					$sql = "SELECT id FROM %prodimages% WHERE keyname=?";
					$res = CommonUtils::fetchSqlAll($pdo, $sql, array($keyname));
					if (count($res) > 0) {
						$sql = "UPDATE %prodimages% SET imgh=?,imgm=?,imgl=? WHERE keyname=?";
						CommonUtils::execSql($pdo, $sql, array($parts[2],$parts[3],$parts[4],$keyname));
					} else {
						$sql = "INSERT INTO %prodimages% (keyname,imgh,imgm,imgl) VALUES(?,?,?,?)";
						CommonUtils::execSql($pdo, $sql, array($keyname,$parts[2],$parts[3],$parts[4]));
					}
				}
			}

			fclose ($handle);
			$pdo->commit();
		} catch (Exception $ex) {
			echo json_encode(array("status" => "ERROR","msg" => $ex->getMessage()));
			$pdo->rollBack();
			exit();
		}
		
		echo json_encode(array("status" => "OK"));
	}
	function sortProdAlpha($typeid) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$pdo->beginTransaction();
		$sql = "SELECT id,longname FROM %products% WHERE category=? AND removed is null ORDER BY longname";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($typeid));
		$sort = 1;
		$sql = "UPDATE %products% SET sorting=? WHERE id=?";
		foreach ($result as $prodentry) {
			$prodid = $prodentry["id"];
			CommonUtils::execSql($pdo, $sql, array($sort,$prodid));
			$sort++;
		}
		$pdo->commit();
		echo json_encode(array("status" => "OK"));
	}
	
	function getMaxSortOfTypeInKat($pdo,$idOfKat) {
		if (!is_null($idOfKat)) {
			$sql = "SELECT MAX(sorting) as maxsort,reference FROM %prodtype% WHERE reference=? GROUP BY reference";
			$maxSorting = CommonUtils::fetchSqlAll($pdo, $sql, array($idOfKat));
		} else {
			$sql = "SELECT MAX(sorting) as maxsort,reference FROM %prodtype% WHERE reference is null AND removed is null GROUP BY reference";
			$maxSorting = CommonUtils::fetchSqlAll($pdo, $sql, null);
		}
		
		if (count($maxSorting) != 1) {
			return null;
		} else {
			return intval($maxSorting[0]["maxsort"]) + 1;
		}
	}
	
	function createProdType($id,$prodTypeName) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$pdo->beginTransaction();
		
		if ($id == "top") {
			$nextSort = $this->getMaxSortOfTypeInKat($pdo, null);
			
			$kind = 0;
			if (isset($_POST["kind"])) {
				$kind = $_POST["kind"];
			}
			$sql = "INSERT INTO `%prodtype%` (`name`,`usekitchen`,`usesupplydesk`,`kind`,`printer`,`fixbind`,`sorting`,`reference`) VALUES(?,1,1,?,1,?,?,?)";
			CommonUtils::execSql($pdo, $sql, array($prodTypeName,$kind,0,$nextSort,null));
			$pdo->commit();
			echo json_encode(array("status" => "OK"));
			return;
		}
		if (!is_numeric($id)) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_ID_TYPE, "msg" => ERROR_GENERAL_ID_TYPE_MSG));
			return;
		}

		$nextSort = $this->getMaxSortOfTypeInKat($pdo, $id);
		
		$sql = "SELECT kind FROM %prodtype% WHERE id=?";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array($id));
		$row =$stmt->fetchObject();
		
		if ($row == null) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
			return;
		}
		$kind = $row->kind;
		
		$sql = "INSERT INTO `%prodtype%` (`name`,`usekitchen`,`usesupplydesk`,`kind`,`printer`,`fixbind`,`sorting`,`reference`) VALUES(?,?,?,?,?,?,?,?)";
		CommonUtils::execSql($pdo, $sql, array($prodTypeName,1,1,$kind,1,0,$nextSort,$id));
		
                (new SRoomSync())->sync($pdo);
		$pdo->commit();
		echo json_encode(array("status" => "OK"));
	}
	
	private function createProduct() {
                $pdo = DbUtils::openDbAndReturnPdoStatic();
                
                if (isset($_POST["category"])) {
                        $typeId = $_POST["category"];
                } else {
                        $typeId = null;
                }

		if (!is_numeric($typeId)) {
			return array("status" => "ERROR","msg" => "Artikelgruppe nicht erkannt");
		}

		try {
			$pdo->beginTransaction();
                        
                        $prodEntry = new ProductEntry();
                        $ok = $prodEntry->createFromPostData($_POST);
                        if ($ok) {
                                $prodEntry->setCategory($typeId);
                        }
                    
			$newProdId = $prodEntry->createProductInDb($pdo);

			$this->sorter->setMaxSortingForProdId($pdo, $newProdId);
                        HistFiller::createProdInHist($pdo, $newProdId);
			$pdo->commit();
                        $prodname = $prodEntry->getLongName();
                        (new SRoomSync())->sync($pdo);
                        return array("status" => "OK","msg" => "Artikel $prodname angelegt");
		} catch (Exception $e) {
			$pdo->rollBack();
                        return array("status" => "ERROR","msg" => "Artikel konnte nicht angelegt werden: " . $e->getMessage());
		}
	}

	/*
	 * Change the properties of a type of products
	 */
	function applyType($id,$name,$kind,$usekitchen,$usesupply,$printer,$fixbind) {
		if (!is_numeric($id) || !is_numeric($kind) || !is_numeric($usekitchen) || !is_numeric($usesupply) || !is_numeric($printer) || !is_numeric($fixbind)) {
			return;
		}
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$updateSql = "UPDATE %prodtype% SET kind=?, name=?, usekitchen=?, usesupplydesk=?, printer=?, fixbind=? WHERE id=?";
		CommonUtils::execSql($pdo, $updateSql, array($kind,$name,$usekitchen,$usesupply,$printer,$fixbind,$id));
                (new SRoomSync())->sync($pdo);
		echo json_encode("OK: $updateSql");
	}
	
	function delType($id) {
		if (!is_numeric($id)) {
			echo json_encode(array("status" => "FAILED"));
		}
		$pdo = $this->dbutils->openDbAndReturnPdo();
		$pdo->beginTransaction();
		$this->delTypeCore($pdo, $id);
		$pdo->commit();
		echo json_encode(array("status" => "OK"));
	}
	
	function delTypeCore($pdo,$id) {		
		
		$allTypesInThisLevel = $this->getProdTypesWithReferenz($pdo,$id);
		foreach ($allTypesInThisLevel as $aType) {
			$this->delTypeCore($pdo, $aType["id"]);
		}
		
		$allProdsInThisLevel = $this->getProductsWithReferenz($pdo,$id);
		foreach ($allProdsInThisLevel as $aProd) {
			self::declareProductAsDeletedWithoutResort($pdo, $aProd["id"]);
		}
		
		self::declareTypeAsDeleted($pdo, $id);
                (new SRoomSync())->sync($pdo);
	}
	
	static private function declareTypeAsDeleted($pdo,$id) {
		$sql = "UPDATE %prodtype% SET removed=? WHERE id=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array(1,$id));
		
		
		$sql = "SELECT COALESCE(reference,0) as reference FROM %prodtype% WHERE id=?";
		$parentRef = CommonUtils::fetchSqlAll($pdo, $sql, array($id));
		if (count($parentRef) != 1) {
			return;
		}
		$parentId = $parentRef[0]['reference'];
		
		$sql = "SELECT id,reference,sorting FROM %prodtype% WHERE reference=? AND removed is null ORDER BY sorting";
		$allChildren = CommonUtils::fetchSqlAll($pdo, $sql, array($parentId));
		
		$sort = 1;
		$sql = "UPDATE %prodtype% SET sorting=? WHERE id=?";
		foreach($allChildren as $c) {
			CommonUtils::execSql($pdo, $sql, array($sort,$c["id"]));
			$sort++;
		}
	}
	
	static private function declareProductAsDeletedWithoutResort($pdo,$id) {
		$sql = "UPDATE %products% SET removed=? WHERE id=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array(1,$id));
	}
	
	/* 
	 * Return a html table with all products in a structured way
	 */
	private function getDbProductsWithRef_json_version($pdo,$ref,$depth) {
		$allProdsAndTypesInThisLevel = array();
	
		$allProdsInThisLevel = $this->getProductsWithReferenz($pdo,$ref);
		$allTypesInThisLevel = $this->getProdTypesWithReferenz($pdo,$ref);
		
 		for ($index_prod=0;$index_prod<count($allProdsInThisLevel);$index_prod++) {
 			$aProd = $allProdsInThisLevel[$index_prod];
 			$allProdsAndTypesInThisLevel[] = array("entry" => $aProd, "content" => '');
 		}
		for ($index_type=0;$index_type < count($allTypesInThisLevel);$index_type++) {
			$aProdType = $allTypesInThisLevel[$index_type];
			$typeRef = $aProdType['id'];
			$allProdsAndTypesInThisLevel[] = array("entry" => $aProdType,"content" => $this->getDbProductsWithRef_json_version($pdo,$typeRef,$depth+1));
		}
		return $allProdsAndTypesInThisLevel;
	}
	
	private function readDbProductsWithRef_json_version($pdo,$ref,$depth) {
		$decpoint = $this->getDecPoint($pdo);
		$text = "";
	
		$allProdsInThisLevel = $this->getProductsWithReferenz($pdo,$ref);
		$allTypesInThisLevel = $this->getProdTypesWithReferenz($pdo,$ref);
	
		for ($index_prod=0;$index_prod<count($allProdsInThisLevel);$index_prod++) {
			$aProd = $allProdsInThisLevel[$index_prod];
			
			$prodTextArr = (new ProductEntry())->createProductStr($aProd,$decpoint);
                        foreach($prodTextArr as $prodText) {
                                $text .= substr("                               ", 0, $depth) . $prodText . "\n";
                        }
		}
		
		for ($index_type=0;$index_type < count($allTypesInThisLevel);$index_type++) {
			$aProdType = $allTypesInThisLevel[$index_type];
			$typeRef = $aProdType['id'];
			
			$indent = substr ( "                                " , 0 ,$depth);

			$prodTypeName = $aProdType['name'];
			$kind = ($aProdType['kind'] == 0 ? "F" : "D");
			$usekitchen = ($aProdType['usekitchen'] == 1 ? "K" : "");
			$usesupplydesk = ($aProdType['usesupplydesk'] == 1 ? "B" : "");
			$printer = ($aProdType['printer']);
			$fixbind = ($aProdType['fixbind']);
			$fixBindTxt = "RD";
			if ($fixbind == 1) {
				$fixBindTxt = "KD";
			}
			
			$text .= $indent . $prodTypeName . " = $usekitchen$usesupplydesk$kind = $printer = $fixBindTxt\n";
			
			$text .= $this->readDbProductsWithRef_json_version($pdo,$typeRef,$depth+1);
		}
		return $text;
	}
	
	private function getPriceLevelInfo() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		if(session_id() == '') {
			session_start();
		}
		$lang = $_SESSION['language'];
		
		$currentPriceLevel = $this->commonUtils->getCurrentPriceLevel($pdo);
		$currentPriceLevelId = $currentPriceLevel["id"];
		$currentPriceLevelName = $currentPriceLevel["name"];
		
		$pricelevels = array();
		$sql = "SELECT id,name,info FROM %pricelevel%";
		if ($lang == 1) {
			$sql = "SELECT id,name,info_en as info FROM %pricelevel%";
		} else if ($lang == 2) {
			$sql = "SELECT id,name,info_esp as info FROM %pricelevel%";
		}
		
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));		
		$stmt->execute();
		$result = $stmt->fetchAll();
		foreach($result as $zeile) {
			$theId = $zeile['id'];
			$selected = "0";
			if ($theId == $currentPriceLevelId) {
				$selected = "1";
			}
			
			$levels_entry = array(
					"id" => $theId,
					"name" => $zeile['name'],
					"info" => $zeile['info'],
					"selected" => $selected);
			$pricelevels[] = $levels_entry;
		}
		
		$retArray = array("currentId" => $currentPriceLevelId, "currentName" => $currentPriceLevelName, "levels" => $pricelevels);
		echo json_encode($retArray);
	}
	
	private function setPriceLevelInfo($levelId) {
		if (is_numeric($levelId)) {
			$pdo = DbUtils::openDbAndReturnPdoStatic();	
			$hist = new HistFiller();
			$hist->updateConfigInHist($pdo, "pricelevel", $levelId);
			echo json_encode("OK");
		}
	}
	
	public function getSpeisekarte($pdo) {
		$legend = file_get_contents("../customer/menulegend.txt");

		$decpoint = $this->getDecPoint($pdo);
		
		$sql = "SELECT * FROM %products% WHERE removed is null";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		$numberOfProds = $stmt->rowCount();
		
		$sql = "SELECT * FROM %prodtype% WHERE removed is null";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		$numberOfProdTypes = $stmt->rowCount();
		
		$predef = file_get_contents ("../customer/speisekarte.txt");
		$predef = str_replace('{.}',$decpoint,$predef);
		if (($numberOfProds == 0) && ($numberOfProdTypes == 0)) {
			$text = $legend;
		} else {
			$text = $legend . $this->readDbProducts($pdo);
		}
		
		return array("status" => "OK","msg" => $text, "predef" => $predef);
	}
	
	private function endsWith($haystack, $needle)
	{
		return $needle === "" || substr($haystack, -strlen($needle)) === $needle;
	}
	
	private function getAudioFiles() {
		$dir    = '../customer';
		$fileList = scandir($dir);
		$audioFiles = array();
		
		foreach ($fileList as $aFile) {
			if ($this->endsWith($aFile, '.mp3') || $this->endsWith($aFile, '.ogg') || $this->endsWith($aFile, '.wav')) {
				$audioFiles[] = $aFile;
			}
		}

		echo json_encode($audioFiles);
	}
	
	private static function getAllProdTypesOfActiveProds($pdo) {
		$sql = "SELECT DISTINCT category from %products% WHERE removed is null";
		$result = CommonUtils::fetchSqlAll($pdo, $sql);
		$types = array();
		foreach($result as $pt) {
			$types[] = self::getProdIdChain($pdo, $pt['category']);
		}
		return $types;
	}
	
	public static function getAllActiveProducts($pdo) {
		try {
			$alltypes = self::getAllProdTypesOfActiveProds($pdo);

			$prods = array();
			foreach($alltypes as $t) {
				$sql = "SELECT * from %products% WHERE removed is null AND category=? ORDER BY sorting";
				$result = CommonUtils::fetchSqlAll($pdo, $sql,array($t["id"]));
				$prods[] = array("id" => $t["id"],"chain" => $t["chain"],"products" => $result);
				$t["products"] = $result;
			}

			return array("status" => "OK","msg" => $prods);
		} catch (Exception $ex) {
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
	}
	
	private static function getProdIdChain($pdo,$prodid) {
		$nameArr = self::getProdIdChainRecursiveCore($pdo, $prodid,array());
		$chainNameArr = array();
		for ($i=(count($nameArr)-1);$i>=0;$i--) {
			$chainNameArr[] = $nameArr[$i];
		}
		return array("id" => $prodid,"chain" => implode(" - ", $chainNameArr));
	}
	
	private static function getProdIdChainRecursiveCore($pdo,$prodid,$name) {
		$sql = "SELECT name,reference FROM %prodtype% WHERE id=?";
		$r = CommonUtils::fetchSqlAll($pdo, $sql, array($prodid));
		$curName = $r[0]['name'];
		$name[] = $curName;
		$parent = $r[0]['reference'];
		
		if (is_null($parent)) {
			return $name;
		} else {
			return self::getProdIdChainRecursiveCore($pdo, $parent, $name);
		}
	}
	
	public static function changesetofproducts($pdo,$changeset,$newprods) {
		try {
			$pdo->beginTransaction();
			foreach($changeset as $aChangedProd) {
				$prodid = $aChangedProd["prodid"];
				$longname = $aChangedProd["longname"];
				$shortname = $aChangedProd["shortname"];
				if (($longname == "") && ($shortname == "")) {
					$pdo->rollBack();
					return array("status" => "ERROR","msg" => "Name eines Produkts fehlt");
				}
				if (($longname == "") && ($shortname != "")) {
					$longname = $shortname;
				} else if (($longname != "") && ($shortname == "")) {
					$shortname = $longname;
				}
				$priceA = $aChangedProd["priceA"];
				$priceB = $aChangedProd["priceB"];
				$priceC = $aChangedProd["priceC"];
				
				if (!is_numeric($priceA) || !is_numeric($priceB) || !is_numeric($priceC)) {
					$pdo->rollBack();
					return array("status" => "ERROR","msg" => "Nicht alle Preise sind numerisch");
				}

				$sql = "UPDATE %products% SET shortname=?, longname=?, priceA=?, priceB=?, priceC=? WHERE id=?";
				CommonUtils::execSql($pdo, $sql, array($shortname,$longname,$priceA,$priceB,$priceC,$prodid));
				HistFiller::updateProdInHist($pdo,$prodid);
			}
			$sorter = new Sorter();
			foreach($newprods as $prod) {
				$prodtypeid = $prod["prodtypeid"];
				$longname = $prod["longname"];
				$shortname = $prod["shortname"];
				$priceA = $prod["priceA"];
				$priceB = $prod["priceB"];
				$priceC = $prod["priceC"];
				
				if (!is_numeric($prodtypeid)) {
					$pdo->rollBack();
					return array("status" => "ERROR","msg" => "Prod.kategorie ungültig");
				}
				if (($priceA == "") || ($priceB == "") || ($priceC == "")) {
					$pdo->rollBack();
					return array("status" => "ERROR","msg" => "Preisangabe eines neuen Produkts fehlt");
				}
				if (!is_numeric($priceA) || !is_numeric($priceB) || !is_numeric($priceC)) {
					$pdo->rollBack();
					return array("status" => "ERROR","msg" => "Preisangabe eines neuen Produkts ungültig");
				}
				if (($longname == "") || ($shortname == "")) {
					$pdo->rollBack();
					return array("status" => "ERROR","msg" => "Name eines neuen Produkts fehlt");
				}
				if (($longname == "") && ($shortname != "")) {
					$longname = $shortname;
				} else if (($longname != "") && ($shortname == "")) {
					$shortname = $longname;
				}
				$prodEntry = new ProductEntry();
				$prodEntry->createWithSubsetOfData($prodtypeid,$longname,$shortname,$priceA,$priceB,$priceC);
				$newProdId = $prodEntry->createProductInDb($pdo);
				$sorter->setMaxSortingForProdId($pdo, $newProdId);
				HistFiller::createProdInHist($pdo, $newProdId);
			}
			$pdo->commit();
			return array("status" => "OK");
		} catch(Exception $ex) {
			$pdo->rollBack();
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
	}
        public static function setprodproperty($longname,$property,$value) {
                if (!in_array($property,array('amount','favorite'))) {
                        return array("status" => "ERROR","msg" => "Artikeleigenschaft kann über REST nicht gesetzt werden");
                } else {
                        try {
                                $pdo = DbUtils::openDbAndReturnPdoStatic();
                                $sql = "UPDATE %products% SET $property=? WHERE longname=? and ((removed is null) OR (removed='0'))";
                                CommonUtils::execSql($pdo, $sql, array($value,$longname));
                                return array("status" => "OK","msg" => "Artikel $longname Eigenschaft $property gesetzt auf $value");
                        } catch (Exception $ex) {
                                return array("status" => "ERROR","msg" => "Artikeleigenschaft kann über REST nicht gesetzt werden: " . $ex->getMessage());
                        }
                }
        }
}
