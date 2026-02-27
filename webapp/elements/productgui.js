class ProductGui {
        
        constructor() {}
        
        createOption(currentVal, optionvalue, optionlabel) {
                if (currentVal == optionvalue) {
                        return "<option value='" + optionvalue + "' selected>" + optionlabel + "</option>";
                } else {
                        return "<option value='" + optionvalue + "'>" + optionlabel + "</option>";
                }
        };
        
        createOptionsList(choices,currentVal) {
                var instance = this;
                var txt = "";
                choices.forEach((aChoice) => {
                                txt += instance.createOption(currentVal,aChoice.val,aChoice.label);
                        });
                return txt;
        }
        
        createYesNoOptions(currentValue) {
                let choices = [
                        {val: 1, label: "Ja"},
                        {val: 0, label: "Nein"}
                ];
                return this.createOptionsList(choices,currentValue);
        }
        
        createApplyArea(idApply) {
                return '<p><fieldset>'
		    		+ '<div><button id="' + idApply + '" type="submit" data-theme="b" class="oscmd" data-icon="check">' + PROD_APPLY[lang] + '</button></div>'
		    		+ '</fieldset></p>';
        }


        createInputOsCmdField(idForNameOfField,idForInputField,nameOfInputField,label,inputValue) {
                var txt = '<p><div data-role="fieldcontain">'
                        + '<label for="' + idForInputField + '"><span id="' + idForNameOfField + '">' + label + '</span></label>'
                        + '<input type="button" name="' + nameOfInputField + '" id="' + idForInputField + '" class="oscmd" value="' + inputValue + '" data-theme="c" /></div></p>';
                return txt;
        }
        
        createProdNavArea(id) {
                var buttonUp = '<button id="produp_' + id + '" type="submit" data-theme="c" class="oscmd">&uarr;</button>';
                var buttonDown = '<button id="proddown_' + id + '" type="submit" data-theme="c" class="oscmd">&darr;</button>';
                var buttonDel = '<button id="proddel_' + id + '" type="submit" data-theme="c" class="oscmd">' + PROD_DEL[lang] + '</button>';

                var aText = '';
                aText += '<p><fieldset class="ui-grid-b">';
                aText += '	<div class="ui-block-a delProd">' + buttonDel + '</div>';
                aText += '	<div class="ui-block-b prodUp">' + buttonUp + '</div>';
                aText += '	<div class="ui-block-c prodDown">' + buttonDown + '</div>';
                aText += '</fieldset></p>';
                return aText;
        }
        
        createProdContentStructure(productEntry,proddef,isNewProd,language) {
                let prodid = productEntry.id;
                let defaultStyle = ' style="background:white;color:black;" ';
                
                var txt = "";
                proddef.forEach( (prodDefEntry) => {
                        let property = prodDefEntry.property;
                        var value = null;
                        if ((productEntry !== null) && productEntry.hasOwnProperty(property)) {
                                var value = productEntry[property];
                        } else {
                                if (prodDefEntry.hasOwnProperty("default")) {
                                        value = prodDefEntry["default"];
                                }
                        }
                        let thetype = prodDefEntry["type"];
                        if (prodDefEntry["guiname"] !== undefined) {
                                var guiname = prodDefEntry["guiname"][language];
                        }
                        let guivals = prodDefEntry["guivals"];
                        if (guivals !== undefined) {
                                thetype = "selection";
                        }
                        
                        if ((property === "taxaustria") && (austria == 0)) {
                                return;
                        }
                        if (((property === "tax") || (property === "togotax")) && (austria == 1)) {
                                return;
                        }
                        
                        if ((guiname !== undefined) && (property !== "id")) {
                                let idOfField = property + "_input_" + prodid;
                                let nameIdStyleAttribute = ' name="' + idOfField + '" id="' + idOfField + '" ' +defaultStyle;
                                switch (thetype) {
                                        case "int":
                                                txt += '<p><div data-role="fieldcontain">'
                                                        + '<label for="' + idOfField + '">' + guiname + '</label>'
                                                        + '<input type="number" ' + nameIdStyleAttribute + '/></div></p>';
                                                break;
                                        case "float":
                                        case "text":
                                                txt += '<p><div data-role="fieldcontain">'
                                                        + '<label for="' + idOfField + '">' + guiname + '</label>'
                                                        + '<input type="text" ' + nameIdStyleAttribute + '/></div></p>';
                                                break;
                                        case "textarea":
                                                txt += '<p><div data-role="fieldcontain">'
                                                        + '<label for="' + idOfField + '">' + guiname + '</label>'
                                                        + '<textarea name="' + idOfField + '" id="' + idOfField + '" ' + defaultStyle + '/></textarea></div></p>';
                                                break;
                                        case "yesno":
                                                let yesNoOptions = this.createYesNoOptions(value);
                                                txt += '<p><div data-role="fieldcontain">'
                                                        + '<label for="' + idOfField + '">' + guiname + '</label>'
                                                        + '<select id="' + idOfField + '" >'
                                                        + yesNoOptions
                                                        + '</select>'
                                                        + '</div></p>';
                                                break;
                                        case "selection":
                                                let allSelectionOptions = this.createOptionsList(guivals,value);
                                                txt += '<p><div data-role="fieldcontain">'
                                                        + '<label for="' + idOfField + '">' + guiname + '</label>'
                                                        + '<select id="' + idOfField + '" >'
                                                        + allSelectionOptions
                                                        + '</select>'
                                                        + '</div></p>';
                                                break;
                                };
                                
                        }
                        if (prodDefEntry["info"] !== undefined) {
                                var info = prodDefEntry["info"];
                                txt += "<i>" + info + "</i>";
                        }
                });
                
                if (isNewProd) {
                        txt += this.createApplyArea(prodid);
                } else {
                        var idOfExtrasInput = "prodextrainput_" + prodid;
                        txt += this.createInputOsCmdField("prodextra_" + prodid,idOfExtrasInput,"extra",PROD_EXTRAS[lang],PROD_DO_ASS[lang]);
                        txt += "<div id=assextralist_" + prodid + " class='assextralist'></div>";
                        txt += createResetApplyArea("prodapply_" + prodid,"prodcancel_" + prodid,null) + '</div>';
                }
                return txt;
        }
        

        insertValues(productEntry,proddef) {
                let prodid = productEntry.id;
                proddef.forEach( (prodDefEntry) => {
                        let property = prodDefEntry.property;
                        if (property !== "id") {
                                let value = productEntry[property];
                                let thetype = prodDefEntry["type"];
                                if ((value === 'null') && (thetype !== 'int')) {
                                        value = '';
                                } else if ((value === 'null') && (thetype === 'int')) {
                                        value = prodDefEntry["default"];
                                } else if ((property === "display") && (value !== "KG") && (value !== "K") && (value !== "G")) {
                                        value = prodDefEntry["default"];
                                } else if ((property === "days") && (value === "0123456")) {
                                        value = '';
                                }

                                let idOfField = property + "_input_" + prodid;
                                
                                switch (thetype) {
                                        case "int":
                                        case "float":
                                                let countrySpecificVal = '';
                                                if ((value !== null) && (value !== undefined) && (typeof value === 'string' || value instanceof String)) {
                                                        countrySpecificVal = value.replace(".",decpoint);
                                                }
                                                $("#" + idOfField).val(countrySpecificVal);
                                                break;
                                        case "text":
                                        case "textarea":
                                                $("#" + idOfField).val(value);
                                                break;
                                };
                        }
                });
        }
        
        getPropertiesOfProdFromGui(prodid,proddef) {
                var props = {};
                proddef.forEach( (prodDefEntry) => {
                        if (prodDefEntry.hasOwnProperty("guiname")) {
                                let property = prodDefEntry.property;
                                if (property !== "id") {
                                        let idOfField = property + "_input_" + prodid;
                                        let value = $("#" + idOfField).val();
                                        let theType = prodDefEntry["type"];
                                        if ((value === "") && (prodDefEntry.hasOwnProperty("default"))) {
                                                switch(theType) {
                                                        case "int":
                                                        case "float":                
                                                                value = prodDefEntry.default;
                                                                break;
                                                }
                                        } else {
                                                if (theType === "float") {
                                                        value = value.replace(decpoint,".");
                                                }
                                                if ((theType === "int") && (prodDefEntry.minvalue !== undefined) && (parseInt(value) < prodDefEntry.minvalue)) {
                                                        value = prodDefEntry.minvalue;
                                                        $("#" + idOfField).val(value);
                                                        alert("Korrigiere Wert für " + prodDefEntry.hasOwnProperty("guiname") + " auf " + prodDefEntry.minvalue);
                                                }
                                        }
                                        props[property] = value;
                                }
                        }
                });
                return props;
        }
        
        createOneEntryOfProdInCat(divIdPrefix,prodid,longname,isNewProdEntry,isAvailable) {
                var theme="e";
                if (isNewProdEntry) {
                        theme="f";
                }
                let isAvailableClass = 'prodisavailable';
                if (isAvailable == 0) {
                        isAvailableClass = 'prodisnotavailable';
                }
                var line = '<div id="' + divIdPrefix + + prodid + '" data-role="collapsible" data-content-theme="a" data-theme="' + theme + '" class="prodcollapsible" >'
                        +'<h3><span id=prodheader_' + prodid + ' class="' + isAvailableClass + '">' + toHtml(longname) + '</span>';
                if (!isNewProdEntry) {
                        line += "<div class='produp oscmd' id=produp_" + prodid + ">&uarr;</div>"
                        + "<div class='proddown oscmd' id=proddown_" + prodid + ">&darr;</div>"
                        + "<div class='proddel oscmd' id=proddel_" + prodid + ">&#128465;</div>";
                };
                line += "</h3>";
                if (!isNewProdEntry) {
                        line += '<p id="contprod_' + prodid + '" ><img src="php/3rdparty/images/ajax-loader.gif" /></div>';
                } else {
                        line += '<p id="newprod_' + prodid + '" ><img src="php/3rdparty/images/ajax-loader.gif" /></div>';
                }
                return line;
        }
        createListOfProductsOfACategory(catId,allProdsOfCat,doCreateEntryForNewProduct) {
                var txt = "";
                txt += PROD_PRODUCTS_LIST[lang] + ":<br>";
                allProdsOfCat.forEach((aProd) => {
                        txt += this.createOneEntryOfProdInCat("cont_",aProd.id,aProd.longname,false,aProd.available);
                });
                if (doCreateEntryForNewProduct) {
                        txt += this.createOneEntryOfProdInCat("new_",catId,PROD_NEW_PROD[lang],true,false);
                }
                return txt;
        }
}