<?php

class Influxer {
        // https://docs.influxdata.com/influxdb/v2.7/get-started/write/?t=InfluxDB+API write data and line protocol
        
        private static $translateStr = array("unclear" => "unklar","yes" => "ja","no" => "nein");
        
        private static function getWaitQueueLength($pdo): int {
                $influxupdfreq = intval(CommonUtils::getConfigValue($pdo, 'influxupdfreq', '0'));
                return $influxupdfreq * 10;
        }
        
        public function addInfoToInflux($pdo) {
                
                
                $influxSyncWaitQueue = self::getWaitQueueLength($pdo);
                
                $perftoinflux = CommonUtils::getConfigValue($pdo, 'perftoinflux', 0);
                $salestoinflux = CommonUtils::getConfigValue($pdo, 'salestoinflux', 0);
                if (($perftoinflux == 0) || ($salestoinflux == 0)) {
                        return array("status" => "OK","msg" => "");
                }
                
                $sql = "SELECT count(id) as countid from %work% WHERE item='influxsyncneeded'";
                $r = CommonUtils::fetchSqlAll($pdo, $sql);
                if ($r[0]['countid'] == 0) {
                        $sql = "INSERT INTO %work% (item,value,signature) VALUES(?,?,?)";
                        CommonUtils::execSql($pdo, $sql, array('influxsyncneeded',$influxSyncWaitQueue,null));
                }
                $sql = "SELECT value from %work% WHERE item='influxsyncneeded'";
                $r = CommonUtils::fetchSqlAll($pdo, $sql);
                $freeShots = intval($r[0]['value']);
                
                $sql = "UPDATE %work% SET value=? WHERE item=?";
                if ($freeShots <= 0) {
                        CommonUtils::execSql($pdo, $sql, array($influxSyncWaitQueue,'influxsyncneeded'));
                        return $this->syncWithInflux($pdo, $perftoinflux, $salestoinflux);
                } else {
                        CommonUtils::execSql($pdo, $sql, array($freeShots-1,'influxsyncneeded'));
                }
        }
        
        private function syncWithInflux($pdo,$perftoinflux,$salestoinflux):array {
                
                
                $baseInfluxdbUrl = CommonUtils::getConfigValue($pdo, 'influxurl', '');
                $influxorg = CommonUtils::getConfigValue($pdo, 'influxorg', '');
                $influxbucket = CommonUtils::getConfigValue($pdo, 'influxbucket', '');
                $authToken = CommonUtils::getConfigValue($pdo, 'influxtoken', '');
                
                $influxperflabel = CommonUtils::getConfigValue($pdo, 'influxperflabel', 'perf');
                $influxtablelabel = CommonUtils::getConfigValue($pdo, 'influxtablelabel', 'tables');
                $influxsoldlabel = CommonUtils::getConfigValue($pdo, 'influxsoldlabel', 'sold');
                $influxsaleslabel = CommonUtils::getConfigValue($pdo, 'influxsaleslabel', 'sales');
                
                if (is_null($baseInfluxdbUrl) || (trim($baseInfluxdbUrl) == '')) {
			return array("status" => "OK","msg" => "");
		}
                
                $influxApiUrl = "$baseInfluxdbUrl/api/v2/write";
                $query = "org=$influxorg&bucket=$influxbucket&precision=s";
                $completeUrl = $influxApiUrl . "?" . $query;
                
                $headerArr = array("Authorization: Token $authToken", 
                     "Content-Type: text/plain; charset=utf-8",
                     "Accept: application/json");
                
                $perfStr = "";
                if ($perftoinflux == 1) {
                        $sqlTask = Performance::getTaskNameAsSql();
                        $sqlTse = "(CASE WHEN tse='0' THEN 'nein' WHEN tse='1' THEN 'ja' ELSE 'Fehler' END) as usetse";
                        $sqlIsClient = "(CASE WHEN isclientaction='0' THEN 'nein' WHEN isclientaction='1' THEN 'ja' ELSE '' END) as isclientaction";
                        $sqlIsLocalhost = "(CASE WHEN islocalhost='1' THEN 'ja' WHEN islocalhost='2' THEN 'nein' ELSE '' END) as islocalhost";
                        $sql = "SELECT UNIX_TIMESTAMP(perfdatetime) as perfdatetime,$sqlTask,$sqlTse,$sqlIsClient,$sqlIsLocalhost,duration FROM %performance% WHERE issenttoinflux='0' AND numberofsamples = '1'";
                        $perfresult = CommonUtils::fetchSqlAll($pdo, $sql);

                        foreach($perfresult as $perfline) {
                                $taskname = str_replace(" ", "\ ", $perfline["taskname"]);
                                $aLine = "$influxperflabel,";
                                $aLine .= "task=" . $taskname . ",";
                                $aLine .= "isLocalhost=" . $perfline["islocalhost"] . ",";
                                $aLine .= "isTseUsed=" . $perfline["usetse"] . ",";
                                $aLine .= "isClientAction=" . $perfline["isclientaction"] . " ";
                                $aLine .= "duration=" . $perfline["duration"] . "i ";
                                $aLine .= $perfline["perfdatetime"];
                                $perfStr .= $aLine . "\n";
                        }
                }
                
                $salesStr = "";
                if ($salestoinflux == 1) {
                        $reports = new Reports();
			$stat = $reports->getStatsCore($pdo,true);
                        $todaysales = $stat["today"]["max"];
                        $aLine = "$influxsaleslabel,item=einnahme ";
                        $aLine .= "sum=" . $todaysales . " ";
                        $aLine .= time() . "\n";
                        
                        $prodcount = $stat["prodscount"];
                        foreach($prodcount as $aCount) {
                                $longname = str_replace(" ", "\ ", $aCount["longname"]);
                                $soldcount = $aCount["value"];
                                $aLine .= "$influxsoldlabel,artikel=$longname anzahl=" . $soldcount . "i " . time() . "\n"; 
                        }
                        $salesStr .= $aLine;
                        
                        $tablesInfo = $stat["tables"];
                        $openTablesCount = $tablesInfo["opentables"];
                        $openTablesSum = $tablesInfo["sum"];
                        $salesStr .= "$influxtablelabel,item=opentables numberofopentables=" . $openTablesCount . "i,opensum=$openTablesSum " . time() . "\n";
                }
                
                $transferdata = $perfStr . $salesStr;
                
                $status = $this->sendToInfluxDbViaCurl($completeUrl, $transferdata, $headerArr);
                
                if ($status && ($perftoinflux)) {
                        CommonUtils::execSql($pdo, "UPDATE %performance% SET issenttoinflux='1'", null);
                }
                if ($status) {
                        return array("status" => "OK","msg" => "");
                } else {
                        return array("status" => "ERROR","msg" => "Kommunikation mit InfluxDB fehlgeschlagen");
                }
        }
        
        private function cURLcheckBasicFunctions() {

                if (!function_exists("curl_init") &&
                        !function_exists("curl_setopt") &&
                        !function_exists("curl_exec") &&
                        !function_exists("curl_close"))
                        return false;
                else
                        return true;
        }

        private function sendToInfluxDbViaCurl(string $url, string $data, array $headerArr): bool {
                if (!$this->cURLcheckBasicFunctions()) {
                        error_log("curl not available");
                        return false;
                }

                $ch = curl_init();
                if ($ch) {
                        if (!curl_setopt($ch, CURLOPT_URL, $url)) {
                                curl_close($ch); 
                                error_log("curl cannot setup url");
                                return false;
                        }
                        
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1 );
                        curl_setopt($ch, CURLOPT_POST,           1 );
                        curl_setopt($ch, CURLOPT_POSTFIELDS,     $data ); 
                        curl_setopt($ch, CURLOPT_HTTPHEADER,     $headerArr); 

                        $transferStatus = true;
                        if(curl_exec($ch) === false) {
                                error_log('Curl error: ' . curl_error($ch));
                                $transferStatus = false;
                        } else {
                                $transferStatus = true;
                        }

                        
                        $i=0;
                        curl_close($ch);
                        return $transferStatus;
                } else {
                        return false;
                }
        }
}