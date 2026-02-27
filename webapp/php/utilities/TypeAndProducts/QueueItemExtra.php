<?php
/**
 * QueueItemExtra.php
 *
 * This file contains the class definition for QueueItemExtra. It will be used for the queueing, not for the article definition.
 *
 * @package    core
 * @subpackage utilities
 * @author     Stefan Pichel
 */

class QueueItemExtra {
	private $extraid;
	private $name;
	private $amount;

	function __construct($extraid, $name, $amount) {
		$this->extraid = $extraid;
		$this->name = $name;
		$this->amount = $amount;
	}

	public function addExtraToQueueExtrasDbTable($pdo, $queueid) {
		$sql = "INSERT INTO %queueextras% (`queueid`,`extraid`,`amount`,`name`) VALUES(?,?,?,?)";
		CommonUtils::execSql($pdo, $sql, array($queueid,$this->extraid,$this->amount,$this->name));
	}
}


