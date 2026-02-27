<?php

class Menumanager {

	
	public static function getLevel0Categories($pdo) {
		$sql = "SELECT id,name FROM %prodtype% WHERE reference is NULL AND removed is null";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	}
	
	public static function getProdTypeName($pdo,$id) {
		$sql = "SELECT name FROM %prodtype% WHERE id=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($id));
		return $stmt->fetchObject()->name;
	}
	
	private static function getDecPoint($pdo) {
		$sql = "SELECT setting FROM %config% WHERE name=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array("decpoint"));
		return $stmt->fetchObject()->setting;
	}
	private static function prods2Txt($prods,$depth,$decpoint) {
		$txt = "";
		foreach ($prods as $aProd) {
			$price = str_replace(".",$decpoint,$aProd["priceA"]);
			$txt .= "<li class='plevel$depth product'>" . htmlspecialchars($aProd["longname"]) . "&nbsp;&nbsp;&nbsp;$price</li>";
		}
		return $txt;
	}
	
	private static function getSubMenu($pdo,$id,$depth,$decpoint) {
		$txt = "";
		$sql = "SELECT id,longname,priceA FROM %products% WHERE removed is null AND category=? ORDER BY sorting";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($id));
		$allProdsInThisCat = $stmt->fetchAll(PDO::FETCH_ASSOC);
		$txt = self::prods2Txt($allProdsInThisCat, $depth, $decpoint);
		
		$sql = "SELECT id,name FROM %prodtype% WHERE removed is null AND reference=? ORDER BY sorting";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($id));
		$allProdTypesInThisCat = $stmt->fetchAll(PDO::FETCH_ASSOC);
		
		foreach($allProdTypesInThisCat as $aCat) {
			$txt .= "<li class='level$depth type'>" . htmlspecialchars($aCat["name"]) . "</li>";
			
			$txt .= self::getSubMenu($pdo, $aCat["id"],$depth+1,$decpoint);
		}
		return $txt;
	}
	
	public static function getMenu($pdo,$id) {
		$txt = "<ul>";
		$txt .= self::getSubMenu($pdo, $id, 0, self::getDecPoint($pdo));
		$txt .= "</ul>";
		
		return $txt;
	}
}

?>