<?php
// Datenbank-Verbindungsparameter
require_once ('dbutils.php');
require_once ('commonutils.php');
require_once ('globals.php');
require_once ('admin.php');
require_once ('customers.php');
require_once ('utilities/Emailer.php');

class Closing {
var $dbutils;
var $t;

public static $SALES_TXT = "Verkauf";
public static $TGAG = "TG an AG";

function __construct() {
$this->dbutils = new DbUtils();
require_once 'translations.php';
}

function handleCommand($command) {
$isManagerOrAdmin = $this->hasCurrentUserManagerOrAdminRights();
$mayCreateClosing = $this->hasCurrentUserRightToCreateClosing();

if ($command == 'getcertainclosing') {
if (!$isManagerOrAdmin) {
echo json_encode(array("status" => "ERROR","msg" => "Fehlende Benutzerrechte"));
return; 
}
} else if ($command != 'remotecreateclosing') {
if (!$isManagerOrAdmin && !$mayCreateClosing) {
echo json_encode(array("status" => "ERROR","msg" => "Fehlende Benutzerrechte"));
return;
}
}

if($command == 'createClosing') {
if (isset($_POST['counted'])) {
$counted = $_POST['counted'];
} else {
$counted = null;
}
$bartransit = 0;
if (isset($_POST['dobartransit'])) {
$bartransit = $_POST['dobartransit'];
}
if (!is_null($counted) && ($bartransit == 1)) {
echo json_encode(array("status" => "ERROR","msg" => "Zählprotokoll mit vorheriger Geldtransit nicht erlaubt"));
return;
}
$pdo = DbUtils::openDbAndReturnPdoStatic();
$cashSumNotInClosing = self::getExpectedBarValue($pdo);
$counting = $_POST['counting'];
$coinsCount = $_POST['coinscount'];
$notesCount = $_POST['notescount'];      

$mindeposit = 0;
if (isset($_POST['mindeposit'])) {
$mindeposit = round(floatval($_POST['mindeposit']));
}

if ($bartransit == 1) {
// doCashActionCore($money,$remark, $datetime,$userId,$cashtype,$paymentid) 

$cashForTransit = max(0,($cashSumNotInClosing - $mindeposit));
if ($cashForTransit > 0) {
$pureCash = 0.0 - $cashForTransit;

if(session_id() == '') {
session_start();
}
$userId = $_SESSION['userid'];
date_default_timezone_set(DbUtils::getTimeZone());
$currentTime = date('Y-m-d H:i:s');
$pdo = null;
$retStatusCashOp = Bill::doCashActionCore($pdo,$pureCash, "", $currentTime, $userId, Bill::$CASHTYPE_Geldtransit["value"],DbUtils::$PAYMENT_BAR,ALLOW_TRANSACTIONS,false);
if ($retStatusCashOp['status'] != 'OK') {
echo json_encode($retStatusCashOp);
return;
}
}
} else if (!is_null($counted)) {


$countedFloat = doubleval($counted);
$difference = $countedFloat - $cashSumNotInClosing;
if ($difference > 0.0) {
if(session_id() == '') {
session_start();
}
$userId = $_SESSION['userid'];
date_default_timezone_set(DbUtils::getTimeZone());
$currentTime = date('Y-m-d H:i:s');
$pdo = null;
$retStatusCashOp = Bill::doCashActionCore($pdo,$difference, "", $currentTime, $userId, Bill::$CASHTYPE_DifferenzSollIst["value"],DbUtils::$PAYMENT_BAR,ALLOW_TRANSACTIONS,false);
if ($retStatusCashOp['status'] != 'OK') {
echo json_encode($retStatusCashOp);
return;
}
}
}
$retStat = $this->createClosing($_POST['remark'],$_POST['print'],$counting,$coinsCount,$notesCount,$counted);
echo json_encode($retStat);
return;
} else if ($command == 'remotecreateclosing') {
if (isset($_POST['remoteaccesscode'])) {
if (isset($_POST['remark'])) {
$this->remotecreateclosing($_POST['remoteaccesscode'],$_POST['remark']);
} else {
$this->remotecreateclosing($_POST['remoteaccesscode'],'');
}
} else {
echo json_encode("Remote access code not given");
}
return;
} else if ($command == 'getcertainclosing') {
$pdo = DbUtils::openDbAndReturnPdoStatic();
$ret = $this->getASingleClosing($pdo,$_GET['id'],false);
echo json_encode($ret);
} else if ($command == 'exportCsv') {
$this->exportCsv($_GET['closingid']);
} else if ($command == 'exportGuestCsv') {
$this->exportGuestCsv($_GET['closingid']);
} else if ($command == 'emailCsv') {
$this->emailCsv($_GET['closingid'],$_GET['emailaddress'],$_GET['topic']);
} else if ($command == 'getClosing') {
$this->getClosing($_GET['closingid']);
} else if ($command == 'getClosingSummary') {
$this->getClosingSummary($_GET['closingid'],null,true);
} else if ($command == 'htmlreport') {
$this->htmlreport($_GET["closid"]);
} else if ($command == 'getClosingsListOfMonthYear') {
$sort = 'asc';
if (isset($_GET['sort'])) {
$sort = $_GET['sort'];
}
$this->getClosingsListOfMonthYear($_GET["month"],$_GET["year"],$sort);
} else if ($command == 'getFirstAndLastClosing') {
self::getFirstAndLastClosing();
} else if ($command == 'getCashSumsForNextClosing') {
$pdo = DbUtils::openDbAndReturnPdoStatic();
self::getCashSumsForNextClosing($pdo);
} else if ($command == 'getxbeleg') {
$pdo = DbUtils::openDbAndReturnPdoStatic();
echo json_encode(self::getXBeleg($pdo));
} else {
echo "Command not supported.";
}
}

private function hasCurrentUserManagerOrAdminRights() {
if(session_id() == '') {
session_start();
}
if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
// no user logged in
return false;
} else {
return ($_SESSION['right_manager'] || $_SESSION['is_admin']);
}
}
private function hasCurrentUserRightToCreateClosing() {
if(session_id() == '') {
session_start();
}
if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
// no user logged in
return false;
} else {
return ($_SESSION['right_closing']);
}
}

private function getDecPoint($pdo = null) {
if (is_null($pdo)) {
$pdo = DbUtils::openDbAndReturnPdoStatic();
}
$sql = "SELECT name,setting FROM %config% WHERE name=?";
$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
$stmt->execute(array("decpoint"));
$row = $stmt->fetchObject();
return($row->setting);
}

private function saveLastClosingCreation($pdo) {
date_default_timezone_set(DbUtils::getTimeZone());
$date = new DateTime();
$unixTimeStamp = $date->getTimestamp();
$sql = "SELECT count(id) as countid FROM %work% WHERE item=?";
$row = CommonUtils::getRowSqlObject($pdo, $sql, array('lastclosing'));
if ($row->countid == 0) {
$sql = "INSERT INTO %work% (item,value,signature) VALUES(?,?,?)";
CommonUtils::execSql($pdo, $sql, array('lastclosing', $unixTimeStamp, null));
} else {
$sql = "UPDATE %work% SET value=? WHERE item=?";
CommonUtils::execSql($pdo, $sql, array($unixTimeStamp, 'lastclosing'));
}
}

private function isClosingAllowed($pdo) {
$TIMEOUT = 120;

$sql = "SELECT count(id) as countid FROM %work% WHERE item=?";
$row = CommonUtils::getRowSqlObject($pdo, $sql, array('lastclosing'));
if ($row->countid == 0) {
return true;
} else {
$sql = "SELECT value FROM %work% WHERE item=?";
$row = CommonUtils::getRowSqlObject($pdo, $sql, array('lastclosing'));
$lastaccess = $row->value;

date_default_timezone_set(DbUtils::getTimeZone());
$date = new DateTime();
$currentTimeStamp = $date->getTimestamp();

if (($currentTimeStamp - $lastaccess) > $TIMEOUT) {
return true;
} else {
return false;
}
}
}

private function remotecreateclosing($remoteaccesscode,$remark) {
$pdo = DbUtils::openDbAndReturnPdoStatic();
$code = CommonUtils::getConfigValue($pdo, 'remoteaccesscode', null);

if (is_null($code) || ($code == '')) {
echo json_encode("Remote access code was not configured!");
} else {
if (md5($remoteaccesscode) == $code) {
echo json_encode($this->createClosing($remark,0,0,0,0,0.0));
} else {
echo json_encode("Remote access code not correct!");
}
}
}

private function createClosing ($remark,$doPrint,$counting,$coinscount,$notescount,$counted) {
$pdo = DbUtils::openDbAndReturnPdoStatic();
date_default_timezone_set(DbUtils::getTimeZone());
if (!$this->isClosingAllowed($pdo)) {
if (!CommonUtils::startsWith($remark, "Test")) {
// for auto-tests do not require that time in between
return(array("status" => "ERROR", "msg" => "Time between closings too short", "code" => ERROR_CLOSING_TIME_LIMIT));
}
}
$closingTime = date('Y-m-d H:i:s');
$result = $this->createClosingCore($pdo,$remark,$doPrint,$closingTime,true,$counting,$coinscount,$notescount,$counted);
return $result;
}

private static function insertCounting($pdo,$clsid,$coinscount,$notescount) {
self::insertCountingOfType($pdo, $clsid, $coinscount, 1);
self::insertCountingOfType($pdo, $clsid, $notescount, 0);
}
private static function insertCountingOfType($pdo,$clsid,$counts,$type) {
$sql = "INSERT INTO %counting% (clsid,value,count,iscoin) VALUES(?,?,?,?)";
foreach($counts as $c) {
$value = $c["value"];
$count = $c["count"];
CommonUtils::execSql($pdo, $sql, array($clsid,$value,$count,$type));
}
}

public function createClosingCore($pdo,$remark,$doPrint, $closingTime,$checkForNewVersionAvailable,$counting,$coinscount,$notescount,$counted) {
set_time_limit(60*60);

if (is_null($remark)) {
$remark = "";
}

date_default_timezone_set(DbUtils::getTimeZone());

$perfTimes = array("started" => round(microtime(true) * 1000));

$pdo->beginTransaction();

$this->saveLastClosingCreation($pdo);

if (CommonUtils::callPlugin($pdo, "createClosing", "replace")) {
return array("status" => "OK");
}
CommonUtils::callPlugin($pdo, "createClosing", "before");

$perfTimes["transactionstarted"] = round(microtime(true) * 1000);

CommonUtils::execSql($pdo, 'UPDATE %queue% SET orderid=null', null);
CommonUtils::execSql($pdo, 'DELETE FROM %orders%', null);
CommonUtils::execSql($pdo, 'DELETE FROM %recordsqueue%', null);
CommonUtils::execSql($pdo, 'DELETE FROM %records%', null);
$perfTimes["cleandone"] = round(microtime(true) * 1000);

$sql = "SELECT MAX(id) as maxid FROM %closing%";
$maxIdRes = CommonUtils::fetchSqlAll($pdo, $sql);
$maxId = 0;
if (!is_null($maxIdRes)) {
$prevClsId = $maxIdRes[0]["maxid"];
if (!is_null($prevClsId)) {
$maxId = intval($prevClsId);
}

}
$newClosingId = $prevClsId + 1;
$perfTimes["nextclsid found"] = round(microtime(true) * 1000);

$dsfinvkversion = CommonUtils::getConfigValue($pdo, 'dsfinvkversion', null);
$dsfinvk_name = CommonUtils::getConfigValue($pdo, 'dsfinvk_name', null);
$dsfinvk_street = CommonUtils::getConfigValue($pdo, 'dsfinvk_street', null);
$dsfinvk_postalcode = CommonUtils::getConfigValue($pdo, 'dsfinvk_postalcode', null);
$dsfinvk_city = CommonUtils::getConfigValue($pdo, 'dsfinvk_city', null);
$dsfinvk_country = CommonUtils::getConfigValue($pdo, 'dsfinvk_country', null);
$dsfinvk_stnr = CommonUtils::getConfigValue($pdo, 'dsfinvk_stnr', null);
$dsfinvk_ustid = CommonUtils::getConfigValue($pdo, 'dsfinvk_ustid', null);
$version = CommonUtils::getConfigValue($pdo, 'version', '');
$taxset1 = CommonUtils::getConfigValue($pdo, 'tax', 19.00);
$taxset2 = CommonUtils::getConfigValue($pdo, 'togotax', 7.00);
if (($taxset1 == '') || ($taxset2 == '')) {
// this is obviously austria - so taxes are useless here - make 0 to allow SQL
$taxset1 = 0.0;
$taxset2 = 0.0;
}
$terminalInfo = Terminals::getTerminalInfo();
$terminalEntryId = Terminals::createOrReferenceTerminalDbEntry($pdo, $terminalInfo);
$perfTimes["configread"] = round(microtime(true) * 1000);

$dsfinvkCols = "`dsfinvkversion`,`dsfinvk_name`,`dsfinvk_street`,`dsfinvk_postalcode`,`dsfinvk_city`,`dsfinvk_country`,`dsfinvk_stnr`,`dsfinvk_ustid`,`terminalid`";
$dsfinvkQuests = "?,?,?,?,?,?,?,?,?";
$closingEntrySql = "INSERT INTO `%closing%` (`id`,`closingdate`,`remark`,`billcount`,`billsum`,`signature`,`counting`,`counted`,$dsfinvkCols,`version`,`taxset1`,`taxset2`) VALUES (?,?,?,?,?,?,?,?,$dsfinvkQuests,?,?,?)";
CommonUtils::execSql($pdo, $closingEntrySql, array($newClosingId,$closingTime,$remark,0,0.0,null,$counting,$counted,
$dsfinvkversion,$dsfinvk_name,$dsfinvk_street,$dsfinvk_postalcode,$dsfinvk_city,$dsfinvk_country,$dsfinvk_stnr,$dsfinvk_ustid,$terminalEntryId,$version,$taxset1,$taxset2));

if ($counting == 1) {
self::insertCounting($pdo,$newClosingId,$coinscount,$notescount);
UsedFeatures::noteUsedFeature($pdo, UsedFeatures::$Counting);
}
$perfTimes["clsinitindb"] = round(microtime(true) * 1000);

set_time_limit(60*60);
$sql = "SELECT id FROM %bill% WHERE closingid is null AND (tableid >= '0' OR status='c') ";
$result = CommonUtils::fetchSqlAll($pdo, $sql);
$perfTimes["billidsfound"] = round(microtime(true) * 1000);

$utils = new CommonUtils();

$ok = true;
foreach($result as $row) {
$aBillId = $row['id'];
if (!$utils->verifyBill($pdo, $aBillId)) {
$ok=false;
break;
}
}
if (!$ok) {
return(array("status" => "ERROR", "code" => ERROR_INCONSISTENT_DB, "msg" => ERROR_INCONSISTENT_DB_MSG));
}
$perfTimes["billsverified"] = round(microtime(true) * 1000);

$sql = "SELECT COALESCE(SUM(B.tip),'0.00') as tipbar FROM %bill% B WHERE B.closingid is null AND (B.paymentid=? OR B.paymentid=? OR B.paymentid=? OR B.paymentid=? OR B.paymentid=? OR B.paymentid=?)";
$tipbarresult = CommonUtils::fetchSqlAll($pdo, $sql, array(DbUtils::$PAYMENT_BAR, DbUtils::$PAYMENT_GUEST, DbUtils::$PAYMENT_HOTEL, DbUtils::$PAYMENT_LAST, DbUtils::$PAYMENT_RECHNUNG, DbUtils::$PAYMENT_UEBERW));
$tipbar = $tipbarresult[0]['tipbar'];
$sql = "SELECT COALESCE(SUM(B.tip),'0.00') as tipunbar FROM %bill% B WHERE B.closingid is null AND (B.paymentid=? OR B.paymentid=?)";
$tipunbarresult = CommonUtils::fetchSqlAll($pdo, $sql, array(DbUtils::$PAYMENT_EC,DbUtils::$PAYMENT_KREDIT));
$tipunbar = $tipunbarresult[0]['tipunbar'];
$perfTimes["tipscalculated"] = round(microtime(true) * 1000);

set_time_limit(60*60);
$sql = "UPDATE %bill% SET closingid=? WHERE closingid is null AND (tableid >= '0' OR status='c')";
CommonUtils::execSql($pdo, $sql, array($newClosingId));
$perfTimes["billsclosed"] = round(microtime(true) * 1000);

set_time_limit(60*60);
$sql = "SELECT count(id) as billstotake FROM %bill% WHERE closingid=? AND (tableid >= '0' OR status='c') AND paymentid <> ?";
$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
$stmt->execute(array($newClosingId,DbUtils::$PAYMENT_GUEST));
$row = $stmt->fetchObject();
$billsToTake = $row->billstotake;
$perfTimes["gotbillstotake"] = round(microtime(true) * 1000);

$pricesum = null;
// now calculate the sum of the prices of this closing
if ($billsToTake > 0) {
$sql = "SELECT sum(brutto) as pricesum FROM %bill% WHERE closingid=? AND (tableid >= '0' OR status='c') AND paymentid <> ?";
$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
$stmt->execute(array($newClosingId,DbUtils::$PAYMENT_GUEST));
$row = $stmt->fetchObject();
$pricesum = $row->pricesum;
}

if (is_null($pricesum)) {
$pricesum = 0;
}
$perfTimes["pricesum"] = round(microtime(true) * 1000);

$prevClosingDate = self::getDateOfPreviousClosing($pdo,$newClosingId);
if (is_null($prevClosingDate)) {
$prevClosingDate = "";
}
$perfTimes["prevclsfound"] = round(microtime(true) * 1000);
$pricesumstr = number_format($pricesum, 2, ".", '');
$data = "I($newClosingId)-S($prevClosingDate)-E($closingTime)-D($billsToTake)-S($pricesumstr)";
$signature = md5($data);

set_time_limit(60*60);
$sql = "UPDATE %closing% SET billcount=?, billsum=?,signature=? WHERE id=?";
CommonUtils::execSql($pdo, $sql, array($billsToTake,$pricesum,$signature,$newClosingId));

set_time_limit(60*60);
$sql = "SELECT value as val FROM %work% WHERE item=?";
$indexunclosedqueue = 0;
$r = CommonUtils::fetchSqlAll($pdo, $sql, array('indexunclosedqueue'));
if (count($r) > 0) {
$rval = $r[0]["val"];
if (!is_null($rval)) {
$indexunclosedqueue = intval($rval);
}
}
$perfTimes["workevaled"] = round(microtime(true) * 1000);

set_time_limit(60*60);
$sql = "UPDATE %queue% Q SET Q.clsid=? WHERE Q.id > ? AND Q.clsid is null";
CommonUtils::execSql($pdo, $sql, array($newClosingId,$indexunclosedqueue));
$perfTimes["queueitemclsidclosed"] = round(microtime(true) * 1000);




set_time_limit(60*60);
$sql = "UPDATE %queue% SET toremove='1' WHERE billid is null AND clsid=?";
CommonUtils::execSql($pdo, $sql, array($newClosingId));
$perfTimes["queueitemscleaned"] = round(microtime(true) * 1000);

set_time_limit(60*60);
$sql = "UPDATE %queue% Q LEFT JOIN %bill% B ON Q.billid=B.id SET paidtime=?,delivertime=? WHERE billid is not null AND paidtime is null AND B.paymentid <> ? AND B.paymentid <> ?";
CommonUtils::execSql($pdo, $sql, array($closingTime,$closingTime,DbUtils::$PAYMENT_GUEST,DbUtils::$PAYMENT_HOTEL));
$perfTimes["queueitemsdone"] = round(microtime(true) * 1000);

$sql = "UPDATE %queue% set delivertime=?,workprinted=? WHERE billid is not null AND delivertime IS NULL";
CommonUtils::execSql($pdo, $sql, array($closingTime,1));
$perfTimes["queueitemsdelivered"] = round(microtime(true) * 1000);

$sql = "DELETE FROM %printjobs%";
CommonUtils::execSql($pdo, $sql, null);

$sql = "DELETE FROM %work% WHERE item=?";
CommonUtils::execSql($pdo, $sql, array("sumuphash"));

set_time_limit(60*60);
$sql = "UPDATE %queue% SET isclosed=?";
CommonUtils::execSql($pdo, $sql, array(1));
$perfTimes["queueitemsclosed"] = round(microtime(true) * 1000);

if ($counting == 0) {
$sql = "SELECT counted FROM %closing% WHERE id=?";
$previousCounted = CommonUtils::fetchSqlAll($pdo, $sql, array($prevClsId));

if ((count($previousCounted) == 0) || (!-isset($previousCounted[0]["counted"]))) {
$prevCountedVal = 0;
} else {
$prevCountedVal = doubleVal($previousCounted[0]["counted"]);
}

$sql = "SELECT COALESCE(SUM(B.brutto),'0.00') as sumbrutto FROM %bill% B,%closing% C WHERE B.paymentid='1' AND C.id=? AND C.id=B.closingid";
$result = CommonUtils::fetchSqlAll($pdo, $sql,array($newClosingId));
$salesAndCashOps = doubleVal($result[0]['sumbrutto']);
$perfTimes["calcnewcash"] = round(microtime(true) * 1000);

$counted = $prevCountedVal + $salesAndCashOps;
$sql = "UPDATE %closing% SET counted=? WHERE id=?";
CommonUtils::execSql($pdo, $sql, array($counted,$newClosingId));
}

$clsInfo = self::calcSalesTotalOfClosing($pdo, $newClosingId);
$salesTotal = $clsInfo["salestotal"];
$tipsTotal = $clsInfo["tipstotal"];
$perfTimes["salescalced"] = round(microtime(true) * 1000);

$sql = "SELECT CAST(ROUND(COALESCE(SUM(brutto),'0.00'),2) as DECIMAL(12,2)) as cashsum FROM %closing% C ";
$sql .= " INNER JOIN %bill% B ON B.closingid=C.id ";
$sql .= " WHERE C.id=? and B.paymentid=?";
$cashsumresult = CommonUtils::fetchSqlAll($pdo, $sql, array($newClosingId,1));
$pureCashsum = $cashsumresult[0]['cashsum'];
$perfTimes["cashsumcalced"] = round(microtime(true) * 1000);

$sql = "SELECT CAST(ROUND(COALESCE(SUM(brutto),'0.00'),2) as DECIMAL(12,2)) as barsum FROM %closing% C ";
$sql .= " INNER JOIN %bill% B ON B.closingid=C.id ";
$sql .= " WHERE C.id=? and B.paymentid<> ? and B.paymentid<> ?";
$sumwithoutcashresult = CommonUtils::fetchSqlAll($pdo, $sql, array($newClosingId, DbUtils::$PAYMENT_GUEST, DbUtils::$PAYMENT_HOTEL));
$pureSumWithoutCashSum = $sumwithoutcashresult[0]['barsum'];
$perfTimes["purecashsumcalced"] = round(microtime(true) * 1000);


$sql = "UPDATE %closing% SET cashsum=?,tipbar=?,tipunbar=?,saleswithoutcash=?,salestotal=? WHERE id=?";
CommonUtils::execSql($pdo, $sql, array($pureCashsum,$tipbar,$tipunbar,$pureSumWithoutCashSum,$salesTotal,$newClosingId));
$perfTimes["cashsumssaved"] = round(microtime(true) * 1000);

$dblogging = CommonUtils::getConfigValue($pdo, 'dblog', 1);
if ($dblogging == 0) {
$sql = "DELETE FROM %log%";
CommonUtils::execSql($pdo, $sql, null);
}

workreceipts::resetWorkReceiptId($pdo);

$sql = "UPDATE %customerlog% SET clsid=? WHERE clsid is null";
CommonUtils::execSql($pdo, $sql, array($newClosingId));

$sql = "UPDATE %hist% SET clsid=? WHERE clsid IS NULL";
CommonUtils::execSql($pdo, $sql, array($newClosingId));
$perfTimes["histupdated"] = round(microtime(true) * 1000);

self::signValueByTseAndUpdateClosing($pdo, $newClosingId, "Kassenabschluss $newClosingId - $closingTime");
$perfTimes["tsesigned"] = round(microtime(true) * 1000);

CommonUtils::execSql($pdo, "UPDATE %operations% SET clsid=? WHERE clsid is null", array($newClosingId));

// commit must before email, because there direct access to db happens
$pdo->commit();

$sql = "SELECT count(id) as countid from %work% WHERE item='optimizationneeded'";
$r = CommonUtils::fetchSqlAll($pdo, $sql);
if ($r[0]['countid'] == 0) {
$sql = "INSERT INTO %work% (item,value,signature) VALUES(?,?,?)";
CommonUtils::execSql($pdo, $sql, array('optimizationneeded',20,null));
}
$sql = "SELECT value from %work% WHERE item='optimizationneeded'";
$r = CommonUtils::fetchSqlAll($pdo, $sql);
$freeShots = intval($r[0]['value']);

$sql = "UPDATE %work% SET value=? WHERE item=?";
if ($freeShots == 0) {
CommonUtils::execSql($pdo, "OPTIMIZE TABLE %printjobs%", null);
CommonUtils::execSql($pdo, "OPTIMIZE TABLE %log%", null);
CommonUtils::execSql($pdo, "OPTIMIZE TABLE %orders%", null);
CommonUtils::execSql($pdo, "OPTIMIZE TABLE %usedfeatures%",null);
CommonUtils::execSql($pdo, "OPTIMIZE TABLE %performance%",null);
CommonUtils::execSql($pdo, "OPTIMIZE TABLE %work%",null);
CommonUtils::execSql($pdo, $sql, array(20,'optimizationneeded'));
} else {
CommonUtils::execSql($pdo, $sql, array($freeShots-1,'optimizationneeded'));
}
$perfTimes["tablesoptimized"] = round(microtime(true) * 1000);

$toEmail = $this->getGeneralItemFromDbWithPdo($pdo,"receiveremail");
if (($toEmail != '') && (strpos($toEmail,'@') !== false)) {
$this->emailCsvCore($pdo,$newClosingId, $toEmail, "Tagesabschluss",$prevClosingDate,$closingTime);
}

UsedFeatures::sumUpUsedFeatures($pdo);
Performance::averageOnAllMeasurements($pdo);
$perfTimes["telemetry"] = round(microtime(true) * 1000);

// send telemetry data if use has allowed
$publishlocation = CommonUtils::getConfigValue($pdo, 'publishlocation', 0);
$publishperformance = CommonUtils::getConfigValue($pdo, 'publishperformance', 0);
$publishfeatures = CommonUtils::getConfigValue($pdo, 'publishfeatures', 0);
if (!CommonUtils::startsWith($remark, "Test") && ((($publishlocation == 2) || ($publishperformance == 2) || ($publishfeatures == 2)))) {
$telemetryData = array();
if ($publishlocation == 2) {
$company = array();
$company["companyinfo"] = CommonUtils::getConfigValue($pdo, 'companyinfo', '');
$company["dsfinvkname"] = CommonUtils::getConfigValue($pdo, 'dsfinvk_name', '');
$company["dsfinvkstreet"] = CommonUtils::getConfigValue($pdo, 'dsfinvk_street', '');
$company["dsfinvkpostalcode"] = CommonUtils::getConfigValue($pdo, 'dsfinvk_postalcode', '');
$company["dsfinvkcity"] = CommonUtils::getConfigValue($pdo, 'dsfinvk_city', '');
$company["dsfinvkcountry"] = CommonUtils::getConfigValue($pdo, 'dsfinvk_country', '');
$company["installdate"] = CommonUtils::getConfigValue($pdo, 'installdate', '');
$company["version"] = CommonUtils::getConfigValue($pdo, 'version', '');

$telemetryData["company"] = $company;
}
if ($publishperformance == 2) {
$telemetryData["performance"] = Performance::getPurePerfData($pdo,10);
}
if ($publishfeatures == 2) {
$telemetryData["features"] = UsedFeatures::getPureData($pdo);
}
$dataToTransmit = json_encode($telemetryData);
$transferdataBase64 = base64_encode($dataToTransmit);
$query = http_build_query(array("data" => $transferdataBase64));

$opts = array(
'http' => array(
'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
"Content-Length: " . strlen($query) . "\r\n" .
"User-Agent:MyAgent/1.0\r\n",
'method' => 'POST',
'content' => $query,
'timeout' => 560
)
);

$context = stream_context_create($opts);

if (!(ISDEMO)) {
try {
$retOfTelemetryTransmission = @file_get_contents("https://www.ordersprinter.de/telemetry/telemetry.php", false, $context);
$perfTimes["telemetrysent"] = round(microtime(true) * 1000);
} catch (Exception $ex) {
//
}
}
}

$admin = new Admin();
$versionInfo = $admin->getEnv($pdo);
$content = array("env" => $versionInfo,"result" => $pricesum, "closingid" => $newClosingId);

if ($checkForNewVersionAvailable) {
// check if new version is evailable
// (do not inform user if last install or update is right before new version - let new version mature a bit..)
$url = "http://www.ordersprinter.de/version/checkversion.php?";
$url .= "v=" .$versionInfo["version"] . "&i=" . $versionInfo["installdate"] . "l=" .  $versionInfo["lastupdate"];
$ctx = stream_context_create(array('http'=>
array(
'timeout' => 5, // 5 seconds
)
));

if (!CommonUtils::startsWith($remark, "Test")) {
// for auto-tests do not check for new available versions
$newversionavailable = @file_get_contents($url, false, $ctx);
}
// TODO: has to be forwarded to user to inform him
}
$perfTimes["versionchecked"] = round(microtime(true) * 1000);

CommonUtils::keepOnlyLastLog($pdo);
// call plugin after completion of closing
CommonUtils::callPlugin($pdo, "createClosing", "after");
$perfTimes["closingcreated"] = round(microtime(true) * 1000);

error_log("closing perf: " . self::calcDurations($perfTimes));

return(array("status" => "OK", 
"msg" => $content, 
"print" => $doPrint, 
"counting" => $counting, 
"counted" => $counted, 
"clsid" => $newClosingId, 
"purecash" => $pureCashsum,
"salestotal" => $salesTotal,
"tipstotal" => $tipsTotal,
"pureSumWithoutCashSum" => $pureSumWithoutCashSum));
}

private static function calcDurations(array $perfTimes): string {
$started = $perfTimes['started'];
$perfMeasurement = array();
foreach (array_keys($perfTimes) as $aKey) {
$elapsed = $perfTimes[$aKey] - $started;
$perfMeasurement[] = "$aKey:" . $elapsed;
}
return implode(',',$perfMeasurement);
}

private static function calcSalesTotalOfClosing($pdo,$clsid) {
$salesTotal = 0.00;
$tipsTotal = 0.00;
$usersums = self::getUserGroupedSumOfClosing($pdo,$clsid,false,',');
foreach($usersums as $aUserSum) {
$salesTotal += $aUserSum['salesinbar'] + $aUserSum['salesnotinbar'];
$tipsTotal += $aUserSum['sumtips'];
}
$salesTotal = number_format($salesTotal, 2, ".", '');
$tipsTotal = number_format($tipsTotal, 2, ".", '');
return array("salestotal" => $salesTotal,"tipstotal" => $tipsTotal);
}

public static function recalcSalesOfClosings($pdo) {
$sql = "SELECT id from %closing%";
$res = CommonUtils::fetchSqlAll($pdo, $sql);
$sql = "UPDATE %closing% SET salestotal=? WHERE id=?";
foreach ($res as $r) {
$clsinfo = self::calcSalesTotalOfClosing($pdo, $r["id"]);
CommonUtils::execSql($pdo, $sql, array($clsinfo["salestotal"],$r["id"]));
}
}

private function getSumOfBillsWithClosingId($pdo,$closingid,$onlyBar,$formatAsString) {
$sql = "SELECT count(id) as countid FROM %bill% WHERE closingid=?";
$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
$stmt->execute(array($closingid));
$row = $stmt->fetchObject();
if ($row->countid == 0) {
if ($formatAsString) {
return '0.00';
} else {
return 0.0;
}
}

$sql = "SELECT CAST(ROUND(sum(brutto),2) as DECIMAL(12,2)) as billsum FROM %bill% B WHERE closingid=? AND B.paymentid <> ? AND B.paymentid <> ?";
if ($onlyBar) {
$sql .= " AND paymentid='1'";
}
$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
$stmt->execute(array($closingid,DbUtils::$PAYMENT_GUEST,DbUtils::$PAYMENT_HOTEL));
$row = $stmt->fetchObject();

if ($formatAsString) {
$sum = $row->billsum;
} else {
$sum = floatval($row->billsum);
}

return $sum;
}

private function getSumOfPureSalesBillsWithClosingId($pdo,$closingid) {
$sql = "SELECT COALESCE(sum(brutto),'0.00') as billsum FROM %bill% WHERE closingid=? AND paymentid <> ? AND paymentid <> ? AND ((status is null) OR (status is not null and status<>'c'))";
$result = CommonUtils::fetchSqlAll($pdo, $sql, array($closingid, DbUtils::$PAYMENT_GUEST, DbUtils::$PAYMENT_HOTEL));
if (count($result) > 0) {
return floatval($result[0]['billsum']);
} else {
return 0.00;
}
}
private function getCountOfSalesBillsWithClosingId($pdo,$closingid) {
$sql = "SELECT COUNT(id) as billcount FROM %bill% WHERE closingid=? AND paymentid <> ? AND paymentid <> ? AND (status is null or status<>'c')";
$result = CommonUtils::fetchSqlAll($pdo, $sql, array($closingid, DbUtils::$PAYMENT_GUEST, DbUtils::$PAYMENT_HOTEL));
if (count($result) > 0) {
return $result[0]['billcount'];
} else {
return 0;
}
}

private static function getUserGroupedSumOfClosing($pdo,$closingid,$doReplaceDecpoint,$decpoint) {
return self::getGroupedSumOfClosing($pdo, $closingid, $doReplaceDecpoint, $decpoint, 'userid,username','username,userid');
}
private static function getTotalsGroupedSumOfClosing($pdo,$closingid,$doReplaceDecpoint,$decpoint) {
return self::getGroupedSumOfClosing($pdo, $closingid, $doReplaceDecpoint, $decpoint, null,null);
}
private static function getXBeleg($pdo) {
try {
$userSums = self::getGroupedSumOfXBeleg($pdo,'userid,username','username,userid');
$taxes = self::getTaxesGroupedOfXBeleg($pdo);
$payments = self::getDetailsOfSalesAndTaxesByTaxAndPaymentGroupedOfXBeleg($pdo);
$cancelledBills = self::getCancelledBillsOfXBeleg($pdo);
$ordercancelsdetails = self::getOrderCancelsDetailsOfXBeleg($pdo);
$result = array("usersums" => $userSums,"taxes"=> $taxes, "payments" => $payments,"cancelledbills" => $cancelledBills,"cancelledorders" => $ordercancelsdetails);

return array("status" => "OK","msg" => $result);
} catch (Exception $ex) {
return array("status" => "ERROR","msg" => $ex->getMessage());
}
}

private static function getGroupedSumOfClosing($pdo,$closingid,$doReplaceDecpoint,$decpoint,?string $grouping,?string $ordering) {
if (!$doReplaceDecpoint) {
$decpoint = ".";  
}
$sql = "SELECT userid,username,COALESCE(fullname,username) as fullname,";

$sql .= "CAST(ROUND(sum(if((cashtype is null OR (cashtype<>'" . Bill::$CASHTYPE_DifferenzSollIst["value"] . "')) "
. "AND (paymentid <> '" .  DbUtils::$PAYMENT_GUEST . "') AND (paymentid <> '" .  DbUtils::$PAYMENT_HOTEL . "')"
. ",brutto,'0.00')),2) as DECIMAL(12,2)) as billsumall,";

$sql .= "REPLACE(ROUND(sum(if(paymentid='" . DbUtils::$PAYMENT_BAR . "' AND (status <> 'c' or status is null),brutto,'0.00')),2),'.','" . $decpoint . "') as salesinbar,";

$sql .= "REPLACE(ROUND(sum(if(paymentid='" . DbUtils::$PAYMENT_EC . "' AND (status <> 'c' or status is null),brutto,'0.00')),2),'.','" . $decpoint . "') as salesinec,";
$sql .= "REPLACE(ROUND(sum(if(paymentid='" . DbUtils::$PAYMENT_KREDIT . "' AND (status <> 'c' or status is null),brutto,'0.00')),2),'.','" . $decpoint . "') as salesincredit,";

$sql .= "REPLACE(ROUND(sum(if((paymentid='" . DbUtils::$PAYMENT_EC . "' OR paymentid='" . DbUtils::$PAYMENT_KREDIT . "') AND (status <> 'c' or status is null),brutto,'0.00')),2),'.','" . $decpoint . "') as salesnotinbar,";

$sql .= "REPLACE(ROUND(sum(if(status = 'c' "
. "AND cashtype <> '" . Bill::$CASHTYPE_TrinkgeldAG['value'] . "' "
. "AND cashtype <> '" . Bill::$CASHTYPE_TrinkgeldAN['value'] . "' "
. "AND cashtype <> '" . Bill::$CASHTYPE_Geldtransit['value'] . "' "
. "AND cashtype <> '" . Bill::$CASHTYPE_DifferenzSollIst['value'] . "',brutto,'0.00')),2),'.','" . $decpoint . "') as sumcash, ";

$sql .= "REPLACE(ROUND(sum(if(status = 'c' AND cashtype = '" . Bill::$CASHTYPE_Geldtransit['value'] . "',brutto,'0.00')),2),'.','" . $decpoint . "') as geldtransit, ";

$sql .= "REPLACE(ROUND(sum(if(cashtype = '" . Bill::$CASHTYPE_TrinkgeldAG['value'] . "' OR cashtype = '" . Bill::$CASHTYPE_TrinkgeldAN['value'] . "',brutto,'0.00')),2),'.','" . $decpoint . "') as sumtips, ";

$sql .= "REPLACE(ROUND(sum(if(paymentid='" . DbUtils::$PAYMENT_BAR . "' AND (cashtype = '" . Bill::$CASHTYPE_TrinkgeldAG['value'] . "' OR cashtype = '" . Bill::$CASHTYPE_TrinkgeldAN['value'] . "'),brutto,'0.00')),2),'.','" . $decpoint . "') as sumtipsbar, ";
$sql .= "REPLACE(ROUND(sum(if(paymentid <> '" . DbUtils::$PAYMENT_BAR . "' AND (cashtype = '" . Bill::$CASHTYPE_TrinkgeldAG['value'] . "' OR cashtype = '" . Bill::$CASHTYPE_TrinkgeldAN['value'] . "'),brutto,'0.00')),2),'.','" . $decpoint . "') as sumtipsunbar ";

$sql .= "FROM %bill%,%user% WHERE userid=%user%.id AND closingid=?";
if (!is_null($grouping)) {
$sql .= " GROUP BY $grouping";
}
if (!is_null($ordering)) {
$sql .= " ORDER BY $ordering";
}

return CommonUtils::fetchSqlAll($pdo, $sql, array($closingid));
}

private static function getGroupedSumOfXBeleg($pdo,?string $grouping,?string $ordering) {

$sql = "SELECT userid,username,COALESCE(fullname,username) as fullname,";

$sql .= "CAST(ROUND(sum(if((cashtype is null OR (cashtype<>'" . Bill::$CASHTYPE_DifferenzSollIst["value"] . "')) "
. "AND (paymentid <> '" .  DbUtils::$PAYMENT_GUEST . "') AND (paymentid <> '" .  DbUtils::$PAYMENT_HOTEL . "')"
. ",brutto,'0.00')),2) as DECIMAL(12,2)) as billsumall,";

$sql .= "ROUND(sum(if(paymentid='" . DbUtils::$PAYMENT_BAR . "' AND (status <> 'c' or status is null),brutto,'0.00')),2) as salesinbar,";

$sql .= "ROUND(sum(if(paymentid='" . DbUtils::$PAYMENT_EC . "' AND (status <> 'c' or status is null),brutto,'0.00')),2) as salesinec,";
$sql .= "ROUND(sum(if(paymentid='" . DbUtils::$PAYMENT_KREDIT . "' AND (status <> 'c' or status is null),brutto,'0.00')),2) as salesincredit,";

$sql .= "ROUND(sum(if((paymentid='" . DbUtils::$PAYMENT_EC . "' OR paymentid='" . DbUtils::$PAYMENT_KREDIT . "') AND (status <> 'c' or status is null),brutto,'0.00')),2) as salesnotinbar,";

$sql .= "ROUND(sum(if(status = 'c' "
. "AND cashtype <> '" . Bill::$CASHTYPE_TrinkgeldAG['value'] . "' "
. "AND cashtype <> '" . Bill::$CASHTYPE_TrinkgeldAN['value'] . "' "
. "AND cashtype <> '" . Bill::$CASHTYPE_Geldtransit['value'] . "' "
. "AND cashtype <> '" . Bill::$CASHTYPE_DifferenzSollIst['value'] . "',brutto,'0.00')),2) as sumcash, ";

$sql .= "ROUND(sum(if(status = 'c' AND cashtype = '" . Bill::$CASHTYPE_Geldtransit['value'] . "',brutto,'0.00')),2) as geldtransit, ";

$sql .= "ROUND(sum(if(cashtype = '" . Bill::$CASHTYPE_TrinkgeldAG['value'] . "' OR cashtype = '" . Bill::$CASHTYPE_TrinkgeldAN['value'] . "',brutto,'0.00')),2) as sumtips, ";

$sql .= "ROUND(sum(if(paymentid='" . DbUtils::$PAYMENT_BAR . "' AND (cashtype = '" . Bill::$CASHTYPE_TrinkgeldAG['value'] . "' OR cashtype = '" . Bill::$CASHTYPE_TrinkgeldAN['value'] . "'),brutto,'0.00')),2) as sumtipsbar, ";
$sql .= "ROUND(sum(if(paymentid <> '" . DbUtils::$PAYMENT_BAR . "' AND (cashtype = '" . Bill::$CASHTYPE_TrinkgeldAG['value'] . "' OR cashtype = '" . Bill::$CASHTYPE_TrinkgeldAN['value'] . "'),brutto,'0.00')),2) as sumtipsunbar ";

$sql .= "FROM %bill%,%user% WHERE userid=%user%.id AND closingid IS NULL";
if (!is_null($grouping)) {
$sql .= " GROUP BY $grouping";
}
if (!is_null($ordering)) {
$sql .= " ORDER BY $ordering";
}
return CommonUtils::fetchSqlAll($pdo, $sql, null);

}

private function getOrderCancelsOfClosing($pdo,int $clsid) {
$sql = "select CAST(ROUND(COALESCE(SUM(price),'0.00'),2) as DECIMAL(12,2)) as price from %operations% O,%queue% Q where O.clsid=? and Q.opidcancel=O.id";
$result = CommonUtils::fetchSqlAll($pdo, $sql, array($clsid));
return $result[0]["price"];
}

private static function getOrderCancelsDetailsOfXBeleg($pdo) : array {
$sql = "select price,ordertime,COALESCE(R.tableno,'-') as tablename,PN.name as articlename 
FROM %queue% Q 
INNER JOIN %operations% O ON Q.opidcancel=O.id 
INNER JOIN %prodnames% PN ON PN.id=Q.prodnameid 
LEFT JOIN %resttables% R ON Q.tablenr=R.id WHERE O.clsid IS NULL 
ORDER BY ordertime,PN.name";
$result = CommonUtils::fetchSqlAll($pdo, $sql);
return $result;
}

private static function getOrderCancelsDetailsOfClosing($pdo,int $clsid) : array {
$sql = "select price,ordertime,COALESCE(R.tableno,'-') as tablename,PN.name as articlename 
FROM %queue% Q 
INNER JOIN %operations% O ON Q.opidcancel=O.id 
INNER JOIN %prodnames% PN ON PN.id=Q.prodnameid 
LEFT JOIN %resttables% R ON Q.tablenr=R.id WHERE O.clsid=? 
ORDER BY ordertime,PN.name";
$result = CommonUtils::fetchSqlAll($pdo, $sql, array($clsid));
return $result;
}

private static function getTaxesGroupedOfXBeleg($pdo) {

$sql = "SELECT 'VERKAUF' as thetype,Q.tax as tax,"
. "ROUND(SUM(price),2) as brutto,"
. "ROUND(SUM(price)/(1 + Q.tax/100.0),2) as netto "
. "FROM %queue% Q,%bill% B ";
$sql .= " WHERE billid=B.id AND B.closingid IS NULL AND B.paymentid <> ? AND B.paymentid <> ? GROUP BY tax";
$sql .= " UNION ALL ";
$sql .= "SELECT 'TRINKG.' as thetype,tax,"
. "ROUND(SUM(brutto),2) as brutto,"
. "ROUND(SUM(netto),2) as netto "
. "FROM %bill% B WHERE B.closingid IS NULL and status='c' "
. "AND cashtype IS NOT null AND (cashtype=? OR cashtype=? OR cashtype=?) GROUP BY tax";
$resultOfSalesAndTips = CommonUtils::fetchSqlAll($pdo, $sql, array(
DbUtils::$PAYMENT_GUEST, DbUtils::$PAYMENT_HOTEL,
Bill::$CASHTYPE_TrinkgeldBoth['value'],Bill::$CASHTYPE_TrinkgeldAG['value'],Bill::$CASHTYPE_TrinkgeldAN['value']));

$sql = "SELECT '" . self::$SALES_TXT . "' as thetype,Q.tax as tax,P.name as payment,SUM(price) as brutto,CAST(ROUND(SUM(price)/(1 + Q.tax/100.0),2) as DECIMAL(12,2)) as netto "
. "FROM %queue% Q,%bill% B,%payment% P ";
$sql .= " WHERE P.id=B.paymentid AND billid=B.id AND B.closingid IS NULL AND B.paymentid <> ? AND B.paymentid <> ? GROUP BY tax,P.name";
$sql .= " UNION ALL ";
$sql .= "SELECT 'Trinkgeld' as thetype,tax,P.name as payment,SUM(brutto) as brutto,CAST(ROUND(SUM(netto),2) as DECIMAL(12,2)) as netto FROM %bill% B,%payment% P "
. "WHERE P.id=B.paymentid AND B.closingid IS NULL and status='c' "
. "AND cashtype IS NOT null AND (cashtype=? OR cashtype=? OR cashtype=?) GROUP BY tax,P.name";
$resultOfSalesAndTipsByTaxAndPayment = CommonUtils::fetchSqlAll($pdo, $sql, array(
DbUtils::$PAYMENT_GUEST, DbUtils::$PAYMENT_HOTEL,
Bill::$CASHTYPE_TrinkgeldBoth['value'],Bill::$CASHTYPE_TrinkgeldAG['value'],Bill::$CASHTYPE_TrinkgeldAN['value']));

$sql = "SELECT 'C' as thetype,tax,SUM(brutto) as brutto,CAST(ROUND(SUM(netto),2) as DECIMAL(12,2)) as netto FROM %bill% B WHERE B.closingid IS NULL and "
. "(status='c' "
. "AND cashtype is null OR (cashtype<>? AND cashtype<>? AND cashtype<>? AND cashtype<>? AND cashtype<>?)) GROUP BY tax";
$resultCashes = CommonUtils::fetchSqlAll($pdo, $sql, array(Bill::$CASHTYPE_TrinkgeldBoth['value'],Bill::$CASHTYPE_TrinkgeldAG['value'],Bill::$CASHTYPE_TrinkgeldAN['value'],Bill::$CASHTYPE_Geldtransit['value'],Bill::$CASHTYPE_DifferenzSollIst['value']));

$sql = "SELECT 'CT' as thetype,tax,SUM(brutto) as brutto,CAST(ROUND(SUM(netto),2) as DECIMAL(12,2)) as netto FROM %bill% B WHERE B.closingid IS NULL and "
. "(status='c' "
. "AND cashtype=?) GROUP BY tax";
$resultGeldtransits = CommonUtils::fetchSqlAll($pdo, $sql, array(Bill::$CASHTYPE_Geldtransit['value']));

$sql = "SELECT CAST(ROUND(COALESCE(SUM(brutto),'0.00'),2) as DECIMAL(12,2)) as brutto FROM %bill% B WHERE B.closingid IS NULL and (status='c' AND cashtype=?)";
$resultGeldtransitsSum = CommonUtils::fetchSqlAll($pdo, $sql, array(Bill::$CASHTYPE_Geldtransit['value']));
$transitSum = $resultGeldtransitsSum[0]["brutto"];

$sql = "SELECT CAST(ROUND(COALESCE(SUM(brutto),'0.00'),2) as DECIMAL(12,2)) as brutto FROM %bill% B WHERE B.closingid IS NULL and "
. "(status='c' AND cashtype is null OR (cashtype<>? AND cashtype<>? AND cashtype<>? AND cashtype<>? AND cashtype<>?))";
$resultCashesSum = CommonUtils::fetchSqlAll($pdo, $sql, array(Bill::$CASHTYPE_TrinkgeldBoth['value'],Bill::$CASHTYPE_TrinkgeldAG['value'],Bill::$CASHTYPE_TrinkgeldAN['value'],Bill::$CASHTYPE_Geldtransit['value'],Bill::$CASHTYPE_DifferenzSollIst['value']));
$cashesSum = $resultCashesSum[0]["brutto"];

$sales = array("salesandtips" => $resultOfSalesAndTips,
"salesandtipsByTaxAndPayment" => $resultOfSalesAndTipsByTaxAndPayment,
"casheswithouttransit" => $resultCashes,
"transits" => $resultGeldtransits,
"transitsum" => $transitSum,
"cashessum" => $cashesSum);

return ($sales);
}

private function getTaxesGroupedOfClosing($pdo,$closingid,$doReplaceDecpoint,$decpoint) {
if (!$doReplaceDecpoint) {
$decpoint = ".";  
}
$sql = "SELECT 'VERKAUF' as thetype,Q.tax as tax,"
. "REPLACE(ROUND(SUM(price),2),'.','" . $decpoint . "') as brutto,"
. "REPLACE(ROUND(SUM(price)/(1 + Q.tax/100.0),2),'.','" . $decpoint . "') as netto "
. "FROM %queue% Q,%bill% B,%closing% C ";
$sql .= " WHERE billid=B.id AND B.closingid=C.id AND closingid=? AND B.paymentid <> ? AND B.paymentid <> ? GROUP BY tax";
$sql .= " UNION ALL ";
$sql .= "SELECT 'TRINKG.' as thetype,tax,"
. "REPLACE(ROUND(SUM(brutto),2),'.','" . $decpoint . "') as brutto,"
. "REPLACE(ROUND(SUM(netto),2),'.','" . $decpoint . "') as netto "
. "FROM %bill% B WHERE B.closingid=? and status='c' "
. "AND cashtype IS NOT null AND (cashtype=? OR cashtype=? OR cashtype=?) GROUP BY tax";
$resultOfSalesAndTips = CommonUtils::fetchSqlAll($pdo, $sql, array(
$closingid, DbUtils::$PAYMENT_GUEST, DbUtils::$PAYMENT_HOTEL,
$closingid,Bill::$CASHTYPE_TrinkgeldBoth['value'],Bill::$CASHTYPE_TrinkgeldAG['value'],Bill::$CASHTYPE_TrinkgeldAN['value']));

$sql = "SELECT '" . self::$SALES_TXT . "' as thetype,Q.tax as tax,P.name as payment,SUM(price) as brutto,CAST(ROUND(SUM(price)/(1 + Q.tax/100.0),2) as DECIMAL(12,2)) as netto "
. "FROM %queue% Q,%bill% B,%closing% C,%payment% P ";
$sql .= " WHERE P.id=B.paymentid AND billid=B.id AND B.closingid=C.id AND closingid=? AND B.paymentid <> ? AND B.paymentid <> ? GROUP BY tax,P.name";
$sql .= " UNION ALL ";
$sql .= "SELECT 'Trinkgeld' as thetype,tax,P.name as payment,SUM(brutto) as brutto,CAST(ROUND(SUM(netto),2) as DECIMAL(12,2)) as netto FROM %bill% B,%payment% P "
. "WHERE P.id=B.paymentid AND B.closingid=? and status='c' "
. "AND cashtype IS NOT null AND (cashtype=? OR cashtype=? OR cashtype=?) GROUP BY tax,P.name";
$resultOfSalesAndTipsByTaxAndPayment = CommonUtils::fetchSqlAll($pdo, $sql, array(
$closingid, DbUtils::$PAYMENT_GUEST, DbUtils::$PAYMENT_HOTEL,
$closingid,Bill::$CASHTYPE_TrinkgeldBoth['value'],Bill::$CASHTYPE_TrinkgeldAG['value'],Bill::$CASHTYPE_TrinkgeldAN['value']));

$sql = "SELECT 'C' as thetype,tax,SUM(brutto) as brutto,CAST(ROUND(SUM(netto),2) as DECIMAL(12,2)) as netto FROM %bill% B WHERE B.closingid=? and "
. "(status='c' "
. "AND cashtype is null OR (cashtype<>? AND cashtype<>? AND cashtype<>? AND cashtype<>? AND cashtype<>?)) GROUP BY tax";
$resultCashes = CommonUtils::fetchSqlAll($pdo, $sql, array($closingid, Bill::$CASHTYPE_TrinkgeldBoth['value'],Bill::$CASHTYPE_TrinkgeldAG['value'],Bill::$CASHTYPE_TrinkgeldAN['value'],Bill::$CASHTYPE_Geldtransit['value'],Bill::$CASHTYPE_DifferenzSollIst['value']));

$sql = "SELECT 'CT' as thetype,tax,SUM(brutto) as brutto,CAST(ROUND(SUM(netto),2) as DECIMAL(12,2)) as netto FROM %bill% B WHERE B.closingid=? and "
. "(status='c' "
. "AND cashtype=?) GROUP BY tax";
$resultGeldtransits = CommonUtils::fetchSqlAll($pdo, $sql, array($closingid,Bill::$CASHTYPE_Geldtransit['value']));

$sql = "SELECT CAST(ROUND(COALESCE(SUM(brutto),'0.00'),2) as DECIMAL(12,2)) as brutto FROM %bill% B WHERE B.closingid=? and (status='c' AND cashtype=?)";
$resultGeldtransitsSum = CommonUtils::fetchSqlAll($pdo, $sql, array($closingid,Bill::$CASHTYPE_Geldtransit['value']));
$transitSum = $resultGeldtransitsSum[0]["brutto"];

$sql = "SELECT CAST(ROUND(COALESCE(SUM(brutto),'0.00'),2) as DECIMAL(12,2)) as brutto FROM %bill% B WHERE B.closingid=? and "
. "(status='c' AND cashtype is null OR (cashtype<>? AND cashtype<>? AND cashtype<>? AND cashtype<>? AND cashtype<>?))";
$resultCashesSum = CommonUtils::fetchSqlAll($pdo, $sql, array($closingid, Bill::$CASHTYPE_TrinkgeldBoth['value'],Bill::$CASHTYPE_TrinkgeldAG['value'],Bill::$CASHTYPE_TrinkgeldAN['value'],Bill::$CASHTYPE_Geldtransit['value'],Bill::$CASHTYPE_DifferenzSollIst['value']));
$cashesSum = $resultCashesSum[0]["brutto"];

$result = array("salesandtips" => $resultOfSalesAndTips, 
"salesandtipsByTaxAndPayment" => $resultOfSalesAndTipsByTaxAndPayment,
"casheswithouttransit" => $resultCashes, 
"transits" => $resultGeldtransits,
"transitsum" => $transitSum,
"cashessum" => $cashesSum);

return ($result);
}

private function getDetailsOfSalesAndTaxesByTaxAndPaymentGroupedOfClosing($pdo,$closingid,$doReplaceDecpoint,$decpoint) {
if (!$doReplaceDecpoint) {
$decpoint = ".";  
}
$sql = "SELECT REPLACE(a.tax,'.','$decpoint') as tax,a.payment,"
. "REPLACE(SUM(a.brutto),'.','$decpoint') as brutto,"
. "REPLACE(SUM(a.netto),'.','$decpoint') as netto,"
. "REPLACE(SUM(a.brutto-a.netto),'.','$decpoint') as mwst "
. " FROM (";
$sql .= "SELECT '" . self::$SALES_TXT . "' as thetype,Q.tax as tax,P.name as payment,"
. "SUM(price) as brutto,"
. "CAST(ROUND(SUM(price)/(1 + Q.tax/100.0),2) as DECIMAL(12,2)) as netto "
. "FROM %queue% Q,%bill% B,%closing% C,%payment% P ";
$sql .= " WHERE P.id=B.paymentid AND billid=B.id AND B.closingid=C.id AND closingid=? AND B.paymentid <> ? AND B.paymentid <> ? GROUP BY tax,P.name";
$sql .= " UNION ALL ";
$sql .= "SELECT 'Trinkgeld' as thetype,tax,P.name as payment,"
. "SUM(brutto) as brutto,"
. "CAST(ROUND(SUM(netto),2) as DECIMAL(12,2)) as netto "
. " FROM %bill% B,%payment% P "
. "WHERE P.id=B.paymentid AND B.closingid=? and status='c' "
. "AND cashtype IS NOT null AND (cashtype=? OR cashtype=? OR cashtype=?) GROUP BY tax,P.name";
$sql .= ") a group by a.payment,a.tax ORDER BY a.payment,a.tax";

$result = CommonUtils::fetchSqlAll($pdo, $sql, array(
$closingid, DbUtils::$PAYMENT_GUEST, DbUtils::$PAYMENT_HOTEL,
$closingid,Bill::$CASHTYPE_TrinkgeldBoth['value'],Bill::$CASHTYPE_TrinkgeldAG['value'],Bill::$CASHTYPE_TrinkgeldAN['value']));
return $result;
}

private static function getDetailsOfSalesAndTaxesByTaxAndPaymentGroupedOfXBeleg($pdo) {
$sql = "SELECT a.tax as tax,a.payment,"
. "SUM(a.brutto) as brutto,"
. "SUM(a.netto) as netto,"
. "SUM(a.brutto-a.netto) as mwst "
. " FROM (";
$sql .= "SELECT '" . self::$SALES_TXT . "' as thetype,Q.tax as tax,P.name as payment,"
. "SUM(price) as brutto,"
. "CAST(ROUND(SUM(price)/(1 + Q.tax/100.0),2) as DECIMAL(12,2)) as netto "
. "FROM %queue% Q,%bill% B,%payment% P ";
$sql .= " WHERE P.id=B.paymentid AND billid=B.id AND B.closingid IS NULL AND B.paymentid <> ? AND B.paymentid <> ? GROUP BY tax,P.name";
$sql .= " UNION ALL ";
$sql .= "SELECT 'Trinkgeld' as thetype,tax,P.name as payment,"
. "SUM(brutto) as brutto,"
. "CAST(ROUND(SUM(netto),2) as DECIMAL(12,2)) as netto "
. " FROM %bill% B,%payment% P "
. "WHERE P.id=B.paymentid AND B.closingid IS NULL and status='c' "
. "AND cashtype IS NOT null AND (cashtype=? OR cashtype=? OR cashtype=?) GROUP BY tax,P.name";
$sql .= ") a group by a.payment,a.tax ORDER BY a.payment,a.tax";

$result = CommonUtils::fetchSqlAll($pdo, $sql, array(
DbUtils::$PAYMENT_GUEST, DbUtils::$PAYMENT_HOTEL,
Bill::$CASHTYPE_TrinkgeldBoth['value'],Bill::$CASHTYPE_TrinkgeldAG['value'],Bill::$CASHTYPE_TrinkgeldAN['value']));
return $result;
}

private function getOperationsByTax($pdo,$closingid,$doReplaceDecpoint,$decpoint) {
if (!$doReplaceDecpoint) {
$decpoint = ".";  
}

$guestPayment = DbUtils::$PAYMENT_GUEST;
$hotelPayment = DbUtils::$PAYMENT_HOTEL;

$sql = "SELECT '" . self::$SALES_TXT . "' as thetype,"
. "REPLACE(ROUND(COALESCE(SUM(IF(B.status='s',-1,1) * Q.price),'0.00'),2),'.','" . $decpoint . "') as bruttosum,"
. "REPLACE(ROUND(COALESCE(SUM((IF(B.status='s',-1,1) * Q.price / (1 + Q.tax * 0.01))),'0.00'),2),'.','" . $decpoint . "') as nettosum,"
. "REPLACE(ROUND(COALESCE(SUM((IF(B.status='s',-1,1) * (Q.price - Q.price / (1 + Q.tax * 0.01)))),'0.00'),2),'.','" . $decpoint . "') as steuer,"
. "Q.tax "
. "from %queue% Q,%billproducts% BP,%bill% B "
. "WHERE Q.id=BP.queueid and BP.billid=B.id AND B.closingid=? AND B.paymentid <> '$guestPayment' AND B.paymentid <> '$hotelPayment' GROUP BY tax "
. " UNION ALL "
. "SELECT '" . self::$TGAG . "' as thetype,"
. "REPLACE(ROUND(COALESCE(SUM(brutto),'0.00'),2),'.','" . $decpoint . "') as bruttosum,"
. "REPLACE(ROUND(COALESCE(SUM(netto),'0.00'),2),'.','" . $decpoint . "') as nettosum,"
. "REPLACE(ROUND(COALESCE(SUM(brutto-netto),'0.00'),2),'.','" . $decpoint . "') as steuer,"
. "tax FROM %bill% B WHERE B.status='c' AND closingid=? AND cashtype='" . Bill::$CASHTYPE_TrinkgeldAG['value'] . "' GROUP BY tax"
. " UNION ALL "
. "SELECT 'Sonstiges' as thetype,"
. "REPLACE(ROUND(COALESCE(SUM(brutto),'0.00'),2),'.','" . $decpoint . "') as bruttosum,"
. "REPLACE(ROUND(COALESCE(SUM(netto),'0.00'),2),'.','" . $decpoint . "') as nettosum,"
. "REPLACE(ROUND(COALESCE(SUM(brutto-netto),'0.00'),2),'.','" . $decpoint . "') as steuer,"
. "tax FROM %bill% B WHERE B.status='c' AND closingid=? AND cashtype <> '" . Bill::$CASHTYPE_TrinkgeldAG['value'] . "' GROUP BY tax";
return CommonUtils::fetchSqlAll($pdo, $sql, array($closingid,$closingid,$closingid));
}

private function getCashOpsOfClosing($pdo,$closingid) {
$allowedCashtypes = array(Bill::$CASHTYPE_Einzahlung["value"],Bill::$CASHTYPE_Privateinlage["value"],Bill::$CASHTYPE_Privatentnahme["value"],Bill::$CASHTYPE_Auszahlung["value"]);

$sql = "SELECT CAST(ROUND(COALESCE(SUM(brutto),'0.00'),2) as DECIMAL(12,2)) as cashsum FROM %bill%,%closing% WHERE status=? AND closingid=%closing%.id AND closingid=? "
. "AND cashtype IN (" . implode(',',$allowedCashtypes) . ")";
$result = CommonUtils::fetchSqlAll($pdo, $sql, array('c',$closingid));

return ($result[0]["cashsum"]);
}

private function getTips($pdo,$closingid) {
$sql = "SELECT userid,username as iter,";
$sql .= "SUM(B.tip) as sum ";
$sql .= "FROM %bill% B,%user% U WHERE B.userid=U.id AND B.closingid=? ";
$sql .= "GROUP BY userid,username ";
$sql .= "HAVING sum is not null ORDER BY username";
$result = CommonUtils::fetchSqlAll($pdo, $sql, array($closingid));
return $result;
}
private function getSumOfAllTips($pdo,$closingid) {
$sql = "SELECT COALESCE(SUM(B.tip),'0.00') as sum FROM %bill% B WHERE B.closingid=? ";
$result = CommonUtils::fetchSqlAll($pdo, $sql, array($closingid));
return $result[0]["sum"];
}

private function getCategoriasBruttoOfClosing($pdo,$closingid,$decpoint) {
$sql = "SELECT REPLACE(ROUND(SUM(price),2),'.','" . $decpoint . "') as brutto"
. ",kind,(CASE WHEN kind='0' THEN 'Speisen' WHEN KIND='1' THEN 'Getränke' ELSE '?' END) as readablekind "
. "FROM %queue% Q,%bill%,%closing%,%prodtype% T,%products% P ";
$sql .= " WHERE billid=%bill%.id AND %bill%.closingid=%closing%.id AND closingid=? AND %bill%.paymentid <> ? ";
$sql .= " AND Q.productid=P.id AND P.category=T.id ";
$sql .= " GROUP BY kind";
return CommonUtils::fetchSqlAll($pdo, $sql, array($closingid,DbUtils::$PAYMENT_GUEST));
}

private static function getFirstAndLastClosing() {
$pdo = DbUtils::openDbAndReturnPdoStatic();
$sql = "select id,closingdate from %closing% ORDER BY closingdate LIMIT 1";
$firstClosing = CommonUtils::fetchSqlAll($pdo, $sql);
$sql = "select id,closingdate from %closing% ORDER BY closingdate DESC LIMIT 1";
$lastClosing = CommonUtils::fetchSqlAll($pdo, $sql);
echo json_encode(array("status" => "OK", "firstclosing" => self::closingSqlDateToText($firstClosing),"lastclosing" => self::closingSqlDateToText($lastClosing)));
}

private static function closingSqlDateToText($closdate) {
if (count($closdate) > 0) {
return $closdate[0]["closingdate"];
} else {
return "";
}
}

private function getClosingsListOfMonthYear($month,$year,$sort) {
$pdo = DbUtils::openDbAndReturnPdoStatic();
date_default_timezone_set(DbUtils::getTimeZone());
$monthText=$month;
if ($month < 10) {
$monthText = "0" . $month;
}

$lastDayInMonth = date("t", mktime(0, 0, 0, $month, 1, $year));
$dateStart = $year . $monthText . "01";
$dateEnd = $year . $monthText . $lastDayInMonth;

if ($sort != 'desc') {
$sort = '';
}

$sql = "SELECT id,COALESCE(remark,'') as remark,DATE_FORMAT(closingdate,'%w') as dayofweek,DATE_FORMAT(closingdate, '%d.%m.%Y %k:%i') as closdate from %closing% WHERE DATE(closingdate) BETWEEN ? AND ? ORDER BY id $sort";
$result = CommonUtils::fetchSqlAll($pdo, $sql, array($dateStart,$dateEnd));
echo json_encode(array("status" => "OK", "msg" => $result));
}

private static function getStornoDetailsOfSetOfBills($pdo,array $bills):array {
$retArr = array();
$sql = "SELECT billdate as origdate,"
. "ROUND(brutto,2) as brutto,"
. "billuid,COALESCE(reason,'') as reason from %bill% where status is not null and status=? and ref=?";
foreach($bills as $r) {
$billid = $r['id'];
$origBills = CommonUtils::fetchSqlAll($pdo, $sql, array('x',$billid));
$retArr[] = array(
"origdate" => $origBills[0]["origdate"],
"brutto" => $origBills[0]["brutto"],
"billuid" => $origBills[0]["billuid"],
"canceldate" => $r["canceldate"],
"canceltime" => $r["canceltime"],
"cancellingbilluid" => $r["cancellingbilluid"],
"reason" => $origBills[0]["reason"]
);
}
return $retArr;
}

private static function getCancelledBillsOfXBeleg($pdo) {
$sql = "SELECT billdate as canceldate,TIME(billdate) as canceltime,billuid as cancellingbilluid,id FROM %bill% WHERE status is not null and status=? and closingid IS NULL";
$result = CommonUtils::fetchSqlAll($pdo, $sql, array('s'));
$retArr = self::getStornoDetailsOfSetOfBills($pdo,$result);
return $retArr;
}

private static function getCancelledBills($pdo,int $clsid,$doReplaceDecpoint,$decpoint) {
if (!$doReplaceDecpoint) {
$decpoint = ".";  
}
$sql = "SELECT billdate as canceldate,TIME(billdate) as canceltime,billuid as cancellingbilluid,id FROM %bill% WHERE status is not null and status=? and closingid=?";
$result = CommonUtils::fetchSqlAll($pdo, $sql, array('s',$clsid));
$retArr = self::getStornoDetailsOfSetOfBills($pdo,$result);
return $retArr;
}

public function getASingleClosing($pdo,$closingid,$doReplaceDecpoint = false) {
$clsid = $closingid;
if (gettype($closingid) == 'string') {
$clsid= intval($closingid);
}
$decpoint = '.';
if ($doReplaceDecpoint) {
$decpoint = CommonUtils::getConfigValue($pdo, 'decpoint', '.');
}

$sql = "SELECT id,closingdate,remark,counting,counted,version FROM %closing% WHERE id=?";
$result = CommonUtils::fetchSqlAll($pdo, $sql, array($clsid));
$zeile = $result[0];

$closingDate = $zeile['closingdate'];
$remark = $zeile['remark'];
$counting = $zeile['counting'];
$counted = str_replace('.',$decpoint,$zeile['counted']);
$version = $zeile["version"];
$masterData = CommonUtils::getMasterDataAtCertainDateTime($pdo, $closingDate, "clstemplate");
$coinname = $masterData['coinvalname'];
$notename = $masterData['notevalname'];
$barTotalBeforeTE = self::getBarTotalBeforeTE($pdo,$clsid,$decpoint);
$barTotalAfterTE = self::getBarTotalAfterTE($pdo,$clsid,$decpoint);

$diffSollIst = 0.0;
if ($counting == 1) {
$sql = "SELECT id,value,count,iscoin,IF(iscoin='1','Münzen','Banknoten') as coinsornotes,REPLACE(ROUND((count*value*IF(iscoin='1',1,100))/100.0,2),'.','" . $decpoint . "') as sum,IF(iscoin='1',1,100) as factor FROM %counting% WHERE clsid=? ORDER BY iscoin DESC,value";
$countingprotocol = CommonUtils::fetchSqlAll($pdo, $sql, array($clsid));
$diffSollIst = $counted - $barTotalAfterTE;
} else {
$countingprotocol = array();
}

$totalSum = str_replace('.',$decpoint,$this->getSumOfBillsWithClosingId($pdo,$clsid, false, true));
$ordercancels = str_replace('.',$decpoint,$this->getOrderCancelsOfClosing($pdo,$clsid));

$userSums = self::getUserGroupedSumOfClosing($pdo, $clsid,$doReplaceDecpoint,$decpoint);
$totalSums = self::getTotalsGroupedSumOfClosing($pdo, $clsid,$doReplaceDecpoint,$decpoint);
$taxessums = $this->getTaxesGroupedOfClosing($pdo,$clsid,$doReplaceDecpoint,$decpoint);
$salesAndTipsByPaymentAndTax = $this->getDetailsOfSalesAndTaxesByTaxAndPaymentGroupedOfClosing($pdo,$clsid,$doReplaceDecpoint,$decpoint);
$operationsByTax = $this->getOperationsByTax($pdo, $clsid,$doReplaceDecpoint,$decpoint);
$overview = array();
foreach($taxessums['salesandtipsByTaxAndPayment'] as $anItem) {
$overview[] = array("name" => $anItem["thetype"],"brutto" => $anItem["brutto"],"netto" => $anItem["netto"],"tax" => $anItem["tax"],"payment" => $anItem["payment"]);
}
foreach($taxessums['casheswithouttransit'] as $anItem) {
$overview[] = array("name" => "Barein-/auslage","brutto" => $anItem["brutto"],"netto" => $anItem["netto"],"tax" => $anItem["tax"],"payment" => "Bar");
}
foreach($taxessums['transits'] as $anItem) {
$overview[] = array("name" => "Geldtransit","brutto" => $anItem["brutto"],"netto" => $anItem["netto"],"tax" => $anItem["tax"],"payment" => "Bar");
}

$proddetails = $this->getProdDetailsOfAClosing($pdo,$clsid);

$categorysums = $this->getCategoriasBruttoOfClosing($pdo,$clsid,$decpoint);
$cashsum = $this->getCashOpsOfClosing($pdo,$clsid);
$tips = $this->getTips($pdo,$clsid);
$daynameno = date('N', strtotime($closingDate));

$pureSales = $this->getSumOfPureSalesBillsWithClosingId($pdo, $clsid);
$transitsum = $taxessums['transitsum'];
$tipssum = $this->getSumOfAllTips($pdo, $clsid);
if ($doReplaceDecpoint) {
$pureSales = str_replace('.',$decpoint,number_format($pureSales, 2, '.',''));
$transitsum = str_replace('.',$decpoint,$transitsum);
$tipssum = str_replace('.',$decpoint,$tipssum);
$cashsum  = str_replace('.',$decpoint,$cashsum);
}

$billcount = $this->getCountOfSalesBillsWithClosingId($pdo, $clsid);

$cancelledBills = self::getCancelledBills($pdo,$clsid,$doReplaceDecpoint,$decpoint);
$ordercancelsdetails = self::getOrderCancelsDetailsOfClosing($pdo,$clsid);

$sn = CommonUtils::getConfigValue($pdo, "sn", "");
$systemid = CommonUtils::getConfigValue($pdo, "systemid", "");
$companyonfo = CommonUtils::getConfigValue($pdo, "companyinfo", "");

$closingEntry = array(
"status" => "OK",
"closingid" => $clsid, 
"closingDate" => $closingDate, 
"sn" => $sn,
"systemid" => $systemid,
"companyinfo" => $companyonfo,
"version" => $version,
"daynameno" => $daynameno, 
"remark" => $remark, 
"totalsum" => $totalSum, 
"cashsum" => $cashsum,
"usersums" => $userSums,
"totalsums" => $totalSums,
"taxessums" => $taxessums,
"salesandtipsbypaymentandtax" => $salesAndTipsByPaymentAndTax,
"operationsbytax" => $operationsByTax,
"categorysums" => $categorysums,
"cashops" => $cashsum,
"counted" => $counted,
"coinname" => $coinname,
"notename" => $notename,
"tips" => $tips,
"tipssum" => $tipssum,
"puresales" => $pureSales,
"billcount" => $billcount,
"transits" => $taxessums['transits'],
"casheswithouttransit" => $taxessums['cashessum'], 
"transitsum" => $transitsum,
"ordercancels" => $ordercancels,
"ordercancelsdetails" => $ordercancelsdetails,
"overview" => $overview,
"details" => $proddetails,
"countingprotocol" => $countingprotocol,
"barTotalBeforeTE" => $barTotalBeforeTE,
"barTotalAfterTE" => $barTotalAfterTE,
"counting" => $counting,
"diffsollist" => $diffSollIst,
"cancelledbills" => $cancelledBills,
"template" => $masterData["template"]);
return $closingEntry;
}

private static function getBarTotalBeforeTE($pdo,$clsid,$decpoint) {

if ($clsid == 1) {
return "0.00";
}

$sqlPrevClsId = "SELECT IFNULL(MAX(id),'0') FROM %closing% C where id<?";
$sql = "SELECT REPLACE(COALESCE(counted,'0.00'),'.','$decpoint') as counted FROM %closing% WHERE id=($sqlPrevClsId)";
$countedSql = CommonUtils::fetchSqlAll($pdo, $sql, array($clsid));
return $countedSql[0]['counted'];
}

private static function getBarTotalAfterTE($pdo,$clsid,$decpoint) {

$previousCash = doubleVal(self::getBarTotalBeforeTE($pdo, $clsid, '.'));
$sql = "SELECT COALESCE(SUM(B.brutto),'0.00') as sumbrutto FROM %bill% B,%closing% C "
. "WHERE B.paymentid='" . DbUtils::$PAYMENT_BAR . "' "
. "AND (B.cashtype is null OR (B.cashtype <> '" . Bill::$CASHTYPE_DifferenzSollIst["value"] . "')) "
. "AND C.id=? "
. "AND C.id=B.closingid AND paymentid <> ? AND paymentid <> ?";
$result = CommonUtils::fetchSqlAll($pdo, $sql,array($clsid,DbUtils::$PAYMENT_GUEST, DbUtils::$PAYMENT_HOTEL));
$salesAndCashOps = doubleval($result[0]['sumbrutto']);
return number_format($salesAndCashOps + $previousCash,2,$decpoint,'');
}

private function getClosing($closingid) {
$pdo = DbUtils::openDbAndReturnPdoStatic();
$this->retrieveClosingFromDb($pdo,$closingid, false, false);
}

private function exportCsv($closingid) {
$pdo = DbUtils::openDbAndReturnPdoStatic();
$this->retrieveClosingFromDb($pdo,$closingid, true, false);
}

private function exportGuestCsv($closingid) {
$pdo = DbUtils::openDbAndReturnPdoStatic();
Customers::exportLogOfOneClosing($pdo, $closingid);
}

private function emailCsvCore($pdo,$closingid,$toEmail,$topic,$startdate,$enddate) {
$decpoint = $this->getDecPoint($pdo);
$closing = $this->getASingleClosing($pdo, $closingid,true);
$taxessums = $closing["taxessums"];

$msg = "Zeitraum: $startdate - $enddate\n";

$msg .= "\nUmsatz: " . $closing['puresales'] . "\n\n";

foreach($taxessums['salesandtipsByTaxAndPayment'] as $anItem) {
$msg .= $anItem["thetype"] . "\n";
$msg .= "*   Steuersatz: " . number_format($anItem["tax"],2,$decpoint, '') . "\n";
$msg .= "*   Brutto: " . number_format($anItem["brutto"],2,$decpoint, '') . "\n";
$msg .= "*   Netto: " . number_format($anItem["netto"],2,$decpoint, '') . "\n";
}

$msg = str_replace("\n", "\r\n", $msg);

$topictxt = $topic . " " .  $closingid . "\r\n";

if (Emailer::sendEmail($pdo, $msg, $toEmail, $topictxt)) {
return true;
} else {
return false;
}
}
private function emailCsv($closingid,$toEmail,$topic) {
$pdo = DbUtils::openDbAndReturnPdoStatic();
$prevClosingDate = self::getDateOfPreviousClosing($pdo,$closingid);
if (is_null($prevClosingDate)) {
$prevClosingDate = "";
}
$sql = "SELECT closingdate, billcount, billsum FROM %closing% WHERE id=?";
$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
$stmt->execute(array($closingid));
$row = $stmt->fetchObject();
$closdate = $row->closingdate;

if ($this->emailCsvCore($pdo,$closingid, $toEmail, $topic, $prevClosingDate,$closdate)) {
echo json_encode(array("status" => "OK"));
} else {
echo json_encode(array("status" => "ERROR", "code" => ERROR_EMAIL_FAILURE, "msg" => ERROR_EMAIL_FAILURE_MSG));
}
}

private function getGeneralItemFromDbWithPdo($pdo,$field) {
if (is_null($pdo)) {
$pdo = $this->dbutils->openDbAndReturnPdo();
}

$aValue="";
$sql = "SELECT setting FROM %config% where name='$field'";
$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
$stmt->execute();
$row =$stmt->fetchObject();
if ($row != null) {
$aValue = $row->setting;
}
return $aValue;
}

public static function getDateOfPreviousClosing($pdoval,$closingid) {
if (is_null($pdoval)) {
$pdo = DbUtils::openDbAndReturnPdoStatic();
} else {
$pdo = $pdoval;
}

// ids can be generated but not used in case of rollback
$sql = "SELECT MAX(id) as previousid FROM %closing% WHERE id<?";
$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
$stmt->execute(array($closingid));
$row =$stmt->fetchObject();
if ($row != null) {
$previousId = intval($row->previousid);

$sql = "SELECT closingdate FROM %closing% WHERE id=?";
$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
$stmt->execute(array($previousId));
$row =$stmt->fetchObject();
if ($row != null) {
return $row->closingdate;
} else {
return null;
}
} else {
return null;
}
}


private function retrieveClosingFromDb($pdo,$closingid,$doCsvExport,$onlyresultreturn) {
if(session_id() == '') {
session_start();
}

$l = $_SESSION['language'];
$commonUtils = new CommonUtils();
$currency = $commonUtils->getCurrency();

$decpoint = $this->getDecPoint();
$previousClosingDate = self::getDateOfPreviousClosing(null,$closingid);

$unit = CommonUtils::caseOfSqlUnitSelection($pdo);

$csv = "";

if ($doCsvExport || $onlyresultreturn) {
$file_name = "tagesabschluss.csv";
header("Content-type: text/x-csv");
header("Content-Disposition: attachment; filename=$file_name");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Pragma: no-cache");
header("Expires: 0");

$csv .= $this->t['ID'][$l] . ";" . $this->t['Date'][$l] . ";"  . $this->t['Tablename'][$l] . ";" . $this->t['Prod'][$l] . ";" 
. $this->t['Category'][$l] . ";" . $this->t['Option'][$l] . ";"
. $this->t['Brutto'][$l] . " ($currency);Brutto abgerechnet ($currency);";
$csv .= $this->t['Netto'][$l] . "($currency);Netto abgerechnet ($currency);";
$csv .= $this->t['Tax'][$l] . ";%;";
$csv .= $this->t['PayWay'][$l] . ";";
$csv .= $this->t['Userid'][$l] . ";";
$csv .= $this->t['User'][$l] . ";";
$csv .= $this->t['Fullname'][$l] . ";";
$csv .= $this->t['State'][$l] . ";";
$csv .= $this->t['Ref'][$l] . "\n";
}


$sql = "SELECT closingdate,remark,signature,billsum,billcount,saleswithoutcash FROM %closing% WHERE id=?";
$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
$stmt->execute(array($closingid));
$row = $stmt->fetchObject();
$closingdate = $row->closingdate;
$remark = $row->remark;
$saleswithoutcash = $row->saleswithoutcash;

$billuidSql = Bill::getBillUidSqlPart();

$sql = "SELECT * from (";
$sql .= "SELECT ";
$sql .= "B.id as dbbillid,$billuidSql as billid,"
. "B.billdate,COALESCE(R.tableno,'') as tablename,CONCAT($unit,PN.name) as productname,"
. "T.name as prodtype,Q.anoption as remark,"
. "CONCAT(CASE WHEN B.status='s' THEN '-' ELSE '' END,REPLACE(Q.price,'.','$decpoint')) as brutto,"
. "(CASE WHEN B.paymentid='" . DbUtils::$PAYMENT_GUEST . "' THEN REPLACE('0.00','.','$decpoint') ELSE CONCAT(CASE WHEN B.status='s' THEN '-' ELSE '' END,REPLACE(Q.price,'.','$decpoint')) END) as realbrutto,"
. "CONCAT(CASE WHEN B.status='s' THEN '-' ELSE '' END,REPLACE(round(Q.price / (1.0 + Q.tax/100.0),2),'.','$decpoint')) as netto,"
. "(CASE WHEN B.paymentid='" . DbUtils::$PAYMENT_GUEST . "' THEN REPLACE('0.00','.','$decpoint') ELSE CONCAT(CASE WHEN B.status='s' THEN '-' ELSE '' END,REPLACE(round(Q.price / (1.0 + Q.tax/100.0),2),'.','$decpoint')) END) as realnetto,"
. "CONCAT(CASE WHEN B.status='s' THEN '-' ELSE '' END,REPLACE(ROUND(Q.price-(Q.price / (1.0 + Q.tax/100.0)),2),'.','$decpoint')) as mwst,"
. "REPLACE(Q.tax,'.','$decpoint') as tax, "
. "(CASE WHEN B.status is null THEN '' WHEN B.status='s' THEN 'storniert' WHEN B.status='x' THEN 'storniert' END) as status, "
. "COALESCE(BILLREF.billuid,'') as ref,"
. "PAY.name as paymentname,"
. "U.id as userid, "
. "U.username as username, "
. "COALESCE(U.fullname,U.username) as fullname ";
$sql .= "FROM %queue% Q ";
$sql .= "INNER JOIN %prodnames% PN ON Q.prodnameid=PN.id ";
$sql .= "INNER JOIN %products% P ON Q.productid=P.id ";
$sql .= "LEFT JOIN %prodtype% T ON P.category=T.id ";
$sql .= "INNER JOIN %billproducts% BP ON Q.id=BP.queueid ";
$sql .= "INNER JOIN %bill% B ON BP.billid=B.id ";
$sql .= "LEFT JOIN %resttables% R ON R.id=B.tableid ";
$sql .= "INNER JOIN %payment% PAY ON B.paymentid=PAY.id ";
$sql .= "INNER JOIN %user% U ON B.userid=U.id ";
$sql .= "LEFT JOIN %bill% BILLREF ON B.ref=BILLREF.id ";
$sql .= "WHERE B.closingid=? AND ((B.status is null) OR (B.status <> 'c')) ";


$sql .= " UNION ALL ";
$sql .= "SELECT ";
$sql .= "B.id as dbbillid,CONCAT('V-',billuid) as billid,"
. "billdate,'' as tablename,"
. "(CASE WHEN cashtype='" . Bill::$CASHTYPE_Auszahlung['value'] . "' THEN 'Auszahlung' 
WHEN cashtype='" . Bill::$CASHTYPE_Einzahlung['value'] . "' THEN 'Einzahlung'
WHEN cashtype='" . Bill::$CASHTYPE_Privateinlage['value'] . "' THEN 'Privateinlage'
WHEN cashtype='" . Bill::$CASHTYPE_Privatentnahme['value'] . "' THEN 'Privatentnahme'
WHEN cashtype='" . Bill::$CASHTYPE_Geldtransit['value'] . "' THEN 'Geldtransit' 
WHEN cashtype='" . Bill::$CASHTYPE_DifferenzSollIst['value'] . "' THEN 'DifferenzSollIst' 
WHEN cashtype='" . Bill::$CASHTYPE_TrinkgeldAG['value'] . "' THEN 'TrinkgeldAG' 
WHEN cashtype='" . Bill::$CASHTYPE_TrinkgeldAN['value'] . "' THEN 'TrinkgeldAN' 
ELSE '' END) as productname,"
. "'' as prodtype,B.reason as remark,"
. "REPLACE(B.brutto,'.','$decpoint') as brutto,"
. "REPLACE(B.brutto,'.','$decpoint') as realbrutto,"
. "REPLACE(B.netto,'.','$decpoint') as netto,"
. "REPLACE(B.netto,'.','$decpoint') as realnetto,"
. "REPLACE(ROUND(0.00,2),'.','$decpoint') as mwst,"
. "REPLACE(0.00,'.','$decpoint') as tax, "
. "(CASE WHEN cashtype='" . Bill::$CASHTYPE_Auszahlung['value'] . "' THEN 'Auszahlung' 
WHEN cashtype='" . Bill::$CASHTYPE_Einzahlung['value'] . "' THEN 'Einzahlung'
WHEN cashtype='" . Bill::$CASHTYPE_Privateinlage['value'] . "' THEN 'Privateinlage'
WHEN cashtype='" . Bill::$CASHTYPE_Privatentnahme['value'] . "' THEN 'Privatentnahme'
WHEN cashtype='" . Bill::$CASHTYPE_Geldtransit['value'] . "' THEN 'Geldtransit' 
WHEN cashtype='" . Bill::$CASHTYPE_DifferenzSollIst['value'] . "' THEN 'DifferenzSollIst' 
WHEN cashtype='" . Bill::$CASHTYPE_TrinkgeldAG['value'] . "' THEN 'TrinkgeldAG' 
WHEN cashtype='" . Bill::$CASHTYPE_TrinkgeldAN['value'] . "' THEN 'TrinkgeldAN' 
ELSE '' END) as status,"
. "'' as ref, "
. "'' as paymentname,"
. "U.id as userid, "
. "U.username as username, "
. "COALESCE(U.fullname,U.username) as fullname ";
$sql .= "FROM %bill% B ";
$sql .= "INNER JOIN %user% U ON B.userid=U.id ";
$sql .= "WHERE B.closingid=? AND B.status='c' ";


$sql .= ") a ";
$sql .= "ORDER BY CAST(a.dbbillid AS UNSIGNED)";

$sales = CommonUtils::fetchSqlAll($pdo, $sql, array($closingid,$closingid));

$retValues = array();

foreach ($sales as $aSale) {

$retValues[] = array (
"billid" => $aSale['billid'],
"tablename" => $aSale['tablename'],
"paidtime" => $aSale['billdate'],
"productname" => $aSale['productname'],
"kind" => "-",
"option" => $aSale['remark'],
"price" => $aSale['brutto'],
"realbrutto" => $aSale['realbrutto'],
"netto" => $aSale['netto'],
"realnetto" => $aSale['realnetto'],
"tax" => $aSale['tax'],
"payment" => $aSale['paymentname'],
"userid" => $aSale['userid'],
"username" => $aSale['username'],
"fullname" => $aSale['fullname'],
"status" => $aSale['status'],
"ref" => $aSale['ref']
//"ref" => $ref
);

if ($doCsvExport || $onlyresultreturn) {
$csv .= '"' . $aSale['billid'] . '";';
$csv .= '"' . $aSale['billdate'] . '";';
$csv .= '"' . $aSale['tablename'] . '";';
$csv .= '"' . $aSale['productname'] . '";';
$csv .= '"' . $aSale['prodtype'] . '";';
$csv .= '"' . $aSale['remark'] . '";';
$csv .= $aSale['brutto'] . ';';
$csv .= $aSale['realbrutto'] . ';';
$csv .= $aSale['netto'] . ';';
$csv .= $aSale['realnetto'] . ';';
$csv .= $aSale['mwst'] . ';';
$csv .= $aSale['tax'] . ';';
$csv .= '"' . $aSale['paymentname'] . '";';
$csv .= $aSale['userid'] . ';';
$csv .= '"' . $aSale['username'] . '";';
$csv .= '"' . $aSale['fullname'] . '";';
$csv .= '"' . $aSale['status'] . '";';
$csv .= '"' . $aSale['ref'] . '";';
$csv .= "\n";
}
}

if ($doCsvExport) {
echo $csv;
} else if ($onlyresultreturn) {
return "Tagesabschluss-Datum: $closingdate\nBemerkung: $remark\n\ncsv-Daten:\n" . $csv;
} else {
echo json_encode(array("status" => "OK", "msg" => $retValues, "closingid" => $closingid, "closingdate" => $closingdate, "previousClosingDate" => $previousClosingDate, "saleswithoutcash" => $saleswithoutcash));
}
}

public function getClosingSummaryWoSign_obsolete($closingid,$pdo,$fromWeb,$replacedecpoint,$fl=0) {
return $this->getClosingSummaryCore($closingid, $pdo, $fromWeb, false,$replacedecpoint,$fl);
}

public function getClosingSummary($closingid,$pdo,$fromWeb,$fl=0) {
return $this->getClosingSummaryCore($closingid, $pdo, $fromWeb, true,false,$fl);
}

public static function signAllClosings($pdo) {
$sql = "select id,closingdate,billcount,billsum,remark,signature from %closing%";
$r = CommonUtils::fetchSqlAll($pdo, $sql);
$sql = "UPDATE %closing% SET signature=? WHERE id=?";
foreach ($r as $c) {
$closingid = $c["id"];
$previousClosingDate = self::getDateOfPreviousClosing($pdo,$closingid);
if (is_null($previousClosingDate)) {
$startDate = "";
} else {
$startDate = $previousClosingDate;
}
$billsumstr = number_format($c["billsum"], 2, ".", '');
$billcount = $c["billcount"];
$closingdate = $c["closingdate"];
$data = "I($closingid)-S($startDate)-E($closingdate)-D($billcount)-S($billsumstr)";
$md5ofdata = md5($data);
CommonUtils::execSql($pdo, $sql, array($md5ofdata,$closingid));
}
}

public static function checkForClosingConsistency($pdo,$closingid) {
$sql = "select id,closingdate,billcount,billsum,remark,signature from %closing% where id=?";
$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
$stmt->execute(array($closingid));
$closingpart = $stmt->fetchObject();


$previousClosingDate = self::getDateOfPreviousClosing($pdo,$closingid);
if (is_null($previousClosingDate)) {
$startDate = "";
} else {
$startDate = $previousClosingDate;
}
$billsumstr = number_format($closingpart->billsum, 2, ".", '');
$billcount = $closingpart->billcount;
$closingdate = $closingpart->closingdate;
$data = "I($closingid)-S($startDate)-E($closingdate)-D($billcount)-S($billsumstr)";
$md5ofdata = md5($data);
$ok = 1;
if (($closingpart->signature) != $md5ofdata) {
$ok = 0;
}
return $ok;
}

private static function getClosingMasterDataAtClsTime($pdo,$closid) {
$sql = "SELECT closingdate FROM %closing% WHERE id=?";
$res = CommonUtils::fetchSqlAll($pdo, $sql, array($closid));
$clsdate = $res[0]["closingdate"];
return CommonUtils::getMasterDataAtCertainDateTime($pdo, $clsdate, "clstemplate");
}

private static function getExpectedBarValue($pdo) {
$sql = "select counted from %closing% ORDER BY id DESC LIMIT 1";
$result = CommonUtils::fetchSqlAll($pdo, $sql, null);
$cashFromPreviousCls = 0.0;
if (count($result) > 0) {
$cashFromPreviousCls = doubleval($result[0]["counted"]);
}
$notInCls = self::getCashSumOfBillsNotInClosingYet($pdo);
return $cashFromPreviousCls + $notInCls;
}
private static function getCashSumOfBillsNotInClosingYet($pdo) {
$sql = "SELECT CAST(ROUND(COALESCE(SUM(brutto),0.00),2) as DECIMAL(12,2)) as sumbrutto "
. "FROM %bill% "
. "WHERE (status is null OR (status='x' OR status='s' OR status='c')) AND paymentid=? AND closingid is null";
$result = CommonUtils::fetchSqlAll($pdo, $sql, array(1));
return doubleval($result[0]["sumbrutto"]);
}

public static function getCashSumsForNextClosing($pdo) {
$decpoint = CommonUtils::getConfigValue($pdo, "decpoint", ".");

$sql = "SELECT MAX(id) as maxid FROM %closing%";
$result = CommonUtils::fetchSqlAll($pdo, $sql);
$maxid = $result[0]["maxid"];
if (is_null($maxid)) {
$origCashInDeskFloat = 0.00;
} else {
$sql = "SELECT counted FROM %closing% WHERE id=?";
$result = CommonUtils::fetchSqlAll($pdo, $sql, array($maxid));
$origCashInDeskFloat = doubleval($result[0]["counted"]);
}

$cashedNotInClosing = self::getCashSumOfBillsNotInClosingYet($pdo);

$cashInDeskFloat = $origCashInDeskFloat + $cashedNotInClosing;

$cashInDesk = number_format($cashInDeskFloat,2,$decpoint,'');
$cashedNotInClosing = number_format($cashedNotInClosing,2,$decpoint,'');
echo json_encode (array("status" => "OK","msg" => array("cashindesk" => $cashInDesk,"unclosedsalesincash" => $cashedNotInClosing)));
}

private function getClosingOverview($pdo,$closingid) {
$decpoint = CommonUtils::getConfigValue($pdo, 'decpoint', ".");
$statusOfPaymentSql = "CASE WHEN B.status='x' THEN 'VERK' WHEN B.status is null THEN 'VERK' WHEN B.status='s' THEN 'BSTR' WHEN B.status='c' THEN 'EINL' END";

$sql = "SELECT REPLACE(SUM(B.brutto),'.','$decpoint') as brutto,REPLACE(round(sum(B.netto),2),'.','$decpoint') as netto,P.name,"
. "IF(B.status='x',null,B.status) as status, ";
$sql .= "($statusOfPaymentSql) as process ";
$sql .= " from %bill% B,%payment% P WHERE ";
$sql .= "B.closingid=? AND B.paymentid=P.id AND B.paymentid <> ? AND";
$sql .= "(B.status <> 's' OR B.status is null) ";
$sql .= "GROUP BY B.tax,P.name,IF(B.status='x',null,B.status) ";
$sql .= "HAVING SUM(B.brutto) is not null ";
$sql .= " UNION ALL ";
$sql .= "SELECT REPLACE(sum(B.brutto),'.','$decpoint') as brutto,REPLACE(round(sum(B.netto),2),'.','$decpoint') as netto,'',B.status, ";
$sql .= "($statusOfPaymentSql) as process ";
$sql .= " FROM %bill% B WHERE ";
$sql .= "B.closingid=? AND B.status='s' AND B.paymentid <> ? ";
$sql .= "HAVING sum(B.brutto) is not null ";
$sql .= " UNION ALL ";
$sql .= "SELECT REPLACE(sum(Q.price),'.','$decpoint') as brutto,REPLACE(TRUNCATE(sum(Q.price / (1 + Q.tax * 0.01)),2),'.','$decpoint') as netto,'','d' as status,'PSTR' as process from %queue% Q WHERE Q.toremove='1' AND Q.clsid=? ";
$sql .= "HAVING sum(Q.price) is not null ";

$overview = CommonUtils::fetchSqlAll($pdo, $sql, array($closingid,DbUtils::$PAYMENT_GUEST,$closingid,DbUtils::$PAYMENT_GUEST,$closingid));
return $overview;
}

private function getProdDetailsOfAClosing($pdo,$closingid) {
$decpoint = CommonUtils::getConfigValue($pdo, 'decpoint', ".");
$sql = "select count(PN.name) as count,PN.name as productname,REPLACE(Q.price,'.','$decpoint') as price,REPLACE(Q.tax,'.','$decpoint') as tax,REPLACE(sum(Q.price),'.','$decpoint') as sumprice ";
$sql .= " from %queue% Q,%prodnames% PN,%bill% B where ";
$sql .= "Q.billid=B.id AND Q.prodnameid=PN.id AND B.closingid=? AND B.paymentid <> ? AND B.paymentid <> ? AND ";
$sql .= "B.status is null "; 
$sql .= "group by PN.name,Q.tax,Q.price ";
$sql .= "ORDER BY count(PN.name) DESC,sum(Q.price) DESC";
return CommonUtils::fetchSqlAll($pdo, $sql, array($closingid,DbUtils::$PAYMENT_GUEST, DbUtils::$PAYMENT_HOTEL));
}

public function getClosingSummaryCore($closingid,$pdo,$fromWeb,$exportSignature,$replacedecpoint,$fl=0) {
if(is_null($pdo)) {
$pdo = DbUtils::openDbAndReturnPdoStatic();
};

$currency = CommonUtils::getConfigValue($pdo, 'currency', "Euro");
if ($replacedecpoint) {
$decpoint = CommonUtils::getConfigValue($pdo, 'decpoint', ".");
} else {
$decpoint = ".";
}

$masterData = self::getClosingMasterDataAtClsTime($pdo, $closingid);

$sql = "select id,closingdate,billcount,REPLACE(billsum,'.','$decpoint') as billsum,remark,signature,counting,REPLACE(counted,'.','$decpoint') as counted,"
. "COALESCE(REPLACE(saleswithoutcash,'.','$decpoint'),'?') as saleswithoutcash,"
. "counted as countedorig from %closing% where id=?";
$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
$stmt->execute(array($closingid));
$closingpart = $stmt->fetchObject();

$ok = self::checkForClosingConsistency($pdo, $closingid);
if (($ok == 0)) {
if ($fromWeb) {
echo json_encode(array("status" => "ERROR", "code" => ERROR_INCONSISTENT_DB, "msg" => ERROR_INCONSISTENT_DB_MSG));
return;
} else {
return null;
}
}

$barTotalAfterTEPointDecpoint = self::getBarTotalAfterTE($pdo,$closingid,".");
$barTotalBeforeTE = self::getBarTotalBeforeTE($pdo,$closingid,$decpoint);
$barTotalAfterTE = self::getBarTotalAfterTE($pdo,$closingid,$decpoint);
$diffSollIst = 0.0;
$counting = $closingpart->counting;
$counted = $closingpart->counted;
$countedWithPoint = $closingpart->countedorig;
if ($counting == 1) {
$sql = "SELECT id,value,count,iscoin,IF(iscoin='1','Münzen','Banknoten') as coinsornotes,";
$sql .= "REPLACE(ROUND((count*value*IF(iscoin='1',1,100))/100.0,2),'.','$decpoint') as sum,IF(iscoin='1',1,100) as factor ";
$sql .= "FROM %counting% WHERE clsid=? ORDER BY iscoin DESC,value";
$countingprotocol = CommonUtils::fetchSqlAll($pdo, $sql, array($closingid));
$diffSollIst = number_format($barTotalAfterTEPointDecpoint - $countedWithPoint,2,$decpoint,'');
} else {
$countingprotocol = array();
}


$overview = $this->getClosingOverview($pdo, $closingid);




$sql = "select REPLACE(%queue%.tax,'.','$decpoint') as t,REPLACE(SUM(%queue%.price),'.','$decpoint') as bruttosum,REPLACE(ROUND(SUM(%queue%.price)/(1 + %queue%.tax/100.0),2),'.','$decpoint') as nettosum ";
$sql .= " FROM %bill% B,%queue% ";
$sql .= " WHERE B.closingid=? AND %queue%.billid=B.id AND B.paymentid <> ? GROUP BY REPLACE(%queue%.tax,'.','$decpoint')";
$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
$stmt->execute(array($closingid,DbUtils::$PAYMENT_GUEST));
$taxessum = $stmt->fetchAll(PDO::FETCH_ASSOC);

$sql = "SELECT DISTINCT paymentid,name FROM %bill%,%payment% WHERE %bill%.closingid=? AND %bill%.paymentid=%payment%.id AND %bill%.paymentid <> ? ";
$payments = CommonUtils::fetchSqlAll($pdo, $sql, array($closingid, DbUtils::$PAYMENT_GUEST));
$paymenttaxes = array();
foreach($payments as $aPayment) {
if ($aPayment["paymentid"] != DbUtils::$PAYMENT_GUEST) {
$sql = "select REPLACE(%queue%.tax,'.','$decpoint') as t,REPLACE(SUM(%queue%.price),'.','$decpoint') as bruttosum,REPLACE(ROUND(SUM(%queue%.price)/(1 + %queue%.tax/100.0),2),'.','$decpoint') as nettosum ";
$sql .= " FROM %bill%,%queue% ";
$sql .= " WHERE %bill%.closingid=? AND %queue%.billid=%bill%.id AND %bill%.paymentid=? GROUP BY REPLACE(%queue%.tax,'.','$decpoint')";
$paymenttaxessum = CommonUtils::fetchSqlAll($pdo, $sql, array($closingid,$aPayment["paymentid"]));
if (count($paymenttaxessum) > 0) {
$paymenttaxes[] = array("payment" => $aPayment["name"],"paymenttaxessum" => $paymenttaxessum);
}
}
}

$details = $this->getProdDetailsOfAClosing($pdo,$closingid);
// -> returns something like this:

if (!$exportSignature || $fromWeb) {
unset($closingpart->signature);
}

$closshowci = CommonUtils::getConfigValue($pdo, 'closshowci', 1);
$closshowpaytaxes = CommonUtils::getConfigValue($pdo, 'closshowpaytaxes', 1);
$closshowprods = CommonUtils::getConfigValue($pdo, 'closshowprods', 1);
$companyinfo = CommonUtils::getConfigValueAtClosingTime($pdo, 'companyinfo', '', $closingid);
$retVal = array("closing" => $closingpart,
"overview" => $overview, 
"details" => $details, 
"taxessum" => $taxessum, 
"companyinfo" => $companyinfo, 
"paymenttaxessum" => $paymenttaxes,
"closshowci" => $closshowci,
"closshowpaytaxes" => $closshowpaytaxes,
"closshowprods" => $closshowprods,
"countingprotocol" => $countingprotocol,
"barTotalBeforeTE" => $barTotalBeforeTE,
"barTotalAfterTE" => $barTotalAfterTE,
"diffsollist" => $diffSollIst
);

$retVal["sn"] = CommonUtils::getConfigValueAtClosingTime($pdo, 'sn', "?", $closingid);
$retVal["uid"] = CommonUtils::getConfigValueAtClosingTime($pdo, 'uid', "?", $closingid);
$retVal["version"] = CommonUtils::getConfigValueAtClosingTime($pdo, 'version', "?", $closingid);
$retVal["systemid"] = CommonUtils::getConfigValueAtClosingTime($pdo, 'systemid', "?", $closingid);
$retVal["version"] = $masterData["version"];
$retVal["template"] = $masterData["template"];
$retVal["companyinfo"] = $masterData["companyinfo"];

$retVal["dsfinvk_name"] = $masterData["dsfinvk_name"];
$retVal["dsfinvk_street"] = $masterData["dsfinvk_street"];
$retVal["dsfinvk_postalcode"] = $masterData["dsfinvk_postalcode"];
$retVal["dsfinvk_city"] = $masterData["dsfinvk_city"];
$retVal["dsfinvk_country"] = $masterData["dsfinvk_country"];
$retVal["dsfinvk_stnr"] = $masterData["dsfinvk_stnr"];
$retVal["dsfinvk_ustid"] = $masterData["dsfinvk_ustid"];

$retVal["closingid"] = $closingid;
$retVal["billcount"] = $closingpart->billcount;
$retVal["billsum"] = str_replace('.',$decpoint,$closingpart->billsum) . " " . $currency;
$retVal["saleswithoutcash"] = str_replace('.',$decpoint,$closingpart->saleswithoutcash) . " " . $currency;
$retVal["closingdate"] = $closingpart->closingdate;
$retVal["remark"] = $closingpart->remark;
$retVal["counting"] = $counting;
$retVal["counted"] = $counted;

if ($fromWeb) {
echo json_encode(array("status" => "OK", "msg" => $retVal));
} else {
return $retVal;
}
}

private function htmlreport($closingid) {
$pdo = DbUtils::openDbAndReturnPdoStatic();
$ok = self::checkForClosingConsistency($pdo, $closingid);
if (($ok == 0)) {
echo "Datenbank inkonsistent";
return;
}

//$closing = $this->getClosingSummaryCore($closingid,$pdo,false,false,false,8);
$closing = $this->getASingleClosing($pdo, $closingid);
$clostemplate = CommonUtils::getConfigValue($pdo, "clostemplate", "Kein Template festgelegt");
$templatelines = Templateutils::getTemplateAsLineArray($clostemplate);
$reporter = new ClosTemplater($templatelines,$closingid,$closing);
$txt = $reporter->createWithTemplate($pdo);

$html = "<html>";
$html = "<head><meta charset='UTF-8'></head>";
$html .= "<body><style>";
$html .= "</style>";
$html .= $txt;
$version = CommonUtils::getConfigValue($pdo, "version", "");
$html .= "<br><br><hr><div style='text-align:right;font-style:italic;'>OrderSprinter $version</div>";
$html .= "</body></html>";

echo $html;
}

public static function createdCountedValuesForClosing($pdo) {
$sql = "SELECT id from %closing% ORDER BY id";
$result = CommonUtils::fetchSqlAll($pdo, $sql);
foreach($result as $r) {
$clsid = $r['id'];
$sqlNoCounting = "SELECT COALESCE(SUM(B.brutto),'0.00') as sumbrutto FROM %bill% B,%closing% C WHERE B.paymentid='1' AND C.id<=? AND C.id=B.closingid AND B.intguestid is null";
$sumbruttores = CommonUtils::fetchSqlAll($pdo, $sqlNoCounting, array($clsid));
CommonUtils::execSql($pdo, "UPDATE %closing% SET counted=? WHERE id=?", array($sumbruttores[0]["sumbrutto"],$clsid));
}
}

private static function signValueByTseAndUpdateClosing($pdo,$closingid,$valueToSign) {
$tseanswer = TSE::sendFreeContentToTSE($pdo, $valueToSign);
if ($tseanswer["status"] != "OK") {
return(array("status" => "ERROR","msg" => "TSE-Signierung fehlgeschlagen. Vorgang konnte nicht ausgeführt werden."));
} else {
$logtime = 0;
$trans = 0;
$tseSignature = '';
$pubkeyRef = null;
$sigalgRef = null;
$sigcounter = 0;
$serialNoRef = null;
$certificateRef = null;

if ($tseanswer["usetse"] == DbUtils::$TSE_OK) {
$logtime = $tseanswer["logtime"];
$trans = $tseanswer["trans"];
$sigcounter = $tseanswer["sigcounter"];
$tseSignature = $tseanswer["signature"];
$sigalgRef = CommonUtils::referenceValueInTseValuesTable($pdo, $tseanswer["sigalg"]);
$pubkeyRef = CommonUtils::referenceValueInTseValuesTable($pdo, $tseanswer["publickey"]);
$serialNoRef = CommonUtils::referenceValueInTseValuesTable($pdo, $tseanswer["serialno"]);
$certificateRef = CommonUtils::referenceValueInTseValuesTable($pdo, $tseanswer["certificate"]);
}

$opid = Operations::createOperation(
$pdo, 
DbUtils::$PROCESSTYPE_SONSTIGER_VORGANG,
DbUtils::$OPERATION_IN_CLOSING_TABLE,
$logtime,
$trans,
$valueToSign,
$tseSignature,
$pubkeyRef,
$sigalgRef,
$serialNoRef,
$certificateRef,
$sigcounter,
$tseanswer["usetse"]);

$sql = "UPDATE %closing% SET opid=? WHERE id=?";
CommonUtils::execSql($pdo, $sql, array($opid, $closingid));

return array("status" => "OK");
}
}
}