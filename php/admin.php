<?php
// Datenbank-Verbindungsparameter
require_once ('dbutils.php');
require_once ('globals.php');
require_once ('utilities/TypeAndProducts/TypeAndProductFileManager.php');
require_once ('utilities/userrights.php');
require_once ('utilities/HistFiller.php');
require_once ('utilities/basedb.php');
require_once ('utilities/decimaldefs.php');
require_once ('utilities/sorter.php');
require_once ('utilities/Logger.php');
require_once ('utilities/Emailer.php');
require_once ('utilities/version.php');
require_once ('utilities/dsfinvk.php');
require_once ('utilities/backuprestore.php');
require_once ('utilities/sroomsync.php');
require_once ('utilities/ebonsync.php');
require_once ('hotelinterface.php');

class Admin {
	var $dbutils;
	var $userrights;
	var $histfiller;
	
	private static $timezone = null;
	
	private static $rights = array(
		"createNewUser"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"createNewRole"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"updateUser"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"userup"				=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"updateRole"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"deleteUser"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"deleteRole"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"changepassword"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"changeConfig"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"readlogo"				=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"readnicelogo"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"readsroomtitleimg"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"deletelogo"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		
		"getCurrentUser"		=> array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		"tryAuthenticate"		=> array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		"setLastModuleOfUser"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getViewAfterLogin"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"isUserAlreadyLoggedIn"	=> array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		"isLoggedinUserAdmin"	=> array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		"isLoggedinUserKitchen"	=> array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		"isLoggedinUserBar"		=> array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		"isLoggedinUserAdminOrManagerOrTE"  => array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		"hasUserPaydeskRight"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getJsonMenuItemsAndVersion"	    => array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		"getUserList"			=> array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		"getRoleList"			=> array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		"setTime"				=> array("loggedin" => 1, "isadmin" => 1, "rights" => null),
		"changeOwnPassword"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		
		"setUserLanguage"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"setUserReceiptPrinter"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"setUserQuickcash"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"setBtnSize"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getGeneralConfigItems"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getGeneralConfigItemsAndUsers" => array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getWaiterSettings"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getPayPrintType"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getPayments"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"autobackup"			=> array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		"autoftpbackup"			=> array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		"shutdown"				=> array("loggedin" => 1, "isadmin" => 1, "rights" => null),
		"optimize"				=> array("loggedin" => 1, "isadmin" => 1, "rights" => null),
		"ftpbackup"				=> array("loggedin" => 1, "isadmin" => 1, "rights" => null),
		"backup"				=> array("loggedin" => 1, "isadmin" => 1, "rights" => null),
		"restore"				=> array("loggedin" => 1, "isadmin" => 1, "rights" => null),
		"restoreDemoFromZip"	=> array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		"golive"				=> array("loggedin" => 1, "isadmin" => 1, "rights" => null),
		"drop"					=> array("loggedin" => 1, "isadmin" => 1, "rights" => null),
		"fill"					=> array("loggedin" => 1, "isadmin" => 1, "rights" => null),
		"fillSpeisekarte"		=> array("loggedin" => 1, "isadmin" => 1, "rights" => null),
		"assignTaxes"			=> array("loggedin" => 1, "isadmin" => 1, "rights" => null),
		"getDbStat"				=> array("loggedin" => 1, "isadmin" => 1, "rights" => null),
		"setTurbo"				=> array("loggedin" => 1, "isadmin" => 1, "rights" => null),
		
		"exportConfigCsv"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"exportUserCsv"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"exportLog"				=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"setOrderVolume"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"setPreferTableMap"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"setKeepTypeLevel"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
			"setMobileTheme"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"setApplyExtrasBtnPos"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"setTablesAfterSend"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"setPreferimgdesk"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"setPreferimgmobile"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"setPrefershowplusminus"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"setPreferfixbtns"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"setPreferCalc"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"isInstalled"			=> array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		
		"isPrinterServerActive"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getWaiterMessage"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"setWaiterMessage"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"getmobilecss"			=> array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		
		"getprinterinstances"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"setprinterinstances"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		
		"getdashreports"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("dash")),
		"getDailycode"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"askforcompanyinfo"		=> array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		"getrectemplateall"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getrectemplateqrcode"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getrectemplatetext"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getclstemplate"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getclostemplate"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getcashtemplate"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getfoodworktemplate"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getfoodworkbigtemplate"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getfoodworklinetemplate"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getfoodworkbiglinetemplate"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getdrinkworktemplate"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getdrinkworkbigtemplate"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getdrinkworklinetemplate"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getdrinkworkbiglinetemplate"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getcanceltemplate"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getpickuptemplate"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"dsinvkexport"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		
		"uploaduserphoto"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getuserphotoinsession"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"removeuserphoto"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getwaiterphotoforprint"	=> array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		"getcoinsandnotes"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"checkemail"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"resetdemo"				=> array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		"addperformancedata"	=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getdbdiagdata"			=> array("loggedin" => 1, "isadmin" => 1, "rights" => null),
		"getphpdiagdata"		=> array("loggedin" => 1, "isadmin" => 1, "rights" => null),
	
		"setconfigitem"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"setconfigitemrest"		=> array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		"checksroomaccess"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
	
		"fillDefaultSpeisekarte"	=> array("loggedin" => 1, "isadmin" => 1, "rights" => null),

		"getPreferCalc"			=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),
		"getPreferimgmobile"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => null),

		"checkebonaccess"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"delallprintjobs"		=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin")),
		"setshowonlymyjobs"		=> array("loggedin" => 0, "isadmin" => 0, "rights" => null),
		"reset"					=> array("loggedin" => 1, "isadmin" => 0, "rights" => array("manager_or_admin"))
		);
	
	function __construct() {
		$this->dbutils = new DbUtils();
		$this->userrights = new Userrights();
		$this->histfiller = new HistFiller();
	}
	
	
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
				if ($_SESSION['is_admin'] == false) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_COMMAND_NOT_ADMIN, "msg" => ERROR_COMMAND_NOT_ADMIN_MSG));
				return false;
				}
			}
	    }
		if (!is_null($cmdRights["rights"])) {
			foreach($cmdRights["rights"] as $aRight) {
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
	
	function handleCommand($command) {
		if (!CommonUtils::checkRights($command, self::$rights)) {
			return false;
		}

	    if ($command == 'tryAuthenticate') {
			$this->tryAuthenticate($_POST['userid'],$_POST['password'],$_POST['modus'],$_POST["time"]);
		} else if ($command == 'setLastModuleOfUser') {
			$this->setLastModuleOfUser($_POST['view']);
		} else if ($command == 'getViewAfterLogin') {
			$this->getViewAfterLogin();
		} else if ($command == 'isUserAlreadyLoggedIn') {
			$this->isUserAlreadyLoggedIn();
		} else if ($command == 'logout') {
			$this->logout();
		} else if ($command == 'getCurrentUser') {
			$this->getCurrentUser();
		} else if ($command == 'isLoggedinUserAdmin') {
			$this->isLoggedinUserAdmin();
		} else if ($command == 'isLoggedinUserKitchen') {
			$this->isLoggedinUserKitchen();
		} else if ($command == 'isLoggedinUserBar') {
			$this->isLoggedinUserBar();
		} else if ($command == 'isLoggedinUserAdminOrManagerOrTE') {
			$this->isLoggedinUserAdminOrManagerOrTE();
		} else if ($command == 'hasUserPaydeskRight') {
			$this->hasUserPaydeskRight();
		} else if ($command == 'getJsonMenuItemsAndVersion') {
			$this->getJsonMenuItemsAndVersion();
		} else if ($command == 'getUserList') {
			$this->getUserList();
		} else if ($command == 'getRoleList') {
			$this->getRoleList();
		} else if ($command == 'setTime') {
			$this->setTime($_POST['day'], $_POST['month'], $_POST['year'], $_POST['hour'], $_POST['minute']);
		} else if ($command == 'createNewUser') {
			$this->createNewUser();
		} else if ($command == 'createNewRole') {
			$this->createNewRole();
		} else if ($command == 'updateUser') {
			$this->updateUser();
                } else if ($command == 'userup') {
                        echo json_encode($this->userup());
		} else if ($command == 'updateRole') {
			$this->updateRole();
		} else if ($command == 'deleteUser') {
			if (ISDEMO) {
					echo json_encode(array("status" => "ERROR","msg" => "Vorgang im Demo-System nicht erlaubt", "code" => "FORBIDDEN"));
			} else {
					$this->deleteUser($_POST['userid']);
			}
		} else if ($command == 'deleteRole') {
			if (ISDEMO) {
					echo json_encode(array("status" => "ERROR","msg" => "Vorgang im Demo-System nicht erlaubt", "code" => "FORBIDDEN"));
			} else {
					$this->deleteRole($_POST['roleid']);
			}
		} else if ($command == 'changepassword') {
			$this->changepassword($_POST['userid'], $_POST['password']);
		} else if ($command == 'changeOwnPassword') {
			$this->changeOwnPassword($_POST['oldPass'], $_POST['newPass']);
		} else if ($command == 'setUserLanguage') {
			$this->setUserLanguage($_POST['language']);
		} else if ($command == 'setUserReceiptPrinter') {
			$this->setUserReceiptPrinter($_POST['printer']);
		} else if ($command == 'setUserQuickcash') {
			$this->setUserQuickcash($_POST['value']);
		} else if ($command == 'setBtnSize') {
			$this->setBtnSize($_POST['btn'], $_POST['size']);
		} else if ($command == 'changeConfig') {
			$this->changeConfig($_POST['changed']);
		} else if ($command == 'readlogo') {
			$this->readlogo();
		} else if ($command == 'readnicelogo') {
			$this->readnicelogo();
		} else if ($command == 'readsroomtitleimg') {
				$this->readsroomtitleimg();
		} else if ($command == 'deletelogo') {
			$imgname = 'logoimg';
			if (isset($_GET['img'])) {
					$imgname = $_GET['img'];
			}
			if (in_array($imgname, array('logoimg','nicelogoimg'))) {
					$this->deletelogo($imgname);
			}
		} else if ($command == 'getGeneralConfigItems') {
			$this->getGeneralConfigItems(true, null);
		} else if ($command == 'getGeneralConfigItemsAndUsers') {
			$this->getGeneralConfigItemsAndUsers(true, null);
		} else if ($command == 'getWaiterSettings') {
			$this->getWaiterSettings();
			// from here on admin rights are needed
		} else if ($command == 'getPayPrintType') {
			$this->getPayPrintType();
		} else if ($command == 'getPayments') {
			$this->getPayments();
		} else if ($command == 'autobackup') {
			$this->backup('auto', $_POST['remoteaccesscode'], false);
		} else if ($command == 'autoftpbackup') {
			$this->ftpbackup('auto', $_POST['remoteaccesscode']);
		} else if ($command == 'fill') {
			$this->fillSampleContent();
			echo json_encode(array("status" => "OK"));
		} else if ($command == 'fillSpeisekarte') {
			if (ISDEMO) {
				echo json_encode(array("status" => "ERROR","msg" => "Vorgang im Demo-System nicht erlaubt", "code" => "FORBIDDEN"));
			} else {
				$this->fillSpeisekarte($_POST['speisekarte']);
			}
		} else if ($command == 'fillDefaultSpeisekarte') {
			$this->fillDefaultSpeisekarte();
		} else if ($command == 'backup') {
			$this->backup($_GET['type'], null, false);
			return;
		} else if ($command == 'ftpbackup') {
			$this->ftpbackup($_GET['type'], null);
			return;
		} else if ($command == 'restore') {
			if (ISDEMO) {
					echo json_encode(array("status" => "ERROR","msg" => "Vorgang im Demo-System nicht erlaubt", "code" => "FORBIDDEN"));
					return;
			} else {
					$this->restore();
					return;
			}
		} else if ($command == 'restoreDemoFromZip') {
			if (ISDEMO) {
					echo json_encode(array("status" => "ERROR","msg" => "Vorgang im Demo-System nicht erlaubt", "code" => "FORBIDDEN"));
					return;
			} else {
					$pdo = DbUtils::openDbAndReturnPdoStatic();
					$this->restoreDemoFromZip($pdo);
					return;
			}
		} else if ($command == 'golive') {
			if (ISDEMO) {
					echo json_encode(array("status" => "ERROR","msg" => "Vorgang im Demo-System nicht erlaubt", "code" => "FORBIDDEN"));
					return;
			} else {
					$this->golive();
					return;
			}
		} else if ($command == 'shutdown') {
			if (ISDEMO) {
					echo json_encode(array("status" => "ERROR","msg" => "Vorgang im Demo-System nicht erlaubt", "code" => "FORBIDDEN"));
					return;
			} else {
					$this->shutdown();
					return;
			}
		} else if ($command == 'optimize') {
			if (ISDEMO) {
					echo json_encode(array("status" => "ERROR","msg" => "Vorgang im Demo-System nicht erlaubt", "code" => "FORBIDDEN"));
					return;
			} else {
					$this->optimize();
					return;
			}
		} else if ($command == 'assignTaxes') {
			if (ISDEMO) {
					echo json_encode(array("status" => "ERROR","msg" => "Vorgang im Demo-System nicht erlaubt", "code" => "FORBIDDEN"));
					return;
			} else {
					$this->assignTaxes($_POST['food'], $_POST['drinks']);
					return;
			}
		} else if ($command == 'exportConfigCsv') {
			if ($this->isCurrentUserAdmin() || $this->hasCurrentUserRight('right_manager')) {
				$this->exportConfigCsv();
			}
		} else if ($command == 'exportUserCsv') {
			if ($this->isCurrentUserAdmin() || $this->hasCurrentUserRight('right_manager')) {
				$this->exportUserCsv();
			}
		} else if ($command == 'exportLog') {
			if ($this->isCurrentUserAdmin() || $this->hasCurrentUserRight('right_manager')) {
				$this->exportLog();
			}
		} else if ($command == 'setOrderVolume') {
			if ($this->isUserAlreadyLoggedInForPhp()) {
				$this->setOrderVolume($_POST['volume']);
			}
		} else if ($command == 'setPreferTableMap') {
			if ($this->isUserAlreadyLoggedInForPhp()) {
				$this->setPreferTableMap($_POST['prefertablemap']);
			}
		} else if ($command == 'setPreferimgdesk') {
			if ($this->isUserAlreadyLoggedInForPhp()) {
				$this->setPreferimgdesk($_POST['preferredvalue']);
			}
		} else if ($command == 'setPreferimgmobile') {
			if ($this->isUserAlreadyLoggedInForPhp()) {
				$this->setPreferimgmobile($_POST['preferredvalue']);
			}
		} else if ($command == 'setPrefershowplusminus') {
			if ($this->isUserAlreadyLoggedInForPhp()) {
				$this->setShowplusminus($_POST['preferredvalue']);
			}
		} else if ($command == 'setPreferfixbtns') {
			if ($this->isUserAlreadyLoggedInForPhp()) {
				$this->setPreferfixbtns($_POST['preferredvalue']);
			}
		} else if ($command == 'setPreferCalc') {
			if ($this->isUserAlreadyLoggedInForPhp()) {
				$this->setPreferCalc($_POST['preferredvalue']);
			}
		} else if ($command == 'setKeepTypeLevel') {
			if ($this->isUserAlreadyLoggedInForPhp()) {
				$this->setKeepTypeLevel($_POST['keeptypelevel']);
			}
		} else if ($command == 'setMobileTheme') {
			if ($this->isUserAlreadyLoggedInForPhp()) {
				$this->setMobileTheme($_POST['mobiletheme']);
			}
		} else if ($command == 'setApplyExtrasBtnPos') {
			if ($this->isUserAlreadyLoggedInForPhp()) {
				$this->setExtrasApplyBtnPos($_POST['applyextrasbtnpos']);
			}
		} else if ($command == 'setTablesAfterSend') {
			if ($this->isUserAlreadyLoggedInForPhp()) {
				$this->setTablesAfterSend($_POST['tablesaftersend']);
			}
		} else if ($command == 'getPreferimgmobile') {
			if ($this->isUserAlreadyLoggedInForPhp()) {
				$this->getPreferimgmobile();
			}
		} else if ($command == 'getmobilecss') {
			$this->getmobilecss();
		} else if ($command == 'getMobileTheme') {
			if ($this->isUserAlreadyLoggedInForPhp()) {
				$this->getMobileTheme();
			}
		} else if ($command == 'getPreferCalc') {
			if ($this->isUserAlreadyLoggedInForPhp()) {
				$this->getPreferCalc();
			}
		} else if ($command == 'isInstalled') {
			$this->isInstalled();
		} else if ($command == 'isPrinterServerActive') {
			$this->isPrinterServerActive();
		} else if ($command == 'getWaiterMessage') {
			$this->getWaiterMessage();
		} else if ($command == 'setWaiterMessage') {
			$this->setWaiterMessage($_POST['waitermessage']);
		} else if ($command == 'getDbStat') {
			$this->getDbStat();
		} else if ($command == 'getprinterinstances') {
			$this->getPrinterInstances();
		} else if ($command == 'setprinterinstances') {
			$this->setprinterinstances($_POST["k1"], $_POST["k2"], $_POST["k3"], $_POST["k4"], $_POST["k5"], $_POST["k6"], $_POST["f1"], $_POST["f2"], $_POST["f3"], $_POST["f4"], $_POST["d1"], $_POST["d2"], $_POST["d3"], $_POST["d4"], $_POST["p1"]);
		} else if ($command == 'getdashreports') {
			$this->getdashreports();
		} else if ($command == 'getDailycode') {
			$this->getDailycode();
		} else if ($command == 'askforcompanyinfo') {
			self::askforcompanyinfo();
		} else if ($command == 'setTurbo') {
			$this->setTurbo($_POST["turbo"]);
		} else if ($command == 'getrectemplateall') {
			self::getdefaulttemplate("rectemplate.txt","");
		} else if ($command == 'getrectemplateqrcode') {
			self::getdefaulttemplate("rectemplateqrcode.txt","");
		} else if ($command == 'getrectemplatetext') {
			self::getdefaulttemplate("rectemplatetext.txt","");
		} else if ($command == 'getclstemplate') {
			self::getdefaulttemplate("clstemplate.txt","");
		} else if ($command == 'getclostemplate') {
			self::getdefaulttemplate("closingtemplate.txt","");      
		} else if ($command == 'getcashtemplate') {
			self::getdefaulttemplate("cashtemplate.txt","");
		} else if ($command == 'getfoodworktemplate') {
			self::getdefaulttemplate("generalworktemplate.txt","Speisen");
		} else if ($command == 'getfoodworkbigtemplate') {
			self::getdefaulttemplate("generalworkbigtemplate.txt","Speisen");
		} else if ($command == 'getdrinkworktemplate') {
			self::getdefaulttemplate("generalworktemplate.txt","Getränke");
		} else if ($command == 'getdrinkworkbigtemplate') {
			self::getdefaulttemplate("generalworkbigtemplate.txt","Getränke");
		} else if ($command == 'getfoodworklinetemplate') {
			self::getdefaulttemplate("generalworklinetemplate.txt","Speisen");
		} else if ($command == 'getfoodworkbiglinetemplate') {
			self::getdefaulttemplate("generalworkbiglinetemplate.txt","Speisen");
		} else if ($command == 'getdrinkworklinetemplate') {
			self::getdefaulttemplate("generalworklinetemplate.txt","Getränke");
		} else if ($command == 'getdrinkworkbiglinetemplate') {
			self::getdefaulttemplate("generalworkbiglinetemplate.txt","Getränke");
		} else if ($command == 'getcanceltemplate') {
			self::getdefaulttemplate("canceltemplate.txt","");
		} else if ($command == 'getpickuptemplate') {
			self::getdefaulttemplate("pickuptemplate.txt","");
		} else if ($command == 'dsinvkexport') {
			$this->dsfinvkexport($_GET['format']);
		} else if ($command == 'uploaduserphoto') {
			self::uploaduserphoto();
		} else if ($command == 'getuserphotoinsession') {
			$userid = null;
			if (isset($_GET["userid"])) {
				$userid = $_GET["userid"];
			}
			self::getuserphotoInSession($userid);
		} else if ($command == 'removeuserphoto') {
			$userid = null;
			if (isset($_GET["userid"])) {
				$userid = $_GET["userid"];
			}
			self::removeuserphoto($userid);
		} else if ($command == 'getwaiterphotoforprint') {
			self::getwaiterphotoforprint($_GET["userid"]);
		} else if ($command == 'getcoinsandnotes') {
			self::getcoinsandnotes();
		} else if ($command == 'setshowonlymyjobs') {
			$showonlymyjobs = 0;
			if (isset($_POST["showonlymyjobs"])) {
				$showonlymyjobs = $_POST["showonlymyjobs"];
			}
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			echo json_encode($this->setshowonlymyjobs($pdo,$showonlymyjobs));
		} else if ($command == 'checkemail') {
			self::checkemail($_POST['smtphost'],$_POST['smtpauth'],$_POST['smtpuser'],$_POST['smtppass'],$_POST['smtpsecure'],$_POST['smtpport'],$_POST['email'],$_POST['receiveremail']);
		} else if ($command == 'resetdemo') {
			if (ISDEMO) {
				echo json_encode(self::resetdemo());
			} else {
				echo json_encode(array("status" => "SILENTERROR","msg" => "Einstellungen können auf diese Weise nur im Demo-Modus zurückgesetzt werden.", "code" => "FORBIDDEN"));
			}
		} else if ($command == 'setconfigitem') {
			// this method is not intended for productive use, because it does not impact history!
			if (ISDEMO) {
				echo json_encode(array("status" => "ERROR","msg" => "In Demo-Modus nicht erlaubt")); 
			} else {
				$pdo = DbUtils::openDbAndReturnPdoStatic();
				echo json_encode(self::setconfigitem($pdo,$_GET["item"],$_GET["setting"]));
			}
		} else if ($command == 'setconfigitemrest') {
			// this method is not intended for productive use, because it does not impact history!
			if (isset($_POST['remoteaccesscode'])) {
				$pdo = DbUtils::openDbAndReturnPdoStatic();
				$isCodeOk = self::checkRemoteAccessCode($pdo,$_POST['remoteaccesscode']);
				if ($isCodeOk) {
					echo json_encode(self::setconfigitem($pdo,$_GET["item"],$_GET["setting"]));
				} else {
					echo json_encode(array("status" => "ERROR","msg" => "Falscher Fernzugriffcode")); 
				}
			} else {
				echo json_encode(array("status" => "ERROR","msg" => "Fernzugriffscode nicht gesetzt")); 
			}
		} else if ($command == 'delallprintjobs') {
			if (ISDEMO) {
				echo json_encode(array("status" => "ERROR","msg" => "In Demo-Modus nicht erlaubt")); 
			} else {
				echo json_encode(self::delallprintjobs());
			}
		} else if ($command == 'checksroomaccess') {
			echo json_encode((new SRoomSync())->checkaccess($_POST['sroomurl'],$_POST['sroomcode'],"sroomcode"));
		} else if ($command == 'checkebonaccess') {
			echo json_encode((new EbonSync())->checkaccess($_POST['ebonurl'],$_POST['eboncode'],"eboncode"));
		} else if ($command == 'addperformancedata') {
			echo json_encode(self::addperformancedata(null,$_GET['task'],$_GET['elapsedtime']));
		} else if ($command == 'getdbdiagdata') {
			self::getdbdiagdata($_GET['format']);
		} else if ($command == 'getphpdiagdata') {
			self::getphpdiagdata();
		} else if ($command == 'reset') {
			self::reset();
		} else {
			echo "Command not supported.";
		}
	}

	/***
	 * Is the installation already done? Or was the html/php code overwritten, i.e. a new or updated version to install?
	 */
	private function isInstalled() {
		if(defined('INSTALLSTATUS')){
			if (INSTALLSTATUS == 'new') {
				echo json_encode(array("status" => "OK","msg" => "No"));
			} else {
				echo json_encode(array("status" => "OK","msg" => "Yes"));
			}
		} else {
			echo json_encode(array("status" => "OK","msg" => "No"));
		}
	}
	
	private function isPrinterServerActive() {

		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic(false);
			if (is_null($pdo)) {
				echo json_encode(array("status" => "OK", "msg" => 0, "tasksforme" => '0', "tsestatus" => '0'));
				return;
			}

			$tasksForMe = Tasks::areThereTasksForMe($pdo);
			
			$tsestatus = TSE::checkTseServerAccesible($pdo);
			$binaryTseStatus = 0;
			if ($tsestatus["status"] == "OK") {
				$binaryTseStatus = 1;
			}
			
			Hotelinterface::hs3sync($pdo);

			Guestsync::sync($pdo);


			$active = json_encode(array("status" => "OK", "msg" => 1, "tasksforme" => $tasksForMe, "tsestatus" => $binaryTseStatus));
			$notActive = json_encode(array("status" => "OK", "msg" => 0, "tasksforme" => $tasksForMe, "tsestatus" => $binaryTseStatus));

			if (ISDEMO) {
				echo $active;
				return;
			}
			
			$printMode = CommonUtils::getConfigValue($pdo, 'payprinttype', "s");
			if ($printMode != "s") {
				echo $active;
				return;
			}

			$TIMEOUT = 40;
			
			$sql = "SELECT IF(UNIX_TIMESTAMP(NOW())-COALESCE((SELECT value from %work% where item=?),0) < ?,'ACTIVE','NOTACTIVE') as status";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array('lastprtserveraccess',$TIMEOUT));
			$row = $stmt->fetchObject();
			$activeStatus = $row->status;
			if ($activeStatus == 'ACTIVE') {
					echo $active;
			} else {
					echo $notActive;
			}
		} catch (Exception $ex) {
			error_log("Error in isPrinterServerActive: " . $ex->getMessage());
			echo json_encode(array("status" => "OK", "msg" => 0, "tasksforme" => 0, "tsestatus" => 0));
		}
	}
	
	private static function checkTse($pdo) {
		$tseurl = CommonUtils::getConfigValue($pdo, 'tseurl', "");
		if ($tseurl == "") {
			return true;
		} else {
		}
	}

	function isUserAlreadyLoggedInForPhp() {
		if(session_id() == '') {
			session_start();
		}
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			return false;
		} else {
			return true;
		}
	}

	function isUserAlreadyLoggedIn() {
		if(session_id() == '') {
	    	session_start();
		}
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			echo json_encode("NO");
		} else {
			echo json_encode("YES");
		}
	}
	
	function logout() {
		if(session_id() == '') {
			session_start();
			
		}
                session_destroy();
		echo json_encode("OK");
	}
	
	function tryAuthenticate($userid,$password,$modus,$unixtime) {
		
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$authenticated = false;
		
		$isLoginAllowed = self::checkIsLoginAllowed($pdo,$userid);
		if (!$isLoginAllowed) {
			Logger::logcmd("admin","authentication","Login with id $userid failed");
			echo json_encode(array("status" => "WAIT"));
			return;
		}
		
		$sql = "SELECT *,%user%.id as id FROM %user%,%roles% WHERE %user%.id=? AND active='1' AND %user%.roleid=%roles%.id";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($userid));

		$numberOfEntries = count($result);
		if ($numberOfEntries == 1) {
			$zeile = $result[0];
			$pass_hash = $zeile['userpassword'];

			if (ISDEMO) {
				$authenticated = true;
			}
			
			// password_verify requires PHP > 5.5, so let's use MD5 instead
			// (it is no banking software...)
			$passHashToCheck = md5($password);
			if ($modus == 2) {
				$passHashToCheck = $password;
			} 
			if ($passHashToCheck == $pass_hash) {
				$authenticated = true;
			}
		}

		if ($modus == 2) {
			$modus = CommonUtils::getConfigValue($pdo, "defaultview", 0);	
		}
		if ($authenticated) {
			date_default_timezone_set(DbUtils::getTimeZone());
			$now = getdate();

			$serverTime = $now["0"];
			$timeDiff = 0;
			if (abs($serverTime - $unixtime) > (60*60*2)) {
				$timeDiff = 1;
			}

			if(session_id() == '') {
				session_start();
			}
			$username = $zeile["username"];
			
			$_SESSION['angemeldet'] = true;
			 

			$userid = $zeile['id'];
			$_SESSION['userid'] = $userid;
			$_SESSION['currentuser'] = $username;
			$_SESSION['modus'] = $modus;
			
			
			$workflowconfigfood = $this->getConfigItemsAsString($pdo, "workflowconfig");
                        $workflowconfigdrinks = $this->getConfigItemsAsString($pdo, "workflowconfigdrinks");
			
			Permissions::setPermissionsAfterAuthenticatedLogin($pdo, $zeile, $workflowconfigfood, $workflowconfigdrinks, $userid);
			
			$rights = array();
			$perms = CommonUtils::getPermissions($pdo);
			foreach($perms as $aPermission) {
				if ($aPermission['flag'] == 'normal') {
					$rights[] = $zeile[$aPermission['name']];
				}
			}

			if (Permissions::isOnlyRatingUser()) {
				$_SESSION['keeptypelevel'] = false;
			} else {
				$_SESSION['keeptypelevel'] = ($zeile['keeptypelevel'] == 1 ? true : false);
			}
			
			$_SESSION["roombtnsize"] = $zeile['roombtnsize'];
			$_SESSION["tablebtnsize"] = $zeile['tablebtnsize'];
			$_SESSION["prodbtnsize"] = $zeile['prodbtnsize'];
			
			$language = $zeile['language'];
			if (is_null($language)) {
				$language = 0;
			}
			$_SESSION['language'] = intval($language);
			
			$receiptprinter = $zeile['receiptprinter'];
			if (is_null($receiptprinter)) {
				$receiptprinter = 1;
			}
			$_SESSION['receiptprinter'] = intval($receiptprinter);
			
			$quickcash = $zeile['quickcash'];
			if (is_null($quickcash)) {
				$quickcash = 0;
			}
			$_SESSION['quickcash'] = intval($quickcash);
			
			$preferTm = $zeile['prefertablemap'];
			if (is_null($preferTm)) {
				$preferTm = 1;
			}
			$_SESSION['prefertm'] = intval($preferTm);
			
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$_SESSION['timezone'] = $this->getTimeZone($pdo);
			
			Logger::logcmd("admin","authentication","Login $username successful");
			self::clearFailedLogins($pdo, $userid);
			$loginMessage = $this->getMessage(null,'loginmessage');
			echo json_encode(array("status" => "YES","loginmessage" => $loginMessage, "timediff" => $timeDiff, "isadmin" => $zeile['is_admin'],"lang" => $_SESSION["language"]));
		} else {
			Logger::logcmd("admin","authentication","Login with id $userid failed");
			self::increaseFailedLogins($pdo,$userid);
			
			echo json_encode(array("status" => "NO"));
		}
	}
	
	private static function checkIsLoginAllowed($pdo,$userid) {
		date_default_timezone_set(DbUtils::getTimeZone());
		$now = getdate();
		$serverTime = $now["0"];
		
		$sql = "SELECT failedlogins FROM %user% WHERE id=?";
		$row = CommonUtils::getRowSqlObject($pdo, $sql, array($userid));
		if (is_null($row) || is_null($row->failedlogins)) {
			return true;
		} else {
			$lastFailure = explode("_",$row->failedlogins)[0];
			$attempt = intval(explode("_",$row->failedlogins)[1]);
			
			if ($attempt >= 5) {
				if (abs($serverTime - $lastFailure) > (60*3)) {
					self::clearFailedLogins($pdo, $userid);
					return true;
				} else {
					return false;
				}
			} else {
				return true;
			}
		}
	}
	
	private static function increaseFailedLogins($pdo,$userid) {
		date_default_timezone_set(DbUtils::getTimeZone());
		$now = getdate();
		$serverTime = $now["0"];
		$lastFailure = (string) $serverTime . "_";
			
		$sql = "SELECT failedlogins FROM %user% WHERE id=?";
		$row = CommonUtils::getRowSqlObject($pdo, $sql, array($userid));
		if (is_null($row) || is_null($row->failedlogins)) {
			$lastFailure .= "1";
		} else {
			$attempt = intval(explode("_",$row->failedlogins)[1]);
			$lastFailure .= ($attempt + 1); 
		}
		$sql = "UPDATE %user% SET failedlogins=? WHERE id=?";
		CommonUtils::execSql($pdo, $sql, array($lastFailure,$userid));
	}
	
	private static function clearFailedLogins($pdo,$userid) {
		$sql = "UPDATE %user% SET failedlogins=? WHERE id=?";
		CommonUtils::execSql($pdo, $sql, array(null,$userid));
	}
	
	private function getMessage($pdo,$messageType) {
		if (is_null($pdo)) {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
		}
		$sql = "SELECT value FROM %work% WHERE item=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($messageType));
		$row = $stmt->fetchObject();
		$msg = "";
		if ($stmt->rowCount() > 0) {
			$msg = $row->value;
		}
		return $msg;
	}
	
	function getButtonSizes() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		echo json_encode(self::getButtonSizesCore($pdo));
	}
	
	private static function getButtonSizesCore($pdo) {
		$userid = $_SESSION['userid'];
			
		$sql = "SELECT roombtnsize,tablebtnsize,prodbtnsize FROM %user% WHERE id=?";
	
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($userid));
		$row =$stmt->fetchObject();
	
		$roombtnsize = $row->roombtnsize;
		if (is_null($roombtnsize)) {
			$roombtnsize = 0;
		}
	
		$tablebtnsize = $row->tablebtnsize;
		if (is_null($tablebtnsize)) {
			$tablebtnsize = 0;
		}
	
		$prodbtnsize = $row->prodbtnsize;
		if (is_null($prodbtnsize)) {
			$prodbtnsize = 0;
		}
	
		return(array("roombtnsize" => $roombtnsize,"tablebtnsize" => $tablebtnsize,"prodbtnsize" => $prodbtnsize));
	}
	

	private static function getUserValueCore($pdo,$item,$defaultvalue,$setNullTo0) {
		$userid = $_SESSION['userid'];
		
		if ($setNullTo0) {
			$sql = "SELECT COALESCE($item,0) AS result FROM %user% WHERE id=?";
		} else {
			$sql = "SELECT $item AS result FROM %user% WHERE id=?";
		}
		
		$stmt = $pdo->prepare(Dbutils::substTableAlias($sql));
		$stmt->execute(array($userid));
		if ($stmt->rowCount() == 0) {
			return $defaultvalue;
		}
		$row = $stmt->fetchObject();
		$aVal = 0;
		if ($row != null) {
			$aVal = $row->result;
			if (is_null($aVal)) {
				$aVal = $defaultvalue;
			}
		}
		return $aVal;
	}

	function getPreferimgmobile() {
		self::getUserValue('preferimgmobile', 0);
	}

	private static function getUserValue($item,$defaultvalue) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$value = self::getUserValueCore($pdo,$item,$defaultvalue,false);
		echo json_encode($value);
	}

	public static function getUserValueAllowNull($col) {
		$userid = $_SESSION['userid'];
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$sql = "SELECT COALESCE($col,0) AS result FROM %user% WHERE id=?";
		$row = CommonUtils::getRowSqlObject($pdo, $sql, array($userid));
		$pdo = null;
		return $row->result;
	}
	function getKeepTypeLevel() {
		self::getUserValue('keeptypelevel',1);
	}
	
	private function getUsersMobileTheme($pdo) {
		if (isset($_SESSION['userid'])) {
			$userid = $_SESSION['userid'];
			$sql = "SELECT mobiletheme FROM %user% WHERE id=?";
			$res = CommonUtils::fetchSqlAll($pdo, $sql, array($userid));
			if (count($res) > 0) {
				return $res[0]["mobiletheme"];
			} else {
				return 0;
			}
		}
		return 0;
	}
	
	function getmobilecss() {
		$pdo = DbUtils::openRepliDb();
		$mobileTheme = $this->getUsersMobileTheme($pdo);
		
		switch ($mobileTheme) {
			case 0:
				$cssFile = "orderstyle.min.css";
				$mod = "colorfulstylemod.css";
				break;
			case 1:
				$cssFile = "orderstyle-pale.min.css";
				$mod = "palestylemod.css";
				break;
			case 2:
				$cssFile = "orderstyle-darksoul.min.css";
				$mod = "darkstylemod.css";
				break;
			case 3:
				$cssFile = "orderstyle-stylisch.min.css";
				$mod = "stylischstylemod.css";
				break;
			case 4:
				$cssFile = "orderstyle-bluethunder.min.css";
				$mod = "bluethundermod.css";
				break;
			case 5:
				$cssFile = "orderstyle-cool.min.css";
				$mod = "coolmod.css";
				break;
			case 6:
				$cssFile = "orderstyle-pinklady.min.css";
				$mod = "pinkladymod.css";
				break;
			case 7:
				$cssFile = "orderstyle-greenfield.min.css";
				$mod = "greenfieldmod.css";
				break;
			case 8:
				$cssFile = "orderstyle-brightenergy.min.css";
				$mod = "brightenergymod.css";
				break;
			default:
				$cssFile = "orderstyle-brightenergy.min.css";
				$mod = "brightenergymod.css";
				break;
		}
		
		
		$cssMobileFile = "3rdparty/orderstyle/" . $cssFile;
		$modFile = "../css/" . $mod;
		header('Content-type: text/css');
    
		readfile($cssMobileFile);
		readfile($modFile);
	}
	
	function getMobileTheme() {
		self::getUserValue('mobiletheme', 0);
	}

	function getPreferCalc() {
		self::getUserValue('calcpref', 1);
	}

	
	private static function setUserValue($item,$theValue) {
		$userid = $_SESSION['userid'];
			
		$sql = "UPDATE %user% SET $item=? WHERE id=?";
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$pdo->beginTransaction();
			
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($theValue,$userid));
		$pdo->commit();
		echo json_encode(array("status" => "OK"));
	} 
	function setOrderVolume($volume) {
		self::setUserValue('ordervolume', $volume);
	}
	function setPreferTableMap($preferValue) {
		self::setUserValue('prefertablemap',$preferValue);
	}
	function setPreferimgdesk($preferValue) {
		if ($preferValue == 0) {
			$preferValue = null;
		}
		self::setUserValue('preferimgdesk',$preferValue);
	}
	function setPreferimgmobile($preferValue) {
		if ($preferValue == 0) {
			$preferValue = null;
		}
		self::setUserValue('preferimgmobile',$preferValue);
	}
	function setShowplusminus($preferValue) {
		if ($preferValue == 0) {
			$preferValue = null;
		}
		self::setUserValue('showplusminus',$preferValue);
	}
	function setPreferfixbtns($preferValue) {
		if ($preferValue == 0) {
			$preferValue = null;
		}
		self::setUserValue('preferfixbtns',$preferValue);
	}
	function setPreferCalc($preferValue) {
		self::setUserValue('calcpref',$preferValue);
	}
	function setKeepTypeLevel($preferValue) {
		self::setUserValue('keeptypelevel',$preferValue);
	}
	function setMobileTheme($preferValue) {
		self::setUserValue('mobiletheme', $preferValue);
	}
	function setExtrasApplyBtnPos($preferValue) {
		self::setUserValue('extrasapplybtnpos',$preferValue);
	}
	function setTablesAfterSend($preferValue) {
		if ($preferValue == 1) {
			$preferValue = null;
		}
		self::setUserValue('tablesaftersend',$preferValue);
	}
	private function setLastModuleOfUser($view) {
		try {
			if ($this->isUserAlreadyLoggedInForPhp()) {
				if ($view != "logout.php") {
					try {
						$userid = $_SESSION['userid'];
						
						$questPos = strpos($view,'?');
						if ($questPos != false) {
							$view = substr($view,0,$questPos);
						}
						
						$sql = "UPDATE %user% SET lastmodule=? WHERE id=? AND active='1'";
						$pdo = DbUtils::openDbAndReturnPdoStatic();
						CommonUtils::execSql($pdo,$sql,array($view,$userid));
					} catch(Exception $ex) {
						error_log("Setting last module failed: " . $ex->getMessage());
						echo json_encode(array("status" => "Error","msg" => "Last Module cannot be set: " . $ex->getMessage()));
						return;
					}
				}
				echo json_encode(array("status" => "OK"));
			} else {
				echo json_encode(array("status" => "Error","msg" => "Benutzer nicht eingeloggt"));
			}
		} catch (Exception $ex) {
			// the method is not that important. In case of deadlocks or whatever just log it and silently ignore it
			error_log("Error in setLastModuleOfUser: " . $ex->getMessage());
			echo json_encode(array("status" => "OK"));
		}
	}
	
	public function getConfigItemsAsString($pdo,$key) {
		$sql = "SELECT setting FROM %config% WHERE name=?";
		
		if (is_null($pdo)) {
			return "";
		}
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array($key));
		$row = $stmt->fetchObject();

		if ($stmt->rowCount() == 0) {
			return "";
		}
		
		$theValue = $row->setting;
		
		if (is_null($theValue)) {
			return "";
		} else {
			return $theValue; 
		}
	}	
	
	public static function overruleTimeZone($timezone) {
		self::$timezone = $timezone;
		DbUtils::overruleTimeZone($timezone);
	}
	
	public function getTimeZone($pdo) {
		if (is_null(self::$timezone)) {
			$timezone = $this->getConfigItemsAsString($pdo, "timezone");
			if ($timezone == "") {
				$timezone = "Europe/Berlin";
			}
			return $timezone;
		} else {
			return self::$timezone;
		}
	}
	public function getEnv($pdo) {
		$installdate = $this->getConfigItemsAsString($pdo, "installdate");
		$lastupdate = $this->getConfigItemsAsString($pdo, "lastupdate");
		$version = $this->getConfigItemsAsString($pdo, "version");
		return(array("version" => $version, "installdate" => $installdate, "lastupdate" => $lastupdate));
	}
	
	private function getWaiterSettings() {
		$pdo = DbUtils::openRepliDb();
		$userLoggedIn = $this->isUserAlreadyLoggedInForPhp();
		if (!$userLoggedIn) {
			$retVal = array("isUserLoggedIn" => 0);
			echo json_encode($retVal);
			return;
		}

		$items = array(
			array("decpoint",".",true),
			array("version","V.unbekannt",true),
			array("cancelunpaidcode","0",true),
			array("cancelinpaydesk","0",true),
			array("delaydigiworkprint","0",true),
			array("kitchenshoworderuser","0",true),
			array("tax",19.9,true),
			array("togotax",7.0,true),
			array("taxaustrianormal",0,true),
			array("taxaustriaerm1",0,true),
			array("taxaustriaerm2",0,true),
			array("taxaustriaspecial",0,true),
			array("currency","Euro",true),
			array("workflowconfig",0,true),
			array("workflowconfigdrinks",0,true),

			array("prominentsearch",0,true),
			array("discount1",null,true),
			array("discount2",null,true),
			array("discount3",null,true),
			array("discountname1","",true),
			array("discountname2","",true),
			array("discountname3","",true),

			array("waitergopayprint",1,true),
			array("cashenabled",1,false),

			array("returntoorder",1,false),
			array("restaurantmode",1,false),
			array("usebarcode",0,false),
			array("needcrinbarcode",0,false),

			array("commentinpaydesk",0,false),
			array("showreceiptinpaydesk",1,false),
			array("pickupsnoauth",0,false),
			array("startprodsearch",3,false),
			array("barcodedelimiter",'#',false),

			array("priceinlist",0,false),
			array("showartcommentmobile",1,false),
			array("showordercommentmob",1,false),
			array("showdaycode",null,false),

			array("dailycode",null,false),
			array("showtogo",1,false),
			array("billprintjobs",2,false),
			array("showtransferbtns",1,false),
			array("enableorders",null,false),
			array("invertarticlename",0,true),

			array("showphases",null,false),
			array("defaultphase",null,false),
			array("handleamount",0,false),
			array("priceassignedmobile",0,false),
			array("priceassigneddesktop",0,false)
		);
	
		$configresult = $this->getGeneralConfigItems(false,$pdo,false,$items);

		if ($userLoggedIn) {
			$sql = "SELECT language,right_supply,right_changeprice,right_cashop,keeptypelevel,extrasapplybtnpos,right_paydesk,COALESCE(calcpref,1) as calcpref,COALESCE(preferimgdesk,0) as preferimgdesk,COALESCE(preferimgmobile,0) as preferimgmobile,COALESCE(showplusminus,0) as showplusminus,COALESCE(preferfixbtns,0) as preferfixbtns,COALESCE(tablesaftersend,1) as tablesaftersend,COALESCE(quickcash,0) as quickcash,COALESCE(mobiletheme,1) as mobiletheme FROM %user%,%roles% WHERE %user%.id=? AND %user%.roleid=%roles%.id";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($_SESSION['userid']));
			$row = $stmt->fetchObject();
		}

		$buttonSizes = self::getButtonSizesCore($pdo);
		
		$jsonMenuItems = $this->getJsonMenuItemsAndVersionCore($pdo);
		
		$currentUser = $this->getCurrentUserPhp($pdo);
		$ordervolume = self::getUserValueCore($pdo,'ordervolume',0,false);
		$prefertablemap = self::getUserValueCore($pdo,'prefertablemap',1,false);
		$retVal = array("config" => $configresult,
				"rightchangeprice" => $row->right_changeprice,
				"rightpaydesk" => $row->right_paydesk,
				"rightcashop" => $row->right_cashop,
				"supplyright" => $row->right_supply,
				"userlanguage" => $row->language,
				"buttonsizes" => $buttonSizes,
				"keeptypelevel" => $row->keeptypelevel,
				"extrasapplybtnpos" => $row->extrasapplybtnpos,
				"tablesaftersend" => $row->tablesaftersend,
				"isUserLoggedIn" => 1,
				"jsonMenuItemsAndVersion" => $jsonMenuItems,
				"preferimgdesk" => $row->preferimgdesk,
				"preferimgmobile" => $row->preferimgmobile,
				"showplusminus" => $row->showplusminus,
				"preferfixbtns" => $row->preferfixbtns,
				"quickcash" => $row->quickcash,
				"mobiletheme" => $row->mobiletheme,
				"calcpref" => $row->calcpref,
				"currentuser" => $currentUser,
				"ordervolume" => $ordervolume,
				"prefertablemap" => $prefertablemap
		);
		echo json_encode($retVal);
	}
	
	private function getGeneralConfigItemsAndUsers($forHtml,$pdo) {
		return $this->getGeneralConfigItems($forHtml, $pdo, true);
	}
	
	public function getGeneralConfigItems($forHtml,$pdo,$includeUserInfo = false,$items = null) {
		$userLoggedIn = $this->isUserAlreadyLoggedInForPhp();
				
		if ($userLoggedIn || (!$forHtml)) {

			if (is_null($items)) {

				$items = array(
					array("systemid",1,false),
					array("companyinfo",null,false),
					array("orderemailtext",null,false),
					array("hosttext",null,false),
					array("rectemplate",null,false),
					array("clstemplate",null,false),
					array("cashtemplate",null,false),
					array("foodtemplate",null,false),
					array("drinktemplate",null,false),
					array("canceltemplate",null,false),
					array("clostemplate",null,false),
					array("pickuptemplate",null,false),
					array("decpoint",null,false),
					array("version",null,false),
					array("payprinttype",null,false),

					array("tax",null,false),
					array("togotax",null,false),
					array("taxaustrianormal",null,false),
					array("taxaustriaerm1",null,false),
					array("taxaustriaerm2",null,false),
					array("taxaustriaspecial",null,false),
					array("serverurl",null,false),
					array("guesturl",null,false),
					array("guestcode",null,false),
					array("dailycode",null,false),
					
					array("email",null,false),
					array("bigfontworkreceipt",null,false),
					array("prominentsearch",null,false),
					array("guestjobprint",null,false),
					array("guestarticleconfirm",null,false),
					array("guesttheme",null,false),
					array("askdaycode",null,false),
					array("showdaycode",null,false),
					array("showkitsel",null,false),
					array("showbarsel",null,false),
					array("asktablecode",null,false),
					array("guesttimeout",5,false),
					array("discount1",null,false),
					array("discount2",null,false),
					array("discount3",null,false),
					array("austria",null,false),
					array("digigopaysetready",1,false),
					array("waitergopayprint",0,false),
					array("oneprodworkrecf",0,false),
					array("oneprodworkrecd",0,false),
					array("digiprintwork",1,false),
					array("groupworkitemsf",1,false),
					array("groupworkitemsd",1,false),
					array("receiveremail","",false),
					array("smtpsecure",1,false),
					array("smtpauth",1,false),
					
					array("emailbadrating","",false),
					array("emailratingcontact","",false),
					array("billlanguage",null,false),
					array("enableorders",null,false),
					array("hotelinterface",0,false),
					array("hsinfile",null,false),
					array("hsoutfile",null,false),
					array("hscurrency",null,false),
					array("currency",null,false),
					array("receiptfontsize",null,false),
					array("reservationnote",null,false),
					
					array("paymentconfig",0,false),
					array("workflowconfig",0,false),
					array("workflowconfigdrinks",0,false),
					array("dashslot1",1,false),
					array("dashslot2",2,false),
					array("dashslot3",3,false),
					array("addreceipttoprinter","",false),
					array("printandqueuejobs",0,false),
					array("cashenabled",1,false),
					array("returntoorder",1,false),
					array("beepcooked",0,false),
					array("allprodstoreceipt",0,false),
					array("beepordered",0,false),
					array("taskallassign",0,false),
					array("taskifempty",0,false),
					array("taskownerempty",0,false),
					array("showtogo",1,false),
					array("showtableforcustomer",0,false),
					
					array("closshowci",1,false),
					array("closshowpaytaxes",1,false),
					array("closshowprods",1,false),
					array("showpayments",1,false),
					array("showpayment2",1,false),
					array("showpayment3",1,false),
					array("showpayment4",1,false),
					array("showpayment5",1,false),
					array("showpayment6",1,false),
					array("showpayment7",1,false),
					array("showpayment8",1,false),
					array("restaurantmode",1,false),
					array("usebarcode",0,false),
					array("needcrinbarcode",0,false),
					array("defaultview",0,false),
					array("dblog",1,false),
					array("showtransferbtns",1,false),
					array("printpickups",0,false),
					array("printpickupsdrinks",0,false),
					array("billprintjobs",2,false),
					array("printextras",0,false),
					array("forceprint",0,false),
					array("priceinlist",0,false),
					array("startprodsearch",3,false),
					array("barcodedelimiter",'#',false),
					
					array("discountname1","",true),
					array("discountname2","",true),
					array("discountname3","",true),
					
					array("memorylimit",256,false),
					array("minbeforecome",0,false),
					array("minaftergo",0,false),
					array("updateurl","",false),
					array("tmpdir","",false),
					array("hs3refresh",60,false),
					array("paydeskid","",false),
					array("aeskey","",false),
					array("cbirdfolder","",false),
					
					array("certificatesn","",false),
					array("rksvserver","",false),
					array("webimpressum","",false),
					array("showprepinwaiter",1,false),
					
					array("pollbills",2,false),
					array("showpickupsno",20,false),
					array("showhostprint",1,false),
					array("oneclickcooked",0,false),
					array("showpickupdelbtn",1,false),
					array("showpickhelp",1,false),
					
					array("sumupforcard",0,false),
					array("affiliatekey",'',true),
					array("appid",'',true),
					array("sumupfailuretext","",false),
					
					array("printcash",0,false),
					array("showerrorlog",1,false),
					array("logolocation",1,false),
					array("austriabind",0,false),
					array("doublereceipt",0,false),
					array("printextraprice",1,false),
					array("turbo",5,false),
					array("guestqrtext",null,false),
					array("guestqrsize",null,false),
					array("guestqrfontsize",null,false),
					array("reservationitem",null,false),
					
					array("sn",null,false),
					
					array("dsfinvk_name",'',true),
					array("dsfinvk_street",'',true),
					array("dsfinvk_postalcode",'',true),
					array("dsfinvk_city",'',true),
					array("dsfinvk_country",'',true),
					array("dsfinvk_stnr",'',true),
					array("dsfinvk_ustid",'',true),
					
					array("tseurl","",true),
					array("fiskalyapikey","",true),
					array("fiskalyapisecret","",true),
					
					array("fonparticipantid","",true),
					array("fonuserid","",true),
					array("fonuserpin","",true),
					
					array("rksvleinumber","",true),
					array("rksvleiname","",true),
					
					array("fonauthstate","",true),
					array("rksvscuid","",true),
					array("rksvscustate","",true),
					array("rksvcashregid","",true),
					array("rksvcashregstate","",true),
								
					array("usetse",0,false),
					array("allowminuscheapest",0,false),
					
					array("coins",'',true),
					array("notes",'',true),
					array("coinvalname",'',true),
					array("notevalname",'',true),
					
					array("kitchenextrasize",null,false),
					array("kitchenoptionsize",null,false),
								
					array("cat1name",null,true),
					array("cat2name",null,true),
					array("prodlistname",null,true),
					array("cat1viewname",null,true),
					array("cat2viewname",null,true),
					array("deskviewname",null,true),
					array("mostsoldasfavs",0,false),
					array("allowguestexport",0,false),
					
					array("publishlocation",-1,false),
					array("publishperformance",-1,false),
					array("publishfeatures",null,false),
					
					array("allowexceedamount",-1,false),
					array("showphases",null,false),
					array("bartransitbeforecls",0,false),
					array("handleamount",0,false),
					array("guestshowsoldprods",null,false),
					
					array("sroomurl",null,false),
					array("sroomcode",null,false),
					array("sroomtitle",null,false),
					array("sroomimpressum",null,false),
					array("sroomprivacy",null,false),
					array("sroomcss",null,false),
					array("sroomabout",null,false),
					array("sroomnews",null,false),
					array("sroomfood",null,false),
					array("sroomdrinks",null,false),
					array("sroomutilization",null,false),
					array("sroomprodview",null,false),
					array("sroomonlygarticles",null,false),
					array("sroomshowworkload",-1,false),
					
					array("perftoinflux",-1,false),
					array("salestoinflux",-1,false),
					array("influxurl",null,false),
					array("influxbucket",null,false),
					array("influxorg",null,false),
					array("influxtoken",null,false),
					array("influxupdfreq",null,false),
					array("influxperflabel",null,false),
					array("influxsaleslabel",null,false),
					array("influxtablelabel",null,false),
					array("influxsoldlabel",null,false),
					
					array("commentinpaydesk",0,false),
					array("showreceiptinpaydesk",0,false),
					array("pickupsnoauth",0,false),
					array("showonlymyjobscheck",0,false),
					
					array("ebonurl",null,false),
					array("eboncode",null,false),
					array("showartcommentmobile",1,false),
					array("showordercommentmob",1,false),
					array("cancelinpaydesk",null,false),
					array("delaydigiworkprint",null,null),
					array("kitchenshoworderuser",null,null),
					array("priceassignedmobile",0,false),
					array("priceassigneddesktop",0,false),
					array("defaultphase",0,false),
					array("invertarticlename",null,false)
				);
							
				if ( (isset($_SESSION['is_admin']) && ($_SESSION['is_admin'] == 1)) || (isset($_SESSION['right_manager']) && ($_SESSION['right_manager']))) {
					$items[] = array("cancelunpaidcode",null,false);
					$items[] = array("cancelguestcode",null,false);
					$items[] = array("tsepass","",true);
					$items[] = array("tsepin","",true);
					$items[] = array("tsepuk","",true);

					$items[] = array("smtphost","",true);
					$items[] = array("smtpuser","",true);
					$items[] = array("smtppass","",true);
					$items[] = array("smtpport","",true);

					$items[] = array("ftphost","",true);
					$items[] = array("ftpuser","",true);
					$items[] = array("ftppass","",true);
				}
			}
			if (is_null($pdo)) {
				$pdo = DbUtils::openRepliDb();
			}

			$configNames = array_map(function($item) { return $item[0]; }, $items);

			$assocConfigItems = array();
			foreach ($items as $anItem) {
				$assocConfigItems[$anItem[0]] = $anItem;
			}

			$sql = "SELECT name, setting FROM %config%";
			$completeConfigInDb = CommonUtils::fetchSqlAll($pdo,$sql);
			$retVal = array();
			foreach($completeConfigInDb as $aConfigSetting) {
				$key = $aConfigSetting['name'];
				if (array_key_exists($key,$assocConfigItems)) {
					$value = $aConfigSetting['setting'];
					if (!is_null($assocConfigItems[$key][2]) && ($assocConfigItems[$key][2])) {
						$value = trim($value);
					}
					$retVal[$key] = $value;
				}
			}

			foreach(array_keys($assocConfigItems) as $aKey) {
				if (!array_key_exists($aKey,$retVal)) {
					$retVal[$aKey] = $assocConfigItems[$aKey][1];
				}
			}

			$userlang = 0; // of no interest, if not called from web
			$receiptprinter = 1; // of no interest, if not called from web
			$right_changeprice = 0;
			$quickcash = 0;
			if ($userLoggedIn) {
				$userlang = $_SESSION["language"];
				$receiptprinter = $_SESSION['receiptprinter'];
				$quickcash = $_SESSION['quickcash'];
				$right_changeprice = ($_SESSION['right_changeprice'] ? 1 : 0);
					$showonlymyjobs = $this->getShowOnlyMyOrders($pdo);
			}
			
			$defaultTmp = sys_get_temp_dir();
			
			date_default_timezone_set(DbUtils::getTimeZone());
			$now = getdate();
			
			$retVal["userlanguage"] = $userlang;
			$retVal["receiptprinter"] = $receiptprinter;
			$retVal["quickcash"] = $quickcash;
			$retVal["rightchangeprice"] = $right_changeprice;
			$retVal["usershowonlymyjobs"] = $showonlymyjobs;
			
			$retVal["sday"] = $now["mday"];
			$retVal["smonth"] = $now["mon"];
			$retVal["syear"] = $now["year"];
			$retVal["shour"] = $now["hours"];
			$retVal["smin"] = $now["minutes"];
			
			$retVal["defaulttmp"] = $defaultTmp;
			
			$taskownerempty = 0;
			if (array_key_exists("taskownerempty",$retVal)) {
				$taskownerempty = $retVal["taskownerempty"];
			}
			if ($taskownerempty == "") {
				$taskownerempty = 0;
			}
			if (is_null($taskownerempty)) {
				$taskownerempty = 0;
			}
			$sql = "SELECT active FROM %user% WHERE id=?";
			$result = CommonUtils::fetchSqlAll($pdo, $sql,array($taskownerempty));
			if (count($result) > 0) {
				$active = $result[0]["active"];
				if ($active != 1) {
					$taskownerempty = 0;
				}
			}
			
			$retVal["taskownerempty"] = $taskownerempty;
			
			if ($includeUserInfo) {
				$sql = "SELECT U.id as id,username,is_admin,right_manager FROM %user% U,%roles% R WHERE active='1' ";
				$sql .= " AND U.roleid=R.id AND (R.right_tasks=? OR R.right_tasksmanagement=?) ";
				$sql .= " ORDER BY sorting";
				$activeUsers = CommonUtils::fetchSqlAll($pdo, $sql,array(1,1));
				$retVal["activeusers"] = $activeUsers;
			}
			
			$userMayDoCashops = Permissions::canUserDoCashOps($pdo,$_SESSION['userid']);
			$retVal["usercandocashop"] = ($userMayDoCashops ? 1 : 0);
			
			if ($forHtml) {
				echo json_encode(array("status" => "OK", "msg" => $retVal));
			} else {
				return $retVal;
			}
		} else {
			if ($forHtml) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_NOT_AUTHOTRIZED, "msg" => ERROR_NOT_AUTHOTRIZED_MSG));
			} else {
				return null;
			}
		}
	}
	
	private function setshowonlymyjobs($pdo,$value) {
			if ($this->isUserAlreadyLoggedInForPhp()) {
					$userid = $_SESSION['userid'];
					$sql = "UPDATE %user% SET showonlymyjobs=? WHERE id=?";
					CommonUtils::execSql($pdo, $sql, array($value,$userid));
					return array("status" => "OK");
			} else {
					return array("status" => "ERROR","msg" => "Nicht eingeloggt");
			}
	}
	
	public function getShowOnlyMyOrders($pdo) {
			if ($this->isUserAlreadyLoggedInForPhp()) {
					$userid = $_SESSION['userid'];
					$sql = "SELECT showonlymyjobs FROM %user% WHERE id=?";
					$ret = CommonUtils::fetchSqlAll($pdo, $sql, array($userid));
					if (count($ret) == 1) {
							$showonlymyjobs = $ret[0]['showonlymyjobs'];
					}
			} else {
					$showonlymyjobs = 0;
			}
			return $showonlymyjobs;
	}
        
	function getViewAfterLogin() {
		if ($this->isUserAlreadyLoggedInForPhp()) {
			$userid = $_SESSION['userid'];
			$modus = $_SESSION['modus'];

			if (Permissions::isOnlyRatingUser()) {
				echo json_encode("rating.html");
				return;
			}
			
			$sql = "SELECT lastmodule FROM %user% WHERE id=? AND active='1'";
			$pdo = $this->dbutils->openDbAndReturnPdo();
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($userid));
			$row =$stmt->fetchObject();
			
			$view = "preferences.html";
			if ($row != null) {
				$newView = $row->lastmodule;
				if ($newView != null) {
					$view = $newView;
					
					if ($modus == 1) {
					    if (($view == "waiter.html") || ($view == "paydesk.html") || ($view == "waiterdesktop.php")) {
						$view = "waiterdesktop.php";
					    }
					} else {
					    if ($view == "waiterdesktop.php") {
						$view = "waiter.html";
					    }
					}
				}
			}
			
			$valid = false;
			if (($view == 'preferences.html') || ($view == 'feedback.html') || ($view == 'help.php')) {
				$valid = true;
			} else if ($view == 'manager.html') {
				if (($_SESSION['is_admin']) || ($_SESSION['right_manager']) || ($_SESSION['right_closing'])) {
					$valid = true;
				}
			} else if (($view == 'waiter.html') && ($_SESSION['right_waiter'])) {
				$valid = true;
			} else if (($view == 'waiterdesktop.php') && ($_SESSION['right_waiter'] || $_SESSION['right_paydesk'])) {
				$valid = true;
			} else if ($view == 'index.html') {
				echo json_encode($view . "?v=2.9.12");
				return true;
			} else {
                                $perms = CommonUtils::getPermissions($pdo);
				foreach($perms as $aPerm) {
					if (isset($aPerm['view'])) {
						$viewName = $aPerm['view'];
						$permName = $aPerm['name'];
						if (($viewName == $view) && ($_SESSION[$permName])) {
							echo json_encode($view . "?v=2.9.12");
							return true;
						}
					}
				}
			}
			if ($valid == false) {
				$view = "preferences.html";
			}
			
			echo json_encode($view . "?v=2.9.12");
		}
	}
	
	function isLoggedinUserAdmin() {
		if ($this->isCurrentUserAdmin()) {
			echo json_encode(YES);
		} else {
			echo json_encode(NO);
		}
	}
	
    function isLoggedinUserAdminOrManagerOrTE() {
	if ($this->hasCurrentUserRight('is_admin')) {
	    echo json_encode("admin");
	} else if ($this->hasCurrentUserRight('right_manager')) {
	    echo json_encode("manager");
	} else if ($this->hasCurrentUserRight('right_closing')) {
	    echo json_encode("closing");
	} else {
	    echo json_encode(NO);
	}
    }

    function isLoggedinUserKitchen() {
		if ($this->hasCurrentUserRight('right_kitchen')) {
			echo json_encode(YES);
		} else {
			echo json_encode(NO);
		}
	}
	
	function isLoggedinUserBar() {
		if ($this->hasCurrentUserRight('right_bar')) {
			echo json_encode(YES);
		} else {
			echo json_encode(NO);
		}
	}
	
	function hasUserPaydeskRight() {
		if ($this->hasCurrentUserRight('right_paydesk')) {
			echo json_encode(YES);
		} else {
			echo json_encode(NO);
		}
	}
	
	function hasUserReservationRight() {
		if ($this->hasCurrentUserRight('right_reservation')) {
			echo json_encode(YES);
		} else {
			echo json_encode(NO);
		}
	}
	
	function hasCurrentUserRight($whichRight) {
		if(session_id() == '') {
	    	session_start();
		}
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			return false;
		} else {
			return ($_SESSION[$whichRight] == 1 ? true : false);
		}
	}
	
	function isCurrentUserAdmin() {
		return $this->hasCurrentUserRight('is_admin');
	}


	function fillSampleContentBySqlFile($pdo,$sqlFile) {
		$handle = fopen ($sqlFile, "r");
		while (!feof($handle)) {
			$sql = fgets($handle);
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute();
		}
		fclose ($handle);
	}
	
	private function assignTaxes($foodTax,$drinksTax) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$pdo->beginTransaction();
		
		try {			
			$sql = "UPDATE %products%,%prodtype% SET %products%.taxaustria=? WHERE %products%.category=%prodtype%.id AND %prodtype%.kind=? AND %products%.removed is null AND %prodtype%.removed is null";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));

			$stmt->execute(array($foodTax,0));
			$stmt->execute(array($drinksTax,1));
			
			HistFiller::readAllProdsAndFillHistByDb($pdo);
			
			$pdo->commit();
			echo json_encode (array("status" => "OK"));
		} catch (Exception $e) {
			$pdo->rollBack();
			echo json_encode(array("status" => "ERROR", "code" => NUMBERFORMAT_ERROR, "msg" => NUMBERFORMAT_ERROR_MSG, "errormsg" => $e->getMessage()));
		}
		
	}
	
        private function fillDefaultSpeisekarte() {
                $menu = file_get_contents(__DIR__ . "/../customer/speisekarte.txt");
                $pdo = DbUtils::openDbAndReturnPdoStatic();
                CommonUtils::execSql($pdo, "UPDATE %products% SET removed='1'",null);
                $ret = $this->fillSpeisekarteCore($pdo,$menu);
		if ($ret["status"] != "OK") {
			echo "Fehler";
		} else {
			echo "Speisekarte eingelesen";
		}
        }
        
	private function fillSpeisekarte($speisekarte) {		
		$pdo = DbUtils::openDbAndReturnPdoStatic();
			
		$pdo->beginTransaction();
		
		$ret = $this->fillSpeisekarteCore($pdo,$speisekarte);
		if ($ret["status"] != "OK") {
			$pdo->rollBack();
		} else {
			$pdo->commit();
		}
		echo json_encode($ret);
	}
	
	public function fillSpeisekarteCore($pdo,$speisekarte,$doCleanProdImages = true) {
		CommonUtils::execSql($pdo, "DELETE FROM %extrasprods%", null);
		
		$speisekartenHandler = new TypeAndProductFileManager();
		
		$ret = $speisekartenHandler->manageSpeisekarte($pdo,$speisekarte);

		if ($doCleanProdImages) {
			Products::cleanProdImagesTable($pdo);
		}
		
		$basedb = new Basedb();
		$basedb->sortProdTypes($pdo);
                
		return $ret;
	}
	
	private function fillSampleContent()
	{
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$sql = "DELETE FROM `%queue%`";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
                CommonUtils::execSql($pdo, "DELETE FROM %prodnames%", null);
                
		$this->fillSampleContentBySqlFile($pdo,"samples/queuecontent.txt");
		
		$sql = "DELETE FROM `%hist%` WHERE action='3' OR action='7' OR action='8'";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		
		$sql = "DELETE FROM `%histuser%`";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		
		$sql = "DELETE FROM `%user%`";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		$this->fillSampleContentBySqlFile($pdo,"samples/usercontent.txt");

		$this->histfiller->readUserTableAndSendToHist($pdo);
	}
	
	function getJsonMenuItemsAndVersion() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		echo json_encode($this->getJsonMenuItemsAndVersionCore($pdo));
	}
	
	/*
	 * Return all the entries for the main menu (the modules)
	 */
	private function getJsonMenuItemsAndVersionCore($pdo) {
		if(session_id() == '') {
			session_start();
		}
		$mainMenu = array();
		$currentUser = "";
		$waiterMessage = "";
		$loggedIn = true;
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			$mainMenu[] = array("name" => "Startseite", "link" => "index.html");
			$loggedIn = false;
		} else {
			$lang = $_SESSION['language'];
			$settingtxt = array("Einstellungen","Preferences","Propriedades");
			$waitertxt = array("Bestellung","Orderdesk","Camarero");
                        $deskviewname = CommonUtils::getConfigValue($pdo, "deskviewname", "Kellneransicht");
                        $waiterdesktxt = array($deskviewname,$deskviewname,$deskviewname);
			$paydesktxt = array("Kasse","Paydesk","Caja");		
			$timetrackingtxt = array("Zeiterfassung","Time tracking","Tiempos");
			$admintxt = array("Administration","Administration","Administrar");
			$logout = array("Abmelden","Log out","Adios");

			if (!Permissions::isOnlyRatingUser()) {
			    if ($_SESSION['modus'] == 0) {
				if ($_SESSION['right_waiter']) { $mainMenu[] = array("name" => $waitertxt[$lang], "link" => "waiter.html?v=2.9.12"); }
				if ($_SESSION['right_paydesk']) { $mainMenu[] = array("name" => $paydesktxt[$lang], "link" => "paydesk.html?v=2.9.12"); }
			    } else {
				if ($_SESSION['right_waiter']) { $mainMenu[] = array("name" => $waiterdesktxt[$lang], "link" => "waiterdesktop.php?v=2.9.12"); }
			    }
			    
			    
			    $timetrackingAlreadyInserted = false;
			    $adminAlreadyInserted = false;
                            $perms = CommonUtils::getPermissions($pdo);
			    foreach ($perms as $aPermission) {
					if (isset($aPermission['menu'])) {
						$menuName = $aPermission['menu'];
						$rightName = $aPermission['name'];
						$link = $aPermission['view'];
						if ($_SESSION[$rightName]) { 
							$mainMenu[] = array("name" => $menuName[$lang], "link" => $link . "?v=2.9.12");
							if (($rightName == 'right_timetracking') || ($rightName == 'right_timemanager')) {
								$timetrackingAlreadyInserted = true;
							}
							if (($rightName == 'is_admin') || ($rightName == 'right_manager')) {
								$adminAlreadyInserted = true;
							}
						}
					}
				}

			    $mainMenu[] = array("name" => $settingtxt[$lang], "link" => "preferences.html?v=2.9.12");
			    if ($_SESSION['right_timetracking'] || $_SESSION['right_timemanager']) {
				    if (!$timetrackingAlreadyInserted) {
					$mainMenu[] = array("name" => $timetrackingtxt[$lang], "link" => "timetracking.html?v=2.9.12");
				    }
			    }
			    if ($_SESSION['is_admin'] || $_SESSION['right_manager'] || $_SESSION['right_closing']) {
					$mainMenu[] = array("name" => $admintxt[$lang], "link" => "manager.html?v=2.9.12");
			    }
			    
			    $mainMenu[] = array("name" => "Hilfe", "link" => "help.php?v=2.9.12");
			    $mainMenu[] = array("name" => "Feedback", "link" => "feedback.html?v=2.9.12");
			}
			
			$mainMenu[] = array("name" => $logout[$lang], "link" => "logout.php");
			$currentUser = $_SESSION['currentuser'];
			
			$waiterMessage = $this->getMessage(null, "waitermessage");
		}
		$installedVersion = CommonUtils::getConfigValue($pdo, 'version', '?');
		
		$mainMenuAndVersion = array ("version" => "OrderSprinter $installedVersion", 
				"user" => $currentUser, 
				"menu" => $mainMenu, 
				"waitermessage" => $waiterMessage,
				"loggedin" => ($loggedIn ? 1:0)
		);
		return($mainMenuAndVersion);
	}
	
	private function getPrinterInstances() {
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$k1 = CommonUtils::getConfigValue($pdo, 'k1prinstance', 1);
			$k2 = CommonUtils::getConfigValue($pdo, 'k2prinstance', 1);
			$k3 = CommonUtils::getConfigValue($pdo, 'k3prinstance', 1);
			$k4 = CommonUtils::getConfigValue($pdo, 'k4prinstance', 1);
			$k5 = CommonUtils::getConfigValue($pdo, 'k5prinstance', 1);
			$k6 = CommonUtils::getConfigValue($pdo, 'k6prinstance', 1);
			$f1 = CommonUtils::getConfigValue($pdo, 'f1prinstance', 1);
			$f2 = CommonUtils::getConfigValue($pdo, 'f2prinstance', 1);
			$f3 = CommonUtils::getConfigValue($pdo, 'f3prinstance', 1);
			$f4 = CommonUtils::getConfigValue($pdo, 'f4prinstance', 1);
			$d1 = CommonUtils::getConfigValue($pdo, 'd1prinstance', 1);
			$d2 = CommonUtils::getConfigValue($pdo, 'd2prinstance', 1);
			$d3 = CommonUtils::getConfigValue($pdo, 'd3prinstance', 1);
			$d4 = CommonUtils::getConfigValue($pdo, 'd4prinstance', 1);
			$p1 = CommonUtils::getConfigValue($pdo, 'p1prinstance', 1);
			
			$ret = array("k1" => $k1,"k2" => $k2,"k3" => $k3, "k4" => $k4,"k5" => $k5,"k6" => $k6,"d1" => $d1,"d2" => $d2,"d3" => $d3,"d4" => $d4,"f1" => $f1,"f2" => $f2,"f3" => $f3,"f4" => $f4,"p1" => $p1);
			echo json_encode(array("status" => "OK","msg" => $ret));
			
		} catch (Exception $ex) {
			echo json_encode(array("status" => "ERROR","msg" => "Error: " . $ex->getMessage()));
			return;
		}
	}
	
	private function setprinterinstances($k1,$k2,$k3,$k4,$k5,$k6,$f1,$f2,$f3,$f4,$d1,$d2,$d3,$d4,$p1) {
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$sql = "UPDATE %config% SET setting=? WHERE name=?";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($k1,"k1prinstance"));
			$stmt->execute(array($k2,"k2prinstance"));
			$stmt->execute(array($k3,"k3prinstance"));
			$stmt->execute(array($k4,"k4prinstance"));
			$stmt->execute(array($k5,"k5prinstance"));
			$stmt->execute(array($k6,"k6prinstance"));
			
			$stmt->execute(array($f1,"f1prinstance"));
			$stmt->execute(array($f2,"f2prinstance"));
			$stmt->execute(array($f3,"f3prinstance"));
			$stmt->execute(array($f4,"f4prinstance"));
			
			$stmt->execute(array($d1,"d1prinstance"));
			$stmt->execute(array($d2,"d2prinstance"));
			$stmt->execute(array($d3,"d3prinstance"));
			$stmt->execute(array($d4,"d4prinstance"));
			
			$stmt->execute(array($p1,"p1prinstance"));
			
			echo json_encode(array("status" => "OK"));
			
		} catch (Exception $ex) {

		}
	}
	
	private function getdashreports() {
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			
			$reports = new Reports();
			$stat = $reports->getStatsCore($pdo,true);
			
                        UsedFeatures::noteUsedFeature($pdo, UsedFeatures::$Dashboard);
			echo json_encode(array("status" => "OK","msg" => array("stat" => $stat)));
			
		} catch (Exception $ex) {
			echo json_encode(array("status" => "ERROR","msg" => "Error: " . $ex->getMessage()));
			return;
		}
	}
	
	private function getDailycode() {
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic(false);
			if (is_null($pdo)) {
				echo json_encode(array("status" => "ERROR","msg" => "Error: Db access not possible."));
				return;
			}
			$dailycode = CommonUtils::getConfigValue($pdo, 'dailycode', "");	
			echo json_encode(array("status" => "OK","msg" => $dailycode));	
		} catch (Exception $ex) {
			echo json_encode(array("status" => "ERROR","msg" => "Error: " . $ex->getMessage()));
			return;
		}
	}
	
	private function setTurbo($turbo) {
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$hist = new HistFiller();
			$hist->updateConfigInHist($pdo, "turbo", $turbo);
			echo json_encode(array("status" => "OK"));	
		} catch (Exception $ex) {
			echo json_encode(array("status" => "ERROR","msg" => "Error: " . $ex->getMessage()));
			return;
		}
	}

	private static function getdefaulttemplate(string $templatename, string $categoryname) {
		$template = file_get_contents(__DIR__. '/../customer/' . $templatename);
                $template = str_replace("KATEGORIE", $categoryname, $template);
		echo json_encode(array("status" => "OK","msg" => $template));
	}
	
	private function getDbStat() {
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			
			$sql = "SELECT  table_name, round(sum( data_length + index_length ) / 1024) as tablesizeinmb FROM information_schema.TABLES ";
			$sql .= " WHERE table_schema=? AND table_name like ? group by table_name order by table_name";
			$result = CommonUtils::fetchSqlAll($pdo, $sql, array(MYSQL_DB,TAB_PREFIX . "%"));
			
			$max = 0;
			foreach ($result as $aTableResult) {
				$size = intval($aTableResult["tablesizeinmb"]);
				if ($max < $size) {
					$max = $size;
				}
			}
			
			echo json_encode(array("status" => "OK","msg" => array("max" => $max,"tablesizes" => $result)));
		} catch (Exception $e) {
			echo json_encode(array("status" => "ERROR","msg" => "Error: $e"));
			return;
		}
	}
	
	private function getWaiterMessage() {
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$waiterMessage = $this->getMessage($pdo, "waitermessage");
			echo json_encode(array("status" => "OK","msg" => $waiterMessage));
		} catch (Exception $e) {
			echo json_encode(array("status" => "ERROR","msg" => "Error: " . $e->getMessage()));
			return;
		}
	}
        
        private function setWaiterMessage($waiterMessage) {
                try {
                        $msg = trim($waiterMessage);
                        
			$pdo = DbUtils::openDbAndReturnPdoStatic();
                        if ($msg == "") {
                                CommonUtils::execSql($pdo, 'DELETE FROM %work% WHERE item=?', array('waitermessage'));
                        } else {
                                $sql = "SELECT COUNT(item) as msgnumber from %work% where item=?";
                                $res = CommonUtils::fetchSqlAll($pdo, $sql, array('waitermessage'));
                                $noOfEntries = intval($res[0]['msgnumber']);
                                if ($noOfEntries == 0) {
                                        CommonUtils::execSql($pdo, 'INSERT INTO %work% (item,value) VALUES(?,?)', array('waitermessage',$msg));
                                } else {
                                        CommonUtils::execSql($pdo, "UPDATE %work% SET value=? WHERE item=?", array($msg,'waitermessage'));
                                }
                        }
			echo json_encode(array("status" => "OK","msg" => $waiterMessage));
		} catch (Exception $e) {
			echo json_encode(array("status" => "ERROR","msg" => "Error: " . $e->getMessage()));
			return;
		}
        }
	
	private function getHotelInfo($pdo) {
		try {
			$hotelinterface = CommonUtils::getConfigValue($pdo, "hotelinterface", 0);
			$guests = array();
			if ($hotelinterface == 1) {
				$sql = "SELECT reservationid,object,guest FROM %hsout%";
				$guests = CommonUtils::fetchSqlAll($pdo, $sql, null);
			}
			return(array("status" => "OK","hotelinterface" => $hotelinterface,"guests" => $guests));
		} catch (Exception $e) {
			return(array("status" => "ERROR","hotelinterface" => 0,"guests" => array(),"msg" => "Error: " . $e->getMessage()));
		}
	}
	
	private function getGuestInfo($pdo) {
		try {
			$sql = "SELECT %customers%.id as id,%customers%.id as object,CONCAT(COALESCE(name,''),' - ',COALESCE(room,'')) as guest ";
			$sql .= " FROM %customers% ";
                        $sql .= " LEFT JOIN %vacations% ON %customers%.id=%vacations%.customerid ";
			$sql .= " WHERE ";
			$sql .= " ((checkin <= CURDATE()) AND (CURDATE() <= checkout)) ";
			$sql .= " OR (checkin is null AND (CURDATE() <= checkout)) ";
			$sql .= " OR ((checkin <= CURDATE()) AND checkout is null) ";
                        $sql .= " OR (permanent = '1') ";
			$sql .= " GROUP BY id,object,guest";
			$guests = CommonUtils::fetchSqlAll($pdo, $sql, null);
			return(array("status" => "OK","guests" => $guests));
		} catch (Exception $ex) {
			return(array("status" => "ERROR","msg" => "Error: " . $ex->getMessage()));
		}
	}
	
	function getRoleList() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$roles = $this->getRoleListCore($pdo);
		echo json_encode(array("status" => "OK","msg" => $roles));
	}
	
	function getRoleListCore($pdo) {
		$perms = array();
		$permForSqlQuery = array();
		$roleNames = array();

                $permsList = CommonUtils::getPermissions($pdo);
		foreach ($permsList as $aPerm) {
			$perms[] = $aPerm['name'];
			$roleNames[] = $aPerm['rolename'];
			$permForSqlQuery[] = $aPerm['name'] . ' DESC';
		}
		
		$sql = 'SELECT * FROM %roles% ORDER BY ' . implode(',',$permForSqlQuery);
		$roles = CommonUtils::fetchSqlAll($pdo, $sql, null);
		
		$sql = "SELECT * from %roles% ORDER BY is_admin,right_manager,right_waiter DESC,right_kitchen DESC,right_bar DESC,right_paydesk DESC,right_bill DESC,right_supply DESC,right_tasks DESC,right_tasksmanagement DESC";
		$roles = CommonUtils::fetchSqlAll($pdo, $sql);

		return array("roles" => $roles, "rightnames" => $perms, "rolenames" => $roleNames);
	}
	
	function getUserList() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$sql = "SELECT *,%user%.id as id,COALESCE(%user%.fullname,'') as fullname,COALESCE(%user%.isowner,'0') as isowner,is_admin,right_manager,COALESCE(%user%.area,'0') as tablearea,COALESCE(%user%.articletag,'') as articletag FROM %user%,%roles% WHERE active='1' AND %user%.roleid=%roles%.id ORDER BY %user%.sorting";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		$rolesInfo = $this->getRoleListCore($pdo);
		$roles = $rolesInfo["roles"];
		
		$defaultview = CommonUtils::getConfigValue($pdo, "defaultview", 0);
		
		echo json_encode(array("users" => $users,"roles" => $roles,"defaultview" => $defaultview));
	}
	
	function setTime($day,$month,$year,$hour,$min) {
		if (!($this->userrights->hasCurrentUserRight('is_admin'))) {
			echo json_encode (array("status" => "ERROR","msg" => "Benutzerrechte nicht ausreichend!"));
			return false;
		} else {
			
			$txt = sprintf("%02d", $month) .  sprintf("%02d", $day) .  sprintf("%02d", $hour) .  sprintf("%02d", $min) . $year = substr($year, -2);

			try {
				if (substr(php_uname(), 0, 7) == "Windows"){
					echo json_encode (array("status" => "ERROR","msg" => "Zeit auf Windows-Server kann nicht gesetzt werden!"));
					return false;
				}
				else {
					$cmd = "date \"$txt\"";
					shell_exec($cmd . " > /dev/null &");
				}
			} catch(Exception $e) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_SCRIPT_NOT_EXECUTABLE, "msg" => ERROR_SCRIPT_NOT_EXECUTABLE_MSG,"errormsg" => $e->getMessage()));
			}
			
			$this->getGeneralConfigItems(true,null);
		}
	}

	function updateRole() {
		
		if(session_id() == '') {
			session_start();
		}
		
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		
		if (($_POST["is_admin"] == 1) && (!$_SESSION['is_admin'])) {
			echo json_encode(array("status" => "ERROR","msg" => "Nicht-Admins dürfen keine Rollen mit Admin-Rechten setzen!"));
			return;
		}
		
		$roleid = $_POST["roleid"];
		$roleIsAdmin = self::isRoleAdmin($pdo, $roleid);

		if ($roleIsAdmin && !($this->isCurrentUserAdmin())) {
			echo json_encode(array("status" => "ERROR","msg" => "Benutzer ist kein Admin und darf keine Admin-Rollen bearbeiten!"));
			return;
		}

		$sql = "SELECT id FROM %user% WHERE roleid=? AND active='1'";
		$effectedUsers = CommonUtils::fetchSqlAll($pdo, $sql, array($roleid));
		
		$keys = array();
		$vals = array();

                $perms = CommonUtils::getPermissions($pdo);
		foreach ($perms as $aPerm) {
			$key=$aPerm['name'];
			$keys[] = $key . "=?";
			$vals[] = $_POST[$key];
		}
		
		$keys[] = "name=?";
		$vals[] = $_POST["name"];
		$vals[] = $roleid;

		$keysStr = join(",",$keys);
		$sql = "UPDATE %roles% SET " . $keysStr . " WHERE id=?";
		CommonUtils::execSql($pdo, $sql, $vals);
		
		foreach( $effectedUsers as $aUser) {
			$userid = $aUser["id"];
			HistFiller::updateUserInHist($pdo, $userid);
		}
		
		echo json_encode(array("status" => "OK"));
	}
	
	function createNewRole() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		
		$keys = array();
		$vals = array();
		$quests = array();
				
                $perms = CommonUtils::getPermissions($pdo);
		foreach ($perms as $aPerm) {
			$keys[] =$aPerm['name'];
			$vals[] = $_POST[$aPerm['name']];
			$quests[] = '?';
		}
		$keys[] = "name";
		$vals[] = $_POST["name"];
		$quests[] = '?';
						
		$keysStr = join(",",$keys);
		$questsStr = join(",",$quests);
		$sql = "INSERT INTO %roles% (" . $keysStr . ") VALUES(" . $questsStr . ")";
		CommonUtils::execSql($pdo, $sql, $vals);

		echo json_encode(array("status" => "OK"));
				
	}
	
	function createNewUser() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		
                $sql = "SELECT MAX(sorting) as maxsort FROM %user% U WHERE active='1'";
                $res = CommonUtils::fetchSqlAll($pdo, $sql);
                if (count($res) == 0) {
                        $maxSorting=-1;
                } else {
                        $maxSorting = $res[0]['maxsort'];
                        if (is_null($maxSorting)) {
                                $maxSorting = -1;
                        } else if (is_string($maxSorting)) {
                                $maxSorting = intval($maxSorting);
                        }
                }
                $nextSorting = $maxSorting+1;
                
		$username = $_POST['name'];
                $fullname = $_POST['fullname'];
                $isowner = $_POST['isowner'];
		$password = $_POST['password'];
		$roleid = $_POST['roleid'];
		$area = $_POST['area'];
		if ($area == 0) {
			$area = null;
		}
                $articletag = self::getArticleTagFromPostData();
		
		$sql = "SELECT count(id) as countid FROM %user% WHERE active='1' AND username=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($username));
		$row = $stmt->fetchObject();

		if ($row->countid > 0) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_NAME_EXISTS_ALREADY, "msg" => ERROR_NAME_EXISTS_ALREADY_MSG));
			return;
		} else {
			
			if(session_id() == '') {
				session_start();
			}			
			$lang = $_SESSION['language'];

			$roleIsAdmin = self::isRoleAdmin($pdo, $roleid);
			if ($roleIsAdmin && !($this->isCurrentUserAdmin())) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_COMMAND_NOT_ADMIN, "msg" => ERROR_COMMAND_NOT_ADMIN_MSG));
				return;
			} else {
				$password_hash = md5($password);
				
				$sql = "INSERT INTO %user% (username,fullname,isowner,userpassword,roleid,area,articletag,language,showplusminus,keeptypelevel,extrasapplybtnpos,prefertablemap,preferimgdesk,preferimgmobile,active,sorting) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
				$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
				$stmt->execute(array($username,$fullname,$isowner,$password_hash,$roleid,$area,$articletag,$lang,1,1,1,1,1,1,1,$nextSorting));
			
				$lastId = $pdo->lastInsertId();
				
				HistFiller::createUserInHist($pdo, $lastId);

				echo json_encode(array("status" => "OK"));
			}
		}
	}
	
	function getPayPrintType() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$this->sendJsonValueFromConfigTable($pdo,'payprinttype');
	}
        
        public static function getAllowedPayments($pdo):array {
                if(session_id() == '') {
			session_start();
		}
		$lang = $_SESSION['language'];
                $defaultColNameForPaymentName = "name";
                if ($lang == 1) {
                        $defaultColNameForPaymentName = "name_en";
                } else if ($lang == 2) {
                        $defaultColNameForPaymentName = "name_esp";
                }
                $sql = "SELECT id,$defaultColNameForPaymentName as name FROM %payment% WHERE isallowed='1'";
                return CommonUtils::fetchSqlAll($pdo, $sql);
        }
        
        public static function getPaymentwayName($pdo, $paymentid, $billlanguage): string {
                $paymentCol = "name";
                if ($billlanguage == 1) {
                        $paymentCol = "name_en";
                } else if ($billlanguage == 2) {
                        $paymentCol = "name_esp";
                }

                $sql = "SELECT $paymentCol as name FROM %payment% WHERE id=?";
                $res = CommonUtils::fetchSqlAll($pdo, $sql, array($paymentid));
                return $res[0]['name'];
        }

        function getPayments() {
		if(session_id() == '') {
			session_start();
		}
		$pdo = DbUtils::openDbAndReturnPdoStatic();
                $allPayments = self::getAllowedPayments($pdo);
		
		$hotelinfo = $this->getHotelInfo($pdo);
		$internalguests = $this->getGuestInfo($pdo);
		
		echo json_encode(array("payments" => $allPayments, "hotelinterface" => $hotelinfo["hotelinterface"],"guests" => $hotelinfo["guests"],"internalguests" => $internalguests["guests"]));
	}
	
	function sendJsonValueFromConfigTable($pdo,$whichValue) {
		$theVal = CommonUtils::getConfigValue($pdo, $whichValue, "");
		if ($theVal == null) {
			echo json_encode("");
		} else {
			echo json_encode($theVal);
		}
	}

	private static function changeItemInTable($pdo,$theItem,$theValue,$theTable) {
		$sql = "SELECT id FROM $theTable WHERE name=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($theItem));
		if (count($result) == 0) {
			$sql = "INSERT INTO $theTable (name,setting) VALUES(?,?)";
			CommonUtils::execSql($pdo, $sql, array($theItem,$theValue));
		} else {
			$sql = "UPDATE $theTable SET setting=? WHERE name=?";
			CommonUtils::execSql($pdo, $sql, array($theValue,$theItem));
		}
	}
	
	private function deletelogo($imgname) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		self::changeItemInTable($pdo, $imgname, null, "%logo%");
		echo json_encode("OK");
	}
	
	private function readlogo() {
                $this->readlogoCore('logoimg',300);
	}
        private function readnicelogo() {
                $this->readlogoCore('nicelogoimg',600);
	}
        private function readsroomtitleimg() {
                $pdo = DbUtils::openDbAndReturnPdoStatic();
                $this->readlogoCore('sroomtitleimg',1200);
                (new SRoomSync())->sync($pdo);
                
        }
        
        private function readlogoCore($itemname,$maxDim) {
	
		if ($_FILES['logofile']['error'] != UPLOAD_ERR_OK               //checks for errors
				&& is_uploaded_file($_FILES['logofile']['tmp_name'])) { //checks that file is uploaded
			echo json_encode(array("status" => "ERROR","msg" => "Kann Datei nicht laden"));
			return;
		}
		
		if(!file_exists($_FILES['logofile']['tmp_name']) || !is_uploaded_file($_FILES['logofile']['tmp_name'])) {
			echo json_encode(array("status" => "ERROR","msg" => "Datei nicht angegeben"));
			return;
		}

		if ($_FILES['logofile']['error'] != UPLOAD_ERR_OK               //checks for errors
				&& is_uploaded_file($_FILES['logofile']['tmp_name'])) { //checks that file is uploaded
			echo json_encode(array("status" => "ERROR","msg" => "Kann Datei nicht laden"));
			return;
		}
		
		$pdo = DbUtils::openDbAndReturnPdoStatic();

		$img = CommonUtils::scaleImg($_FILES['logofile']['tmp_name'], $maxDim);
		$imageScaled = $img["img"];
		self::changeItemInTable($pdo, $itemname, $imageScaled, "%logo%");

		echo json_encode(array("status" => "OK"));
	}
	
	private static function returnInRange($aVal,$min,$max, $default) {
		$aVal = trim($aVal);
		
		if (!ctype_digit($aVal)) {
			$aVal = $default;
		}
		$aVal = intval($aVal);
		if (($aVal < $min) || ($aVal > $max)) {
			$aVal = $default;
		}
		
		return $aVal;
	}
	
        private static function isShowRoomInSetOfChangedValues($valuesToChange,$valueSpecifications) {
		foreach ($valuesToChange as $aChangeSet) {
			$name = $aChangeSet['name'];
			$valSpec = $valueSpecifications[$name];
			if (isset($valSpec["isforshowroom"])) {
				$sforshowroom = $valSpec["isforshowroom"];
				if ($sforshowroom === 1) {
					return true;
				}
			}
		}
		return false;
	}
        
	private static function isMasterDataInSetOfChangedValues($valuesToChange,$valueSpecifications) {
		foreach ($valuesToChange as $aChangeSet) {
			$name = $aChangeSet['name'];
			$valSpec = $valueSpecifications[$name];
			if (isset($valSpec["ismasterdata"])) {
				$isMasterData = $valSpec["ismasterdata"];
				if ($isMasterData === 1) {
					return true;
				}
			}
		}
		return false;
	}
	
	function changeConfig($changedValues) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$valueSpecifications = array(
				"systemid" => array("dbcol" => "systemid","checknum" => 0,"ismasterdata" => 1),
				"usstval" => array("dbcol" => "tax","checknum" => 1,"ismasterdata" => 1),
				"togotaxval" => array("dbcol" => "togotax","checknum" => 1,"ismasterdata" => 1),
				"taxaustrianormalval" => array("dbcol" => "taxaustrianormal","checknum" => 1),
				"taxaustriaerm1val" => array("dbcol" => "taxaustriaerm1","checknum" => 1),
				"taxaustriaerm2val" => array("dbcol" => "taxaustriaerm2","checknum" => 1),
				"taxaustriaspecialval" => array("dbcol" => "taxaustriaspecial","checknum" => 1),
				"stornocode" => array("dbcol" => "stornocode","checknum" => 0),
				"printpass" => array("dbcol" => "printpass","checknum" => 0),
				"companyinfo" => array("dbcol" => "companyinfo","checknum" => 0,"ismasterdata" => 0),
                                "orderemailtext" => array("dbcol" => "orderemailtext","checknum" => 0,"ismasterdata" => 0),
				"hosttext" => array("dbcol" => "hosttext","checknum" => 0),
				"rectemplate" => array("dbcol" => "rectemplate","checknum" => 0),
				"clstemplate" => array("dbcol" => "clstemplate","checknum" => 0),
				"cashtemplate" => array("dbcol" => "cashtemplate","checknum" => 0),
				"foodtemplate" => array("dbcol" => "foodtemplate","checknum" => 0),
				"drinktemplate" => array("dbcol" => "drinktemplate","checknum" => 0),
				"canceltemplate" => array("dbcol" => "canceltemplate","checknum" => 0),
				"clostemplate" => array("dbcol" => "clostemplate","checknum" => 0),
				"pickuptemplate" => array("dbcol" => "pickuptemplate","checknum" => 0),
				"serverUrl" => array("dbcol" => "serverurl","checknum" => 0,"ismasterdata" => 0),
				"guesturl" => array("dbcol" => "guesturl","checknum" => 0),
				"guestcode" => array("dbcol" => "guestcode","checknum" => 0),
				"dailycode" => array("dbcol" => "dailycode","checknum" => 0),
				"email" => array("dbcol" => "email","checknum" => 0),
				"emailbadrating" => array("dbcol" => "emailbadrating","checknum" => 0),
				"emailratingcontact" => array("dbcol" => "emailratingcontact","checknum" => 0),
				"receiveremail" => array("dbcol" => "receiveremail","checknum" => 0),
				"payprinttype" => array("dbcol" => "payprinttype","checknum" => 0),
				"paymentconfig" => array("dbcol" => "paymentconfig","checknum" => 0),
				"addreceipttoprinter" => array("dbcol" => "addreceipttoprinter", "checknum" => 0),
				"bigfontworkreceipt" => array("dbcol" => "bigfontworkreceipt","checknum" => 0),
				"prominentsearch" => array("dbcol" => "prominentsearch","checknum" => 0),
				"guestjobprint" => array("dbcol" => "guestjobprint","checknum" => 0),
                                "guestarticleconfirm" => array("dbcol" => "guestarticleconfirm","checknum" => 0),
				"guesttheme" => array("dbcol" => "guesttheme","checknum" => 0),
				"askdaycode" => array("dbcol" => "askdaycode","checknum" => 0),
				"asktablecode" => array("dbcol" => "asktablecode","checknum" => 0),
				"showdaycode" => array("dbcol" => "showdaycode","checknum" => 0),
                                "showkitsel" => array("dbcol" => "showkitsel","checknum" => 0),
                                "showbarsel" => array("dbcol" => "showbarsel","checknum" => 0),
				"guesttimeout"=> array("dbcol" => "guesttimeout","checknum" => 0),
				"discount1" => array("dbcol" => "discount1","checknum" => 0),
				"discount2" => array("dbcol" => "discount2","checknum" => 0),
				"discount3" => array("dbcol" => "discount3","checknum" => 0),
				"austria" => array("dbcol" => "austria","checknum" => 0,"ismasterdata" => 0),
				"digigopaysetready" => array("dbcol" => "digigopaysetready","checknum" => 0),
				"waitergopayprint" => array("dbcol" => "waitergopayprint","checknum" => 0),
				"oneprodworkrecf" => array("dbcol" => "oneprodworkrecf","checknum" => 0),
				"oneprodworkrecd" => array("dbcol" => "oneprodworkrecd","checknum" => 0),
				"digiprintwork" => array("dbcol" => "digiprintwork","checknum" => 0),
				"groupworkitemsf" => array("dbcol" => "groupworkitemsf","checknum" => 0),
				"groupworkitemsd" => array("dbcol" => "groupworkitemsd","checknum" => 0),
				"workflowconfig" => array("dbcol" => "workflowconfig","checknum" => 0),
                                "workflowconfigdrinks" => array("dbcol" => "workflowconfigdrinks","checknum" => 0),
				"dashslot1" => array("dbcol" => "dashslot1","checknum" => 0),
				"dashslot2" => array("dbcol" => "dashslot2","checknum" => 0),
				"dashslot3" => array("dbcol" => "dashslot3","checknum" => 0),
				"receiptfontsize" => array("dbcol" => "receiptfontsize","checknum" => 0),
				"billlanguage" => array("dbcol" => "billlanguage","checknum" => 0),
                                "enableorders" => array("dbcol" => "enableorders","checknum" => 0),
				"hotelinterface" => array("dbcol" => "hotelinterface","checknum" => 0),
				"hsinfile" => array("dbcol" => "hsinfile","checknum" => 0),
				"hsoutfile" => array("dbcol" => "hsoutfile","checknum" => 0),
				"hscurrency" => array("dbcol" => "hscurrency","checknum" => 0),
				"reservationnote" => array("dbcol" => "reservationnote","checknum" => 0),
				"remoteaccesscode" => array("dbcol" => "remoteaccesscode","checknum" => 0),
				"webimpressum" => array("dbcol" => "webimpressum","checknum" => 0),
				"cancelunpaidcode" => array("dbcol" => "cancelunpaidcode","checknum" => 0),
                                "cancelinpaydesk" => array("dbcol" => "cancelinpaydesk","checknum" => 0),
                                "delaydigiworkprint" => array("dbcol" => "delaydigiworkprint","checknum" => 0),
                                "kitchenshoworderuser" => array("dbcol" => "kitchenshoworderuser","checknum" => 0),
				"cancelguestcode" => array("dbcol" => "cancelguestcode","checknum" => 0),
				"printandqueuejobs" => array("dbcol" => "printandqueuejobs","checknum" => 0),
				"cashenabled" => array("dbcol" => "cashenabled","checknum" => 0),
				"returntoorder" => array("dbcol" => "returntoorder","checknum" => 0),
				"beepcooked" => array("dbcol" => "beepcooked","checknum" => 0),
                                "allprodstoreceipt" => array("dbcol" => "allprodstoreceipt","checknum" => 0),
				"beepordered" => array("dbcol" => "beepordered","checknum" => 0),
				"taskallassign" => array("dbcol" => "taskallassign","checknum" => 0),
				"taskifempty" => array("dbcol" => "taskifempty","checknum" => 0),
				"taskownerempty" => array("dbcol" => "taskownerempty","checknum" => 0),
		    "showtogo" => array("dbcol" => "showtogo","checknum" => 0),
                    "showtableforcustomer" => array("dbcol" => "showtableforcustomer","checknum" => 0),
		    "showhostprint" => array("dbcol" => "showhostprint","checknum" => 0),
		    "oneclickcooked" => array("dbcol" => "oneclickcooked","checknum" => 0),
		    "showpickupdelbtn" => array("dbcol" => "showpickupdelbtn","checknum" => 0),
		    "showpickhelp" => array("dbcol" => "showpickhelp","checknum" => 0),
		    	    
		    "closshowci" => array("dbcol" => "closshowci","checknum" => 0),
		    "closshowpaytaxes" => array("dbcol" => "closshowpaytaxes","checknum" => 0),
		    "closshowprods" => array("dbcol" => "closshowprods","checknum" => 0),
		    
		    "showpayments" => array("dbcol" => "showpayments","checknum" => 0),
		    "showpayment2" => array("dbcol" => "showpayment2","checknum" => 0),
		    "showpayment3" => array("dbcol" => "showpayment3","checknum" => 0),
		    "showpayment4" => array("dbcol" => "showpayment4","checknum" => 0),
		    "showpayment5" => array("dbcol" => "showpayment5","checknum" => 0),
		    "showpayment6" => array("dbcol" => "showpayment6","checknum" => 0),
		    "showpayment7" => array("dbcol" => "showpayment7","checknum" => 0),
		    "showpayment8" => array("dbcol" => "showpayment8","checknum" => 0),

				"restaurantmode" => array("dbcol" => "restaurantmode","checknum" => 0,"ismasterdata" => 0),
				"usebarcode" => array("dbcol" => "usebarcode","checknum" => 0),
                                "needcrinbarcode" => array("dbcol" => "needcrinbarcode","checknum" => 0),
				"defaultview" => array("dbcol" => "defaultview", "checknum" => 0),
				"dblog" => array("dbcol" => "dblog","checknum" => 0),
				"showtransferbtns" => array("dbcol" => "showtransferbtns","checknum" => 0),
				"printpickups" => array("dbcol" => "printpickups","checknum" => 0),
                                "printpickupsdrinks" => array("dbcol" => "printpickupsdrinks","checknum" => 0),
				"billprintjobs" => array("dbcol" => "billprintjobs","checknum" => 0),
				"printextras" => array("dbcol" => "printextras","checknum" => 0),
				"forceprint" => array("dbcol" => "forceprint","checknum" => 0),
				"priceinlist" => array("dbcol" => "priceinlist","checknum" => 0),
                                "showartcommentmobile" => array("dbcol" => "showartcommentmobile","checknum" => 0),
                                "showordercommentmob" => array("dbcol" => "showordercommentmob","checknum" => 0),
				"smtphost" => array("dbcol" => "smtphost","checknum" => 0),
				"smtpauth" => array("dbcol" => "smtpauth","checknum" => 1),
				"smtpuser" => array("dbcol" => "smtpuser","checknum" => 0),
				"smtppass" => array("dbcol" => "smtppass","checknum" => 0),
				"smtpsecure" => array("dbcol" => "smtpsecure","checknum" => 1),
				"smtpport" => array("dbcol" => "smtpport","checknum" => 0),
				"startprodsearch" => array("dbcol" => "startprodsearch","checknum" => 1),
				"barcodedelimiter" => array("dbcol" => "barcodedelimiter","checknum" => 0),
		    
				"discountname1" => array("dbcol" => "discountname1","checknum" => 0),
				"discountname2" => array("dbcol" => "discountname2","checknum" => 0),
				"discountname3" => array("dbcol" => "discountname3","checknum" => 0),

				"memorylimit" => array("dbcol" => "memorylimit","checknum" => 0),
				"minbeforecome" => array("dbcol" => "minbeforecome","checknum" => 0),
				"minaftergo" => array("dbcol" => "minaftergo","checknum" => 0),
				"updateurl" => array("dbcol" => "updateurl","checknum" => 0),
				"tmpdir" => array("dbcol" => "tmpdir","checknum" => 0),
				"ftphost" => array("dbcol" => "ftphost","checknum" => 0),
				"ftpuser" => array("dbcol" => "ftpuser","checknum" => 0),
				"ftppass" => array("dbcol" => "ftppass","checknum" => 0),
				"hs3refresh" => array("dbcol" => "hs3refresh","checknum" => 0),
		    
				"pollbills" => array("dbcol" => "pollbills","checknum" => 0),
				"showpickupsno" => array("dbcol" => "showpickupsno","checknum" => 0),
		    
				"paydeskid" => array("dbcol" => "paydeskid","checknum" => 0),
				"aeskey" => array("dbcol" => "aeskey","checknum" => 0),
				"certificatesn" => array("dbcol" => "certificatesn","checksum" => 0),
				"rksvserver" => array("dbcol" => "rksvserver","checksum" => 0),
				"showprepinwaiter" => array("dbcol" => "showprepinwaiter","checksum" => 0),
				"cbirdfolder" => array("dbcol" => "cbirdfolder","checknum" => 0),
		    
		    "sumupforcard" => array("dbcol" => "sumupforcard","checknum" => 0),
		    "affiliatekey" => array("dbcol" => "affiliatekey","checknum" => 0),
		    "appid" => array("dbcol" => "appid","checknum" => 0),
		    "sumupfailuretext" => array("dbcol" => "sumupfailuretext","checknum" => 0),
		    
		    "printcash" => array("dbcol" => "printcash","checknum" => 0),
		    "showerrorlog" => array("dbcol" => "showerrorlog","checknum" => 0),
		    "logolocation" => array("dbcol" => "logolocation","checknum" => 1),
		    "austriabind" => array("dbcol" => "austriabind","checknum" => 0),
		    "doublereceipt" => array("dbcol" => "doublereceipt","checknum" => 0),
		    "printextraprice" => array("dbcol" => "printextraprice","checknum" => 0),
		    "guestqrtext" => array("dbcol" => "guestqrtext","checknum" => 0),
		    "guestqrsize" => array("dbcol" => "guestqrsize","checknum" => 1),
		    "guestqrfontsize" => array("dbcol" => "guestqrfontsize","checknum" => 1),
		    "reservationitem" => array("dbcol" => "reservationitem","checknum" => 0),
		    
		    "dsfinvk_name" => array("dbcol" => "dsfinvk_name","checknum" => 0,"ismasterdata" => 1),
		    "dsfinvk_street" => array("dbcol" => "dsfinvk_street","checknum" => 0,"ismasterdata" => 1),
		    "dsfinvk_postalcode" => array("dbcol" => "dsfinvk_postalcode","checknum" => 0,"ismasterdata" => 1),
		    "dsfinvk_city" => array("dbcol" => "dsfinvk_city","checknum" => 0,"ismasterdata" => 1),
		    "dsfinvk_country" => array("dbcol" => "dsfinvk_country","checknum" => 0,"ismasterdata" => 1),
		    "dsfinvk_stnr" => array("dbcol" => "dsfinvk_stnr","checknum" => 0,"ismasterdata" => 1),
		    "dsfinvk_ustid" => array("dbcol" => "dsfinvk_ustid","checknum" => 0,"ismasterdata" => 1),
		    
		    "tseurl" => array("dbcol" => "tseurl","checknum" => 0),
		    "tsepass" => array("dbcol" => "tsepass","checknum" => 0),
		    "tsepin" => array("dbcol" => "tsepin","checknum" => 0),
		    "tsepuk" => array("dbcol" => "tsepuk","checknum" => 0),
		    "usetse" => array("dbcol" => "usetse","checknum" => 0),
                    
                    "fiskalyapikey" => array("dbcol" => "fiskalyapikey","checknum" => 0),
		    "fiskalyapisecret" => array("dbcol" => "fiskalyapisecret","checknum" => 0),
                    
                    "fonparticipantid" => array("dbcol" => "fonparticipantid","checknum" => 0),
		    "fonuserid" => array("dbcol" => "fonuserid","checknum" => 0),
                    "fonuserpin" => array("dbcol" => "fonuserpin","checknum" => 0),
                    "rksvscuid" => array("dbcol" => "rksvscuid","checknum" => 0),
                    "rksvcashregid" => array("dbcol" => "rksvcashregid","checknum" => 0),
                    "rksvleinumber" => array("dbcol" => "rksvleinumber","checknum" => 0),
                    "rksvleiname" => array("dbcol" => "rksvleiname","checknum" => 0),
                    
                    "allowminuscheapest" => array("dbcol" => "allowminuscheapest","checknum" => 0),
		    
		    "coins" => array("dbcol" => "coins","checknum" => 0),
		    "notes" => array("dbcol" => "notes","checknum" => 0),
		    "coinvalname" => array("dbcol" => "coinvalname","checknum" => 0),
		    "notevalname" => array("dbcol" => "notevalname","checknum" => 0),
		    "kitchenextrasize" => array("dbcol" => "kitchenextrasize","checknum" => 1),
		    "kitchenoptionsize" => array("dbcol" => "kitchenoptionsize","checknum" => 1),
                    "mostsoldasfavs" => array("dbcol" => "mostsoldasfavs","checknum" => 1),
                    "allowguestexport" => array("dbcol" => "allowguestexport","checknum" => 0),
                    
                    "publishlocation" => array("dbcol" => "publishlocation","checknum" => 0),
                    "publishperformance" => array("dbcol" => "publishperformance","checknum" => 0),
                    "publishfeatures" => array("dbcol" => "publishfeatures","checknum" => 0),
                    
					"allowexceedamount" => array("dbcol" => "allowexceedamount","checknum" => 0),
                    "showphases" => array("dbcol" => "showphases","checknum" => 0),
                    "bartransitbeforecls" => array("dbcol" => "bartransitbeforecls","checknum" => 0),
                    "handleamount" => array("dbcol" => "handleamount","checknum" => 1),
                    "guestshowsoldprods" => array("dbcol" => "guestshowsoldprods","checknum" => 0),
                    
                    "sroomurl" => array("dbcol" => "sroomurl","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 1),
                    "sroomcode" => array("dbcol" => "sroomcode","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 1),
                    "sroomtitle" => array("dbcol" => "sroomtitle","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 1),
                    "sroomimpressum" => array("dbcol" => "sroomimpressum","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 1),
                    "sroomprivacy" => array("dbcol" => "sroomprivacy","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 1),
                    "sroomcss" => array("dbcol" => "sroomcss","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 1),
                    "sroomabout" => array("dbcol" => "sroomabout","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 1),
                    "sroomnews" => array("dbcol" => "sroomnews","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 1),
                    "sroomfood" => array("dbcol" => "sroomfood","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 1),
                    "sroomdrinks" => array("dbcol" => "sroomdrinks","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 1),
                    "sroomutilization" => array("dbcol" => "sroomutilization","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 1),
                    "sroomprodview" => array("dbcol" => "sroomprodview","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 1),
                    "sroomonlygarticles" => array("dbcol" => "sroomonlygarticles","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 1),
                    "sroomshowworkload" => array("dbcol" => "sroomshowworkload","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 1),
                    
                    "perftoinflux" => array("dbcol" => "perftoinflux","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 0),
                    "salestoinflux" => array("dbcol" => "salestoinflux","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 0),
                    "influxurl" => array("dbcol" => "influxurl","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 0),
                    "influxbucket" => array("dbcol" => "influxbucket","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 0),
                    "influxorg" => array("dbcol" => "influxorg","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 0),
                    "influxtoken" => array("dbcol" => "influxtoken","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 0),
                    "influxupdfreq" => array("dbcol" => "influxupdfreq","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 0),
                    "influxperflabel" => array("dbcol" => "influxperflabel","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 0),
                    "influxsaleslabel" => array("dbcol" => "influxsaleslabel","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 0),
                    "influxtablelabel" => array("dbcol" => "influxtablelabel","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 0),
                    "influxsoldlabel" => array("dbcol" => "influxsoldlabel","checknum" => 0,"ismasterdata" => 0,"isforshowroom" => 0),
                    
                    "commentinpaydesk" => array("dbcol" => "commentinpaydesk","checknum" => 0),
                    "showreceiptinpaydesk" => array("dbcol" => "showreceiptinpaydesk","checknum" => 0),
                    "pickupsnoauth" => array("dbcol" => "pickupsnoauth","checknum" => 0),
                    "showonlymyjobscheck" => array("dbcol" => "showonlymyjobscheck","checknum" => 0),
                    
                    "ebonurl" => array("dbcol" => "ebonurl","checknum" => 0,"ismasterdata" => 0),
                    "eboncode" => array("dbcol" => "eboncode","checknum" => 0,"ismasterdata" => 0),

					"priceassignedmobile" => array("dbcol" => "priceassignedmobile","checknum" => 0),
					"priceassigneddesktop" => array("dbcol" => "priceassigneddesktop","checknum" => 0),
					"defaultphase" => array("dbcol" => "defaultphase", "checknum" => 0),
					"invertarticlename" => array("dbcol" => "invertarticlename","checknum" => 0)
		);
                
		$isMasterData = self::isMasterDataInSetOfChangedValues($changedValues, $valueSpecifications);
		if ($isMasterData) {
			$canMasterDataBeChanged = CommonUtils::canMasterDataBeChanged($pdo);
			if (!$canMasterDataBeChanged) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_MASTERDATA, "msg" => "Stammdatenänderungen erfordern einen vorherigen Tagesabschluss"));
				return;
			}
		}
		
		$problem = false;
		foreach ($changedValues as $aChangeSet) {
			$name = $aChangeSet['name'];
			$aVal = $aChangeSet['value'];
			
			if ($name == "payprinttype") {
				if (((string)$aVal) == "1") {
					$aVal = "l";
				}
				if (((string)$aVal) == "2") {
					$aVal = "s";
				}
			}
			
			if ($name == 'addreceipttoprinter') {
			    if ((((string)$aVal) == "0") || (!is_numeric($aVal))) {
				$aVal = null;
			    }
			}
			
			if ($name == "remoteaccesscode") {
				if (((string)$aVal) == "") {
					$aVal = null;
				} else {
					$aVal = md5($aVal);
				}
			}
			if ($name == "printpass") {
				$aVal = md5($aVal);
			}
			
			if ($name == "startprodsearch") {
				if (is_numeric($aVal)) {
					$aVal = round($aVal);
				}
			}
			if ($name == "memorylimit") {
				$aVal = trim($aVal);
				if ($aVal != "-1") {
					if (!ctype_digit($aVal)) {
						$aVal = "256";
					}
					$aVal = intval($aVal);
					if (($aVal < 64) || ($aVal > 65535)) {
						$aVal = 256;
					}
				}
			}
			
			
			if ($name == "pollbills") {
				$aVal = self::returnInRange($aVal, 1, 30, 2);
			}
                        if ($name == "mostsoldasfavs") {
				$aVal = self::returnInRange($aVal, 0, 30, 0);
			}
			
			if ($name == "showpickupsno") {
				$aVal = self::returnInRange($aVal, 1, 200, 20);
			}
			if ($name == "minbeforecome") {
				$aVal = trim($aVal);
				if ($aVal != "-1") {
					if (!ctype_digit($aVal)) {
						$aVal = "0";
					}
					$aVal = intval($aVal);
					if ($aVal < 0) {
						$aVal = 0;
					}
				}
			}
			if ($name == "minaftergo") {
				$aVal = trim($aVal);
				if ($aVal != "-1") {
					if (!ctype_digit($aVal)) {
						$aVal = "0";
					}
					$aVal = intval($aVal);
					if ($aVal < 0) {
						$aVal = 0;
					}
				}
			}
			
			if ($name == "guesttimeout") {
				if (is_numeric($aVal)) {
					$aVal = round($aVal);
					if ($aVal < 0) {
						$aVal = 0;
					}
				} else {
					$aVal = 5;
				}
			}
			
			if (($name == "updateurl") || ($name == "tmpdir") || ($name == "ftphost") || ($name == "ftpuser") || ($name == "sumupfailuretext")) {
				$aVal = trim($aVal);
			}
			
			if ($name == "hs3refresh") {
				$aVal = trim($aVal);
				if (!ctype_digit($aVal)) {
					$aVal = "60";
				}
				$aVal = intval($aVal);
				if ($aVal < 5) {
					$aVal = 60;
				}
			}
			
			$association = $valueSpecifications[$name];
			$dbcol = $association["dbcol"];
			$check = $association["checknum"];
			
			if ($check == 1) {
				if (is_numeric($aVal)) {
					$this->changeOneConfigDbItem($pdo,$dbcol,$aVal);
				} else {
					$problem = true;
				}
			} else {
				$this->changeOneConfigDbItem($pdo,$dbcol,$aVal);
			}
		}
                
                if (self::isShowRoomInSetOfChangedValues($changedValues, $valueSpecifications)) {
                        (new SRoomSync())->sync($pdo);
                }
                
		if (!$problem) {
			echo json_encode(array("status" => "OK"));
		} else {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_COMMAND_ERROR, "msg" => ERROR_COMMAND_ERROR_MSG));
		}
	}
	
	function changeOneConfigDbItem($pdo,$theItem,$theValue) {
		$histFiller = new HistFiller();
		$histFiller->updateConfigInHist($pdo, $theItem, $theValue);
	}
	
	public static function isRoleAdmin($pdo,$roleid) {
		$sql = "SELECT is_admin FROM %roles% WHERE id=?";
		$is_admin_role = CommonUtils::getRowSqlObject($pdo, $sql, array($roleid));
		return ($is_admin_role->is_admin == 1 ? true : false);
	}
	
	public static function isRoleOfUserAdmin($pdo,$userid) {
		$sql = "SELECT roleid FROM %user% WHERE id=?";
		$role = CommonUtils::getRowSqlObject($pdo, $sql, array($userid));
		$roleid = $role->roleid;
		
		return self::isRoleAdmin($pdo, $roleid);
	}
	
        private function userup():array {
                try {
                        $pdo = DbUtils::openDbAndReturnPdoStatic();
                        $theUserId = $_POST['userid'];
                        $sql = "SELECT sorting,active FROM %user% U WHERE id=? AND active='1'";
                        $res = CommonUtils::fetchSqlAll($pdo, $sql, array($theUserId));
                        if (count($res) == 0) {
                                return array("status" => "ERROR","msg" => "Benutzer kann nicht gefunden werden");
                        }
                        $sorting = $res[0]['sorting'];
                        if (is_null($sorting)) {
                                return array("status" => "ERROR","msg" => "Benutzersortierung in Datenbank defekt");
                        }
                        if (is_string($sorting)) {
                                $sorting = intval($sorting);
                        }
                        if ($sorting == 0) {
                                return array("status" => "OK");
                        }
                        $nextSorting = $sorting-1;
                        $sql = "SELECT id,sorting,active FROM %user% U WHERE sorting=? AND active='1'";
                        $res = CommonUtils::fetchSqlAll($pdo, $sql, array($nextSorting));
                        if (count($res) == 0) {
                                return array("status" => "OK");
                        } else {
                                $switchId = $res[0]['id'];
                        }
                        $sql = "UPDATE %user% SET sorting=? WHERE id=?";
                        CommonUtils::execSql($pdo, $sql, array($sorting,$switchId));
                        CommonUtils::execSql($pdo, $sql, array($nextSorting,$theUserId));
                        return array("status" => "OK");
                } catch (Exception $ex) {
                        return array("status" => "ERROR","msg" => "Fehler bei Benutzermanagement: " . $ex->getMessage());
                }
        }
        
        private static function getArticleTagFromPostData() {
                $articletag = null;
                if (isset($_POST['articletag'])) {
                        $articletag = trim($_POST['articletag']);
                }
                if ($articletag == '') {
			$articletag = null;
		}
                return $articletag;
        }
        
	function updateUser() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$theUserId = $_POST['userid'];
		$username = $_POST['username'];
                $fullname = $_POST['fullname'];
                $isowner = $_POST['isowner'];
		$roleid = $_POST['roleid'];
		$area = $_POST['area'];
		if ($area == 0) {
			$area = null;
		}
                $articletag = self::getArticleTagFromPostData();
		
		$is_admin_role = self::isRoleAdmin($pdo, $roleid);
		$isRoleOfUserAdmin = self::isRoleOfUserAdmin($pdo, $theUserId);
		
		if (!$this->isCurrentUserAdmin() && ($is_admin_role ||  $isRoleOfUserAdmin)) {
			echo json_encode("noadmin");
		} else {
			$sql = "UPDATE %user% SET username=?,roleid=?,area=?,articletag=?,fullname=?,isowner=? WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array($username,$roleid,$area,$articletag,$fullname,$isowner,$theUserId));
			HistFiller::updateUserInHist($pdo,$theUserId);
			echo json_encode("OK");
		}
	}
	
	function deleteRole($roleid) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$sql = "SELECT username FROM %user% WHERE roleid=? AND active='1'";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($roleid));
		if (count($result) > 0) {
                        $assignedUsers = array();
                        foreach($result as $r) {
                                $assignedUsers[] = $r['username'];
                        }
			echo json_encode(array("status" => "ERROR","msg" => "Rolle ist noch diesen Benutzern zugewiesen: " . implode(',',$assignedUsers)));
		} else {
			$sql = "DELETE FROM %roles% WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array($roleid));
			echo json_encode(array("status" => "OK"));
		}
	}
	
	function deleteUser($theUserId) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		
                $curSorting = null;
                $sql = "SELECT sorting,active FROM %user% U WHERE id=? AND active='1'";
                $res = CommonUtils::fetchSqlAll($pdo, $sql, array($theUserId));
                if (count($res) > 0) {
                        $curSorting = $res[0]['sorting'];
                        if (is_string($curSorting)) {
                                $curSorting = intval($curSorting);
                        }
                }
                $maxSorting = 0;
                $sql = "SELECT MAX(sorting) as maxsort FROM %user% U WHERE active='1'";
                $res = CommonUtils::fetchSqlAll($pdo, $sql);
                if (count($res) > 0) {
                        $maxSorting = $res[0]['maxsort'];
                        if (is_string($maxSorting)) {
                                $maxSorting = intval($maxSorting);
                        }
                }
                        
		$is_admin_role = self::isRoleOfUserAdmin($pdo, $theUserId);
		if (!$this->isCurrentUserAdmin() && $is_admin_role) {
                        echo json_encode(array("status" => "ERROR","msg" => "Löschvorgang nicht erlaubt. Aktueller Benutzer ist kein Admin."));
		} else {
                        if(session_id() == '') {
                                session_start();
                        }
                        if ($_SESSION["userid"] == $theUserId) {
                                echo json_encode(array("status" => "ERROR","msg" => "Ein Benutzer kann sich nicht selbst löschen."));
                                return;
                                
                        }
                        
			$sql = "UPDATE %user% set active='0' WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array($theUserId));

			HistFiller::updateUserInHist($pdo,$theUserId);

			$sql = "UPDATE %user% set roleid=? WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array(null,$theUserId));
			
                        
                        if (!is_null($curSorting)) {
                                $sql = "UPDATE %user% U SET sorting=? WHERE sorting=? AND active='1'";
                                for ($i = $curSorting; $i <= $maxSorting; $i++) {
                                        CommonUtils::execSql($pdo, $sql, array($i,$i+1));
                                }
                        }
                        
			echo json_encode(array("status" => "OK"));
			
		}		
	}
	
	function getCurrentUser() {
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			echo json_encode("Nobody");
		} else {
			echo json_encode($_SESSION['currentuser']);
		}
	}
	function getCurrentUserPhp() {
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			return "Nobody";
		} else {
			return($_SESSION['currentuser']);
		}
	}
	
	function changepassword($userid,$password) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		
		$sql = "SELECT count(id) as countid FROM %user% WHERE active='1' AND id=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($userid));
		$row = $stmt->fetchObject();
		if ($row->countid == 0) {
		    echo json_encode("ERROR");
		    return;
		}
		
		$userToChgPassIsAdm = self::isRoleOfUserAdmin($pdo, $userid);
		
		$currentUserAdmin = $this->isCurrentUserAdmin();
		if (!$currentUserAdmin && $userToChgPassIsAdm) {
			echo json_encode("noadmin");
		} else {
			if(session_id() == '') {
				session_start();
			}
			$otherUser = false;
			if ($_SESSION['userid'] != $userid) {
				$otherUser = true;
			}

			if ($otherUser && $userToChgPassIsAdm && !($this->isCurrentUserAdmin())) {
				echo json_encode("noadmin");
			} else {
				$password_hash = md5($password);
				$sql = "UPDATE %user% set userpassword=? WHERE active='1' AND id=?";
				CommonUtils::execSql($pdo, $sql, array($password_hash,$userid));
				echo json_encode("OK");
			}
		}
	}
	
	function setUserLanguage($language) {
		if(session_id() == '') {
			session_start();
		}
		$currentuserid = $_SESSION['userid'];
		$_SESSION['language'] = intval($language);

		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$sql = "UPDATE %user% set language=? WHERE active='1' AND id=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($language,$currentuserid));
		echo json_encode("OK");
	}
	
	function setUserReceiptPrinter($printer) {
		self::setUserIntProperty("receiptprinter", $printer, false);
	}
	function setUserQuickcash($value) {
		self::setUserIntProperty("quickcash", $value, true);
	}
	
	private static function setUserIntProperty($item,$value,$doHist) {
		if(session_id() == '') {
			session_start();
		}
		$currentuserid = $_SESSION['userid'];
		$_SESSION[$item] = intval($value);
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$sql = "UPDATE %user% set " . $item . "=? WHERE active='1' AND id=?";
		CommonUtils::execSql($pdo, $sql, array($value,$currentuserid));
		if ($doHist) {
			HistFiller::updateUserInHist($pdo,$currentuserid);
		}
		echo json_encode("OK");
	}
	
	function setBtnSize($btn,$size) {
		if(session_id() == '') {
			session_start();
		}
		$currentuserid = $_SESSION['userid'];
		$assoc = array ("0" => "roombtnsize","1" => "tablebtnsize","2" => "prodbtnsize");
		
		$_SESSION[$assoc[$btn]] = intval($size);
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$sql = "UPDATE %user% set " . $assoc[$btn] . "=? WHERE active='1' AND id=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($size,$currentuserid));
		echo json_encode("OK");
	}
	
	function changeOwnPassword($oldpassword,$newpassword) {
		if(session_id() == '') {
			session_start();
		}
		$currentuser = $_SESSION['currentuser'];
		$oldp_hash = md5($oldpassword);

		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$ok = true;
		
		
		$sql = "SELECT count(id) as countid FROM %user% WHERE username=? AND active='1'";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($currentuser));
		$row = $stmt->fetchObject();
		if ($row->countid == 0) {
		    echo json_encode("FAILED");
		    return;
		}
		
		$sql = "SELECT userpassword FROM %user% WHERE username=? AND active='1'";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($currentuser));
		$row = $stmt->fetchObject();
		
		if ($row->userpassword != $oldp_hash) {
			$ok = false;
		}
		
		if ($ok) {
			$newp_hash = md5($newpassword);
			$sql = "UPDATE %user% set userpassword=? WHERE active='1' AND username=?";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($newp_hash,$currentuser));
			echo json_encode("OK");
		} else {
			echo json_encode("FAILED");
		}
	}
	
	private static function writeCsvHeader($defaultFilename) {
		header("Content-type: text/x-csv");
		header("Content-Disposition: attachment; filename=$defaultFilename");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header("Pragma: no-cache");
		header("Expires: 0");
	}
	
	private function exportConfigCsv() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		self::writeCsvHeader("datenexport-config.csv");
		
		echo("Eintragsid; Datum ; Tagesabschluss; Konfiguration; Wert;Beschreibung\n");
		
		$sql = "SELECT DISTINCT %hist%.id as id,date,COALESCE(clsid,'-') as clsid,";
		$sql .= "%config%.name as configitem,%histconfig%.setting as setting,description ";
		$sql .= " FROM %hist%, %histconfig%, %histactions%, %config% ";
		$sql .= " WHERE (refid=%histconfig%.id) ";
		$sql .= " AND %histconfig%.configid = %config%.id ";
		$sql .= " AND (action='2' OR action='6') ";
		$sql .= " AND (action=%histactions%.id) ";
		$sql .= " ORDER BY date,id";
		
		$result = CommonUtils::fetchSqlAll($pdo, $sql);
		
		foreach($result as $zeile) {
			$val1 = $zeile['id'];
			$val2 = $zeile['date'];
			$val3 = $zeile['clsid'];
			$val4 = $zeile['configitem'];
			$val5 = str_replace("\r\n","<CR>",$zeile['setting']);
			$val5 = str_replace("\n","<CR>",$val5);
			$val6 = $zeile['description'];
		
			echo "$val1; $val2; $val3; \"$val4\"; \"$val5\"; \"$val6\"\n";
		}
	}
	
	private function exportLog() {
	    header("Content-type: text/plain");
	    header("Content-Disposition: attachment; filename=server.log");
	    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
	    header("Pragma: no-cache");
	    header("Expires: 0");
	    $pdo = DbUtils::openDbAndReturnPdoStatic();
	    echo CommonUtils::getLog($pdo);
	}
	
	private function exportUserCsv() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		self::writeCsvHeader("datenexport-benutzer.csv");
		
		$colNamesArr = array("Eintragsid","Datum","Benutzerid","Benutzername");
                $perms = CommonUtils::getPermissions($pdo);
		foreach ($perms as $aPerm) {
			if (isset($aPerm['rolename'])) {
				$colNamesArr[] = $aPerm['rolename'][0];
			}
		}
		$colNamesArr[] = "Tischbereich";
                $colNamesArr[] = "Artikelkennung";
		$colNamesArr[] = "Schnellkasse";
		$colNamesArr[] = "Aktiviert";
		$colNamesArr[] = "Aktion";
		
		echo (implode(";",$colNamesArr) . "\n");
		
		$permsQueryStr = Permissions::createCommaSeperatedStringOfRights();
		$sql = "SELECT DISTINCT %hist%.id as id,date,";
		$sql .= "COALESCE(userid,'') as userid,COALESCE(username,'') as username,";
		$sql .= $permsQueryStr;	
		$sql .= ",active,";
		$sql .= "COALESCE(area,'-') as area,";
                $sql .= "COALESCE(articletag,'') as articletag,";
		$sql .= "COALESCE(quickcash,'-') as quickcash,";
		$sql .= "description ";
		$sql .= " FROM %hist%, %histuser%, %histactions% ";
		$sql .= " WHERE (refid=%histuser%.id) ";
		$sql .= " AND (action='3' OR action='7' OR action='8') ";
		$sql .= " AND (action=%histactions%.id) ";
		$sql .= " ORDER BY date,id";
		
		$result = CommonUtils::fetchSqlAll($pdo, $sql,null);
		
                $perms = CommonUtils::getPermissions($pdo);
		foreach($result as $zeile) {
			$vals = array();
			
			$vals[] = $zeile['id'];
			$vals[] = $zeile['date'];
			$vals[] = $zeile['userid'];
			$vals[] = $zeile['username'];
			foreach($perms as $aPermission) {
				$vals[] = ($zeile[$aPermission['name']] == '1' ? 'Ja' : 'Nein');
			}
			$area = $zeile["area"];
			$character = $area;
			if ($area != '-') {
				$character = chr(intval($area) + 64);
			}

			$vals[] = $character;
                        $vals[] = $zeile["articletag"];
			$vals[] = ($zeile["quickcash"] == '1' ? "Ja" : "Nein");
			$vals[] = ($zeile['active'] == '1' ? "Ja" : "Nein");
			$vals[] = $zeile['description'];
			
			$valsTxt = join(';', $vals);
			echo "$valsTxt\n";
		}
	}
	
	private function dsfinvkexport($format) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$out = Dsfinvk::export($pdo,($format == 'html' ? true : false));
		echo $out;
	}
	
	private function createDirectoryInTemp($tmpFolder) {
		$tmpFolder = trim($tmpFolder);
		if ($tmpFolder == "") {
			$tempfile=tempnam(sys_get_temp_dir(),'');
		} else {
			$tempfile=tempnam($tmpFolder,'');
		}

		if (is_null($tempfile) || ($tempfile== "")) {
			return null;
		}

		if (file_exists($tempfile)) { unlink($tempfile); }
		mkdir($tempfile);
		if (is_dir($tempfile)) {
			$tempfile = str_replace('\\','/',$tempfile);
			return $tempfile;
		} else {
			return null;
		}
	}
	
	
	
	public function ftpbackup($theType,$remoteaccesscode) {
		if (!extension_loaded("ftp")) {
			echo json_encode(array("status" => "ERROR","msg" => "PHP-Extension ftp ist nicht installiert"));
			return;
		}
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$ftphost = CommonUtils::getConfigValue($pdo, 'ftphost', '');
		$ftpuser = CommonUtils::getConfigValue($pdo, 'ftpuser', '');
		$ftppass = CommonUtils::getConfigValue($pdo, 'ftppass', '');
		$pdo = null;
		
		if (($ftphost == '') || ($ftpuser == '') || ($ftppass = '')) {
			echo json_encode(array("status" => "ERROR","msg" => "Ftp-Verbindung wurde nicht konfiguriert"));
		} else {
			$ok = $this->backup($theType, $remoteaccesscode,true);
			echo json_encode($ok);
		}
	}
	
	private static function getKeysOfDataLine($dataline) {
		$html = "<tr>";
		$keys = array_keys($dataline);
		foreach ($keys as $k) {
			$html .= "<th>" . htmlspecialchars($k);
		}
		$html .= "</tr>";
		return $html;
	}
	private static function showDataLineAsHtml($dataline) {
		$html = "<tr>";
		$keys = array_keys($dataline);
		foreach ($keys as $k) {
			$val = $dataline[$k];
			if (!is_null($val)) {
				$html .= "<td>" . htmlspecialchars($dataline[$k]);
			} else {
				$html .= "<td><i>NULL</i>";
			}
		}
		$html .= "</tr>";
		return $html;
	}
	
	private static function exportdebugdata() {
		$pdo = DButils::openDbAndReturnPdoStatic();
		$timeLimitedTables = array(
		    array('config',null,array('printpass','cancelguestcode','cancelunpaidcode','cancelinpaydesk','dailycode','ftppass','guestcode','remoteaccesscode','smtppass','stornocode'),null),
                    array('queue','ordertime',null,null),
		    array('bill','billdate',null,null),
		    array('closing','closingdate',null,null),
		    array('extras',null,null,null),
                    array('prodnames',null,null,null),
		    array('queueextras',null,null,100),
		    array('printjobs',null,null,null),
		    array('log','date',null,null));
		
		$html = self::debugDataStyle();
		foreach($timeLimitedTables as $t) {
			$tablename = $t[0];
			$datecol = $t[1];
			$notins = $t[2];
			$maxLines = $t[3];
			$where = "";
			$limit = "";
			if (!is_null($datecol)) {
				$where = " WHERE DATE(`$datecol`) >= ( CURDATE() - INTERVAL 2 DAY )";
			}
			if (!is_null($maxLines)) {
				$limit = " ORDER BY id DESC LIMIT $maxLines";
			}
			$sql = "SELECT * from `%$tablename%` $where $limit";
                        
			$result = CommonUtils::fetchSqlAll($pdo, $sql);
			
			$html .= "<h2>Tabelle " . htmlspecialchars($tablename) . ":</h2>";
			if (count($result) > 0) {
				$html .= "<table class='viewtable'>";
				$html .= self::getKeysOfDataLine($result[0]);
				foreach($result as $aLine) {
					if (!is_null($notins)) {
						if (in_array($aLine["name"],$notins)) {
							continue;
						}
					}
					$html .= self::showDataLineAsHtml($aLine);
				}
				$html .= "</table><p>";
			}
		}
		echo $html;
	}
	

	private static function checkRemoteAccessCode($pdo,$givenCode) {
		if (is_null($givenCode) || (trim($givenCode) == "")) {
			error_log("Kein Fernzugriffcode wurde angegeben");
			return false;
		}
		$code = CommonUtils::getConfigValue($pdo, 'remoteaccesscode', null);
		if ($code != md5($givenCode)) {
			error_log("Wrong remote access code used");
			return false;
		}
		return true;
	}

	private static function debugDataStyle() {
		$css = "<style>" . file_get_contents(__DIR__ . "/../css/bestformat.css") . "</style>";
		return $css;
	}
	
        public function backup($theType,$remoteaccesscode,$doFtp = false) {
                $pdo = DButils::openDbAndReturnPdoStatic();
		if ($theType == "auto") {
			$code = CommonUtils::getConfigValue($pdo, 'remoteaccesscode', null);
			if (is_null($code)) {
				echo "No remote access code available - backup not allowed";
				return;
			}
			
			if (is_null($code) || (trim($code) == "")) {
				echo "No remote access code set - backup not allowed";
				return;
			}
			if ($code != md5($remoteaccesscode)) {
				echo "Wrong remote access code used - backup not allowed";
				return;
			}
		}
                
                if (ISDEMO) {
                        $fileName = "backup.bak";
                        $retStr = "Backup of Demosystem - absichtlich kein Inhalt...";
                        ob_start();
                        header("Pragma: public");
                        header("Expires: 0");
                        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                        header("Cache-Control: public");
                        header("Content-Description: File Transfer");
                        header("Content-type: application/octet-stream");
                        header("Content-Disposition: attachment; filename=\"$fileName\"");
                        header("Content-Transfer-Encoding: binary");
                        header("Content-Length: ". strlen($retStr));

                        echo $retStr;
                        ob_end_flush();
                        return;
                }
                
                if ($doFtp) {
                        return Backuprestore::backupWithCursor($pdo, $theType,true);    
                }
                
                if ($theType == "debugdata") {
			self::exportdebugdata();
			return;
		}
                return Backuprestore::backupWithCursor($pdo, $theType,false);                
        }
	
	private function doFtp($pdo,$filename,$content,$zipfile) {
		try {
			$ftphost = CommonUtils::getConfigValue($pdo, 'ftphost', '');
			$ftpuser = CommonUtils::getConfigValue($pdo, 'ftpuser', '');
			$ftppass = CommonUtils::getConfigValue($pdo, 'ftppass', '');

			$conn_id = ftp_connect($ftphost);
			$login_result = ftp_login($conn_id, $ftpuser, $ftppass);
			ftp_pasv($conn_id, true);

			if ((!$conn_id) || (!$login_result)) {
				return array("status" => "ERROR","msg" => "Ftp-Verbindung zum Server $ftphost konnte nicht hergestellt werden!");
			}

			if (is_null($zipfile)) {
				$fp = fopen('php://temp', 'r+');
				//fwrite($fp, $content);
				fwrite($fp,$content);
				rewind($fp);       
				$upload = ftp_fput($conn_id, $filename, $fp, FTP_BINARY);
			} else {
				$upload = ftp_put($conn_id, $filename, $zipfile, FTP_BINARY);
			}

			ftp_close($conn_id);

			if (!$upload) {
				return array("status" => "ERROR","msg" => "Ftp-Upload war nicht erfolgreich");
			} else {
				return array("status" => "OK");
			}
		} catch (Exception $ex) {
			return array("status" => "ERROR","msg" => "Ftp-Upload war nicht erfolgreich: " . $ex->getMessage());
		}
	}
	
	private function restore() {
		set_time_limit(60*60);

		if ($_FILES['userfile']['error'] != UPLOAD_ERR_OK               //checks for errors
				&& is_uploaded_file($_FILES['userfile']['tmp_name'])) { //checks that file is uploaded
			echo json_encode(array("status" => "ERROR","msg" => "Kann Datei nicht laden."));
			exit();
		}
		
		if(!file_exists($_FILES['userfile']['tmp_name'])) {
			echo json_encode(array("status" => "ERROR","msg" => "Datei existiert nicht. Bitte PHP-Variablen upload_max_filesize und post_max_size_checken."));
			exit();
		}
		
		if(!is_uploaded_file($_FILES['userfile']['tmp_name'])) {
			echo json_encode(array("status" => "ERROR","msg" => "Datei konnte nicht hochgeladen werden."));
			exit();
		}
		
		$zipExtension = true;
		if (!extension_loaded("zip")) {
			$zipExtension = false;
		}

		$origname = $_FILES['userfile']['name'];
		$pdo = DbUtils::openDbAndReturnPdoStatic();
                if (CommonUtils::strEndsWith($origname, '.' . Backuprestore::$NATIVE_BACKUP_EXTENSION)) {
                        Backuprestore::restore($pdo, $_FILES['userfile']['tmp_name']);
                } else if (CommonUtils::strEndsWith($origname, '.zip')) {
			if ($zipExtension) {
				$zipFile = $_FILES['userfile']['tmp_name'];
				$this->restoreFromZip($pdo,$zipFile);
			} else {
				echo json_encode(array("status" => "ERROR","msg" => "PHP-Zip-Extension ist nicht installiert."));
			exit();
			}
		} else {
			$content = file_get_contents($_FILES['userfile']['tmp_name']);
			$this->restoreFromJson($pdo,$content);
		}
	}
	
	private function getContextOfImportedJsonFile($dbContent) {
		
		$tableKey = 'table';
		$fieldKey = 'fieldname';
		$contentKey = 'content';
		$valueKey = 'value';
		$isolatedDataFormat = false;
		if (count($dbContent) > 0) {
			$sampleTable = $dbContent[0];
			if (isset($sampleTable['t'])) {
				$fieldKey = 'f';
				$contentKey = 'c';
				$tableKey = 't';
				$valueKey = 'v';
			}
		}
		
		foreach($dbContent as $table) {
			if ($table[$tableKey] == 'config') {
					
				$foundConfigItem = null;
				
				
				if (isset($table["w"])) {
					$isolatedDataFormat = true;
					$content = $table["w"]["content"];
					foreach($content as $aTableRow) {
						if ($aTableRow[1] == "version") {
							$bakVersion = base64_decode($aTableRow[2]);
							return array($bakVersion,$tableKey,$fieldKey,$contentKey,$valueKey,$isolatedDataFormat);
						}
					}
				}
				foreach($table[$contentKey] as $aConfigItem) {

					foreach($aConfigItem as $aConfigDbPart) {
						if (($aConfigDbPart[$fieldKey] == 'name') && ($aConfigDbPart[$valueKey] == 'version')) {
							$foundConfigItem = $aConfigItem;
							break;
						}
					}
					
				}
				if (!is_null($foundConfigItem)) {
					foreach($foundConfigItem as $aConfigDbPart) {
						if ($aConfigDbPart[$fieldKey] == 'setting') {
							$bakVersion = base64_decode($aConfigDbPart[$valueKey]);
							return array($bakVersion,$tableKey,$fieldKey,$contentKey,$valueKey,$isolatedDataFormat);
						}
					}
				}
				
			}
		}
		return array("0",$tableKey,$fieldKey,$contentKey,$valueKey,$isolatedDataFormat);
	}
	
	private function restoreDemoFromZip($pdo) {
		// TO BE RELACED BY DEMO
	}
	
	private function restoreFromJson($pdo,$content) {
		$binaryFields = array("signature","img","setting","content");
		
		$basedb = new Basedb();
		$basedb->setPrefix(TAB_PREFIX);
		$basedb->setTimeZone(DbUtils::getTimeZone());

		$dbContent = json_decode($content,true);
		
		$context = $this->getContextOfImportedJsonFile($dbContent);

		$bakVersion = $context[0];
		$tableKey = $context[1];
		$fieldKey = $context[2];
		$contentKey = $context[3];
		$valueKey = $context[4];
		$isolatedDataFormat = $context[5];
		
		if ((CommonUtils::startsWith($bakVersion, "1.0")) || (CommonUtils::startsWith($bakVersion, "1.1")) || (CommonUtils::startsWith($bakVersion, "1.2"))) {
			echo json_encode(array("status" => "ERROR","msg" => "Backup hat eine zu frühe Version zum Import ($bakVersion)."));
			exit();
		}
		
		Version::createTablesAndUpdateUntilVersion($pdo, $basedb, $bakVersion);
		
		$typeIsOnlyConfig = true;
		
		self::doSql($pdo, "SET foreign_key_checks = 0", null);
		
		foreach($dbContent as $table) {
			$tablename = "`%" . $table[$tableKey] . "%`";
			
			$sql = "DELETE FROM $tablename";
			CommonUtils::execSql($pdo, $sql, null);
			
			if ($isolatedDataFormat) {
				$fields = $table["w"]["fields"];
				$colstr = implode(",",$fields);
				$tablecontent = $table["w"]["content"];
			} else {
				$tablecontent = $table[$contentKey];
			}

			if ($table[$tableKey] == "queue") {
				$typeIsOnlyConfig = false;
			}
			
			$chunkSize = CommonUtils::getConfigValue($pdo, 'turbo', 1);
			if ($tablename == '%prodimages%') {
				$chunkSize = 1;
			}
			$chunkNo = 0;
			$indexEnd = MIN(count($tablecontent)-1,$chunkSize);
			$chunkCount = intdiv(count($tablecontent),$chunkSize) + 1;

			if ($isolatedDataFormat) {
				$binColIndices = array();
				foreach($fields as $f) {
					if (in_array($f, $binaryFields)) {
						$binColIndices[] = true;
					} else {
						$binColIndices[] = false;
					}
				}
			}
			if (count($tablecontent)>0) {
				if (!$isolatedDataFormat) {
					$colstr = self::createColsForRestoreInsert($tablecontent[0], $fieldKey);
				}
			
				for ($chunkNo=0;$chunkNo<$chunkCount;$chunkNo++) {
					set_time_limit(60*60);
					$indexStart = $chunkNo * $chunkSize;
					$indexEnd = MIN(count($tablecontent)-1,$indexStart + $chunkSize - 1);
					$vals = array();
					for($i=$indexStart;$i<=$indexEnd;$i++) {
						$row = $tablecontent[$i];
						if (!$isolatedDataFormat) {
							foreach ($row as $field) {
								$fieldname = $field[$fieldKey];
								if (in_array($fieldname, $binaryFields) && (!is_null($field[$valueKey]))  ) {
									$vals[] = base64_decode($field[$valueKey]);
								} else {
									$vals[] = $field[$valueKey];
								}
							}
						} else {
							for ($colIndex = 0;$colIndex<count($binColIndices);$colIndex++) {
								$val = $row[$colIndex];
								if ($binColIndices[$colIndex] && (!is_null($val))) {
									$val = base64_decode($val);
								}
								$vals[] = $val;
							}
						}
					}
					$numberOfSets = $indexEnd - $indexStart + 1;
                                        if ($numberOfSets > 0) {
                                                $queststr = self::createQuestionMarksForSqlInsert(count($tablecontent[0]), $numberOfSets);
                                                $sql = "INSERT INTO $tablename ($colstr) VALUES $queststr";
                                                $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
                                                try {
                                                        $stmt->execute($vals);
                                                } catch (Exception $e) {
                                                            $errorMsg = $e->getMessage();
                                                            error_log($errorMsg);
                                                }
                                        }
				}
			}
		}
		if (!$typeIsOnlyConfig) {
			HistFiller::insertRestoreHistEntry($pdo);
		}

		$basedb->signLastBillid($pdo);
		
		self::doSql($pdo, "SET foreign_key_checks = 1", null);
		
		Version::completeImportProcess($pdo);
	}
	
	private static function createColsForRestoreInsert($dataEntry,$fieldKey) {
		$cols = array();
				
		foreach ($dataEntry as $field) {
			$fieldname = $field[$fieldKey];
			$cols[] = $fieldname;
		}
		$colstr = implode(",",$cols);
		return $colstr;
	}
	
	private static function createQuestionMarksForSqlInsert($numberOfCols,$numberOfSets) {
		$entryQuests = array();
		for ($set=0;$set<$numberOfSets;$set++) {
			$quests = array();
			for($col=0;$col<$numberOfCols;$col++) {
				$quests[] = '?';
			}
			$aSet = '(' . implode(',',$quests) . ')';
			$entryQuests[] = $aSet;
		}
		return implode(',',$entryQuests);
	}
	
	private function restoreFromZip($pdo,$zipFile) {
		$tmpdir = CommonUtils::getConfigValue($pdo, 'tmpdir', '');
		if ($tmpdir == '') {
			echo json_encode(array("status" => "ERROR","msg" => "Zip-Files können nur importiert werden, wenn ein PHP Temp. Directory konfiguriert ist."));
			exit();
		}
		$zip = new ZipArchive;

		$jsonFiles = array();
		if ($zip->open($zipFile) == TRUE) {
			for ($i = 0; $i < $zip->numFiles; $i++) {
				$jsonFiles[] = $zip->getNameIndex($i);
			}
			$zip->extractTo($tmpdir, $jsonFiles);
			$zip->close();
			
			
		} else {
			echo json_encode(array("status" => "ERROR","msg" => "Hochgeladenes Zip-File kann nicht geöffnet werden."));
			exit();
		}
		$binaryFields = array("signature","img","setting","content");
		
		$basedb = new Basedb();
		$basedb->setPrefix(TAB_PREFIX);
		$basedb->setTimeZone(DbUtils::getTimeZone());

		$bakVersion = file_get_contents($tmpdir . "/version");
		
		if ((CommonUtils::startsWith($bakVersion, "1.0")) || (CommonUtils::startsWith($bakVersion, "1.1")) || (CommonUtils::startsWith($bakVersion, "1.2"))) {
			echo json_encode(array("status" => "ERROR","msg" => "Backup hat eine zu frühe Version zum Import ($bakVersion)."));
			exit();
		}
		
		Version::createTablesAndUpdateUntilVersion($pdo, $basedb, $bakVersion);
		
		$typeIsOnlyConfig = true;
		
		self::doSql($pdo, "SET foreign_key_checks = 0", null);
		
		foreach($jsonFiles as $table) {
			if ($table == "version") {
				continue;
			}
			
			$tablename = "`%" . $table . "%`";
			
			$sql = "DELETE FROM $tablename";
			CommonUtils::execSql($pdo, $sql, null);
			
			$tablecontent = json_decode(file_get_contents($tmpdir . "/" . $table),true);
			$isolatedDataFormat = false;
			if (isset($tablecontent["fields"])) {
				$isolatedDataFormat = true;
				$fields = $tablecontent["fields"];
				$colstr = implode(",",$fields);
				$tablecontent = $tablecontent["content"];

				$binColIndices = array();
				foreach($fields as $f) {
					if (in_array($f, $binaryFields)) {
						$binColIndices[] = true;
					} else {
						$binColIndices[] = false;
					}
				}	
			}
			
			if ($table == "queue") {
				$typeIsOnlyConfig = false;
			}
			
			$chunkSize = CommonUtils::getConfigValue($pdo, 'turbo', 1);
			if ($tablename == '%prodimages%') {
				$chunkSize = 1;
			}
			$chunkNo = 0;
			$indexEnd = MIN(count($tablecontent)-1,$chunkSize);
			$chunkCount = intdiv(count($tablecontent),$chunkSize) + 1;
			
			if (count($tablecontent)>0) {
				if (!$isolatedDataFormat) {
					$colstr = self::createColsForRestoreInsert($tablecontent[0], 'f');
				}
				for ($chunkNo=0;$chunkNo<$chunkCount;$chunkNo++) {
					set_time_limit(60*60);
					$indexStart = $chunkNo * $chunkSize;
					$indexEnd = MIN(count($tablecontent)-1,$indexStart + $chunkSize - 1);
					$vals = array();
					for($i=$indexStart;$i<=$indexEnd;$i++) {
						$row = $tablecontent[$i];
						if (!$isolatedDataFormat) {
							foreach ($row as $field) {
								try {
									$fieldname = $field['f'];

									if (in_array($fieldname, $binaryFields) && (!is_null($field['v']))) {
										$vals[] = base64_decode($field['v']);
									} else {
										$vals[] = $field['v'];
									}					
								} catch (Exception $ex) {
									echo $ex->getMessage();
									exit;
								}
							}
						} else {
							for ($colIndex = 0;$colIndex<count($binColIndices);$colIndex++) {
								$val = $row[$colIndex];
								if ($binColIndices[$colIndex] && (!is_null($val))) {
									$val = base64_decode($val);
								}
								$vals[] = $val;
							}
						}
					}
					$numberOfSets = $indexEnd - $indexStart + 1;
					$queststr = self::createQuestionMarksForSqlInsert(count($tablecontent[0]), $numberOfSets);
					$sql = "INSERT INTO $tablename ($colstr) VALUES $queststr";
					$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
					try {
						$stmt->execute($vals);
					} catch (Exception $e) {
                                                error_log("Exception happened: " . $e->getMessage());
					}
				}
			}
		}
		
		foreach($jsonFiles as $table) {
			unlink($tmpdir . "/" . $table);
		}
		
		if (!$typeIsOnlyConfig) {
			HistFiller::insertRestoreHistEntry($pdo);
		}

		self::doSql($pdo, "SET foreign_key_checks = 1", null);
		
		$basedb->signLastBillid($pdo);
		
		Version::completeImportProcess($pdo);
	}
	
	private function shutdown() {
		try {
			if (substr(php_uname(), 0, 7) == "Windows"){
				$cmd = "shutdown /s /t 10";
				pclose(popen("start /B ". $cmd, "r"));
			}
			else {
				chmod("shutdown.bat", "700");
				$cmd = "sh < shutdown.bat";
				exec($cmd . " > /dev/null &");
			}
			echo json_encode(array("status" => "OK"));
		} catch(Exception $e) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_SCRIPT_NOT_EXECUTABLE, "msg" => ERROR_SCRIPT_NOT_EXECUTABLE_MSG, "errormsg" => $e->getMessage()));
		}
		
	}
	
	public static function optimizeCore($pdo) {
		set_time_limit(60 * 20);
		try {
			self::doSql($pdo, "OPTIMIZE TABLE %queue%", null);
                        self::doSql($pdo, "OPTIMIZE TABLE %prodnames%", null);
			self::doSql($pdo, "OPTIMIZE TABLE %billproducts%", null);
			self::doSql($pdo, "OPTIMIZE TABLE %products%", null);
			self::doSql($pdo, "OPTIMIZE TABLE %prodimages%", null);
			self::doSql($pdo, "OPTIMIZE TABLE %extrasprods%", null);
			self::doSql($pdo, "OPTIMIZE TABLE %queueextras%", null);
			self::doSql($pdo, "OPTIMIZE TABLE %log%", null);
			self::doSql($pdo, "OPTIMIZE TABLE %roles%", null);
			return array("status" => "OK");
		} catch (Exception $ex) {
			return array("status" => "ERROR", "code" => ERROR_COMMAND_ERROR, "msg" => ERROR_COMMAND_ERROR_MSG, "errormsg" => $ex->getMessage());
		}
	}
	
	private function optimize() {
		$pdo = DButils::openDbAndReturnPdoStatic();
		$ok = self::optimizeCore($pdo);
		echo json_encode($ok);
	}

	private static function getForeignKeyName($pdo,$fromtable,$totable,$dbname,$default = null) {
	    $foreignKey = null;
	    try {
		$sql = "SELECT constraint_name as foreignkey FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE constraint_schema = '$dbname' AND table_name = '%$fromtable%' AND REFERENCED_TABLE_NAME='%$totable%'";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		$result = $stmt->fetchAll();
		if (count($result) != 1) {
		    return $default;
		}
		$foreignKey = $result[0]["foreignkey"];
	    } catch (Exception $e) {
		    error_log("Exception in getForeignKeyName method: " . $e->getMessage());
		return $default;
	    }
	    
	    return $foreignKey;
	}
	
	private function golive() {
		set_time_limit(60*10);
		$pdo = DButils::openDbAndReturnPdoStatic();
		try {
			$billprodref_fk = self::getForeignKeyName($pdo, 'billproducts', 'bill', MYSQL_DB);
			$queuebillref_fk = self::getForeignKeyName($pdo, 'queue', 'bill', MYSQL_DB);
			$queueclosingref_fk = self::getForeignKeyName($pdo, 'queue', 'closing', MYSQL_DB);
			$billclosingref_fk = self::getForeignKeyName($pdo, 'bill', 'closing', MYSQL_DB);
			$billbillref_fk = self::getForeignKeyName($pdo, 'bill', 'bill', MYSQL_DB);
			$cuslogbillref_fk = self::getForeignKeyName($pdo, 'customerlog', 'bill', MYSQL_DB);
			$cuslogclosingref_fk = self::getForeignKeyName($pdo, 'customerlog', 'closing', MYSQL_DB);
			$histclosingref_fk = self::getForeignKeyName($pdo, 'hist', 'closing', MYSQL_DB);
			$countingclosingref_fk = self::getForeignKeyName($pdo, 'counting', 'closing', MYSQL_DB);
		} catch (Exception $ex) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_COMMAND_ERROR, "msg" => ERROR_COMMAND_ERROR_MSG . " - impossible to get foreign keys: " . $ex->getMessage()));
		}
		
	    try {
                self::doSql($pdo, "DELETE FROM %performance%", null);
                self::doSql($pdo, "DELETE FROM %usedfeatures%", null);
                
                self::doSql($pdo, "DELETE FROM %liveorders%", null);
                self::doSql($pdo, "DELETE FROM %work% WHERE item='ebon'", null);
                    
		self::doSql($pdo, "DELETE FROM %taskhist%", null);
		self::doSql($pdo, "DELETE FROM %tasks%", null);
		
		self::doSql($pdo, "DELETE FROM %customerlog%", null);
		
		self::doSql($pdo, "DELETE FROM %times%", null);
		
		self::doSql($pdo, "DELETE FROM %recordsqueue%", null);
		self::doSql($pdo, "DELETE FROM %records%", null);
	
		HistFiller::readUserTableAndSendToHist($pdo);
		
		$products = new Products();
		$menu = $products->getSpeisekarte($pdo);
		if ($menu['status'] != "OK") {
		    echo json_encode(array("status" => "ERROR", "code" => ERROR_COMMAND_ERROR, "msg" => ERROR_COMMAND_ERROR_MSG));
		    return;
		} else {
		    self::doSql($pdo, "SET foreign_key_checks = 0;", null);
		    self::doSql($pdo, "DELETE FROM %queueextras%", null);
		    self::doSql($pdo, "DELETE FROM %extrasprods%", null);
		    self::doSql($pdo, "DELETE FROM %extras%", null);
		    self::doSql($pdo, "DELETE FROM %billproducts%", null);
		    self::doSql($pdo, "DELETE FROM %queue%", null);
                    self::doSql($pdo, "DELETE FROM %prodnames%", null);
                    self::doSql($pdo, "UPDATE %queue% SET orderid=null", null);
                    self::doSql($pdo, "DELETE FROM %orders%", null);
		    self::doSql($pdo, "DELETE FROM %vouchers%", null);
		    self::doSql($pdo, "DELETE FROM %printjobs%", null);
		    self::doSql($pdo, "DELETE FROM %bill%", null);
		    self::doSql($pdo, "DELETE FROM %operations%", null);
		    self::doSql($pdo, "DELETE FROM %terminals%", null);
		    self::doSql($pdo, "DELETE FROM %tsevalues%", null);
		    self::doSql($pdo, "DELETE FROM %ratings%", null);
		    self::doSql($pdo, "DELETE FROM %counting%", null);
		    self::doSql($pdo, "DELETE FROM %closing%", null);
		    
		    self::doSql($pdo, "UPDATE %hist% set clsid=null", null);
			self::doSql($pdo, "DELETE FROM %hist%", null);
			self::doSql($pdo, "DELETE FROM %histprod%", null);
			self::doSql($pdo, "DELETE FROM %histconfig%", null);
			self::doSql($pdo, "DELETE FROM %histuser%", null);
		
		    self::doSql($pdo, "SET foreign_key_checks = 1;", null);
		    
		    $ret = $this->fillSpeisekarteCore($pdo, $menu['msg']);
		    
		    self::doSql($pdo, "DELETE FROM %products% WHERE removed is not null", null);
		    self::doSql($pdo, "SET foreign_key_checks = 0;", null);
		    self::doSql($pdo, "DELETE FROM %prodtype% WHERE removed is not null", null);
		    self::doSql($pdo, "SET foreign_key_checks = 1;", null);
		    
		    if ($ret["status"] != "OK") {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_COMMAND_ERROR, "msg" => ERROR_COMMAND_ERROR_MSG));
			return;
		    }
		    HistFiller::readAllProdsAndFillHistByDb($pdo);
		    
		    self::doSql($pdo, "DELETE w FROM %histprod% w INNER JOIN %hist% e ON refid=w.id WHERE action='4'", null);
		    self::doSql($pdo, "DELETE FROM %hist% where action='4'", null);
		}
		
		self::doSql($pdo, "ALTER table %bill% drop foreign key $billbillref_fk", null);
		self::doSql($pdo, "ALTER table %customerlog% drop foreign key $cuslogbillref_fk", null);
		self::doSql($pdo, "ALTER TABLE %customerlog% DROP foreign key $cuslogclosingref_fk", null);
		self::doSql($pdo, "ALTER table %billproducts% drop foreign key $billprodref_fk", null);
		self::doSql($pdo, "ALTER table %queue% drop foreign key $queuebillref_fk", null);
		self::doSql($pdo, "ALTER table %queue% drop foreign key $queueclosingref_fk", null);
		self::doSql($pdo, "ALTER TABLE %bill% drop foreign key $billclosingref_fk", null);
		self::doSql($pdo, "ALTER TABLE %bill% DROP id", null);
		self::doSql($pdo, "ALTER TABLE %bill% ADD id INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST", null);
		self::doSql($pdo, "ALTER TABLE %bill% ADD CONSTRAINT $billbillref_fk FOREIGN KEY (ref) REFERENCES %bill%(id)", null);
		self::doSql($pdo, "ALTER TABLE %hist% DROP foreign key $histclosingref_fk", null);
		self::doSql($pdo, "ALTER TABLE %counting% DROP foreign key $countingclosingref_fk", null);
		self::doSql($pdo, "ALTER TABLE %closing% DROP id", null);
		self::doSql($pdo, "ALTER TABLE %closing% ADD id INT (10) NOT NULL AUTO_INCREMENT PRIMARY KEY FIRST", null);
		self::doSql($pdo, "ALTER TABLE %hist% ADD CONSTRAINT $histclosingref_fk FOREIGN KEY (clsid) REFERENCES %closing%(id)", null);
		self::doSql($pdo, "ALTER TABLE %counting% ADD CONSTRAINT $countingclosingref_fk FOREIGN KEY (clsid) REFERENCES %closing%(id)", null);
		self::doSql($pdo, "ALTER TABLE %customerlog% ADD CONSTRAINT $cuslogbillref_fk FOREIGN KEY (billid) REFERENCES %bill%(id)", null);
		self::doSql($pdo, "ALTER TABLE %customerlog% ADD CONSTRAINT $cuslogclosingref_fk FOREIGN KEY (clsid) REFERENCES %closing%(id)", null);
		self::doSql($pdo, "ALTER TABLE %billproducts% ADD CONSTRAINT $billprodref_fk FOREIGN KEY (billid) REFERENCES %bill%(id)", null);
		self::doSql($pdo, "ALTER TABLE %queue% ADD CONSTRAINT $queuebillref_fk FOREIGN KEY (billid) REFERENCES %bill%(id)", null);
		self::doSql($pdo, "ALTER TABLE %bill% ADD CONSTRAINT $billclosingref_fk FOREIGN KEY (closingid) REFERENCES %closing%(id)", null);
		self::doSql($pdo, "ALTER TABLE %queue% ADD CONSTRAINT $queueclosingref_fk FOREIGN KEY (clsid) REFERENCES %closing%(id)", null);
		
		
		$basedb = new Basedb();
		$basedb->setPrefix(TAB_PREFIX);
		$basedb->setTimeZone(DbUtils::getTimeZone());
		$basedb->signLastBillid($pdo);
		
		$histFiller = new HistFiller();
		$histFiller->readConfigTableAndSendToHist();
		
		self::doSql($pdo, "DELETE FROM %resttables% WHERE removed is not null", null);
		self::doSql($pdo, "DELETE FROM %room% WHERE removed is not null", null);
		
		self::doSql($pdo, "DELETE FROM %reservations%", null);
		self::doSql($pdo, "DELETE FROM %groupcustomer%", null);
		self::doSql($pdo, "DELETE FROM %vacations%", null);
		self::doSql($pdo, "DELETE FROM `%groups%`", null);
		self::doSql($pdo, "DELETE FROM %customers%", null);
		
		self::doSql($pdo, "DELETE FROM %work% WHERE item='lastclosing'", null);
		self::doSql($pdo, "UPDATE %work% SET value='0' WHERE item='newfoodtocook'", null);
		self::doSql($pdo, "UPDATE %work% SET value='0' WHERE item='newdrinktocook'", null);
		self::doSql($pdo, "UPDATE %work% SET value='0' WHERE item='indexunclosedqueue'", null);
		Workreceipts::resetWorkReceiptId($pdo);
		
		$basedb->createOrUpdateUID($pdo);
		
		echo json_encode(array("status" => "OK"));
	    } catch(Exception $e) {
		    echo json_encode(array("status" => "ERROR", "code" => ERROR_COMMAND_ERROR, "msg" => ERROR_COMMAND_ERROR_MSG . " - Error message: $e"));
	    }
	}
	
	private static function doSql($pdo,$sql,$params) {
	    $stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
	    if (is_null($params)) {
		$stmt->execute();
	    } else {
		$stmt->execute($params);
	    }
	}
	
	private static function askforcompanyinfo() {
		try {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$companyInfo = CommonUtils::getConfigValue($pdo, 'companyinfo', '');
			$logolocation = CommonUtils::getConfigValue($pdo, 'logolocation', 1);
			echo json_encode(array("status" => "OK","msg" => array("companyinfo" => $companyInfo,"logolocation" => $logolocation)));
		} catch (Exception $ex) {
			echo json_encode(array("status" => "ERROR","msg" => $ex->getMessage()));
		}
	}

	private static function uploaduserphoto() {
		if(session_id() == '') {
			session_start();
		}
		$userid = $_SESSION['userid'];
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		
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
		
		$img = CommonUtils::scaleImg($fn, 500);
                $image = $img["img"];
		$imageBase_64 = base64_encode($image);
		
		try {
			$sql = "UPDATE %user% SET photo=? WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array($imageBase_64,$userid));
		} catch (Exception $ex) {
			echo json_encode(array("status" => "ERROR","msg" => $ex->getMessage()));
			return;
		}
		
		echo json_encode(array("status" => "OK"));
	}
	
	private static function getuserphoto($userid) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$sql = "SELECT photo FROM %user% WHERE id=?";
		$res = CommonUtils::fetchSqlAll($pdo, $sql, array($userid));
		$photo = $res[0]["photo"];
		if (!is_null($photo)) {
			$imagedata = base64_decode($photo);
			header("Content-type: image/png");
			echo $imagedata;
			exit;
		} else {
			$im = imagecreatefrompng("../img/person.png");

			header('Content-Type: image/png');

			imagepng($im);
			imagedestroy($im);
		}
	}
	
	private static function getuserphotoInSession($userid) {
		
		if (is_null($userid)) {
			if(session_id() == '') {
				session_start();
			}
			$userid = $_SESSION['userid'];
		}
		return self::getuserphoto($userid);
	}
	public static function getwaiterphotoforprint($userid) {
		return self::getuserphoto($userid);
	}
	private static function removeuserphoto($userid) {
		
		if (is_null($userid)) {
			if(session_id() == '') {
				session_start();
			}
			$userid = $_SESSION['userid'];
		}
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$sql = "UPDATE %user% SET photo=null WHERE id=?";
		CommonUtils::execSql($pdo, $sql, array($userid));
		echo json_encode(array("status" => "OK"));
	}
	
	private static function getIntArrayOutOfCsvString($txt) {
		$parts = explode(",", $txt);
		$intarr = array();
		foreach($parts as $p) {
			$intarr[] = intval(trim($p));
		}
		return $intarr;
	}
	private static function getcoinsandnotes() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$coins = CommonUtils::getConfigValue($pdo, 'coins', '');
		$notes = CommonUtils::getConfigValue($pdo, 'notes', '');
		$coinsIntArr = self::getIntArrayOutOfCsvString($coins);
		$notesIntArr = self::getIntArrayOutOfCsvString($notes);
		
		$coinvalname = CommonUtils::getConfigValue($pdo, 'coinvalname', '');
		$notevalname = CommonUtils::getConfigValue($pdo, 'notevalname', '');
		echo json_encode(array("status" => "OK","msg" => array("coins" => $coinsIntArr,"notes" => $notesIntArr,"coinvalname" => $coinvalname, "notevalname" => $notevalname)));
	}
        private static function checkemail($smtphost,$smtpauth,$smtpuser,$smtppass,$smtpsecure,$smtpport,$email,$receiveremail) {
                $subject = "Test";
                $msg = "Dies ist eine Testemail von der OrderSprinter-Instanz.\nThis is a test email from the OrderSprinter instance.\nEsta mensaje es de OrderSprinter.\n";
                $htmlMsg = "<h1>Test</h1>"
                        . "<p />Dies ist eine Testemail von der OrderSprinter-Instanz.<br>"
                        . "This is a test email from the OrderSprinter instance.<br>"
                        . "Esta mensaje es de OrderSprinter.<br>";
                $ok = Emailer::sendEmailWithTheseSmtpConfig($smtphost,$smtpauth,null,$smtpuser,$smtppass,$smtpsecure,$smtpport,$email,$receiveremail,$subject,$msg,$htmlMsg);
                if ($ok) {
                        echo json_encode(array("status" => "OK"));
                } else {
                        echo json_encode(array("status" => "ERROR","msg" => "Fehler beim E-Mail-Versand"));
                }
        }
        private static function resetdemo() {
                $pdo = DbUtils::openDbAndReturnPdoStatic();
                $sql = "UPDATE %user% SET lastmodule=?,mobiletheme=? WHERE username like ?";
                CommonUtils::execSql($pdo, $sql, array("waiter.html",8,"%Kellner%"));
                CommonUtils::execSql($pdo, $sql, array("kitchen.html",8,"%Koch%"));
                CommonUtils::execSql($pdo, $sql, array("bar.html",8,"%Bar%"));
                CommonUtils::execSql($pdo, $sql, array("manager.html",0,"%Chef%"));
                CommonUtils::execSql($pdo, $sql, array("pickups.html",0,"%Abhol%"));
                CommonUtils::execSql($pdo, $sql, array("manager.html",3,"%admin%"));
                
                $sql = "UPDATE %user% SET keeptypelevel=?,tablesaftersend=?,calcpref=?,area=?,extrasapplybtnpos=?,quickcash=?";
                CommonUtils::execSql($pdo, $sql, array(1,null,null,null,1,0));
                
                $sql = "UPDATE %user% SET roombtnsize=?,tablebtnsize=?,prodbtnsize=?,prefertablemap=?,preferimgdesk=?,preferimgmobile=?";
                CommonUtils::execSql($pdo, $sql, array(2,1,0,1,1,1));
                
                $sql = "UPDATE %config% SET setting=? WHERE name=?";
                CommonUtils::execSql($pdo, $sql, array(1,"pricelevel"));
                CommonUtils::execSql($pdo, $sql, array(19.0,"tax"));
                CommonUtils::execSql($pdo, $sql, array(7.0,"togotax"));
                CommonUtils::execSql($pdo, $sql, array(123,"stornocode"));
                CommonUtils::execSql($pdo, $sql, array("Mustercafe\nBeispielstraße 123\n12345 Musterort","companyinfo"));
                CommonUtils::execSql($pdo, $sql, array("","serverurl"));
                CommonUtils::execSql($pdo, $sql, array("","email"));
                CommonUtils::execSql($pdo, $sql, array("","receiveremail"));
                CommonUtils::execSql($pdo, $sql, array("s","payprinttype"));
                CommonUtils::execSql($pdo, $sql, array("Euro","currency"));
                CommonUtils::execSql($pdo, $sql, array(12,"receiptfontsize"));
                CommonUtils::execSql($pdo, $sql, array(0,"paymentconfig"));
                CommonUtils::execSql($pdo, $sql, array("Mustercafe\nBeispielstraße 123\n12345 Musterort","webimpressum"));
                CommonUtils::execSql($pdo, $sql, array("","smtphost"));
                CommonUtils::execSql($pdo, $sql, array(1,"smtpauth"));
                CommonUtils::execSql($pdo, $sql, array("","smtpuser"));
                CommonUtils::execSql($pdo, $sql, array("","smtppass"));
                CommonUtils::execSql($pdo, $sql, array(0,"cashenabled"));
                CommonUtils::execSql($pdo, $sql, array(0,"beepcooked"));
                CommonUtils::execSql($pdo, $sql, array(0,"prominentsearch"));
                CommonUtils::execSql($pdo, $sql, array(1,"groupworkitemsf"));
                CommonUtils::execSql($pdo, $sql, array(0,"austria"));
                CommonUtils::execSql($pdo, $sql, array(0,"usebarcode"));
                CommonUtils::execSql($pdo, $sql, array(0,"needcrinbarcode"));
                CommonUtils::execSql($pdo, $sql, array(1,"restaurantmode"));
                
                CommonUtils::execSql($pdo, $sql, array("","discountname1"));
                CommonUtils::execSql($pdo, $sql, array("","discountname2"));
                CommonUtils::execSql($pdo, $sql, array("","discountname3"));
                
                CommonUtils::execSql($pdo, $sql, array(3,"startprodsearch"));
				CommonUtils::execSql($pdo, $sql, array('#',"barcodedelimiter"));
                CommonUtils::execSql($pdo, $sql, array(1,"printextras"));
                CommonUtils::execSql($pdo, $sql, array(1,"printextraprice"));
                CommonUtils::execSql($pdo, $sql, array(0,"defaultview"));
                CommonUtils::execSql($pdo, $sql, array(1,"logolocation"));
                
                CommonUtils::execSql($pdo, $sql, array("Musterrestaurant","dsfinvk_name"));
                CommonUtils::execSql($pdo, $sql, array("ABC-Srasse 123","dsfinvk_street"));
                CommonUtils::execSql($pdo, $sql, array(0,"usetse"));
                CommonUtils::execSql($pdo, $sql, array(0,"allowminuscheapest"));
                CommonUtils::execSql($pdo, $sql, array(0,"workflowconfig"));
                CommonUtils::execSql($pdo, $sql, array(0,"workflowconfigdrinks"));
                CommonUtils::execSql($pdo, $sql, array(1,"allowguestexport"));
                CommonUtils::execSql($pdo, $sql, array(0,"handleamount"));
                
                CommonUtils::execSql($pdo, $sql, array("","sroomurl"));
                CommonUtils::execSql($pdo, $sql, array("","sroomcode"));
                CommonUtils::execSql($pdo, $sql, array("","sroomtitle"));
                CommonUtils::execSql($pdo, $sql, array("","sroomimpressum"));
                CommonUtils::execSql($pdo, $sql, array("","sroomprivacy"));
                CommonUtils::execSql($pdo, $sql, array("","sroomcss"));
                CommonUtils::execSql($pdo, $sql, array("","sroomabout"));
                CommonUtils::execSql($pdo, $sql, array("","sroomnews"));
                CommonUtils::execSql($pdo, $sql, array("","sroomfood"));
                CommonUtils::execSql($pdo, $sql, array("","sroomdrinks"));
                CommonUtils::execSql($pdo, $sql, array("","sroomutilization"));
                
                CommonUtils::execSql($pdo, $sql, array("","influxurl"));
                CommonUtils::execSql($pdo, $sql, array("","influxbucket"));
                CommonUtils::execSql($pdo, $sql, array("","influxorg"));
                CommonUtils::execSql($pdo, $sql, array("","influxtoken"));
                CommonUtils::execSql($pdo, $sql, array("","influxupdfreq"));
                CommonUtils::execSql($pdo, $sql, array("","influxperflabel"));
                CommonUtils::execSql($pdo, $sql, array("","influxsaleslabel"));
                CommonUtils::execSql($pdo, $sql, array("","influxtablelabel"));
                CommonUtils::execSql($pdo, $sql, array("","influxsoldlabel"));
                
                CommonUtils::execSql($pdo, $sql, array(0,"commentinpaydesk"));
                CommonUtils::execSql($pdo, $sql, array(0,"showreceiptinpaydesk"));
                CommonUtils::execSql($pdo, $sql, array(0,"pickupsnoauth"));
                CommonUtils::execSql($pdo, $sql, array(0,"showonlymyjobscheck"));
                
                CommonUtils::execSql($pdo, $sql, array("","ebonurl"));
                CommonUtils::execSql($pdo, $sql, array("","eboncode"));
				CommonUtils::execSql($pdo, $sql, array(0,"defaultphase"));
                
                return array("status" => "OK","msg" => "Zurückgesetzt");
        }

        private static function addperformancedata($pdo,$task,$elapsedtime) {
                if (is_null($pdo)) {
                        $pdo = DbUtils::openDbAndReturnPdoStatic();
                }
                
                if ($task === 'waitersendorder') {
                        Performance::addPerformance($pdo, Performance::$OrderClientMeasurement, true, $elapsedtime);
                } else if ($task === 'waitershowallrooms') {
                        Performance::addPerformance($pdo, Performance::$ShowAllRooms, true, $elapsedtime);
                } else if ($task === 'getentriestocook') {
                        Performance::addPerformance($pdo, Performance::$GetEntriesToCook, true, $elapsedtime);
                } else if ($task === 'getcookedentries') {
                        Performance::addPerformance($pdo, Performance::$GetCookedEntries, true, $elapsedtime);
                }
                return array("status" => "OK");
        }
        
        private static function tableAsCsv(array $cols,array $datarows): string {
                $lines = array();
                $txtArr = array();
                foreach($cols as $aColName) {
                        $txtArr[] = $aColName;
                }
                $lines[] = implode(';',$txtArr);
                
                foreach($datarows as $dataRow) {
                        $txtArr = array();
                        foreach($cols as $aColName) {
                                $txtArr[] = $dataRow[$aColName];
                        }
                        $lines[] = implode(';',$txtArr);
                }
                return implode("\n",$lines);
        }
        
        private static function checkPhpExtensions() {
                $txt = "<div class='phpdiagdata'><table class='reporttable'>";
                $txt .= "<tr><th>Erweiterung<th>Status</tr>";
                $extensions = array("gd","mysqli","openssl","pdo_mysql","PDO","session","zlib","curl","zip","ftp","xml","iconv");
	
		foreach($extensions as $anExtension) {
                        $txt .= "<tr><td>" . $anExtension;
			if (!extension_loaded($anExtension)) {
                                $txt .= "<td>Nicht aktiviert";
			} else {
                                $txt .= "<td>Aktiviert";
                        }
                        $txt .= "</tr>";
		}
                $txt .= "</table></div><br>";
                return $txt;
        }
        
        private static function getphpdiagdata() {
                try {
                        ob_start();
                        phpinfo();
                        $phpinfo = ob_get_contents();
                        ob_end_clean();
                        $phpinfo = preg_replace('%^.*<body>(.*)</body>.*$%ms', '$1', $phpinfo);
                        $retPhpInfo = "<div id='phpinfo'>$phpinfo</div>";
                        $extensionsInfo = '<p>Benötigte PHP-Erweiterungen:<p>' . self::checkPhpExtensions();
                        $comment = "<p><b>Es folgt die Ausgabe von phpinfo(). Die Lizenzinformation am Ende dieser Ausgabe bezieht sich nicht auf OrderSprinter, sondern auf phpinfo!</b><p>";
                        echo json_encode(array("status" => "OK","msg" =>$extensionsInfo .  $comment . $retPhpInfo));
                } catch (Exception $ex) {
                        echo json_encode(array("status" => "ERROR","msg" => "PHP diag data: " . $ex->getMessage()));
                }
        }

        private static function reset() {
                try {
                        $pdo = DbUtils::openDbAndReturnPdoStatic();
                        $sql = "UPDATE %config% SET setting=? WHERE name=?";
                        CommonUtils::execSql($pdo, $sql, array(0,'workflowconfig'));
                        CommonUtils::execSql($pdo, $sql, array(0,'workflowconfigdrinks'));
                        CommonUtils::execSql($pdo, $sql, array(0,'digiprintwork'));
                        CommonUtils::execSql($pdo, $sql, array(1,'delaydigiworkprint'));
                        CommonUtils::execSql($pdo, $sql, array(0,'kitchenshoworderuser'));
						CommonUtils::execSql($pdo, $sql, array(0,'showphases'));
						CommonUtils::execSql($pdo, $sql, array(0,'defaultphase'));

						CommonUtils::execSql($pdo, $sql, array(5,'guesttimeout'));
						CommonUtils::execSql($pdo, $sql, array(0,'guestjobprint'));
						CommonUtils::execSql($pdo, $sql, array('Gastbestellung','guestqrtext'));
						CommonUtils::execSql($pdo, $sql, array(15,'guestqrfontsize'));
						CommonUtils::execSql($pdo, $sql, array(150,'guestqrsize'));

						CommonUtils::execSql($pdo, $sql, array(0,'guesttheme'));
						CommonUtils::execSql($pdo, $sql, array(1,'guestarticleconfirm'));
						CommonUtils::execSql($pdo, $sql, array(1,'allowguestexport'));
						CommonUtils::execSql($pdo, $sql, array(0,'guestshowsoldprods'));
						
						CommonUtils::execSql($pdo, $sql, array(0,'priceassignedmobile'));
						CommonUtils::execSql($pdo, $sql, array(0,'priceassigneddesktop'));

						CommonUtils::execSql($pdo, $sql, array(1,'guestarticlemaxamount'));
						CommonUtils::execSql($pdo, $sql, array(5,'guesttimeout'));
						CommonUtils::execSql($pdo, $sql, array(1,'askdaycode'));
						CommonUtils::execSql($pdo, $sql, array(1,'asktablecode'));

						CommonUtils::execSql($pdo, $sql, array(1,'pricelevel'));

                        CommonUtils::execSql($pdo,"UPDATE %queue% SET toremove='1'",null);
                        CommonUtils::execSql($pdo,"DELETE FROM %printjobs%",null);
						CommonUtils::execSql($pdo,"UPDATE %user% SET active='0' WHERE username LIKE '%Ratinguser%'",null);

						CommonUtils::execSql($pdo,"DELETE FROM %comments%",null);
						
						// now reset the values for the user Charlie Chef
						$userSql = "UPDATE %user% SET 
							language=0,
							mobiletheme=8,
							receiptprinter=NULL,
							roombtnsize=NULL,
							tablebtnsize=NULL,
							prodbtnsize=NULL,
							prefertablemap=1,
							preferimgdesk=1,
							preferimgmobile=1,
							preferfixbtns=NULL,
							showplusminus=1,
							keeptypelevel=1,
							tablesaftersend=NULL,
							extrasapplybtnpos=1,
							calcpref=NULL,
							failedlogins=NULL,
							lastmodule=NULL,
							area=NULL,
							quickcash=0,
							photo=NULL,
							fullname='Bodo Boss',
							isowner=1,
							showonlymyjobs=0,
							articletag=NULL
							WHERE username='Charlie Chef'";
						CommonUtils::execSql($pdo, $userSql, null);
						$_SESSION['quickcash'] = 0;
                        echo json_encode(array("status" => "OK","msg" => "Resetted"));
                } catch (Exception $ex) {
                        error_log("reset() failed: " . $ex->getMessage());
                        echo json_encode(array("status" => "ERROR","msg" => "DB diag data: " . $ex->getMessage()));
                }
        }
        
        private static function getdbdiagdata(string $format): void {
                try {
                        $pdo = DbUtils::openDbAndReturnPdoStatic();

                        $sql = "SELECT DISTINCT REPLACE(table_name,'" . TAB_PREFIX . "','') as table_name,index_name,column_name,index_type FROM INFORMATION_SCHEMA.STATISTICS where index_schema=? AND table_name like '" . TAB_PREFIX . "%'";
                        $tableProps = CommonUtils::fetchSqlAll($pdo, $sql, array(MYSQL_DB));

                        $sql = "SELECT REPLACE(table_name,'" . TAB_PREFIX . "','') as table_name,table_rows,engine,create_time,update_time FROM information_schema.TABLES where table_schema=? AND table_name like '" . TAB_PREFIX . "%'";
                        $tableEngines = CommonUtils::fetchSqlAll($pdo, $sql, array(MYSQL_DB));

                        if ($format == 'json') {
                                $msg = array("tableprops" => $tableProps,"engines" => $tableEngines);
                                echo json_encode(array("status" => "OK","msg" => $msg));
                        } else if ($format == 'csv') {
                                self::writeCsvHeader("tableprops.csv");
                                $props = self::tableAsCsv(array("table_name","index_name","column_name","index_type"), $tableProps);
                                $engines = self::tableAsCsv(array("table_name","table_rows","engine","create_time","update_time"), $tableEngines);
                                echo ($props . "\n\n" . $engines);
                        }
                        
                } catch (Exception $ex) {
                        error_log("DB diag data not readable: " . $ex->getMessage());
                        echo json_encode(array("status" => "ERROR","msg" => "DB diag data: " . $ex->getMessage()));
                }
        }
        
        public static function delallprintjobs() {
                $pdo = DbUtils::openDbAndReturnPdoStatic();
                CommonUtils::execSql($pdo, "DELETE FROM %printjobs%", null);
                return array("status" => "OK");
        }
        
        public static function setconfigitem($pdo,$item,$setting) {
			try {
				$sql = "UPDATE %config% SET setting=? WHERE name=?";
				CommonUtils::execSql($pdo, $sql, array($setting,$item));
				return array("status" => "OK","msg" => "$item wurde auf den Wert $setting gesetzt");
			} catch (Exception $ex) {
				return array("status" => "ERROR","msg" => "Dieses Item konnte über REST nicht gesetzt werden: " . $ex->getMessage());
			}
        }

		private static function setconfigitemrest($item,$setting) {
		}
}