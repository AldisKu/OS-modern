<?php

class ServerCom {
        protected static $TRANSFORM_DATA_TO_JSON = true;
        protected static $KEEP_DATA_TYPE = false;
        
        protected static function getPostArgOrDbData($pdo,$dbconfigitem,$postarg) {
		$value = CommonUtils::getConfigValue($pdo, $dbconfigitem, '');
		if (isset($_POST[$postarg])) {
			$value = $_POST[$postarg];
		}
		return $value;
	}
        
        /**
         * Get the response code from the http response header instead of calling http_respond_code(), because other content
         * may have been output so that the code is not valid anymore
         */
        private static function getHttpResponseCodeFromHeader(array $headerLines):int {
                foreach($headerLines as $aLine) {
                        if (CommonUtils::startsWith($aLine, 'HTTP/')) {
                                $parts = explode(' ', $aLine);
                                return intval($parts[1]);
                        }
                }
                return (-1);
        }
        
        protected static function sendToServer($url, $data,$timeout,$expectContentInJson,string $contentType,bool $provideHttpRespCode, $method, $bearerToken,bool $transformDataToJson) {
                if ($transformDataToJson) {
                        $data = json_encode($data);
                }
                
                if (!is_null($data)) {
                        $header = "Content-Type: $contentType\r\n" .
                                "Content-Length: " . strlen($data) . "\r\n" .
                                "User-Agent:MyAgent/1.0\r\n";
                } else {
                        $header = "Content-Type: text/plain\r\n"
                                . "User-Agent:MyAgent/1.0\r\n";
                }
                if (!is_null($bearerToken)) {
                        $header .= "Authorization: Bearer $bearerToken\r\n";
                }
                
                if (!is_null($data)) {
                        $opts = array(
                            'http' => array(
                                'header' => $header,
                                'method' => $method,
                                'content' => $data,
                                'timeout' => $timeout,
                                'ignore_errors' => true
                            )
                        );
                } else {
                        $opts = array(
                            'http' => array(
                                'header' => $header,
                                'method' => $method,
                                'timeout' => $timeout,
                                'ignore_errors' => true
                            )
                        );
                }
		$context = stream_context_create($opts);

		try {
			$ret = file_get_contents($url, false, $context);
		} catch (Exception $ex) {
			return array("status" => "ERROR","msg" => "No connection with Server at $url. http-response: . " . json_encode($http_response_header) . " ,Message: " . $ex->getMessage());
		}
                
                $respHeader = isset($http_response_header) ? $http_response_header : [];
		if ($ret === false) {
			return array("status" => "ERROR","msg" => "No connection with Server at $url and http-response $respHeader");
		}
                
                
                $output = $ret;
                if ($expectContentInJson) {
                        $output = json_decode($ret, true);
                }
                
                if ($provideHttpRespCode) {
                        $output["httpresponsecode"] = self::getHttpResponseCodeFromHeader($respHeader);
                        $output["puremessage"] = $ret;
                }
                return $output;
	}
}
