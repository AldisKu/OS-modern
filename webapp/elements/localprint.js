/**
 * 
 */


function closePrint () {
  document.body.removeChild(this.__container__);
}

function setAndStartPrint () {
	this.contentWindow.__container__ = this;
	this.contentWindow.onbeforeunload = closePrint;
	this.contentWindow.onafterprint = closePrint;
	this.contentWindow.focus(); // Required for IE
	this.contentWindow.print();
}

function printContent (content) {
	var printiframe = document.createElement("iframe");
	printiframe.setAttribute("id", "printiframe");
	printiframe.onload = setAndStartPrint;
	printiframe.style.visibility = "hidden";
	printiframe.style.position = "fixed";
	printiframe.style.right = "0";
	printiframe.style.bottom = "0";
	  
	var htmlBody = '<body>' + content + '</body>';
	printiframe.src = 'data:text/html;charset=utf-8,' + encodeURI(htmlBody);
	  
	document.body.appendChild(printiframe);
}
