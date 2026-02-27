var GEN_APPLY = ["Anwenden","Apply","Aplicar"];
var GEN_CANCEL = ["Abbrechen","Cancel","Cancelar"];
var GEN_DEL = ["Löschen","Remove","Removar"];
var GEN_ACTION = ["Aktion","Action","Accion"];
var GEN_DIRECTION = ["Verschieben","Move","Mover"];

var MAN_ROOM_PRINTER_NO = ["Kategorieeinstellung","Category setting","Configuración/categoria"];
var MAN_ROOM_PRINTER_1 = ["Drucker 1","Printer 1","Imprimadora 1"];
var MAN_ROOM_PRINTER_2 = ["Drucker 2","Printer 2","Imprimadora 2"];
var MAN_ROOM_PRINTER_3 = ["Drucker 3","Printer 3","Imprimadora 3"];
var MAN_ROOM_PRINTER_4 = ["Drucker 4","Printer 4","Imprimadora 4"];
var MAN_ROOM_PRINTER_TXT = ["Arbeitsdrucker","Work printer","Imprimadora de trabajo"];
var MAN_ROOM_ABBR_TXT = ["Kürzel","Abbr.","Abbr."];
var MAN_CREATENEWROOM = ["Neuer Raum","New Room","Nueva habitación"];
var MAN_SAMPLEROOMNAME = ["Raum","Room","Habitación"];
var MAN_WARNING_ROOMASSIGNENT = ["Sind bereits Artikel auf Tischen gebucht, so kann eine Änderungen am Raumplan alle Artikelzuweisungen löschen! Fortahren?","If articles are already booked on tables, a change to the room plan can delete all article assignments! Continue?","Si los artículos ya están reservados en las mesas, un cambio en el plan de la sala puede eliminar todas las asignaciones de artículos. ¿Continuar?"];

function Groundplan() {
	this.rooms = [];
	this.togoworkprinter = 0;


	this.insertRoomFieldDataFromServer = function(roomfield_json) {
		var roomfield = roomfield_json.roomfield;
		this.togoworkprinter = roomfield_json.togoworkprinter;
		this.rooms = [];
		for (var room_index = 0; room_index < roomfield.length; room_index++) {
			var aRoom = roomfield[room_index];
			this.rooms[this.rooms.length] = new Room(aRoom);	
		};
		this.repaintGround();
		showTableMap();
	};
	
	this.sortRoomsBySorting = function() {
		this.rooms = this.rooms.sort(function(a,b) {
			return a.sorting - b.sorting;
		});
	};
	this.repaintGround = function() {
		this.sortRoomsBySorting();
		this.render();
		
		var roomMap = new Roommap("#tablemaps");
	};
	
	this.responseFromServer = function(answer) {
		if (answer.status != "OK") {
			alert("Es ist ein Fehler aufgetreten: " + answer.msg);
		} else {
			alert("Aktion durchgeführt");

			this.insertRoomFieldDataFromServer(answer);
			
			$('html, body').animate({
				scrollTop: $("#dbactionroomconfig").offset().top
			}, 1000);
		}
	};
	
	this.init = function() {
		var instance = this;
		doAjax("GET","php/contenthandler.php?module=roomtables&command=getRoomfieldAlsoInactive",null,instance.insertRoomFieldDataFromServer.bind(instance),"Raumplan",true);
	};
	
	this.renderCancelApplyBtn = function() {
		var txt = "";
		txt += '<button type="submit" data-theme="f" id="createnewroombtn"><span id=createnewroomtxt>' + MAN_CREATENEWROOM[lang] + '</span></button>';
		txt += '<div class="ui-grid-a" class="noprint">';
		txt += '<div class="ui-block-a"><button type="submit" data-theme="d" id="cancelgroundplanbtn"><span id=cancelgroundplanbtntxt>' + GEN_CANCEL[lang] + '</span></button></div>';
		txt += '<div class="ui-block-b grid_right"><button type="submit" data-theme="f" id="applygroundplanbtn"><span id=applygroundplanbtntxt>' + GEN_APPLY[lang] + '</span></button></div>';
		txt += '</div>';
		return txt;
	};
	
	this.renderCreateTableCodes = function() {
		var txt = '<div data-role="collapsible" data-content-theme="c" data-collapsed="true" data-theme="e">';
		txt += "<h3>" + MAN_GUESTSECTION[lang] + "</h3>";
		txt += '<p><button type="submit" data-theme="f" id="createtablecodesbtn"><span id=createtablecodesbtntxt>' + MAN_CREATETABLECODES[lang] + '</span></button>';
		txt += '<button type="submit" data-theme="f" id="createtableqrcodesbtn"><span id=createtableqrcodesbtntxt>' + MAN_CREATETABLEQR[lang] + '</span></button>';
		txt += "</div>";
		return txt;
	};
	
	this.bindRoomChanges = function() {
		var instance = this;
		for (var i=0;i<this.rooms.length;i++) {
			var aRoom = this.rooms[i];
			$(".roomvalue_" + aRoom.roomid + "_printer").off("change").on("change", function (e) {
				e.stopImmediatePropagation();
				e.preventDefault();
				var roomid = this.id.split("_")[1];
				instance.applyValues.call(instance,roomid,true);
			});
			$(".roomvalue_" + aRoom.roomid + "_text").off("keyup").on("keyup", function (e) {
				e.stopImmediatePropagation();
				e.preventDefault();
				hideTableMap();
				var roomid = this.id.split("_")[1];
				instance.applyValues.call(instance,roomid,false);
			});
			
			$(".roomvalue_" + aRoom.roomid + "_delete").off("click").on("click", function (e) {
				e.stopImmediatePropagation();
				e.preventDefault();
				hideTableMap();
				var roomid = this.id.split("_")[1];
				instance.delete.call(instance,roomid);
			});
			
			$(".roomvalue_" + aRoom.roomid + "_up").off("click").on("click", function (e) {
				e.stopImmediatePropagation();
				e.preventDefault();
				var roomid = this.id.split("_")[1];
				instance.up.call(instance,roomid);
			});
		};
	};
	
	this.binding = function() {
		var instance = this;
		for (var i=0;i<this.rooms.length;i++) {
			var aRoom = this.rooms[i];
			aRoom.binding();
		};
		
		$("#cancelgroundplanbtn").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
			instance.init.call(instance);
		});
		
		$("#applygroundplanbtn").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
			
                        var r = confirm(MAN_WARNING_ROOMASSIGNENT[lang]);
                        if (!r) {
                                return;
                        }

			var roomdata = [];
			instance.rooms.forEach(function(aRoom,i) {
				roomdata[roomdata.length] = aRoom.exportObject();
			});
			
			// togoreceipt_printer
			var selectedtogoworkprinter = $("#togoreceipt_printer").val();
			
			
			var d = JSON.stringify(roomdata); 
			var data = { rooms:d, togoworkprinter:selectedtogoworkprinter};
			doAjax("POST","php/contenthandler.php?module=roomtables&command=setRoomInfo",data,instance.responseFromServer.bind(instance),"Raumplan nicht änderbar",true);
		});
		
		$("#createnewroombtn").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
			hideTableMap();
			instance.createNewRoom.call(instance);
		});
                
                $(".cloneroombtn").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
                        let roomid = this.id.split("_")[1];
                        instance.cloneRoom.call(instance,roomid);
		});
		
		$("#createtablecodesbtn").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
			hideTableMap();
			instance.createTableCodes.call(instance);
		});
		
		$("#createtableqrcodesbtn").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
			window.open("php/contenthandler.php?module=roomtables&command=tableqrcodes&v=2.9.12",'_blank');
		});
		
		this.bindRoomChanges();
	};
	
	this.getTogoEntry = function() {
		var txt = '<div data-role="collapsible" data-content-theme="f" data-collapsed="true" data-theme="e" >';
		txt += '<h2>' + MAN_TOGO[lang]+ '</h2>';
		txt += '<p>' + MAN_ROOM_PRINTER_TXT[lang] + ": ";
		var printerOptions = [
			{ name: MAN_ROOM_PRINTER_NO[lang], value: 0},
			{ name: MAN_ROOM_PRINTER_1[lang], value: 1},
			{ name: MAN_ROOM_PRINTER_2[lang], value: 2},
			{ name: MAN_ROOM_PRINTER_3[lang], value: 3},
			{ name: MAN_ROOM_PRINTER_4[lang], value: 4}
		];
		txt += createGenericSelectBox("togoreceipt_printer",printerOptions,this.togoworkprinter,"togoworkreceiptprinter","c");

		txt += '</div>';
		
		return txt;
	}
	
	this.render = function() {
		var txt = "<form>";
		for (var i=0;i<this.rooms.length;i++) {
			var aRoom = this.rooms[i];
			txt += "<p>" + aRoom.render();
		};
		txt += this.getTogoEntry();
		txt += this.renderCancelApplyBtn();
		txt += this.renderCreateTableCodes();
		txt += "</form>";
		
		$("#roomfield").html(txt);
		$("#roomfield").trigger("create");
		
		this.binding();
	};
	
	this.getRoomWithId = function (roomid) {
		for (var i=0;i<this.rooms.length;i++) {
			if (this.rooms[i].roomid == roomid) {
				return this.rooms[i];
			}
		}
		return null;
	};
	
	
	
	this.getRoomIndexWithId = function (id) {
		for (var i=0;i<this.rooms.length;i++) {
			if (this.rooms[i].roomid == id) {
				return i;
			}
		}
		return null;
	};
	
	this.getRoomWithSorting = function (sorting) {
		for (var i=0;i<this.rooms.length;i++) {
			if (this.rooms[i].sorting == sorting) {
				return this.rooms[i];
			}
		}
		return null;
	};
	
	this.getMaxPropValue = function(startmax,property) {
		var max = startmax;
		for (var i=0;i<this.rooms.length;i++) {
			var maxValOfEntry = this.rooms[i][property];
			if (maxValOfEntry > max) {
				max = maxValOfEntry;
			}
		}
		return parseInt(max);
	};
	
	this.getMaxSorting = function () {
		return this.getMaxPropValue(0,"sorting");
	};
	this.getMaxId = function () {
		return this.getMaxPropValue(0,"id");
	};
	
	this.applyValues = function(roomid,doRerender) {
		var htmlId = "#room_" + roomid + "_";
		var name = $(htmlId + "name").val();
		var abbr = $(htmlId + "abbreviation").val();
		var printer = $(htmlId + "printer").val();
		
		var room = this.getRoomWithId(roomid);
		room.name = name;
		room.abbreviation = abbr;
		room.printer = printer;
		
		if (doRerender) {
			this.repaintGround();
		}
	};
	
	this.up = function(roomid) {
		var currentRoom = this.getRoomWithId(roomid);
		var currentSorting = currentRoom.sorting;
		if (currentSorting != 1) {
			var objBefore = this.getRoomWithSorting(currentSorting-1);
			objBefore.sorting = currentSorting;
			currentRoom.sorting = currentSorting-1;
		}
		this.repaintGround();
	};
	
	this.delete = function(roomid) {		
		var currentRoom = this.getRoomWithId(roomid);
		
		var currentSorting = parseInt(currentRoom.sorting);
		var currentIndex = parseInt(this.getRoomIndexWithId(roomid));
		var maxSort = this.getMaxSorting();
		this.rooms.splice(currentIndex,1);
		
		if (maxSort != currentSorting) {
			for (var sort=(currentSorting+1);sort<=maxSort;sort++) {
				var aRoom = this.getRoomWithSorting(sort);
				aRoom.sorting = (sort-1);
			}
		}

		this.repaintGround();
	};
	
        this.cloneRoom = function(roomid) {
                
                let roomname = $("#room_" + roomid + "_name").val();
                let abbr = $("#room_" + roomid + "_abbreviation").val();
                let printer = $("#room_" + roomid + "_printer").val();
                let sort = this.getMaxSorting() + 1;
                let clonedRoomId = this.createNewRoomWithParams(roomname + " - " + sort,abbr + "_" + sort,printer);
                let clonedRoom = this.getRoomWithId(clonedRoomId);
                // TODO: now step by step add the values to the new room
                let noOfTables = $('#roomtablearea_' + roomid + " table tbody tr" ).length - 2;
                let newTableList = [];
                for (let i=0;i<noOfTables;i++) {
                        let tableNameIntern = $('#roomtablearea_' + roomid + " table tbody tr").eq(i+1).find('td').eq(0).find('input').eq(0).val();
                        let tableNameExtern = $('#roomtablearea_' + roomid + " table tbody tr").eq(i+1).find('td').eq(1).find('input').eq(0).val();
                        let tableActive = $('#roomtablearea_' + roomid + " table tbody tr").eq(i+1).find('td').eq(2).find('select').val();
                        let tableArea = $('#roomtablearea_' + roomid + " table tbody tr").eq(i+1).find('td').eq(3).find('select').val();
                        let tableAllowGuestorder = $('#roomtablearea_' + roomid + " table tbody tr").eq(i+1).find('td').eq(5).find('select').val();
                        
                        let json = {
				roomid: this.roomid,
				tablename: tableNameIntern,
				name: tableNameExtern,
				area: tableArea,
				active: tableActive,
				code: "",
				allowoutorder: tableAllowGuestorder
			};
                        newTableList[newTableList.length] = json;
                }
                clonedRoom.createMultipleTables(newTableList);
        };
        
        this.createNewRoomWithParams = function(roomname,abbreviation,printer) {
		let newId = genUID("RR");
		let sort = this.getMaxSorting() + 1;

		var roomSpec = {roomid: newId,
			tables: [],
			roomname: roomname,
			abbreviation: abbreviation,
			printer: printer,
			sorting: sort,
			tableclassname: "roomtables_" + newId 
		};

		this.rooms[this.rooms.length] = new Room(roomSpec);
		this.repaintGround();
                return newId;
	};
        
	this.createNewRoom = function() {
                let sort = this.getMaxSorting() + 1;
                let roomname = MAN_SAMPLEROOMNAME[lang] + " " + sort;
                return this.createNewRoomWithParams(roomname,"",0);
	};
	
	this.createTableCodes = function() {
		var instance = this;
		doAjax("POST","php/contenthandler.php?module=roomtables&command=createTableCodes",null,instance.serverAnswerFromTablesChange.bind(instance),null,true);
	};
	
	this.serverAnswerFromTablesChange = function(answer) {
		if (answer.status != "OK") {
			alert("Fehler: " + answer.msg);
		} else {
			alert("Aktion durchgeführt");
			this.init();
		}
	};
}

function Room(jsonRoom) {
	
	
	this.tables = [];
	this.roomid = jsonRoom.roomid;
	this.name = jsonRoom.roomname;
	this.abbreviation = jsonRoom.abbreviation;
	this.printer = jsonRoom.printer;
	this.sorting = jsonRoom.sorting;

	this.tableclassname = "roomtables_" + this.roomid;
	
	for (var tableIndex = 0; tableIndex < jsonRoom.tables.length;tableIndex++) {
		this.tables[this.tables.length] = new Resttable(jsonRoom.tables[tableIndex],this.roomid,this.tableclassname);
	};
	
	this.sortTablesBySorting = function() {
		this.tables = this.tables.sort(function(a,b) {
			return a.sorting - b.sorting;
		});
	};
	
	this.sortTablesBySorting();

	this.renderTablesArea = function() {
		var staticTable = new Resttable(null,null,'');

		var txt = "<p><table class='groundplan'>";
		txt += staticTable.renderHeader();
		for (var i=0;i<this.tables.length;i++) {
			var aTable = this.tables[i];
			txt += aTable.render();
		}
		txt += staticTable.renderNewEntry(this.roomid);
		txt += "</table>";
		return txt;
	};
	
	this.rerenderTablesArea = function() {
		this.sortTablesBySorting();
		var txt = this.renderTablesArea();
		$("#roomtablearea_" + this.roomid).html(txt);
		$("#roomcollapsible_" + this.roomid).trigger("create");
		this.binding();
	};
	
	this.render = function() {
		var txt = "";
		
		txt += "<p>";
		txt += this.createCollapsibleStart("Raum " + this.name,"true");
		
		txt += "<p>";
		txt += this.createRoomHtml();
		
		txt += '<div id="roomtablearea_' + this.roomid + '">';
		txt += this.renderTablesArea();
		
		txt += this.createCollapsibleEnd();
		
		return txt;
	};
	
	this.createRoomHtml = function() {
		var htmlId = "room_" + this.roomid;
		var txt = "<table class='groundplan'>";
		txt += "<tr><th>Raumname<th>" + MAN_ROOM_ABBR_TXT[lang] + "<th>" + MAN_ROOM_PRINTER_TXT[lang] +  "<th>" + GEN_DIRECTION[lang] + "<th>" + GEN_ACTION[lang] + "</tr>";
		txt += "<tr><td>" + createGenericInputField(htmlId + "_name", this.name,"roomvalue_" + this.roomid + "_text");
		txt += "<td>" + createGenericInputField(htmlId + "_abbreviation", this.abbreviation,"roomvalue_" + this.roomid + "_text");
		
		var printerOptions = [
			{ name: MAN_ROOM_PRINTER_NO[lang], value: 0},
			{ name: MAN_ROOM_PRINTER_1[lang], value: 1},
			{ name: MAN_ROOM_PRINTER_2[lang], value: 2},
			{ name: MAN_ROOM_PRINTER_3[lang], value: 3},
			{ name: MAN_ROOM_PRINTER_4[lang], value: 4}
		];
		txt += "<td>" + createGenericSelectBox(htmlId + "_printer",printerOptions,this.printer,"roomvalue_" + this.roomid + "_printer","f");
		txt += "<td style='text-align:center;'><img id='" + htmlId + "_up' class='roomvalue_" + this.roomid + "_up' src='img/higher.png' />";
		txt += "<td style='text-align:center;color:red;'><img id='" + htmlId + "_delete' class='roomvalue_" + this.roomid + "_delete' src='img/delete.png' />";
                txt += "&nbsp;<img id='" + htmlId + "_clone' class='cloneroombtn' src='img/clone.png' />";

		txt += "</tr></table>";
		return txt;
	};
	
	this.createCollapsibleStart = function(headerText,isCollapsed) {
		var txt = '<div id="roomcollapsible_' + this.roomid + '" data-role="collapsible" data-collapsed="' + isCollapsed + '" data-theme="e" data-content-theme="f" class="noprint">';
		txt += '<h3>' + toHtml(headerText) + '</h3><p>';
		return txt;
	};
	this.createCollapsibleEnd = function() {
		return "</div></div>";
	};
	
	this.binding = function() {
		var instance = this;
		$("." + this.tableclassname + "_up").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
			var resttableid = this.id.split("_")[2];
			instance.up.call(instance,resttableid);
		});
		$("." + this.tableclassname + "_delete").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
			hideTableMap();
			var resttableid = this.id.split("_")[2];
			instance.delete.call(instance,resttableid);
		});
		$(".tablevalue_" + this.roomid).off("keyup").on("keyup", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
			hideTableMap();
			var resttableid = this.id.split("_")[2];
			instance.applyValues.call(instance,resttableid);
		});
		$(".tablevalue_" + this.roomid).off("change").on("change", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
			hideTableMap();
			var resttableid = this.id.split("_")[2];
			instance.applyValues.call(instance,resttableid);
			instance.rerenderTablesArea.call(instance);
		});
		$(".createnewtable_" + this.roomid).off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
			hideTableMap();
			instance.createNewTable.call(instance);
			instance.rerenderTablesArea.call(instance);
		});
	};
	
	this.applyValues = function(tableid) {
		var htmlId = "#table_" + this.roomid + "_" + tableid + "_";
		var tablename = $(htmlId + "tablename").val();
		var name = $(htmlId + "name").val();
		var area = $(htmlId + "area").val();
		var code = $(htmlId + "code").val();
		var active = $(htmlId + "active").val();
		var allowoutorder = $(htmlId + "allowoutorder").val();
		
		var table = this.getTableWithId(tableid);
		table.tablename = tablename;
		table.name = name;
		table.area = area;
		table.active = active;
		table.code = code;
		table.allowoutorder = allowoutorder;
	};
	
	this.up = function(tableid) {
		var currentTable = this.getTableWithId(tableid);
		var currentSorting = currentTable.sorting;
		if (currentSorting != 1) {
			var tableBefore = this.getTableWithSorting(currentSorting-1);
			tableBefore.sorting = currentSorting;
			currentTable.sorting = currentSorting-1;
		}
		this.rerenderTablesArea();
	};
	
	this.delete = function(tableid) {		
		var currentTable = this.getTableWithId(tableid);
		var currentSorting = parseInt(currentTable.sorting);
		var currentIndex = parseInt(this.getTableIndexWithId(tableid));
		var maxSort = this.getMaxSorting();
		this.tables.splice(currentIndex,1);
		
		if (maxSort != currentSorting) {
			for (var sort=(currentSorting+1);sort<=maxSort;sort++) {
				var aTable = this.getTableWithSorting(sort);
				aTable.sorting = (sort-1);
			}
		}

		this.rerenderTablesArea();
	};
	
        this.createMultipleTables = function(tableProps) {
                for (let i=0;i<tableProps.length;i++) {
                        let aTableProp = tableProps[i];
                        let newId = genUID("R" + this.roomid);
                        let sort = this.getMaxSorting() + 1;
                        
                        aTableProp['id'] = newId;
                        aTableProp['sorting'] = sort;
                        var restTable = new Resttable(aTableProp,this.roomid,this.tableclassname);
			this.tables[this.tables.length] = restTable;
                }
                this.rerenderTablesArea.call(this);
        }
        
	this.createNewTable = function() {
		var newId = genUID("R" + this.roomid);
		var sort = this.getMaxSorting() + 1;
		var tbltablename = $("#newtablename_" + this.roomid).val().trim();
		var tblname = $("#newname_" + this.roomid).val().trim();
		var tblarea = $("#newarea_" + this.roomid).val();
		var tblactive = $("#newactive_" + this.roomid).val();
		var tblcode = $("#newcode_" + this.roomid).val();
		var tblallowoutorder = $("#newallowoutorder_" + this.roomid).val();
		
		if (tbltablename == "") {
			alert("Tischname wurde nicht eingegeben!");
		} else {
			var json = {id: newId,
				roomid: this.roomid,
				tablename: tbltablename,
				name: tblname,
				area: tblarea,
				active: tblactive,
				code: tblcode,
				allowoutorder: tblallowoutorder,
				sorting: sort
			};

			var restTable = new Resttable(json,this.roomid,this.tableclassname);
			this.tables[this.tables.length] = restTable;
		}
	};
	
	this.getTableWithId = function (tableid) {
		for (var i=0;i<this.tables.length;i++) {
			if (this.tables[i].id == tableid) {
				return this.tables[i];
			}
		}
		return null;
	};
	
	this.getTableIndexWithId = function (tableid) {
		for (var i=0;i<this.tables.length;i++) {
			if (this.tables[i].id == tableid) {
				return i;
			}
		}
		return null;
	};
	
	this.getTableWithSorting = function (sorting) {
		for (var i=0;i<this.tables.length;i++) {
			if (this.tables[i].sorting == sorting) {
				return this.tables[i];
			}
		}
		return null;
	};
	
	this.getMaxPropValue = function(startmax,property) {
		var max = parseInt(startmax);
		for (var i=0;i<this.tables.length;i++) {
			var maxValOfEntry = parseInt(this.tables[i][property]);
			if (maxValOfEntry > max) {
				max = maxValOfEntry;
			}
		}
		return parseInt(max);
	};
	
	this.getMaxSorting = function () {
		return this.getMaxPropValue(0,"sorting");
	};
	this.getMaxId = function () {
		return this.getMaxPropValue(0,"id");
	};
	
	this.exportObject = function() {
		var exportObj = {
			roomid: this.roomid,
			name: this.name,
			abbreviation: this.abbreviation,
			printer: this.printer,
			sorting: this.sorting,
			tables: []
		};
		
		var tablesArr = [];
		
		this.tables.forEach(function(table,i) {
			tablesArr[tablesArr.length] = table.exportObject();
		});
		
		exportObj.tables = tablesArr;
		
		return exportObj;
	};
}

function Resttable(json,roomid,theClassname) {
	
	
	this.id = "";
	this.roomid = "";
	this.tablename = "";
	this.name = "";
	this.area = 0;
	this.active = 1;
	this.code = "";
	this.allowoutorder = "";
	this.sorting = 1;
	this.classname = "";
	
	if (json !== null) {
		this.id = json.id;
		this.roomid = roomid;
		this.tablename = json.tablename;
		this.name = json.name;
		this.area = json.area;
		this.active = json.active;
		this.code = json.code;
		this.allowoutorder = json.allowoutorder;
		this.sorting = json.sorting;
		this.classname = theClassname;
	}
	
	this.render = function() {
		var htmlId = "table_" + roomid + "_" + this.id;
		var txt = "";
		txt += "<tr><td>" + createGenericInputField(htmlId + "_tablename",this.tablename,"tablevalue_" + roomid);
		txt += "<td>" + createGenericInputField(htmlId + "_name",this.name,"tablevalue_" + roomid);
		txt += "<td>" + createGenericSelectBox(htmlId + "_active",[{name: "Ja",value: 1},{name: "Nein", value: 0}],this.active,"tablevalue_" + roomid,"f");
		txt += "<td>" + createGenericSelectBox(htmlId + "_area",this.createAreaValueArr(),this.area,"tablevalue_" + roomid,"f");
		txt += "<td>" + createGenericInputField(htmlId + "_code",this.code,"tablevalue_" + roomid);
		txt += "<td>" + createGenericSelectBox(htmlId + "_allowoutorder",[{name: "Ja",value: 1},{name: "Nein", value: 0}],this.allowoutorder,"tablevalue_" + roomid,"f");
		txt += "<td style='text-align:center;'><img id='" + htmlId + "_up' class='" + this.classname + "_up' src='img/higher.png' />";
		txt += "<td style='text-align:center;color:red;'><img id='" + htmlId + "_delete' class='" + this.classname + "_delete' src='img/delete.png' />";
		txt += "</tr>";
		return txt;
	};
	
	this.createAreaValueArr = function() {
		var vals = [];
		vals[vals.length] = {name: ' ', value: 0};
		for (var i=0;i<26;i++) {
			var character = String.fromCharCode(65 + i);
			vals[vals.length] = {name: character, value: (i+1)};
		}
		return vals;
	};

	this.renderHeader = function() {
		var txt = "<tr><th>Tischname (intern)<th>Tischname (extern)<th>Aktiv<th>Bereich<th>Code<th>Gastbestellung<th>" + GEN_DIRECTION[lang] + "<th>" + GEN_DEL[lang] + "</tr>";
		return txt;
	};
	
	this.renderNewEntry = function(id) {
		var txt = "<tr>";
		txt += "<td>" + createGenericInputField("newtablename_" + id,"");
		txt += "<td>" + createGenericInputField("newname_" + id,"");
		txt += "<td>" + createGenericSelectBox("newactive_" + id,[{name: "Ja",value: 1},{name: "Nein", value: 0}],1,"","f");
		txt += "<td>" + createGenericSelectBox("newarea_" + id,this.createAreaValueArr(),0,"","f");
		txt += "<td>" + createGenericInputField("newcode_" + id,"");
		txt += "<td>" + createGenericSelectBox("newallowoutorder_" + id,[{name: "Ja",value: 1},{name: "Nein", value: 0}],0,"","f");
		txt += "<td>&nbsp;";
		txt += "<td style='text-align:center;'><img class='createnewtable_" + id + "' src='img/add.png' style='height:50px;' />";
		return txt;
	};
	
	this.exportObject = function() {
		return {
			id: this.id,
			roomid: this.roomid,
			tablename: this.tablename,
			name: this.name,
			area: this.area,
			active: this.active,
			code: this.code,
			allowoutorder: this.allowoutorder,
			sorting: this.sorting
		};
	};
	
}

function createGenericSelectBox(id,options,value,theclass,datatheme) {
	var txt = "<select id='" + id + "' class='" + theclass + "' data-theme='" + datatheme + "'>";
	for (var i=0;i<options.length;i++) {
		var anOption = options[i];
		if (anOption.value == value) {
			txt += "<option value='" + anOption.value + "' selected>" + toHtml(anOption.name) + "</option>";
		} else {
			txt += "<option value='" + anOption.value + "'>" + toHtml(anOption.name) + "</option>";
		}
	}
	txt += "</select>";
	return txt;
}

function createGenericInputField(id,value,theclass) {
	var txt = "<input id='" + id + "' type='text' value='" + toHtml(value) + "' style='background:white;' class='" + theclass + "' />";
	return txt;
}

function genUID(prefix) {
    return prefix + Math.floor((1 + Math.random()) * 0x10000)
      .toString(10)
      .substring(1);
}

function hideTableMap() {
	$("#tablemap").hide();
}

function showTableMap() {
	$("#tablemap").show();
}
