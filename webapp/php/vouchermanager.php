<?php


class Vouchermanager {
	
	public static function handleCommand($command) {
		if (!self::hasCurrentUserBillRights()) {
			echo json_encode(array("status" => "ERROR","msg" => "Fehlendes Benutzerrecht Gutscheinverwaltung"));
			return;
		}
		if ($command == "getvouchers") {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$vouchers = Vouchermanager::getvouchers($pdo);
			echo json_encode($vouchers);
		}
	}
	
	private static function hasCurrentUserBillRights() {
		if (session_id() == '') {
			session_start();
		}
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			return false;
		} else {
			return ($_SESSION['right_bill']);
		}
	}

	private static function getvouchers($pdo) {
		$decpoint = CommonUtils::getConfigValue($pdo, 'decpoint', '.');
		$currency = CommonUtils::getConfigValue($pdo, 'currency', '');
		$creatoruserSql = "COALESCE((SELECT username FROM %user% U WHERE U.id=creatorid),'')";
		$redeemeruserSql = "COALESCE((SELECT username FROM %user% U WHERE U.id=redeemerid),'')";
		$sql = "SELECT V.id,creationdate,V.name,COALESCE(redeemdate,'') as redeemdate,creatorid,$creatoruserSql as creatoruser,$redeemeruserSql as redeemeruser,CONCAT(REPLACE(ROUND(price,2),'.','$decpoint'),' ','$currency') as price,ordertype,COALESCE(removed,0) as removed FROM %vouchers% V ";
		$sql .= " INNER JOIN %queue% Q ON Q.voucherid=V.id WHERE ordertype=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array(DbUtils::$ORDERTYPE_1ZweckKauf));
		return array("status" => "OK","msg" => $result);
	}
}