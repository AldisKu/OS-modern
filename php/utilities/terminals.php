<?php

class Terminals {
	public static function getTerminalInfo() {
		$ipaddress = 'UNKNOWN';
		$keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
		foreach ($keys as $k) {
			if (isset($_SERVER[$k]) && !empty($_SERVER[$k]) && filter_var($_SERVER[$k], FILTER_VALIDATE_IP)) {
				$ipaddress = $_SERVER[$k];
				break;
			}
		}
		
		$useragent = $_SERVER['HTTP_USER_AGENT'];
		$bname = 'Unknown';
		$platform = 'Unknown';
		$version = "";

		//First get the platform?
		if (preg_match('/linux/i', $useragent)) {
			$platform = 'linux';
		} elseif (preg_match('/macintosh|mac os x/i', $useragent)) {
			$platform = 'mac';
		} elseif (preg_match('/windows|win32/i', $useragent)) {
			$platform = 'windows';
		}

		// Next get the name of the useragent yes seperately and for good reason
		if (preg_match('/MSIE/i', $useragent) && !preg_match('/Opera/i', $useragent)) {
			$bname = 'Internet Explorer';
			$ub = "MSIE";
		} elseif (preg_match('/Firefox/i', $useragent)) {
			$bname = 'Mozilla Firefox';
			$ub = "Firefox";
		} elseif (preg_match('/Chrome/i', $useragent)) {
			$bname = 'Google Chrome';
			$ub = "Chrome";
		} elseif (preg_match('/Safari/i', $useragent)) {
			$bname = 'Apple Safari';
			$ub = "Safari";
		} elseif (preg_match('/Opera/i', $useragent)) {
			$bname = 'Opera';
			$ub = "Opera";
		} elseif (preg_match('/Netscape/i', $useragent)) {
			$bname = 'Netscape';
			$ub = "Netscape";
		}

		// finally get the correct version number
		$known = array('Version', $ub, 'other');
		$pattern = '#(?<browser>' . join('|', $known) .
			')[/ ]+(?<version>[0-9.|a-zA-Z.]*)#';
		if (!preg_match_all($pattern, $useragent, $matches)) {
			// we have no matching number just continue
		}

		// see how many we have
		$i = count($matches['browser']);
		if ($i != 1) {
			//we will have two since we are not using 'other' argument yet
			//see if version is before or after the name
			if (strripos($useragent, "Version") < strripos($useragent, $ub)) {
				$version = $matches['version'][0];
			} else {
				$version = $matches['version'][1];
			}
		} else {
			$version = $matches['version'][0];
		}

		// check if we have a number
		if ($version == null || $version == "") {
			$version = "?";
		}

		return array(
		    'ipaddress' => $ipaddress,
		    'useragent' => $useragent,
		    'browser' => $bname,
		    'version' => $version,
		    'platform' => $platform
		);
	}

	public static function createOrReferenceTerminalDbEntry($pdo,$terminalInfo) {
		$sql = "SELECT id FROM %terminals% WHERE ipaddress=? AND useragent=? AND browser=? AND version=? AND platform=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($terminalInfo['ipaddress'],$terminalInfo['useragent'],$terminalInfo['browser'],$terminalInfo['version'],$terminalInfo['platform']));
		if (count($result) > 0) {
			return $result[0]['id'];
		} else {
			$sql = "INSERT INTO %terminals% (ipaddress,useragent,browser,version,platform) VALUES(?,?,?,?,?)";
			CommonUtils::execSql($pdo, $sql, array($terminalInfo['ipaddress'],$terminalInfo['useragent'],$terminalInfo['browser'],$terminalInfo['version'],$terminalInfo['platform']));
			return $pdo->lastInsertId();
		}
	}
}
