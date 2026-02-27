<?php

class Imager {

	private static function endsWith($haystack, $needle) {
		// search forward starting from end minus needle length characters
		return $needle === "" || (($temp = strlen($haystack) - strlen($needle)) >= 0 && strpos($haystack, $needle, $temp) !== FALSE);
	}
	
	public static function imagesInFolder($pdo) {
		if (is_null($pdo)) {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
		}
		
		$files = scandir("restaurantbilder");

		$images = array();
		foreach($files as $aFile) {
			if (self::endsWith(strtolower($aFile), ".jpg")
					||
					self::endsWith(strtolower($aFile), ".png")
					||
					self::endsWith(strtolower($aFile), ".gif")
			) {
 				$fileWithoutExtension = substr($aFile, 0, strlen($aFile) - 4);
				if (file_exists("restaurantbilder/" . $fileWithoutExtension . ".txt")) {
					$images[] = array("image" => $aFile, "description" => $fileWithoutExtension . ".txt");
				} else if ("restaurantbilder/" . file_exists($fileWithoutExtension . ".TXT")) {
					$images[] = array("image" => $aFile, "description" => $fileWithoutExtension . ".TXT");
				} else {
					$images[] = array("image" => $aFile, "description" => null);
				}
			}
		}
		
		return $images;
	}
	
	public static function getPageLayout($images,$prevImg,$imgno,$nextImg) {
		$html = '<br><table id=wrapper><tr>';
		
		$html .= '<td class=nav><input type=button onClick="parent.location=\'images.php?i=' . $prevImg . '\'" value="&lt;" />';
		
		$html .= '<td class=imgarea>';
		if (!is_null($images[$imgno]["description"])) {
			$txt = file_get_contents("restaurantbilder/" . $images[$imgno]["description"]);
			$txt = str_replace("\n","<br>",$txt);
			$html .= $txt . '<br><br>';
		}
		
		$html .= '<img id=displayedimage class=restimg src="restaurantbilder/' . $images[$imgno]["image"] . '" />';
		
		$html .= '<td class=nav><input type=button onClick="parent.location=\'images.php?i=' . $nextImg . '\'" value="&gt;" />';
		$html .= '</tr></table>';

		return $html;
	}
}


?>