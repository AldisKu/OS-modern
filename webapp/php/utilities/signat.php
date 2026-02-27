<?php
require_once (__DIR__. '/../dbutils.php');



class SignAT extends ServerCom {
	
        private const FISKALY_API_URL = "https://rksv.fiskaly.com/api/v1";
        private static $FISKALY_API_KEY_ITEM = 'fiskalyapikey';
        private static $FISKALY_API_SECRET_ITEM = 'fiskalyapisecret';
        
        private static $FON_PARTICIPANT_ID = 'fonparticipantid';
        private static $FON_USER_ID = 'fonuserid';
        private static $FON_USER_PIN = 'fonuserpin';
        
        private static $LEI_NUMBER = 'leinumber';
        private static $LEI_NAME = 'leiname';
        
        private static $FISKALY_JWSTOKEN_ITEM = 'fiskalyjwstoken';
        private static $FISKALY_TOKEN_EXPIRE_AT_ITEM = 'fiskalyjwsexpireat';
        
	private static $rights = array(
	    "signatcmd" => array("loggedin" => 1, "isadmin" => 0, "rights" => null),
            "exportdep7" => array("loggedin" => 1, "isadmin" => 0, "rights" => null)
	);

	public static function handleCommand($command) {
		if (!CommonUtils::checkRights($command, self::$rights)) {
			return false;
		}

		$pdo = DbUtils::openDbAndReturnPdoStatic();
		switch ($command) {
			case 'signatcmd':
				$retVal = self::signatcmd($pdo);
                                echo json_encode($retVal);
				break;
                        case 'exportdep7':
                                self::exportdep7();
                                break;
			default:
				echo json_encode(array("status" => "ERROR", "msg" => "Command not supported"));
				break;
		}
	}
        
        public static function signReceipt($pdo,$content):array {
                $jwsRequestTask = self::requestJwsTokenIfNearlyExpired($pdo);
                if ($jwsRequestTask['status'] != 'OK') {
                        return $jwsRequestTask;
                }
                $jwstoken = CommonUtils::getWorkValue($pdo, self::$FISKALY_JWSTOKEN_ITEM, '');
                
                $cashRegId = CommonUtils::getConfigValue($pdo, 'rksvcashregid', '');
                if ($cashRegId == '') {
                        return array('status' => 'ERROR','msg' => 'Registerkassen-ID wurde noch nicht erstellt');
                }
                $receiptId = self::genUUIDv4();
                
                $output = self::sendToServer(self::FISKALY_API_URL . "/cash-register/$cashRegId/receipt/$receiptId", $content, 5600, true, 'application/json; charset=utf-8', true, 'PUT', $jwstoken,self::$TRANSFORM_DATA_TO_JSON);
                $respCode = $output['httpresponsecode'];

                if ($respCode == '200') {
                        $fiskalySignResponse = new Fiskalysignresponse();
                        $fiskalySignResponse->createFromFiskalyOutput($output);
                        return array('status' => 'OK','msg' => $fiskalySignResponse);
                } else if ($respCode == '404') {
                        $status_code = $output['status_code'];
                        $error = $output['error'];
                        $code = $output['code'];
                        $message = $output['message'];
                        $completeMsg = "Signing by Fisfaly Sign-AT failed: status code=$status_code, error=$error,code=$code,message=$message";
                        error_log($completeMsg);
                        return array("status" => "ERROR","msg" => "Signierung fehlgeschlagen: " . $message);
                } else {
                        return array("status" => "ERROR","msg" => "Signierung fehlgeschlagen: " . json_encode($output));
                }
        }
        private static function updateFiskalyApiIfNeededFromPostData($pdo) {
                self::updateConfigValueFromPostDataIfNeeded($pdo, 'fiskalyapikey', self::$FISKALY_API_KEY_ITEM);
                self::updateConfigValueFromPostDataIfNeeded($pdo, 'fiskalyapisecret', self::$FISKALY_API_SECRET_ITEM);
        }
        
        private static function updateFonArgsIfNeededFromPostData($pdo):array {
                $fpid = self::updateConfigValueFromPostDataIfNeeded($pdo, 'fon_participant_id', self::$FON_PARTICIPANT_ID);
                $fuid = self::updateConfigValueFromPostDataIfNeeded($pdo, 'fon_user_id', self::$FON_USER_ID);
                $fup = self::updateConfigValueFromPostDataIfNeeded($pdo, 'fon_user_pin', self::$FON_USER_PIN);
                return array('fon_participant_id' => $fpid,'fon_user_id' => $fuid,'fon_user_pin' => $fup);
        }
        
        private static function updateRksvLeiIfNeededFromPostData($pdo):array {
                $lnumber = self::updateConfigValueFromPostDataIfNeeded($pdo, 'leinumber', self::$LEI_NUMBER);
                $lname = self::updateConfigValueFromPostDataIfNeeded($pdo, 'leiname', self::$LEI_NAME);
                return array('leinumber' => $lnumber,'leiname' => $lname);
        }
                
        private static function updateConfigValueFromPostDataIfNeeded($pdo,string $postItem,string $configItem):string{
                $val = '';
                if (isset($_POST[$postItem])) {
			$val = $_POST[$postItem];
		}
                CommonUtils::updateConfigValueIfNeeded($pdo, $configItem, $val);
                if (!is_string($val)) {
                        $val = strval($val);
                }
                return $val;
        }
        
        private static function requestJwsToken($pdo) {
                $fiskalyApiKey = CommonUtils::getConfigValue($pdo, self::$FISKALY_API_KEY_ITEM, '');
                $fiskalyApiSecret = CommonUtils::getConfigValue($pdo, self::$FISKALY_API_SECRET_ITEM, '');
                
                $transferdata = array(
                    "api_key" => $fiskalyApiKey,
                    "api_secret" => $fiskalyApiSecret
                );

                $output = self::sendToServer(self::FISKALY_API_URL . "/auth", $transferdata, 5600, true, 'application/json; charset=utf-8', true, 'POST',null,self::$TRANSFORM_DATA_TO_JSON);

                $respCode = $output['httpresponsecode'];
                if ($respCode == '200') {
                        CommonUtils::insertOrUpdateValueInWorkTable($pdo,self::$FISKALY_JWSTOKEN_ITEM,$output['access_token']);
                        CommonUtils::insertOrUpdateValueInWorkTable($pdo,self::$FISKALY_TOKEN_EXPIRE_AT_ITEM,gmdate("Y-m-d H:i:s", $output['access_token_expires_at']));
                        return array("status" => "OK", "msg" => $output);
                } else if ($respCode == '401') {
                        return array("status" => "ERROR", "msg" => "Keine Autentifizierung mit der Kombination aus API-Key und API-Secret möglich");
                } else {
                        return array("status" => "ERROR", "msg" => "Autentifizierung fehlgeschlagen: Code " . $respCode);
                }
        }
        
        private static function requestJwsTokenIfNearlyExpired($pdo):array {
                $needForCreation = false;
                $expireAt = CommonUtils::getWorkValue($pdo, self::$FISKALY_TOKEN_EXPIRE_AT_ITEM, '');
                if ($expireAt == '') {
                        $needForCreation = true;
                        error_log("JWSToken first creation");
                } else {
                        $now = time();
                        $expireAtTime = strtotime($expireAt);
                        $datediff = $expireAtTime - $now;
                        $diffInHours = round($datediff / (60 * 60));
                        if ($diffInHours < 2) {
                                $needForCreation = true;
                                error_log("JWSToken request because close to expiration in $diffInHours hours.");
                        }
                }
                $requestStatus = array("status" => "OK","msg" => "JWSToken check done");
                if ($needForCreation) {
                        $requestStatus = self::requestJwsToken($pdo);
                }
                return $requestStatus;
        }
        
        private static function authFonCore(array $transferdata,string $jwstoken):array {
                $output = self::sendToServer(self::FISKALY_API_URL . "/fon/auth", $transferdata, 5600, true, 'application/json; charset=utf-8', true, 'PUT', $jwstoken,self::$TRANSFORM_DATA_TO_JSON);
                $respCode = $output['httpresponsecode'];
                return array('output' => $output,'respcode' => $respCode);
        }
        
        private static function authFon($pdo):array {
                
                self::updateFiskalyApiIfNeededFromPostData($pdo);
                $jwstokenUpdateStatus = self::requestJwsTokenIfNearlyExpired($pdo);
                if ($jwstokenUpdateStatus['status'] != 'OK') {
                        return $jwstokenUpdateStatus;
                }
                
                $transferdata = self::updateFonArgsIfNeededFromPostData($pdo);
                $jwstoken = CommonUtils::getWorkValue($pdo, self::$FISKALY_JWSTOKEN_ITEM, '');
                
                $authFonTask = self::authFonCore($transferdata, $jwstoken);
                if ($authFonTask['respcode'] == '200') {
                        $hist = new HistFiller();
                        $hist->updateConfigInHist($pdo, 'fonauthstate', $authFonTask['output']["authentication_status"]);
                        return array("status" => "OK", "msg" => $authFonTask['output']["authentication_status"]);
                } else {
                        error_log("Authentifizierung fehlgeschlagen. Erfrage neuen JWSToken");
                        $requestStatus = self::requestJwsToken($pdo);
                        if ($requestStatus['status'] != 'OK') {
                                error_log("Neuer JWSToken konnte nicht erstellt werden. API-Zugang korrekt?");
                                return $requestStatus;
                        }
                        error_log("Erneuter Versuch der FON Authentifizierung mit neuem JWSToken");
                        $authFonTask = self::authFonCore($transferdata, $jwstoken);
                        return $authFonTask;
                }
        }
        
        private static function genUUIDv4():string {
                $data = random_bytes(16);
                $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
                $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
                return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
        }

        private static function createScuCore(string $scuid,array $leiData,string $jwstoken):array {
                $transferdata = array(
                    "legal_entity_id" => array("vat_id" => $leiData['leinumber']),
                    "legal_entity_name" => $leiData['leiname'],
                    "metadata" => array("system" => "OrderSprinter")
                );
                $output = self::sendToServer(self::FISKALY_API_URL . "/signature-creation-unit/$scuid", $transferdata, 5600, true, 'application/json; charset=utf-8', true, 'PUT', $jwstoken,self::$TRANSFORM_DATA_TO_JSON);
                $respCode = $output['httpresponsecode'];

                if ($respCode == '200') {
                        return array("status" => "OK", "msg" => array(
                            "state" => $output["state"],
                            "certificate_serial_number" => $output["certificate_serial_number"],
                            "uuidv4" => $scuid));
                } else {
                        error_log(json_encode($output));
                        return array("status" => "ERROR", "msg" => "Fehler bei SCU-Erstellung: Code $respCode, " . $output['message'] );
                }
        }
        private static function initializeScuCore(string $scuid,string $jwstoken):array {
                return self::requestStateRksvComponentCore($scuid, $jwstoken, "signature-creation-unit", "SCU konnte nicht initialisiert werden","INITIALIZED");
        }
        private static function registerCashRegCore(string $cashregid,string $jwstoken): array {
                return self::requestStateRksvComponentCore($cashregid, $jwstoken, "cash-register", "Registrierkasse konnte nicht initialisiert werden","REGISTERED");
        }
        private static function initializeCashRegCore(string $cashregid,string $jwstoken): array {
                return self::requestStateRksvComponentCore($cashregid, $jwstoken, "cash-register", "Registrierkasse konnte nicht initialisiert werden","INITIALIZED");
        }
        private static function requestStateRksvComponentCore(string $compId,string $jwstoken,string $apiFct,string $errorMsg,string $targetState):array {
                $transferdata = array(
                    "state" => $targetState
                );
                $output = self::sendToServer(self::FISKALY_API_URL . "/$apiFct/$compId", $transferdata, 5600, true, 'application/json; charset=utf-8', true, 'PATCH', $jwstoken, self::$TRANSFORM_DATA_TO_JSON);
                $respCode = $output['httpresponsecode'];
                if ($respCode == '200') {
                        return array("status" => "OK","msg" => $output);
                } else {
                        return array("status" => "ERROR","msg" => "$errorMsg: " . $output['message']);
                }
        }
        
        private static function createCashregister($pdo):array {
                $cashregid = self::genUUIDv4();
                self::updateFiskalyApiIfNeededFromPostData($pdo);
                $requestStatus = self::requestJwsToken($pdo);
                if ($requestStatus['status'] != "OK") {
                        return $requestStatus;
                }
                $jwstoken = CommonUtils::getWorkValue($pdo, self::$FISKALY_JWSTOKEN_ITEM, '');
                $transferData = array("description" => "OrderSprinter Cash register");
                $output = self::sendToServer(self::FISKALY_API_URL . "/cash-register/$cashregid", $transferData, 5600, true, 'application/json; charset=utf-8', true, 'PUT', $jwstoken,self::$TRANSFORM_DATA_TO_JSON);
                $respCode = $output['httpresponsecode'];

                if ($respCode == '200') {
                        $cashregstate = $output["state"];
                        $hist = new HistFiller();
                        $hist->updateConfigInHist($pdo, 'rksvcashregstate', $cashregstate);
                        $hist->updateConfigInHist($pdo, 'rksvcashregid', $cashregid);
                        return array("status" => "OK", "msg" => array(
                            "rksvcashregid" => $cashregid,
                            "rksvcashregstate" => $cashregstate)
                        );
                } else {
                        error_log(json_encode($output));
                        return array("status" => "ERROR", "msg" => "Fehler bei Registrierung der Registrierkasse: Code $respCode, " . $output['message'] );
                }
        }
        
        private static function createScu($pdo):array {
                
                $scuid = self::genUUIDv4();
                
                
                
                self::updateFiskalyApiIfNeededFromPostData($pdo);
                $requestStatus = self::requestJwsToken($pdo);
                if ($requestStatus['status'] != "OK") {
                        return $requestStatus;
                }
                $jwstoken = CommonUtils::getWorkValue($pdo, self::$FISKALY_JWSTOKEN_ITEM, '');
                $leiData = self::updateRksvLeiIfNeededFromPostData($pdo);
                
                $attemptsLeft = 5;
                $completed = false;
                while (!$completed && ($attemptsLeft > 0)) {
                        $creationTask = self::createScuCore($scuid, $leiData, $jwstoken);
                        if ($creationTask['status'] != "OK") {
                                return $creationTask;
                        }
                        $state = $creationTask['msg']['state'];
                        $certSN = $creationTask['msg']["certificate_serial_number"];
                        if ($state == "CREATED") {
                                $completed = true;
                                $hist = new HistFiller();
                                $hist->updateConfigInHist($pdo, 'rksvscuid', $scuid);
                                $hist->updateConfigInHist($pdo, 'rksvcertsn', $certSN);
                        } else {
                                error_log("SCU could not be created. Current state: $state. Waiting and retries left: $attemptsLeft.");
                                sleep(2);
                                $attemptsLeft--;
                        }       
                }
                if ($completed) {
                        return array("status" => "OK","msg" => $scuid,"scuid" => $scuid,"certsn" => $certSN);
                } else {
                        return array("status" => "ERROR","msg" => "SCU konnte nicht erstellt und registriert werden","scuid" => $scuid);
                }
        }
        
        private static function registerCashReg($pdo):array {
                return self::requestStateRksvComponent($pdo,"Regstrierkasse konnte nicht initialisiert werden.","registerCashRegCore","rksvcashregid",'rksvcashregstate','REGISTERED');
        }
        private static function initializeCashReg($pdo):array {
                return self::requestStateRksvComponent($pdo,"Regstrierkasse konnte nicht initialisiert werden.","initializeCashRegCore","rksvcashregid",'rksvcashregstate','INITIALIZED');
        }
        private static function initializeScu($pdo):array {
                return self::requestStateRksvComponent($pdo,"SCU konnte nicht initialisiert werden.","initializeScuCore","rksvscuid",'rksvscustate','INITIALIZED');
        }
        
        private static function requestStateRksvComponent($pdo,string $errorMsg,string $fctName,string $idConfigItem,string $configStateItem,string $targetState):array {
                // The rksvcompid (SCUID resp. CashRegID) must only be in the config, if a successful registration was possible!!!
                $rksvcompid = CommonUtils::getConfigValue($pdo, $idConfigItem, '');
                if ($rksvcompid == '') {
                        return array("status" => "ERROR","msg" => "Die SCU wurde nicht gesetzt.");
                }
                
                self::updateFiskalyApiIfNeededFromPostData($pdo);
                $requestStatus = self::requestJwsToken($pdo);
                if ($requestStatus['status'] != "OK") {
                        return $requestStatus;
                }
                $jwstoken = CommonUtils::getWorkValue($pdo, self::$FISKALY_JWSTOKEN_ITEM, '');
                 
                $attemptsLeft = 5;
                $completed = false;
                while (!$completed && ($attemptsLeft > 0)) {
                        $initializeTask = self::$fctName($rksvcompid, $jwstoken);
                        if ($initializeTask["status"] !== "OK") {
                                error_log($initializeTask['msg']);
                                return array("status" => "ERROR","msg" => $initializeTask['msg']);
                        } else {
                                $initTaskStatus = $initializeTask['msg']['state'];
                                $hist = new HistFiller();
                                $hist->updateConfigInHist($pdo, $configStateItem, $initTaskStatus);
                                if ($initTaskStatus == $targetState) {
                                        $completed = true;
                                        return array("status" => "OK", "msg" => array("state" => $targetState));
                                } else {
                                        error_log("$errorMsg. Current state: $initTaskStatus. Waiting and retries left: $attemptsLeft.");
                                        sleep(2);
                                        $attemptsLeft--;
                                }
                        }
                }
                return array("status" => "ERROR", "msg" => $errorMsg);
        }
        
        private static function decomCashReg($pdo, bool $filterAll):array {
                return self::decomRksvComponent($pdo, $filterAll,"cash-register","rksvcashregid","rksvcashregstate");
        }
        private static function decomScu($pdo, bool $filterAll):array {
                return self::decomRksvComponent($pdo, $filterAll,"signature-creation-unit","rksvscuid",'rksvcertsn');
        }
        
        private static function decomRksvComponent($pdo, bool $filterAll,string $apiFct,string $idConfigItem,?string $furtherConfigItemClearInErrorCond):array {
                self::updateFiskalyApiIfNeededFromPostData($pdo);
                $requestStatus = self::requestJwsToken($pdo);
                if ($requestStatus['status'] != "OK") {
                        return $requestStatus;
                }
                $jwstoken = CommonUtils::getWorkValue($pdo, self::$FISKALY_JWSTOKEN_ITEM, '');
           
                $output = self::sendToServer(self::FISKALY_API_URL . "/$apiFct", null, 5600, true, 'application/json; charset=utf-8', true, 'GET', $jwstoken,self::$KEEP_DATA_TYPE);
                $respCode = $output['httpresponsecode'];
                if ($respCode != '200') {
                        return array("status" => "ERROR","msg" => "Abmeldung unmöglich: " . $output['message']);     
                }
                $numberOfDecommedScu = 0;
                $scuData = $output["data"];
                
                $configuredScuid = CommonUtils::getConfigValue($pdo, $idConfigItem, '');
                $transferdata = array(
                        "state" => "DECOMMISSIONED"
                );
                foreach($scuData as $aScu) {
                        $scuId = $aScu["_id"];
                        if (($scuId != '') && (($aScu['state'] == "INITIALIZED") || ($aScu['state'] == "OUTAGE"))) {
                                if ($filterAll || ($scuId == $configuredScuid)) {
                                        error_log("RKSV Component ID to decom: $scuId");
                                        $output = self::sendToServer(self::FISKALY_API_URL . "/$apiFct/$scuId", $transferdata, 5600, true, 'application/json; charset=utf-8', true, 'PATCH', $jwstoken,self::$TRANSFORM_DATA_TO_JSON);
                                        $respCode = $output['httpresponsecode'];
                                        if ($respCode == '200') {
                                                $numberOfDecommedScu++;
                                                if ($scuId == $configuredScuid) {
                                                        $hist = new HistFiller();
                                                        $hist->updateConfigInHist($pdo, $idConfigItem, '');
                                                        if (!is_null($furtherConfigItemClearInErrorCond)) {
                                                                $hist->updateConfigInHist($pdo, $furtherConfigItemClearInErrorCond, '');
                                                        }
                                                }
                                        } else {
                                                error_log("Error while decom a rksvc component with id $scuId: " . $output['message']);
                                        }
                                }  
                        }
                }
                
                if (!$filterAll && ($numberOfDecommedScu < 1)) {
                        return array("status" => "ERROR","msg" => "Keine Abmeldung möglich: " . $output['message']);
                } else {
                        return array("status" => "OK","msg" => "Anzahl decomm. RKSV-Komponenten: $numberOfDecommedScu");
                }
        }
        
        private static function exportdep7() {
                try {
                        $pdo = DbUtils::openDbAndReturnPdoStatic();
                        $jwsRequestTask = self::requestJwsTokenIfNearlyExpired($pdo);
                        if ($jwsRequestTask['status'] != 'OK') {
                                return $jwsRequestTask;
                        }
                        $jwstoken = CommonUtils::getWorkValue($pdo, self::$FISKALY_JWSTOKEN_ITEM, '');

                        $cashRegId = CommonUtils::getConfigValue($pdo, 'rksvcashregid', '');
                        if ($cashRegId == '') {
                                return array('status' => 'ERROR','msg' => 'Registerkassen-ID wurde noch nicht erstellt');
                        }
                
                        $startReceipt = 0;
                        $sql = "SELECT COALESCE(MAX(fiskalyreceiptnumber),0) as maxrecno FROM %bill%";
                        $res = CommonUtils::fetchSqlAll($pdo, $sql, null);
                        if (count($res) == 1) {
                                $endReceipt  = intval($res[0]['maxrecno']);
                        } else {
                                $endReceipt = 0;
                        }

                        $output = self::sendToServer(self::FISKALY_API_URL . "/cash-register/$cashRegId/export", null, 10000, true, 'application/json; charset=utf-8', true, 'GET', $jwstoken,self::$KEEP_DATA_TYPE);
                        $respCode = $output['httpresponsecode'];

                        if ($respCode == '200') {
                                ob_start();
                                header("Pragma: public");
                                header("Expires: 0");
                                //header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                                header("Cache-Control: public");
                                header("Content-Description: File Transfer");
                                header("Content-type: application/json");
                                header("Content-Disposition: attachment; filename=\"dep7-export.json\"");
                                header("Content-Transfer-Encoding: binary");
                                header("Content-Length: ". strlen($output['puremessage']));

                                echo $output['puremessage'];
                                ob_end_flush();
                        } else {
                                error_log(json_encode($output));
                                return array("status" => "ERROR", "msg" => "Fehler beim Export - siehebserver-Logs!");
                        }
                } catch (Exception $ex) {
                        error_log("Error whole export DEP7: " . $ex->getMessage());
                }
        }
        private static function signatcmd($pdo) {
                try {
                        if (!isset($_POST['request'])) {
                                echo json_encode(array("status" => "ERROR","msg" => "No Sign-AT request transmitted"));
                                return;
                        }
                        $request = $_POST['request'];

                        $filterAll = false;
                        if (isset($_POST['filter'])) {
                                $filter = $_POST['filter'];
                                if ($filter == 'all') {
                                        $filterAll = true;
                                }
                        }
                                        
                        switch($request) {
                                case "authapi":
                                        return self::authApi($pdo);
                                        break;
                                case "authfon":
                                        return self::authFon($pdo);
                                        break;
                                case "createrksvscuid":
                                        return self::createScu($pdo);
                                        break;
                                case "initializerksvscu":
                                        return self::initializeScu($pdo);
                                        break;
                                case "registerrksvcashreg":
                                        return self::registerCashReg($pdo);
                                        break;
                                case "initializerksvcashreg":
                                        return self::initializeCashReg($pdo);
                                        break;
                                case "createrksvcashregid":
                                        return self::createCashregister($pdo);
                                        break;
                                case "decomscu":
                                        return self::decomScu($pdo,$filterAll);
                                        break;
                                case "decomcashreg":
                                        return self::decomCashReg($pdo,$filterAll);
                                        break;
                                case "exportdep7":
                                        return self::exportdep7($pdo);
                                        break;
                        }
                } catch (Exception $ex) {
                        return array("status" => "ERROR","msg" => $ex->getMessage());
                }
        }
}
