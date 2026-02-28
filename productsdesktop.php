<!DOCTYPE html>
<html lang="de">
    <head>
    <title>Ansicht Produkte</title>
    <meta name="viewport" content="width=device-width, initial-scale=1"> 
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <meta name="author" content="Stefan Pichel">
	<link rel="stylesheet" type="text/css" href="css/bestformat.css?v=2.9.12">
	
	<link rel="stylesheet" href="css/ospage.css" />
	<link rel="stylesheet" href="css/tablepanel.css" />
	<link rel="stylesheet" href="css/productpanel.css" />
	
  
    <link rel="stylesheet" href="php/3rdparty/jqueryui1-12-0/jquery-ui.min.css" />
    <script src="php/3rdparty/jquery-2.2.4.min.js"></script>
    <script src="php/3rdparty/jqueryui1-12-0/jquery-ui.min.js"></script>
  
   <script src="utilities.js?v=2.9.12"></script>
  
   
   <script>
	   
var PRODDESK_LONG_NAME = ["Produktname","Product name:","Nombre del producto"];
var PRODDESK_SHORTNAME = ["Name in Bestellansicht","Name in order view","Nombre en vista del orden"];
var PRODDESK_PRICE = ["Preis","Price","Precio"];

var lang = 0;
var decpoint = '.';
var currency = 'Euro';
var loggedinUser = '';
var mainmenu = [];
var version = '';
var prods = null;
var newProdsOfType = {};

$(document).ready(function () {
	
	doAjax("GET", "php/contenthandler.php?module=admin&command=getWaiterSettings", null, insertWaiterConfig, "Fehler Konfigurationsdaten", true);
	doAjax("GET", "php/contenthandler.php?module=admin&command=getJsonMenuItemsAndVersion", null, saveMenuInfo, null, true);
	
	intervalCheckConnection(2);
	intervalGetPrinterStatus(5);
	bindBackBtn();
	bindApplyBtn();
});

function saveMenuInfo(menuAndVersion) {
    if (menuAndVersion.loggedin == 1) {
        loggedinUser = menuAndVersion.user;
        $("#loggedinuser").html("&nbsp;" + loggedinUser);
        
        $("#versioninfo").html(menuAndVersion.version);
        version = menuAndVersion.version;
    }
}

function bindBackBtn() {
	$(".backtoproductsbtn").off("click").on("click", function (e) {
		e.stopImmediatePropagation();
		e.preventDefault();
		setTimeout(function(){document.location.href = "products.html";},250);
	});	
}

function bindApplyBtn() {
	$(".applyproductsbtn").off("click").on("click", function (e) {
		e.stopImmediatePropagation();
		e.preventDefault();
		doApply();
	});	
}
function insertWaiterConfig(settings) {
	var isUserLoggedIn = settings.isUserLoggedIn;
	if (isUserLoggedIn != 1) {
		setTimeout(function(){document.location.href = "index.html"},250);
	} else {
		var config = settings.config;
		decpoint = config.decpoint;
		currency = config.currency;
		lang = settings.userlanguage;
	}
	doAjax("GET", "php/contenthandler.php?module=products&command=getAllActiveProducts", null, saveProducts, null, true);
}

function saveProducts(answer) {
	if (answer.status != "OK") {
		alert("Fehler: " + answer.msg);
		return;
	}
	
	prods = answer.msg;
	
	var text = '';
	for (var i=0;i<prods.length;i++) {
		var typeentry = prods[i];
		text += renderProdsOfType(typeentry);
	}
	
	$("#main").html(text);
	bindChange();
}

function renderProdsOfType(prodtype) {
	var prodtypeid = prodtype.id;
	var txt = "<table class='billtable'>";
	txt += "<tr><th id='prodtype_" + prodtypeid + "' colspan='5'>" + toHtml(prodtype.chain) + "</tr>";
	txt += "<tr><td>" + PRODDESK_LONG_NAME[lang];
	txt += "<td>" + PRODDESK_SHORTNAME[lang];
	txt += "<td>" + PRODDESK_PRICE[lang] + " (A)";
	txt += "<td>" + PRODDESK_PRICE[lang] + " (B)";
	txt += "<td>" + PRODDESK_PRICE[lang] + " (C)";
	txt += "</tr>";
	var prodsOfType = prodtype.products;
	for (var i=0;i<prodsOfType.length;i++) {
		txt += renderAProd(prodtypeid,prodsOfType[i]);
	}
	var newCounter = 1;
	txt += renderNewProdLine(prodtypeid,1);
	txt += "</table>";
	newProdsOfType[prodtypeid] = newCounter;
	return txt;
}

function renderNewProdLine(prodtypeid,counter) {
	var idSuffixOfNewProd = prodtypeid + "_new_" + counter;
	var txt = "<tr id='newprodline_" + prodtypeid + "_" + counter + "'>";
	txt += '<td><input id="longname_' + idSuffixOfNewProd + '" class="newfield" value="" />';
	txt += '<td><input id="shortname_' + idSuffixOfNewProd + '" class="newfield" value="" />';
	txt += '<td><input id="pricea_' + idSuffixOfNewProd + '" class="newfield" value="" />';
	txt += '<td><input id="priceb_' + idSuffixOfNewProd + '" class="newfield" value="" />';
	txt += '<td><input id="pricec_' + idSuffixOfNewProd + '" class="newfield" value="" /></tr>';
	return txt;
}

function renderAProd(prodtypeid,product) {
	var txt = "";
	txt += "<tr>";
	txt += "<td>" + asInput('longname_' + prodtypeid + "_" + product.id,toHtml(product.longname),product.available);
	txt += "<td>" + asInput('shortname_' + prodtypeid + "_" + product.id,toHtml(product.shortname),product.available);
	txt += "<td>" + asInput('pricea_' + prodtypeid + "_" + product.id,priceFormat(product.priceA),product.available);
	txt += "<td>" + asInput('priceb_' + prodtypeid + "_" + product.id,priceFormat(product.priceB),product.available);
	txt += "<td>" + asInput('pricec_' + prodtypeid + "_" + product.id,priceFormat(product.priceC),product.available);
	txt += "</tr>";
	return txt;
}

function priceFormat(price) {
	return price.toString().replace(".",decpoint);
}
function asInput(id,value,prodIsAvailable) {
        var spanClassTagForAvailability = "prodisavailable"; 
        if (prodIsAvailable === "0") {
                spanClassTagForAvailability = "prodisnotavailable";
        }
	var txt = '<input id="' + id + '" value="' + value + '" type="text" class="' + spanClassTagForAvailability + '" />';
	return txt;
}

function bindChange() {
	$(".billtable input").off("keyup").on("keyup", function (e) {
		e.stopImmediatePropagation();
		e.preventDefault();
		var id = this.id;
		var prodtypeid = id.split("_")[1];
		var prodidOrNewCat = id.split("_")[2];
		if (prodidOrNewCat == "new") {
			var curCounter = parseInt(id.split("_")[3]);
			var curCat = id.split("_")[0];
			var shortname = $("#shortname_" + prodtypeid + "_new_" + curCounter).val().trim();
			var longname = $("#longname_" + prodtypeid + "_new_" + curCounter).val().trim();
			var pricea = $("#pricea_" + prodtypeid + "_new_" + curCounter).val().trim();
			var priceb = $("#priceb_" + prodtypeid + "_new_" + curCounter).val().trim();
			var pricec = $("#pricec_" + prodtypeid + "_new_" + curCounter).val().trim();
			$("#" + id).data("entered",1);
			var isEnteredLongName = $("#longname_" + prodtypeid + "_new_" + curCounter).data("entered");
			var isEnteredShortName = $("#shortname_" + prodtypeid + "_new_" + curCounter).data("entered");
			var isEnteredPriceA = $("#pricea_" + prodtypeid + "_new_" + curCounter).data("entered");
			var isEnteredPriceB = $("#priceb_" + prodtypeid + "_new_" + curCounter).data("entered");
			var isEnteredPriceC = $("#pricec_" + prodtypeid + "_new_" + curCounter).data("entered");
			if (curCat == "longname") {
				if ((shortname == "") || (isEnteredShortName == undefined)) {
					$("#shortname_" + prodtypeid + "_new_" + curCounter).val(longname);
				}
			} else if (curCat == "shortname") {
				if ((longname == "") || (isEnteredLongName == undefined)) {
					$("#longname_" + prodtypeid + "_new_" + curCounter).val(shortname);
				}
			} else if (curCat == "pricea") {
				if ((priceb == "") || (isEnteredPriceB == undefined)) {
					$("#priceb_" + prodtypeid + "_new_" + curCounter).val(pricea);
				}
				if ((pricec == "") || (isEnteredPriceC == undefined)) {
					$("#pricec_" + prodtypeid + "_new_" + curCounter).val(pricea);
				}
			} else if (curCat == "priceb") {
				if ((pricea == "") || (isEnteredPriceA == undefined)) {
					$("#pricea_" + prodtypeid + "_new_" + curCounter).val(priceb);
				}
				if ((pricec == "") || (isEnteredPriceC == undefined)) {
					$("#pricec_" + prodtypeid + "_new_" + curCounter).val(priceb);
				}
			} else if (curCat == "pricec") {
				if ((pricea == "") || (isEnteredPriceA == undefined)) {
					$("#pricea_" + prodtypeid + "_new_" + curCounter).val(pricec);
				}
				if ((priceb == "") || (isEnteredPriceB == undefined)) {
					$("#priceb_" + prodtypeid + "_new_" + curCounter).val(pricec);
				}
			}
			
			if (curCounter >= newProdsOfType[prodtypeid]) {
				var txt = renderNewProdLine(prodtypeid,curCounter + 1);
				$(txt).insertAfter("#newprodline_" + prodtypeid + "_" + curCounter);
				newProdsOfType[prodtypeid] = curCounter + 1;
				bindChange();
			}
		} else {
			var prodid = prodidOrNewCat;
			colorFieldsIfNeeded(prodtypeid,prodid);
		}
	});
}
function colorFieldsIfNeeded(prodtypeid,prodid) {
	var theChangedProduct = findProduct(prodtypeid,prodid);
	if (theChangedProduct == null) {
		return;
	}
	colorTextInputField("#longname_" + prodtypeid + "_" + prodid,theChangedProduct.longname,false);
	colorTextInputField("#shortname_" + prodtypeid + "_" + prodid,theChangedProduct.shortname,false);
	colorTextInputField("#pricea_" + prodtypeid + "_" + prodid,theChangedProduct.priceA,true);
	colorTextInputField("#priceb_" + prodtypeid + "_" + prodid,theChangedProduct.priceB,true);
	colorTextInputField("#pricec_" + prodtypeid + "_" + prodid,theChangedProduct.priceC,true);
}

function colorTextInputField(idOfProperty,origContent,isNum) {
	var propOnUi = $(idOfProperty).val().trim();
	origContent = origContent.trim();
	if (isNum) {
		propOnUi = propOnUi.toString().replace(",",".");
	}
	if (propOnUi == origContent) {
		$(idOfProperty).removeClass("changedfield");
	} else {
		$(idOfProperty).addClass("changedfield");
	}
}
function findProduct(prodtypeid,prodid) {
	for (var i=0;i<prods.length;i++) {
		var typeentry = prods[i];
		if (typeentry.id == prodtypeid) {
			var prodsOfType = typeentry.products;
			for (var j=0;j<prodsOfType.length;j++) {
				var aProd = prodsOfType[j];
				if (aProd.id == prodid) {
					return aProd;
				}
			}
		}
	}
	return null;
}

function doApply() {
	var changedProds = [];
	var newProds = [];
	for (var i=0;i<prods.length;i++) {
		var typeentry = prods[i];
		var prodtypeid = typeentry.id;
		var prodsOfType = typeentry.products;
		for (var j=0;j<prodsOfType.length;j++) {
			var aProd = prodsOfType[j];
			if (isProdChanged(prodtypeid,aProd.id,aProd)) {
				changedProds[changedProds.length] = {
					prodid: aProd.id,
					longname: $("#longname_" + prodtypeid + "_" + aProd.id).val().trim(),
					shortname: $("#shortname_" + prodtypeid + "_" + aProd.id).val().trim(),
					priceA: $("#pricea_" + prodtypeid + "_" + aProd.id).val().trim().replace(",","."),
					priceB: $("#priceb_" + prodtypeid + "_" + aProd.id).val().trim().replace(",","."),
					priceC: $("#pricec_" + prodtypeid + "_" + aProd.id).val().trim().replace(",",".")
				};
			}
			
		}
		var counterOfProdType = newProdsOfType[prodtypeid];
		for (counter = 1;counter <= (counterOfProdType - 1); counter++) {
			var aNewProd = {
				prodtypeid: prodtypeid,
				longname: $("#longname_" + prodtypeid + "_new_" + counter).val().trim(),
				shortname: $("#shortname_" + prodtypeid + "_new_" + counter).val().trim(),
				priceA: $("#pricea_" + prodtypeid + "_new_" + counter).val().trim().replace(",","."),
				priceB: $("#priceb_" + prodtypeid + "_new_" + counter).val().trim().replace(",","."),
				priceC: $("#pricec_" + prodtypeid + "_new_" + counter).val().trim().replace(",",".")
			}
			if ((aNewProd.longname != "") && (aNewProd.shortname != "")) {
				newProds[newProds.length] = aNewProd;
			}
		}
	}
	var data = {
		changeset: changedProds,
		newprods: newProds
	};
	doAjax("POST", "php/contenthandler.php?module=products&command=changesetofproducts", data, handleChangeProds, null, true);
}

function isProdChanged(prodtypeid,prodid,origProd) {
	var changed = false;
	changed |= isPropertyChanged("#longname_" + prodtypeid + "_" + prodid,origProd.longname,false);
	changed |= isPropertyChanged("#shortname_" + prodtypeid + "_" + prodid,origProd.shortname,false);
	changed |= isPropertyChanged("#pricea_" + prodtypeid + "_" + prodid,origProd.priceA,true);
	changed |= isPropertyChanged("#priceb_" + prodtypeid + "_" + prodid,origProd.priceB,true);
	changed |= isPropertyChanged("#pricec_" + prodtypeid + "_" + prodid,origProd.priceC,true);
	return changed;
}
function isPropertyChanged(idOfProperty,origContent,isNum) {
	var propOnUi = $(idOfProperty).val().trim();
	origContent = origContent.trim();
	if (isNum) {
		propOnUi = propOnUi.toString().replace(",",".");
	}
	if (propOnUi == origContent) {
		return false;
	} else {
		return true;
	}
}

function handleChangeProds(answer) {
	if (answer.status != "OK") {
		alert("Fehler: " + answer.msg);
		return;
	}
	alert("Änderungen erfolgt!");
	setTimeout(function(){document.location.href = "productsdesktop.php"},250);
}
   </script>
</head>

<style>


</style> 

<body>
    
<div class="tableospage">
    <span class="header">Artikelansicht  <img src="img/connection.png" class="connectionstatus" style="display:none;" /> <img src="img/printerstatus.png" class="printerstatus" style="display:none;" /> <img src="img/tasksstatus.png" class="tasksstatus" style="display:none;" /></span>
</div>

	<input class="backtoproductsbtn input100 inputwhite" type="submit" value="Zur&uuml;ck zur mobilen Produktansicht" style="background-color: #f2d7d5;width:49%;" />
	<input class="applyproductsbtn input100 inputwhite"type="submit" value="Anwenden" style="background-color: #58d68d;width:49%;" />
	
	<div id="allwithoutheaderfooter">
		
		<div id="main">
			 <img src="php/3rdparty/images/ajax-loader.gif" />
		</div>
		
	</div>
 
	<input class="backtoproductsbtn input100 inputwhite" type="submit" value="Zur&uuml;ck zur mobilen Produktansicht" style="background-color: #f2d7d5;width:49%;" />
	<input class="applyproductsbtn input100 inputwhite"type="submit" value="Anwenden" style="background-color: #58d68d;width:49%;" />
	
  <div class="tablefooter">
	  <span id="loggedinuser"></span>
	  <span id="versioninfo"></span>
  </div>


	      
</body>
</html>