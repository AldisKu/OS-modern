<?php
require_once ('dbutils.php');
require_once ('commonutils.php');
require_once 'queuecontent.php';
class Guestsync {
	public static function handleCommand($command) {
		
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		
		if($command == 'sync') {
			$ret = self::sync($pdo);
			echo json_encode($ret);
		}
	}
	
	public static function sync($pdo) {
		if (!self::shallWeSync($pdo)) {
			return array("status" => "OK","msg" => '');
		}
		
		$url = CommonUtils::getConfigValue($pdo, 'guesturl', "");
		if (is_null($url)) {
			return array("status" => "OK","msg" => "");
		} else {
			$url = trim($url);
			if ($url == "") {
				return array("status" => "OK","msg" => "");
			}
		}
		$guestcode = trim(CommonUtils::getConfigValue($pdo, 'guestcode', ''));
		
		if ($guestcode == '') {
			$ret = array("status" => "ERROR","msg" => "Guest system access code not set - stopping here for security reasons!");
			return $ret;
		}
                
                UsedFeatures::noteUsedFeature($pdo, UsedFeatures::$Guestsystem);
		
		$timezone = CommonUtils::getConfigValue($pdo, 'timezone', "Europe/Berlin");
		$dailycode = CommonUtils::getConfigValue($pdo, 'dailycode', "dfhdztfghgjzt");
		
		$resttables = json_encode(Roomtables::getTablesForGuestsystem($pdo));
		// id,name,code in an array
		
		$prodTypes = self::getMenuForGuests($pdo);
		$types = json_encode($prodTypes["types"]);
		$products = json_encode($prodTypes["products"]);
        $extras = json_encode(self::getExtras($pdo));
		
		$currency = CommonUtils::getConfigValue($pdo, 'currency', 'Euro');
		$guesttheme = CommonUtils::getConfigValue($pdo, 'guesttheme', 8);
		$decpoint = CommonUtils::getConfigValue($pdo, 'decpoint', '.');
		
		$askdaycode = CommonUtils::getConfigValue($pdo, 'askdaycode', "1");
		$asktablecode = CommonUtils::getConfigValue($pdo, 'asktablecode', "1");
		$guesttimeout = CommonUtils::getConfigValue($pdo, 'guesttimeout', "5");
                $guestarticleconfirm = CommonUtils::getConfigValue($pdo, 'guestarticleconfirm', "1");
		
		$logo = '';
		$sql = "SELECT setting FROM %logo% WHERE name=? and setting is not null";
		$resultNiceLogo = CommonUtils::fetchSqlAll($pdo, $sql, array('nicelogoimg'));
		if (count($resultNiceLogo) > 0) {
				$logo = base64_encode($resultNiceLogo[0]["setting"]);
		} else {
				$resultBonLogo = CommonUtils::fetchSqlAll($pdo, $sql, array('logoimg'));
				if (count($resultBonLogo) > 0) {
						$logo = base64_encode($resultBonLogo[0]["setting"]);
				}
		}
		
		$prodimages = self::getImagesForGuestProducs($pdo);

		$transferdata = array(
			"timezone" => $timezone,
			"dailycode" => $dailycode,
			"resttables" => $resttables,
			"guestcode" => $guestcode,
			"types" => $types,
			"products" => $products,
			"extras" => $extras,
			"currency" => $currency,
			"guesttheme" => $guesttheme,
			"guestarticleconfirm" => $guestarticleconfirm,
			"decpoint" => $decpoint,
			"askdaycode" => $askdaycode,
			"asktablecode" => $asktablecode,
			"guesttimeout" => $guesttimeout,
			"logo" => $logo,
			"prodimages" => $prodimages
		);

		$data = json_encode($transferdata);
		$transferdataBase64 = base64_encode($data);

		$guestorders = self::sendToGuestsystem($pdo,$url, $transferdataBase64);
		
		$i=0;
	}
	
	private static function sendToGuestsystem($pdo,$url, $data) {
		
		$url .= "/sync.php";
		
		$query = http_build_query(array("data" => $data));

		$opts = array(
		    'http' => array(
			'header' => 
			"Content-Type: application/x-www-form-urlencoded\r\n" .
			"Content-Length: " . strlen($query) . "\r\n" .
			"User-Agent:MyAgent/1.0\r\n",
			'method' => 'POST',
			'content' => $query
		    )
		);

		$context = stream_context_create($opts);

		$ret = @file_get_contents($url, false, $context);

		if (!$ret) {
			$ret = array("status" => "ERROR","msg" => "Communication with guest system not successful!");
		}
		
		self::insertWorkDataFromGuestSystem($pdo, $ret);
	}

	private static function insertWorkDataFromGuestSystem($pdo,$dataJson) {
                if (gettype($dataJson) != 'string') {
                        return true;
                }
		$dataDecoded = json_decode($dataJson,true);
		if ($dataDecoded["status"] == "OK") {
			try {
				$entries = $dataDecoded["msg"];
				foreach($entries as $entry) {
					$date = $entry["date"];
					$tableid = $entry["tableid"];
					$prodid = $entry["prodid"];
					$dailycode = $entry["dailycode"];
					$tablecode = $entry["tablecode"];
                                        $extras = null;
                                        if (isset($entry["extrasinjson"])) {
                                                $extras = json_decode($entry['extrasinjson'], true);
                                        }

					$permission = self::checkPermission($pdo, $tableid, $tablecode, $prodid, $dailycode);

					if ($permission) {
						$success = self::addToQueue($pdo,$date,$prodid,$extras,$tableid);
						if (!$success) {
							return false;
						}
					}
				}
			} catch (Exception $ex) {
				echo $ex->getMessage();
				return false;
			}
		}
		return true;
	}
	
	private static function checkPermission($pdo,$tableid,$tablecode,$prodid,$dailycode) {
		$askdaycode = CommonUtils::getConfigValue($pdo, 'askdaycode', 1);
		if ($askdaycode == 1) {
			$dailycodeInDb = trim(CommonUtils::getConfigValue($pdo, 'dailycode', ''));
			if ($dailycode != trim($dailycodeInDb)) {
				return false;
			}
		}
		
		$asktablecode = CommonUtils::getConfigValue($pdo, 'asktablecode', 1);
		if ($asktablecode == 1) {
			$sql = "SELECT COALESCE(code,'') as code from %resttables% WHERE id=? AND allowoutorder=?";
			$result = CommonUtils::fetchSqlAll($pdo, $sql, array($tableid,1));
			if (count($result) == 0) {
				return false;
			}
			$tablecodeInDb = trim($result[0]["code"]);
			if ($tablecodeInDb != $tablecode) {
				return false;
			}
		}
		
		$sql = "SELECT id FROM %products% WHERE id=? AND removed is null AND (display is null OR display='KG' OR display='G')";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array($prodid));
		if (count($result) == 0) {
			return false;
		}
		
		return true;
	}

	private static function shallWeSync($pdo) {
		date_default_timezone_set(DbUtils::getTimeZone());
		$date = new DateTime();
		$currentTimeStamp = $date->getTimestamp();
			
		$sql = "SELECT value FROM %work% WHERE item=?";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array('lastsyncwithguest'));
		
		if (count($result) == 0) {
			
			$sql = "INSERT INTO %work% (item,value) VALUES(?,?)";
			CommonUtils::execSql($pdo, $sql, array("lastsyncwithguest",$currentTimeStamp));
			
			return true;
		} else {
			$lastaccess = $result[0]["value"];

			if (($currentTimeStamp - $lastaccess) > 5) {
				$sql = "UPDATE %work% SET value=? WHERE item=?";
				CommonUtils::execSql($pdo, $sql, array($currentTimeStamp,"lastsyncwithguest"));
				return true;
			} else {
				return false;
			}
		}
	}

	private static $typesWithContent = array();
	
	private static function getImagesForGuestProducs($pdo) {
                $prodDayVar = CommonUtils::getProdDefVariableForCurrentDay($pdo);
		
		$sql = "SELECT P.id as prodid,COALESCE(I.imgl,'-') as imagedata ";
		$sql .= " FROM %products% P LEFT JOIN %prodimages% I ON P.prodimageid=I.id ";
		$sql .= " WHERE P.available='1' AND P.removed is null ";
		$sql .= " AND (P.$prodDayVar = '1') AND (display = 'KG'  OR display = 'G' OR display is null) ";
		$sql .= " AND (unit is null OR unit='0') ";
		
		$allProductImgs = CommonUtils::fetchSqlAll($pdo, $sql, null);
		

		return $allProductImgs;
	}
	
        private static function getExtras($pdo) {
                $sql = "SELECT id,name,price,maxamount FROM %extras% WHERE removed is null OR removed='0' ORDER BY sorting";
                $allExtras = CommonUtils::fetchSqlAll($pdo, $sql);
                $sql = "SELECT EP.id,extraid,prodid from %extrasprods% EP,%products% P WHERE EP.prodid=P.id AND P.removed is null or P.removed='0'";
                $allExtrasAssigments = CommonUtils::fetchSqlAll($pdo, $sql);
                return array("extras" => $allExtras, "assignments" => $allExtrasAssigments);
        }
        
	private static function getMenuForGuests($pdo) {
		$prodDayVar = CommonUtils::getProdDefVariableForCurrentDay($pdo);
		
		$pricelevel = CommonUtils::getConfigValue($pdo, "pricelevel", 0);
		
		$priceTxt = "priceA";
		if ($pricelevel == 2) {
			$priceTxt = "priceB";
		} else if ($pricelevel == 3) {
			$priceTxt = "priceC";
		}
		
		$guestShowSoldArticles = CommonUtils::getConfigValue($pdo, 'guestshowsoldprods', 0);
		$handleAmount = CommonUtils::getConfigValue($pdo, 'handleamount', Products::$HANDLE_AMOUNT_SHOW_AND_ALLOW_ORDER);
		$handleAmountSql = "";
		if (($handleAmount == Products::$HANDLE_AMOUNT_HIDE_ARTICLE) || ($guestShowSoldArticles == 0)) {
				$handleAmountSql = " AND ((amount is null) OR (amount > '0')) ";
		}
                
		$sql = "select id,longname,description,category as ref,$priceTxt as price,COALESCE(unit,'0') as unit,COALESCE(guestmaxorder,'1') as guestmaxorder ";
		$sql .= " from %products% where available='1' AND removed is null AND (%products%.$prodDayVar = '1') AND (display = 'KG'  OR display = 'G' OR display is null) ";
		$sql .= " AND (unit is null OR unit='0') ";
		$sql .= " AND (guestmaxorder is null OR guestmaxorder > '0') ";
        $sql .= " $handleAmountSql ";
		$sql .= " ORDER BY longname";

		$allProducts = array();
		try {
				$allProducts = CommonUtils::fetchSqlAll($pdo, $sql, null);
		} catch (Exception $ex) {
				error_log("getMenuForGuests could not read menu: " . $ex->getMessage());
		}
		

		$sql = "select id,name,COALESCE(reference,0) as reference,sorting from %prodtype% where removed is null";
		$allTypes = CommonUtils::fetchSqlAll($pdo, $sql, null);
		
		$filteredTypes = self::filterUsedTypes($allTypes, $allProducts);
		
		return array("products" => $allProducts,"types" => $filteredTypes);
	}
	

	private static function filterUsedTypes($types,$products) {
		self::$typesWithContent = array();
		foreach ($products as $p) {
			$ref = $p["ref"];
			$typeOfProd = self::getTypeOfId($types, $ref);
			if (!is_null($typeOfProd)) {
				self::declareProdTypeAndParentsInUse($types, $typeOfProd);
			}
		}
		$out = array();
		$keys = array_keys(self::$typesWithContent);
		foreach($keys as $aKey) {
			$t = self::$typesWithContent[$aKey];
			$out[] = array("id" => $t["id"],"reference" => $t["reference"],"name" => $t["name"],"sorting" => $t["sorting"]);
		}
		
		usort($out,"Products::cmptypes");
		return $out;
	}
	
	private static function getTypeOfId($alltypes,$typeid) {
		foreach($alltypes as $t) {
			if ($t["id"] == $typeid) {
				return $t;
			}
		}
		return null;
	}
	
	private static function declareProdTypeAndParentsInUse($alltypes,$aType) {
		$typeid = $aType["id"];
		$reference = $aType["reference"];
		$sorting = $aType["sorting"];
		if (!array_key_exists($typeid, self::$typesWithContent)) {
			self::$typesWithContent[$typeid] = array("id" => $typeid,"name" => $aType["name"],"reference" => $reference, "sorting" => $sorting);
			
			$parent = null;
			foreach($alltypes as $a) {
				$typeid = $a["id"];
				if ($typeid == $reference) {
					$parent = $a;
					break;
				}
			}
			if (!is_null($parent)) {
				self::declareProdTypeAndParentsInUse($alltypes, $parent);
			}
		}
	}
	
	private static function addToQueue($pdo,$date,$prodid,$extras,$tableid) {
		$printjob = CommonUtils::getConfigValue($pdo, 'guestjobprint', 1);
		if (is_null($printjob)) {
			$printjob = 1;
		}
		$queue = new QueueContent();
		$success = $queue->addProductListToQueueForGuest($pdo, $date, $tableid, $prodid, $extras, $printjob);
		if (!$success) {
			return false;
		}
		if ($printjob == 0) {
			QueueContent::setFlagForCooking($pdo,$prodid);
		}
		return true;
	}
}