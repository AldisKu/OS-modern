<?php
// Datenbank-Verbindungsparameter
require_once (__DIR__. '/../globals.php');


define ( 'R_ADM', 1);
define ( 'R_WAI', 2);
define ( 'R_KIT', 4);
define ( 'R_BAR', 8);
define ( 'R_SUP', 16);
define ( 'R_PAY', 32);
define ( 'R_STA', 64);
define ( 'R_BIL', 128);
define ( 'R_PRO', 256);
define ( 'R_RES', 512);
define ( 'R_RAT', 1024);
define ( 'R_MAN', 2048);
define ( 'R_CP', 4096);
define ( 'R_CL', 8192);
define ( 'R_CUS', 16384);
define ( 'R_DASH', 32768);


class Userrights {
	
	function setSession($isAdm,$rWait,$rKit,$rBar,$rSupply,$rPay,$rStat,$rBill,$rProd,$rRes,$rRat,$rChangePrice,$rCustomers,$rMan,$rClos,$rDash) {
		$ret = R_ADM * ($isAdm ? 1:0) | R_WAI * ($rWait ? 1:0) | R_KIT * ($rKit ? 1:0) | R_BAR * ($rBar ? 1:0) | R_SUP * ($rSupply ? 1:0) | R_PAY * ($rPay ? 1:0);
		$ret |= R_STA * ($rStat ? 1:0) | R_BIL * ($rBill ? 1:0) | R_PRO * ($rProd ? 1:0) | R_RES * ($rRes ? 1:0) | R_RAT * ($rRat ? 1:0) | 
			R_CP * ($rChangePrice ? 1:0) | R_CUS * ($rCustomers ? 1:0) | R_MAN * (($rMan ? 1:0) | R_CL * (($rClos ? 1:0)) | R_DASH * ($rDash ? 1:0));
		$_SESSION['allrights'] = $ret;
	}
	
	/**
	 * At least one of the OR-combined rights in the argument must match with the SESSION-right to return true 
	 * @param unknown $rights
	 */
	function isCmdAllowedForUser($rights) {
		if(session_id() == '') {
			session_start();
		}
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			return false;
		}
		$_SESSION['angemeldet'] = true;
		if (($rights & $_SESSION['allrights']) > 0) {
			return true;
		} else {
			return false;
		}
	}
	
	/*
	 * can the current call the currentCmd
	 */
	function canUserCallCommands($currentCmd, $cmdArray,$right) {
		session_start();
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			// no user logged in
			return false;
		} else {
			// user is logged in
			if (in_array($currentCmd, $cmdArray)) {
				// yes, the current command is in the set of commands to test!
				if ($_SESSION[$right]) {
					return true;
				}
			}
			return false;
		}
	}

		
	function isCurrentUserAdmin() {
		if(session_id() == '') {
	    	session_start();
		}
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			// no user logged in
			return false;
		} else {
			return ($_SESSION['is_admin']);
		}
	}
	

	function hasCurrentUserRight($whichRight) {
		if(session_id() == '') {
	    	session_start();
		}
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			// no user logged in
			return false;
		} else {
			return ($_SESSION[$whichRight]);
		}
	}
}