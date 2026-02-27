<?php

class ExtraItem {
	private $name = "";  // name of the item (the text string in the line)
	private $price = 0.00; // default (not set)
        private $purchasingprice = null;
	private $maxamount = 1;
	private $extraid = null;
	private $assignedProdIds = array();

	function __construct($pdo,$line) {
		try {
			$ofInterest = substr($line,1);
			$parts = explode ( '#' , $ofInterest );
			$this->name = trim($parts[0]);
			
			if (count($parts) == 1) {
				return;
			}
			
			$thePrice = self::getValueFromItemText($parts[1], 'Preis', 0.0);
			$aPrice = str_replace(',','.',$thePrice);
			if (is_numeric($aPrice)) {
				$this->price = floatval($aPrice);
			}
                        $thePurchasingPrice = self::getValueFromItemText($parts[1], 'Einkaufspreis', null);
                        if (!is_null($thePurchasingPrice)) {
                                $aPurchasingPrice = str_replace(',','.',$thePurchasingPrice);
                                $this->purchasingprice = $aPurchasingPrice;
                        } else {
                                $this->purchasingprice = null;
                        }
                        
			$this->maxamount = self::getValueFromItemText($parts[1], 'Max', 1);
			
			$assignedProdsTxt = self::getValueFromItemText($parts[1], 'Zugewiesen', 1);
			
 			$matches = array();
 			preg_match('/\(ID:([0-9]+)\)$/', $this->name,$matches,PREG_OFFSET_CAPTURE);
 			if (count($matches) > 0) {
 				$theMatch = $matches[0];
 				$this->extraid = intval(substr($theMatch[0],4,strlen($theMatch[0])-5));
 				$theMatchPos = $theMatch[1];
 				$this->name = trim(substr($this->name,0,$theMatchPos-1));
 			}

			$assignedProds = explode (',', $assignedProdsTxt );
			foreach($assignedProds as $assProd) {
				$assProd = trim($assProd);
				$matches = array();
				preg_match('/\(([0-9]+)\)$/', $assProd,$matches,PREG_OFFSET_CAPTURE);
				if (count($matches) > 0) {
					$theMatch = $matches[0];
					$theProdId = intval(substr($theMatch[0],1,strlen($theMatch[0])-2));
					
					$sql = "SELECT count(id) as countid FROM %products% WHERE id=? AND removed is null";
					$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
					$stmt->execute(array($theProdId));
					$row = $stmt->fetchObject();
					if ($row->countid > 0) {
						$this->assignedProdIds[] = $theProdId;
					}
				} else {
					$sql = "SELECT id FROM %products% WHERE longname=? AND removed is null";
					$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
					$stmt->execute(array($assProd));
					$allProdIds = $stmt->fetchAll(PDO::FETCH_ASSOC);

					foreach($allProdIds as $aProdId) {
						$this->assignedProdIds[] = $aProdId["id"];
					}
				}
			}
		} catch(Exception $e) {
			// leave default values
                        error_log("Exception in extras constructor: " . $e->getMessage());
		}
	}
	
	private static function getValueFromItemText($txt,$item,$default) {
		$txtparts = explode(';',$txt);
		foreach($txtparts as $itemvaluepair) {
			$itemvalarr = explode(":",$itemvaluepair);
			$itemintxt = trim($itemvalarr[0]);
			$value = trim($itemvalarr[1]);
			if ($item == $itemintxt) {
				return $value;
			}
		}
		return $default;
	}
	
	function getName() {
		return $this->name;
	}
	function getPrice() {
		return $this->price;
	}
        function getPurchasingPrice() {
		return $this->purchasingprice;
	}
	function getMaxamount() {
		return $this->maxamount;
	}
	function setName($aName) {
		$this->name = $aName;
	}
	function setPrice($aPrice) {
		$this->price = $aPrice;
	}
        function setPurchasingPrice($aPurchasingPrice) {
		$this->purchasingprice = $aPurchasingPrice;
	}
	function setMaxamount($maxamount) {
		$this->maxamount = $maxamount;
	}
	function getAssignedProdIds() {
		return $this->assignedProdIds;
	}
}