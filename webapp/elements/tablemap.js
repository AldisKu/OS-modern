var tminfo = null;

function insertTMInfo(jsonTminfo) {
	tminfo = jsonTminfo;
}

function Tablemap (roomid,tables,elem) {
    this.tables = tables;
    this.roomid = roomid;
    this.elem = elem;
    this.selectedTableId = -1;
    
    if ((tables != null) && (tables.length > 0)) {
    	this.selectedTableId = tables[0].id;
    }
}

Tablemap.prototype.renderContent = function() {
	doAjaxTransmitData("GET","php/tablemap.php?command=getTableMap&roomid=" + this.roomid,null,this.renderTableList,null,this);
	
	d = new Date();
	$("#mapimgpart").attr("src", "php/tablemap.php?command=getTableMapImgAsPng&roomid=" + this.roomid + "&tableid=" + this.selectedTableId + "&"+d.getTime());
}

Tablemap.prototype.renderTableList = function(tables,instance) {
	instance.tables = tables;
	var tablelist = instance.createList(tables,"table_","tablebtn","c",instance);
	$(instance.elem).html(tablelist);
	tmRefreshList(instance.elem);
	instance.binding(instance);
}

Tablemap.prototype.createList = function(aList,idPrefix,classes,defaultTheme,instance) {
	var txt = '<form><fieldset data-role="controlgroup" data-type="horizontal">';
	for (var i=0;i<aList.length;i++) {
		var aListElem = aList[i];
		var name = aListElem.name;
		var id = aListElem.id;
		var theme = "d";
		var icon = "alert";
		
		var hasPos = aListElem.haspos;
		if (hasPos == 1) {
			icon = "check";
			theme = defaultTheme;
		}
		if (id == instance.selectedTableId) {
			theme = "f";
		}
		txt += '<input id="' + idPrefix + id + '" class="' + classes + '" type="submit" value="' + name + '" data-theme="' + theme + '" data-icon="' + icon + '" />';
	}
	txt += '</fieldset></form>';
	return txt;
}

Tablemap.prototype.binding = function(instance) {
	$('.tablebtn').off("click").on("click", function (e) {
		e.stopImmediatePropagation();
		e.preventDefault();
		instance.selectedTableId = this.id.split("_")[1];
		instance.renderContent(instance.elem);
	});
	
	$("#tmimgbtn").off("click").on("click", function (e) {
		e.stopImmediatePropagation();
		e.preventDefault();
		instance.uploadImg(instance);
	});
	
	$("#mapimgpart").off("click").on("click", function (e) {
		e.stopImmediatePropagation();
		e.preventDefault();
		
		var width = $('#mapimgpart').width();
		var height = $('#mapimgpart').height();

		var offset_t = $(this).offset().top - $(window).scrollTop();
	    var offset_l = $(this).offset().left - $(window).scrollLeft();

	    var left = Math.round( (e.clientX - offset_l) );
	    var top = Math.round( (e.clientY - offset_t) );

	    var data = {
	    		tableid:instance.selectedTableId,
	    		x:(100*left)/width,
	    		y:(100*top)/height
	    };
	    
	    doAjaxTransmitData("POST","php/tablemap.php?command=setPosition",data,instance.renderIfOk,null,instance);
	});
}

Tablemap.prototype.createOverlay = function (elem,positions,payTxt,decpoint,currency,tables,ostablebtnsize,selectedTableid) {
	if (typeof selectedTableid === 'undefined') {
		selectedTableid = null; 
	}
	var t = [];

	var sizeclass = "";
	if (ostablebtnsize == 0) {
		sizeclass = "overlaysize-0";
	} else if (ostablebtnsize == 1) {
		sizeclass = "overlaysize-1";
	} else if (ostablebtnsize == 2) {
		sizeclass = "overlaysize-2";
	}
	
	for (var i=0;i<positions.length;i++) {
		var aPos = positions[i];
		if (aPos.haspos == 1) {
			var posOfATable = aPos.pos;
			var tableId = aPos.id;
			var tablename = aPos.name;

			var price = '0.00';
			let unpaidprodcount = 0;
			for (j=0;j<tables.length;j++) {
				if (tables[j].id == tableId) {
					price = tables[j].pricesum;
					unpaidprodcount = tables[j].unpaidprodcount;
					break;
				}
			}

			var left = posOfATable.x;
			var top =posOfATable.y; //100 / height * posOfATable.y;
			var spanid = "overlay_" + tableId;
			var priceTxt = price.replace(".", decpoint) + " " + currency;
			var selectedCss = '';
			if (selectedTableid == tableId) {
				selectedCss = ' selectedtable ';
			}

			var txt = '<span id="' + spanid + '" class="overlaytxt overlayempty ' + selectedCss + sizeclass + '" style="z-index:100;position:absolute;left:' + left + '%;top:' + top + '%;">' + tablename;
			if ((unpaidprodcount > 0) || (price != 0.00)) {
				txt = '<span id="' + spanid + '" class="overlaytxt overlayfull ' + selectedCss + sizeclass + '" style="z-index:100;position:absolute;left:' + left + '%;top:' + top + '%;">' + tablename;
				if (payTxt != '') {
					txt += '<br>(' + payTxt + ': ' + priceTxt +    ')';
				} else {
					txt += '<br>(' + priceTxt +    ')';
				}
			}
			txt += '</span>';
			t[t.length] = txt;
		}
	}
	return t;
}

Tablemap.prototype.bindingForOverlaySelection = function(fct,roomid,tables,fctZoomView) {
	if (fctZoomView != null) {
		$("#tablemapcontent:not(.overlaytxt)").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
			fctZoomView();
		});
	}
	$("#tablemapcontent .overlaytxt").off("click").on("click", function (e) {
		e.stopImmediatePropagation();
		e.preventDefault();
		
		var tableid = this.id.split('_')[1];
		var tablename = "?";
		for (var i=0;i<tables.length;i++) {
			var table = tables[i];
			if (table.id == tableid) {
				tablename = table.name;
			}
		}
		
		var data = {
	    		roomid:roomid,
	    		tableid:tableid,
	    		tablename:tablename,
	    		x:0,
	    		y:0
	    };
		fct(data);
	});
}

Tablemap.prototype.bindingForSelection = function(elem,fct,positions,roomid) {
	$(elem).off("click").on("click", function (e) {
		e.stopImmediatePropagation();
		e.preventDefault();
		
		var width = $(elem).width();
		var height = $(elem).height();

		var offset_t = $(this).offset().top - $(window).scrollTop();
	    var offset_l = $(this).offset().left - $(window).scrollLeft();

	    var left = 100 * Math.round( (e.clientX - offset_l) ) / width;
	    var top = 100 * Math.round( (e.clientY - offset_t) ) / height;

	    if ((positions != null) && (positions.length > 0)) {
	    	var foundTableId = positions[0].id;
	    	var tablename = positions[0].name;
	    	var minDist = (100*100) + (100*100);
		    for (var i=0;i<positions.length;i++) {
		    	var aPos = positions[i];
		    	if (aPos.haspos == 1) {
		    		var posOfATable = aPos.pos;
		    		var aDist = (posOfATable.x - left) * (posOfATable.x - left) + ((posOfATable.y - top)  * (posOfATable.y - top));
		    		if (aDist < minDist) {
		    			minDist = aDist;
		    			foundTableId = aPos.id; 
		    			tablename = aPos.name;
		    		}
		    	}
		    }
		    var data = {
		    		roomid:roomid,
		    		tableid:foundTableId,
		    		tablename:tablename,
		    		x:left,
		    		y:top
		    };
		    fct(data);
	    }
	});
}


Tablemap.prototype.renderIfOk = function(jsonAnswer,instance) {
	if (jsonAnswer.status == "OK") {
		instance.renderContent(instance.elem);
	} else {
		alert("Fehler: " + jsonAnswer.msg);
	}
}

Tablemap.prototype.tmCreateList = function(aList,idPrefix,classes,defaultTheme) {
	var txt = '<form><fieldset data-role="controlgroup" data-type="horizontal">';
	for (var i=0;i<aList.length;i++) {
		var aListElem = aList[i];
		var name = aListElem.name;
		var id = aListElem.id;
		txt += '<input id="' + idPrefix + id + '" class="' + classes + '" type="submit" value="' + name + '" data-theme="' + defaultTheme + '" />';
	}
	txt += '</fieldset></form>';
	return txt;
}

Tablemap.prototype.uploadImg = function(instance) {
	var formData = new FormData($('#tablemapimgform')[0]);

    formData.append("roomid",instance.roomid);
    $.ajax({
        url: 'php/tablemap.php?command=uploadimg',  //Server script to process data
        type: 'POST',
        dataType: "json",
        xhr: function() {  // Custom XMLHttpRequest
            var myXhr = $.ajaxSettings.xhr();
            //if(myXhr.upload){ // Check if upload property exists
                //myXhr.upload.addEventListener('progress',progressHandlingFunction, false); // For handling the progress of the upload
            //}
            return myXhr;
        },
        //Ajax events
        success: function(jsonContent) {
        	if (jsonContent.status != "OK") {
        		instance.imgNotUploaded(jsonContent);
        	} else {
        		instance.imgUploaded(jsonContent,instance);
        	}
        },
        error: function(answer) {
        	instance.imgNotUploaded(answer);
        },
        // Form data
        data: formData,
        //Options to tell jQuery: do not to process data or worry about content-type.
        cache: false,
        contentType: false,
        processData: false
    });
}

Tablemap.prototype.imgUploaded = function(text,instance) {
	instance.renderContent();
}

Tablemap.prototype.imgNotUploaded = function(text) {
	alert("Bildupload nicht erlaubt oder Bild konnte nicht hochgeladen werden. Ist es zu groß (> 1 MB)? Oder wurde der Dateiname nicht angegeben?");
}

function shallDisplayRoom(roomid) {
	var xyz = tminfo;
	if (tminfo == null) {
		return {show:false};
	}
	for (var i=0;i<tminfo.length;i++) {
		if (tminfo[i].roomid == roomid) {
			return {show:tminfo[i].displaymap,pos:tminfo[i].tablepositions};
		}
	}
        
	return {show:0};
}
