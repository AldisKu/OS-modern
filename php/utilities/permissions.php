<?php

class Permissions {
	
	
	public static function checkRights($command,$rights) {
		if (session_id() == '') {
			session_start();
		}
		if (!array_key_exists($command, $rights)) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_COMMAND_NOT_FOUND, "msg" => ERROR_COMMAND_NOT_FOUND_MSG));
			return false;
		}
		$cmdRights = $rights[$command];
		if ($cmdRights["loggedin"] == 1) {
			if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_NOT_AUTHOTRIZED, "msg" => ERROR_NOT_AUTHOTRIZED_MSG));
				return false;
			}
		}
		if ($cmdRights["isadmin"] == 1) {
			if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_NOT_AUTHOTRIZED, "msg" => ERROR_NOT_AUTHOTRIZED_MSG));
				return false;
			} else {
				if ($_SESSION['is_admin'] == false) {
					echo json_encode(array("status" => "ERROR", "code" => ERROR_COMMAND_NOT_ADMIN, "msg" => ERROR_COMMAND_NOT_ADMIN_MSG));
					return false;
				}
			}
		}
		if (!is_null($cmdRights["rights"])) {
			foreach ($cmdRights["rights"] as $aRight) {
				if ($aRight == 'timetracking') {
					if (($_SESSION['is_admin']) || ($_SESSION['right_timetracking'])) {
						return true;
					}
				} else if ($aRight == 'timemanager') {
					if ($_SESSION['right_timemanager']) {
						return true;
					}
				} else if ($aRight == 'tasks') {
					if (($_SESSION['is_admin']) || ($_SESSION['right_tasks'])) {
						return true;
					}
				} else if ($aRight == 'tasksmanagement') {
					if ($_SESSION['right_tasksmanagement']) {
						return true;
					}
				}
			}
			echo json_encode(array("status" => "ERROR", "code" => ERROR_NOT_AUTHOTRIZED, "msg" => ERROR_NOT_AUTHOTRIZED_MSG));
			return false;
		}
		return true;
	}
	
	private static function isOnlyRatingUserCheckByRole($pdo,$userid) {
		$permsQueryStr = self::createCommaSeperatedStringOfRights();
		$sql = "SELECT $permsQueryStr FROM %user% U,%roles% R WHERE U.id=? AND U.roleid=R.id";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($userid));
		if (count($result) == 0) {
			return true;
		}
		$permission = $result[0];
		$anyNormalRight = false; 
                $perms = CommonUtils::getPermissions($pdo);
		foreach($perms as $aPermission) {
			if ($aPermission['flag'] == 'normal') {
				if ($permission[$aPermission['name']] == 1) {
					$anyNormalRight = true;
				}
			}
		}
		if ($anyNormalRight) {
			return false;
		}
		$permissionForRating = $permission['right_rating'];
		if ($permissionForRating == 1) {
			return true;
		} else {
			return false;
		}
	}
	
	public static function setPermissionsAfterAuthenticatedLogin($pdo,$userattributes,$workflowconfigfood,$workflowconfigdrinks,$userid) {

                $perms = CommonUtils::getPermissions($pdo);
		if (self::isOnlyRatingUserCheckByRole($pdo, $userid)) {
			foreach($perms as $aPermission) {
					$_SESSION[$aPermission['name']] = false;
			}
			$_SESSION['right_rating'] = true;
			$_SESSION['keeptypelevel'] = false;
		} else {
			foreach($perms as $aPermission) {
				$_SESSION[$aPermission['name']] = ($userattributes[$aPermission['name']] == 1 ? true : false);
			}

			$extendedPickupPermission = false;
			if (($userattributes['right_pickups'] == 1)  && ( ($userattributes['right_kitchen'] == 1) || ($userattributes['right_bar'] == 1))) {
				$extendedPickupPermission = true;
			}
			$_SESSION['right_extendedpickup'] = $extendedPickupPermission;

                        if (($workflowconfigfood == 2) || ($workflowconfigfood == 3)) {
				$_SESSION['right_kitchen'] = false;
			}
                        if (($workflowconfigdrinks == 2) || ($workflowconfigdrinks == 3)) {
				$_SESSION['right_bar'] = false;
			}
                        if ((($workflowconfigfood == 2) || ($workflowconfigfood == 3)) && (($workflowconfigdrinks == 2) || ($workflowconfigdrinks == 3))) {
                                $_SESSION['right_supply'] = false;
                        }
		}
	}
	
	public static function isOnlyRatingUser() {
		$anyNormalRight = false;
                $perms = CommonUtils::getPermissions(null);
		foreach($perms as $aPermission) {
			if ($aPermission['flag'] == 'normal') {
				if ($_SESSION[$aPermission['name']]) {
					$anyNormalRight = true;
				}
			}
		}
		if ($anyNormalRight) {
			return false;
		}
		if ($_SESSION['right_rating']) {
			return true;
		} else {
			return false;
		}
	}
	
	public static function createCommaSeperatedStringOfRights() {
		$rights = array();
                $perms = CommonUtils::getPermissions(null);
		foreach($perms as $aPermission) {
			$rights[] = $aPermission['name'];
		}
		return implode(',',$rights);
	}
	
	public static function canUserDoCashOps($pdo,$userId) {
		$sql = "SELECT right_cashop,right_paydesk,right_bill FROM %roles% R,%user% U WHERE U.roleid=R.id AND U.id=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($userId));
		$cashopPermission = $result[0]['right_cashop'];
                $paydeskPermission = $result[0]['right_paydesk'];
                $billpPermission = $result[0]['right_bill'];
		if (($cashopPermission == 0) || ($paydeskPermission == 0) || ($billpPermission == 0)){
			return false;
		} else {
			return true;
		}
	}
}
