<?php
require_once ('dbutils.php');
require_once ('utilities/Emailer.php');

class Rating {
var $dbutils;
function __construct() {
$this->dbutils = new DbUtils();
}

function handleCommand($command) {
if (!$this->isUserAlreadyLoggedInForPhpAndMayRate()) {
echo json_encode(array("status" => "ERROR", "code" => ERROR_RATE_NOT_AUTHOTRIZED, "msg" => ERROR_RATE_NOT_AUTHOTRIZED_MSG));
} else {
if ($command == 'doRate') {
$this->doRate($_POST['service'],$_POST['kitchen'],$_POST['remark'],$_POST['contact']);
}
else {
echo "Kommando nicht unterstuetzt.";
}
}
}


function isUserAlreadyLoggedInForPhpAndMayRate() {
if(session_id() == '') {
session_start();
}
if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
return false;
} else {
return ($_SESSION['right_rating']);
}
}

static function convertRatingToInt($rate) {
$rate = (string) $rate;
if (is_null($rate)) {
return null;
} else if ($rate == "0") {
return 0;
} else if ($rate == "good") {
return 1;
} else if ($rate == "ok") {
return 2;
} else if ($rate == "bad") {
return 3;
}
}

static function convertIntToRating($rate) {
if ($rate == 0) {
return "Keine Angabe";
} else if ($rate == 1) {
return "Gut";
} else if ($rate == 2) {
return "Na ja";
} else if ($rate == 3) {
return "Schlecht";
}
}

function doRate($service,$kitchen,$remark,$contact) {
date_default_timezone_set(DbUtils::getTimeZone());
$datetime = date('Y-m-d H:i:s');

$pdo = $this->dbutils->openDbAndReturnPdo();
$pdo->beginTransaction();

$service = self::convertRatingToInt($service);
$kitchen = self::convertRatingToInt($kitchen);

$sql = "INSERT INTO %ratings% (`id` , `date` , `service`,`kitchen`,`remark`) VALUES (NULL,?,?,?,?)";
$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
$stmt->execute(array($datetime,$service,$kitchen,$remark));

$pdo->commit();

$ok = true;
$emailbadrating = self::getGeneralItemFromDbWithPdo($pdo, "emailbadrating");
if ((($service == 3) || ($kitchen == 3)) && ($emailbadrating != "")) {
$ok = self::emailRatingInfo($pdo, $emailbadrating, $service, $kitchen, $remark, $contact, "Schlechte Bewertung\r\n");
}

$emailRatingContact = self::getGeneralItemFromDbWithPdo($pdo, "emailratingcontact");
if (!is_null($contact) && ($contact != "") && ($emailRatingContact != "")) {
$ok &= self::emailRatingInfo($pdo, $emailRatingContact, $service, $kitchen, $remark, $contact, "Bewertung - Kunde wünscht Kontakt!\r\n");
}

UsedFeatures::noteUsedFeature($pdo, UsedFeatures::$Rating);
if ($ok) {
echo json_encode(array("status" => "OK"));
} else {
echo json_encode(array("status" => "ERROR"));
}
}

private static function emailRatingInfo($pdo,$toEmail,$service,$kitchen,$remark,$contact,$topictxt) {
$from = self::getGeneralItemFromDbWithPdo($pdo, "email");
if (is_null($from) || ($from == "")) {
return false;
}
$emailbadrating = self::getGeneralItemFromDbWithPdo($pdo, "emailbadrating");
$emailratingcontact = self::getGeneralItemFromDbWithPdo($pdo, "emailratingcontact");


$msg = "Bewertung Service/Bedienung: " . self::convertIntToRating($service) . "\n";
$msg .= "Bewertung Küche: " . self::convertIntToRating($kitchen) . "\n";
$msg .= "Nachricht des Gastes: " . $remark . "\n";
$msg .= "Kontaktinformation des Gastes: " . $contact . "\n";
$msg = str_replace("\n", "\r\n", $msg);

return (Emailer::sendEmail($pdo, $msg, $toEmail, $topictxt));
}

static private function getGeneralItemFromDbWithPdo($pdo,$field) {
$aValue="";
$sql = "SELECT setting FROM %config% where name='$field'";
$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
$stmt->execute();
$row =$stmt->fetchObject();
if ($row != null) {
$aValue = $row->setting;
}
return $aValue;
}
}