<?php

class Updater {
	
	function handleCommand($command) {
		if (!self::isUserAlreadyLoggedInAndAdmin()) {
			echo json_encode(array("status" => "ERROR", "msg" => "Not authorized"));
		} else {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			if ($command == 'getAvailableVersion') {
				echo json_encode(self::getAvailableVersion($pdo));
			} else if ($command == 'updatecheck') {
                                defined('ISDEMO') || define ('ISDEMO', false);
                                if (ISDEMO) {
                                        echo json_encode(array("status" => "ERROR","msg" => "Vorgang im Demo-System nicht erlaubt", "code" => "FORBIDDEN"));
                                } else {
                                        echo json_encode(self::updatecheck($pdo));
                                }
			} else if ($command == 'replace') {
				echo json_encode(self::replace($pdo,$_GET["fileindex"],$_GET["totalLines"]));
			}
			else {
				echo "Kommando nicht unterstuetzt.";
			}
		}
	}
	
	private static function isUserAlreadyLoggedInAndAdmin() {
		if(session_id() == '') {
			session_start();
		}
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			return false;
		} else {
			return ($_SESSION['is_admin']);
		}
	}
	
	private static function getFile($url,$file,$asArray = false,$timeout = 200) {
		$ctx = stream_context_create(array('http' =>
		    array(
			'timeout' => $timeout, // seconds
		    )
		));

		$url = $url . "/downloader.php?file=" . $file;
		
		try {
			$infoFile = file_get_contents($url, false, $ctx);
			$test = substr($infoFile, 1,50);
			if ($infoFile != FALSE) {
				if ($asArray) {
					$retArr = array();
					$lines = explode("\n", $infoFile);

					foreach($lines as $aLine) {
						$l = trim($aLine);
						if ($l != '') {
							$retArr[] = $l;
						}
					}
					return array("status" => "OK","msg" => $retArr);
				} else {
					return array("status" => "OK","msg" => $infoFile);
				}
			} else {
				return array("status" => "ERROR","msg" => "File not found ($url)");
			}
		} catch (Exception $ex) {
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
	}
	private static function getInfoFile($url,$file,$asArray = false,$timeout = 200) {
		$ctx = stream_context_create(array('http' =>
		    array(
			'timeout' => $timeout, // seconds
		    )
		));

		$url = $url . "/" . $file;
		
		try {
			$infoFile = @file_get_contents($url, false, $ctx);
			
			if ($infoFile != FALSE) {
				if ($asArray) {
					$retArr = array();
					$lines = explode("\n", $infoFile);

					if (count($lines) < 1) {
						return array("status" => "ERROR","msg" => "Info file not valid");
					}
					$versionMatch = '/^[0-9]*\.[0-9]*\.[0-9]*/';
					$ret = preg_match($versionMatch, $lines[0]);
					if ($ret == 0) {
						return array("status" => "ERROR","msg" => "Info file has no version info.");
					}
					
					foreach($lines as $aLine) {
						$l = trim($aLine);
						if ($l != '') {
							$retArr[] = $l;
						}
					}
					return array("status" => "OK","msg" => $retArr);
				} else {
					return array("status" => "OK","msg" => $infoFile);
				}
			} else {
				return array("status" => "ERROR","msg" => "File to replace not found ($url)");
			}
		} catch (Exception $ex) {
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
	}
        
        private static function evalChangelog(PDO $pdo,string $url,string $file, int $timeout = 200):string {
                $ctx = stream_context_create(array('http' =>
		    array(
			'timeout' => $timeout, // seconds
		    )
		));

		$url = $url . "/" . $file;
		
		try {
			$history = @file_get_contents($url, false, $ctx);
			if ($history == FALSE) {
				return '';
			}
		} catch (Exception $ex) {
			return '';
		}
                 
                $curVer = CommonUtils::getConfigValue($pdo, "version", 'xxxx');
               
                $lines = explode("\n",$history);
                $v = '';
                $histlines = array();
                $isEnglish = false;
                foreach($lines as $l) {
                        if (strpos($l, '#') === 0) {
                                $vFound = explode(";",substr($l, 1))[0];
                                if ($vFound == $v) {
                                        $isEnglish = true;
                                        continue;
                                } else {
                                        $isEnglish = false;
                                }
                                $v = $vFound;
                                if ($v == $curVer) {
                                        break;
                                }
                                $histlines[] = "<i><b>Version $v</b></i><br>";
                        } else if (!$isEnglish) {
                                
                                $l = str_replace("<p", "<br", $l);
                                if ((trim($l) == "<ul>") && (count($histlines) > 0)) {
                                        $histlines[count($histlines) - 1] = $histlines[count($histlines) - 1] . $l;
                                } else {
                                        $histlines[] = $l;
                                }
                        }
                }
                return implode("<br>",$histlines);
        }
        
	private static function getAvailableVersion($pdo) {
		$url = CommonUtils::getConfigValue($pdo, "updateurl", '');
		$installedVersion = CommonUtils::getConfigValue($pdo, "version", '');
		$infoFile = self::getInfoFile($url,'updateinfo.txt',true,3);
		if ($infoFile["status"] != "OK") {
			return array("status" => "ERROR","msg" => "could not get info file: " . $infoFile["msg"],"url" => $url);
		}
		$infoFileLines = $infoFile["msg"];
                
                $changelog = self::evalChangelog($pdo,$url,'history.txt',200);
		
		if (count($infoFileLines) > 1) {
			$checkIfNewerVersion = self::isV2Newer($installedVersion,trim($infoFileLines[0]));
			return array("status" => "OK","msg" => $infoFileLines[0],"url" => $url,"neweravailable" => ($checkIfNewerVersion ? 1 : 0),"changelog" => $changelog);
		} else {
			return array("status" => "ERROR","msg" => "Info file not valid","url" => $url);
		}
	}
	
	private static function isV2Newer($v1,$v2) {
		if (is_null($v1) || is_null($v2)) {
			return false;
		}
		$v1key = self::genVerKey($v1);
		$v2key = self::genVerKey($v2);
	
		if (is_null($v1key) || is_null($v2key)) {
			return false;
		}
		
		if ($v1key < $v2key) {
			return true;
		} else {
			return false;
		}
	}
	
	private static function genVerKey($v) {
		$vparts = explode('.',$v);
		$len = count($vparts);
		$key = 0;
		try {
			for ($i=0;$i<$len;$i++) {
				$key += intval($vparts[$i]) * pow(1000,2-$i);
			}
			return $key;
		} catch (Exception $e) {
			return null;
		}
	}

	
	private static function doCheck($lineArr) {		
		if (count($lineArr) < 2) {
			return array("status" => "OK","msg" => '');
		}

		for ($i=1;$i<count($lineArr);$i++) {
			if (trim($lineArr[$i]) == '') {
				continue;
			}
			$aLine =  "../" . $lineArr[$i];
			$basename = basename($aLine);
			$dirname = dirname($aLine);
			
			$isDirExists = file_exists($dirname);

			if (!$isDirExists) {
				$ret = self::createSubDir($dirname);
				if ($ret["status"] != "OK") {
					return $ret;
				}
			}
			
			$isFileExists = file_exists($aLine);
			$isDirWritable = is_writable($dirname);
			$isFileWritable = is_writable($aLine);
			
			if ($isFileExists) {
				if (!$isFileWritable) {
					return array("status" => "ERROR","msg" => $aLine . " cannot be overwritten");
				}
			} else {
				// file does not exist, but can it be created?
				if (!$isDirWritable) {
					return array("status" => "ERROR","msg" => $basename . " cannot be written into $dirname with this path: " . $aLine);
				}
			}
			
		}
		return array("status" => "OK","msg" => "");
	}
	
	private static function createSubDir($dirname) {
		if (!is_writable("..")) {
			return array("status" => "ERROR","msg" => "Root directory not writable. But this is necessary to create and delete install directory.");
		}

		if (!file_exists($dirname)) {
			if (!mkdir($dirname, 0777)) {
				return array("status" => "ERROR","msg" => "$dirname directory could not be created.");
			}
		} else {
			if (!is_writable($dirname)) {
				return array("status" => "ERROR","msg" => "Cannot write into $dirname directory.");
			}
		}
		return array("status" => "OK");
	}
	
	public static function updatecheck($pdo) {
		$res = DbUtils::checkForInstallUpdateDbRights($pdo);
		if ($res["status"] != "OK") {
			return array("status" => "ERROR","msg" => $res["msg"]);
		}
		if ($res["ok"] == 0) {
			return array("status" => "ERROR","msg" => "Fehlende DB-Rechte: " . join(",",$res["msg"]));
		}

		$url = CommonUtils::getConfigValue($pdo, "updateurl", '');
		
		$infoFile = self::getInfoFile($url,'updateinfo.txt',true,3);
		if ($infoFile["status"] != "OK") {
			return array("status" => "ERROR","msg" => "could not get info file: " . $infoFile["msg"]);
		}
		$infoFileLines = $infoFile["msg"];

		$check = self::doCheck($infoFileLines);
		if ($check["status"] != "OK") {
			$ret = array("status" => "ERROR","msg" => "Check returned: " . $check["msg"]);
		} else {
			$ret = array("status" => "OK","msg" => $infoFileLines);
		}
		
		return $ret;
	}
	
	public static function replace($pdo,$fileindex,$totallines) {
		$url = CommonUtils::getConfigValue($pdo, "updateurl", '');
		$infoFile = self::getInfoFile($url,'updateinfo.txt',true,3);
		if ($infoFile["status"] != "OK") {
			return array("status" => "ERROR","msg" => "could not get file: " . $infoFile["msg"]);
		}
		$lineArr = $infoFile["msg"];
		
		try {
			$fileToRead = $lineArr[intval($fileindex) + 1];
			
			if (trim($fileToRead) != '') {
				$targetFile =  "../" . $fileToRead;
				$fileContent = self::getFile($url, $fileToRead);
				if ($fileContent["status"] == "OK") {
					file_put_contents($targetFile, $fileContent["msg"]);
				} else {
					return array("status" => "ERROR","msg" => "$targetFile cannot be fetched from update server.");
				}
			}
		} catch (Exception $ex) {
			return array("status" => "ERROR","msg" => $ex->getMessage());
		}
		
		return array("status" => "OK","msg" => array("index" => $fileindex,"file" => $fileToRead,"totalLines" => $totallines));
	}
}