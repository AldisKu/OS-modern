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
$contentTemplate = __DIR__. '/vorlage-menu.txt';

$pdo = DbUtils::openDbAndReturnPdoStatic();

$prodtype = $_GET["pt"];

$header = Pager::readAndSubstituteFile($pdo,$headerFileTemplate);

$menu = Pager::getMenuItems($pdo,"menu", $prodtype);

$footer = Pager::readAndSubstituteFile($pdo,$footerFileTemplate);

$content = "<div class='content'>" . Pager::readAndSubstituteFile($pdo, $contentTemplate) . "</div>";

$content = str_replace("{Produktkategorie}",Menumanager::getProdTypeName($pdo,$prodtype),$content);

$menutxt = Menumanager::getMenu($pdo, $prodtype);
$content = str_replace("{Menu}", $menutxt, $content);

$page = $header . $menu . $content . $footer;
echo $page;



?>