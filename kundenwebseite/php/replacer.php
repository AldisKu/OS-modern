<?php

class Replacer {
	public static function getRestName($pdo) {
		$companyInfo = self::getConfigItem($pdo, "companyinfo");
		if (!is_null($companyInfo)) {
			$lines = explode ("\n", $companyInfo );
			return htmlspecialchars($lines[0]);
		} else {
			return "";
		}
	}
	
	public static function getCompanyInfo($pdo) {
		$companyInfo = self::getConfigItem($pdo, "companyinfo");
		return str_replace("\n","<br>",htmlspecialchars($companyInfo));
	}
	
	public static function getWebimpressum($pdo) {
		$companyInfo = self::getConfigItem($pdo, "webimpressum");
		return str_replace("\n","<br>",htmlspecialchars($companyInfo));
	}

	public static function lineSubstitution ($pdo,$textline) {
		$txt = str_replace("{Restaurantname}",self::getRestName($pdo),$textline);
		$txt = str_replace("{Betriebsinfo}",self::getWebimpressum($pdo),$txt);
		$txt = str_replace("{Version}",self::getConfigItem($pdo,"version"),$txt);
		return $txt;
	}
	
	private static function getConfigItem($pdo,$item) {
		$sql = "SELECT setting FROM %config% WHERE name=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($item));
		$row = $stmt->fetchObject();
		return $row->setting;
	}
	
}

?>