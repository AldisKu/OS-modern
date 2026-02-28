<?php

defined('LAYOUTER_HTML_MODE') || define ( 'LAYOUTER_HTML_MODE','0' );
class ColProperty {
	public static $LEFT = 0;
	public static $RIGHT = 1;
	public static $CENTER = 2;
	public static $COMPLETE = 3;
	
	public static $NORMAL = 0;
	public static $HIGH = 1;
	public static $WIDE = 2;
	public static $WIDEHIGH = 3;
	public static $HUGE = 4;
	
	public static $HTML = 0;
	public static $PR = 1;

	public $length = -1;

	public static $LF = 10;
	private static $escpos_doubleheight = array(27, 33, 16);
	private static $escpos_doublewidth = array(27, 33, 32);
	private static $escpos_doubleheightwidth = array(27, 33, 48);
	private static $escpos_double_off = array(27, 33, 0);
	private static $escpos_scale_huge = array(29, 33, 85); // 6x width/height = hex 55/dec 85
	private static $escpos_scale_off = array(29, 33, 0);
	
	function __construct($aColSpec) {
		if ($aColSpec != "") {
			$lengthOfACell = trim($aColSpec);
			$this->length = intval($lengthOfACell);
			if ($this->length < 0) {
				$this->length = 0;
			}
		}
	}
	
	function setLength($len) {
		if ($len< 0) {
			$this->length = 0;
		} else {
			$this->length = $len;
		}
	}
	
	public function render($varcontent,$just,$label,$varstyle,$useColonAfterLabel) {
		$endOfLabel = "";
		if ($useColonAfterLabel) {
			$endOfLabel = ":";
		}
		if (is_null($varcontent)) {
			$text = $label;
		} else {
			$text = $varcontent;
			if ($label != "") {
				$text = $label . $endOfLabel . $varcontent;
			}
		}
		$textInCp437 = self::asciiEncodeStringTo437($text);
		$contentLength = 0;
		$factor = 1;
		if (($varstyle == self::$NORMAL) || ($varstyle == self::$HIGH)) {
			$contentLength = strlen($textInCp437);
			$factor = 1;
		} else if ($varstyle == self::$WIDE) {
			$contentLength = strlen($textInCp437) * 2;
			$factor = 2;
		} else if ($varstyle == self::$WIDEHIGH) {
			$contentLength = strlen($textInCp437) * 2;
			$factor = 2;
		} else if ($varstyle == self::$HUGE) {
			$contentLength = strlen($textInCp437) * 6;
			$factor = 6;
		}	

		if ($just == ColProperty::$COMPLETE) {
			if ($contentLength > $this->length) {
				$textLines = array();
				$remaingText = $text;
				while (strlen($remaingText) > 0) {
					$textToSplit = $remaingText;					
					$maxLength = strlen($textToSplit) * $factor;
					$cutLength = min($maxLength,$this->length / $factor);
					$textLines[] = substr($textToSplit,0,$cutLength);
					$remaingText = substr($textToSplit,$cutLength);
				}
				$text = implode("\n", $textLines);
			}
			return $this->textOutDueToFont(0, $text, 0, $varstyle);
		}
		$textlen = $contentLength;
		if ($contentLength > $this->length) {
			$text = substr($text, 0, $this->length / $factor);
			$textlen = $this->length;
		}
		
		$freeSpace = $this->length - $textlen;
		
		$fillstart = 0;
		$fillend = $freeSpace;
		if ($just == self::$RIGHT) {
			$fillstart = intval($freeSpace);
			$fillend = 0;
		} else if ($just == self::$CENTER) {
			$fillstart = intval($freeSpace / 2);
			$fillend = $freeSpace - $fillstart;
		}
		$out = $this->textOutDueToFont($fillstart, $text, $fillend, $varstyle);

		return $out;
	}
        
	private function textOutDueToFont($fillspacestart,$text,$fillspaceend,$namestyle) {
                $start = str_repeat(" ", $fillspacestart);
                $end = str_repeat(" ", $fillspaceend);
                
		$htmltxt = '';
		$fillchar = '';
		if (($namestyle == self::$WIDE) || ($namestyle == self::$WIDEHIGH)) {
			$fillchar = ' ';
		} else if ($namestyle == self::$HUGE) {
			$fillchar = '     ';
		}
		for ($i=0;$i<strlen($text);$i++) {
			$htmltxt .= $text[$i] . $fillchar;
		}
		$htmltxt = $start . $htmltxt . $end;
		
		$prbytes = array();
		$textInCp437 = self::asciiEncodeStringTo437($text);
		$fontctrlstart = array();
		$fontctrlend = array();
		if ($namestyle == self::$WIDE) {
			$fontctrlstart = self::$escpos_doublewidth;
			$fontctrlend = self::$escpos_double_off;
		} else if ($namestyle == self::$HIGH) {
			$fontctrlstart = self::$escpos_doubleheight;
			$fontctrlend = self::$escpos_double_off;
		} else if ($namestyle == self::$WIDEHIGH) {
			$fontctrlstart = self::$escpos_doubleheightwidth;
			$fontctrlend = self::$escpos_double_off;
		} else if ($namestyle == self::$HUGE) {
			// scaling with GS ! n
			$fontctrlstart = self::$escpos_scale_huge;
			$fontctrlend = self::$escpos_scale_off;
		}
		
		for ($i=0;$i<$fillspacestart;$i++) {
			$prbytes[] = 32;
		}
		foreach ($fontctrlstart as $b) {
			$prbytes[] = $b;
		}
		for ($i=0;$i<strlen($textInCp437);$i++) {
			//$prbytes[] = $textInCp437[$i];
			$prbytes[] = strval(ord(strval($textInCp437[$i])));
		}
		foreach ($fontctrlend as $b) {
			$prbytes[] = $b;
		}
		for ($i=0;$i<$fillspaceend;$i++) {
			$prbytes[] = 32;
		}
		return array("html" => $htmltxt,"printer" => $prbytes);
	}
	
	private static function detect_encoding($string, $enc=null) { 
                if ($string == '') {
                        return null;
                }
		static $list = array('utf-8', 'iso-8859-1', 'windows-1251');

		foreach ($list as $item) {
		    $sample = iconv($item, $item, $string);
                    if ($sample == false) {
                        error_log("iconv is failing - are the character sets / locals installed?");
                    } else {
                        if (md5($sample) == md5($string)) { 
                            if ($enc == $item) { return true; } else { return $item; } 
                        }
                    }
		}
		return null;
	}


	public static function asciiEncodeStringTo437($string)
	{
		if ($string == "") {
				return "";
		}
		
		$sourceEncoding = self::detect_encoding($string);
		if (is_null($sourceEncoding)) {
				$sourceEncoding = 'utf-8';
		}
		$destinationEncoding = 'CP437'; // Extended ASCII - Codepage 437
		$stringout = iconv($sourceEncoding, $destinationEncoding, $string);
		if ($stringout == false) {
				$destinationEncoding = 'CP858';
				$stringout = iconv($sourceEncoding, $destinationEncoding, $string);
				if ($stringout != false) {
						$stringout = "\et\x13" . $stringout . "\et\0"; //activate Codepage 858 (No. 19(\x13)) then reset to Codepage 437 (No. 0(\0)) epson esc/pos codes
				} else {
						error_log("iconv is failing - are the character sets / locals installed?");
				}
		}
		return $stringout;
	}


	public function log() {
		return "COL(" . $this->length . ")";
	}
	
}

class TemplateTable {
	// col properties of each col
	private $cols;
	// total space for each line
	private $totalSpace;
	
	function __construct($tabspecline,$total) {
		$this->totalSpace = $total;
		// something like TAB:links-3:recht:mittig:5
		$this->cols = array();
		$colParts = explode(":",$tabspecline);
		// remove "TAB"
		array_shift($colParts);
		foreach($colParts as $aColSpec) {
			// "links-4"  or "mittig"
			$colProperty = new ColProperty($aColSpec);			
			$this->cols[] = $colProperty;
		}
		// now check number of elements without length
		$colsWithUnSpecifiedLength = 0;
		$colsTotalLength = 0;
		foreach($this->cols as $aCol) {
			if ($aCol->length == -1) {
				$colsWithUnSpecifiedLength++;
			} else {
				$colsTotalLength += $aCol->length;
			}
		}
		$roomToDistribute = $this->totalSpace - $colsTotalLength;
		$eachCol = intval($roomToDistribute / max($colsWithUnSpecifiedLength,1));
		
		$assignedCols = 0;
		foreach($this->cols as $aCol) {
			if ($aCol->length == -1) {
				if (($assignedCols+1) == $colsWithUnSpecifiedLength) {
					// rest
					$aCol->setLength($roomToDistribute - $eachCol * $assignedCols);
				} else {				
					$aCol->setLength($eachCol);
					$assignedCols++;
				}
			}
		}
	}

	public function getColSpec($colNo) {
		return $this->cols[$colNo];
	}
	
	public function log() {
		$log = "";
		foreach($this->cols as $aCol) {
			$log .= $aCol->log() . " ";
		}
		return $log;
	}
}

class TicketEntry {
	public $typeofentry;
	public $content;
        // it is important that the instance variables keep being public so that json_encode can work. It could be handles by 
        // JSONSerialize interface, but there are differences between PHP 7 and 8, so we use this way!
	
	function __construct($theType,$theContent) {
		$this->typeofentry = $theType;
			if ($theType == 'bytes') {
				$this->content = implode(',',$theContent);
			} else {
				$this->content = $theContent;
			}
	}
}

class Layouter {
	private static $outPr = array();
	private static $outHtml = "";
	
	private static $space = 40;
	private static $curCol = 0;
	private static $tableProperties;
	
	public static function layoutTicket($template,$data,$printersize,$isInPreviewMode) {
		self::$outPr = array();
		self::$outHtml = "";
		self::$space = $printersize;
		
		$lines = explode("\n", $template);
		self::loop($lines,$data,$isInPreviewMode);

                $htmltxt = str_replace(' ', '&nbsp;', self::$outHtml);
                $htmltxt = str_replace(':SPACE:', ' ', $htmltxt);
                
		if (LAYOUTER_HTML_MODE == 1) {
			return array("html" => $htmltxt, "printer" => "<pre>" . $htmltxt . "</pre>");
		} else {
			return array("html" => $htmltxt, "printer" => self::$outPr);
		}
	}
	
        private static function textToBytes(string $text) {
                $textInCp437 = ColProperty::asciiEncodeStringTo437($text);
                $prbytes = array();
                for ($i=0;$i<strlen($textInCp437);$i++) {
			$prbytes[] = strval(ord(strval($textInCp437[$i])));
		}
		return $prbytes;
        }
        
        private static function getDataLineCountainer($container,$marker) {
			$markerParts = explode('.', $marker);
			if (count($markerParts) == 1) {
					return $container[$marker];
			} else {
					$newContainer = $container[$markerParts[0]];
					array_shift($markerParts);
					$newMarker = implode('.',$markerParts);
					return self::getDataLineCountainer($newContainer, $newMarker);
			}
	}
	private static function loop($lines, $data, $isInPreviewMode) {
		$skippinguntil = null;
		
		foreach ($data as $dataline) {
			$linenumber = 0;

			while ($linenumber < count($lines)) {
				$t = $lines[$linenumber];
				$t = trim($t);

				$lineHadContent = false;
				if ($t != "") {
					$pos = stripos($t,'if:');
					if ($pos !== false) {
						if (($pos >= 0) && ($pos <= 7)) {
							$ifParts = explode(':',$t);
							$itemToCheck = $ifParts[1];
							$valueToCheck = $ifParts[2];
							$realValue = $dataline[$itemToCheck];
							if ($realValue != $valueToCheck) {
								$skippinguntil = $itemToCheck;
								++$linenumber;
								continue;
							}
						}
					}
					$pos = stripos($t,'fi:');
					if ($pos !== false) {
						if (($pos >= 0) && ($pos <= 7)) {
							$endifParts = explode(':',$t);
							$itemToCheck = $endifParts[1];
							if ($itemToCheck == $skippinguntil) {
								$skippinguntil = null;
								++$linenumber;
								continue;
							}
						}
					}
					
					if (!is_null($skippinguntil)) {
						++$linenumber;
						continue;
					}

					if (CommonUtils::startsWith($t,'#')) {
						++$linenumber;
						continue;
					}
					
					if (CommonUtils::startsWith($t, 'LOGO')) {
						$ticketEntry = new TicketEntry("img", "1");
                                                if ($isInPreviewMode) {
                                                        self::$outHtml .= '<img:SPACE:src="customer/logo.png":SPACE:alt="Logo":SPACE:style="width:300px;"/><br>';
                                                }
                                                self::$outPr[] = $ticketEntry;
                                                
						++$linenumber;
						continue;
					}

					$pos = strpos($t,'START');
					if ($pos !== false) {
						if (($pos >= 0) && ($pos <= 7)) {
							$loopDeclParts = explode(':',$t);
							$marker = trim($loopDeclParts[1]);
							$noItemsInLoopText = "";
							if (count($loopDeclParts) > 2) {
									$noItemsInLoopText = trim($loopDeclParts[2]);
							}
							$loopLines = self::collectLinesOfLoop($lines,$linenumber+1,$marker);
							$linesToSkip = count($loopLines) + 2;
							$datalineOfLoop = self::getDataLineCountainer($dataline,$marker);
							if (is_null($datalineOfLoop) || (count($datalineOfLoop) == 0)) {
							} else {
									self::loop($loopLines, $datalineOfLoop,$isInPreviewMode);
							}
							$linenumber += $linesToSkip;
							continue;
						}
					}

					$outContent = array();
					$theType = "";
					$parts = self::getParts($t);
					foreach($parts as $element) {
						$elemWithoutBrackets = substr($element, 1, strlen($element)-2);
						
						$partEval = self::evalElem($elemWithoutBrackets,$dataline);
						if ($partEval['linehascontent']) {
								$lineHadContent = true;
						}
						self::$outHtml .= $partEval["html"];
						$theType = $partEval['typeofentry'];
						if ($partEval['typeofentry'] == "bytes") {
							$outContent = array_merge($outContent,$partEval['bytes']);
						} else {
							$outContent = $partEval['bytes']; // may be also string for image reference
						}
					}
					if ($lineHadContent) {
						$ticketEntry = new TicketEntry($theType, $outContent);
						self::$outPr[] = $ticketEntry;
					}
				} else {
					self::$outPr[] = new TicketEntry("bytes",array(ColProperty::$LF));
					$lineHadContent = true;
				}
				if ($lineHadContent) {
					self::$outHtml .= "<br>";
				}
                                self::$curCol = 0;
				++$linenumber;
			}
		}
	}
	
	private static function collectLinesOfLoop($lines,$linenumber,$marker) {
		$looplines = array();
		while ($linenumber < count($lines)) {
			$aLine = $lines[$linenumber];
			$pos = stripos($aLine,'end:'.$marker);
			if ($pos !== false) {
				if (($pos >= 0) && ($pos < 5)) {
					return $looplines;
				} else {
					$looplines[] = $aLine;
				}
			} else {
				$looplines[] = $aLine;
			}
			$linenumber++;
		}
		return $looplines;
	}
	
	private static function getCommaSeparatedValuesOfElem($text) {
		$out = array();
		$parts = explode(":", $text);
		if (count($parts) > 1) {
			$valsPart = $parts[1];
			$out = explode(",",$valsPart);
		}
		return $out;
	}
	
	private static function evalElem($elemContent, $dataline) {
		if (self::startsWith($elemContent, "TAB")){
			self::setTableProperties($elemContent);
			return array("bytes" => array(),"html" => "", "linehascontent" => false,"typeofentry" => "bytes");
		} else if (self::startsWith($elemContent, "RAWD")) {
			$vals = self::getCommaSeparatedValuesOfElem($elemContent);
			return array("bytes" => $vals,"html" => "", "linehascontent" => true,"typeofentry" => "bytes");
		} else if (self::startsWith($elemContent, "RAWH")) {
			$vals = self::getCommaSeparatedValuesOfElem($elemContent);
			$decVals = array();
			foreach($vals as $v) {
				$decVals[] = hexdec(strtolower($v));
			}
			return array("bytes" => $decVals,"html" => "", "linehascontent" => true,"typeofentry" => "bytes");
		} else if (self::startsWith($elemContent, "LINIE")){
			$parts = explode(":", $elemContent);
			$charToUse = "-";
			if (count($parts) > 1) {
				$charToUse = $parts[1];
			}
			$fillLine = "";
			$outbytes = array();
			for ($i=0;$i<self::$space;$i++) {
				$fillLine .= $charToUse;
				$outbytes[] = ord($charToUse);
			}
			return array("bytes" => $outbytes,"html" => $fillLine, "linehascontent" => true,"typeofentry" => "bytes");
		} else if (self::startsWith(strtolower ($elemContent), "qrcode")) {
			$qrparts = explode(':', $elemContent);
			$scale = 100;
			if (count($qrparts) > 1) {
				$scale = $qrparts[1];
			}
			$billid = $dataline["id"];
			return array("bytes" => "2,$billid,$scale","html" => "<img:SPACE:src='img/exampleqrcode.png'/><br>", "linehascontent" => true,"typeofentry" => "img");
		} else if (self::startsWith(strtolower ($elemContent), "fiskalyqrcode")) {
			$qrparts = explode(':', $elemContent);
			$scale = 100;
			if (count($qrparts) > 1) {
				$scale = $qrparts[1];
			}
			$billid = $dataline["id"];
			return array("bytes" => "4,$billid,$scale","html" => "<img:SPACE:src='img/exampleqrcode.png'/><br>", "linehascontent" => true,"typeofentry" => "img");
		} else if (self::startsWith(strtolower ($elemContent), "waiterphoto")) {
			$parts = explode(':', $elemContent);
			$scale = 100;
			if (count($parts) > 1) {
				$scale = $parts[1];
			}
			if (isset($dataline["userid"])) {
				$userid = $dataline["userid"];
				if (!is_null($userid) && (intval($userid) > 0)) {
					return array("bytes" => "3,$userid,$scale","html" => "php/contenthandler.php?module=admin&command=getwaiterphotoforprint&userid=$userid", "linehascontent" => true,"typeofentry" => "img");		
				}
			}
		} else {
			$parts = explode(":", $elemContent);
			$placeholder = $parts[0];
			$realElem = self::substPlaceholder($placeholder,$dataline);
                        if (is_null($realElem)) {
                                // PHP 8.1 cannot handle explode(null)
                                $lines = array('');
                        } else {
                                $lines = explode("\n", $realElem);
                        }
			if (count($lines) == 1) {
				$colContent = self::evalOneElementOfALine($elemContent, $dataline);
			} else {
				// multiple lines
				$colContent = array("html" => "","printer" => array());
				$i = 0;
				foreach($lines as $contentLine) {
					$aDataLine = array($placeholder => $contentLine);
					$newColContent = self::evalOneElementOfALine($elemContent, $aDataLine);
					
					if (($i+1) < count($lines)) {
						$bytes = array_merge($colContent["printer"],$newColContent["printer"],array(ColProperty::$LF));
						$html = $colContent["html"] . $newColContent["html"] . "<br>";
					} else {
						$bytes = array_merge($colContent["printer"],$newColContent["printer"]);
						$html = $colContent["html"] . $newColContent["html"];
					}

					$colContent = array("html" => $html,"printer" => $bytes);
					
					++$i;
				}
			}
			self::$curCol++;
			
			$linehascontent = false;
			if (self::hasArrayWithNonSpaces($colContent["printer"])) {
					$linehascontent = true;
			}
			return array("bytes" => $colContent["printer"],"html" => $colContent["html"], "linehascontent" => $linehascontent, "typeofentry" => "bytes");
		}
	}
        
        private static function hasArrayWithNonSpaces(array $byteArr) {
                foreach($byteArr as $aByte) {
                        if ($aByte != 32) {
                                return true;
                        }
                }
                return false;
        }
	
	private static function evalOneElementOfALine($elemContent,$dataline) {
		$parts = explode(":", $elemContent);
		$placeholder = $parts[0];
		$realElem = self::substPlaceholder($placeholder,$dataline);
		if (!is_null($realElem)) {
				$realElem = str_replace("’","'",$realElem);
		}
		if (!is_null($realElem) && ($realElem == "")) {
			return array("html" => "","printer" => array());
		}

		$side = ColProperty::$LEFT;
		if (count($parts) > 1) {
			$just = trim($parts[1]);

			if (($just == "links") || ($just == "left")) {
				$side = ColProperty::$LEFT;
			} else if (($just == "rechts") || ($just == "right")) {
				$side = ColProperty::$RIGHT;
			} else if (($just == "mittig") || ($just == "center")) {
				$side = ColProperty::$CENTER;
			} else if (($just == "komplett") || ($just == "complete")) {
				$side = ColProperty::$COMPLETE;
			}
		}
		$label = "";
		$useColonAfterLabel = true;
		if (count($parts) > 2) {
			$label = $parts[2];
			if (($label != "") &&  ($label[strlen($label)-1] == " ")) {
				$useColonAfterLabel = false;
			}
		}
		$varstyle = ColProperty::$NORMAL;
		if (count($parts) > 3) {
			switch(trim($parts[3])) {
				case "hoch":
					$varstyle = ColProperty::$HIGH;
					break;
				case "breit":
					$varstyle = ColProperty::$WIDE;
					break;
				case "hochbreit":
					$varstyle = ColProperty::$WIDEHIGH;
					break;
				case "riesig":
					$varstyle = ColProperty::$HUGE;
					break;
			}
		}

		if (is_null(self::$tableProperties)) {
			throw new Exception("TAB-Element fehlt in Vorlage");
		}
		$colSpec = self::$tableProperties->getColSpec(self::$curCol);
		$colContent = $colSpec->render($realElem,$side,$label,$varstyle,$useColonAfterLabel);
		return $colContent;
	}
	
	private static function substPlaceholder($vartext,$dataline) {
		if ($vartext == '-') {
			return null;
		}

		if (isset($dataline[$vartext])) {
			return strval($dataline[$vartext]);
		} else {
			return "";
		}
	}
	
	private static function getParts($line) {
		$matches = null;
		preg_match_all('/\{[a-zA-Z0-9êèáà€?éöäüÖÄÜßâẽũĩõãỹṽñŵêẑûîôâŝĝĥĵŷĉẅëẗïḧÿẍẘůåẙēūīōāȳűőěřťžǔǒšďǧȟǰǩľčňȩŗţşḑģḩķļçņ`€<>#_\'":\-=\+%\.,\/\(\) ]*\}/', $line, $matches);
		return $matches[0];
	}
	
	private static function startsWith ($string, $startString) 
	{ 
		$len = strlen($startString); 
		return (substr($string, 0, $len) === $startString); 
	}
	
	private static function addToOut($t) {
		if ($t == "\n") {
			self::$outHtml .= "<br>";
			self::$outPr[] = ColProperty::$LF;
		} else {
			self::$outHtml .= $t;
			self::$outPr .= $t;
		}
	}
	
	private static function setTableProperties($elem) {
		self::$tableProperties = new TemplateTable($elem,self::$space);
		self::$curCol = 0;
	}
}

