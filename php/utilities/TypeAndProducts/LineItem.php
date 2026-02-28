<?php

/*
 * This class is used for storing items that are read from a file. Each
 * line of a file for category or product definition can be saved in 
 * an instance of this class for later further processing.
 * 
 * The instances of this class will be used for streaming them later
 * into tables of a database. Therefore this class is in package 
 * 'utilities'.
 */

define("ENTRY_TYPE", 0);
define("ENTRY_PRODUCT",1);
define("FOOD",0);
define("DRINK",1);
define("KIND_UNDEFINED",-1);

class LineItem {
	private $id = 0;
	private $depth = 0;  // indenting of the item in the text line
	private $name = "";  // name of the item (the text string in the line)
	private $reference = 0;  // reference to another LineItem instance (by $id)
	private $type = ENTRY_TYPE; // if it is a type (Drink,Meal,...) or product
	private $usekitchen = -1; // default (not set)
	private $usesupplydesk = -1; // default (not set)
	private $kind = KIND_UNDEFINED; // default
	private $printer = 1;
	private $fixbind = 0;
        // REM* for products it is possible to add lines that are not inline
        private array $multilines = array();

	/*
	 * Constructor
	 */
	function __construct($aDepth,$anId,$aName) {
		$this->id = $anId;
		$this->depth = $aDepth;
		$this->name = $aName;
		if (self::syntaxIsProductType($aName)) {
			$this->type = ENTRY_TYPE;
		} else {
			$this->type = ENTRY_PRODUCT;
			return;
		}
		// this is only of interest for categories
		// for products this content will be nonsens, but who cares...
		$parts = explode ("=",$aName,4);
		if (count($parts) > 1) {
			$this->name = $parts[0];
			$theUsePart = $parts[1];
			if(stristr($theUsePart, 'k') == true) {
				$this->usekitchen = 1;
			} else {
				$this->usekitchen = 0;
			}
			if(stristr($theUsePart, 'b') == true) {
				$this->usesupplydesk = 1;
			} else {
				$this->usesupplydesk = 0;
			}
			if(stristr($theUsePart, 'd') == true) {
				$this->kind = DRINK;
			} 
			if (stristr($theUsePart, 'f') == true) {
				$this->kind = FOOD;
			}
		}
		
		$this->printer = 1;
		if (count($parts) > 2) {
			try {
				$this->printer = intval($parts[2]);
				// REM* 1..4 is allowed for work printers
				if (($this->printer < 1) || ($this->printer > 4)) {
					$this->printer = 1;
				}
			} catch (Exception $e) {
				$this->printer = 1;
			}
		}
		
		$this->fixbind = 0;
		if (count($parts) > 3) {
			$p = strtolower($parts[3]);
			try {
				if (stristr($p, 'rd') !== FALSE) {
					$this->fixbind = 0;
				} else if (stristr($p, 'kd') !== FALSE) {
					$this->fixbind = 1;
				}
			} catch (Exception $ex) {
				$this->fixbind = 0;
			}
		}
	}
	
	function getPrinter() {
		return $this->printer;
	}
	function getFixBind() {
		return $this->fixbind;
	}
	
	function getId() {
		return $this->id;
	}
	function getDepth() {
		return $this->depth;
	}
	function getName() {
		return $this->name;
	}
	function getReference() {
		return $this->reference;
	}
	function getType() {
		return $this->type;
	}
	function getUseKitchen() {
		return $this->usekitchen;
	}
	function getUseSupplyDesk() {
		return $this->usesupplydesk;
	}
	function getKind() {
		return $this->kind;
	}

	function setId($anId) {
		$this->id = $anId;
	}
	function setPrinter($aPrinter) {
		$this->printer = $aPrinter;
	}
	function setFixBind($fixbindval) {
		$this->fixbind = $fixbindval;
	}
	function setDepth($aDepth) {
		$this->depth = $aDepth;
	}
	function setName($aName) {
		$this->name = $aName;
	}
	function setReference($aReference) {
		$this->reference = $aReference;
	}
	function setType($aType) {
		$this->type = $aType;
	}
	function setUseKitchen($aVal) {
		$this->usekitchen = $aVal;
	}
	function setUseSupplyDesk($aVal) {
		$this->usesupplydesk = $aVal;
	}
	function setKind($aVal) {
		$this->kind = $aVal;
	}
        public function addMultiline(string $aLine) {
                $aLineWithoutMultilineMarker = str_replace("\\\\", "", $aLine);
                $aLineWithNewLines = str_replace("{newline}","\n",$aLineWithoutMultilineMarker);
                $this->multilines[] = $aLineWithNewLines;
        }
        public function concatenateMultiline(string $aLine) {
                // REM* add to last entry
                $lastIndex = count($this->multilines) - 1;
                $aLineWithNewLines = str_replace("{newline}","\n",$aLine);
                $this->multilines[$lastIndex] = $this->multilines[$lastIndex] . $aLineWithNewLines;
        }
        public function getMultilines():array {
                return $this->multilines;
        }
	
	function toString() {
		return "T (" . $this->name . ",Depth:" . $this->depth . ",ID:" . $this->id . "): " . $this->type . " id:" . $this->id . " -> " . $this->reference;
	}
	
	private static function syntaxIsProductType($textline) {
		// REM* something like Speisen = KBF = 1 = RD
                $pattern = '/^[^=]*[ ]*[=]{1,1}[ ]*';
		// REM* "KBF", "KD" usw
                $pattern .= '[kbKB]{0,2}[fFdD]{1,1}[ ]*';
		// REM* optional: " = 1 ", "=1" (allow up to 6 printers)
                $pattern .= '([=]{1,1}[ ]*[1-6]{1,1}){0,1}';
		// REM* optional: " = RD", " = kd "
                $pattern .= '[ ]*([=]{1,1}[ ]*[rRkK]{1,1}[dD]{1,1}){0,1}[ ]*';
                $pattern .= '$/';

		$treffer = '';
		if (preg_match($pattern,$textline,$treffer)) {
			return true;
		} else {
			return false;
		}
	}
}