<?php

require_once (__DIR__ . '/influxer.php');

class Performance {
        public static $NO_ASK = 0;
        public static $NO = 1;
        public static $YES = 2;
        public static $NO_MEASUREMENT = 3;
        
        public static $OrderClientMeasurement = array("id" => 1,"isclientaction" => 1,"name" => "Bestellvorgang Client");
        public static $TseSigning = array("id" => 2,"isclientaction" => 0,"name" => "Signierung durch TSE");
        public static $ShowAllRooms = array("id" => 3,"isclientaction" => 1,"name" => "Tischinfo über alle Räume");
        public static $GetEntriesToCook = array("id" => 4,"isclientaction" => 1,"name" => "Zuzubereitende Artikel abfragen");
        public static $GetCookedEntries = array("id" => 5,"isclientaction" => 1,"name" => "Zubereitete Artikel abfragen");
        
        public static $IS_LOCALHOST = array("unclear" => 0,"yes" => 1,"no" => 2);
                
        public static function addPerformance($pdo,$task,$checkForServerConnection,$elapsedtime) {
                $doMeasurement = self::isMeasurePerf($pdo);
                if ($doMeasurement) {
                        $islocalhostStr = "unclear";
                        if ($checkForServerConnection) {
                                if (isset($_SERVER['HTTP_HOST'])) {
                                      if ($_SERVER['HTTP_HOST'] == 'localhost') {
                                              $islocalhostStr = "yes";
                                      } else {
                                              $islocalhostStr = "no";
                                      }
                                }
                        }
                        $islocalhost = Performance::$IS_LOCALHOST[$islocalhostStr];

                        if ($elapsedtime >= 600000000) {
                                error_log("Ignored performance value - probably a measurement error, duration: " . $elapsedtime);
                                return;
                        }

                        try {
                                $usetse = CommonUtils::getConfigValue($pdo, 'usetse', 0);
                                $sql = "INSERT INTO %performance% (perfdate,perfdatetime,task,queuesize,tse,numberofsamples,isclientaction,islocalhost,duration,issenttoinflux) VALUES(DATE(NOW()),NOW(),?,(SELECT COUNT(id) FROM %queue%),?,'1',?,?,?,?)";
                                CommonUtils::execSql($pdo, $sql, array($task["id"],$usetse,$task["isclientaction"],$islocalhost,$elapsedtime,0));
                        } catch (Exception $ex) {
                                error_log("Insert performance value '$elapsedtime' went wrong: " . $ex->getMessage());
                        }
                        try {
                                (new Influxer())->addInfoToInflux($pdo);
                        } catch (Exception $ex) {
                                error_log("Insert value into InfluxDb failed task=$task with exception " . $ex->getMessage());
                        }
                }
        }
        
        private static function isMeasurePerf($pdo) {
                $doPerfMeasurements = CommonUtils::getConfigValue($pdo, 'publishperformance', self::$NO_MEASUREMENT);
                if ($doPerfMeasurements != self::$NO_MEASUREMENT) {
                        return true;
                } else {
                        return false;
                }
        }
        
        public static function averageOnAllMeasurements($pdo) {
                
                $doMeasurement = self::isMeasurePerf($pdo);
                if ($doMeasurement) {
                        $sql = "SELECT id,perfdate,task,MAX(queuesize) maxqueuesize,tse,isclientaction,islocalhost,SUM(numberofsamples) as thecount,round(SUM(duration * numberofsamples)/SUM(numberofsamples)) as duration "
                                . " FROM %performance% "
                                . " WHERE perfdate BETWEEN DATE_SUB(NOW(), INTERVAL 7 DAY) AND NOW() "
                                . " GROUP BY perfdate,task,tse,isclientaction,islocalhost";
                        $result = CommonUtils::fetchSqlAll($pdo, $sql);
                        CommonUtils::execSql($pdo, "DELETE FROM %performance%", null);
                        $sql = "INSERT INTO %performance% (perfdate,perfdatetime,task,queuesize,tse,isclientaction,islocalhost,numberofsamples,duration,issenttoinflux) VALUES(?,NOW(),?,?,?,?,?,?,?,?)";
                        foreach ($result as $r) {
                                CommonUtils::execSql($pdo, $sql, array($r['perfdate'],$r['task'],$r['maxqueuesize'],$r['tse'],$r['isclientaction'],$r['islocalhost'],$r['thecount'],$r['duration'],1));
                        }
                }
        }
        
        public static function getPerformance($pdo, $task,$doForLocalhost,$doForTse) {
                $perfData = self::getPerformanceForDisplay($pdo, $task, $doForLocalhost, $doForTse);
                return array("status" => "OK","msg" => $perfData);
        }
        
        public static function getTaskNameAsSql() {
                $tasks = self::getTaskArray();
                $sqlTask = "(CASE ";
                foreach($tasks as $t) {
                        $taskid = $t['id'];
                        $taskname = $t['name'];
                        $sqlTask .= " WHEN task='$taskid' THEN '$taskname' ";
                }
                $sqlTask .= "END) as taskname";
                return $sqlTask;
        }
        
        public static function getPurePerfData($pdo,$limit) {
                $sqlTask = self::getTaskNameAsSql();

                $localhostCase = "(CASE WHEN islocalhost='1' THEN 'JA' WHEN islocalhost='2' THEN 'NEIN' ELSE '?' END) as islocalhost";
                $tseCase = "(CASE WHEN tse='0' THEN 'NEIN' WHEN tse='1' THEN 'JA' ELSE '?' END) as tse";
                $clientCase = "(CASE WHEN isclientaction='0' THEN 'NEIN' WHEN isclientaction='1' THEN 'JA' ELSE '?' END) as isclientaction";
                
                $limitStr = "";
                if (!is_null($limit) && is_numeric($limit)) {
                        $limitStr = " DESC LIMIT $limit";
                }
                $sql = "SELECT perfdate,task,$sqlTask,queuesize,$tseCase,numberofsamples,$clientCase,$localhostCase,duration "
                        . " FROM %performance% "
                        . " ORDER BY perfdate $limitStr";
                return CommonUtils::fetchSqlAll($pdo, $sql,null);
        }
        public static function downloadCsvData($pdo) {
                $doMeasurement = self::isMeasurePerf($pdo);
                if (!$doMeasurement) {
                        echo "Keine Daten";
                        return;
                }
                header("Content-type: text/x-csv");
                header("Content-Disposition: attachment; filename=\"ordersprinter-perfdata.csv\"");
                header("Cache-Control: max-age=0");

                $result = self::getPurePerfData($pdo,null);
                echo "Datum Messungen;Task-ID;Taskname;Queuesize;TSE aktiv;Client-Aktion;Localhost-Aufruf;Dauer\n";
                foreach($result as $r) {
                        // TODO: name des Task ersetzen!
                        echo $r['perfdate'] . ";" . $r['task'] . ";" . $r['taskname'] . ";" . $r['queuesize'] . ";" . $r['tse'] . ";" . $r['isclientaction'];
                        echo ";" . $r['islocalhost'] . ";" . $r['duration'] . "\n";
                }
        }
        
        private static function getTaskArray() {
                $tasks = array(self::$OrderClientMeasurement,self::$TseSigning,self::$ShowAllRooms,self::$GetEntriesToCook,self::$GetCookedEntries);
                return $tasks;
        }
        
        private static function getPerformanceForDisplay($pdo,$task,$doForLocalhost,$doForTse) {
                $doMeasurement = self::isMeasurePerf($pdo);
                $tasks = self::getTaskArray();
                if (!$doMeasurement) {
                        return array("max" => "0.0","content" => array(),"tasks" => $tasks);
                }

                $forLocalhost = self::$IS_LOCALHOST['yes'];
                if ($doForLocalhost == 0) {
                        $forLocalhost = self::$IS_LOCALHOST['no'];
                }
                $sql = "SELECT id,perfdate as iter,task,MAX(queuesize) maxqueuesize,"
                        . "round(SUM(duration * numberofsamples)/SUM(numberofsamples)) as sum FROM %performance% "
                        . "WHERE task=? AND (islocalhost=? OR islocalhost=?) AND tse=? GROUP BY perfdate ORDER BY perfdate";
                $result = CommonUtils::fetchSqlAll($pdo, $sql,array($task,$forLocalhost,self::$IS_LOCALHOST['unclear'],$doForTse));
                
                $maxDur = 0;
                $minDur = PHP_INT_MAX;
                if (count($result) > 0) {
                        $minDate = $result[0]['iter'];
                        $maxDate = $result[count($result) - 1]['iter'];
                        $measurements = count($result);
                        $minDur = PHP_INT_MAX;
                        $maxDur = 0;
                        foreach($result as $r) {
                                $dur = $r['sum'];
                                if ($minDur > $dur) {
                                        $minDur = $dur;
                                }
                                if ($maxDur < $dur) {
                                        $maxDur = $dur;
                                }
                        }
                        
                        return array("minDate" => $minDate,"maxDate" => $maxDate, "measurements" => $measurements, "minDur" => $minDur, "max" => $maxDur, "content" => $result, "tasks" => $tasks);
                } else {
                        return array("max" => "0.0","content" => array(),"tasks" => $tasks);
                }
        }
}