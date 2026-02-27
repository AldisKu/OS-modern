<?php
require_once ('LineItem.php');
/*
 * This class is used for storing instance of LineItems. It shall
 * be something like the ArrayList<LineItem> in Java.
 */

class EntryList {
	private $content;

	/*
	 * Constructor
	 */
	function __construct() {
		$this->content = array();
	}
	
	function add($entry) {
		$this->content[] = $entry;
	}
	
	function get($index) {
		return $this->content[$index];
	}
	
	function remove($index) {
		array_splice($this->content, $index, 1);
	}
	
	function size() {
		return count($this->content);
	}
	
	function toHtmlString() {
		$out = "";
		for ($i=0;$i<count($this->content);$i++) {
			$item = $this->content[$i];
			$out = $out . $item->toString() . "<br>";
		}
		return $out;
	}
}