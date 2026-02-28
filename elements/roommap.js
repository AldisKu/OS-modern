/**
 * 
 */


function Roommap (containerEl) {
	this.roomindex = -1;
	this.containerEl = containerEl;
	doAjaxTransmitData("GET","php/tablemap.php?command=getRoomTableMap",null,this.fillRoom,null,this);
}

Roommap.prototype.reload = function() {
	doAjaxTransmitData("GET","php/tablemap.php?command=getRoomTableMap",null,this.fillRoom.bind(this),null,this);
}

Roommap.prototype.fillRoom = function(tabs,instance) {
	instance.tmRoommap = tabs;
	if (instance.roomindex < 0) {
		if (instance.tmRoommap.length > 0) {
			instance.roomindex = 0;
		} else {
			instance.roomindex = -1;
		}
	}
	instance.renderRoomList(instance);
}

Roommap.prototype.renderRoomList = function(instance) {
	
	var tabHtml = tmCreateList(instance.tmRoommap,"room_","roombtn","e",instance);
	$(instance.containerEl).html(tabHtml);
	$(instance.containerEl).trigger("create");
	
	instance.tablemaps = [];
	for (var roomindex=0;roomindex<instance.tmRoommap.length;roomindex++) {
		var tables = instance.tmRoommap[roomindex].tables;
		instance.tablemaps[instance.tablemaps.length] = new Tablemap(instance.tmRoommap[roomindex].id,tables,"#tablenav");
	}
	
	if (instance.tmRoommap.length > 0) {
		instance.tablemaps[instance.roomindex].renderContent();
	}
	
	instance.binding(instance);
}

Roommap.prototype.binding = function(instance) {
	$('.roombtn').off("click").on("click", function (e) {
		e.stopImmediatePropagation();
		e.preventDefault();
		var roomindex = this.id.split("_")[1];
		instance.roomindex = roomindex;
		
		//instance.tablemaps[roomindex].renderContent();
		instance.renderRoomList(instance);
	});
	
	$("#tmimgdelbtn").off("click").on("click", function (e) {
		e.stopImmediatePropagation();
		e.preventDefault();
		var roomid = instance.tmRoommap[instance.roomindex].id;
		doAjaxTransmitData("POST","php/tablemap.php?command=deleteTableMap",{roomid:roomid},instance.reload.bind(instance),null,instance);
	});
}

function tmCreateList(aList,idPrefix,classes,defaultTheme,instance) {
	var txt = '<form><fieldset data-role="controlgroup" data-type="horizontal">';
	for (var i=0;i<aList.length;i++) {
		var theme = defaultTheme;
		if (i==instance.roomindex) {
			theme = "f";
		}
		var aListElem = aList[i];
		var name = aListElem.name;
		var id = aListElem.id;
		txt += '<input id="' + idPrefix + i + '" class="' + classes + '" type="submit" value="' + name + '" data-theme="' + theme + '" />';
	}
	txt += '</fieldset></form>';
	return txt;
}

function tmRefreshList(selector) {
	if ( $(selector).hasClass('ui-listview')) {
		$(selector).listview('refresh');
	} else {
	    $(selector).trigger('create');
	}
}