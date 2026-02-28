<html>
    <head>
    <title>Ansicht Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1"> 
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <meta name="author" content="Stefan Pichel">
	<link rel="stylesheet" type="text/css" href="css/bestformat.css?v=2.9.12">
	
	<link rel="stylesheet" href="css/ospage.css" />
  
  
  
    <link rel="stylesheet" href="php/3rdparty/orderstyle/orderstyle.min.css" />
    <link rel="stylesheet" href="php/3rdparty/orderstyle/jquery.mobile.icons.min.css" />

   <link rel="stylesheet" href="php/3rdparty/jquery.mobile-1.4.0.min.css" type="text/css" />
   <script src="php/3rdparty/jquery-2.2.4.min.js"></script>
   <script src="php/3rdparty/jquery.mobile-1.4.0.min.js"></script>
    
   <script src="utilities.js?v=2.9.12"></script>
   <script src="receiptutils.js"></script>
   <script src="elements/dash.js"></script>
   <script src="php/3rdparty/Chart.bundle.min.js"></script>
   
   <script>
   // insert waiterdesk elements here
   </script>
</head>

<style>
.rounddiv {
    border-radius: 25px;
    border: 5px solid #73AD21;
    padding: 20px;
    background-color: #111111;
}

.rounddiv h1 {
	font-size: 25px;
	text-align: center;
	color: white;
	background-color: gray;
	border-bottom: 15px;
}
</style> 

<body>

<script>	
	var lang = 0;
	var decpoint = ",";
	var currency = "EUR";
	
	var mainmenu = [];
	var version = "";
	var loggedinUser = "";
	
	var dashslot1 = 1;
	var dashslot2 = 2;
	var dashslot3 = 3;

	$(document).ready(function () {
		getGeneralConfigItems();
		initializeMainMenu("#modulemenu");
		hideMenu();
		//doAjax("GET", "php/contenthandler.php?module=admin&command=getJsonMenuItemsAndVersion", null, saveMenuInfo, null, true);
	});

	function getGeneralConfigItems() {
		doAjax("GET", "php/contenthandler.php?module=admin&command=getGeneralConfigItems", null, insertGeneralConfigItems, "Fehler Konfigurationsdaten");
		intervalGetPrinterStatus(5);
		intervalGetDashReports(10);
	}

	function insertGeneralConfigItems(configResult) {
		if (configResult.status == "OK") {
			var values = configResult.msg;

			decpoint = values.decpoint;
			currency = values.currency;
			lang = values.userlanguage;
			
			dashslot1 = values.dashslot1;
			dashslot2 = values.dashslot2;
			dashslot3 = values.dashslot3;

		} else {
			$("#contentpart").hide();
			setTimeout(function(){document.location.href = "index.html"},250); // not logged in
		}
	}
	
	function saveMenuInfo(menuAndVersion) {
		if (menuAndVersion.loggedin == 1) {
		    loggedinUser = menuAndVersion.user;
		    $("#loggedinuser").html("&nbsp;" + loggedinUser);

		    $("#versioninfo").html(menuAndVersion.version);
		    version = menuAndVersion.version;

		    $.each(menuAndVersion.menu, function (i, module) {
			var name = module.name;
			var link = module.link;

			mainmenu[mainmenu.length] = { name: name, link: link };
		    });

		    $("#mainmenubtn").show();
		} else {
		    $("#mainmenubtn").hide();
		}
		bindMainMenuButton();
	    }
	    
	function bindMainMenuButton() {
		$("#mainmenudlg").dialog(
			{autoOpen: false,
			modal: true,
			height: 400,
			width: 200,
			position:{my:"right top",at:"right top", of:"body"},
			buttons: {
			    Abbrechen: function() {$(this).dialog("close"); }
			}
		});
	
	
		$("#mainmenubtn").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();

			var txt = "<div><ul class='mainmenuchoice'>";
			for (var i = 0; i < mainmenu.length; i++) {
			    txt += "<li id='mainmenu_" + i + "' class='mainmenuitem' >" + toHtml(mainmenu[i].name) + "</li>";
			}
			txt += "</ul></div>";
			$("#mainmenudlg").html(txt);

			var height = 280 + mainmenu.length * 40;

			$("#mainmenudlg").dialog(
			    {autoOpen: false,
			    modal: true,
			    height: height,
			    width: 200,
			    position:{my:"right top",at:"right top", of:"body"},
			    buttons: {
			    Abbrechen: function() {$(this).dialog("close"); }
			    }
			});

			$("#mainmenudlg").dialog("open");

			$(".mainmenuitem").off("click").on("click", function (e) {
			    e.stopImmediatePropagation();
			    e.preventDefault();
			    var selectedmenuindex = parseInt(this.id.split('_')[1]);
			    var url = mainmenu[selectedmenuindex].link;
			    $("#mainmenudlg").dialog("close");
			    setTimeout(function(){document.location.href = url},250);
			});
		});
	}
	
	function intervalGetDashReports(seconds) {
		doAjax("GET","php/contenthandler.php?module=admin&command=getdashreports",null,insertDashReports,null,true);
		var fetchTimer = setInterval(function() {
			doAjax("GET","php/contenthandler.php?module=admin&command=getdashreports",null,insertDashReports,null,true);
		}, seconds * 1000);
	}
	
	function insertDashReports(answer) {
		if (answer.status != "OK") {
			alert("Fehler bei der Abfrage: " + answer.msg);
			setTimeout(function(){document.location.href = "index.html"},250); // not logged in
		} else {
			var dash = new Dash();
			createDashSlot(dash,"#slot1div",dashslot1,answer.msg.stat);
			createDashSlot(dash,"#slot2div",dashslot2,answer.msg.stat);
			createDashSlot(dash,"#slot3div",dashslot3,answer.msg.stat);
		}
		
	}
	
	function createDashSlot(dash,slotId,diagramNumber,stat) {
		if (diagramNumber == 0) {
			$(slotId).hide();
			return;
		}
		$(slotId).show();
		if (diagramNumber == 1) {
			dash.createUserCash(slotId,stat.usersums, currency);
		} else if (diagramNumber == 2) {
			dash.createTablesReport(slotId,stat.tables, currency);
		} else if (diagramNumber == 3) {
			dash.createProdCountReport(slotId,stat.prodscount, currency);
		} else if (diagramNumber == 4) {
			dash.createProdSumReport(slotId,stat.prodssum, currency);
		} else if (diagramNumber == 5) {
			dash.createMonthReport(slotId,stat.thismonth, currency);
		} else if (diagramNumber == 6) {
			dash.createDayReport(slotId,stat.today, currency);
		} else if (diagramNumber == 7) {
			dash.createDurationReport(slotId,stat.durations, currency);
		};
	}

</script>

<div data-role="page" id="index-page">
	<div data-role="panel" id="modulepanel" data-position="right" data-display="overlay">
        <ul data-role="listview" id="modulemenu" data-divider-theme="a" data-inset="true">
            <li data-role="list-divider" data-theme="b" data-role="heading">Hauptmenü</li>
        </ul>
    </div><!-- /panel -->
    <div data-role="header" data-theme="b" data-position="fixed" id="theheader">
         <h1>OrderSprinter <img src="img/connection.png" class="connectionstatus" style="display:none;" /> <img src="img/printerstatus.png" class="printerstatus" style="display:none;" /> <img src="img/tsestatus.png" class="tsestatus" style="display:none;" /> <img src="img/tasksstatus.png" class="tasksstatus" style="display:none;" /></h1>
		 <div data-type="horizontal" style="top:0px;position:absolute;float:right;z-index:10;display:inline;" align="right" class="ui-btn-right"> 
			<a href="#" data-role="button" data-icon="arrow-d" data-ajax="false" id="menuswitch">Hauptmenü</a> 
		 </div>
    </div>
    
    <div data-role="content" id="main">    



	<table>
		<tr>
			<td><div id="slot1div" class="rounddiv"><h1></h1><canvas id="slot1" width="550" height="550"></canvas></div>	
			<td><div id="slot2div" class="rounddiv"><h1></h1><canvas id="slot2" width="550" height="550"></canvas></div>
			<td><div id="slot3div" class="rounddiv"><h1></h1><canvas id="slot3" width="550" height="550"></canvas></div>	
		</tr>
		
	</table>
	
	
</div> <!-- main -->
 
  <div data-role="footer" data-theme="b" id="thefooterr"> 
		<div class="ui-grid-a">
			<div class="ui-block-a userinfo" id="loggedinuser"></div>
			<div class="ui-block-b grid_right" id="versioninfo"></div>
		</div><!-- /grid-a -->
	</div> <!--  footer  -->
	
</div>

<div id="mainmenudlg" title="Hauptmenü">Hauptmenü</div>
	   
</body>
</html>