<?php
require_once ('LineItem.php');
require_once ('EntryList.php');
require_once ('ExtraItem.php');
require_once ('ProductEntry.php');
require_once (__DIR__. '/../../dbutils.php');
require_once (__DIR__. '/../../products.php');
/*
 * This class is used for reading in a file with the definition
 * of types (drink, meal, etc.) and products.
 * 
 * The file uses the space indenting for assignment to higher level items.
 * If an item has no deeper elements it is considered a product, not
 * a type.
 * 
 * A sample can look like
 * 
 * Type1
 * 		Product A
 * 		Product B
 * 		Type 3
 * 			Product C
 * 			Type 7
 * 				Product D
 * 				Product E
 * 			Type 8
 * 				Product F
 * 		Type 4
 * 			Product G
 * 
 * A type can have additional attributes after "=":
 * 	- nothing: do not run the products in this category over kitchen and supplydesk
 * 			(for example: waiter mixes everything in bar and delivers at same time)
 *  - B: do not run the products in this category, but over supplydesk
 *  		(for example: drinks are mixed by waiter at bar, not in kitchen)
 *  - KB: run all products in this category over kitchen and supplydesk
 *  		(the default, if not overwritten by "type = " in higher category)
 *  - K: over kitchen, not over supplydesk
 *  		(for example: cook delivers product himself)
 */

class TypeAndProductFileManager {
	
	private $entries;   // of type EntryList  (in the beginning all items, later the types)
	private $leafArray;  // later filled by all leafs (= products) when filtered out from $entries;
	private $extras;
	
	private $nextIdOfProdType = 1;
	
	var $dbutils;
	
	function __construct() {
		$this->dbutils = new DbUtils();
	}
	
	/*
	 * Look at the beginning of a line and count the number of spaces or tabs
	 */
	private function intendingOfText($text) {
		$charCounter = 0;
		while (($text[$charCounter] == ' ') || ($text[$charCounter] == "\t")) {
			$charCounter++;
		}
		return $charCounter;
	}
	
	private function startsWith($aText, $needle)
	{
		return $needle === "" || strpos($aText, $needle) === 0;
	}
	
	private function findNextIdOfProdType($pdo) {
		$sql = "SELECT MAX(id) as maxid FROM %prodtype%";
		$res = CommonUtils::fetchSqlAll($pdo, $sql);
		$this->nextIdOfProdType = 1;
		if (count($res) > 0) {
			$this->nextIdOfProdType = intval($res[0]["maxid"]) + 1;
		}
	}
	
	public static function removeExtras($pdo) {
                CommonUtils::execSql($pdo, "DELETE FROM %extrasprods%", null);
                CommonUtils::execSql($pdo, "UPDATE %extras% SET removed=?", array(1));
	}
	
        private function removeLastBackslash(string $aText):string {
                $textlen = strlen($aText);
                if (CommonUtils::endsWith($aText, "\\")) {
                        $aText = substr($aText, 0, $textlen-1);
                }
                return $aText;
        }
	/*
	 * read in the "Speisekarte.txt"
	* put the content in the array list "EntryList" as it is
	* without any modifications
	*/
	private function parseContent($pdo,$speisekarte) {
		// remove old content if any
		$this->entries = new EntryList();
		$this->extras = array();
	
		self::removeExtras($pdo);

		// get max number of prodtype id -> the old ones will be kept!
		$index = $this->nextIdOfProdType;
		
		$lines = explode("\n", $speisekarte);

		$previousDepth = 0;
                $lastLineItem = null;
                $appendMode = false;
		for ($i=0;$i<count($lines);$i++) {
			$textline = $lines[$i];
			$cleanLine = str_replace(" ", "", $textline);
			if (($this->startsWith($textline,'!')) && (strlen($cleanLine) > 0)) {
				$this->extras[] = $textline;
                        
			} else if (strlen($cleanLine) > 0) {
                                $depth = $this->intendingOfText($textline);
                                if ($depth > ($previousDepth+1)) {
                                        return array("status" => "ERROR","code" => PARSE_ERROR,"msg" => PARSE_ERROR_MSG,"line" => $textline);
                                } else {
                                        $previousDepth = $depth;
                                }
                                $textline = trim($textline);
                                if ($appendMode) {
                                        $lastLineItem->concatenateMultiline($this->removeLastBackslash($textline));
                                        if (!CommonUtils::endsWith($textline, "\\")) {
                                                $appendMode = false;
                                        }
                                } else if ($this->startsWith($textline, "\\\\") && !is_null($lastLineItem)) {
                                        $lastLineItem->addMultiline($this->removeLastBackslash($textline));
                                        
                                        if (CommonUtils::endsWith($textline, "\\")) {
                                                $appendMode = true;
                                        }
                                } else if(!($this->startsWith($textline,'#'))) {
                                        $lastLineItem = new LineItem($depth,$index,$textline);
                                        if (is_null($lastLineItem)) {
                                                return array("status" => "ERROR","code" => PARSE_ERROR,"msg" => PARSE_ERROR_MSG,"line" => $textline);
                                        } else {
                                                $this->entries->add($lastLineItem);
                                                $index++;
                                        }
                                }
			}
		}
		return array("status" => "OK");
	}
	
	/*
	 * if something is a leaf node it means that it has no
	 * more susequent items with a higher depth.
	 * 
	 * In the sense of the Speisekarte this is a 'product' and
	 * not a 'type'.
	 */
	private function isLeafNode($index) {
		$currentEntry = $this->entries->get($index);
		if ($currentEntry->getType() == ENTRY_TYPE) {
			return false;
		} else {
			return true;
		}
	}
	
	/*
	 * This is like if the entry at index $abIndex would have
	 * been removed from the array. (In fact this will be done
	 * after call of this method probably).
	 * This means that all the entries later will have an index
	 * that has to be decremented by 1. In this case index=id
	 * (the index is stored in the id).
	 * If lower elements have references to the elements with 
	 * decreased indeces, they have to lower their reference as
	 * well.
	 */
	private function reduceIndex($abIndex) {
		// now decrease the index and references if the refer to the reduced part
		$theEntryToRemove = $this->entries->get($abIndex);
		$idOfEntryToRemove = intval($theEntryToRemove->getId());
                for ($i=$abIndex;$i<$this->entries->size();$i++) {
                    $entry = $this->entries->get($i);
                    $theId = intval($entry->getId()); // maybe not necessary going by id
                    $ref = intval($entry->getReference());
                    $entry->setId($theId-1);
                    if ($ref >= $idOfEntryToRemove) {
                            $entry->setReference($ref-1);
                    }
                }
	}
	
	/*
	 * Recursive called function!
	 * 
	 * Find all subitems (with a deeper indenting) and start this
	 * method on these deeper items.
	 * 
	 * This method sets the references, so that it has no return value.
	 */
	private function findAllSubItemsOfIndex($index,$useKitchen,$useSupplyDesk,$kind,$usePrinter,$fixbind) {
		$currentEntry = $this->entries->get($index);
		$currentId = $currentEntry->getId(); // maybe currentId = $index enough?
		$currentDepth = $currentEntry->getDepth();

		// now set the type (leaf -> product, not lead -> type)
		if ($this->isLeafNode($index)) {
			$currentEntry->setType(ENTRY_PRODUCT);
		} else {
			$currentEntry->setType(ENTRY_TYPE);
			$useKitOfEntry = $currentEntry->getUseKitchen();
			$useSupplyOfEntry = $currentEntry->getUseSupplyDesk();
			$kindOfEntry = $currentEntry->getKind();
			$printer = $currentEntry->getPrinter();
			$fixbindOfEntry = $currentEntry->getFixBind();
			
			if ($useKitOfEntry < 0) {
				$this->entries->get($index)->setUseKitchen($useKitchen);
			} else {
				$useKitchen = $useKitOfEntry;
			}
			if ($useSupplyOfEntry < 0) {
				$this->entries->get($index)->setUseSupplyDesk($useSupplyDesk);
			} else {
				$useSupplyDesk = $useSupplyOfEntry;
			}
			if ($kindOfEntry == KIND_UNDEFINED) {
				$this->entries->get($index)->setKind($kind);
			} else {
				$kind = $kindOfEntry;
			}
			if ($printer < 0) {
				$this->entries->get($index)->setPrinter($printer);
			} else {
				if (!is_null($printer)) {
					$usePrinter = $printer;
				} else {
					$usePrinter = 1;
				}
			}
			if ($fixbindOfEntry < 0) {
				$this->entries->get($index)->setFixBind($fixbind);
			} else {
				$fixbind = $fixbindOfEntry;
			}
		}
		
		// now look at all the subsequent items after this item
		for ($nextIndex=($index+1);$nextIndex<$this->entries->size();$nextIndex++) {
			$nextEntry = $this->entries->get($nextIndex);
			$nextDepth = $nextEntry->getDepth();
			
			if ($nextDepth == ($currentDepth + 1)) {
				// directly under currentDepth
				$nextEntry->setReference($currentId);
				$this->findAllSubItemsOfIndex($nextIndex,$useKitchen,$useSupplyDesk,$kind,$usePrinter,$fixbind); // recursive!
			} else if ($nextDepth <= $currentDepth) {
				// same depth level or higher - is no sub-element
				break;
			}
		}
	}

	
	/*
	 * Sort out the leafs from the entries. In the entries only the 
	 * types shall be left. The leafs (products) shall go to another
	 * array.
	 */
	private function sortOutLeafs() {
		$this->leafArray = new EntryList(); // is of same type
		for ($index=0;$index < $this->entries->size();$index++) {
			$currentEntry = $this->entries->get($index);
			if ($currentEntry->getType() == ENTRY_PRODUCT) {
				$this->leafArray->add($currentEntry);
				// now modify the references from current index+1 by a ref-1
				$this->reduceIndex($index);
				
				$this->entries->remove($index);
				$index--;
			}
		}
	}
	
	/*
	 * Fill the table prodtype in the DB. The access is done via the 
	 * view name.
	 */
	private function fillProdTypeDbTable ($pdo) {
		for ($i = 0;$i < $this->entries->size(); $i++) {
			$usekitchen = 1; // default
			$usesupplydesk = 1; // default
			$printer = 1; // default
			$fixbind = 0; // default: Raumdrucker
			$theEntry = $this->entries->get($i);
			
			$parts = explode(';', $theEntry->getName(), 2);
			//$parts = explode(';', "123 ; 45", 2);
			$theNameEntry = $parts[0];
			$usekitchen = $theEntry->getUseKitchen();
			$usesupplydesk = $theEntry->getUseSupplyDesk();
			$kind = $theEntry->getKind();
			$printer = $theEntry->getPrinter();
			$fixbind = $theEntry->getFixBind();
			
			$theProdTypeName = trim($theNameEntry);
			$theRefId = $theEntry->getReference();
			$id = $theEntry->getId();

			$insertSql = "INSERT INTO `%prodtype%` (`id`, `name`, `reference`, `usekitchen`, `usesupplydesk`, `kind`,`printer`,`fixbind`) VALUES (?,?,?,?,?,?,?,?)";
			$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($insertSql));
			try {
				if ($theEntry->getDepth() > 0) {
					$stmt->execute(array($id,$theProdTypeName,$theRefId,$usekitchen,$usesupplydesk,$kind,$printer,$fixbind));
				} else {
					$stmt->execute(array($id,$theProdTypeName,null,$usekitchen,$usesupplydesk,$kind,$printer,$fixbind));
				}
			} catch (Exception $e) {
				return array("status" => "ERROR","code" => PARSE_ERROR,"msg" => PARSE_ERROR_MSG,"line" => $theProdTypeName);
			}
		}
		return array("status" => "OK");
	}
        
        private function clearAllSortings($pdo) {
                CommonUtils::execSql($pdo, "UPDATE %products% SET sorting='0' WHERE (removed is null) or (removed='0')", null);
        }
        
	
	private function fillProductDbTable($pdo,$leafArray) {
		$sortArr = array();
		
		$changedProdIds = array();
		
		$prodIdSet = array();
                
                $this->clearAllSortings($pdo);
		
		for ($i=0;$i < $leafArray->size(); $i++) {
			$theLeafEntry = $leafArray->get($i);
			$product = new ProductEntry();
			$ret = $product->parse($theLeafEntry->getName(),$theLeafEntry->getMultilines());
			if ($ret["status"] != "OK") {
				return $ret;
			}
                        
                        
                        
                        $prodid = $product->getProdId();
			if (array_key_exists($prodid, $prodIdSet)) {
				$prodid = null;
			} else {
				$prodIdSet[$prodid] = $product;
			}                     
                        
			
			$prodimageid = $product->getProdImageId();
			
			$sql = "SELECT id FROM %prodimages% WHERE id=?";
			$prodimages = CommonUtils::fetchSqlAll($pdo, $sql, array($prodimageid));
			if (count($prodimages) == 0) {
				$prodimageid = null;
			}
			
			$category = $theLeafEntry->getReference();
			
			$sorting = 0;
			if (array_key_exists($category, $sortArr)) {
			    $sorting = $sortArr[$category] + 1;
			    $sortArr[$category] = $sorting;
			} else {
			    $sortArr[$category] = 0;
			}

			$isNewProd = true;
			if (!is_null($prodid)) {
				$sql = "SELECT count(id) as countid FROM %products% WHERE id=?";
				$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
				$stmt->execute(array($prodid));
				$row = $stmt->fetchObject();
				$countid = $row->countid;
				if ($countid > 0) {
					$isNewProd = false;
				}
			}
                        $product->setCategory($category);
			if ($isNewProd) {
                                $newProdid = $product->createProductInDb($pdo);
                                CommonUtils::execSql($pdo, "UPDATE %products% SET sorting=?,removed=? WHERE id=?", array($sorting,null,$newProdid));
				HistFiller::createProdInHist($pdo, $newProdid);
			} else {
				
                                if ($product->isDifferentFromDB($pdo, $prodid)) {   
                                        $changedProdIds[] = $prodid;
                                }
                                $product->applyProductInDb($pdo);
                                CommonUtils::execSql($pdo, "UPDATE %products% SET sorting=?,removed=? WHERE id=?", array($sorting,null,$prodid));
			}
		}
		return (array("status" => "OK","changedprods" => $changedProdIds));
	}

	
	function manageSpeisekarte($pdo,$content) {
		$this->findNextIdOfProdType($pdo);
		// first remove previous content
		CommonUtils::execSql($pdo, "UPDATE %products% SET removed='1'", null);
		CommonUtils::execSql($pdo, "UPDATE %prodtype% SET removed='1'", null);

				
		$ret = $this->parseContent($pdo,$content);
		if ($ret["status"] != "OK") {
			return $ret;
		}

	
		
		for ($i=0;$i<$this->entries->size();$i++) {
			$anEntry = $this->entries->get($i);

			if ($anEntry->getDepth() == 0) {
				// highest level
				$this->findAllSubItemsOfIndex($i,1,1,FOOD,null,0);
			}
		}

		$this->sortOutLeafs();
		
		$ret = $this->fillProdTypeDbTable($pdo);
		if ($ret["status"] != "OK") {
			return $ret;
		}
		$ret = $this->fillProductDbTable($pdo,$this->leafArray);
		$changedprodids = $ret["changedprods"]; 

		// now add the extras
		$prodInstance = new Products();

		foreach($this->extras as $anExtraLine) {
			$anExtra = new ExtraItem($pdo,$anExtraLine);
			$prodInstance->createExtraCore($pdo, $anExtra->getName(), $anExtra->getPrice(), $anExtra->getPurchasingPrice(),$anExtra->getMaxamount(), $anExtra->getAssignedProdIds());
                        
			$changedExtrasProdIds = $anExtra->getAssignedProdIds();
			foreach($changedExtrasProdIds as $anId) {
				if (!in_array($anId, $changedprodids)) {
					$changedprodids[] = $anId;
				}
			}
		}
		
		foreach ($changedprodids as $anId) {
			try {
				HistFiller::updateProdInHist($pdo, $anId);
			} catch (Exception $ex) {
				
			}
		}
		unset($ret["changedprods"]);
                
        CommonUtils::setSendToShowRoomNecessary($pdo, true);
                
		return $ret;
	}
}