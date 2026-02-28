<?php
// Datenbank-Verbindungsparameter
require_once ('config.php');

require_once ('commonutils.php');

class DbUtils {
	private static $timezone = null;
	private static $prefix = null;
	private static $dbname = null;
	
	public static $WORKFLOW_DIGITAL_AND_WORK = 0;
	public static $WORKFLOW_ONLY_DIGITAL = 1;
	public static $WORKFLOW_ONLY_WORK = 2;
	public static $WORKFLOW_WORK_WITH_SERVER = 3;
	
	public static $PRICE_TYPE_BASE = 0;
	public static $PRICE_TYPE_DICOUNT = 1;
	public static $PRICE_TYPE_EXTRA_AMOUNT = 2;
	public static $PROCESSTYPE_BELEG = 1;
	public static $PROCESSTYPE_VORGANG = 2;
	public static $PROCESSTYPE_SONSTIGER_VORGANG = 3;
	public static $OPERATION_IN_BILL_TABLE = 1;
	public static $OPERATION_IN_QUEUE_TABLE = 2;
	public static $OPERATION_IN_CLOSING_TABLE = 3;
	public static $NO_TSE = 0;
	public static $TSE_OK = 1;
	public static $TSE_KNOWN_ERROR = 2;
	public static $TSE_RUNTIME_ERROR = 3;
	public static $TSE_MISCONFIG = 4;
	public static $OSLABEL = "OrderSprinter";
	public static $OSVERSLABEL = "Version";
	
	public static $ORDERTYPE_PRODUCT = 1;
	public static $ORDERTYPE_1ZweckKauf = 2;
	public static $ORDERTYPE_1ZweckEinl = 3;
        
        public static $PAYMENT_BAR = 1;
        public static $PAYMENT_EC = 2;
        public static $PAYMENT_KREDIT = 3;
        public static $PAYMENT_RECHNUNG = 4;
        public static $PAYMENT_UEBERW = 5;
        public static $PAYMENT_LAST = 6;
        public static $PAYMENT_HOTEL = 7;
        public static $PAYMENT_GUEST = 8;

	public static function overruleTimeZone($timezone) {
		self::$timezone = $timezone;
	}
	public static function overrulePrefix($prefix) {
		self::$prefix = $prefix;
	}
	public static function overruleDbName($dbname) {
		self::$dbname = $dbname;
	}
	public static function getDbName() {
		$db = MYSQL_DB;
		if (!is_null(self::$dbname)) {
			$db = self::$dbname;
		}
		return $db;
	}
	
	public static function openDbAndReturnPdoStatic ($doEchoError = true) {
                defined('MYSQL_PORT') || define ( 'MYSQL_PORT','3306' );
                
		$dsn = 'mysql:host=' . MYSQL_HOST . ';port=' . MYSQL_PORT . ';dbname=' . MYSQL_DB;
		$user = MYSQL_USER;
		$password = MYSQL_PASSWORD;
		$pdo = null;
		try {
			$pdo = new PDO($dsn, $user, $password);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
			$sql = "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'";
			CommonUtils::execSql($pdo, $sql, null);
		}
		catch (PDOException $e) {
			if ($doEchoError) {
				echo 'Connection failed: ' . $e->getMessage();
			}
		}
		return $pdo;
	}
	function openDbAndReturnPdo () {
                defined('MYSQL_PORT') || define ( 'MYSQL_PORT','3306' );
                
		$dsn = 'mysql:host=' . MYSQL_HOST . ';port=' . MYSQL_PORT . ';dbname=' . MYSQL_DB;
                
		$user = MYSQL_USER;
		$password = MYSQL_PASSWORD;
		$pdo = null;
		try {
			$pdo = new PDO($dsn, $user, $password);
			$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                        $pdo->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, true);
		}
		catch (PDOException $e) {
			echo 'Connection failed: ' . $e->getMessage();
		}
		return $pdo;
	}
	
        public static function openRepliDb () {
                defined('MYSQL_REPLIDBS') || define ( 'MYSQL_REPLIDBS', '' );
                
                $replis = trim(MYSQL_REPLIDBS);
                if ($replis == '') {
                        return self::openDbAndReturnPdoStatic();
                }
                $repliArr = explode(';',$replis);
                $numberOfReplis = count($repliArr);
                $instanceToUse = rand(1, $numberOfReplis);
                $repliToUse = $repliArr[$instanceToUse - 1];
                $repliParts = explode(':',$repliToUse);
                $host = $repliParts[0];
                $port = $repliParts[1];
                
		$dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . MYSQL_DB;
		$user = MYSQL_USER;
		$password = MYSQL_PASSWORD;
		$pdo = null;
		try {
                        error_log("User Repli instance $instanceToUse with dsn=$dsn");
			$pdo = new PDO($dsn, $user, $password);
			$pdo ->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
			$sql = "SET SESSION sql_mode = 'STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION'";
			CommonUtils::execSql($pdo, $sql, null);
		}
		catch (PDOException $e) {
                        error_log("Repli-DB with instance $instanceToUse could not be accessed: " . $e->getMessage());
                        error_log("Trying again");
                        return self::openRepliDb();
		}
		return $pdo;
	}
        
	function testDbAccess($host,$dbname,$user,$pass) {
		$dsn = 'mysql:host=' . $host . ';dbname=' . $dbname;
		$password = $pass;
		$pdo = null;
		try {
			$pdo = new PDO($dsn, $user, $password);
			$pdo ->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
		}
		catch (PDOException $e) {
			//
		}
		if ($pdo != null) {
			return true;
		} else {
			return false;
		}
	}
	
	/*
	 * To use sql strings that are easy to read the table names are used
	 * without variables. But since the user can specify a prefix for all
	 * tables the substitution must be done somewhere. This is the function
	 * that replaces the %TABLE% by $prefix_table
	 */
	public static function substTableAlias($sqlString) {
		$prefix = TAB_PREFIX;
		if (!is_null(self::$prefix)) {
			$prefix = self::$prefix;
		}
		return self::substTableAliasCore($sqlString, $prefix);
	}
	
	public static function substTableAliasCore($sqlString,$prefix) {
                $cnv = array(
                    "%queue%" => $prefix . 'queue',
                    "%products%" => $prefix . 'products',
                    "%user%" => $prefix . 'user',
                    "%room%" => $prefix . 'room',
                    "%resttables%" => $prefix . 'resttables',
                    "%bill%"=> $prefix . 'bill',
                    "%customerlog%" => $prefix . 'customerlog',
                    "%customers%" => $prefix . 'customers',
                    "%groups%" => $prefix . 'groups',
                    "%groupcustomer%" => $prefix . 'groupcustomer',
                    "%vacations%" => $prefix . 'vacations',
                    "%tablemaps%" => $prefix . "tablemaps",
                    "%tablepos%" => $prefix . "tablepos",
                    "%pricelevel%" => $prefix . 'pricelevel',
                    "%config%" => $prefix . 'config',
                    "%closing%" => $prefix . 'closing',
                    "%printjobs%" => $prefix . 'printjob',
                    "%hist%" => $prefix . 'hist',
                    "%histprod%" => $prefix . 'histprod',
                    "%histconfig%" => $prefix . 'histconfig',
                    "%histuser%" => $prefix . 'histuser',
                    "%histactions%" => $prefix . 'histactions',
                    "%payment%" => $prefix . 'payment',
                    "%billproducts%" => $prefix . 'billproducts',
                    "%work%" => $prefix . 'work',
                    "%comments%" => $prefix . 'comments',
                    "%hsin%" => $prefix . 'hsin',
                    "%hsout%" => $prefix . 'hsout',
                    "%reservations%" => $prefix . 'reservations',
                    "%logo%" => $prefix . 'logo',
                    "%log%" => $prefix . 'log',
                    "%usedfeatures%" => $prefix . 'usedfeatures',
                    "%performance%" => $prefix . 'performance',
                    "%extras%" => $prefix . 'extras',
                    "%extrasprods%" => $prefix . 'extrasprods',
                    "%queueextras%" => $prefix . 'queueextras',
                    "%ratings%" => $prefix . 'ratings',
                    "%prodimages%" => $prefix . 'prodimages',
                    "%roles%" => $prefix . 'roles',
                    "%recordsqueue%" => $prefix . 'recordsqueue',
                    "%records%" => $prefix . 'records',
                    "%times%" => $prefix . 'times',
                    "%tasks%" => $prefix . 'tasks',
                    "%taskhist%" => $prefix . 'taskhist',
                    "%tsevalues%" => $prefix . 'tsevalues',
                    "%operations%" => $prefix . 'operations',
                    "%terminals%" => $prefix . 'terminals',
                    "%counting%" => $prefix . 'counting',
                    "%vouchers%" => $prefix . 'vouchers',
                    "%orders%" => $prefix . 'orders',
                    "%prodnames%" => $prefix . 'prodnames',
                    "%liveorders%" => $prefix . 'liveorders',
                    "%testchk%" => $prefix . 'testchk',
                    "%prodtype%" => $prefix . 'prodtype'
                );
	
		return strtr($sqlString,$cnv);			
	}
	
	public function resolveTablenamesInSqlString($sqlString) {
		return DbUtils::substTableAlias($sqlString);
	}
	
	public static function getTimeZone() {
		if (is_null(self::$timezone)) {
			if(session_id() == '') {
				session_start();
			}
			if (isset($_SESSION['timezone'])) {
				return $_SESSION['timezone'];
			} else {
				return "Europe/Berlin";
			}
		} else {
			return self::$timezone;
		}
	}
        
	public static function getTimeZoneDb($pdo) {
		if (is_null($pdo)) {
			return "Europe/Berlin";
		}
		try {
			return CommonUtils::getConfigValue($pdo, 'timezone', "Europe/Berlin");
		} catch (Exception $ex) {
			return "Europe/Berlin";
		}
	}
	
        public static $userCols = array(
            array("col" => 'id',                "hist" => 1),
            array("col" => 'username',          "hist" => 1),
            array("col" => 'userpassword',      "hist" => 0),
	    array("col" => 'quickcash',		"hist" => 1),
            array("col" => 'active',            "hist" => 1, "default" => 1),
	    array("col" => 'area',		"hist" => 1, "new" => null	    ,"default" => null,	    "update" => null),
            array("col" => 'articletag',	"hist" => 1, "new" => null	    ,"default" => null,	    "update" => null),
            array("col" => 'lastmodule',        "hist" => 0, "new" => null	    ,"default" => null,	    "update" => null),
            array("col" => 'ordervolume',       "hist" => 0, "new" => null	    ,"default" => null,	    "update" => null),
            array("col" => 'language',          "hist" => 0, "new" => null,				    "update" => null),
            array("col" => 'receiptprinter',    "hist" => 0, "new" => null	    ,"default" => null,	    "update" => null),
            array("col" => 'roombtnsize',       "hist" => 0, "new" => null	    ,"default" => null,	    "update" => null),
            array("col" => 'tablebtnsize',      "hist" => 0, "new" => null	    ,"default" => null,	    "update" => null),
            array("col" => 'prodbtnsize',       "hist" => 0, "new" => null	    ,"default" => null,	    "update" => null),
            array("col" => 'prefertablemap',    "hist" => 0, "new" => null	    ,"default" => 1,	    "update" => null),
	    array("col" => 'preferimgdesk',     "hist" => 0, "new" => null	    ,"default" => null,	    "update" => null),
	    array("col" => 'preferimgmobile',   "hist" => 0, "new" => null	    ,"default" => null,	    "update" => null),
	    array("col" => 'showplusminus',	"hist" => 0, "new" => null	    ,"default" => null,	    "update" => null),
            array("col" => 'keeptypelevel',     "hist" => 0, "new" => null	    ,"default" => 0,	    "update" => null),
            array("col" => 'extrasapplybtnpos', "hist" => 0, "new" => null	    ,"default" => 1,	    "update" => null),
            array("col" => 'fullname',          "hist" => 1, "new" => null	    ,"default" => '',	    "update" => null),
            array("col" => 'isowner',           "hist" => 1, "new" => null	    ,"default" => 0,	    "update" => null),
            array("col" => 'sorting',           "hist" => 0, "new" => null	    ,"default" => '',	    "update" => null),
            array("col" => 'showonlymyjobs',    "hist" => 0, "new" => null	    ,"default" => 0,	    "update" => null)
        );
        
									
        public static $prodCols = array(
            array("col" => 'id',        "hist" => 1,	"property" => "id",         "default" => null,      "type" => "int","menu" => "ID",             "guiname" => ["ID","ID","ID"]),
            array("col" => 'longname',  "hist" => 1,	"property" => "longname",   "default" => "",        "type" => "text",                           "guiname" => ["Artikelname","Name of article","Nombre del articulo"]),
            array("col" => 'shortname', "hist" => 1,	"property" => "shortname",  "default" => "",        "type" => "text","menu" => "Kurzname",      "guiname" => ["Name in Bestellansicht","Name in order view","Nombre en vista del orden"]),
            array("col" => 'nameonworkrec',"hist" => 1,	"property" => "nameonworkrec","default" => "",      "type" => "text", "menu" => "Arbeitsbonname","guiname" => ["Name auf Arbeitsbon","Name on work receipt","Nombre en ticket"],
                "info" => "Wird kein Name für den Arbeitsbon angegeben, so wird der Artikelname verwendet."),
            array("col" => 'priceA',    "hist" => 1,	"property" => "priceA",     "default" => 0.00,      "type" => "float",                          "guiname" => ["Normalpreis","Normal price","Prewcio normal"]),
            array("col" => 'priceB',    "hist" => 1,	"property" => "priceB",     "default" => 0.00,      "type" => "float", "menu" => "PreisB",      "guiname" => ["Preis Stufe B","Price Level B","Precio (B)"]),
            array("col" => 'priceC',    "hist" => 1,	"property" => "priceC",     "default" => 0.00,      "type" => "float", "menu" => "PreisC",      "guiname" => ["Preis Stufe C","Price Level C","Precio (C)"]),
            array("col" => 'purchasingprice',    "hist" => 1,	"property" => "purchasingprice",     "default" => null,      "type" => "float", "menu" => "Einkaufspreis",      "guiname" => ["Einkaufspreis","Purchasing Price","Precio de compra"]),
            array("col" => 'barcode',	"hist" => 1,	"property" => "barcode",    "default" => "",        "type" => "text", "menu" => "Barcode",      "guiname" => ["Barcode","Barcode","Barcode"]),
	    	array("col" => 'unit',	"hist" => 1,	"property" => "unit",       "default" => 0,         "type" => "int", "menu" => "Einheit",       "guiname" => ["Einheit","Unit","Unidad"],"guivals" => array(
			array("val" => 0, "label" => "Stück"),
			array("val" => 1, "label" => "Preiseingabe bei Bestellung"),
			array("val" => 2, "label" => "Gewicht (kg)"),
			array("val" => 3, "label" => "Gewicht (gr)"),
			array("val" => 4, "label" => "Gewicht (mg)"),
			array("val" => 5, "label" => "Volumen (l)"),
			array("val" => 6, "label" => "Volumen (ml)"),
			array("val" => 7, "label" => "Länge (m)"),
                        array("val" => 10, "label" => "Dauer (Stunden)"),
			array("val" => 8, "label" => "EinzweckgutscheinKauf"),
			array("val" => 9, "label" => "EinzweckgutscheinEinl")
			)	
                ),
			array("col" => 'monday',    "hist" => 1,	"property" => "monday",   "default" => 1,         "type" => "yesno", "menu" => "Montag",        "guiname" => ["Verkauf Montag","Sell Monday","Venta los lunes"]),
			array("col" => 'tuesday',    "hist" => 1,	"property" => "tuesday",   "default" => 1,         "type" => "yesno", "menu" => "Dienstag",     "guiname" => ["Verkauf Dienstag","Sell Tuesday","Venta los martes"]),
			array("col" => 'wednesday',    "hist" => 1,	"property" => "wednesday",   "default" => 1,         "type" => "yesno",  "menu" => "Mittwoch",  "guiname" => ["Verkauf Mittwoch","Sell Wednesday","Venta los miércoles"]),
			array("col" => 'thursday',    "hist" => 1,	"property" => "thursday",   "default" => 1,         "type" => "yesno",  "menu" => "Donnerstag", "guiname" => ["Verkauf Donnerstag","Sell Thursday","Venta los jueves"]),
			array("col" => 'friday',    "hist" => 1,	"property" => "friday",   "default" => 1,         "type" => "yesno",  "menu" => "Freitag",      "guiname" => ["Verkauf Freitag","Sell Friday","Venta los viernes"]),
			array("col" => 'saturday',    "hist" => 1,	"property" => "saturday",   "default" => 1,         "type" => "yesno",   "menu" => "Samstag",   "guiname" => ["Verkauf Samstag","Sell Saturday","Venta los sabados"]),
			array("col" => 'sunday',    "hist" => 1,	"property" => "sunday",   "default" => 1,         "type" => "yesno", "menu" => "Sonntag",       "guiname" => ["Verkauf Sonntag","Sell Sunday","Venta los domingos"]),
			
			array("col" => 'tax',       "hist" => 1,	"property" => "tax",        "default" => 1,         "type" => "int", "menu" => "Normal-Steuersatz","guiname" => ["Normaler Steuersatz","Normal tax","Impuesta normal"],"guivals" => array(
			array("val" => 1, "label" => "1: Allgemeiner Steuersatz (§ 12 Abs. 1 UStG)"),
			array("val" => 2, "label" => "2: Ermäßigter Steuersatz (§ 12 Abs. 2 UStG)"),
			array("val" => 5, "label" => "5 - 0%: Nicht Steuerbar"),
			array("val" => 11, "label" => "11 - 19%: Historischer allgemeiner Steuersatz (§ 12 Abs. 1 UStG)"),
			array("val" => 12, "label" => "12 - 7%: Historischer ermäßigter Steuersatz (§ 12 Abs. 2 UStG)"),
			array("val" => 21, "label" => "21 - 16%: Historischer allgemeiner Steuersatz (§ 12 Abs. 1 UStG)"),
			array("val" => 22, "label" => "22 - 5%: Historischer ermäßigter Steuersatz (§ 12 Abs. 2 UStG)")
			)	
                ),
	    array("col" => 'togotax',   "hist" => 1,	"property" => "togotax",    "default" => 2,         "type" => "int", "menu" => "To-Go-Steuersatz","guiname" => ["To-go Steuersatz","To-go tax","Impuesta to-go"],"guivals" => array(
			array("val" => 1, "label" => "1: Allgemeiner Steuersatz (§ 12 Abs. 1 UStG)"),
			array("val" => 2, "label" => "2: Ermäßigter Steuersatz (§ 12 Abs. 2 UStG)"),
			array("val" => 5, "label" => "5 - 0%: Nicht Steuerbar"),
			array("val" => 11, "label" => "11 - 19%: Historischer allgemeiner Steuersatz (§ 12 Abs. 1 UStG)"),
			array("val" => 12, "label" => "12 - 7%: Historischer ermäßigter Steuersatz (§ 12 Abs. 2 UStG)"),
			array("val" => 21, "label" => "21 - 16%: Historischer allgemeiner Steuersatz (§ 12 Abs. 1 UStG)"),
			array("val" => 22, "label" => "22 - 5%: Historischer ermäßigter Steuersatz (§ 12 Abs. 2 UStG)")
			)	
		),
		array("col" => 'taxaustria',"hist" => 1,	"property" => "taxaustria", "default" => 1,      "type" => "int", "menu" => "Steuersatz-Austria","guiname" => ["Österreich Steuersatz","Tax for Austria","Impuesta para Austria"],"guivals" => array(
			array("val" => 1, "label" => "Steuersatz 'Normal'"),
			array("val" => 2, "label" => "Steuersatz 'Ermäßigt 1'"),
			array("val" => 3, "label" => "Steuersatz 'Ermäßigt 2'"),
			array("val" => 4, "label" => "Steuersatz 'Besonders'"),
			array("val" => 0, "label" => "Steuersatz 'Null'")
            )),
		array("col" => 'amount',	"hist" => 0,	"property" => "amount",     "default" => null,      "type" => "int", "menu" => "Menge",         "guiname" => ["Menge","Amount","Multitud"], "minvalue" => 0),
		array("col" => 'category',  "hist" => 0,	"property" => "category",   "default" => "",        "type" => "int"),
		array("col" => 'favorite',  "hist" => 1,	"property" => "favorite",   "default" => 0,         "type" => "yesno","menu" => "Favorit",      "guiname" => ["Favorit","Favorite","Favorito"]),
		array("col" => 'sorting',   "hist" => 1),
		array("col" => 'available', "hist" => 1,	"property" => "available",  "default" => 1,         "type" => "yesno","menu" => "vorhanden",    "guiname" => ["verfügbar","available","acessible"]),
		array("col" => 'audio',     "hist" => 1,	"property" => "audio",      "default" => null,      "type" => "text"),
	    array("col" => 'prodimageid',"hist" => 1,	"property" => "prodimageid","default" => 0,         "type" => "int", "menu" => "Bildnr"),
	    array("col" => 'display',	"hist" => 1,	"property" => "display",    "default" => 'KG',      "type" => "text", "menu" => "Anzeige",      "guiname" => ["Anzeige","Display","Anuncio"],"guivals" => array(
                        array("val" => "KG", "label" => "Kellner- und Gastbestellansicht"),
                        array("val" => "K", "label" => "Kellnerbestellansicht"),
                        array("val" => "G", "label" => "Gastbestellansicht"),
            )),
            array("col" => 'tags',	"hist" => 1,	"property" => "tags",       "default" => "",        "type" => "textarea", "menu" => "Tags",         "guiname" => ["Tags","Tags","Tags"],"appearanceinmenu" => "multiline"),
            array("col" => 'description',"hist" => 1,	"property" => "description","default" => "",        "type" => "textarea", "menu" => "Beschreibung", "guiname" => ["Beschreibung","Description","Descripción"],"appearanceinmenu" => "multiline"),
            array("col" => 'removed',   "hist" => 0),
			array("col" => 'guestmaxorder',	"hist" => 1,	"property" => "guestmaxorder",     "default" => 1,      "type" => "int", "menu" => "Maximalgastbestellung",         "guiname" => ["Gastbestellung max. Anzahl","Self-ordering max amount","Self-ordering max multitud"], "minvalue" => 0)
        );
    
	private static function dropDBTable($pdo,$tablename) {
		try {
			CommonUtils::execSql($pdo, "DROP TABLE $tablename", null);
			return true;
		} catch (Exception $ex) {
			return false;
		}
	}
	
	public static function checkForInstallUpdateDbRights($pdo) {
		$tableexists = false;
		try {
			$result = CommonUtils::fetchSqlAll($pdo, "SELECT 1 from %testchk% LIMIT 1", null);
			if (count($result) >= 0) {
				$tableexists = true;
			}
		} catch (Exception $ex) {
			$tableexists = false;
		}

		if ($tableexists) {
			$ok = self::dropDBTable($pdo, '%testchk%');
			if (!$ok) {
				return array("status" => "OK","msg" => array("DROP"),"ok" => 0);
			}
		}
		
		try {
			$sql = "CREATE TABLE `%testchk%` (`id` INT (3)) CHARACTER SET utf8 COLLATE utf8_general_ci ENGINE = InnoDb";
			CommonUtils::execSql($pdo, $sql, null);
		} catch (Exception $ex) {
			return array("status" => "OK","msg" => array("CREATE"),"ok" => 0);
		}
		$missingRights = array();
		try {
			CommonUtils::execSql($pdo, "ALTER TABLE %testchk% ADD testfield INT(1) NULL DEFAULT '0' AFTER id", null);
		} catch (Exception $ex) {
			$missingRights[] = "ALTER";
		}
		try {
			CommonUtils::execSql($pdo, "INSERT INTO  %testchk% (id,testfield) VALUES(?,?)", array(1,2));
		} catch (Exception $ex) {
			$missingRights[] = "INSERT";
		}
		try {
			CommonUtils::execSql($pdo, "UPDATE  %testchk% SET testfield=? WHERE id=?", array(10,1));
		} catch (Exception $ex) {
			$missingRights[] = "UPDATE";
		}
		$ok = self::dropDBTable($pdo, '%testchk%');
		if (!$ok) {
			$missingRights[] = "DROP";
		}

		if (count($missingRights) == 0) {
			return array("status" => "OK","msg" => $missingRights,"ok" => 1);
		} else {
			return array("status" => "OK","msg" => $missingRights,"ok" => 0);
		}
	}
}