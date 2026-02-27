<!DOCTYPE html>
<html lang="de">

<head>
    <title>OrderSprinter Hilfe</title>
    <meta http-equiv=“cache-control“ content=“no-cache“>
<meta http-equiv=“pragma“ content=“no-cache“>
<meta http-equiv=“expires“ content=“0″>
    <meta name="viewport" content="width=device-width, initial-scale=1"> 
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
     <meta name="author" content="Stefan Pichel">

 <link rel="stylesheet" type="text/css" href="css/bestformat.css?v=2.9.12">
 <link rel="stylesheet" type="text/css" href="css/numfield.css?v=2.9.12">
 
	<link rel="stylesheet" href="php/3rdparty/orderstyle/orderstyle.min.css" />
 	<link rel="stylesheet" href="php/3rdparty/orderstyle/jquery.mobile.icons.min.css" />
  
   <link rel="stylesheet" href="php/3rdparty/jquery.mobile-1.4.0.min.css" type="text/css" />
   <script src="php/3rdparty/jquery-2.2.4.min.js"></script>
   <script src="php/3rdparty/jquery.mobile-1.4.0.min.js"></script>
   <script src="utilities.js?v=2.9.12"></script>
</head>

<body>
<script>

$(document).on("pageinit", "#help-page", function () {
	initializeMainMenu("#modulemenu");
	hideMenu();
	$.ajaxSetup({ cache: false });
});

</script>

<div data-role="page" id="help-page" data-theme="c">
	<div data-role="panel" id="modulepanel" data-position="right" data-display="overlay">
        <ul data-role="listview" id="modulemenu" data-divider-theme="a" data-inset="true">
            <li data-role="list-divider" data-theme="b" data-role="heading">Hauptmenü</li>
        </ul>
    </div><!-- /panel -->
    <div data-role="header" data-theme="b" data-position="fixed" id="theheader" style="background-color:black;">
         <h1>OrderSprinter Hilfe <img src="img/connection.png" class="connectionstatus" style="display:none;" /> <img src="img/printerstatus.png" class="printerstatus" style="display:none;" /> <img src="img/tsestatus.png" class="tsestatus" style="display:none;" /> <img src="img/tasksstatus.png" class="tasksstatus" style="display:none;" /></h1>
		 <div data-type="horizontal" style="top:0px;position:absolute;float:right;z-index:10;display:inline;" align="right" class="ui-btn-right"> 
			<a href="#" data-role="button" data-icon="arrow-d" data-ajax="false" id="menuswitch">Hauptmenü</a> 
		 </div>
    </div>
    
    <div data-role="content">    

	<div data-role="collapsible" data-content-theme="c" data-collapsed="true" data-theme="e" >	
		<?php include 'elements/help-views.txt'; ?>
	</div>
	    
	<div data-role="collapsible" data-content-theme="c" data-collapsed="true" data-theme="e" >	
		<?php include 'elements/help-ordering.txt'; ?>		 
	</div>
  
	<div data-role="collapsible" data-content-theme="c" data-collapsed="true" data-theme="e" >	
		<?php include 'elements/help-paydesk.txt'; ?>		 
	</div>
	    
	<div data-role="collapsible" data-content-theme="c" data-collapsed="true" data-theme="e" >	
		<?php include 'elements/help-receipts.txt'; ?>		 
	</div>
	    
	<div data-role="collapsible" data-content-theme="c" data-collapsed="true" data-theme="e" >	
		<?php include 'elements/help-prodimages.txt'; ?>		 
	</div>
	
   </div>   
    
    
    <div data-role="footer" data-theme="b" id="thefooterr" style="background-color:black;"> 
		<div class="ui-grid-a">
			<div class="ui-block-a userinfo" id="loggedinuser"></div>
			<div class="ui-block-b grid_right" id="versioninfo"></div>
		</div><!-- /grid-a -->
	</div> <!--  footer  -->
	
</div>
	
</div>
</body>

</html>

