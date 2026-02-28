<?php

class Pager {
	public static function readAndSubstituteFile($pdo,$fileName) {
		$txt = "";
		$handle = fopen ($fileName, "r");
		while (!feof($handle)) {
			//$textline = Replacer::lineSubstitution($pdo, fgets($handle));
			$textline = fgets($handle);
			$textline = Replacer::lineSubstitution($pdo, $textline);
			$txt .= $textline;
		}
		fclose ($handle);
		return $txt;
	}
	
	public static function getMenuItems($pdo,$page, $ref) {
		$highCats = Menumanager::getLevel0Categories($pdo);
		
		$menu = "<div class='menupart'><ul class='mainmenu'>";
		
		if ($page == "index") {
			$menu .= '<li class="selectedmenuitem">Über uns</li>' . "\n";
		} else {
			$menu .= '<li><a href=index.php>Über uns</a></li>' . "\n";
		}
		
		foreach ($highCats as $aCat) {
			$prodTypeId = $aCat["id"];
			$prodTypeName = $aCat["name"];
			if (($page == 'menu') && ($prodTypeId == $ref)) {
			//if ($page == 'menu') {
				$menu .= "<li class=selectedmenuitem>$prodTypeName</li>\n";
			} else {
				$menu .= "<li><a href='menu.php?pt=$prodTypeId'>$prodTypeName</a></li>\n";
			}
		}

		$images = Imager::imagesInFolder($pdo);

		if (count($images) > 0) {
			if ($page == "images") {
				$menu .= '<li class="selectedmenuitem">Bilder</li>' . "\n";
			} else {
				$menu .= '<li><a href=images.php>Bilder</a></li>' . "\n";
			}
		}
		
		if ($page == "impressum") {
			$menu .= '<li class="selectedmenuitem">Impressum</li>' . "\n";
		} else {
			$menu .= '<li><a href=impressum.php>Impressum</a></li>' . "\n";
		}
		
		
		$menu .= "</ul>&nbsp;\n</div>\n";
		return $menu;
	}
	
	
	
}

?>