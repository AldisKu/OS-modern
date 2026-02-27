var KB_READY = ["Zubereitet","Ready","Preparado"];
var KB_MAX_WAIT = ["Max. Wartezeit: ","Max. waited: ","Tiempo de espera: "];
var KB_TO_MAKE = ["Herzustellen","To prepare","Para preparar"];
var KB_TO_FINISHED = ["Fertig","Ready to serve","Completo"];
var KB_TABLE = ["Tisch","Table","Mesa"];

var isInitialized = false;

var displayWorkToDo = false;
var displayFinished = false;
var displayHeaderFooter = false;

var cookedEntries = null;
var entriesToCook = null;
var user = "";
var beepordered = 0;
var oneclickcooked = 0;
var kitchenextrasize = 0;
var kitchenoptionsize = 0;

var cat1viewname = 0;
var cat2viewname = 0;

var showkitsel = 0;
var showbarsel = 0;

var performanceMeasureTimerEntriesToCook = 0;
var perfStartTimeGetEntriesToCook = 0;
var PERFTIMER_ENTRIES_TO_COOK= 10;
var performanceMeasureTimerCookedEntries = 0;
var perfStartTimeGetCookedEntries = 0;
var PERFTIMER_ENTRIES_COOKED_ENTRIES = 10;

var currentworkPrinter = 0;

let kitchenshoworderuser = 0;

function beep() {
    var snd = new Audio("data:audio/wav;base64,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qJrx4KOQjahZxWbcZgztj2c49nKmkId44S71j0c8eV9yDK6uPRzx5X18eDvjvQ6yKo9ZSS6l//8elePK/Lf//IInrOF/FvDoADYAGBMGb7FtErm5MXMlmPAJQVgWta7Zx2go+8xJ0UiCb8LHHdftWyLJE0QIAIsI+UbXu67dZMjmgDGCGl1H+vpF4NSDckSIkk7Vd+sxEhBQMRU8j/12UIRhzSaUdQ+rQU5kGeFxm+hb1oh6pWWmv3uvmReDl0UnvtapVaIzo1jZbf/pD6ElLqSX+rUmOQNpJFa/r+sa4e/pBlAABoAAAAA3CUgShLdGIxsY7AUABPRrgCABdDuQ5GC7DqPQCgbbJUAoRSUj+NIEig0YfyWUho1VBBBA//uQZB4ABZx5zfMakeAAAAmwAAAAF5F3P0w9GtAAACfAAAAAwLhMDmAYWMgVEG1U0FIGCBgXBXAtfMH10000EEEEEECUBYln03TTTdNBDZopopYvrTTdNa325mImNg3TTPV9q3pmY0xoO6bv3r00y+IDGid/9aaaZTGMuj9mpu9Mpio1dXrr5HERTZSmqU36A3CumzN/9Robv/Xx4v9ijkSRSNLQhAWumap82WRSBUqXStV/YcS+XVLnSS+WLDroqArFkMEsAS+eWmrUzrO0oEmE40RlMZ5+ODIkAyKAGUwZ3mVKmcamcJnMW26MRPgUw6j+LkhyHGVGYjSUUKNpuJUQoOIAyDvEyG8S5yfK6dhZc0Tx1KI/gviKL6qvvFs1+bWtaz58uUNnryq6kt5RzOCkPWlVqVX2a/EEBUdU1KrXLf40GoiiFXK///qpoiDXrOgqDR38JB0bw7SoL+ZB9o1RCkQjQ2CBYZKd/+VJxZRRZlqSkKiws0WFxUyCwsKiMy7hUVFhIaCrNQsKkTIsLivwKKigsj8XYlwt/WKi2N4d//uQRCSAAjURNIHpMZBGYiaQPSYyAAABLAAAAAAAACWAAAAApUF/Mg+0aohSIRobBAsMlO//Kk4soosy1JSFRYWaLC4qZBYWFRGZdwqKiwkNBVmoWFSJkWFxX4FFRQWR+LsS4W/rFRb/////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////VEFHAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAU291bmRib3kuZGUAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAMjAwNGh0dHA6Ly93d3cuc291bmRib3kuZGUAAAAAAAAAACU=");  
    snd.play();
}

function declareProductBeCookingOrCooked(queueid) {
	var data = {
		queueid: queueid,
		workprinter: currentworkPrinter
	};
	doAjax("POST","php/contenthandler.php?module=queue&command=declareProductBeCookingOrCooked",data,resultOfProductDeclaration,"could not declare product",ASYNC);
}

function resultOfProductDeclaration(jsonText) {
	if (jsonText.status !== "OK") {
		alert("Fehler " + jsonText.code + ": " + jsonText.msg);
	} else {
		const performedAction = jsonText.msg.performedaction;
		if (performedAction === "c") {
			removeOrRedeclareAnIdFromPrepared(jsonText.msg.queueid,false);
		} else if (performedAction === "r") {
			removeOrRedeclareAnIdFromPrepared(jsonText.msg.queueid,true);
		} else if (performedAction === "n") {
			getAndDisplayAllEntries();
		} else {
			console.log("Unknown action performed for kitchen call to background: " + performedAction);
		}
		fillTableWithEntriesToCookCore();
		bindEntriesToCook();
	}
}

function declareProductNOTBeCooked(queueid) {
	var data = { queueid: queueid };
	doAjax("POST","php/contenthandler.php?module=queue&command=declareProductNotBeCooked",data,resultOfProductDeclaration,"could not unmake product",ASYNC);
}

function checkEndPerformanceMeaurementGetEntriesToCook() {
        if (performanceMeasureTimerEntriesToCook === 0) {
                performanceMeasureTimerEntriesToCook = PERFTIMER_ENTRIES_TO_COOK;
                if (perfStartTimeGetEntriesToCook !== 0) {
                        
                        var endTime = new Date().getTime();
                        var elapsedTime = endTime - perfStartTimeGetEntriesToCook;
                        var data = {
                                task: "getentriestocook",
                                elapsedtime: elapsedTime
                        };
                        perfStartTimeGetEntriesToCook = 0;
                        doAjax("GET","php/contenthandler.php?module=admin&command=addperformancedata",data,null,null,true);
                }
        } else {
                performanceMeasureTimerEntriesToCook--;
        }
}
function fillTableWithEntriesToCook(answer) {
        checkEndPerformanceMeaurementGetEntriesToCook();
	if (answer.status === "OK") {
		entriesToCook = answer.msg.tocook;
		fillTableWithEntriesToCookCore();
		bindEntriesToCook();
		
		var newproductsToPrepare = answer.msg.newproducts;
		if ((newproductsToPrepare == 1) && (beepordered == 1)) {
			beep();
		}
	}
}

function removeOrRedeclareAnIdFromPrepared(theId, doRemove) {
	$.each(entriesToCook, function (j, table) {
		var aList = table.queueitems;
		var tablename = table.table;


		var i = 0;
		var found = false;
		for (i = 0; i < aList.length; i++) {
			var anEntry = aList[i];
			if (anEntry.id == theId) {
				found = true;
				var longname = anEntry.longname;
				var isguestorder = anEntry.isguestorder;
				var option = anEntry.option;
				var prodid = anEntry.prodid;
				var username = anEntry.username;
				var extras = anEntry.extras;
				break;
			}
		}
		if (found) {
			if (doRemove) {
				aList.splice(i, 1);
				var newEntry = {
					id: theId,
					tablename: tablename,
					longname: longname,
					isguestorder: isguestorder,
					option: option,
					prodid: prodid,
					username: username,
					extras: extras
				};
				cookedEntries.splice(0, 0, newEntry);
				fillTableWithCookedEntriesCore();
				bindCookedEntries();
			} else {
				var theEntry = aList[i];
				theEntry.cooking = user;
			}
		}
	});
}

function bindEntriesToCook() {
	$(".toprep").off("click").on("click", function (e) {
		e.stopImmediatePropagation();
		e.preventDefault();
		var ids = this.closest(".preparedlistitem").id;
		var doRemove = false;
		if (oneclickcooked == 1) {
			doRemove = true;
		}
		if ($(this.closest(".preparedlistitem")).hasClass("cooking")) {
			doRemove = true;
		}
		var idarr = ids.split("_");
		var i=0;
		for (i=0;i<idarr.length;i++) {
			declareProductBeCookingOrCooked(idarr[i]);
		}
	});
	$(".preparedlistitem").off("click").on("click", function (e) {
		var idlist = this.id;
		var idarr = idlist.split("_");
		var firstid = idarr[0]; // this is the id to handle!
		var doRemove = false;
		if (oneclickcooked == 1) {
			doRemove = true;
		}
		if ($(this).hasClass("cooking")) {
			doRemove = true;
		}
		if (doRemove) {
			declareProductBeCookingOrCooked(firstid,"r");
		} else {
			declareProductBeCookingOrCooked(firstid,"c");
		}

		removeOrRedeclareAnIdFromPrepared(firstid,doRemove);
		
		fillTableWithEntriesToCookCore();
		bindEntriesToCook();
	});
}

function fillTableWithEntriesToCookCore() {
	var aList = '';
	$.each(entriesToCook, function (i, table) {   
		aList += listOfTable(table);
	});

	$("#listWithEntriesToCook").html(aList);
	$("#listWithEntriesToCook").trigger("create");
}

function listOfTable(table) {
        var tableid = table.tableid;
        var tablename = table.table;
        var maxWaitTime = table.maxwaittime;

        var itemsForTable = table.queueitems;

        var qset = [];

        $.each(itemsForTable, function (i, entry) {
                var option = '';
                if (entry.option !== '') {
                        var theComment = toHtml(entry.option);
                        if (kitchenoptionsize == 0) {
                                option = '<p>' + theComment + '</p>';
                        } else {
                                option = '<p style="font-size:' + kitchenoptionsize + 'px;">' + theComment + '</p>';
                        }
                }
                var icon = "check";
                var status = "not_cooking";
                var label = toHtml(entry.longname);
                if (entry.isguestorder == 1) {
                        label = "<span class='guestorder'>" + label + "</span>";
                }
                if (entry.cooking !== '') {
                        theme = 'd';
                        label += "<small><i> (&#9749; " + entry.cooking + ")</i></small>";
                }

                var extratxt = createExtraParagraph(entry.extras, kitchenextrasize);

                var entryToAdd = {
                        name: label + extratxt,
                        isguestorder: entry.isguestorder,
                        cooking: entry.cooking,
                        queueid: entry.id,
                        option: option,
                        prodid: entry.prodid,
                        username: entry.username,
                        waiticon: entry.waiticon
                };
                var setLength = qset.length;
                qset[setLength] = entryToAdd;
        });

        var grouped = groupItemToMake(qset);

        var aList = '<ul data-role="listview" id="' + tableid + '" data-divider-theme="a" data-inset="true">';
        aList += '<li data-role="list-divider" data-theme="c" data-role="heading">' + tablename + ' (' + KB_MAX_WAIT[lang] + maxWaitTime + ' min)</li>';

        var i = 0;
        var length = grouped.counts.length;

        for (i = 0; i < length; i++) {
                var img = '<img src="img/waittimes/' + grouped.waiticons[i] + '" />';
                var id_joined = grouped.queueids[i].join("_");
                var theme = 'c';
                var icon = "check";
                var status = "not_cooking";
                var count = grouped.counts[i];

                var label = grouped.names[i];
                
                if (kitchenshoworderuser == 1) {
                        label += "<br><span class='orderuser'>&#127939; " + grouped.usernames[i] + "</span>";
                }
                
                var imgpart = "";
                if (count > 1) {
                        label = "<span style='font-size: 23px;'>" + count + "x</span> " + label;
                        imgpart = "<div class='counting toprep'><img src='img/multi.png' /></div>";
                }
                var cooking = grouped.cookings[i];
                var option = grouped.options[i];
                if (cooking !== '') {
                        theme = 'd';
                        icon = "arrow-d";
                        status = "cooking";
                }
                aList += '<li data-theme="' + theme + '" data-icon="' + icon + '" class="preparedlistitem ' + status + '" + id="' + id_joined + '">';
                aList += '<a href="#">' + img + label + option + imgpart + '</a></li>';
        }

        aList += '</ul>';

        return aList;
}

function checkEndPerformanceMeaurementGetCookedEntries() {
        if (performanceMeasureTimerCookedEntries === 0) {
                performanceMeasureTimerCookedEntries = PERFTIMER_ENTRIES_COOKED_ENTRIES;
                if (perfStartTimeGetCookedEntries !== 0) {
                        var endTime = new Date().getTime();
                        var elapsedTime = endTime - perfStartTimeGetCookedEntries;
                        var data = {
                                task: "getcookedentries",
                                elapsedtime: elapsedTime
                        };
                        perfStartTimeGetCookedEntries = 0;
                        doAjax("GET","php/contenthandler.php?module=admin&command=addperformancedata",data,null,null,true);
                }
        } else {
                performanceMeasureTimerCookedEntries--;
        }
}

function fillTableWithCookedEntries(entries) {
        checkEndPerformanceMeaurementGetCookedEntries();
	cookedEntries = entries;
	fillTableWithCookedEntriesCore();
	bindCookedEntries();
}

function bindCookedEntries() {
	$(".deliveredlistitem").off("click").on("click", function (e) {
		
		var idlist = this.id;
		var idarr = idlist.split("_");
		var firstid = idarr[0]; // this is the id to handle!
		declareProductNOTBeCooked(firstid);
	});
}

function fillTableWithCookedEntriesCore() {
	if ((cookedEntries !== null) && (cookedEntries.length > 0)) {
		
		var qset = [];
		
		$.each(cookedEntries, function (i, entry) {
			var option = '';
			if (entry.option !== '') {
				var theComment = toHtml(entry.option);
				option = '<p>' + theComment + '</p>';
			}
			
			var label = toHtml(entry.longname);
			
			var extratxt = createExtraParagraph(entry.extras,0);

			var table = entry.tablename;
			var entryToAdd = {
					name: label + extratxt,
					queueid: entry.id,
					option: option,
                                        prodid: entry.prodid,
                                        username: entry.username,
					extras: extratxt,
					table: table
			};
			var setLength = qset.length;
			qset[setLength] = entryToAdd;
		});
		
		var grouped = groupMadeItems(qset);

		var theList = '<ul data-role="listview" id="deliveredProdsList" data-divider-theme="a" data-inset="true">';
		theList += '<li data-role="list-divider" data-theme="b" data-role="heading" data-icon="check">' + KB_READY[lang] + '</li>';
		
		var length = grouped.counts.length;

		for (i=0;i<Math.min(length,10);i++) {
			var count = grouped.counts[i];
			
			var label = grouped.names[i];
			var imgpart = "";
			if (count > 1) {
				label = "<span style='font-size: 23px;'>" + count + "x</span> " + label;
				//imgpart = "<div class='counting'><img src='img/multi.png' /></div>";
			}
			var option = grouped.options[i];
			var tablename = KB_TABLE[lang] + ": " + grouped.tables[i];
			var infotext = tablename;
			if (option !== '') {
				infotext = option + "<p>" + tablename + "</p>";
			}

			var id_joined = grouped.queueids[i].join("_");
			theList += '<li data-theme="e" data-icon="arrow-u" id="' + id_joined + '" class="deliveredlistitem"><a href="#">' + label;
			theList += '<p>' + infotext + '</p>' + imgpart;
			theList += '</A></LI>';
		}

		theList += '</ul>';
		
		$("#listWithCookedEntries").html(theList);
		$("#listWithCookedEntries").trigger("create");
		
		
	} else {
		$("#listWithCookedEntries").html("");
	}
}

function handleGlobalRefresh(name) {
	parent.frames[name].location.reload();
	getAndDisplayAllEntries();
}

function getAndDisplayAllEntries()
{
	checkForLogIn();
	if (isInitialized) {
		if (displayWorkToDo) {
			getEntriesToCook();	
		}
		if (displayFinished) {
			getCookedEntries();
		}
	}
}

function getGeneralConfigItems() {
	doAjax("GET", "php/contenthandler.php?module=admin&command=getGeneralConfigItems", null, insertGeneralConfigItems, "Fehler Konfigurationsdaten");
	doAjax("GET","php/contenthandler.php?module=admin&command=getJsonMenuItemsAndVersion",null,setUser,"Benutzerdaten nicht übermittelt");
}

function setUser(userAndVersion) {
	user = userAndVersion.user;
}

function insertGeneralConfigItems(configResult) {
	if (configResult.status === "OK") {
		var values = configResult.msg;
		beepordered = values.beepordered;
		oneclickcooked = values.oneclickcooked;
		kitchenextrasize = values.kitchenextrasize;
		kitchenoptionsize = values.kitchenoptionsize;
                cat1viewname = values.cat1viewname;
                cat2viewname = values.cat2viewname;
                showkitsel = values.showkitsel;
                showbarsel = values.showbarsel;
                kitchenshoworderuser = values.kitchenshoworderuser;
                enableWorkPrinterSelectionOnDemand();
		setLanguage(values.userlanguage,cat1viewname,cat2viewname);
		isInitialized = true;
		if (displayHeaderFooter) {
			initializeMainMenu("#modulemenu");
		} else {
			$("#modulepanel").hide();
			$("#menuswitch").hide();
			$("#thefooterr").hide();
		}
		initializeEverything();
		setHeadlines();
		getAndDisplayAllEntries();
	} else {
		setTimeout(function(){document.location.href = "index.html"},250); // not logged in
	}
}

function setWorkMode(urlsuffix) {
	displayWorkToDo = true;
	displayFinished = true;
	displayHeaderFooter = true;
	return;
}

function setWorkPrinterLabel(label) {
        $(".workprinterlabel").html(label);
}

function bindWorkPrinterChanged() {
        $(".workprinter").off("click").on("click", function (e) {
                e.stopImmediatePropagation();
		e.preventDefault();
                
                workprinterclicked = this.id.split("_")[1];
                if (currentworkPrinter == workprinterclicked) {
                        currentworkPrinter = 0;
                } else {
                        currentworkPrinter = workprinterclicked;
                }
                setWorkPrinter(currentworkPrinter);
                
                getEntriesToCook();
                getCookedEntries();
        });
} 

function setWorkPrinter(workprinter) {
        currentworkPrinter = workprinter;
        //document.getElementById("myH1").style.color = "red"; 
        $(".workprinter").css('background','#e9e9e9');
        $(".workprinter").css('color','#333333');
        $("#workprinter_" + workprinter).css('background','#a9a9a9');
}
/**
 * Not possible in setWorkMode, because set Lang would overwrite.
 * So set headlines in method that can be called later
 */
function setHeadlines() {
	if (!displayHeaderFooter) {
		if (displayWorkToDo) {
			$("#moduleheadline").html(KB_TO_MAKE[lang]);
		} else if (displayFinished) {
			$("#moduleheadline").html(KB_TO_FINISHED[lang]);
		}
		$("#headerline").trigger("create");
	}
}

function groupMadeItems(theSet) {
	var counts = [];
	var joinedvals = [];
	var names = [];
	var isguestorders = [];
	var options = [];
        var prodids = [];
        let usernames = [];
	var extras = [];
	var tables = [];
	var queueids = [];
	
	var grouped = {
		counts:counts,
		joinedvals : joinedvals,
		names: names,
		isguestorders: isguestorders,
		options: options,
                prodids: prodids,
                usernames: usernames,
		extras: extras,
		tables: tables,
		queueids : queueids
	};

	var i=0;
	for (i=0;i<theSet.length;i++) {
		var anEntry = theSet[i];
		var name=anEntry.name;
		var isguestorder = anEntry.isguestorder;
		var option=anEntry.option;
                var prodid = anEntry.prodid;
                let username = anEntry.username;
		var extras = anEntry.extras;
		var table = anEntry.table;
		var queueid = anEntry.queueid;
		var joinedNeedle = prodid + "_" + name + "-" + isguestorder + " - " + option + "-" + "-" + table + "_" + extras;
                if (kitchenshoworderuser == 1) {
                        joinedNeedle += "_" + username;
                }
		var index = grouped.joinedvals.indexOf(joinedNeedle);
		if (index >= 0) {
			grouped.counts[index] = grouped.counts[index] + 1;
			var queueidsarr = grouped.queueids[index];
			queueidsarr[queueidsarr.length] = queueid;
			grouped.queueids[index] = queueidsarr; 
		} else {
			var gLength = grouped.counts.length;
			grouped.counts[gLength] = 1;
			grouped.joinedvals[gLength] = joinedNeedle;
			grouped.names[gLength] = name;
			grouped.isguestorders[gLength] = isguestorder;
			grouped.options[gLength] = option;
                        grouped.prodids[gLength] = prodid;
                        grouped.usernames[gLength] = username;
			grouped.extras[gLength] = extras;
			grouped.tables[gLength] = table;
			grouped.queueids[gLength] = [queueid];
		}     
	} 
	return grouped;
}

function groupItemToMake(theSet) {
	var counts = [];
	var joinedvals = [];
	var names = [];
	var isguestorders = [];
	var options = [];
	var cookings = [];
	var waiticons = [];
	var queueids = [];
        var prodids = [];
        let usernames = [];
	
	var grouped = { counts:counts,
		joinedvals : joinedvals,
		names: names,
		isguestorders: isguestorders,
		options: options,
		cookings: cookings,
		waiticons: waiticons,
		queueids : queueids,
                prodids: prodids,
                usernames: usernames
	};

	var i=0;
	for (i=0;i<theSet.length;i++) {
		var anEntry = theSet[i];
		var name=anEntry.name;
		var isguestorder = anEntry.isguestorder;
		var option=anEntry.option;
		var waiticon = anEntry.waiticon;
		var cooking = anEntry.cooking;
		var queueid = anEntry.queueid;
                var prodid = anEntry.prodid;
                let username = anEntry.username;
		var joinedNeedle = prodid + "_" + name + "-" + isguestorder + "-" + option + "-" + waiticon + "-" + cooking;
                if (kitchenshoworderuser == 1) {
                        joinedNeedle += "-" + username;
                }
		var index = grouped.joinedvals.indexOf(joinedNeedle);
		if (index >= 0) {
			grouped.counts[index] = grouped.counts[index] + 1;
			var queueidsarr = grouped.queueids[index];
			queueidsarr[queueidsarr.length] = queueid;
			grouped.queueids[index] = queueidsarr; 
		} else {
			var gLength = grouped.counts.length;
			grouped.counts[gLength] = 1;
			grouped.joinedvals[gLength] = joinedNeedle;
			grouped.names[gLength] = name;
			grouped.isguestorders[gLength] = isguestorder;
			grouped.options[gLength] = option;
			grouped.cookings[gLength] = cooking;
			grouped.waiticons[gLength] = waiticon;
			grouped.queueids[gLength] = [queueid];
                        grouped.prodids[gLength] = [prodid];
                        grouped.usernames[gLength] = [username];
		}     
	} 
	return grouped;
}