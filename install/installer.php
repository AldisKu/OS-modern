<?php
error_reporting(E_ALL);

define ('IS_INSTALLMODE', '1');

if (is_readable("../php/config1.php")) {
require_once( "../php/config1.php" );
} else {
require_once( "../php/config.php" );
}
require_once ('../php/utilities/basedb.php');
require_once ('../php/utilities/HistFiller.php');
require_once ('../php/utilities/decimaldefs.php');
require_once ('../php/utilities/roles.php');
require_once ('../php/utilities/version.php');
require_once ('../php/admin.php');
require_once ('../php/closing.php');

class ConfigWriter {
function getConfigVals() {
if (!is_readable("../php/config.php") && (!is_readable("../php/config1.php"))) {
echo json_encode(array("status" => "Failed"));
}

defined('MYSQL_PORT') || define ( 'MYSQL_PORT',3306 );

defined('MYSQL_REPLIDBS') || define ( 'MYSQL_REPLIDBS','' );

$retArray = array(
"host" => MYSQL_HOST,
"port" => MYSQL_PORT,
"db" => MYSQL_DB,
"user" => MYSQL_USER,
"password" => MYSQL_PASSWORD,
"tabprefix" => TAB_PREFIX,
"replidbs" => MYSQL_REPLIDBS);
echo json_encode(array("status" => "OK","result" => $retArray));
}
}


class InstallAdmin {
var $pdo;
var $basedb;
var $timezone;

function __construct() {
$this->basedb = new Basedb();
}

function setPrefix($pre) {
$this->basedb->setPrefix($pre);
}

function setPdo($pdo) {
$this->pdo = $pdo;
}

function setTimeZone($zone) {
$this->timezone = $zone;
}

function openDbAndReturnPdo ($host,$port,$db,$user,$password) {
$dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $db;
$pdo = null;
try {
$pdo = new PDO($dsn, $user, $password);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
}
catch (PDOException $e) {
$pdo = null;
}
return $pdo;
}

function checkPhpStatus() {
$extensions = array("gd","mysqli","openssl","pdo_mysql","PDO","session","zlib","curl","zip","ftp","xml","iconv","curl");
$missing = array();

$extensions_status = 1;
foreach($extensions as $anExtension) {
if (!extension_loaded($anExtension)) {
$missing[] = $anExtension;
$extensions_status = 0;
}
}

set_time_limit(60*5+1);
if(session_id() == '') {
ini_set('session.gc_maxlifetime',65535);
session_set_cookie_params(65535);
}

$max_execution_status = 1;
// 5 minutes = 5*60
if (ini_get('max_execution_time') < (5*60)) {
$max_execution_status = 0;
}

$session_lifetime_status = 1;
if (ini_get('session.gc_maxlifetime') < (10*60*60)) {
$session_lifetime_status = 0;
}

$ret = array("extensions_status" => $extensions_status, "missing_extensions" => join(",",$missing),
"max_execution_status" => $max_execution_status, "max_execution_time" => ini_get('max_execution_time'),
"session_lifetime_status" => $session_lifetime_status, "session_gc_maxlifetime" => ini_get('session.gc_maxlifetime')
);

echo json_encode($ret);
}


function setVersion($prefix,$theVersion) {
$pdo = $this->pdo;
try {
$adminCl = new Admin();
DbUtils::overrulePrefix($prefix);

Version::updateVersion($pdo, $theVersion);
return true;
} catch (PDOException $e) {
return false;
}
}

function signLastBillId() {
$pdo = $this->pdo;
$this->basedb->signLastBillid($pdo);
}




public function getCurrentVersion() {
try {
$pdo = $this->pdo;
$sql = "SELECT setting FROM %config% WHERE name=?";
$stmt = $pdo->prepare($this->basedb->resolveTablenamesInSqlString($sql));
$stmt->execute(array("version"));
$row = $stmt->fetchObject();
return($row->setting);
} catch (Exception $e) {
return null;
}
}

public function isTherePreviousVersion($db,$prefix) {
try {
$pdo = $this->pdo;
$sql = "SELECT count(*) as thecount FROM information_schema.tables WHERE table_schema = '$db' AND table_name = '" . $prefix . "config' LIMIT 1";
$stmt = $pdo->prepare($this->basedb->resolveTablenamesInSqlString($sql));
$stmt->execute();
$row = $stmt->fetchObject();
if ($row->thecount == 1) {
return true;
} else {
return false;
}
} catch (Exception $e) {
return false;
}
}

function insertUser($username,$adminpass,$roleid,$lang,$prefertablemap,$fullname,$isowner) {
$md5adminpass = md5($adminpass);
$pdo = $this->pdo;

$sql = "SELECT MAX(sorting) as maxsort FROM %user% U WHERE active='1'";
$res = CommonUtils::fetchSqlAll($pdo, $sql);
$maxSorting = -1;
if (count($res) > 0) {
$maxSorting = $res[0]['maxsort'];
if (is_null($maxSorting)) {
$maxSorting = -1;
} else if (is_string($maxSorting)) {
$maxSorting = intval($maxSorting);
}
}
$nextSorting = $maxSorting + 1;
$userInsertSql = "INSERT INTO `%user%` (`username` , `userpassword`, `roleid`,`language`,`prefertablemap`,`keeptypelevel`,`extrasapplybtnpos`,`showplusminus`,`preferimgdesk`,`preferimgmobile`,`mobiletheme`,`active`,`fullname`,`isowner`,`sorting`) "
. "VALUES (?,?,?,?,?,?,'1','1','1','1','8','1',?,?,?)";
$stmt = $pdo->prepare(DbUtils::substTableAlias($userInsertSql));
$stmt->execute(array($username,$md5adminpass,$roleid,$lang,$prefertablemap,1,$fullname,$isowner,$nextSorting));

$newUserIdForHist = $pdo->lastInsertId();

HistFiller::createUserInHist($pdo, $newUserIdForHist);
}

function testDbConnection($host,$port,$dbname,$user,$pass) {
$pdo = $this->openDbAndReturnPdo($host,$port,$dbname,$user,$pass);
if (is_null($pdo)) {
echo json_encode(array("status" => "ERROR","msg" => "ERROR: DB-Zugriff"));
return;
}
$privileges = DbUtils::checkForInstallUpdateDbRights($pdo);
if ($privileges["status"] != "OK") {
echo json_encode(array("status" => "ERROR","msg" => "ERROR: Rechteabfrage"));
return;
}
$missingRights = "Fehlende Rechte:" . join(',',$privileges["msg"]);
echo json_encode(array("status" => "OK","msg" => $missingRights, "ok" => $privileges["ok"]));
}

function writeConfigFile($host,$port,$db,$user,$password,$prefix,$replidbs) {
$errorlevel = "<?php\nerror_reporting(E_ERROR);\n\n"; // development: E_ALL

$hostlines = "// Zum Aufbau der Verbindung zur Datenbank\n";
$hostlines .= "// die Daten erhalten Sie von Ihrem Provider\n";
$hostlines .= "defined('MYSQL_HOST') || define ( 'MYSQL_HOST','$host' );";
$portlines = "defined('MYSQL_PORT') || define ( 'MYSQL_PORT','$port' );";
$userlines = "defined('MYSQL_USER') || define ( 'MYSQL_USER',  '$user' );";
$dbpasslines = "defined('MYSQL_PASSWORD') || define ( 'MYSQL_PASSWORD',  '$password' );";
$dblines = "defined('MYSQL_DB') || define ( 'MYSQL_DB', '$db' );";
$dbreplislines = "defined('MYSQL_REPLIDBS') || define ( 'MYSQL_REPLIDBS', '$replidbs' );";
$dbloglines = "defined('LOG') || define ( 'LOG', false );";
$prefixlines = "defined('TAB_PREFIX') || define ('TAB_PREFIX', '$prefix');";
$installstatusline = "defined('INSTALLSTATUS') || define ('INSTALLSTATUS', 'installed');";
$isDemo = "defined('ISDEMO') || define ('ISDEMO', false);";
$configText = "$errorlevel\n$hostlines\n$portlines\n$userlines\n$dbpasslines\n$dblines\n$dbloglines\n$prefixlines\n$dbreplislines\n$installstatusline\n$isDemo";
file_put_contents("../php/config.php", $configText);
try {
file_put_contents("../php/config1.php", $configText);
} catch (Exception $e) {
// nothing
}
}


static function insertSampleMenu($pdo,$adminCl,$restmode) {
$prodlistfile = array("speisekarte.txt","angebotfriseur.txt","angebotevents.txt","angebothandel.txt");
$restmodeindex = intval($restmode) - 1;
Basedb::loadSampleProdImages($pdo);
$menu = file_get_contents("../customer/" . $prodlistfile[$restmodeindex]);
$adminCl->fillSpeisekarteCore($pdo, $menu, false);
}

function insertSample($level,$lang,$adminpass,$workflow,$timezone,$restmode) {
$pdo = $this->pdo;
$adminCl = new Admin();
$adminCl::overruleTimeZone($timezone);
$adminCl->changeOneConfigDbItem($pdo,"workflowconfig",$workflow,"%config%",true);
$adminCl->changeOneConfigDbItem($pdo,"workflowconfigdrinks",$workflow,"%config%",true);
if ($level == 1) {
// nothing to do - empty db
} else {			
$roomTxt1 = array("Raum 1 (Tischkarte)","Room 1 (table map)","Espacio 1 (mapa de mesas)");
$roomTxt2 = array("Raum 2 (Tischbuttons)","Room 2 (table buttons)","Espacio (botones des mesas)");
$tableTxt = array("Tisch","Table","Mesa");
$waiterTxt = array("Karl Kellner","Walter Waiter","Carlo Camarero");
$cookTxt = array("Koch 1","Charlie Cook","Cocinero 1");
$bossTxt = array("Charlie Chef","Maggy Manager","Jefe");
if ($restmode != 1) {
$waiterTxt = array("Angestellter 1","Employee 1","Usario 1");
$cookTxt = array("Angestellter 2","Employee 2","Usario 2");
}

$sql = "INSERT INTO `%room%` (`id`, `roomname`) VALUES (?,?)";
$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
$stmt->execute(array(1,$roomTxt1[$lang]));
if ($level == 3) {
$stmt->execute(array(2,$roomTxt2[$lang]));
}

$sql = "INSERT INTO `%resttables%` (`id` , `tableno`, `roomid`) VALUES (? ,?,?)";
$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));

for ($i=1;$i<7;$i++) {
$stmt->execute(array($i,$tableTxt[$lang] . " $i",1));
if ($level == 3) {
$stmt->execute(array($i + 6,$tableTxt[$lang] . " " . ($i + 6),2));
}
}
if ($level == 3) {
$sql = "INSERT INTO `%tablemaps%` (`id` , `roomid`, `img`,`sizex`,`sizey`) VALUES (NULL ,?,?,?,?)";
$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
$room = file_get_contents("../customer/innenraum.png");
$stmt->execute(array(1,$room,739,490));

$sql = "INSERT INTO `%tablepos%` (`id` , `tableid`, `x`,`y`) VALUES (NULL ,?,?,?)";
$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
$stmt->execute(array(1,70,74));
$stmt->execute(array(2,9,57));
$stmt->execute(array(3,19,37));
$stmt->execute(array(4,30,21));
$stmt->execute(array(5,49,21));
$stmt->execute(array(6,76,22));

$guests = array(
array("name" => "Hans Schmidt","address" => "Beispielstrasse 123\n12345 Musterort","phone" => "0123-4567","mobil" => "0170-123456","checkinbefore" => 3),
array("name" => "Donald Duck","address" => "Entenweg 1A\n11111 Entenhausen","phone" => "001-123-4567","mobil" => "001-170-123456","checkinbefore" =>7),
array("name" => "Biene Maja","address" => "Bienenstockstrasse 15\nB-99999 Imkereistadt","phone" => "0800 123456","mobil" => "","checkinbefore" =>1)
);
date_default_timezone_set(DbUtils::getTimeZone());
$currentTime = date('Y-m-d H:i:s');
foreach($guests as $aGuest) {
$sql = "INSERT INTO %customers% (name,address,email,phone,mobil,www,hello,regards,remark,created,lastmodified,permanent) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)";
$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
$stmt->execute(array($aGuest['name'],$aGuest['address'],'',$aGuest['phone'],$aGuest['mobil'],'','','','',$currentTime,$currentTime,0));
$customerid = $pdo->lastInsertId();
$checkin = date('Y-m-d', strtotime('-' . $aGuest['checkinbefore'] . ' days'));
$checkout = date('Y-m-d', strtotime('+14 days'));
$sql = "INSERT INTO %vacations% (customerid,checkin,checkout,room,remark) VALUES(?,?,?,?,?)";
$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
$stmt->execute(array($customerid,$checkin,$checkout,'',''));
}
}

if ($workflow == 2) {
$roleid = Roles::insertWorkWaiterRole($pdo,$restmode);
$this->insertUser($waiterTxt[$lang], $adminpass, $roleid, $lang, 1, 'Karl Kellner Jr', 0);

if ($level == 3) {
$roleid = Roles::insertWorkManagerRole($pdo);
$this->insertUser($bossTxt[$lang], $adminpass, $roleid, $lang, 1, 'Bodo Boss', 1);
}
} else {
$roleid = Roles::insertDigiWaiterRole($pdo,$restmode);
$this->insertUser($waiterTxt[$lang], $adminpass, $roleid, $lang, 1, 'Karl Kellner Jr', 0);

if ($level == 3) {
$roleid = Roles::insertCookRole($pdo,$restmode);
$this->insertUser($cookTxt[$lang], $adminpass, $roleid, $lang, 1, 'Beispielname',  0);

$roleid = Roles::insertDigiManagerRole($pdo);
$this->insertUser($bossTxt[$lang], $adminpass, $roleid, $lang, 1, 'Bodo Boss', 1);
}
}

$this->sortAdminAtEndOfUserList($pdo);

$this->basedb->initTableOrder($pdo);
$this->basedb->initRoomOrder($pdo);


$logoimg = file_get_contents("../customer/logo.png");
$nicelogoimg = file_get_contents("../customer/nicecup.png");
$sql = "INSERT INTO %logo% (id,name,setting) VALUES(?,?,?)";
$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
$stmt->execute(array(1,"logoimg",$logoimg));
$stmt->execute(array(2,"nicelogoimg",$nicelogoimg));
$stmt->execute(array(3,"sroomtitleimg",$nicelogoimg));

self::insertSampleMenu($pdo,$adminCl,$restmode);
}

if ($level == 1) {
$sql = "UPDATE %user% SET preferimgdesk=?,preferimgmobile=?";
$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
$stmt->execute(array(0,0));
}
}

private function getMaxSortingofUser($pdo) {
$sql = "SELECT MAX(sorting) as maxsorting FROM %user% WHERE active='1'";
$stmt = $pdo->prepare($this->basedb->resolveTablenamesInSqlString($sql));
$stmt->execute();
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (count($res) == 0) {
return 0;
} else {
$maxsorting = $res[0]['maxsorting'];
if (is_string($maxsorting)) {
$maxsorting = intval($maxsorting);
}
return $maxsorting;
}
}

private function sortAdminAtEndOfUserList($pdo) {
$maxSorting = $this->getMaxSortingofUser($pdo);
//for ($i=0;$i<=$maxSorting;$i++) {
$sql = "UPDATE %user% SET sorting=sorting-1";
$stmt = $pdo->prepare($this->basedb->resolveTablenamesInSqlString($sql));
$stmt->execute();
//}
$sql = "UPDATE %user% SET sorting=? WHERE sorting=?";
$stmt = $pdo->prepare($this->basedb->resolveTablenamesInSqlString($sql));
$stmt->execute(array($maxSorting,-1));
}
}


function sendInProgressMsg($text,$isAppend) {
if ($isAppend) {
echo "#";
}
echo json_encode(array("status" => "inprogress","msg" => $text));
flush();
ob_flush();
}

$command = $_GET["command"];
if ($command == 'checkWriteAccess') {
$checker = new Checks();
$checker->checkWriteAccess();
} else if ($command == 'checkPhpStatus') {
$checker = new InstallAdmin();
$checker->checkPhpStatus();
} else if ($command == 'testDbConnection') {
$admin = new InstallAdmin();
try {
if (isset($_POST['host']) && isset($_POST['port']) && isset($_POST['dbname']) && isset($_POST['user']) && isset($_POST['pass'])) {
$admin->testDbConnection($_POST['host'],$_POST['port'],$_POST['dbname'],$_POST['user'],$_POST['pass']);
} else {
echo json_encode(array("status" => "ERROR","msg" => "ERROR"));
}
} catch (Exception $e) {
echo json_encode(array("status" => "ERROR","msg" => "ERROR"));
}
} else if ($command == 'getConfig') {
$configWriter = new ConfigWriter();
$configWriter->getConfigVals();
} else if ($command == 'defaultinstall') {
$remoteaccesscode = null;
if (isset($_GET['remoteaccesscode'])) {
$remoteaccesscode = $_GET['remoteaccesscode'];
}

$tabprefix = "os_";
if (isset($_GET['prefix'])) {
$tabprefix = $_GET['prefix'];
}
$db = "ordersprinter";
if (isset($_GET['db'])) {
$db = $_GET['db'];
}
$dbuser = "os";
if (isset($_GET['dbuser'])) {
$dbuser = $_GET['dbuser'];
}
$dbpass = "dbpass";
if (isset($_GET['dbpass'])) {
$dbpass = $_GET['dbpass'];
}

DbUtils::overrulePrefix($tabprefix);
DbUtils::overruleDbName($db);
$admin = new InstallAdmin();
$pdo = $admin->openDbAndReturnPdo("localhost",3306,$db,$dbuser,$dbpass);
$admin->setPdo($pdo);
$admin->setPrefix($tabprefix);
$admin->setTimeZone("Europe/Berlin");

if (isset($_POST['timezone'])) {
DbUtils::overruleTimeZone($_POST['timezone']);
} else {
DbUtils::overruleTimeZone("Europe/Berlin");
}
DbUtils::overrulePrefix($tabprefix);

error_log("Use for defaultinstallation these parameters DB $db, DB-user $dbuser, DB-Prefix: $tabprefix,DB-PASS $dbpass");

set_time_limit(60*10);

$basedb = new Basedb();
$basedb->createAndIntializeTables($pdo,",",0,"Euro","Europe/Berlin",false);

$updResult = Version::runUpdateProcess($pdo, $tabprefix, $db,null,false,Version::$NO_INPROGRESS_EVENTS);
if ($updResult["status"] != "OK") {
echo json_encode("Fehler beim Update: " . $updResult["msg"]);
return;
}

$dsfinvk_name = "Musterrestaurant";
$dsfinvk_street = "Beispielstrasse 123";
$dsfinvk_postalcode = "12345";
$dsfinvk_city = "Beispielstadt";
$dsfinvk_country = "Deutschland";
$dsfinvk_stnr = "123-456";
$dsfinvk_ustid = "123-ABC";
$cat1name = "Speisen";
$cat2name = "Getränke";
$prodlistname = "Speisekarte";
$cat1viewname = "Küche";
$cat2viewname = "Bar";
$deskviewname = "Kellneransicht";
$paydeskid = 1;
$companyinfo = "$dsfinvk_name\n$dsfinvk_street\n$dsfinvk_postalcode $dsfinvk_city\n$dsfinvk_country\nStNR: $dsfinvk_stnr\nUStID:$dsfinvk_ustid";

$restaurantmode = 1;
$cancelcode = "123";
$printpass = md5("123");
$defaultview = 0;
Basedb::changeInitialConfig($pdo,$restaurantmode,
$dsfinvk_name,$dsfinvk_street,$dsfinvk_postalcode,$dsfinvk_city,$dsfinvk_country,$dsfinvk_stnr,$dsfinvk_ustid,
$cat1name,$cat2name,$prodlistname,$cat1viewname,$cat2viewname,$deskviewname,
$paydeskid,$companyinfo,$defaultview,$cancelcode,$printpass,$remoteaccesscode);

$admin->signLastBillId();
$roleid = Roles::insertAdminRole($pdo);
$admin->insertUser("admin", "123", $roleid, 0, 1, 'Admin Guru', 1);
$admin->writeConfigFile("localhost",3306,$db,$dbuser,$dbpass,$tabprefix,'');

if(session_id() == '') {
session_start();
}
session_destroy();
$ok = Admin::optimizeCore($pdo);
if ($ok["status"] == "OK") {
echo json_encode("OK");
} else {
echo json_encode("Fehler beim Update: " . $ok["msg"]);
}
} else if ($command == 'install') {
if(session_id() == '') {
session_start();
}
sendInProgressMsg("Installation gestartet",false);
DbUtils::overrulePrefix($_POST['prefix']);
DbUtils::overruleDbName($_POST['db']);
$admin = new InstallAdmin();
$pdo = $admin->openDbAndReturnPdo($_POST['host'],$_POST['port'],$_POST['db'],$_POST['user'],$_POST['password']);
$admin->setPdo($pdo);
$admin->setPrefix($_POST['prefix']);
$admin->setTimeZone($_POST['timezone']);

DbUtils::overruleTimeZone($_POST['timezone']);
DbUtils::overrulePrefix($_POST['prefix']);

$basedb = new Basedb();
$basedb->createAndIntializeTables($pdo,$_POST['point'],$_POST['lang'],$_POST['currency'],$_POST['timezone'],true);

sendInProgressMsg("Versionsupdates",true);
$updResult = Version::runUpdateProcess($pdo, $_POST['prefix'], $_POST['db'],null,false,Version::$DO_INPROGRESS_EVENTS);
if ($updResult["status"] != "OK") {
echo "#";
echo json_encode(array("status" => "ERROR","msg" => "Fehler beim Update-Schritt: " . $updResult["msg"]));
return;
}


$dsfinvk_name = $_POST["dsfinvk_name"];
$dsfinvk_street = $_POST["dsfinvk_street"];
$dsfinvk_postalcode = $_POST["dsfinvk_postalcode"];
$dsfinvk_city = $_POST["dsfinvk_city"];
$dsfinvk_country = $_POST["dsfinvk_country"];
$dsfinvk_stnr = $_POST["dsfinvk_stnr"];
$dsfinvk_ustid = $_POST["dsfinvk_ustid"];
$cat1name = $_POST["cat1name"];
$cat2name = $_POST["cat2name"];
$prodlistname = $_POST["prodlistname"];
$cat1viewname = $_POST["cat1viewname"];
$cat2viewname = $_POST["cat2viewname"];
$deskviewname = $_POST["deskviewname"];
$paydeskid = $_POST["paydeskid"];

$companyinfo = "$dsfinvk_name\n$dsfinvk_street\n$dsfinvk_postalcode $dsfinvk_city\n$dsfinvk_country\nStNR: $dsfinvk_stnr\nUStID:$dsfinvk_ustid";

$restaurantmode = $_POST["restaurantmode"];
$enabletableselection = 1;
if ($restaurantmode != 1) {
$enabletableselection = 0;
}
$cancelcode = $_POST["cancelcode"];
$printpass = md5($_POST["printpass"]);
$defaultview = $_POST["defaultview"];
$basedb->changeInitialConfig($pdo,
$enabletableselection,$dsfinvk_name,$dsfinvk_street,$dsfinvk_postalcode,$dsfinvk_city,$dsfinvk_country,$dsfinvk_stnr,$dsfinvk_ustid,
$cat1name,$cat2name,$prodlistname,$cat1viewname,$cat2viewname,$deskviewname,
$paydeskid,$companyinfo,$defaultview,$cancelcode,$printpass,null);

$admin->signLastBillId();

$roleid = Roles::insertAdminRole($pdo);
$admin->insertUser("admin", $_POST['adminpass'], $roleid, $_POST['lang'], 1, 'Admin Guru', 1);
$dbreplis = "";
if (isset($_POST['dbreplis'])) {
$dbreplis = $_POST['dbreplis'];
}
$admin->writeConfigFile($_POST['host'], $_POST['port'],$_POST['db'],$_POST['user'],$_POST['password'],$_POST['prefix'],$dbreplis);

session_destroy();
sendInProgressMsg("Optimierungen",true);
$ok = Admin::optimizeCore($pdo);
echo "#";
if ($ok["status"] == "OK") {
echo json_encode(array("status" => "OK"));
} else {
echo json_encode(array("status" => "ERROR","msg" => "Fehler beim Update: " . $ok["msg"]));
}
} else if ($command == 'insertsamplecontent') {
try {
$restmode = 1;
if (isset($_POST["restmode"])) {
$restmode = $_POST["restmode"];
}
$port = 3306;
if (isset($_POST["port"])) {
$port = $_POST["port"];
}
DbUtils::overrulePrefix($_POST['prefix']);
$admin = new InstallAdmin();
$pdo = $admin->openDbAndReturnPdo($_POST['host'],$port,$_POST['db'],$_POST['user'],$_POST['password']);
$admin->setPdo($pdo);
$admin->setPrefix($_POST['prefix']);
$admin->setTimeZone($_POST["timezone"]);

$admin->insertSample(intval($_POST["level"]),intval($_POST["lang"]),$_POST['adminpass'],$_POST["workflow"],$_POST["timezone"],$restmode);
echo json_encode("OK");
}
catch (PDOException $e) {
echo json_encode("ERROR: $e");
}
} else if ($command == 'defaultinsertsamplecontent') {
$tabprefix = "os_";
if (isset($_GET['prefix'])) {
$tabprefix = $_GET['prefix'];
}
$db = "ordersprinter";
if (isset($_GET['db'])) {
$db = $_GET['db'];
}
$dbuser = "os";
if (isset($_GET['dbuser'])) {
$dbuser = $_GET['dbuser'];
}
$dbpass = "dbpass";
if (isset($_GET['dbpass'])) {
$dbpass = $_GET['dbpass'];
}
$port = 3306;
if (isset($_POST["port"])) {
$port = $_POST["port"];
}

try {
DbUtils::overrulePrefix($tabprefix);
$admin = new InstallAdmin();
$pdo = $admin->openDbAndReturnPdo("localhost",$port,$db,$dbuser,$dbpass);
$admin->setPdo($pdo);
$admin->setPrefix($tabprefix);
$admin->setTimeZone("Europe/Berlin");

$admin->insertSample(3,0,"123",0,"Europe/Berlin",1);
echo json_encode("OK");
}
catch (PDOException $e) {
echo json_encode("ERROR: $e");
}
} else if ($command == 'gettimezones') {
$timezone_identifiers = DateTimeZone::listIdentifiers();
$zones = array();
for ($i=0; $i < count($timezone_identifiers); $i++) {
$zones[] = $timezone_identifiers[$i];
}
echo json_encode($zones);
} else if ($command == 'update') {
$configFile = __DIR__ . "/../php/config.php";
if (!is_writable($configFile)) {
echo json_encode("Datei config.php im php-Verzeichnis ist nicht beschreibbar - Update nicht möglich");
return;
}

set_time_limit(60*30);
$installerVersion = "2.9.12";

$port = 3306;
if (isset($_POST["port"])) {
$port = $_POST["port"];
}


$admin = new InstallAdmin();
$pdo = $admin->openDbAndReturnPdo($_POST['host'],$port,$_POST['db'],$_POST['user'],$_POST['password']);
$admin->setPdo($pdo);
$admin->setPrefix($_POST['prefix']);
DbUtils::overrulePrefix($_POST['prefix']);

$isPreviousInstallation = $admin->isTherePreviousVersion($_POST['db'],$_POST['prefix']);
if (!$isPreviousInstallation) {
echo json_encode("Stimmt der Tabellenpräfix?");
return;
}

$version = $admin->getCurrentVersion();
if ($version == $installerVersion) {
error_log("Version already installed: $version");
echo json_encode("Version bereits installiert");
return;
}

if (is_null($version)) {
error_log("Version cannot be determined");
echo json_encode("Version nicht bestimmbar");
return;
}

sendInProgressMsg("Versionsupdate gestartet",false);
$updResult = Version::runUpdateProcess($pdo, $_POST['prefix'], $_POST['db'],null,true,Version::$DO_INPROGRESS_EVENTS);

if(session_id() == '') {
session_start();
}
session_destroy();

$autoupdate = $_POST["autoupdate"];
try {
if ($autoupdate == 1) {
unlink("../install/installer.php");
if (file_exists("../install/phpinfo.php")) {
unlink("../install/phpinfo.php");
}
rmdir("../install");
}
} catch (Exception $e) {
echo json_encode("Install-Verzeichnis lässt sich nicht löschen: ". $e->getMessage());
return;
}

$dbreplis = "";
if (isset($_POST['dbreplis'])) {
$dbreplis = $_POST['dbreplis'];
}
if ($updResult["status"] == "OK") {
$admin->writeConfigFile($_POST['host'],$_POST['port'],$_POST['db'],$_POST['user'],$_POST['password'],$_POST['prefix'],$dbreplis);
sendInProgressMsg("DB optimieren",true);
$ok = Admin::optimizeCore($pdo);
echo "#";
if ($ok["status"] == "OK") {
echo json_encode(array("status" => "OK"));
} else {
echo json_encode(array("status" => "ERROR","msg" => "Fehler beim Update: " . $ok["msg"]));
}

} else {
echo "#";
echo json_encode(array("status" => "ERROR","msg" => "Fehler beim Update: " . $updResult["msg"]));
}
}