<?php

require_once ('dbutils.php');
require_once ('commonutils.php');

class Hotelinterface {
	
	public static function hs3sync($pdo) {
		$hotelinterface = CommonUtils::getConfigValue($pdo, "hotelinterface", 0);
		if ($hotelinterface != 1) {
			// HS/3 is not active thus do nothing
			return array("status" => "OK");
		}
	
		$syncInterval = intval(CommonUtils::getConfigValue($pdo, "hs3refresh", 60));
		
		date_default_timezone_set(DbUtils::getTimeZone());
		$currentTime = date('Y-m-d H:i:s');
		
		$itemNameForLastSync = 'lasths3sync';
			
		$sql = "SELECT count(id) as countid FROM %work% WHERE item=?";
		$row = CommonUtils::getRowSqlObject($pdo, $sql, array($itemNameForLastSync));
		
		if ($row->countid > 0) {
			$sql = "SELECT TIMESTAMPDIFF(SECOND,value,NOW()) as synctimediff FROM %work% WHERE item=?";
			$row = CommonUtils::getRowSqlObject($pdo, $sql, array($itemNameForLastSync));
			$lastdone = $row->synctimediff;
			if ($lastdone < $syncInterval) {
				return array("status" => "OK");
			}
		} else {
			$sql = "INSERT INTO %work% (item,value) VALUES(?,?)";
			CommonUtils::execSql($pdo, $sql, array($itemNameForLastSync,$currentTime));
		}
		
		$sql = "UPDATE %work% SET value=? WHERE item=?";
		CommonUtils::execSql($pdo, $sql, array($currentTime,$itemNameForLastSync));
		
		$resultOfHsOutSync = self::syncHs3Out($pdo);
		$resultOfHsInSync = self::syncHs3In($pdo);
		
		
		return array("status" => "OK");
	}
	
	private static function syncHs3Out($pdo) {
		$hsoutfile = CommonUtils::getConfigValue($pdo, 'hsoutfile', '');
		if ($hsoutfile !== '') {
			if ( !file_exists($hsoutfile) ) {
				CommonUtils::log($pdo, "HS3", "Error HS3CASH.OUT does not exist.");
				return array("status" => "ERROR","msg" => "Fehler beim Zugriff auf HS3CASH.OUT. Datei $hsoutfile existiert nicht.");
			}
			try {
				$pdo->beginTransaction();
				if (($handle = fopen($hsoutfile, "r")) !== FALSE) {
					$sql = "DELETE FROM %hsout%";
					CommonUtils::execSql($pdo, $sql, null);
					$sql = "INSERT INTO %hsout% (reservationid,object,guest) VALUES(?,?,?)";
					$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
					while (($data = fgetcsv($handle, 0, ";")) !== FALSE) {
						$num = count($data);
						if ($num >= 3) {
							$reservationid = $data[0];
							$objectid = $data[1];
							$guestname = $data[2];
							$stmt->execute(array($reservationid,$objectid,$guestname));
						}
					}
					$pdo->commit();
					fclose($handle);
					CommonUtils::log($pdo, "HS3", "HS/3 Outfile read.");
					$ok = unlink($hsoutfile);
					if (!$ok) {
						CommonUtils::log($pdo, "HS3", "Error deleting HS3CASH.OUT.");
						return array("status" => "ERROR","msg" => "Datei HS3CASH.OUT konnte nicht gelöscht werden.");
					}
				} else {
					$pdo->rollBack();
					CommonUtils::log($pdo, "HS3", "Error accessing HS3CASH.OUT.");
					return array("status" => "ERROR","msg" => "Fehler beim Zugriff auf HS3CASH.OUT.");
				}
			} catch (Exception $e) {
				$pdo->rollBack();
				return array("status" => "ERROR","msg" => "Fehler beim Zugriff auf HS3CASH.OUT. Meldung: $e");
			}			
		}
		return array("status" => "OK");
	}
	
	private static function syncHs3In($pdo) {
		$hsinfile = CommonUtils::getConfigValue($pdo, 'hsinfile', '');
		if ($hsinfile !== '') {
			if ( file_exists($hsinfile) ) {
				CommonUtils::log($pdo, "HS3", "HS3CASH.IN still exists - cannot transmit data.");
				return array("status" => "ERROR","msg" => "Fehler beim Zugriff auf HS3CASH.IN. Datei $hsinfile existiert noch.");
			}
			
			try {
				$txt = '';
				$pdo->beginTransaction();
				$sql = "SELECT * from %hsin%";
				$result = CommonUtils::fetchSqlAll($pdo, $sql, null);
				if (count($result) > 0) {
					$lines = array();
					foreach($result as $anEntry) {
						$entryArr = array();
						$entryArr[] = '"' . $anEntry["reservationid"] . '"';
						$entryArr[] = '"' . $anEntry["billid"] . '"';
						$entryArr[] = '"' . $anEntry["date"] . '"';
						$entryArr[] = '"' . $anEntry["time"] . '"';
						$entryArr[] = '"' . $anEntry["number"] . '"';
						$entryArr[] = '"' . $anEntry["prodid"] . '"';
						$entryArr[] = '"' . $anEntry["prodname"] . '"';
						$entryArr[] = '"' . str_replace(".",',',$anEntry['tax']) . '"';
						$entryArr[] = '"' . str_replace(".",',',$anEntry['brutto']) . '"';
						$entryArr[] = '"' . str_replace(".",',',$anEntry['total']) . '"';
						$entryArr[] = '"' . $anEntry["currency"] . '"';
						$entryArr[] = '"' . $anEntry["waiterid"] . '"';
						$entryArr[] = '"' . $anEntry["waitername"] . '"';
						$entryTxt = join(';',$entryArr);
						$lines[] = $entryTxt;
					}
					$fullFileContent = implode("\r\n",$lines);
					
					if (file_put_contents($hsinfile, $fullFileContent) == FALSE) {
						$pdo->rollBack();
						CommonUtils::log($pdo, "HS3", "Error accessing HS3CASH.IN. Cannot write to file.");
						return array("status" => "ERROR","msg" => "Fehler beim Zugriff auf HS3CASH.IN. Datei nicht beschreibbar.");
					}
				}
				
				$sql = "DELETE FROM %hsin%";
				CommonUtils::execSql($pdo, $sql, null);
				
				$pdo->commit();
				CommonUtils::log($pdo, "HS3", "HS/3 Infile written.");
				return array("status" => "OK");
			} catch (Exception $e) {
				$pdo->rollBack();
				return array("status" => "ERROR","msg" => "Fehler beim Zugriff auf HS3CASH.IN. Meldung: $e");
			}			
		}
	}
	
	public static function insertIntoHsin($pdo,$billid) {
		$hotelinterface = CommonUtils::getConfigValue($pdo, "hotelinterface", 0);
		if ($hotelinterface != 1) {
			// HS/3 is not active thus do nothing
			return;
		}
		
		$hscurrency = CommonUtils::getConfigValue($pdo, "hscurrency", "EUR");
		if (strlen($hscurrency) > 3) {
			$hscurrency = substr($hscurrency,0,3);
		}
		
		$sql = "SELECT DATE_FORMAT(DATE(billdate),'%d.%m.%Y') as billdate,TIME_FORMAT(TIME(billdate),'%H:%i') as billtime,reservationid,userid,username,brutto,paymentid from %bill%,%user% WHERE %bill%.id=? AND userid=%user%.id";
		$row = CommonUtils::getRowSqlObject($pdo, $sql, array($billid));
		if ($row->paymentid != 7) {
			return;
		}
		
		$waitername = substr($row->username,0,20);
		
		$sign = '';
		if ($row->brutto < 0) {
			$sign = '-';
		}
		$sql = "SELECT COUNT(Q.id) as count,productid,PN.name,tax,price,SUM(price) as sumprice FROM %queue% Q,%billproducts% BP, %prodnames% PN WHERE BP.billid=? AND BP.queueid=Q.id AND Q.prodnameid=PN.id GROUP BY productid,price,tax";
		$items = CommonUtils::fetchSqlAll($pdo, $sql, array($billid));
		
		foreach ($items as $anItem) {
			$sql = "INSERT INTO %hsin% (reservationid,billid,date,time,number,prodid,prodname,tax,brutto,total,currency,waiterid,waitername) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)";
			$tax = str_replace(".",',',$anItem['tax']);
			
			$brutto = $sign . $anItem['price'];
			$total = $sign . $anItem['sumprice'];
			$prodname = $anItem['productname'];
			if (strlen($prodname) > 100) {
				$prodname = substr($prodname, 0,100);
			}
			
			try {
				CommonUtils::execSql($pdo, $sql, array(
				    $row->reservationid,
				    $billid,
				    $row->billdate,
				    $row->billtime,
				    $anItem['count'],
				    $anItem['productid'],
				    $prodname,
				    $tax,
				    $brutto,
				    $total,
				    $hscurrency,
				    $row->userid,
				    $waitername
				));
			} catch (Exception $e) {
				echo $e;
			}
		}
		
	}
}