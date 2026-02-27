<?php
require_once (__DIR__. '/../dbutils.php');

class Sorter {
	var $dbutils;

	function __construct() {
		$this->dbutils = new DbUtils();
	}
	
	public function getMaxprodSortOfType($pdo,$typeid) {
		$sql = "SELECT MAX(sorting) as maxsort FROM %products% WHERE removed is null AND category=?";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array($typeid));
		$el = $stmt->fetchObject();
		return(intval($el->maxsort));
	}
	
	private function getTypeidOfProd($pdo,$prodid) {
		$sql = "SELECT category from %products% WHERE id=?";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array($prodid));
		$el = $stmt->fetchObject();
		$typeid = $el->category;
		return $typeid;
	}
	

	public function setMaxSortingForProdId($pdo,$id) {
		$typeid = $this->getTypeidOfProd($pdo, $id);
		
		$maxsorting = ($this->getMaxprodSortOfType($pdo, $typeid) + 1);
		
		$sql = "UPDATE %products% SET sorting=? WHERE id=?";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array($maxsorting,$id));
	}
	
	private function getSortingOfProduct($pdo,$prodid) {
		$sql = "SELECT sorting FROM %products% WHERE id=?";
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array($prodid));
		$el = $stmt->fetchObject();
		return (intval($el->sorting));
	}

	public function sortup($pdo,$prodid) {
		try {
			// which sorting has the comment to delete?
			$sorting = $this->getSortingOfProduct($pdo, $prodid);
				
			if ($sorting < 0) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
				return;
			}
			if ($sorting == 0) {
				// finished
				return;
			}
	
			$typeid = $this->getTypeidOfProd($pdo, $prodid);
			
			// get comment before
			$sql = "SELECT id FROM %products% WHERE sorting=? AND category=? AND removed is null";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($sorting - 1,$typeid));
			$row = $stmt->fetchObject();
			$previousId = $row->id;
	
			// change these two prods in their ordering
			$sql = "UPDATE %products% SET sorting=? WHERE id=?";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($sorting,$previousId));
				
			$sql = "UPDATE %products% SET sorting=? WHERE id=?";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($sorting-1,$prodid));
		}
		catch (PDOException $e) {
			return;
		}
	}
	
	public function sortdown($pdo,$prodid) {
		try {		
			// which sorting has the comment to delete?
			$sorting = $this->getSortingOfProduct($pdo, $prodid);
		
			if ($sorting < 0) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
				return;
			}
				
			// is it at end of list?
			$typeid = intval($this->getTypeidOfProd($pdo, $prodid));
			$maxSorting = $this->getMaxprodSortOfType($pdo, $typeid);

			if (($maxSorting == 0) || ($maxSorting == $sorting)) {
				// finished
				return;
			}
		
			// get prod afterwards
			$sql = "SELECT id FROM %products% WHERE sorting=? AND category=? AND removed is null";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($sorting + 1,$typeid));
			$row = $stmt->fetchObject();
			$nextId = $row->id;
		
			// change these two prods in their ordering
			$sql = "UPDATE %products% SET sorting=? WHERE id=?";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($sorting,$nextId));
			
			$sql = "UPDATE %products% SET sorting=? WHERE id=?";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($sorting+1,$prodid));
		}
		catch (PDOException $e) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
		}
	}
	
	public function delproduct($pdo,$prodid) {
		try {
			// which sorting has the prod to delete?
			$sorting = $this->getSortingOfProduct($pdo, $prodid);
			$typeid = intval($this->getTypeidOfProd($pdo, $prodid));
				
			if ($sorting < 0) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
				return;
			}
				
			// delete the prod
			$sql = "UPDATE %products% SET removed=?,sorting=? WHERE id=?";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array(1,null,$prodid));
			
			// REM* TODO: HIST!!!
			
			// subtract all sortings by one higher then the deleted sorting index
			$sql = "SELECT id,sorting FROM %products% WHERE sorting>? AND removed is null AND category=?";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($sorting,$typeid));
				
			$result = $stmt->fetchAll();
			
			foreach($result as $row) {
				$theId = $row['id'];
				$theSort = intval($row['sorting'])-1;
				$sql = "UPDATE %products% SET sorting=? WHERE id=?";
				$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
				$stmt->execute(array($theSort,$theId));
			}
		}
		catch (PDOException $e) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
		}
	}	

	public function resortAfterProduct($pdo,$prodid) {
		try {
			// which sorting has the prod to delete?
			$sorting = $this->getSortingOfProduct($pdo, $prodid);
			$typeid = intval($this->getTypeidOfProd($pdo, $prodid));
		
			if ($sorting < 0) {
				echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
				return;
			}
				
			// subtract all sortings by one higher then the deleted sorting index
			$sql = "SELECT id,sorting FROM %products% WHERE sorting>? AND removed is null AND category=?";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
			$stmt->execute(array($sorting,$typeid));
		
			$result = $stmt->fetchAll();
				
			foreach($result as $row) {
				$theId = $row['id'];
				$theSort = intval($row['sorting'])-1;
				$sql = "UPDATE %products% SET sorting=? WHERE id=?";
				$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
				$stmt->execute(array($theSort,$theId));
			}
		}
		catch (PDOException $e) {
			echo json_encode(array("status" => "ERROR", "code" => ERROR_GENERAL_DB_NOT_READABLE, "msg" => ERROR_GENERAL_DB_NOT_READABLE_MSG));
		}
	}
}
