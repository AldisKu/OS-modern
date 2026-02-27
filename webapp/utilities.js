// use variables for boolean values to understand the calling methods easier
var ASYNC = true;
var SYNC = false;

function initializeMainMenu(menuid) {
	$.ajax({ type: "GET",   
		dataType: "json",
        url: "php/contenthandler.php?module=admin&command=getJsonMenuItemsAndVersion", 
        async: false,
        success : function(moduleEntries)
        {
	        $("#versioninfo").html(moduleEntries.version + "&nbsp;");
	        if (moduleEntries.loggedin == 1) {
	        	$("#loggedinuser").html("&nbsp;" + moduleEntries.user);
	        	var li='<li data-role="list-divider" data-theme="b" data-role="heading">Hauptmenü</li>';
	        	$.each(moduleEntries.menu, function (i, module) {
                                        updateLiveOrders(null,null);
                                        updateLivePayOrders(null,null);
	        		var name = module.name;
	        		var link = module.link;
	        		if ((name !== "Abmelden") && (name !== "Log out") && (name !== "Adios")) {
	        			li += '<li data-theme="e"><a href="' + link + '" target="_top" class="modulebutton">' + toHtml(name) + '</a></li>';
	        		} else {
	        			li += '<li data-theme="e"><a href="' + link + '" target="_top">' + toHtml(name) + '</a></li>';
	        		}
	        	});
	        	$(menuid).empty().append(li).promise().done(function () {
	        		$(menuid).listview("refresh");
	        	});
	        	$("#menuswitch").show();
        	} else {
        		$("#menuswitch").hide();
        	}
        },
		error: function( text ) {
			alert( "Kommunikationsproblem zum Server bei Modulabfrage!" );
		}
	});
	
	$(".modulebutton").off("click").on("click", function (e) {
		var view = $(this).attr("href");
		doAjax("POST","php/contenthandler.php?module=admin&command=setLastModuleOfUser",
				{ view: view}, null, "Problem Benutzerdatenpflege", false);
	});
        
    intervalGetPrinterStatus(5);
	intervalCheckConnection(2);
}


function intervalGetPrinterStatus(seconds) {
    doAjax("GET","php/contenthandler.php?module=admin&command=isPrinterServerActive",null,setPrinterStatus,null,true);
    var fetchTimer = setInterval(function() {
            doAjax("GET","php/contenthandler.php?module=admin&command=isPrinterServerActive",null,setPrinterStatus,null,true);
    }, seconds * 1000);
}

function setPrinterStatus(answer) {
    if (answer.status === "OK") {
        if (answer.msg === 0) {
		$(".printerstatus").show();
        } else {
		$(".printerstatus").hide();
	}
	if (answer.tasksforme === 1) {
		$(".tasksstatus").show();
	} else {
		$(".tasksstatus").hide();
	}
	if (answer.tsestatus === 1) {
		$(".tsestatus").hide();
	} else {
		$(".tsestatus").show();
	}
	return;
    }
    $(".printerstatus").hide();
}

function hideMenu() {
	$( "#modulepanel" ).panel( "close" );
	$("#menuswitch").off("click").on("click", function (e) {
			$("#menuswitch").trigger("mouseout");
			e.stopImmediatePropagation();
			e.preventDefault();
			$( "#modulepanel" ).panel( "open" );;
	});
}

// to make IE happy...
function refreshList(selector) {
	if ( $(selector).hasClass('ui-listview')) {
		$(selector).listview('refresh');
	} else {
	    $(selector).trigger('create');
	}
}

function doAjax(getOrPost, url, data, functionToCallIfSuccess, errorMsg, doAsync) {
	if (typeof doAsync === 'undefined') {
		doAsync = ASYNC;
	}
	return doAjaxWithProgress(getOrPost, url, data, functionToCallIfSuccess, null, errorMsg, doAsync, "json", null, null);
}

function showAlert(debugData) {
	console.log("Anzeige Fehler!");
	if ((debugData.cmd === undefined) || !debugData.cmd.includes("php/debug.php")) {
		if ($("#alertoverlay").length) {
			var html = "";
			html += "<br><br><p><button style='width:100%;'>Schliessen</button><br><br>";
			html += "<h1>Fehler</h1>";
			html += "<p>Aufruf: " + debugData.cmd;
			if ((debugData.errormsg !== undefined) && (debugData.errormsg !== "")) {
				html += "<p>Error: " + debugData.errormsg;
			}
			if ((debugData.xhr !== undefined) && (debugData.xhr !== "")) {
				html += "<p>XHR: " + debugData.xhr;
			}
			if (debugData.content !== undefined) {
				var contentTxt = debugData.content;
				if (contentTxt.length > 1000) {
					contentTxt = contentTxt.substring(0, 999);
				}
				html += "<p>Server-Antwort: " + debugData.content;
			} else {
				html += "<p>Server-Antwort unbekannt";
			}

			html += "<br><br><p><button style='width:100%;'>Schliessen</button>";
			$("#alerttext").html(html);
			$("#alertoverlay").show();
		} else {
			alert("Kommunikationsfehler zum Server: " + debugData.cmd);
		}
	}
}

function alertoff() {
	$("#alerttext").html("");
	$("#alertoverlay").hide();
}

function consoleAjaxErrorOutput(url,xhr,status,errorMsg) {
	console.error("AJAX Error Details: url:" + url);
	console.error("Status:", status);
	console.error("Error Message:", errorMsg);
	console.error("XHR Object:", xhr);
}

function doAjaxWithProgress(getOrPost, url, data, functionToCallIfSuccess, functionToCallForProgress, errorMsg, doAsync, dataType, functionToCallInErrorCase, argForErrorCase) {
	$.ajax({
		type: getOrPost,
		url: url,
		dataType: "text",
		data: data,
		async: doAsync,
		cache: false,
		xhrFields: {
			onprogress: functionToCallForProgress
		},
		success: function (serverAnswer) {
			try {
				var jsonContent = serverAnswer;
				if (dataType === "json") {
					jsonContent = JSON.parse(serverAnswer);
				}
				if (functionToCallIfSuccess !== null) {
					functionToCallIfSuccess(jsonContent);
				}
			} catch (err) {
				var debugData = {
					cmd: url,
					fct: functionToCallIfSuccess,
					xhr: "",
					errormsg: err,
					status: "ParseError: ",
					content: serverAnswer
				};
				showAlert("Ajax-Fehler: " + debugData);
			}
		},
		error: function (xhr, status, error) {
			if (url.includes("isPrinterServerActive") || url.includes("getDailycode")) {
				return;
			}
			if ((functionToCallInErrorCase !== undefined) && (functionToCallInErrorCase !== null)) {
				functionToCallInErrorCase(argForErrorCase);
			}
			if (xhr.status === 0 || xhr.readyState !== 4) {
				alert("Der Webserver ist nicht erreichbar: " + url);
				return;
			}
			if (url !== "php/debug.php") {
				consoleAjaxErrorOutput(url, xhr, status, errorMsg);
				var fctName = "?";
				if (functionToCallIfSuccess !== null) {
					fctName = functionToCallIfSuccess.name;
				}
				var content = xhr.responseText;
				var debugData = {
					cmd: url,
					fct: fctName,
					xhr: xhr.responseText,
					errormsg: errorMsg,
					status: status,
					content: content
				};

				var n = getMillis();

				if (errorMsg !== null) {
					if ($(".connectionstatus").is(":visible")) {
						alert("Kommunikation zum Server ist unterbrochen!");
					} else {
						showAlert(debugData);
						doAjax("POST", "php/debug.php?n=" + n, debugData, null, true);
					}
				}
			}
		}
	});
}

function doAjaxAsync(getOrPost, url, data, functionToCallIfSuccess) {
	$.ajax({
		type: getOrPost,
		url: url,
		dataType: "text",
		data: data,
		async: ASYNC,
		cache: false,
		success: function (serverAnswer) {
			try {
				var jsonContent = JSON.parse(serverAnswer);
				if (functionToCallIfSuccess !== null) {
					functionToCallIfSuccess(jsonContent);
				}
			} catch (err) {
				var debugData = {
					cmd: url,
					fct: functionToCallIfSuccess,
					xhr: "",
					errormsg: "Error",
					status: "ParseError",
					content: serverAnswer
				};
				showAlert(debugData);
			}

		},
		error: function (xhr, status, error) {
			if (xhr.status === 0 || xhr.readyState !== 4) {
				if (!url.includes("isPrinterServerActive") && !url.includes("getDailycode")) {
					alert("Der Webserver ist nicht erreichbar: " + url);
				}
				return;
			}
			if (url !== "php/debug.php") {
				consoleAjaxErrorOutput(url, xhr, status, "");
				var fctName = "?";
				if (functionToCallIfSuccess !== null) {
					fctName = functionToCallIfSuccess.name;
				}
				var debugData = {
					cmd: url,
					fct: fctName,
					xhr: xhr.responseText,
					errormsg: "Error",
					status: status,
					content: xhr.responseText
				};

				var n = getMillis();

				if (error !== null) {
					if ($(".connectionstatus").is(":visible")) {
						alert("Kommunikation zum Server ist unterbrochen!");
					} else {
						showAlert(debugData);
						doAjax("POST", "php/debug.php?n=" + n, debugData, null, true);
					}
				}
			}
		}
	});
}

function doAjaxTransmitData(getOrPost, url, data, functionToCallIfSuccess, errorMsg, dataToTransmit) {
	$.ajax({
		type: getOrPost,
		url: url,
		dataType: "text",
		data: data,
		async: SYNC,
		cache: false,
		success: function (serverAnswer) {
			try {
				var jsonContent = JSON.parse(serverAnswer);
				if (functionToCallIfSuccess !== null) {
					functionToCallIfSuccess(jsonContent, dataToTransmit);
				}
			} catch (err) {
				if (errorMsg !== null) {
					var debugData = {
						cmd: url,
						fct: functionToCallIfSuccess,
						xhr: "",
						errormsg: "Error",
						status: "ParseError",
						content: serverAnswer
					};
					showAlert(debugData);
				}
			}
		},
		error: function (xhr, status, error) {
			if (xhr.status === 0 || xhr.readyState !== 4) {
				alert("Der Webserver ist nicht erreichbar:" + url);
				return;
			}
			consoleAjaxErrorOutput(url, xhr, status, errorMsg);
			if (errorMsg !== null) {
				var debugData = {
					cmd: url,
					fct: functionToCallIfSuccess,
					xhr: xhr.responseText,
					errormsg: "Error",
					status: status,
					content: xhr.responseText
				};
				showAlert(debugData);
			}
		}
	});
}

function doAjaxSuppressError(getOrPost,url,data,functionToCallIfSuccess,errorMsg) {
	$.ajax({ type: getOrPost,   
        url: url, 
        dataType: "json",
        data: data,
        async: false,
        cache: false,
        success : function(jsonContent)
        {
        	if (functionToCallIfSuccess !== null) {
        		functionToCallIfSuccess(jsonContent);
        	}
        },
		error: function( text ) {
			functionToCallIfSuccess("ERROR");
		}
	});
}

function doAjaxNonJsonNonCall(getOrPost,url,data) {
	$.ajax({ type: getOrPost, 
		data : data,
        url: url, 
        async: false,
		error: function( text ) {
			alert( "Kommunikationsproblem zum Server" );
		}
	});
}


function isString(myVar) {
	if (typeof myVar === 'string' || myVar instanceof String) {
		return true;
	} else {
		return false;
	}
}
function toHtml(text) {
	if ((text === undefined) || (text == null)) {
		return "";
	}
	if (typeof text === 'string') {
		return (text.replace(/"/g, '&quot;').replace(/</g, "&lt;").replace(/>/g, "&gt;"));
	} else {
		return text;
	}
}

function createExtraParagraph(extras,fontsize) {
	if ((extras == null) || (extras == "")) {
		return "";
	}
	var extratxt = "";
	for (var j=0;j<extras.length;j++) {
		if (fontsize == 0) {
			extratxt += "<p>+ " + toHtml(extras[j]) + "</p>";
		} else {
			extratxt += "<p style='font-size:" + fontsize + "px;'>+ " + toHtml(extras[j]) + "</p>";
		}
		
	}
	return extratxt;
}

function checkForLogIn() {
	doAjax("GET","php/contenthandler.php?module=admin&command=isUserAlreadyLoggedIn",null,handleTestForLoggedIn,null);
}
function handleTestForLoggedIn(answer) {
	if (answer !== "YES") {
		setTimeout(function(){document.location.href = "index.html"},250);
	}
}
function isInt(value) {
    if(Math.floor(value) == value && $.isNumeric(value)) {
        return true;
    } else {
        return false;
    }
}

function isFloat(n){
    return Number(n) === n && n % 1 !== 0;
}

function roundtodigits(value, digits) {
        value = parseFloat(value);
        if (!value) return 0;
        var factor = Math.pow(10,digits);
        return Math.round(value * factor) / factor;
} 

function getMillis() {
	var d = new Date();
	var n = d.getTime();
	return n;
}

function intervalCheckConnection(seconds) {
	checkConnection();
	var fetchTimer = setInterval(function() {
	    checkConnection();
	}, seconds * 1000);
}

function checkConnection() {
	var img = new Image();
	img.onerror = function () {
		$(".connectionstatus").show();
	};
	img.onload = function () {
		$(".connectionstatus").hide();
	};
	img.src = "img/gray.png?t=" + (+new Date);
}

function createLabelWithTextField(labelid,displayedName,defaultText) {
	var text = '<div class="ui-field-contain">';
	text += '<label for="' + labelid + '">' + displayedName + '</label>';
	text += '<input type="text" id="' + labelid + '" value="" data-mini="true" placeholder="' + defaultText + '" style="background-color:white;color:black;" />';
	text += '</div>';
	return text;
}
function createLabelWithTextFieldWithContent(labelid,displayedName,defaultText,content) {
	var text = '<div class="ui-field-contain">';
	text += '<label for="' + labelid + '">' + displayedName + '</label>';
	text += '<input type="text" id="' + labelid + '" value="' + toHtml(content) + '" data-mini="true" placeholder="' + defaultText + '" style="background-color:white;color:black;" />';
	text += '</div>';
	return text;
}
function createLabelWithTextFieldWithValue(labelid,displayedName,content) {
	var text = '<div class="ui-field-contain">';
	text += '<label for="' + labelid + '">' + displayedName + '</label>';
	text += '<input type="text" id="' + labelid + '" data-mini="true" value="' + content + '" style="background-color:white;color:black;" />';
	text += '</div>';
	return text;
}

function createLabelWithTextArea(labelid,displayedName) {
	var text = '<div class="ui-field-contain">';
	text += '<label for="' + labelid + '">' + displayedName + '</label>';
	text += '<textarea id="' + labelid + '" name="' + labelid + '" cols="40" rows="8" style="background-color:white;color:black;"></textarea>';
	text += '</div>';
	return text;
}
function createLabelWithTextAreaWithValue(labelid,displayedName,content) {
	var text = '<div class="ui-field-contain">';
	text += '<label for="' + labelid + '">' + displayedName + '</label>';
	text += '<textarea id="' + labelid + '" name="' + labelid + '" cols="40" rows="8" style="background-color:white;color:black;">';
	text += content;
	text += '</textarea>';
	text += '</div>';
	return text;
}
function createLabelWithOption(prefix,id,aLabel,displayedName,allValues,theValue) {
	var labelid = id;
	if ((prefix !== "") || (aLabel !== "")) {
		labelid = prefix + aLabel + "_" + id;
	}
	var text = '<div class="ui-field-contain">';
	text += '<label for="' + labelid + '">' + displayedName + '</label>';
	
	text += '<select name="' + labelid + '" id="' + labelid + '" data-theme="f">';
	for (var i=0;i<allValues.length;i++) {
		var aValue = allValues[i];
		if (aValue.id == theValue) {
			text += '<option value="' + aValue.id + '" selected>' + aValue.text + '</option>';
		} else {
			text += '<option value="' + aValue.id + '" >' + aValue.text + '</option>';
		}
	}

	text += '</select></div>';

	return text;
}

function pad(num, size) {
    var s = "000000000" + num;
    return s.substr(s.length-size);
}

function getUrlGetParameter(urlsuffix,paramMarker) {
	var tid = '';
	var urlParts = urlsuffix.split(/&|\?/);
	for (var i=0;i<urlParts.length;i++) {
		var aPart = urlParts[i];
		if (aPart.indexOf(paramMarker) == 0) {
			var parts = aPart.split("=");
			tid = parts[1];
		}
	}
	return tid;
}

var g_units_arr = [
	{ text: "Stück", value: 0, id: "piece", longtext: "Stück"},
	{ text: "Eingabe", value: 1, id: "input", longtext: "Preiseingabe"},
	{ text: "kg", value: 2, id: "kg", longtext: "Gewicht (kg)"},
	{ text: "gr", value: 3, id: "gr", longtext: "Gewicht (gr)"},
	{ text: "mg", value: 4, id: "mg", longtext: "Gewicht (mg)"},
	{ text: "l", value: 5, id: "l", longtext: "Volumen (l)"},
	{ text: "ml", value: 6, id: "ml", longtext: "Volumen (ml)"},
	{ text: "m", value: 7, id: "m", longtext: "Länge (m)"},
	{ text: "EinzweckgutscheinKauf", value: 8, id: "EG", longtext: "EinzweckgutscheinKauf"},
	{ text: "EinzweckgutscheinEinl", value: 9, id: "MG", longtext: "EinzweckgutscheinEinl"},
        { text: "h", value: 10, id: "h", longtext: "Dauer (Stunden)"}
];

var taxesDefs = [
	{ key: 1, value: null, name: "Allgemeiner Steuersatz (§ 12 Abs. 1 UStG)"},
	{ key: 2, value: null, name: "Ermäßigter Steuersatz (§ 12 Abs. 2 UStG)"},
//	{ key: 3, value: 10.70, name: "Durchschnittsatz (§ 24 Abs. 1 Nr. 3 UStG) übrige Fälle"},
//	{ key: 4, value: 5.50, name: "Durchschnittsatz (§ 24 Abs. 1 Nr. 1 UStG)"},
	{ key: 5, value: 0.0, name: "Nicht Steuerbar"},
//	{ key: 6, value: 0.0, name: "Umsatzsteuerfrei"},
//	{ key: 7, value: 0.0, name: "UmsatzsteuerNichtErmittelbar"},
	{ key: 11, value: 19.0, name: "Historischer allgemeiner Steuersatz (§ 12 Abs. 1 UStG)"},
	{ key: 12, value: 7.0, name: "Historischer ermäßigter Steuersatz (§ 12 Abs. 2 UStG)"},
	{ key: 21, value: 16.0, name: "Historischer allgemeiner Steuersatz (§ 12 Abs. 1 UStG)"},
	{ key: 22, value: 5.0, name: "Historischer ermäßigter Steuersatz (§ 12 Abs. 2 UStG)"}	
];

function isSelected(currentIndex,searchIndex) {
	if (currentIndex == searchIndex) {
		return " selected";
	} else {
		return "";
	}
}
function createPreferMobileThemePart(prefervalue,label,labeltxt,l) {
	var PREF_THEME_COLORFUL = ["Active Colors","Active Colors","Active Colors"];
	var PREF_THEME_PALE = ["Power Pale","Power Pale","Power Pale"];
	var PREF_THEME_DARK_SOUL = ["Dark Soul","Dark Soul","Dark Soul"];
	var PREF_THEME_STYLISH = ["Stylish","Stylish","Stylish"];
	var PREF_THEME_BLUETHUNDER = ["Blue Thunder","Blue Thunder","Blue Thunder"];
	var PREF_THEME_COOL = ["Cool","Cool","Cool"];
	var PREF_THEME_PINKLADY = ["Pink Lady","Pink Lady","Pink Lady"];
	var PREF_THEME_GREENFIELD = ["Green Field","Green Field","Green Field"];

	var html = '<div class="ui-field-contain">';
    html += '<label for="' + label + '"><span id="' + label + 'txt">' + labeltxt + '</span>:</label>';
    html += '<select name="' + label + '" id="' + label + '" data-theme="e">';

    html += '<option value="0"' + isSelected(0,prefervalue) + '>' + PREF_THEME_COLORFUL[l] + '</option>';
    html += '<option value="1"' + isSelected(1,prefervalue) + '>' + PREF_THEME_PALE[l] + '</option>';
    html += '<option value="2"' + isSelected(2,prefervalue) + '>' + PREF_THEME_DARK_SOUL[l] + '</option>';
    html += '<option value="3"' + isSelected(3,prefervalue) + '>' + PREF_THEME_STYLISH[l] + '</option>';
    html += '<option value="4"' + isSelected(4,prefervalue) + '>' + PREF_THEME_BLUETHUNDER[l] + '</option>';
    html += '<option value="5"' + isSelected(5,prefervalue) + '>' + PREF_THEME_COOL[l] + '</option>';
    html += '<option value="6"' + isSelected(6,prefervalue) + '>' + PREF_THEME_PINKLADY[l] + '</option>';
    html += '<option value="7"' + isSelected(7,prefervalue) + '>' + PREF_THEME_GREENFIELD[l] + '</option>';
    html += '<option value="8"' + isSelected(8,prefervalue) + '>Bright Energy</option>';
 
    html += '</select></div>';
    return html;
}

function createExtrasText(entry) {
	let extrasTxt = "";
	let extrasArr = [];

	if ((entry.extras !== null) && (entry.extras instanceof Array) && (entry.extras.length > 0)) {
		const firstEntry = entry.extras[0];
		if (!isString(firstEntry)) {
			// an object extra with amount and name
			entry.extras.forEach(function(anExtra) {
				extrasArr[extrasArr.length] = anExtra.amount + "x " + toHtml(anExtra.name);
			});
			extrasTxt = " mit " + extrasArr.join(",");
		} else {
			// pure string with amound and name
			extrasTxt = " mit " + entry.extras.join(",");
		}
		return extrasTxt;
	} else {
		return "";
	}
}


function updateLiveOrders(currentorders, tableid, doAsync) {
	// Safari: SYNC was not sufficient, but in combi with a callback it worked for view change
	let fct = null;
	if ((doAsync !== undefined) && (doAsync == SYNC)) {
		fct = completeUpdateLiveOrdersAnswer;
	} else {
		doAsync = ASYNC;
	}
	var entries = [];
	if (currentorders !== null) {
		currentorders.forEach(function (entry) {
			var price = entry.price;
			if (entry.changedPrice.toLowerCase() === "no") {
				price = entry.price;
			} else {
				price = entry.changedPrice;
			}
			const extrasTxt = createExtrasText(entry);

			var anEntry = {
				prodname: entry.name + extrasTxt,
				price: price,
				tableid: tableid
			};
			entries[entries.length] = anEntry;
		});
	}
	var data = { liveorders: entries };
	doAjax("POST", "php/contenthandler.php?module=queue&command=updateliveorders", data, fct, null, doAsync);
}

function completeUpdateLiveOrdersAnswer(answer) {
	if (answer.status !== "OK") {
		alert("Fehler beim Aktualisieren der Live-Bestellungen: " + answer.msg);
	}
}

function updateLivePayOrders(prodsOnReceiptList, tableid, doAsync) {
	let fct = null;
	if ((doAsync !== undefined)  && (doAsync == SYNC)) {
		fct = completeUpdateLiveOrdersAnswer;
	} else {
		doAsync = ASYNC;
	}
	var entries = [];
	if ((prodsOnReceiptList !== undefined) && (prodsOnReceiptList !== null)) {
		prodsOnReceiptList.forEach(function (entry) {
			const extrasTxt = createExtrasText(entry);
			var anEntry = {
				prodname: entry.longname + extrasTxt,
				price: entry.price,
				tableid: tableid
			};
			entries[entries.length] = anEntry;
		});
	}
	var data = { liveorders: entries };
	doAjax("POST", "php/contenthandler.php?module=queue&command=updateliveorders", data, fct, null, doAsync);
}

function clearLiveOrders() {
	updateLiveOrders(null, null, SYNC);
	updateLivePayOrders(null, null, SYNC);
}

function postForm(path, params) {
    method = 'post';

    var form = document.createElement('form');
    form.setAttribute('method', method);
    form.setAttribute('action', path);

    for (var key in params) {
        if (params.hasOwnProperty(key)) {
            var hiddenField = document.createElement('input');
            hiddenField.setAttribute('type', 'hidden');
            hiddenField.setAttribute('name', key);
            hiddenField.setAttribute('value', params[key]);

            form.appendChild(hiddenField);
        }
    }

    document.body.appendChild(form);
    form.submit();
}

function createDataTable(cols,datarows) {
        var txt = "<table class='reporttable'>";
        txt += "<tr>";
        cols.forEach(function (colEntry) {
           txt += "<th>" + toHtml(colEntry);     
        });
        txt += "</tr>";
        datarows.forEach(function(dataRow) {
                txt += "<tr>";
                cols.forEach(function (colEntry) {
                        txt += "<td>" + toHtml(dataRow[colEntry]);
                });
                txt += "</tr>";
        });
        txt += "</table>";
        return txt;
}

function isUnitOfAmountTypeNotPieceNotVoucher(unit) {
        if (((unit > 1) && (unit < 8)) || (unit == 10)) {
                return true;
        } else {
                return false;
        }
}

function isUnitOfAmountTypeNotVoucher(unit) {
        if ((unit < 8) || (unit == 10)) {
                return true;
        } else {
                return false;
        }
}

function arrayRemoveValue(arr, value) { 
        return arr.filter(function(ele){ 
            return ele != value; 
        });
}
    