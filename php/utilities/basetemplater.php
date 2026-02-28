<?php

class Basetemplater {
	private $templatelines = array();
	private $submodules = array();
	private $closing = null;
	
	function __construct($templatelines,$submodules,$closing) {
		$this->templatelines = $templatelines;
		$this->submodules = $submodules;
		$this->closing = $closing;
	}

	public function createWithTemplate($pdo) {
		$seperatedTemplate = Templateutils::separateSubModule($this->templatelines, $this->submodules);
		
		$alldataitems = $this->getDataItems($pdo);
		
		$txt = "";
		foreach($alldataitems as $aDataEntry) {
			
			$subInstances = array();
			foreach($seperatedTemplate["submodulelines"] as $aSubModLineSection) {
				$subModClassName = $aSubModLineSection["submodule"]["classname"];
				$refl = new ReflectionClass($subModClassName);
				$subInstances[] = $refl->newInstance($aSubModLineSection["foundlines"],$aDataEntry["id"],$this->closing);
			}
		
			foreach($seperatedTemplate["templatelines"] as $aTemplatelLine) {
				$responsible = $aTemplatelLine["responsible"];
				$line = $aTemplatelLine["line"];

				if ($responsible == Templatedefs::$PARSE_SELF) {
					$preprocessedLine = Abbreviations::replaceDefsInTextLine($line);
					$txt .= $this->parse($pdo,$preprocessedLine,$aDataEntry);
				} else if ($responsible == Templatedefs::$PARSE_CONDITIONAL) {
					// parse line only if submodule has entries
					$whichSubmodule = $aTemplatelLine["submoduleindex"];
					$hasItems = call_user_func(array($subInstances[$whichSubmodule],"hasItems"),$pdo); 
					if ($hasItems) {
						$preprocessedLine = Abbreviations::replaceDefsInTextLine($line);
						$txt .= $this->parse($pdo,$preprocessedLine,$aDataEntry);
					}
				} else {
					$whichSubmodule = $aTemplatelLine["submoduleindex"];
					$txt .= call_user_func(array($subInstances[$whichSubmodule],"createWithTemplate"),$pdo); 
				}
			}
		}
		return $txt;
	}
	
	public function parse($pdo,$text,$data) {
		return $text;
	}
	
	public function getDataItems($pdo) {
		return array();
	}
	
	public function hasItems($pdo) {
		$items = $this->getDataItems($pdo);
		if (count($items) > 0) {
			return true;
		} else {
			return false;
		}
	}
	
	public static function getClassName() {
		return get_called_class();
	}
}
