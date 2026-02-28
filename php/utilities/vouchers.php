<?php

class vouchers {
	public static function createEinZweckVoucher($pdo,$name,$userid) {
		date_default_timezone_set(DbUtils::getTimeZone());
		$currentTime = date('Y-m-d H:i:s');
		
		try {
			$sql = "INSERT INTO %vouchers% (creationdate,name,redeemdate,removed,creatorid,redeemerid) VALUES(?,?,?,?,?,?)";
			CommonUtils::execSql($pdo, $sql, array($currentTime,$name,null,null,$userid,null));
			$voucherid = $pdo->lastInsertId();
			return $voucherid;
		} catch (Exception $ex) {
			return null;
		}
	}
	
	public static function redeemVoucher($pdo,$voucherid, $userid) {
		date_default_timezone_set(DbUtils::getTimeZone());
		$currentTime = date('Y-m-d H:i:s');
		
		$sql = "SELECT id as countid from %vouchers% WHERE id=? AND redeemdate is null AND removed is null";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($voucherid));
		if (count($result) == 0) {
			return false;
		} else {
			$sql = "UPDATE %vouchers% SET redeemdate=?,redeemerid=? WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array($currentTime,$userid,$voucherid));
			return true;
		}
	}
	
	public static function handleRemoveQueueItem($pdo,$queueid) {
		$sql = "SELECT ordertype,voucherid FROM %queue% WHERE id=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($queueid));
		if (count($result) > 0) {
			$ordertype = $result[0]["ordertype"];
			$voucherid = $result[0]["voucherid"];
			
			if ($ordertype == DbUtils::$ORDERTYPE_1ZweckEinl) {
				$sql = "UPDATE %vouchers% SET redeemdate=?,redeemerid=? WHERE id=? AND removed is null";
				CommonUtils::execSql($pdo, $sql, array(null,null,$voucherid));
			} else if ($ordertype == DbUtils::$ORDERTYPE_1ZweckKauf) {
				$sql = "UPDATE %vouchers% SET removed=? WHERE id=? AND redeemdate is null";
				CommonUtils::execSql($pdo, $sql, array(1,$voucherid));
			}
		}
	}
	
}
