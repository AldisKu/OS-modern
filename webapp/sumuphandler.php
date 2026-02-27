<html>

<head>
   <head>
    <title>Zahlungsinformation</title>
    <meta name="viewport" content="width=device-width, initial-scale=1"> 
    <meta http-equiv="content-type" content="text/html; charset=utf-8">

 <link rel="stylesheet" type="text/css" href="css/bestformat.css">
 
	<link rel="stylesheet" href="php/3rdparty/orderstyle/orderstyle.min.css" />
 	<link rel="stylesheet" href="php/3rdparty/orderstyle/jquery.mobile.icons.min.css" />
  
   <link rel="stylesheet" href="php/3rdparty/jquery.mobile-1.4.0.min.css" type="text/css" />
   <script src="php/3rdparty/jquery-2.2.4.min.js"></script>
   <script src="php/3rdparty/jquery.mobile-1.4.0.min.js"></script>
   <script src="utilities.js?v=2.9.12"></script>
</head>

<body>
<script>

var SUM_HEADLINE = ["SumUp-Zahlungsinformation","SumUp Payment Information","SumUP información sobre pagamiento"];
var SUM_TO_ORDER = ["Weiter zur Bestellseite","Continue to ordering view","Continuar a página de orden"];
var SUM_TO_PAYMENT = ["Zurück zur Kassenansicht","Back to paydesk view","Volver a la vista de pagamiento"];
var SUM_BILL_ID = ["Rechnungsnummer","Bill id","ID de factura"];
var SUM_STATUS_FAILED = ["Fehlgeschlagen","Failed","Abortado"];
var SUM_STATUS_OK = ["OK","OK","OK"];
var SUM_REASON = ["Grund","Reason","Razón"];
var SUM_CANCEL_ITEM = ["Stornierung","Cancellation","Revocación"];
var SUM_CANCELLATION_OK = ["Die Zahlung wurde in OrderSprinter storniert.","The payment was cancelled in OrderSprinter.","El pago se canceló en OrderSprinter."];

var nextpage = "paydesk.html?version=2.9.12";

var lang;

function setLanguage(language) {
	lang = language;
	
	$("#headline").html(SUM_HEADLINE[lang]);
	
	urlsuffix = location.search;
	if (urlsuffix.indexOf('billinformation=') < 0) {
		alert("Fehler beim Aufruf der Seite - GET-Parameter fehlen!");
		return;
	} else {
		var billinformation = '';
		var smpStatus = '';
		var smpFailureCause = '';
		var urlParts = urlsuffix.split(/&|\?/);
		for (var i=0;i<urlParts.length;i++) {
			var aPart = urlParts[i];
			if (aPart.indexOf('billinformation=') === 0) {
				var parts = aPart.split("=");
				billinformation = parts[1];
			} else if (aPart.indexOf('smp-status=') === 0) {
				var parts = aPart.split("=");
				smpStatus = parts[1];
			} else if (aPart.indexOf('smp-failure-cause=') === 0) {
				var parts = aPart.split("=");
				smpFailureCause = parts[1];
			}
		}
		if (billinformation === "") {
			alert("Fehler beim Aufruf der Seite - Rechnungsinformation wurde nicht übermittelt!");
			return;
		}
		
		var billinfoparts = billinformation.split("-");
		
		if (billinfoparts.length < 4) {
			alert("Fehler beim Aufruf der Seite - Rechnungsinformation wurde nicht komplett übermittelt!");
			return;
		}
		
		var billid = billinfoparts[0];
		var nextPage = billinfoparts[1];
		var tableid = billinfoparts[2];
		var randvalue = billinfoparts[3];
		
		if (smpStatus === "failed") {
			nextPage = "p";
		}

		if (nextPage === "p") {
			$("#nextpagebtntxt").html(SUM_TO_PAYMENT[lang]);
			nextpage = "paydesk.html?t=" + tableid + "&version=2.9.12";
		} else {
			$("#nextpagebtntxt").html(SUM_TO_ORDER[lang]);
			nextpage = "waiter.html?version=2.9.12";
		}
		
		var txt = "<table class='viewtable'>";
		txt += "<tr><td>" + SUM_BILL_ID[lang] + "<td>" + billinfoparts[0] + "</tr>";
		if (smpStatus === "failed") {
			txt += "<tr><td>Status<td><span style='color:red;'>" + SUM_STATUS_FAILED[lang] + "</span></tr>";
			txt += "<tr><td>" + SUM_REASON[lang] + "<td>" + smpFailureCause + "</tr>";
			txt += "<tr><td>" + SUM_CANCEL_ITEM[lang] + '<td id="cancelmsg"><img id="progressimg" src="php/3rdparty/images/ajax-loader.gif" /></tr>';
		} else {
			txt += "<tr><td>Status<td><span style='color:green;'>" + SUM_STATUS_OK[lang] + "</span></tr>";
		}
		txt += "</table>";
		$("#billstatus").html(txt);
		
		if (smpStatus === "failed") {
			cancelPayment(billid, randvalue);
		}
	}
}

function cancelPayment(billid, randvalue) {
	var data = {
		billid: billid,
		randvalue: randvalue
	};
	doAjax("POST", "php/contenthandler.php?module=bill&command=cancelCardPayment", data, handleCancelAnswer, null, true);
}

function handleCancelAnswer(answer) {
	if (answer.status !== "OK") {
		alert("Error: " + answer.msg);
		return;
	}
	
	setTimeout(function(){
		$("#cancelmsg").html(SUM_CANCELLATION_OK[lang]);},250
	);
}

function binding() {
	$("#nextpagebtn").off("click").on("click", function (e) {
		e.stopImmediatePropagation();
		e.preventDefault();
		setTimeout(function(){document.location.href = nextpage;},250);
	});
}

function getGeneralConfigItems() {
	doAjax("GET", "php/contenthandler.php?module=admin&command=getGeneralConfigItems", null, insertGenConfigStartRest, "Fehler Konfigurationsdaten");
}
	
function insertGenConfigStartRest(configResult) {
	
	if (configResult.status === "OK") {
		var values = configResult.msg;
		
		setLanguage(values.userlanguage);
	
		binding();
	} else {
		setTimeout(function(){document.location.href = "index.html"},250); // not logged in
	}
}

$(document).on("pageinit", "#index-page", function () {
	
	initializeMainMenu("#modulemenu");
	hideMenu();
	$.ajaxSetup({ cache: false });
 	
 	getGeneralConfigItems();
});

</script>

<div data-role="page" id="index-page" data-theme="c">
	<div data-role="panel" id="modulepanel" data-position="right" data-display="overlay">
        <ul data-role="listview" id="modulemenu" data-divider-theme="a" data-inset="true">
            <li data-role="list-divider" data-theme="b" data-role="heading">Hauptmenü</li>
        </ul>
    </div><!-- /panel -->
    <div data-role="header" data-theme="b" data-position="fixed" id="theheader" style="background-color:black;">
         <h1>Rechungsinformation</h1>
		 <div data-type="horizontal" style="top:0px;position:absolute;float:right;z-index:10;display:inline;" align="right" class="ui-btn-right"> 
			<a href="#" data-role="button" data-icon="arrow-d" data-ajax="false" id="menuswitch">Hauptmenü</a> 
		 </div>
    </div>
    
    <div data-role="content">    

	<div data-role="collapsible" data-content-theme="c" data-collapsed="false" data-theme="e" id="loginmask">
		<H2><span id="headline">Sumup-Zahlungsinformation</span></H2>

		<div id="billstatus"></div>
		<form>
			<button type="submit" data-theme="f" data-icon="check" id="nextpagebtn"><span id="nextpagebtntxt">Weiter</span></button>
		</form>
	</div>  <!--  Login-Maske -->  

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

