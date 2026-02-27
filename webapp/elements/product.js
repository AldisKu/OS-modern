
function ident(depth) {
	var txt = "";
	var i=0;
	for (i=0;i<depth;i++) {
		txt += "&#9658;";
	}
	return txt;
}

function createInputField(idForNameOfField,idForInputField,nameOfInputField) {
	var txt = '<p><div data-role="fieldcontain">'
		+ '<label for="' + idForInputField + '"><span id="' + idForNameOfField + '"></span></label>'
		+ '<input type="text" name="' + nameOfInputField + '" id="' + idForInputField + '" style="background:white;color:black;" /></div></p>';
	return txt;
}

function createInputOsCmdField(idForNameOfField,idForInputField,nameOfInputField) {
	var txt = '<p><div data-role="fieldcontain">'
		+ '<label for="' + idForInputField + '"><span id="' + idForNameOfField + '"></span></label>'
		+ '<input type="button" name="' + nameOfInputField + '" id="' + idForInputField + '" class="oscmd" value="' + PROD_DO_ASS[lang] + '" data-theme="c" /></div></p>';
	return txt;
}

function createResetApplyArea(idApply,idCancel,idDel) {
	var txt = '<p><fieldset class="ui-grid-';
	if (idDel === null) {
		txt += 'a">'
			+ '<div class="ui-block-a"><button id="' + idCancel + '" type="submit" data-theme="c" class="oscmd" data-icon="back">' + PROD_CANCEL[lang] + '</button></div>'
			+ '<div class="ui-block-b"><button id="' + idApply + '" type="submit" data-theme="b" class="oscmd" data-icon="check">' + PROD_APPLY[lang] + '</button></div>'
			+ '</fieldset></p>';
	} else {
		txt += 'b">'
			+ '<div class="ui-block-a"><button id="' + idCancel + '" type="submit" data-theme="c" class="oscmd" data-icon="back">' + PROD_CANCEL[lang] + '</button></div>'
			+ '<div class="ui-block-b"><button id="' + idApply + '" type="submit" data-theme="b" class="oscmd" data-icon="check">' + PROD_APPLY[lang] + '</button></div>'
			+ '<div class="ui-block-c"><button id="' + idDel + '" type="submit" data-theme="d" class="oscmd" data-icon="delete">' + PROD_DEL[lang] + '</button></div>'
			+ '</fieldset></p>';
	}
	return txt;
}
	
function createProdType(id,name,kind,usekitchen,usesupplydesk,printer,fixbind) {
	var prodtype = {
			id:id,
			name: name,
			usekitchen:usekitchen,
			usesupplydesk:usesupplydesk,
			kind:kind,
			printer:printer,
			fixbind:fixbind,
			createTableStructureLine: function (depth) {
				var depthstr = ident(depth);
		    	var trline = "<tr class='prodtype' id='prodtype_" + this.id + "'>"
		    	+ "<td>" + depthstr + "<input type='text' class='typename whiteinput'></input>"
		    	+ "<td class='type_kind'>" + this.createValSelection(kind,[cat1name,cat2name])
		    	+ "<td class='type_usekitchen'>" + this.createValSelection(usekitchen,[PROD_NO,PROD_YES])
		    	+ "<td class='type_usesupply'>" + this.createValSelection(usesupplydesk,[PROD_NO,PROD_YES])
		    	+ "<td class='type_printer'>" + this.createValSelection(printer,[PROD_PRINTER_1,PROD_PRINTER_2,PROD_PRINTER_3,PROD_PRINTER_4])
		    	+ "<td colspan=6>"
		    	+ "</tr>";
		    	return trline;
		    },
		    insertValuesIntoMenuTable:function() {
		    	$("#prodtype_" + id + " input.typename").val(name);
		    },
		    createValSelection:function(theVal,valTextArray) {
			    var txt = "<select>";
			    for (var i=0;i<valTextArray.length;i++) {
				    if (theVal == i) {
					    txt += "<option val='" + i + "' selected class='yes'>" + valTextArray[i][lang] + "</option>";
				    } else {
					    txt += "<option val='" + i + "' class='no'>" + valTextArray[i][lang] + "</option>";
				    }
			    }
			    return txt + "</select>";
		    },
		    
		    
		    createUpperMenuTypeStructure: function() {
			    
			var txt = '<h3>' + this.name + '</h3><p>'
			    
			+ '<button id="toggleprodtype_' + this.id + '" type="submit" data-theme="c" class="oscmd">' + PROD_TYPEPROPS[lang] + '</button>'

			+ '<div id="typepropertiespart_' + this.id + '" style="display:none;" >'
		    	+ "<div id=dtypekind_" + this.id + " ></div>"
		    	+ "<div id=dtypeuk_" + this.id + " ></div>"
		    	+ "<div id=dtypeus_" + this.id + " ></div>"
		    	+ "<div id=dtypeprinter_" + this.id + " ></div>"
			+ "<div id=dtypefixbind_" + this.id + " ></div>"
	
		    	+ createInputField("typename_" + this.id,"typename_input_" + this.id,"typename_input_" + this.id)
		
		    	+ createResetApplyArea("typeapply_" + this.id,"typecancel_" + this.id,"typedel_" + this.id)
		
			+ '</div>'
	
		    	+ '</p>';
		    	return txt;
		    },
		    createLowerMenuTypeStructure: function() {
		    	var style = ' style="background-color: white;" ';
		    	var newTypeName = '<p><input type="text" name="newtypename" id="newtypename_' + this.id + '" placeholder="' + PROD_PLACEHOLDER_NEW_PRODTYPE[lang] + '"' + style + '/></p>';
		    	
		    	var newTypeBtn = '<p><button id="newtype_' + this.id + '" type="submit" data-theme="c" class="oscmd">' + PROD_NEW_CAT[lang] + '</button></p>';
		    	var assignBtn = '<p><button id="assignprod_' + this.id + '" type="submit" data-theme="c" class="oscmd">' + PROD_ASSIGN[lang] + ' &#10155;  </button></p>';
			var sortAlphaBtn = '<p><button id="sortalphaprod_' + this.id + '" type="submit" data-theme="c" class="oscmd">' + PROD_SORT_ALPHA[lang] + '</button></p>';
		    	return newTypeName + newTypeBtn + assignBtn + sortAlphaBtn;
		    },
		    insertValuesIntoMenuList:function() {
		    	$("#typename_" + this.id).html(PROD_NAME[lang]);
		    	$("#typename_input_" + this.id).val(this.name);
		    	$("#dtypekind_" + this.id).html(this.createMobileSel("kind_" + this.id,this.kind,PROD_TYPE,[cat1name,cat2name]));
		    	$("#dtypeuk_" + this.id).html(this.createMobileSel("usekitchen_" + this.id,this.usekitchen,PROD_KITCHEN_BAR,[PROD_NO_PASS_KITCHEN,PROD_PASS_KITCHEN]));
		    	$("#dtypeus_" + this.id).html(this.createMobileSel("usesupply_" + this.id,this.usesupplydesk,PROD_SUPPLY,[PROD_NO_PASS_SUPPLY,PROD_PASS_SUPPLY]));
		    	$("#dtypeprinter_" + this.id).html(this.createMobileSel("printer_" + this.id,this.printer-1,PROD_PRINTER,[PROD_PRINTER_1,PROD_PRINTER_2,PROD_PRINTER_3,PROD_PRINTER_4]));
			$("#dtypefixbind_" + this.id).html(this.createMobileSel("fixbind_" + this.id,this.fixbind,PROD_PRINTER_PRIO,[PROD_PRINTER_FIXB0,PROD_PRINTER_FIXB1]));
		    },
		    createMobileSel:function(id,theVal,label,valTextArray) {
		    	var txt = '<p><div data-role="fieldcontain">'
		    		+ '<label for="' + id + '">' + label[lang] + '</label>'
		    		+ this.createSelection(id,theVal,valTextArray)
		    		+ '</div></p>';
		    	return txt;
		    },
		    createSelection:function(id,theVal,valTextArray) {
			var txt = "<select id='" + id + "'>";
			for (var i=0;i<valTextArray.length;i++) {
				if (theVal == i) {
					txt += "<option value='" + i + "' selected>" + valTextArray[i][lang] + "</option>";
				} else {
					txt += "<option value='" + i + "' >" + valTextArray[i][lang] + "</option>";
				}
			}
			return txt + "</select>";
		    }
	};
	return prodtype;
}