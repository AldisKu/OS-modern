var mainmenu = [];

var menuIsDisplay = false;

var newOrderListHeaderTemplate = '<ul data-role="listview" id="neworderslist" data-divider-theme="a" data-inset="true">';
newOrderListHeaderTemplate += '<li data-role="list-divider" data-theme="c" data-role="heading">{HEADERLINE}</li>';	
        
// aList += '<li data-theme="' + theme + '" data-icon="' + icon + '" class="preparedlistitem ' + status + '" + id="'+ id_joined + '"><a href="#">' + img + label + option + imgpart + '</a></li>';

var entryTemplate = '<button id="{ORDERID}" class="w3-button w3-mobile bordered {TYPE}">';
entryTemplate += '  <img src="php/3rdparty/images/{ICON}" class="w3-bar-item w3-circle w3-hide-small" style="width:70px">';
entryTemplate += '  <div class="w3-bar-item">';
entryTemplate += '    {ITEM}';
entryTemplate += '  </div>';
entryTemplate += '</button>';
   

$(document).ready(function() {
	checkForLogIn();
	$.ajaxSetup({ cache: false });
        doAjax("GET", "php/contenthandler.php?module=admin&command=getJsonMenuItemsAndVersion", null, saveMenuInfo, null, true);
        intervalCheckConnection(2);
	intervalGetPrinterStatus(5);
        intervalGetNewOrders(20);
        bindmenu();
});

function bindmenu() {
       $(".mainmenu").off("click").on("click", function (e) {
		e.stopImmediatePropagation();
		e.preventDefault();
                if (menuIsDisplay) {
                        $("#mainmenulist").hide();
                        menuIsDisplay = false;
                } else {
                        $("#mainmenulist").show();
                        menuIsDisplay = true;
                }
                
        });
}

function fillmainmenu() {
        var txt = '<ul class="w3-ul w3-border">';
        mainmenu.forEach(function(entry) {
                txt += '<li class="w3-grey"><a href="' + entry.link + '" class="mainmenuentrylistelement">' + entry.name + '</li>';
        });
        txt += "</ul>";
        $("#mainmenulist").html(txt);
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

        fillmainmenu();
        $("#mainmenu").show();     
    } else {
        $("#mainmenu").hide();
    }
}

function intervalGetNewOrders(intervaltimeInseconds) {
        doAjax("GET","php/contenthandler.php?module=orders&command=getorders",null,handleorders,true);
        var fetchTimer = setInterval(function() {
                // TODO: doJsonAjax is not same like in waiter-> make same name!
                doAjax("GET","php/contenthandler.php?module=orders&command=getorders",null,handleorders,true);
		
	 }, intervaltimeInseconds * 1000);
}

function bind() {
        $(".neworder").off("click").on("click", function (e) {
		e.stopImmediatePropagation();
		e.preventDefault();
                var id = this.id;
                var data = {orderid:id};
                doAjax("GET","php/contenthandler.php?module=orders&command=declaredone",data,handleorders,true);
	});
        $(".doneorder").off("click").on("click", function (e) {
		e.stopImmediatePropagation();
		e.preventDefault();
                var id = this.id;
                var data = {orderid:id};
                doAjax("GET","php/contenthandler.php?module=orders&command=declarenew",data,handleorders,true);
	});
}

function renderItemsWithExtras(items) {
        var txt = "<div class='prodlist'>";
        items.forEach(function(anItem) {
                txt += "<span class='prodamount'>" + anItem.amount + "x </span> <span class='prodname'>" + toHtml(anItem.prodname) + "</span>";
                var extras = anItem.extras;
                
                txt += "<br />";
                if ((extras !== null) && (extras.length > 0)) {
                        var extrasTxt = "";
                        extras.forEach(function(anExtra) {
                                extrasTxt += "&nbsp;&nbsp;&nbsp;&nbsp;<span class='extraamount'>" + anExtra.amount + "x </span> "
                                        + "<span class='extraname'>" + anExtra.name + "</span><br />";
                        });
                      txt += extrasTxt;
                        
                }
        });
        txt += "</div>";
        return txt;
}

function handleorders(answer) {
        if (answer.status === "OK") {
                var newOrders = answer.msg.neworders;
                var txt = createorderspanel(newOrders,"neworder");
                $("#neworderslist").html(txt);

                var doneOrders = answer.msg.doneorders;
                var txt = createorderspanel(doneOrders,"doneorder");
                $("#doneorderslist").html(txt);
                
                bind();
        }

}

function createorderspanel(orders,ordertype) {
        
                var txt = ""; // newOrderListHeaderTemplate.replace('HEADERLINE',"Neue Lieferaufträge");
                
                orders.forEach(function (entry) {
                        var orderinfo = entry.orderinfo;

                        var listEntries = [];

                        listEntries[listEntries.length] = '<div class="orderinfo"><span>Auftrag {ORDERID} für: <b>{NAME}</b></span>'.replace('{NAME}', toHtml(orderinfo.name)).replace('{ORDERID}',toHtml(orderinfo.id));
                        if ((orderinfo.street !== "") || (orderinfo.city !== "")) {
                                var address = '<span>Adresse: {STREET} {HOUSENUMBER}, {PLZ} {CITY}</span>'.replace('{STREET}', toHtml(orderinfo.street));
                                address = address.replace('{HOUSENUMBER}', toHtml(orderinfo.housenumber));
                                address = address.replace('{PLZ}', toHtml(orderinfo.postalcode));
                                address = address.replace('{CITY}', toHtml(orderinfo.city));
                                listEntries[listEntries.length] = address;
                        }
                        
                        if (orderinfo.phone !== "") {
                                listEntries[listEntries.length] = '<span>Telefon: {PHONE}</span>'.replace('{PHONE}',toHtml(orderinfo.phone));
                        }
                        if (orderinfo.remark !== "") {
                                listEntries[listEntries.length] = '<span>Bemerkung: {REMARK}</span>'.replace('{REMARK}',toHtml(orderinfo.remark));
                        }
                        if (orderinfo.email !== "") {
                                listEntries[listEntries.length] = '<span>E-Mail: {EMAIL}</span>'.replace('{EMAIL}',toHtml(orderinfo.email));
                        }

                        var whenAndWho = '</div><br><i><span>Annahme durch: {CREATOR} am {CREATIONDATE}</span></i><br>'.replace('{CREATOR}',toHtml(orderinfo.username));
                        whenAndWho = whenAndWho.replace('{CREATIONDATE}',orderinfo.creationdate);
                        listEntries[listEntries.length] = whenAndWho;
                        

                        var entryLine = listEntries.join('<br>');
                        entryLine += renderItemsWithExtras(entry.items);
                        var icon = "round_phone_black_24dp.png";
                        if (ordertype === "doneorder") {
                                icon = "round_archive_black_24dp.png";
                        }
                        // round_phone_black_24dp.png
                        var entryItem = entryTemplate.replace('{ITEM}',entryLine).replace('{ORDERID}',orderinfo.id).replace('{TYPE}',ordertype).replace('{ICON}',icon);
                        txt += entryItem;
                });

                return txt;
}