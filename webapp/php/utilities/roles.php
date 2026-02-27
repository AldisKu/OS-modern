<?php

class Roles {
	private static function createNewRoleEntry($pdo,$rolename) {
		$cols = Basedb::getAllColsOfATable($pdo, '%roles%',array('id','name'));
		$quests = array('?');
		$vals = array($rolename);
		$colnames = array('name');
		
		foreach($cols as $aCol) {
			$quests[] = '?';
			$vals[] = 0;
			$colnames[] = $aCol;
		}
		$colnamesStr = join(',',$colnames);
		$questsStr = join(',', $quests);
		
		$sql = "INSERT INTO %roles% ($colnamesStr) values($questsStr)";
		CommonUtils::execSql($pdo, $sql, $vals);
		return $pdo->lastInsertId();
	}
	
	private static function setPermission($pdo,$roleid,$permissionsArr) {
		foreach ($permissionsArr as $perm) {
			$sql = "UPDATE %roles% SET $perm=? WHERE id=?";
			CommonUtils::execSql($pdo, $sql, array(1,$roleid));
		}
	}
	
	public static function insertAdminRole($pdo) {
		$roleid = self::createNewRoleEntry($pdo, "Admin");
		$permissionsYes = array(
		    'is_admin',
		    'right_statistics',
		    'right_products',
		    'right_manager',
		    'right_closing',
			'right_payallorders',
		    'right_dash',
		    'right_timetracking',
		    'right_timemanager',
		    'right_tasks',
		    'right_tasksmanagement',
                    'right_delivery',
                    'right_customersview',
		    'right_cashop');
		self::setPermission($pdo, $roleid, $permissionsYes);
		return $roleid;
	}
	
	public static function insertDigiManagerRole($pdo) {
		$roleid = self::createNewRoleEntry($pdo, "Manager");
		$permissionsYes = array(
		    'right_waiter',
		    'right_kitchen',
		    'right_bar',
		    'right_supply',
		    'right_paydesk',
		    'right_statistics',
		    'right_bill',
		    'right_products',
		    'right_manager',
		    'right_closing',
			'right_payallorders',
		    'right_dash',
		    'right_reservation',
		    'right_rating',
		    'right_changeprice',
		    'right_customers',
		    'right_timetracking',
		    'right_timemanager',
		    'right_tasks',
		    'right_tasksmanagement',
                    'right_delivery',
                    'right_customersview',
		    'right_pickups',
		    'right_cashop');
		self::setPermission($pdo, $roleid, $permissionsYes);
		return $roleid;
	}
	
	public static function insertWorkManagerRole($pdo) {
		$roleid = self::createNewRoleEntry($pdo, "Manager");
		$permissionsYes = array(
		    'right_waiter',
		    'right_paydesk',
		    'right_statistics',
		    'right_bill',
		    'right_products',
		    'right_manager',
		    'right_closing',
			'right_payallorders',
		    'right_dash',
		    'right_reservation',
		    'right_rating',
		    'right_changeprice',
		    'right_customers',
		    'right_timetracking',
		    'right_timemanager',
		    'right_tasks',
		    'right_tasksmanagement',
                    'right_delivery',
                    'right_customersview',
		    'right_pickups',
		    'right_cashop');
		self::setPermission($pdo, $roleid, $permissionsYes);
		return $roleid;
	}
	
	public static function insertDigiWaiterRole($pdo,$restmode) {
                $rolename = "Kellner";
                if ($restmode != 1) {
                        $rolename = "Standardrechte";
                }
		$roleid = self::createNewRoleEntry($pdo, $rolename);
		$permissionsYes = array(
		    'right_waiter',
		    'right_kitchen',
		    'right_bar',
		    'right_supply',
		    'right_paydesk',
		    'right_bill',
		    'right_closing',
			'right_payallorders',
		    'right_reservation',
		    'right_changeprice',
		    'right_timetracking',
		    'right_tasks',
                    'right_delivery',
                    'right_customersview',
		    'right_cashop'
		);
		self::setPermission($pdo, $roleid, $permissionsYes);
		return $roleid;
	}
	
	public static function insertWorkWaiterRole($pdo,$restmode) {
                $rolename = "Kellner";
                if ($restmode != 1) {
                        $rolename = "Standardrechte";
                }
		$roleid = self::createNewRoleEntry($pdo, $rolename);
		$permissionsYes = array(
			'right_waiter',
			'right_kitchen',
			'right_bar',
			'right_supply',
			'right_paydesk',
			'right_bill',
			'right_closing',
			'right_payallorders',
			'right_reservation',
			'right_changeprice',
			'right_timetracking',
			'right_tasks',
			'right_delivery',
			'right_customersview',
			'right_cashop'
		);
		self::setPermission($pdo, $roleid, $permissionsYes);
		return $roleid;
	}
	
	public static function insertCookRole($pdo,$restmode) {
                $rolename = "Koch";
                if ($restmode == 2) {
                        $rolename = "Lebensmittelverkauf";
                } else if ($restmode == 3) {
                        $rolename = "Tageskartenverkauf";
                } else if ($restmode == 4) {
                        $rolename = "Herrenfrisuren";
                }
		$roleid = self::createNewRoleEntry($pdo, $rolename);
		$permissionsYes = array(
		    'right_kitchen',
		    'right_bar',
		    'right_supply',
		    'right_timetracking',
		    'right_tasks',
                    'right_delivery',
		    'right_pickups'
		);
		self::setPermission($pdo, $roleid, $permissionsYes);
		return $roleid;
	}
}
