<?php

require_once (__DIR__. '/globals.php');
require_once (__DIR__. '/dbutils.php');
require_once (__DIR__. '/roomtables.php');
require_once (__DIR__. '/utilities/usedfeatures.php');

class TableMap {
	var $img = null;
	
	public function readImage($filename) {
		$fileContent = file_get_contents($filename);
		$this->img = imagecreatefromstring($fileContent);
		// now scale image
		$width = imagesx($this->img);
		$font = 5;
		$fontwidthInPixel = imagefontwidth($font);
 		$maxWidth = $fontwidthInPixel * 300;
 		if (function_exists('imagescale')) {
 			$this->img = imagescale($origimg, $maxWidth);
 			// PHP 5.5 is needed for image scaling - so catch in case of lower version
 		}
	}
	
	public function insertRectangle() {
		imagerectangle($this->img, $x0, $y0, $x0+$width*$cols, $y0+$height*$rows, $color);
	}
	
	public function outputImage() {
		header("Content-type: image/png");
		imagepng($this->img);
	}
	
	public function destroyImage() {
		imagedestroy($this->img);
	}

	public function insertText($x,$y,$txt) {	
		$width = imagesx($this->img);
		$height = imagesy($this->img);
		
		$font = 5;
		$fontwidthInPixel = imagefontwidth($font);
		$minWidthOfTextArea = $fontwidthInPixel * strlen($txt);
		
		$textarea = imagecreate ( $minWidthOfTextArea + 4,30 );
		$white = imagecolorallocate($textarea, 255, 255, 255);
		$black = imagecolorallocate($textarea, 0, 0, 0);
		
		$posx = $width / 100.0 * $x;
		$posy = $height / 100.0 * $y;
		imagestring ( $textarea , 5 , 2 , 2 , $txt , $black );
		
		imagecopymerge($this->img, $textarea, $posx , $posy, 0, 0, $minWidthOfTextArea + 4, imagesy($textarea), 100);
		
		imagedestroy($textarea);#
	}

	public function insertStrings($texts) {
		foreach($texts as $aText) {
			$x = $aText["posx"];
			$y = $aText["posy"];
			$text = $aText["text"];
			$this->insertText($x, $y, $text);
		}
	}
	
	public static function getRoomTableMap() {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$sql = "SELECT id,roomname,COALESCE(printer,0) as printer FROM %room% WHERE removed is null";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		$result = $stmt->fetchAll();

		$rooms = array();
		foreach($result as $row) {
			$roomid = $row["id"];
			$tables = self::getTableMapCore($pdo, $roomid);
			$rooms[] = array("name" => $row["roomname"],"printer" => $row["printer"], "id" => $roomid, "tables" => $tables);
		}
		echo json_encode($rooms);
	}
	
	public static function getTableMap($roomid) {
		$pdo = DbUtils::openRepliDb();

		$tablemap = self::getTableMapCore($pdo, $roomid);
                
                $pdo = null;
                $pdo = DbUtils::openDbAndReturnPdoStatic();
		
		echo json_encode($tablemap);
	}

	private static function getCircleSizeFactor($pdo) {
		$userid = $_SESSION['userid'];
		$sql = "SELECT tablebtnsize FROM %user% WHERE id=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($userid));
		$row = $stmt->fetchObject();
		$pref = $row->tablebtnsize;
		if (is_null($pref)) {
			$pref = 0;
		}
		return intval($pref)+1;
	}
	
	public static function getTableMapCore($pdo,$roomid) {
		if(session_id() == '') {
			session_start();
		}
		$userid = $_SESSION['userid'];
		$sql = "SELECT area FROM %user% WHERE id=?";
		$row = CommonUtils::getRowSqlObject($pdo, $sql, array($userid));
		$userarea = $row->area;
		$areaWhere = " ";
		if (!is_null($userarea)) {
			$area = intval($userarea);
			$areaWhere = " AND area='$area' ";
		}
		
		$sql = "SELECT id,tableno as name,active FROM %resttables% WHERE roomid=? AND removed is null AND active='1' $areaWhere ";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($roomid));
		$tables = $stmt->fetchAll();
	
		$tablemap = array();
		foreach ($tables as $aTable) {
			$tableid = $aTable["id"];
			$pos = self::getTablePosition($pdo,$tableid);
			if (is_null($pos)) {
				$tablemap[] = array("id" => $tableid,"name" => $aTable["name"], "haspos" => 0);
			} else {
				$tablemap[] = array("id" => $tableid, "name" => $aTable["name"],"haspos" => 1, "pos" => $pos);
			}
		}
	
		return($tablemap);
	}
	
	public static function deleteTableMap($pdo,$roomid) {
		$sql = "DELETE FROM %tablemaps% WHERE roomid=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($roomid));
		
		$sql = "SELECT id FROM %resttables% WHERE roomid=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($roomid));
		$tables = $stmt->fetchAll();
		foreach($tables as $aTable) {
			$sql = "DELETE FROM %tablepos% WHERE tableid=?";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($aTable["id"]));
		}
	}
	
	private static function getUserTmPreference($pdo) {
		if(session_id() == '') {
			session_start();
		}
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			return 0;
		} else {
			$sql = "SELECT prefertablemap AS result FROM %user% WHERE id=?";
			$stmt = $pdo->prepare(Dbutils::substTableAlias($sql));
			$stmt->execute(array($_SESSION['userid']));
			$row = $stmt->fetchObject();
			$prefer = 1;
			if ($row != null) {
				$prefer = $row->result;
				if ($prefer == null) {
					$prefer = 1;
				}
			}
			return ($prefer);
		}
	}
	
	public static function getTableMapPreferences($pdo) {
		$sql = "SELECT id FROM %room% WHERE removed is null";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute();
		$roomIds = $stmt->fetchAll();

                $userTmPref = self::getUserTmPreference($pdo);
		$roominfo = array();
		foreach ($roomIds as $aRoom) {
			$roomid = $aRoom["id"];

			$sql = "SELECT COUNT(id) as number FROM %tablemaps% WHERE roomid=?";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($roomid));
			$row = $stmt->fetchObject();
			$imgAvailable = false;
			if ($row->number > 0) {
				$imgAvailable = true;
			}
			
			$userWantsMapForThisTable = 0;
			if ((intval($userTmPref) == 1) && $imgAvailable) {
				$userWantsMapForThisTable = 1;
			}
			$tablepositions = null;
			if ($userWantsMapForThisTable == 1) {
				$tablepositions = self::getTableMapCore($pdo,$roomid);
			}
                        if ($userTmPref == 2) {
                                $userWantsMapForThisTable = 3;
                        }
			
			$roominfo[] = array("roomid" => $roomid, "displaymap" => $userWantsMapForThisTable,"tablepositions" => $tablepositions);
		}
		echo json_encode($roominfo);
	}
	
	public static function uploadimg($roomid) {
		if ($_FILES['tmimgfile']['error'] != UPLOAD_ERR_OK               //checks for errors
				&& is_uploaded_file($_FILES['tmimgfile']['tmp_name'])) { //checks that file is uploaded
			//header("Location: ../infopage.html?e=manager.html=Kann_Datei_nicht_laden.");
			echo json_encode(array("status" => "FAILED","msg" => "Kann Datei nicht laden."));
			return;
		}
		
		if(!file_exists($_FILES['tmimgfile']['tmp_name']) || !is_uploaded_file($_FILES['tmimgfile']['tmp_name'])) {
			//header("Location: ../infopage.html?e=manager.html=Datei_nicht_angegeben.");
			echo json_encode(array("status" => "FAILED","msg" => "Datei nicht angegeben."));
			return;
		}
		
		$content = file_get_contents($_FILES['tmimgfile']['tmp_name']);
		
		if ($_FILES['tmimgfile']['error'] != UPLOAD_ERR_OK               //checks for errors
				&& is_uploaded_file($_FILES['userfile']['tmp_name'])) { //checks that file is uploaded
			//header("Location: ../infopage.html?e=manager.html=Kann_Datei_nicht_laden.");
			echo json_encode(array("status" => "FAILED","msg" => "Kann Datei nicht laden!"));
			return;
		}
		
                $fn = $_FILES['tmimgfile']['tmp_name'];
	
		try {
                        $img = CommonUtils::scaleImg($fn, 900);
                        $image = $img["img"];
			$wx = $img["w"];
			$wy = $img["h"];
			
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			
			self::deleteTableMap($pdo,$roomid);
			
			$sql = "INSERT INTO %tablemaps% (`id`,`img`,`sizex`,`sizey`,`roomid`) VALUES(NULL,?,?,?,?)";
                        CommonUtils::execSql($pdo, $sql, array($image,$wx,$wy,$roomid));
			
			echo json_encode(array("status" => "OK","msg" => "Bild hochgeladen!"));
		} catch (Exception $e) {
			echo json_encode(array("status" => "FAILED","msg" => "Bild konnte nicht hochgeladen werden!"));
		}
	}
	
	private static function getAllTablesOfRoom($pdo,$roomid) {
		$sql = "SELECT id FROM %resttables% WHERE roomid=? AND active=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($roomid,1));
		$tables = $stmt->fetchAll();
		return $tables;
	}
	
	public static function getTableMapImgAsPng($pdo,$roomid,$tableid,$tablelist,$showBubbles) {
		if(session_id() == '') {
			session_start();
		}
		$sizefactor = intval(self::getCircleSizeFactor($pdo));

		if (is_null($roomid)) {
			$sql = "SELECT roomid FROM %resttables% WHERE id=?";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($tableid));
			$row = $stmt->fetchObject();
			$roomid = $row->roomid;
		}
		
		$sql = "SELECT img,sizex,sizey from %tablemaps% WHERE roomid=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($roomid));

		if ($stmt->rowCount() > 0) {
			$row = $stmt->fetchObject();
			header("Content-Disposition: attachment; filename=room-map.png");
			header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
			header("Pragma: no-cache");
			header("Expires: Mon, 20 Dec 1998 01:00:00 GMT" );
			header('Content-Type: ' . image_type_to_mime_type(IMAGETYPE_PNG));
			
			$img = $row->img;
			$sizex = $row->sizex;
			$sizey = $row->sizey;
			$php_img = imagecreatefromstring($img);
			
			if ($showBubbles) {
				$tables = self::getAllTablesOfRoom($pdo,$roomid);
				foreach($tables as $aTable) {
					$sql = "SELECT x,y FROM %tablepos% WHERE tableid=?";
					$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
					$stmt->execute(array($aTable["id"]));
					$affectedRows = $stmt->rowCount();
					if ($affectedRows == 1) {
						$positions = $stmt->fetchAll();
						foreach ($positions as $pos)
						$greenCircle = imagecolorallocate($php_img, 0, 255, 0);
					
						$redCircle = imagecolorallocate($php_img, 255, 0, 0);
						$black = imagecolorallocate($php_img, 0, 0, 0);
						
						$width = imagesx($php_img);
						$posx = $sizex / 100 * intval($pos["x"]);
						$posy = $sizey / 100 * intval($pos["y"]);
	
						$circsize = $width/50 * $sizefactor;
						$bordersize = 3*$sizefactor/2;
	
						imagefilledellipse($php_img, $posx, $posy, $circsize + $bordersize,$circsize + $bordersize, $black);
						if ((!is_null($tableid)) && ($tableid == $aTable["id"])) {
							imagefilledellipse($php_img, $posx, $posy, $circsize + $bordersize, $circsize + $bordersize, $redCircle);
						}
						if (!is_null($tablelist)) {
							foreach($tablelist as $aTableInList) {
								if ($aTable["id"] == $aTableInList["id"]) {
									imagefilledellipse($php_img, $posx, $posy, $circsize + $bordersize, $circsize + $bordersize, $redCircle);
									break;
								}
							}
						}
				
						imagefilledellipse($php_img, $posx, $posy, $circsize, $circsize, $greenCircle);
					}
				}
	
			} // showBubbles
			imagepng($php_img, NULL);
			imagedestroy($php_img);
		} else {
			header('Content-Type: image/png');
			readfile('../img/empty-room.png');
		}
	}
	
	public static function getUnpaidTablesMapImgAsPng($roomid,$showBubbles) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$roomtables = new Roomtables();
		$unpaidTables = $roomtables->getUnpaidTablesCore($pdo, $roomid);

		self::getTableMapImgAsPng($pdo,$roomid,null,$unpaidTables,$showBubbles);
	}
	
	
	private static function getTablePosition($pdo,$tableid) {
		$sql = "SELECT x,y FROM %tablepos% WHERE tableid=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($tableid));
		if ($stmt->rowCount() > 0) {
			$row = $stmt->fetchObject();
			return array("x" => $row->x, "y" => $row->y);
		} else {
			return null;
		}
	}
	
	public static function setPosition($tableid,$x,$y) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		
		$sql = "SELECT roomid FROM %resttables% WHERE id=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($tableid));
		$row = $stmt->fetchObject();
		$roomid = $row->roomid;
		$sql = "SELECT count(id) as number from %tablemaps% WHERE roomid=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($roomid));
		$row = $stmt->fetchObject();
		if ($row->number > 0) {
			$sql = "DELETE FROM %tablepos% WHERE tableid=?";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($tableid));
			
			$sql = "INSERT INTO %tablepos% (`id`,`tableid`,`x`,`y`) VALUES(NULL,?,?,?)";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($tableid,$x,$y));
		}
		echo json_encode(array("status" => "OK"));
	}
}

function isCurrentUserAdminOrManager() {
	if(session_id() == '') {
		session_start();
	}
	if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
		// no user logged in
		return false;
	} else {
		return ($_SESSION['is_admin'] || $_SESSION['right_manager']);
	}
}


$command = $_GET["command"];

if ($command == 'getTableMapImgAsPng') {
	$pdo = DbUtils::openDbAndReturnPdoStatic();
	
	$showBubbles = true;
	if (isset($_GET["showBubbles"])) {
		if ($_GET["showBubbles"] == 0) {
			$showBubbles = false;
		}
	}
	
	$tableid = null;
	if (isset($_GET["tableid"])) {
		$tableid = $_GET["tableid"];
	}
	
	$roomid = null;
	if (isset($_GET["roomid"])) {
		$roomid = $_GET["roomid"];
	}
	
	TableMap::getTableMapImgAsPng($pdo,$roomid,$tableid,null, $showBubbles);

	return;
} else if ($command == 'getTableMapPreferences') {
	$pdo = DbUtils::openDbAndReturnPdoStatic();
	TableMap::getTableMapPreferences($pdo);
	return;
} else if ($command == 'getUnpaidTablesMapImgAsPng') {
	$showBubbles = true;
	if (isset($_GET["showBubbles"])) {
		if ($_GET["showBubbles"] == 0) {
			$showBubbles = false;
		}
	}
	TableMap::getUnpaidTablesMapImgAsPng($_GET["roomid"],$showBubbles);
	return;
}

if (!isCurrentUserAdminOrManager()) {
	echo json_encode(array("status" => "ERROR", "code" => ERROR_NOT_AUTHOTRIZED, "msg" => ERROR_NOT_AUTHOTRIZED_MSG));
	return;
}
if ($command == "getRoomTableMap") {
	TableMap::getRoomTableMap();
} else if ($command == 'uploadimg') {
        if (ISDEMO) {
                echo json_encode(array("status" => "ERROR","msg" => "Vorgang im Demo-System nicht erlaubt", "code" => "FORBIDDEN"));
        } else {
                TableMap::uploadimg($_POST["roomid"]);
        }
} else if ($command == 'setPosition') {
        if (ISDEMO) {
                echo json_encode(array("status" => "ERROR","msg" => "Vorgang im Demo-System nicht erlaubt", "code" => "FORBIDDEN"));
        } else {
                TableMap::setPosition($_POST['tableid'],$_POST['x'],$_POST['y']);
        }
} else if ($command == 'getTableMap') {
	TableMap::getTableMap($_GET["roomid"]);
} else if ($command == 'deleteTableMap') {
        if (ISDEMO) {
                echo json_encode(array("status" => "ERROR","msg" => "Vorgang im Demo-System nicht erlaubt", "code" => "FORBIDDEN"));
        } else {
                $pdo = DbUtils::openDbAndReturnPdoStatic();
                TableMap::deleteTableMap($pdo,$_POST["roomid"]);
                echo json_encode(array("status" => "OK"));
        }
} 


?>