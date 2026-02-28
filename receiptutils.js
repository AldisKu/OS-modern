var P_SUM = ["Summe:","Sum:","Todo:"];
var P_TOTAL = ["Total","Total","Total"];
var P_MWST = ["MwSt","Tax","IVA"];
var P_NETTO = ["Netto","Net","Neto"];
var P_BRUTTO = ["Brutto","Gross","Bruto"];
var P_ID = ["Id:","Id:","Id:"];
var P_TABLE = ["Tisch:","Table:","Mesa:"];
var P_WAITER = ["Es bediente Sie:", "Waiter:", "Camarero:"];
var P_NO = ["Anz.", "No.", "Nú."];
var P_DESCR = ["Beschreibung","Description","Descripción"];
var P_PRICE = ["Preis","Price","Precio"];



function createReceiptFooterFromDbTaxes(lang, billid, companyInfo,sum,taxes,decPoint,sn,uid,version,systemid,tseinfo,fiskalyContent) {

	var priceStyle = 'style="border: solid black 0px;padding: 3px;text-align:right;vertical-align:bottom;"';
	var infoStyle = 'style="text-align:center;vertical-align:bottom;"';
	
	var emptyLine = '<tr><td colspan=6>&nbsp</tr>';
	var footer = emptyLine;
	
	footer += '<tr><td colspan=2>' + P_MWST[lang] + '%		<td ' + priceStyle + '>' + P_MWST[lang] + '<td ' + priceStyle + '>' + P_NETTO[lang] + '<td ' + priceStyle + ' colspan=2>' + P_BRUTTO[lang] + '</tr>';

	for (var i=0;i<taxes.length;i++) {
		var tax = taxes[i];
		footer += "<tr><td colspan=2>" + tax.tax;
		footer += "<td " + priceStyle + ">" + tax.mwst.replace(".",decPoint);
		footer += "<td " + priceStyle + ">" + tax.netto.replace(".",decPoint);
		footer += "<td colspan=2 " + priceStyle + ">" + tax.brutto.replace(".",decPoint);
		footer += "</tr>";
	}
	
	footer += emptyLine;
	footer += '<tr><td>	&nbsp;	<td colspan=3>' + P_SUM[lang] + '<td id="priceinreceipt2" class="totalpriceinreceipt" ' + priceStyle + ' colspan=2>' + sum.toFixed(2).replace(".",decPoint) + '</td></tr>';
	footer += emptyLine;
	
	footer += '<tr><td ' + infoStyle + ' colspan=6><center>&nbsp;<br>';
	footer += toHtml(companyInfo).replace(/(?:\r\n|\r|\n)/g, '<br />');
	footer += '</center></tr><br>';
	
	footer += "<tr><td colspan=6><center><img src='php/contenthandler.php?module=bill&command=billqrcode&billid=" + billid + "' /></center></tr>";

	footer += tseinfo;
        footer += fiskalyContent;

	footer += "<tr><td colspan=6>Kassen-ID: " + toHtml(systemid).replace(/(?:\r\n|\r|\n)/g, '<br />') + "</tr>";
	footer += "<tr><td colspan=6>Bonerstellung mit Version: " + toHtml(version).replace(/(?:\r\n|\r|\n)/g, '<br />') + "</tr>";
	
	return footer;	
}



/**
 * Generate the product part of a receipt on base of html table array elements
 * 
 * @param decPoint
 * @param entryListForReceipt
 * @returns {String}
 */
function generateProdPart(decPoint,entryListForReceipt) {
	var index=0;
	tablecontent = '';
	for (index=0;index<entryListForReceipt.length;index++) {
		var anEntry = entryListForReceipt[index];
		
		var count = parseInt(anEntry[0]);
		var prodEntry = anEntry[1];
		var productname = prodEntry.longname;
		var price = prodEntry.price;
		var togo = prodEntry.togo;
		var pricelevelname = prodEntry.pricelevelname;
		
		tablecontent += generateOneProdLine(count,productname,price,pricelevelname,decPoint,togo);
	}
	return tablecontent;	
}



/**
 * Generate HTML output on base of db content
 */
function generateHtmlBillFromScratch(lang,billentry,currency,hosthtml,decPoint) {
	var table = '<table id="receiptpart" class="receipttable" border=1 style="table-layout: fixed;">';
	
	var billid = billentry.id;
	var billuid = billentry.billuid;
        var paymentname = billentry.paymentname;

	var overallinfo = billentry.billcontent.billoverallinfo;
	var companyInfo = overallinfo.companyinfo;
	var sn = overallinfo.sn;
	var uid = overallinfo.uid;
	var version = overallinfo.version;
	var systemid = overallinfo.systemid;
	var prods = billentry.billcontent.products;
	
	var username = overallinfo.username;
	var tablename = overallinfo.table;
	var guestinfo = overallinfo.guestinfo;

	var header = genCreateReceiptHeader(lang,billuid,tablename,username,currency,guestinfo,paymentname);
	
	var products = generateProdPartByDbContent(decPoint,prods);
	
	var sum = parseFloat(overallinfo.brutto);

	var tseinfo = "";
        
        let fiskalyContent = "";
        if (overallinfo.austria == 1) {
                let fiskalysigned = overallinfo.fiskalysigned;
                let fiskalycashregserialno = overallinfo.fiskalycashregserialno;
                let fiskalyreceiptnumber = overallinfo.fiskalyreceiptnumber;
                let fiskalyqrcodeValue = overallinfo.fiskalyqrcode;
                let fiskalyqrcode = '<p><img src="php/utilities/osqrcode.php?cmd=link&arg=' + fiskalyqrcodeValue + '" style="width:300px;" />';
                let fiskalytimesignature = overallinfo.fiskalytimesignature;

                if (fiskalysigned == 0) {
                        fiskalyContent += "<tr><td colspan=6>Sicherheitseinrichtung ausgefallen";
                } else {
                        fiskalyContent += "<tr><td colspan=6>RKSV-Beleg: " + fiskalyreceiptnumber;
                        fiskalyContent += "<tr><td colspan=6>RKSV-Datum: " + fiskalytimesignature;
                        fiskalyContent += "<tr><td colspan=6>RKSV-Kassen-ID: " + fiskalycashregserialno;
                        fiskalyContent += "<tr><td>" + fiskalyqrcode;
                }
        }
        
	if (overallinfo.tsestatus == 1) {
		tseinfo += "<tr><td colspan=6>TSE-Start: " + overallinfo.startlogtime + "</tr>";
		tseinfo += "<tr><td colspan=6>TSE-Ende: " + overallinfo.logtime + "</tr>";
		tseinfo += "<tr><td colspan=6>TSE-Trans.-Nr: " + overallinfo.transnumber + "</tr>";
		tseinfo += "<tr><td colspan=6>TSE-Sig.-Z.: " + overallinfo.sigcounter + "</tr>";
		tseinfo += "<tr><td colspan=6>TSE-Signatur: " + overallinfo.tsesignature + "</tr>";
		tseinfo += "<tr><td colspan=6>TSE-Seriennr.: " + overallinfo.tseserialno + "</tr>";
	}
	var footer = createReceiptFooterFromDbTaxes(lang, billid, companyInfo,sum,billentry.billcontent.taxes,decPoint,sn,uid,version,systemid,tseinfo,fiskalyContent); 

	var receipt = table + header + products + footer;
	if (overallinfo.host == 1) {
		receipt += hosthtml;
	}
	
	receipt += "</table>";
	return receipt;
}
