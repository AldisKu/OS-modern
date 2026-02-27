<?php

require_once (__DIR__ . '/../dbutils.php');
require_once (__DIR__ . '/../commonutils.php');
require_once (__DIR__ . '/../roomtables.php');

class SyncerBase {
        public function checkaccess($url,$code,$codename) {
                if (is_null($url)) {
			return array("status" => "ERROR","msg" => "Keine URL angegeben");
		} else {
			$url = trim($url);
			if ($url == "") {
				return array("status" => "ERROR","msg" => "Keine URL angegeben");
			}
		}
                $code = trim($code);
		
		if ($code == '') {
			return array("status" => "ERROR","msg" => "Zugangscode nicht gesetzt!");
		}
                $transferdata = array(
                    $codename => $code,
                    "test" => "1"
		);

		$data = json_encode($transferdata);
		$transferdataBase64 = base64_encode($data);

		return $this->sendToWebsite($url, $transferdataBase64);
        }

        protected function sendToWebsite($url, $data) {
		
		$url .= "/sync.php";
		
		$query = http_build_query(array("data" => $data));

		$opts = array(
		    'http' => array(
			'header' => 
			"Content-Type: application/x-www-form-urlencoded\r\n" .
			"Content-Length: " . strlen($query) . "\r\n" .
			"User-Agent:MyAgent/1.0\r\n",
			'method' => 'POST',
			'content' => $query
		    )
		);

		$context = stream_context_create($opts);

		$retContent = @file_get_contents($url, false, $context);

                if (!$retContent) {
                        error_log("Component $url does not answer");
                }

		if (!$retContent) {
			$ret = array("status" => "ERROR","msg" => "Communication with website not successful!");
		} else {
                        if (gettype($retContent) == 'string') {
                                $ret = json_decode($retContent,true);
                        } else {
                                $ret = $retContent;
                        }
                }

                return $ret;
	}
}