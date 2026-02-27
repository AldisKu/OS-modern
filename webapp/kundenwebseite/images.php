<?php

require_once (__DIR__. '/../php/config.php');
require_once (__DIR__. '/php/pager.php');
require_once (__DIR__. '/php/replacer.php');
require_once (__DIR__. '/php/menumanager.php');
require_once (__DIR__. '/php/imager.php');
require_once (__DIR__. '/../php/dbutils.php');

header('Content-Type: text/html; charset=utf-8');

$headerFileTemplate = __DIR__. '/vorlagen/classic/header.html';
$footerFileTemplate = __DIR__. '/vorlagen/classic/footer.html';

$pfeil_links = __DIR__. '/vorlagen/classic/pfeil-links.png';
$pfeil_rechts = __DIR__. '/vorlagen/classic/pfeil-rechts.png';


$pdo = DbUtils::openDbAndReturnPdoStatic();

$header = Pager::readAndSubstituteFile($pdo,$headerFileTemplate);

$menu = Pager::getMenuItems($pdo,"images", null);

$footer = Pager::readAndSubstituteFile($pdo,$footerFileTemplate);

$images = Imager::imagesInFolder($pdo);

$imgno = 0;
if(isset($_GET['i']) && !empty($_GET['i'])){
	$imgno = $_GET['i'];
}

$prevImg = $imgno - 1;
if ($prevImg < 0) {
	$prevImg = count($images) - 1;
}

$nextImg = $imgno + 1;
if ($nextImg >= count($images)) {
	$nextImg = 0;
}

$txt = "";
foreach ($images as $im) {
	$txt .= $im["image"];
}

$content = Imager::getPageLayout($images,$prevImg,$imgno,$nextImg);

//$content = $txt;

//$content = "<div class='content'>" . Pager::readAndSubstituteFile($pdo, $contentTemplate) . "</div>";

$page = $header . $menu . $content . $footer;
echo $page;
