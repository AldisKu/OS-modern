<?php
require_once (__DIR__. '/../../dbutils.php');
/*
 * This class is used for reading in a file with the definition
 * of rooms and tables.
 * 
 * The format of such a file is:
 * roomname1: TableName1,TableName2, TableName3
 * roomname2: TableName4
 * roomname3: TableName5, TableName6, TableName7, TableName8
 * 
 */

class RoomsAndTableFileManager {

	var $dbutils;
	
	function __construct() {
		$this->dbutils = new DbUtils();
	}
	
	/*
	 * read in the roomdefinition file and store it into the
	 * db tables for rooms and resttables
	*/
	function readRoomTableDefinition($fileName) {
		// now really read the file so that content starts at index 1
		
		// First remove previous content
		$sql = "DELETE FROM `%room%`";
		$dbresult = $this->dbutils->performSqlCommand($sql);
		$sql = "DELETE FROM `%resttables%`";
		$dbresult = $this->dbutils->performSqlCommand($sql);
		
		// now fill in the correct values
		$roomid = 1;
		$handle = fopen ($fileName, "r");
		while (!feof($handle)) {
			$textline = fgets($handle);
			
			$parts = explode(':', $textline, 2);
			$roomName = trim($parts[0]);
			$tablesstring = trim($parts[1]);
			
			$sql = "INSERT INTO `%room%` (`id`, `roomname`) VALUES ($roomid, '$roomName')";
			$dbresult = $this->dbutils->performSqlCommand($sql);
			
			$tableparts = explode(',', $tablesstring);
			for ($tableindex=0;$tableindex<count($tableparts);$tableindex++) {
				$aTableName = trim($tableparts[$tableindex]);	
			
				$sql = "INSERT INTO `%resttables%` (`id` , `tableno`, `roomid`) VALUES (NULL , '$aTableName', '$roomid')";
				$dbresult = $this->dbutils->performSqlCommand($sql);
			}
			$roomid++;
		}
		fclose ($handle);
	}

	
}