<?php
// Datenbank-Verbindungsparameter
require_once ('dbutils.php');
require_once ('queuecontent.php');
require_once ('commonutils.php');
require_once ('utilities/userrights.php');

class Reports {
	var $dbutils;
	var $queue;
	var $commonUtils;
	var $userrights;
	
	static $sql_service_good = "";
	static $sql_service_ok = "";
	static $sql_service_bad = "";
	static $sql_kitchen_good = "";
	static $sql_kitchen_ok = "";
	static $sql_kitchen_bad = "";
	static $sql_ratings = "";
	static $sql_remarks = "";
        
        static $DURATION_PREPARE = 1;
        static $DURATION_SERVE = 2;
        static $DURATION_PAID = 3;
	
	function __construct() {
		$this->dbutils = new DbUtils();
		$this->queue = new QueueContent();
		$this->commonUtils = new CommonUtils();
		$this->userrights = new Userrights();
	}
	
	public function createSqlPhrases() {
		self::$sql_service_good = "select COUNT(service) as count FROM %ratings% WHERE service='1' AND date between ? AND ?";
		self::$sql_service_ok = "select COUNT(service) as count FROM %ratings% WHERE service='2' AND date between ? AND ?";
		self::$sql_service_bad = "select COUNT(service) as count FROM %ratings% WHERE service='3' AND date between ? AND ?";
		
		self::$sql_kitchen_good = "select COUNT(kitchen) as count FROM %ratings% WHERE kitchen='1' AND date between ? AND ?";
		self::$sql_kitchen_ok = "select COUNT(kitchen) as count FROM %ratings% WHERE kitchen='2' AND date between ? AND ?";
		self::$sql_kitchen_bad = "select COUNT(kitchen) as count FROM %ratings% WHERE kitchen='3' AND date between ? AND ?";
		
		self::$sql_ratings = "select COUNT(id) as count FROM %ratings% WHERE date between ? AND ?";
		
		self::$sql_remarks = "SELECT DATE_FORMAT(date, '%e.%m.%Y %H:%i') AS date,remark FROM %ratings% WHERE CHAR_LENGTH(remark) > 2 AND date between ? AND ?";
	}
	
	function handleCommand($command) {
		header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
		header("Cache-Control: post-check=0, pre-check=0", false);
		header("Pragma: no-cache");
		
		// canUserCallCommands($currentCmd, $cmdArray,$right)
		$cmdArray = array('getStats');
		if (in_array($command, $cmdArray)) {
			if (!($this->userrights->hasCurrentUserRight('right_statistics'))) {
				echo "Benutzerrechte nicht ausreichend!";
				return false;
			}
		}
		
		$this->createSqlPhrases();
		$allDates = self::getDates();
		$pdo = DbUtils::openDbAndReturnPdoStatic();

		switch($command) {
			case 'getStats':
				$this->getStats($pdo);
				break;
			case 'getUsersums':
				$values = $this->getUsersums($pdo);
				echo json_encode(array("status" => "OK","msg" => $values));
				break;
			case 'getToday':
				$values = $this->iterateForHours($pdo, $allDates['todayDate'], intval($allDates['todayHour'])+1,false);
				echo json_encode(array("status" => "OK","msg" => $values));
				break;
			case 'getYesterday':
				$values = $this->iterateForHours($pdo, $allDates['yesterdayDate'], 24,true);
				echo json_encode(array("status" => "OK","msg" => $values));
				break;
			case 'getThismonth':
				$values = $this->iterateForDays($pdo, $allDates['monthAndYearOfThisMonth'],intval($allDates['currentDay']),true);
				echo json_encode(array("status" => "OK","msg" => $values));
				break;
			case 'getLastmonth':
				$values = $this->iterateForDays($pdo, $allDates['monthAndYearOfLastMonth'],intval($allDates['lastDayOfLastMonth']),true);
				echo json_encode(array("status" => "OK","msg" => $values));
				break;
			case 'getProds':
				$days = null;
				if (isset($_GET['days'])) {
					$days = intval($_GET['days']);
				}
				$values = $this->sumSortedByProducts($pdo, $allDates['last30days'][0], $allDates['currentTimeStr'],null,null,$days);
				echo json_encode(array("status" => "OK","msg" => $values));
				break;
			case 'getRatings':
				$values = $this->getRatings($pdo,$allDates['last30days'],$allDates['lastMonthComplete'], $allDates['currentTimeStr']);
				echo json_encode(array("status" => "OK","msg" => $values));
				break;
			case 'getMonthNames':
				echo json_encode(array("status" => "OK","thismonth" => $allDates['thisMonthName'],"lastmonth" => $allDates['lastMonthName']));
				break;
                        case 'getTips':
				$values = $this->getTips($pdo);
				echo json_encode(array("status" => "OK","msg" => $values));
				break;
                        case 'getProcessingTimes':
                                $timeSpan = 30;
                                if (isset($_GET["timespan"])) {
                                        $timeSpan = intval($_GET["timespan"]);
                                }
                                $values = $this->getProcessingTimes($pdo,$timeSpan);
                                echo json_encode($values);
                                break;
                        case 'getProfit':
                                $timeSpan = 30;
                                if (isset($_GET["timespan"])) {
                                        $timeSpan = intval($_GET["timespan"]);
                                }
                                $values = $this->getProfit($pdo,$timeSpan);
                                echo json_encode($values);
                                break;
                        case 'getPerformance':
                                // no rights limitations
                                echo json_encode(Performance::getPerformance($pdo,$_GET['task'],$_GET['forlocalhost'],$_GET['fortse']));
                                break;
                        case 'downloadPerfCsvData':
                                Performance::downloadCsvData($pdo);
                                break;
			default:
				echo "Command not supported.";
		}
	}
	
	private function getStats($pdo = null) {
		if (is_null($pdo)) {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
		}
		$this->createSqlPhrases();
		
		$alldates = self::getDates();
		
		$this->getReports($pdo,$alldates);
	}
	
	public function getStatsCore($pdo,$forDash = false) {
		$this->createSqlPhrases();
		$alldates = self::getDates();
		return($this->getReportsCore($pdo,$alldates,$forDash));
	}
	
	static private function getDates() {
		date_default_timezone_set(DbUtils::getTimeZone());
		$currentTimeStr = date('Y-m-d H:i:s');
		$curTime = strtotime($currentTimeStr);
		
		$todayDate = date('Y-m-d');
		$todayHour = date('H');
		$yesterdayDate = date("Y-m-d", strtotime("-1 day", $curTime));
		
		// now for this month
		$firstDayOfThisMonth = date("Y-m-01", strtotime($currentTimeStr));
		$currentDay = date("d",strtotime($currentTimeStr));  // current day (4 if date is 4 Jan 2014)
		$month = date("m",strtotime($currentTimeStr));
		$monthName = self::getMonthName($month);
		$monthAndYearOfThisMonth = date("Y-m",strtotime($currentTimeStr));
		$thisMonthName = self::getMonthName($month);
		
		// last month
		$last_month_ini = new DateTime("first day of last month");
		$last_month_end = new DateTime("last day of last month");
		$firstDayOfLastMonth = $last_month_ini->format('Y-m-d');
		$lastDayOfLastMonth = $last_month_end-> format('d');
		$iterations = intval($last_month_end->format('d'));
		$lastMonth = intval($last_month_ini->format('m'));
		$monthAndYearOfLastMonth = $last_month_end->format('Y-m');
		$lastMonthComplete = $last_month_ini->format('Y-m-d') . " 00:00:00";
		$lastMonthName = self::getMonthName($lastMonth);
		
		// last 30 days
		$daysArr = array();
		for ($i=29;$i>=0;$i--) {
			$daysArr[] = date("Y-m-d", strtotime('-' . $i . ' day') );
		}
		
		$retArray = array(
				"todayDate" => $todayDate,
				"todayHour" => $todayHour,
				"currentTimeStr" => $currentTimeStr,
				"yesterdayDate" => $yesterdayDate,
				"monthAndYearOfLastMonth" => $monthAndYearOfLastMonth,
				"lastDayOfLastMonth" => $lastDayOfLastMonth,
				"lastMonthComplete" => $lastMonthComplete,
				"lastMonthName" => $lastMonthName,
				"currentDay" => $currentDay,
				"monthAndYearOfThisMonth" => $monthAndYearOfThisMonth,
				"thisMonthName" => $thisMonthName,
				"last30days" => $daysArr
		);
		return $retArray;
	}
	
	public static function getMonthName($monthNo) {
		$mons = array(
				1 => "Januar",
				2 => "Februar",
				3 => "März",
				4 => "April",
				5 => "Mai",
				6 => "Juni",
				7 => "Juli",
				8 => "August",
				9 => "September",
				10 => "Oktober",
				11 => "November",
				12 => "Dezember");
	
		return ($mons[intval($monthNo)]);
	}
	
	private function getReports ($pdo,$allDates) {
		$reports = $this->getReportsCore($pdo,$allDates);
		echo json_encode($reports);
	}
	
	private function getReportsCore($pdo,$allDates,$forDash = false) {
		$pdo->beginTransaction();
		
		// bills of today independently of closing
		$retArrayToday = $this->iterateForHours($pdo, $allDates['todayDate'], intval($allDates['todayHour'])+1,false);
		// closed yesterday bills:
		$retArrayYesterday = $this->iterateForHours($pdo, $allDates['yesterdayDate'], 24,true);
		
		$retThisMonth = $this->iterateForDays($pdo, $allDates['monthAndYearOfThisMonth'],intval($allDates['currentDay']),true);
		
		// closed of last month:
		$retArrayLastMonth = $this->iterateForDays($pdo, $allDates['monthAndYearOfLastMonth'],intval($allDates['lastDayOfLastMonth']),true);
		
		// products in the last 30 days:
		$retArrayProds = $this->sumSortedByProducts($pdo, $allDates['last30days'][0], $allDates['currentTimeStr'],null,null,null);
		
		$retRatings = $this->getRatings($pdo,$allDates['last30days'],$allDates['lastMonthComplete'], $allDates['currentTimeStr']);
		
		$usersums = $this->getUserSums($pdo);
		
		$pdo->commit();
		
		$retArray = array("today" => $retArrayToday, 
				"yesterday" => $retArrayYesterday,
				"thismonth" => $retThisMonth,
				"lastmonth" => $retArrayLastMonth,
				"prodsums" => $retArrayProds,
				"lastmonthname" => $allDates['lastMonthName'],
				"thismonthname" => $allDates['thisMonthName'],
				"ratings" => $retRatings,
				"usersums" => $usersums
		);
		
		if ($forDash) {
			$retArray["tables"] = self::getOpenTables($pdo);
			$retArray["prodscount"]  = self::getMaxSoldProductsCount($pdo);
			$retArray["prodssum"]  = self::getMaxSoldProductsSum($pdo);
			$retArray["durations"] = self::getGuestDuration($pdo);
		}
		
		return $retArray;
	}
	
	/*
	 * returns an array:
	 * 	hour, sum
	 * 	hour, sum
	 */
	private function iterateForHours($pdo,$theDateStr,$noOfIterations,$mustBeClosed) {
		$retArray = array();
		$sumMax = 0.0;
		for ($i=0;$i<$noOfIterations;$i++) {
			$startDateTime = $theDateStr . " $i:00:00";
			$endDateTime =  $theDateStr . " $i:59:59";
			$sum = $this->sumBetween($pdo,$startDateTime,$endDateTime,$mustBeClosed);
			if ($sumMax < $sum) {
				$sumMax = $sum;
			}
			$retArray[] = array("iter" => $i, "sum" => $sum);
		}
		return array("max" => $sumMax, "content" => $retArray);
	}
	
	/*
	 * returns an array wioth "content"
	 * 	day, sum with day 0..31, 
	 * 	day, sum ...
	 */
	private function iterateForDays($pdo,$theMonthYearStr,$noOfIterations,$mustBeClosed) {
		$retArray = array();
		$sumMax = 0.0;
		for ($i=1;$i<($noOfIterations+1);$i++) {
			$dayInTwoDigists = sprintf('%02d', $i);
			$startDateTime = $theMonthYearStr . "-$dayInTwoDigists 00:00:00";
			$endDateTime =  $theMonthYearStr . "-$dayInTwoDigists 23:59:59";
			$sum = $this->sumBetween($pdo,$startDateTime,$endDateTime,$mustBeClosed);
			if ($sumMax < $sum) {
				$sumMax = $sum;
			}
			$retArray[] = array("iter" => $i, "sum" => $sum);
		}
		return array("max" => $sumMax, "content" => $retArray);
	}
	
	private function sumBetween($pdo,$startDateTime,$endDateTime,$mustBeClosed) {
		$sql = "SELECT sum(brutto) as sumtotal FROM %bill% ";
		$sql .= "WHERE status is null "; // no cash insert or take off, no stornos
		$sql .= "AND billdate between ? AND ? ";
		$sql .= "AND paymentid <> '8' ";
		if ($mustBeClosed) {
			$sql .= "AND closingid is not null"; // and must be in a closing
		}
		$sum = 0.0;
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array($startDateTime,$endDateTime));
		$row =$stmt->fetchObject();
		
		if ($row != null) {
			$theSqlSum = $row->sumtotal;
			if ($theSqlSum != null) {
				$sum = $theSqlSum;
			}
		}
		return $sum;
	}
	
	function cmp($a, $b)
	{
		$asum = $a['sum'];
		$bsum = $b['sum'];
		if ($asum == $bsum) {
			return 0;
		}
		return ($asum < $bsum) ? 1 : -1;
	}

	public function sumSortedByProducts($pdo,$startDateTime,$endDateTime,$closidstart=null,$closidend=null,$days=null) {
		// first get all products and with their id and name
		
		if (!is_null($days)) {
			date_default_timezone_set(DbUtils::getTimeZone());
			$startDateTime = date("Y-m-d", strtotime('-' . ($days - 1) . ' day') );
		}
		
                if (is_null($closidstart)) {
                        $timeRangeConstraint = "billdate between ? AND ?";
                } else {
                        $timeRangeConstraint = "closingid between ? AND ?";
                }
                $sql = "SELECT SUM(price) as sum,(CASE WHEN PT.kind=0 THEN 'Speisen' WHEN PT.kind='1' THEN 'Getränke' ELSE 'Sonstiges' END) as iter from %queue% Q,%bill% B,%products% P,%prodtype% PT ";
                $sql .= "WHERE Q.productid=P.id ";
                $sql .= "AND P.category=PT.id ";
                $sql .= "AND billid is not null AND Q.billid=B.id ";
                $sql .= "AND $timeRangeConstraint ";
                $sql .= "AND B.closingid is not null ";
                $sql .= "AND B.status is null ";
                $sql .= "AND B.paymentid <> '" . DbUtils::$PAYMENT_GUEST . "' AND B.paymentid <> '" . DbUtils::$PAYMENT_HOTEL . "' GROUP BY PT.kind";

                if (is_null($closidstart)) {
                        $foodAndDrinksSales = CommonUtils::fetchSqlAll($pdo, $sql, array($startDateTime,$endDateTime));
                } else {
                        $foodAndDrinksSales = CommonUtils::fetchSqlAll($pdo, $sql, array($closidstart,$closidend));
                }

                // Output: with kind=0: food, and kind=1: drinks
                
                $maxFoodDrinksValue = 0.0;
                foreach($foodAndDrinksSales as $anEntry) {
                        $sumValue = doubleval($anEntry['sum']);
                        if ($maxFoodDrinksValue < $sumValue) {
                                $maxFoodDrinksValue = $sumValue;
                        }
                }

		// now iterate over all prods
		$sumMax = 0.0;
		$prodinfos = array();
                if (is_null($closidstart)) {
                        $timeFilter = "B.billdate between ? AND ? AND B.closingid is not null ";
                } else {
                        $timeFilter = "B.closingid is not null AND B.closingid between ? AND ? ";
                }
                
                $sql = "SELECT Q.productid as prodid,P.longname as prodname,sum(Q.price) as sum, count(Q.id) as prodcount, CONCAT(P.longname,'( ',COUNT(Q.id),'x)') as iter from %queue% Q,%bill% B,%products% P "
                        . "WHERE Q.productid=P.id AND Q.billid is not null AND Q.billid=B.id AND "
                        . "$timeFilter AND "
                        . "B.status is null AND "
                        . "B.paymentid <> '" . DbUtils::$PAYMENT_GUEST . "' AND B.paymentid <> '" . DbUtils::$PAYMENT_HOTEL . "' AND "
                        . "Q.productid in (SELECT P1.id from %products% P1) group by Q.productid HAVING sum >'0.0' order by sum(Q.price) DESC";
                
                if (is_null($closidstart)) {
                        $prodinfos = CommonUtils::fetchSqlAll($pdo, $sql, array($startDateTime,$endDateTime));
                } else {
                        $prodinfos = CommonUtils::fetchSqlAll($pdo, $sql, array($closidstart,$closidend));
                }
                
                if (count($prodinfos) > 0) {
                        foreach ($prodinfos as $aProd) {
                                $sumprice = $aProd['sum'];
                                if ($sumMax < $sumprice) {
					$sumMax = $sumprice;
				}
                        }
                }

		return array("max" => $sumMax, "content" => $prodinfos,"foodanddrinkssales" => $foodAndDrinksSales, "maxfooddrinksvalue" => $maxFoodDrinksValue);
	}
	
	static function getRating($pdo,$sql,$start,$end) {
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($start,$end));
		$row =$stmt->fetchObject();
		
		if (!is_null($row)) {
			return(intval($row->count));
		} else {
			return 0;
		}
	}
	
	static function getRelation($val1,$val2,$val3,$maxPercent) {
		$total = $val1 + $val2 + $val3;
		if ($total == 0) {
			return array (0.0,0.0,0.0,$maxPercent);
		} else {
			$rel = $maxPercent / ((double)$total);
			return array($rel * $val1,$rel * $val2,$rel * $val3,0.0);
		}
	}
	
	static function getRatingOfDay($pdo,$aDay,$start,$end) {
		$serviceGood = self::getRating($pdo,self::$sql_service_good, $start, $end);
		$serviceOK = self::getRating($pdo,self::$sql_service_ok, $start, $end);
		$serviceBad = self::getRating($pdo,self::$sql_service_bad, $start, $end);
		$serviceRel = self::getRelation($serviceGood,$serviceOK,$serviceBad,95);
		
		$kitchenGood = self::getRating($pdo,self::$sql_kitchen_good, $start, $end);
		$kitchenOK = self::getRating($pdo,self::$sql_kitchen_ok, $start, $end);
		$kitchenBad = self::getRating($pdo,self::$sql_kitchen_bad, $start, $end);
		$kitchenRel = self::getRelation($kitchenGood,$kitchenOK,$kitchenBad,95);
		
		$totalRatings = self::getRating($pdo,self::$sql_ratings, $start, $end);
		
		$date = new DateTime($aDay);
		
		return array("day" => $date->format('d.m.Y'), "service" => $serviceRel, "kitchen" => $kitchenRel, "total" => $totalRatings);
	}
	
	function getRatings($pdo,$last30days,$startPeriod,$endPeriod){
		$reports = array();
		foreach($last30days as $aDay) {
			$start = $aDay . " 00:00:00";
			$end = $aDay . " 23:59:59";
			$reports[] = self::getRatingOfDay($pdo,$aDay,$start,$end);
		}
		
		$sql = self::$sql_remarks;
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute(array($startPeriod,$endPeriod));
		$result = $stmt->fetchAll();
		
		return array("statistics" => $reports,"remarks" =>$result);
	}
	
	function getUserSums($pdo) {
		$sql = "SELECT userid,username as iter,";
		
		//$sql .= "ROUND(sum(brutto),2) as sum,";
                $sql .= "CAST(ROUND(sum(if("
                        . "((paymentid <> '" . DbUtils::$PAYMENT_GUEST. "') and (paymentid <> '" . DbUtils::$PAYMENT_HOTEL. "')) AND (cashtype is null)"
                        . ",brutto,'0.00')),2) as DECIMAL(12,2)) as sum,";
		
		$sql .= "CAST(ROUND(sum(if(paymentid='" . DbUtils::$PAYMENT_BAR . "',brutto,'0.00')),2) as DECIMAL(12,2)) as sumonlybar,";
		
		$sql .= "CAST(ROUND(sum(if(status = 'c',brutto,'0.00')),2) as DECIMAL(12,2)) as sumcash ";
		
		$sql .= "FROM %bill%,%user% WHERE userid=%user%.id AND closingid is null GROUP BY userid,username";
		
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		$result = $stmt->fetchAll();
		
		$sumMax = 0.0;
		foreach ($result as $a) {
			if ($a["sum"] > $sumMax) {
				$sumMax = $a["sum"];
			}
		}
		
		return array("max" => $sumMax, "content" => $result);
	}
	
	
	public static function getOpenTables($pdo) {
		$sql = "SELECT id,roomname FROM %room% WHERE removed is null";
		$rooms = CommonUtils::fetchSqlAll($pdo, $sql,null);
		
		$tableCountTotal = 0;
		$tableCountOpen = 0;
		$sum = 0.0;
		foreach($rooms as $aRoom) {
			$roomId = $aRoom["id"];
			
			$sql = "SELECT count(id) as countid FROM %resttables% WHERE %resttables%.roomid=?";
			$howManyTables = CommonUtils::getRowSqlObject($pdo, $sql, array($roomId));
			$tableCountTotal += $howManyTables->countid;
			
			$sql = "SELECT %resttables%.id as id,%resttables%.tableno as name,COALESCE(SUM(IF(%queue%.toremove='0' AND %queue%.paidtime is null AND %queue%.isclosed is null,%queue%.price,0.00)),0.00) as pricesum FROM %resttables% ";
			$sql .= " LEFT OUTER JOIN %queue% ON %queue%.tablenr=%resttables%.id WHERE %resttables%.removed is null AND ";
			$sql .= " %resttables%.roomid=? GROUP BY %resttables%.id,name";
			
			$tables = CommonUtils::fetchSqlAll($pdo, $sql, array($roomId));
			
			
			foreach($tables as $aTable) {
				$sum += $aTable["pricesum"];
				
				if ($aTable["pricesum"] != '0.00') {
					$tableCountOpen++;
				}
			}
		}
		return array("tablestotal" => $tableCountTotal,"opentables" => $tableCountOpen,"sum" => $sum);
	}
	
	public static function getMaxSoldProductsCount($pdo) {
		
		$sql = "SELECT longname,productid,count(productid) as value from %queue%,%bill%,%products% ";
		$sql .= "WHERE %queue%.productid=%products%.id ";
		$sql .= "AND productid=%products%.id ";
		$sql .= "AND billid is not null AND %queue%.billid=%bill%.id ";
		$sql .= "AND DATE(billdate) = CURDATE() ";
		$sql .= "AND %bill%.status is null ";
		$sql .= "AND %bill%.paymentid <> '8' ";
		$sql .= "GROUP BY longname,productid ";
		$sql .= "ORDER BY value DESC ";
		$sql .= "LIMIT 10";

		$result = CommonUtils::fetchSqlAll($pdo, $sql, null);
	
		return $result;
	}
	
	public static function getMaxSoldProductsSum($pdo) {
		
		$sql = "SELECT longname,productid,sum(price) as value from %queue%,%bill%,%products% ";
		$sql .= "WHERE %queue%.productid=%products%.id ";
		$sql .= "AND productid=%products%.id ";
		$sql .= "AND billid is not null AND %queue%.billid=%bill%.id ";
		$sql .= "AND DATE(billdate) = CURDATE() ";
		$sql .= "AND %bill%.status is null ";
		$sql .= "AND %bill%.paymentid <> '8' ";
		$sql .= "GROUP BY longname,productid ";
		$sql .= "ORDER BY value DESC ";
		$sql .= "LIMIT 10";
		
		$result = CommonUtils::fetchSqlAll($pdo, $sql, null);
	
		return $result;
	}
	
	public static function getGuestDuration($pdo) {
		date_default_timezone_set(DbUtils::getTimeZone());
		$currentHour = date('H');

		$stat = array();
		$sql = "SELECT HOUR(paidtime) as hour,ROUND(AVG(TIME_TO_SEC(TIMEDIFF(paidtime,ordertime))/60)) as average";
		$sql .= " FROM %queue% WHERE paidtime is not null AND %queue%.toremove='0' AND DATE(paidtime) = DATE(NOW()) AND HOUR(paidtime)=? GROUP BY hour";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		for ($hour = 0; $hour <= $currentHour; $hour++) {
			$stmt->execute(array($hour));
			$result = $stmt->fetchAll();
			if (count($result) > 0) {
				$stat[] = array("hour" => $hour,"average" => $result[0]["average"]);
			} else {
				$stat[] = array("hour" => $hour,"average" => 0);
			}
		}
	
		return $stat;
	}
        
        private function getMaxValue(array $values):float {
                $sumMax = (float) 0.0;
		foreach ($values as $a) {
                        $aVal = $a["sum"];
                        if (is_string($aVal)) {
                                $aVal = floatval($aVal);
                        }
			if ($aVal > $sumMax) {
				$sumMax = $aVal;
			}
		}
                return $sumMax;
        }
        
	function getTips($pdo) {
                $sql = "SELECT userid,username as iter,"
                        . "SUM(brutto) as sum "
                        . "FROM %bill% B,%user% U WHERE B.userid=U.id AND B.closingid is null "
                        . "AND ((B.cashtype='" . Bill::$CASHTYPE_TrinkgeldAG["value"] . "') OR (B.cashtype='" . Bill::$CASHTYPE_TrinkgeldAN["value"] . "')) "
                        . "GROUP BY userid,username ORDER BY username";

                $result = CommonUtils::fetchSqlAll($pdo, $sql);
                
                $sumMax = $this->getMaxValue($result);
		
		return array("max" => $sumMax, "content" => $result);
	}
        
        private function getProcessingTimesCore($pdo,int $timespan, int $typeOfDuration):array {
                if (!is_numeric($timespan)) {
                        return array("status" => "ERROR","msg" => "Wrong timespan format");
                }
                
                $endTime = "readytime";
                $onlyDigitalOrderWhere = " AND printjobid is null ";
                if ($typeOfDuration == self::$DURATION_SERVE) {
                        $endTime = "delivertime";
                } else if ($typeOfDuration == self::$DURATION_PAID) {
                        $endTime = "paidtime";
                        $onlyDigitalOrderWhere = "";
                }
                
                $measureTime = "ROUND(AVG(TIMESTAMPDIFF(MINUTE, ordertime, $endTime)))";
                $sql = "SELECT P.longname as iter,$measureTime as sum from %queue% Q,%products% P where 
                        Q.productid=P.id AND
                        ordertime is not null and 
                        $endTime is not null  
                        $onlyDigitalOrderWhere 
                        and ordertime >= ( CURDATE() - INTERVAL $timespan DAY )
                        GROUP BY productid ORDER BY $measureTime";
                $cookTimeAvg = CommonUtils::fetchSqlAll($pdo, $sql);
                
                $avgMax = $this->getMaxValue($cookTimeAvg);
                $measured = array("max" => $avgMax, "content" => $cookTimeAvg);
                return array("status" => "OK","msg" => $measured);
        }
        
        private function getProcessingTimes($pdo,int $timeSpan): array {
                $prepareDuration = $this->getProcessingTimesCore($pdo, $timeSpan, self::$DURATION_PREPARE);
                if ($prepareDuration["status"] != "OK") {
                        return $prepareDuration;
                }
                $serveDuration = $this->getProcessingTimesCore($pdo, $timeSpan, self::$DURATION_SERVE);
                if ($serveDuration["status"] != "OK") {
                        return $serveDuration;
                }
                $paidDuration = $this->getProcessingTimesCore($pdo, $timeSpan, self::$DURATION_PAID);
                if ($paidDuration["status"] != "OK") {
                        return $paidDuration;
                }
                
                $msg = array("prepareduration" => $prepareDuration,"serveduration" => $serveDuration,"paidduration" => $paidDuration);
                return array("status" => "OK","msg" => $msg);
        }
        
        private function getProfit($pdo,int $timespan): array {
                $avgProfits = "ROUND(AVG(Q.profit),2)";
                $sumProfits = "ROUND(SUM(Q.profit),2)";
                $sql = "SELECT CONCAT(COUNT(P.longname),'x ',P.longname,' (',$avgProfits,')') as iter,$sumProfits as sum from %queue% Q,%products% P where 
                        Q.profit is not null 
                        AND Q.productid=P.id 
                        AND Q.paidtime is not null
                        AND Q.paidtime >= ( CURDATE() - INTERVAL $timespan DAY )
                        GROUP BY productid ORDER BY $sumProfits DESC";
                $cookTimeAvg = CommonUtils::fetchSqlAll($pdo, $sql);
                
                $avgMax = $this->getMaxValue($cookTimeAvg);
                $measured = array("max" => $avgMax, "content" => $cookTimeAvg);
                return array("status" => "OK","msg" => $measured);
        }
}