<?php

class Backuprestore {
        public static $NATIVE_BACKUP_EXTENSION = 'osb';
        
        private static $PREF_VERSION = 'v';
        private static $PREF_ENTRY = 'e';
        private static $PREF_TABLE_START = 't';
        private static $PREF_DATATYPES = 'd';
        private static $PREF_COLNAMES = 'c';
        
        private static $chunkSizes = array("queue" => 10,"bill" => 10, "billproducts" => 20,"operations" => 5,"closing" => 5,"prodtype" => 5, "hist" => 10);
        private static $currentChunk = 0;
        private static $currentSql = '';
        private static $queuedQuestValuesToInsert = array();
        private static $queuedPureValuesToInsert = array();
        
        public static function restore($pdo, $filename) {
                $basedb = new Basedb();
                $basedb->setPrefix(TAB_PREFIX);
                $basedb->setTimeZone(DbUtils::getTimeZone());

                $bakVersion = self::getVersionOfBackup($filename);
		
		Version::createTablesAndUpdateUntilVersion($pdo, $basedb, $bakVersion);
                
                CommonUtils::execSql($pdo, "SET foreign_key_checks = 0", null);
                
                $typeIsOnlyConfig = true;
                
                $currentTable = '';
                $tablename = '';
                $cols = null;
                $datatypes = null;
                $file = fopen($filename, "r");
                try {
                        while (!feof($file)) {
                                $line = self::cleanFromLFCR(fgets($file));
                                if (strpos($line, self::$PREF_TABLE_START . ":") === 0) {
                                        $currentTable = self::getValueOfLine($line);
                                        $cols = null;
                                        $datatypes = null;
                                        self::flushTableInserts($pdo);
                                        self::markEndOfTableForImport();
                                        
                                        if ($currentTable == "queue") {
                                                $typeIsOnlyConfig = false;
                                        }
                                        error_log("Table to import: " . $currentTable);
                                        $tablename = "`%" . $currentTable . "%`";

                                        $sql = "DELETE FROM $tablename";
                                        CommonUtils::execSql($pdo, $sql, null);
                                } else if (strpos($line, self::$PREF_COLNAMES . ":") === 0) {
                                        $cols = explode(';',self::getValueOfLine($line));
                                } else if (strpos($line, self::$PREF_DATATYPES . ":") === 0) {
                                        $datatypes = explode(';',self::getValueOfLine($line));
                                        
                                        $fieldsAndTypes = array();
                                        for ($i=0;$i<count($cols);$i++) {
                                                $fieldsAndTypes[] = array("colname" => $cols[$i],"datatype" => $datatypes[$i]);
                                        }
                                        $colsInSql = implode(',',$cols);
                                        self::$currentSql = "INSERT INTO $tablename ($colsInSql) VALUES ";
                                } else if (strpos($line, self::$PREF_ENTRY . ":") === 0) {
                                        self::queueInsertEntry($pdo,$currentTable, $fieldsAndTypes, self::getValueOfLine($line));
                                }
                        }
                        self::flushTableInserts($pdo);
                        self::markEndOfTableForImport();
                } catch (Exception $ex) {
                        error_log("Exception: " . $ex->getMessage());
                        echo json_encode(array("status" => "ERROR","msg" => "Backupfile hat falschen Inhalt: " . $ex->getMessage()));
			exit();
                }

                fclose($file);
                
                if (!$typeIsOnlyConfig) {
			HistFiller::insertRestoreHistEntry($pdo);
		}

		$basedb->signLastBillid($pdo);

                CommonUtils::execSql($pdo, "SET foreign_key_checks = 1", null);
		
		Version::completeImportProcess($pdo);
        }
        
        private static function queueInsertEntry($pdo,$tableWithoutPrefix,$fieldsAndTypes,$entry) {
                $clearEntry = json_decode($entry, true);
                $clearData = self::getDataTypesNotBased64();
                
                $valArr = array();
                foreach($fieldsAndTypes as $f) {
                        $dataType = $f['datatype'];
                        if (in_array($dataType, $clearData)) {
                                $valArr[] = "?";
                        } else {
                                $valArr[] = "FROM_BASE64(?)";
                        }
                }
                $valStr = '(' . implode(',',$valArr) . ')';
                self::$queuedQuestValuesToInsert[] = $valStr;
                self::$queuedPureValuesToInsert = array_merge(self::$queuedPureValuesToInsert,$clearEntry);
                
                $chunkSizeOfTable = 1;
                if (key_exists($tableWithoutPrefix, self::$chunkSizes)) {
                        $chunkSizeOfTable = self::$chunkSizes[$tableWithoutPrefix];
                }
                if (self::$currentChunk === ($chunkSizeOfTable - 1)) {
                        self::flushTableInserts($pdo);
                } else {
                        self::$currentChunk++;
                }
        }
        
        private static function flushTableInserts($pdo) {
                if (self::$currentSql != '') {
                        if (count(self::$queuedPureValuesToInsert) > 0) {
                                $sql = self::$currentSql . implode(',',self::$queuedQuestValuesToInsert);
                                CommonUtils::execSql($pdo, $sql, self::$queuedPureValuesToInsert);
                        }

                        self::$currentChunk = 0;
                        self::$queuedQuestValuesToInsert = array();
                        self::$queuedPureValuesToInsert = array();
                }
        }
        
        private static function markEndOfTableForImport() {
                self::$currentSql = '';
        }
        
        private static function cleanFromLFCR($str) {
                return str_replace (array("\r\n", "\n", "\r"), ' ', $str);
        }
        private static function getValueOfLine($str) {
                return trim(explode(':',$str,2)[1]);
        }
        private static function getVersionOfBackup($filename) {
                $version = null;
                $file = fopen($filename, "r");

                while (!feof($file) && is_null($version)) {
                        $line = self::cleanFromLFCR(fgets($file));
                        if (strpos($line, self::$PREF_VERSION . ':') === 0) {
                                $version = self::getValueOfLine($line);
                        }
                }
                fclose($file);
                return $version;
        }
        
        private static function getDataTypesNotBased64() {
                return array('int','date','datatime','decimal','varchar','text');
        }
        
        private static string $chunk = "";
        private static int $maxChunkSize = 524288; // 512 KB
        
        private static function ftpChunk (string $ftpConn) {
                $status = file_put_contents($ftpConn, self::$chunk, FILE_APPEND);
                
                if (!$status) {
                        $error = json_encode(error_get_last());
                        throw new Exception("Chunk upload did not work: $error");
                }
        }
        
        private static function transferATextViaFtp(string $ftpConn, string $text) {
                $lenInChunk = strlen(self::$chunk);
                $lenInAdditionalText = strlen($text);
                if (($lenInAdditionalText + $lenInChunk) > self::$maxChunkSize) {
                        // send chunk and create a new one
                        $partForChunk = substr($text,0,self::$maxChunkSize - $lenInChunk);
                        self::$chunk .= $partForChunk;
                        self::ftpChunk($ftpConn);
                        self::$chunk = "";
                        $text = substr($text, self::$maxChunkSize - $lenInChunk);
                        self::transferATextViaFtp($ftpConn, $text);
                } else {
                        self::$chunk .= $text;
                }
        }
        
        private static function flushFtp(string $ftpConn) {
                file_put_contents($ftpConn, self::$chunk, FILE_APPEND);
                self::$chunk = "";
        }
        
        public static function backupWithCursor($pdo,$theType,bool $doFtp) {
                try {
                        // Transfer-Encoding: chunked
                        $version = CommonUtils::getConfigValue($pdo, 'version', '?');

                        set_time_limit(60*60);
                        date_default_timezone_set(DbUtils::getTimeZone());
                        $nowtime = date('Y-m-d');
                        $extension = self::$NATIVE_BACKUP_EXTENSION;

                        $fileName = "backup-" . $version . "_" . $nowtime . "-configuration.$extension";
                        if ($theType == "all") {
                                $fileName = "backup-" . $version . "_" . $nowtime . "-all.$extension";
                        } else if ($theType == "alllogs") {
                                $fileName = "backup-" . $version . "_" . $nowtime . "-all-logs.$extension";
                        } else if ($theType == "confandguests") {
                                $fileName = "backup-" . $version . "_" . $nowtime . "-guests.$extension";
                        }

                        if ($doFtp) {
                                // different filename to avoid failure in case existence of file
                                $fileName = "backup-" . $version . "_" . date('Y-m-d_H-i-s') . "-all.$extension";
                                $ftphost = CommonUtils::getConfigValue($pdo, 'ftphost', '');
                                $ftpuser = CommonUtils::getConfigValue($pdo, 'ftpuser', '');
                                $ftppass = CommonUtils::getConfigValue($pdo, 'ftppass', '');
                        } else {
                                header("Pragma: public");
                                header("Expires: 0");
                                header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
                                header("Cache-Control: public");
                                header("Content-Description: File Transfer");
                                header("Content-type: application/octet-stream");
                                header("Content-Disposition: attachment; filename=\"$fileName\"");
                        }

                        $delHistReferencesToClosings = false;
                        if ($theType == "configuration") {
                                $tables = self::getConfigTablesToBackupRestore();
                                $delHistReferencesToClosings = true;
                        } else if ($theType == "confandguests") {
                                $tables = self::getConfigGuestsTablesToBackupRestore();
                                $delHistReferencesToClosings = true;
                        } else if (($theType == "all") || ($theType == "auto")) {
                                HistFiller::insertSaveHistEntry($pdo);
                                $tables = self::getAllTablesToBackupRestore();
                        } else {
                            HistFiller::insertSaveHistEntry($pdo);
                            $tables = self::getAllWithLogsTablesToBackupRestore();
                        }

                        if ($doFtp) {
                                $ftpConn = "ftp://$ftpuser:$ftppass@$ftphost/$fileName";
                                self::$chunk = "";
                                $txt = self::getChunkedLine(self::$PREF_VERSION . ':' . $version);
                                self::transferATextViaFtp($ftpConn, $txt);
                                self::flushFtp($ftpConn);
                        } else {
                                echo self::getChunkedLine(self::$PREF_VERSION . ':' . $version);
                        }

                        foreach($tables as $table) {
                                set_time_limit(60*60);

                                if ($doFtp) {
                                        self::transferATextViaFtp($ftpConn, self::getChunkedLine(self::$PREF_TABLE_START . ':' . $table));
                                } else {
                                        echo self::getChunkedLine(self::$PREF_TABLE_START . ':' . $table);
                                }

                                $sql = "SELECT COLUMN_NAME as colname,DATA_TYPE as datatype from INFORMATION_SCHEMA.COLUMNS where TABLE_SCHEMA = '" . MYSQL_DB . "' and TABLE_NAME = '%$table%'";
                                $fieldsAndTypes = CommonUtils::fetchSqlAll($pdo, $sql);
                                $fieldsTxt = self::seperatedFieldAttr($fieldsAndTypes,'colname',';','');
                                $dataTypesTxt = self::seperatedFieldAttr($fieldsAndTypes,'datatype',';','');
                                if ($doFtp) {
                                        self::transferATextViaFtp($ftpConn, self::getChunkedLine(self::$PREF_COLNAMES . ':' . $fieldsTxt));
                                        self::transferATextViaFtp($ftpConn, self::getChunkedLine(self::$PREF_DATATYPES . ':' . $dataTypesTxt));
                                } else {
                                        echo self::getChunkedLine(self::$PREF_COLNAMES . ':' . $fieldsTxt);
                                        echo self::getChunkedLine(self::$PREF_DATATYPES . ':' . $dataTypesTxt);
                                }

                                $clearData = self::getDataTypesNotBased64();
                                $sqlArr = array();
                                foreach($fieldsAndTypes as $f) {
                                        $colname = $f['colname'];
                                        $dataType = $f['datatype'];
                                        if (($table == 'hist') && ($colname == "clsid") && $delHistReferencesToClosings) {
                                                $sqlArr[] = "null as `$colname`";
                                        } else if (in_array($dataType, $clearData)) {
                                                $sqlArr[] = "`$colname`";
                                        } else {
                                                $sqlArr[] = "TO_BASE64(`" . $colname . "`) as `$colname`";
                                        }
                                }
                                $sqlStr = "SELECT " . implode(',',$sqlArr) . " FROM  `%$table%`";

                                $stmt = $pdo->prepare(DbUtils::substTableAlias($sqlStr), array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
                                $stmt->execute();

                                while ($rows = $stmt->fetch(PDO::FETCH_NUM, PDO::FETCH_ORI_NEXT)) {
                                        $data = json_encode($rows);
                                        if ($doFtp) {
                                                self::transferATextViaFtp($ftpConn, self::getChunkedLine(self::$PREF_ENTRY . ':' . $data));
                                        } else {
                                                echo self::getChunkedLine(self::$PREF_ENTRY . ':' . $data);
                                        }
                                }
                        }
                        if ($doFtp) {
                                self::flushFtp($ftpConn);
                        }
                        return array("status" => "OK");
                } catch (Exception $ex) {
                        return array("status" => "ERROR","msg" => "Exception " . $ex->getMessage());
                }
        }
        
        private static function getChunkedLine($str) {
                return $str . "\r\n";
        }
     
        private static function seperatedFieldAttr ($fieldsAndTypes,$attr,$separator,$quote) {
                $cols = array();
                foreach($fieldsAndTypes as $entry) {
                        $cols[] = $quote . $entry[$attr] . $quote;
                }
                return implode($separator,$cols);
        }
        
	public static function getConfigTablesToBackupRestore() {
		return array("logo","work","payment","room","resttables","tablepos","tablemaps","pricelevel","prodtype","prodimages","products","config","roles","user","comments","histprod","histconfig","histuser","histactions","hist","extras","extrasprods");
	}
	
	public static function getConfigGuestsTablesToBackupRestore() {
		return array("logo","work","payment","room","resttables","tablepos","tablemaps","pricelevel","prodtype","prodimages","products","config","roles","user","comments","histprod","histconfig","histuser","histactions","hist","extras","extrasprods","customers","groups","groupcustomer","vacations");
	}
	
	public static function getAllTablesToBackupRestore() {
		return array("performance","usedfeatures","tsevalues","terminals","operations","closing","counting","logo","printjobs","ratings","work","payment","room","resttables","tablepos","tablemaps","pricelevel","prodtype","prodimages","products","config",
			"roles","vouchers","user","liveorders","reservations","customers","groups","orders","groupcustomer","vacations","bill","customerlog","prodnames","queue","times","records","recordsqueue","billproducts","hsin","hsout","comments","histprod","histconfig","histuser","histactions","hist",
			"extras","extrasprods","queueextras","tasks","taskhist");
	}
	
	public static function getAllWithLogsTablesToBackupRestore() {
		return array("performance","usedfeatures","log","tsevalues","terminals","operations","closing","counting","logo","printjobs","ratings","work","payment","room","resttables","tablepos","tablemaps","pricelevel","prodtype","prodimages","products","config",
			"roles","vouchers","user","liveorders","reservations","customers","groups","orders","groupcustomer","vacations","bill","customerlog","prodnames","queue","times","records","recordsqueue","billproducts","hsin","hsout","comments","histprod","histconfig","histuser","histactions","hist",
			"extras","extrasprods","queueextras","tasks","taskhist");
	}
}