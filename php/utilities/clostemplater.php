<?php
class ClosTemplatestructure {
	private static $structure = array(
	    "ClosTemplater" => array(
		array("classname" => "ClosProdsReport","delimiter" => "PRODUKTE"),
		array("classname" => "ClosOverviewReport","delimiter" => "ZUSAMMENFASSUNG"),
		array("classname" => "ClosPaymentsReport","delimiter" => "ZAHLUNGSWEGE"),
	    ),
	    "ClosProdsReport" => array(),
	    "ClosOverviewReport" => array(),
	    "ClosPaymentsReport" => array(
		array("classname" => "ClosSingleReport","delimiter" => "ZAHLUNGSWEG")
		),
	    "ClosSingleReport" => array()
	);
	private static $structure_old = array(
	    "Templater" => array(
		    array("classname" => "Reqsection","delimiter" => "SS"),
		    array("classname" => "Testcasesreport","delimiter" => "TC"),
			array("classname" => "Matrixreporter","delimiter" => "MATRIX")
		),
	    "Reqsection" => array(
		    array("classname" => "Reqdetails","delimiter" => "RD")
		),
	    "Reqdetails" => array(
		    array("classname" => "Reqimgs","delimiter" => "REQIMG")
		),
	    "Reqimgs" => array(),
	    "Testcasesreport" => array(
		array("classname" => "Stepactionsreport","delimiter" => "TSACTIONS"),
		array("classname" => "Stepexpectedreport","delimiter" => "TSEXPECTED"),
		array("classname" => "Stepresultsreport","delimiter" => "TSRESULTS")
	    ),
	    "Matrixreporter" => array(),
	    "Stepactionsreport" => array(),
	    "Stepexpectedreport" => array(),
	    "Stepresultsreport" => array()
	);
	
	public static function getSubModules($reporterClass) {
		return self::$structure[$reporterClass];
	}
}


class ClosTemplater extends Basetemplater {
	private $templatelines = array();
	private $closid = null;
	private $closing = null;
	
	function __construct($templatelines, $closid, $closing) {
		$submodules = ClosTemplatestructure::getSubModules(self::getClassName());

		parent::__construct($templatelines,$submodules,$closing);
		$this->templatelines = $templatelines;
		$this->closid = $closid;
		$this->closing = $closing;
	}
	
	public function parse($pdo,$text,$data) {
		$currency = CommonUtils::getConfigValue($pdo, "currency", ".");
		$text = str_replace("{Einheit}",$currency,$text);
		$text = str_replace("{Id}",$data["id"],$text);
		$text = str_replace("{Tag}",$data["day"],$text);
		$text = str_replace("{Monat}",$data["month"],$text);
		$text = str_replace("{Jahr}",$data["year"],$text);
		$text = str_replace("{Stunde}",$data["hour"],$text);
		$text = str_replace("{Minute}",$data["minute"],$text);
		$text = str_replace("{Sekunde}",$data["second"],$text);
		$text = str_replace("{Bonanzahl}",$data["ticketcount"],$text);
		$text = str_replace("{Bemerkung}",$data["remark"],$text);
                $text = str_replace("{Verkauf}",$data["puresales"],$text);
                $text = str_replace("{Trinkgelder}",$data["tipssum"],$text);
                $text = str_replace("{Geldtransit}",$data["transitsum"],$text);
                $text = str_replace("{Bareinauslagen}",$data["casheswithouttransit"],$text);
                $text = str_replace("{DiffSollIst}",$data["diffsollist"],$text);
                $text = str_replace("{Bestellstornos}",$data["ordercancels"],$text);

		$imgurl = "contenthandler.php?module=printqueue&command=getLogoAsPngWithAlphaChannel";
		$text = str_replace("{Logo-klein}","<img src='$imgurl' style='width:100px;' />",$text);
		$text = str_replace("{Logo-gross}","<img src='$imgurl' style='width:300px;' />",$text);
		$text = str_replace("{Betriebsinfo}",$data["companyinfo"],$text);
		
		return $text;
	}
	
	public function getDataItems($pdo) {
		$decpoint = CommonUtils::getConfigValue($pdo, "decpoint", ".");
                
                $counting = $this->closing["counting"];
                $diffsollist = "-";
                if ($counting == 1) {
                       $diffsollist = number_format($this->closing["diffsollist"],2,$decpoint,'');
                }
		$companyinfo = str_replace("\n","<br>",$this->closing["companyinfo"]);
		$clos = $this->closing;
		$closdate = $clos["closingDate"];
		$datetimeparts = explode(" ", $closdate);
		$dateparts = explode("-",$datetimeparts[0]);
		$timeparts = explode(":",$datetimeparts[1]);
		return array(array("id" => $this->closid,
		    "day" => $dateparts[2],
		    "month" => $dateparts[1],
		    "year" => $dateparts[0],
		    "hour" => $timeparts[0],
		    "minute" => $timeparts[1],
		    "second" => $timeparts[2],
		    "ticketcount" => $clos["billcount"],
		    "remark" => $clos["remark"],
		    "decpoint" => $decpoint,
		    "companyinfo" => $companyinfo,
                    "tipssum" => number_format($clos["tipssum"],2,$decpoint,''),
                    "puresales" => number_format($clos["puresales"],2,$decpoint,''),
                    "transitsum" => number_format($clos["transitsum"],2,$decpoint,''),
                    "ordercancels" => number_format($clos["ordercancels"],2,$decpoint,''),
                    "casheswithouttransit" => number_format($clos["casheswithouttransit"],2,$decpoint,''),
                    "diffsollist" => $diffsollist
		));
	}
}

class Abbreviations {
	private static $defs = array();
	
	public static function addUserDefinition($txt) {
		$parts = explode('=',$txt,2);
		$key = explode('#',$parts[0])[1];
		$value = $parts[1];
		self::$defs[] = array("key" => $key,"value" => $value);
	}
	
	public static function replaceDefsInTextLine($txt) {
		foreach(self::$defs as $aDef) {
			$txt = str_replace($aDef["key"], $aDef["value"], $txt);
		}
		return $txt;
	}
}

class Templatedefs {
	public static $PARSE_SELF = 0;
	public static $PARSE_SUBMODULE = 1;
	public static $PARSE_CONDITIONAL = 2;
}

class Templateutils {
		
	public static function getTemplateAsLineArray($templateTxt) {
		$alllines = explode("\n", $templateTxt);
		return $alllines;
	}
	
	private static function hasStartDelimiterOfASubModule($line,$submodules) {
		$trimmedLine = trim($line);
		for ($i=0;$i<count($submodules);$i++) {
			if ($trimmedLine == ">>" . $submodules[$i]["delimiter"]) {
				return array(true,$i);
			}
		}
		return array(false,null);
	}
	
	public static function separateSubModule($alllines,$submodules) {
		$outlines = array();
		
		$subtemplates = array();
		$conditionalMarkups = array(); // line beginnings
		$i=0;
		foreach($submodules as $aSubModule) {
			$subtemplates[] = array("submodule" => $aSubModule,"foundlines" => array());
			// now store the conditional markup in associative array
			$conditionalMarkups[$aSubModule["delimiter"]] = $i++;
		}
		$activeParsingSubmoduleIndex = null; // index in $subtemplates
		$enddelimiter = null;

		$started = false;	// general for "is parsing lines for submodule"
		$submodulelineAdded = false;
		foreach($alllines as $aline) {
			$trimmedLine = trim($aline);
			$hasStartDel = self::hasStartDelimiterOfASubModule($trimmedLine, $submodules);
			
			if (CommonUtils::startsWith($trimmedLine,"//")) {
			} else if (preg_match("/#\[[a-zA-Z0-9_-]+\]=/i", $trimmedLine)) {
				Abbreviations::addUserDefinition($trimmedLine);
				
			} else if ($trimmedLine === $enddelimiter) {
				$started = false;
			} else if (($hasStartDel[0]) && !$started) {
				// at this point we know there is a startdelimiter in the line
				$activeParsingSubmoduleIndex = $hasStartDel[1];
				$started = true;
				$enddelimiter = "<<" . $subtemplates[$activeParsingSubmoduleIndex]["submodule"]["delimiter"];
			} else if ($started) {
				$subtemplates[$activeParsingSubmoduleIndex]["foundlines"][] = $aline;
				if (!$submodulelineAdded) {
					$outlines[] = array("responsible" => Templatedefs::$PARSE_SUBMODULE,"line" => $aline, "submoduleindex" => $activeParsingSubmoduleIndex);
					$submodulelineAdded = true;
				}
			} else {
				
				$lineParts = explode(":",$trimmedLine,3);
				if (count($lineParts) >= 3) {
					$markPart = $lineParts[1];
					if (array_key_exists($markPart, $conditionalMarkups)) {
						$conditionalLine = $lineParts[2];
						$outlines[] = array("responsible" => Templatedefs::$PARSE_CONDITIONAL,"line" => $conditionalLine, "submoduleindex" => $conditionalMarkups[$markPart]);
					} else {
						$outlines[] = array("responsible" => Templatedefs::$PARSE_SELF,"line" =>$aline);
					}
				} else {
					$outlines[] = array("responsible" => Templatedefs::$PARSE_SELF,"line" =>$aline);
				}
				$submodulelineAdded = false;
			}
		}
		return array("templatelines" => $outlines, "submodulelines" => $subtemplates);
	}
	
	public static function debugOutput($sepTemp, $className) {
		$templatelines = $sepTemp["templatelines"];
		$txt = "Start debugOutput for '$className' - Templatelines:\n";
		foreach($templatelines as $t) {
			$isSelf = ($t["responsible"] == Templatedefs::$PARSE_SELF ? true : false );
			switch($t["responsible"]) {
				case Templatedefs::$PARSE_SELF:
					$txt .= "SELF"; break;
				case Templatedefs::$PARSE_CONDITIONAL:
					$txt .= "COND"; break;
				case Templatedefs::$PARSE_SUBMODULE:
					$txt .= "SUB"; break;
				default:
					$txt .= "Undefined line marup"; break;
			}
			
			$txt .= "   " . $t["line"];
			if (!$isSelf) {
				$txt .= "    " . $t["submoduleindex"];
			}
			$txt .= "\r\n<br>";
		}
		return $txt;
	}
}

class ClosProdsReport extends Basetemplater {
	private $templatelines = array();
	private $closid = null;
	private $closing = null;
	
	function __construct($templatelines, $closid, $closing) {
		$submodules = ClosTemplatestructure::getSubModules(self::getClassName());

		parent::__construct($templatelines,$submodules,$closing);
		$this->templatelines = $templatelines;
		$this->closid = $closid;
		$this->closing = $closing;
	}
	
	public function parse($pdo,$text,$data) {
		$decpoint = CommonUtils::getConfigValue($pdo, "decpoint", ".");
		$currency = CommonUtils::getConfigValue($pdo, "currency", ".");
		$text = str_replace("{Einheit}",$currency,$text);
		$text = str_replace("{Anzahl}",$data["count"],$text);
		$text = str_replace("{Steuer}",$data["tax"],$text);
		$text = str_replace("{Produktname}",$data["productname"],$text);
		
		$price = str_replace(".", $decpoint, $data["price"]);
		$text = str_replace("{Einzelpreis}",$price,$text);
		$sumprice = str_replace(".", $decpoint, $data["sumprice"]);
		$text = str_replace("{Gesamtpreis}",$sumprice,$text);
		return $text;
	}
	
	public function getDataItems($pdo) {
		$decpoint = CommonUtils::getConfigValue($pdo, "decpoint", ".");
		$closprods = $this->closing["details"];
		$prods = array();
		foreach($closprods as $aProdEntry) {
			$tax = str_replace(".", $decpoint, $aProdEntry["tax"]);
			$prods[] = array("id" => $this->closid,
			    "count" => $aProdEntry["count"],
			    "productname" => $aProdEntry["productname"], 
			    "price" => $aProdEntry["price"],
			    "tax" => $tax,
			    "sumprice" => $aProdEntry["sumprice"]
			);
		};
		return $prods;
	}
}

class ClosOverviewReport extends Basetemplater {
	private $templatelines = array();
	private $closid = null;
	private $closing = null;
	
	function __construct($templatelines, $closid, $closing) {
		$submodules = ClosTemplatestructure::getSubModules(self::getClassName());

		parent::__construct($templatelines,$submodules,$closing);
		$this->templatelines = $templatelines;
		$this->closid = $closid;
		$this->closing = $closing;
	}
	
	public function parse($pdo,$text,$data) {
		$currency = CommonUtils::getConfigValue($pdo, "currency", ".");
		$text = str_replace("{Einheit}",$currency,$text);
		$text = str_replace("{Brutto}",$data["brutto"],$text);
		$text = str_replace("{Netto}",$data["netto"],$text);
		$text = str_replace("{Zahlungsweg}",$data["payment"],$text);
		$text = str_replace("{Vorgang}",$data["name"],$text);
                $text = str_replace("{Steuer}",$data["tax"],$text);
		return $text;
	}
	
	public function getDataItems($pdo) {
		$decpoint = CommonUtils::getConfigValue($pdo, "decpoint", ".");
		$closoverview = $this->closing["overview"];
		$prods = array();
		foreach($closoverview as $entry) {
			$brutto = str_replace(".", $decpoint, $entry["brutto"]);
			$name = $entry["name"];
			$netto = str_replace(".", $decpoint, $entry["netto"]);
			$payment = $entry["payment"];
			$tax = str_replace(".", $decpoint, $entry["tax"]);
                        
			$prods[] = array("id" => $this->closid,
			    "brutto" => $brutto,
			    "netto" => $netto,
			    "name" => $name,
			    "payment" => $payment,
                            "tax" => $tax
			);
		};
		return $prods;
	}
}


class ClosPaymentsReport extends Basetemplater {
	private $templatelines = array();
	private $closid = null;
	private $closing = null;
	
	function __construct($templatelines, $closid, $closing) {
		$submodules = ClosTemplatestructure::getSubModules(self::getClassName());

		parent::__construct($templatelines,$submodules,$closing);
		$this->templatelines = $templatelines;
		$this->closid = $closid;
		$this->closing = $closing;
	}
	
	public function parse($pdo,$text,$data) {
		$currency = CommonUtils::getConfigValue($pdo, "currency", ".");
		$text = str_replace("{Einheit}",$currency,$text);
		$text = str_replace("{Zahlungsweg}",$data["payment"],$text);
		return $text;
	}
	
	public function getDataItems($pdo) {
		$payments = $this->closing["paymenttaxessum"];
		$p = array();
		foreach($payments as $entry) {
			$p[] = array("payment" => $entry["payment"],
			    "id" => $entry["paymenttaxessum"]);
		}
		return $p;
	}
}

class ClosSingleReport extends Basetemplater {
	private $templatelines = array();
	private $payment = null;
	private $closing = null;
	
	function __construct($templatelines, $payment, $closing) {
		$submodules = ClosTemplatestructure::getSubModules(self::getClassName());

		parent::__construct($templatelines,$submodules,$closing);
		$this->templatelines = $templatelines;
		$this->payment = $payment;
		$this->closing = $closing;
	}
	
	public function parse($pdo,$text,$data) {
		$currency = CommonUtils::getConfigValue($pdo, "currency", ".");
		$text = str_replace("{Einheit}",$currency,$text);
		$text = str_replace("{Brutto}",$data["bruttosum"],$text);
		$text = str_replace("{Netto}",$data["nettosum"],$text);
		$text = str_replace("{Steuer}",$data["tax"],$text);
		return $text;
	}
	
	public function getDataItems($pdo) {
		$decpoint = CommonUtils::getConfigValue($pdo, "decpoint", ".");
		$payments = $this->payment;
		foreach($payments as $entry) {
			$tax = str_replace(".", $decpoint, $entry["t"]);
			$bruttosum = str_replace(".", $decpoint, $entry["bruttosum"]);
			$nettosum = str_replace(".", $decpoint, $entry["nettosum"]);
			$p[] = array("id" => "dummy",
			    "tax" => $tax,
			    "bruttosum" => $bruttosum,
			    "nettosum" => $nettosum
			);
		}
		return $p;
	}
}