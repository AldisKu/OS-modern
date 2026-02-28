<?php
require_once (__DIR__. '/../dbutils.php');


class Tse extends ServerCom  {
	
	private static $rights = array(
	    "tsecmd" => array("loggedin" => 1, "isadmin" => 0, "rights" => null)
	);

	public static function handleCommand($command) {
		if (!CommonUtils::checkRights($command, self::$rights)) {
			return false;
		}

		$pdo = DbUtils::openDbAndReturnPdoStatic();
		switch ($command) {
			case 'tsecmd':
				self::tsecmd($pdo,null);
				break;
			default:
				echo json_encode(array("status" => "ERROR", "msg" => "Command not supported"));
				break;
		}
	}


	private static function csvToArray($csvStr) {
		$values = array();
		try {
			$parts = explode(",", $csvStr); 
			foreach($parts as $p) {
				$values[] = intval($p);
			}
		} catch (Exception $ex) {
		}
		return $values;
	}
	private static function getTseParams($pdo) {
		$pin = self::getPostArgOrDbData($pdo, 'tsepin', 'pin');
		$pinBytes = self::csvToArray($pin);
		$puk = self::getPostArgOrDbData($pdo, 'tsepuk', 'puk');
		$pukBytes = self::csvToArray($puk);
		$clientid = CommonUtils::getConfigValue($pdo, 'sn', '');
		
		return array(
		    "url" => self::getPostArgOrDbData($pdo, 'tseurl', 'url'),
		    "pass" => self::getPostArgOrDbData($pdo, 'tsepass', 'pass'),
		    "clientid" => $clientid,
		    "pin" => $pinBytes,
		    "puk" => $pukBytes
		);
	}
	private static function tsecmd($pdo,$request) {
		if (is_null($request)) {
			if (!isset($_POST['request'])) {
				echo json_encode(array("status" => "ERROR","msg" => "No TSE request transmitted"));
				return;
			}
			$request = $_POST['request'];
		}		
		
		$tseparams = self::getTseParams($pdo);
		
		$transferdata = array(
		    "pass" => $tseparams['pass'],
		    "pin" => $tseparams['pin'],
		    "puk" => $tseparams['puk'],
		    "clientid" => $tseparams['clientid'],
		    "cmd" => $request
		);

		if (($request == "setup") || ($request == "factory_reset")) {
			$hist = new HistFiller();
			$hist->updateConfigInHist($pdo, 'tsepin', implode(',',$tseparams['pin']));
			$hist->updateConfigInHist($pdo, 'tsepuk', implode(',',$tseparams['puk']));
		}
		
		$data = json_encode($transferdata);
		$transferdataBase64 = base64_encode($data);
		
		$expectInJson = true;
		if (($request == "exportdownload") || ($request == "exportdownloadlastyear")){
			$expectInJson = false;
		}
		$output = self::sendToTseConnector($tseparams['url'] . "/admin", $transferdataBase64,560,$expectInJson.self::$KEEP_DATA_TYPE);
		if (($request != "exportdownload") && ($request != "exportdownloadlastyear")) {
				echo json_encode($output);
		} else {
				ob_start();
				header("Pragma: public");
				header("Expires: 0");
				//header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
				header("Cache-Control: public");
				header("Content-Description: File Transfer");
				header("Content-type: application/x-tar");
				header("Content-Disposition: attachment; filename=\"tse-export.tar\"");
				header("Content-Transfer-Encoding: binary");
				header("Content-Length: ". strlen($output));

				echo $output;
				ob_end_flush();
				
		}
	}
	
	private static function sendToTseConnector(string $url, $data,int $timeout,bool $expectContentInJson) {
                $query = http_build_query(array("data" => $data));
                return self::sendToServer($url, $query, $timeout, $expectContentInJson,'application/x-www-form-urlencoded',false, 'POST',null,self::$KEEP_DATA_TYPE);
        }

	private static function sendValueToTseForSigning($pdo,$valueToSign,$cmd) {
		$useTse = CommonUtils::getConfigValue($pdo, 'usetse', 0);
		if ($useTse == DbUtils::$NO_TSE) {
			return array("status" => "OK","usetse" => DbUtils::$NO_TSE);
		} else if ($useTse == DbUtils::$TSE_KNOWN_ERROR) {
			return array("status" => "OK","usetse" => DbUtils::$TSE_KNOWN_ERROR);
		}
		$tseurl =  trim(CommonUtils::getConfigValue($pdo, 'tseurl', ''));
		if ($tseurl == "") {
			return array("status" => "OK","usetse" => DbUtils::$TSE_MISCONFIG);
		}

		$tseparams = self::getTseParams($pdo);
		
		$transferdata = array(
		    "pass" => $tseparams['pass'],
		    "pin" => $tseparams['pin'],
		    "clientid" => $tseparams['clientid'],
		    "cmd" => $cmd,
		    "value" => $valueToSign
		);

		$data = json_encode($transferdata);
		$transferdataBase64 = base64_encode($data);
		
                $timeStart = round(microtime(true) * 1000);
		$tseanswer = self::sendToTseConnector($tseurl . "/sign", $transferdataBase64,560,true,self::$KEEP_DATA_TYPE);
                $timeEnd = round(microtime(true) * 1000);
                $elapsedTime = $timeEnd - $timeStart;
                
                Performance::addPerformance($pdo, Performance::$TseSigning, false, $elapsedTime);
		if ($tseanswer["status"] == "OK") {
			$tseanswer["usetse"] = DbUtils::$TSE_OK;
                        UsedFeatures::noteUsedFeature($pdo, UsedFeatures::$Tse);
		} else {
			$tseanswer["usetse"] = DbUtils::$TSE_RUNTIME_ERROR;
		}
		return $tseanswer;
	}

	public static function sendNormalBillToTSE($pdo,$billValueToSign) {
		return self::sendValueToTseForSigning($pdo, $billValueToSign, "signnormalbill");
	}
	public static function sendOrdersToTSE($pdo,$prodEntriesToSign) {
		return self::sendValueToTseForSigning($pdo, $prodEntriesToSign, "signorders");
	}
	public static function sendFreeContentToTSE($pdo,$freeContent) {
		return self::sendValueToTseForSigning($pdo, $freeContent, "signfreecontent");
	}
	
	public static function checkTseServerAccesible($pdo) {
		$useTse = CommonUtils::getConfigValue($pdo, 'usetse', 0);
		$tseurl = CommonUtils::getConfigValue($pdo, 'tseurl', "");
		if (($useTse == 0) || ($tseurl == "")) {
			return array("status" => "OK");
		} else {
			$tseparams = self::getTseParams($pdo);
		
			$transferdata = array(
			    "pass" => $tseparams['pass'],
			    "pin" => $tseparams['pin'],
			    "clientid" => $tseparams['clientid'],
			    "cmd" => "check"
			);

			$data = json_encode($transferdata);
			$transferdataBase64 = base64_encode($data);
			
			try {
				$tseanswer = self::sendToTseConnector($tseurl . "/admin", $transferdataBase64,560,true,self::$KEEP_DATA_TYPE);
			} catch (Exception $ex) {
				$tseanswer["usetse"] = DbUtils::$TSE_RUNTIME_ERROR;
				$tseanswer["status"] = "ERROR";
			}
		
		
			if ($tseanswer["status"] == "OK") {
				$tseanswer["usetse"] = DbUtils::$TSE_OK;
			} else {
				$tseanswer["usetse"] = DbUtils::$TSE_RUNTIME_ERROR;
			}
			return $tseanswer;
		}
	}
	
	private static function getClientIP()
	{
		$ipaddress = 'UNKNOWN';
		$keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
		foreach ($keys as $k) {
			if (isset($_SERVER[$k]) && !empty($_SERVER[$k]) && filter_var($_SERVER[$k], FILTER_VALIDATE_IP)) {
				$ipaddress = $_SERVER[$k];
				break;
			}
		}
		return $ipaddress;
	}

}
