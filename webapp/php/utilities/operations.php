<?php
require_once (__DIR__. '/../dbutils.php');
require_once (__DIR__. '/sroomsync.php');

class Operations {

	public static function createOperation($pdo,$opType,$table,
					$logtime,
					$trans,
					$signtxt,
					$tseSignature,
					$pubkeyRef,
					$sigalgRef,
					$serialNoRef,
					$certificateRef,
					$sigcounter,
					$tseerror) {
		
                $sroomanswer = (new SRoomSync())->updateWorkload($pdo);
                if ($sroomanswer['status'] != "OK") {
                        error_log("Workload update in Showroom failed");
                }
                
		$terminalInfo = Terminals::getTerminalInfo();
		$terminalEntryId = Terminals::createOrReferenceTerminalDbEntry($pdo, $terminalInfo);
		
		$range = RANGE_ORDER;
		if ($opType == DbUtils::$PROCESSTYPE_VORGANG) {
			$range = RANGE_ORDER;
		} else if ($opType == DbUtils::$PROCESSTYPE_BELEG) {
			$range = RANGE_BILL;
		} else if ($opType == DbUtils::$PROCESSTYPE_SONSTIGER_VORGANG) {
			$range = RANGE_CLOSING;
		}
		
		$sql = "SELECT MAX(COALESCE(bonid,0)) as maxbonid FROM %operations% WHERE typerange=?";
		$res = CommonUtils::fetchSqlAll($pdo, $sql,array($range));
		$maxbonid = intval($res[0]["maxbonid"]);
		$sql = "SELECT MAX(COALESCE(id,0)) as maxid FROM %operations%";
		$res = CommonUtils::fetchSqlAll($pdo, $sql);
		$maxid = intval($res[0]["maxid"]);
                
		if (strlen($signtxt) > 120) {
			// the signtxt is only for debug purposes. So cut it and show that it was cut
			$signtxt = substr($signtxt, 0, 117) . "...";
		}

                $signtxt_ascii = preg_replace('/[[:^print:]]/', '', $signtxt);
                        
                $sql = "INSERT INTO %operations% (id,typerange,bonid,processtype,handledintable,logtime,trans,sigcounter,tsesignature,pubkey,sigalg,serialno,certificate,signtxt,tseerror,terminalid) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
                CommonUtils::execSql($pdo, $sql, array($maxid+1,$range,$maxbonid+1,$opType,$table,$logtime,$trans, $sigcounter, $tseSignature, $pubkeyRef, $sigalgRef, $serialNoRef,$certificateRef, $signtxt, $tseerror, $terminalEntryId));
                $opid = $pdo->lastInsertId();
		return $opid;
	}
}
