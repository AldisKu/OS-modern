<!DOCTYPE html>
<html lang="de">
    <head>
    <title>Lieferaufträge</title>
    <meta name="viewport" content="width=device-width, initial-scale=1"> 
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <meta name="author" content="Stefan Pichel">
	<link rel="stylesheet" type="text/css" href="css/bestformat.css?v=2.9.12">
        <link rel="stylesheet" href="css/delivery.css?v=2.9.12" />
        <link rel="stylesheet" href="php/3rdparty/orderstyle/w3.css?v=2.9.12" />

    
    <script src="php/3rdparty/jquery-2.2.4.min.js"></script>
    <script src="php/3rdparty/jqueryui1-12-0/jquery-ui.min.js"></script>
  
   <script src="utilities.js"></script>
   <script src="elements/ordersview.js"></script>
</head>

<body>
 
        <div class="w3-container w3-teal">
                <div class="w3-left"><h1>Lieferaufträge <img src="img/connection.png" class="connectionstatus" style="display:none;" /> <img src="img/printerstatus.png" class="printerstatus" style="display:none;" /> <img src="img/tsestatus.png" class="tsestatus" style="display:none;" /> <img src="img/tasksstatus.png" class="tasksstatus" style="display:none;" /></h1></div>
                <div class="w3-right" id="mainmenu"><h2><span class="mainmenu"><img class="mainmenu" src="php/3rdparty/images/round_menu_black_24dp.png" alt="Hauptmenü"/> Menü</span></h2><div id="mainmenulist" class="w3-container" style="display:none;">XXX</div></div>      
        </div> 
           
        <br /><br />
        <div class="w3-container w3-blue">
                <h1>Neue Aufträge</h1>
        </div> 
        <div class="w3-container" id="neworderslist">
                <img src="php/3rdparty/images/ajax-loader.gif" />
        </div>        

        <hr />
       <br /><br />
        <div class="w3-container w3-blue">
                <h1>Abgeschlossene Aufträge</h1>
        </div> 
        <div class="w3-container" id="doneorderslist">
        </div>    
        
       <br /><br />
  
        
        <div class="w3-container w3-teal w3-padding-16">
         <div class="w3-bar w3-teal">
  <div id="loggedinuser" class="w3-left">w3-left</div>
  <div id="versioninfo" class="w3-right">w3-right</div>
</div> 
        </div> 
</body>
</html>