<!DOCTYPE html>
<html lang="de">
    <head>
    <title>Kundenansicht</title>
    <meta name="viewport" content="width=device-width, initial-scale=1"> 
    <meta http-equiv="content-type" content="text/html; charset=utf-8">
    <meta name="author" content="Stefan Pichel">
	<link rel="stylesheet" type="text/css" href="css/bestformat.css?v=2.9.12">
        <link rel="stylesheet" href="css/delivery.css?v=2.9.12" />
        <link rel="stylesheet" href="php/3rdparty/orderstyle/w3.css?v=2.9.12" />

    
    <script src="php/3rdparty/jquery-2.2.4.min.js"></script>
    <script src="php/3rdparty/jqueryui1-12-0/jquery-ui.min.js"></script>
  
   <script src="utilities.js"></script>
</head>

<body>
 
        <script>
                
        var mainmenu = [];
        var menuIsDisplay = false;

                
        $(document).ready(function() {
                checkForLogIn();
                $.ajaxSetup({ cache: false });
                doAjax("GET", "php/contenthandler.php?module=admin&command=getJsonMenuItemsAndVersion", null, saveMenuInfo, null, true);
                intervalGetLiveOrders(2);
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
            
        function intervalGetLiveOrders(period) {
                doAjax("GET","php/contenthandler.php?module=queue&command=getliveorders",null,handleorders,true);
                var fetchTimer = setInterval(function() {
                        // TODO: doJsonAjax is not same like in waiter-> make same name!
                        doAjax("GET","php/contenthandler.php?module=queue&command=getliveorders",null,handleorders,true);

                 }, period * 1000);
        }
        
        function handleorders(answer) {
                if (answer.status === "OK") {
                        
                        var showtableforcustomer = answer.msg.showtableforcustomer;
                        var tablename = answer.msg.tablename;
                        var entries = answer.msg.orders;
                        var userInfo = answer.msg.user;
                        var sum = answer.msg.sum;
                        
                        var ebonInfo = answer.msg.ebon;
                        var ebonUrl = answer.msg.ebonurl;
                        
                        if ((ebonInfo === '') || (ebonUrl === '')) {
                                showLiveOrders(showtableforcustomer,tablename,entries,userInfo,sum);
                        } else  {
                                showEbonInfo(ebonInfo,ebonUrl);
                        }
                }
        }
        
        function showLiveOrders(showtableforcustomer,tablename,entries,userInfo,sum) {
                var txt = '<table class="w3-table-all w3-xxlarge">';
                txt += '<thead>';
                txt += '<tr class="w3-Khaki w3-xxlarge" style="line-height: 140px;" ><th colspan="2" style="font-weight:bold;text-align:center;">Summe<th style="font-weight:bold;">' + sum + "</tr>";
                txt += '<tr><th>Menge<th>Artikel<th>Preis</tr></thead>';
                txt += '<tbody>';
                entries.forEach(function(anEntry) {
                        txt += '<tr><td>' + anEntry.amount;
                        txt += '<td>' + toHtml(anEntry.prodname);
                        txt += '<td>' + anEntry.price;
                        txt += '</tr>';
                });

                txt += '</tbody>';
                txt += '</table>';

                if ((showtableforcustomer == 1) && (tablename !== undefined) && (tablename !== null) &&  (tablename !== '')) {
                        userInfo = " an " + tablename;
                }

                txt += "<br /><br /><span style='font-size:25px;'>Es bedient Sie: " + toHtml(userInfo) + "</span>";

                $("#liveorderslist").show();
                $("#ebonqrcodearea").hide();
                $("#liveorderslist").html(txt);
        }
        
        function showEbonInfo(ebonInfo,ebonUrl) {
                $("#liveorderslist").hide();
                $("#ebonqrcodearea").show();
                if ((ebonUrl !== "") && (ebonInfo !== "")) {
                        var infoTxt = "<span style='font-size:30px;'>Abruf Ihres elektronischen Kassenbons</span>";
                        var link = ebonUrl + "/index.php?ebonref=" + ebonInfo;
                        var qrCode = '<p><img src="php/utilities/osqrcode.php?cmd=link&arg=' + link + '" style="width:200px;" />';
                        var reference = "<a href='" + link + "'>" + link + "</a>";
                        $("#ebonqrcodearea").html(infoTxt + qrCode + "<p>" + reference);
                } else {
                        $("#ebonqrcodearea").html("");
                }
        }
        
        </script>
        
        <div id="alertoverlay" onclick="alertoff();" >
                <div id="alerttext" style="margin:20px;padding:50px;"></div>
        </div>

        <div class="w3-container w3-teal">
                <div class="w3-left"><h1>Kundenansicht <img src="img/connection.png" class="connectionstatus" style="display:none;" /> <img src="img/printerstatus.png" class="printerstatus" style="display:none;" /> <img src="img/tsestatus.png" class="tsestatus" style="display:none;" /> <img src="img/tasksstatus.png" class="tasksstatus" style="display:none;" /></h1></div>
                <div class="w3-right" id="mainmenu"><h2><span class="mainmenu"><img class="mainmenu" src="php/3rdparty/images/round_menu_black_24dp.png" alt="Hauptmenü"/> Menü</span></h2><div id="mainmenulist" class="w3-container" style="display:none;">XXX</div></div>      
        </div> 
           
        <br /><br />
        
        <div class="w3-container" id="liveorderslist">
                <img src="php/3rdparty/images/ajax-loader.gif" />
        </div>
        
        <div class="w3-container" id="ebonarea">
            <div id="ebonqrcodearea">
                <img src="php/3rdparty/images/ajax-loader.gif" />
            </div>
        </div>   
        
       <br /><br />
  
        
        <div class="w3-container w3-teal w3-padding-16">
         <div class="w3-bar w3-teal">
  <div id="loggedinuser" class="w3-left">w3-left</div>
  <div id="versioninfo" class="w3-right">w3-right</div>
</div> 
        </div> 
</body>
</html>