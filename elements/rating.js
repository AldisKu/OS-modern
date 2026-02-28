function Rating (day,serviceArr,kitchenArr,total) {
    this.day = day;
    this.service = serviceArr;
    this.kitchen = kitchenArr;
    this.total = total;
}
 
Rating.prototype.getServiceInfo = function() {
	var good = "<img src=img/green.png style='height:20px;width:" + this.service[0] + "%;' />";
	var ok = "<img src=img/yellow.png style='height:20px;width:" + this.service[1] + "%;' />";
	var bad = "<img src=img/red.png style='height:20px;width:" + this.service[2] + "%;' />";
	var nothing = "<img src=img/gray.png style='height:20px;width:" + this.service[3] + "%;' />";
	
	return good + ok + bad + nothing;
};

Rating.prototype.getKitchenInfo = function() {
	var good = "<img src=img/green.png style='height:20px;width:" + this.kitchen[0] + "%;' />";
	var ok = "<img src=img/yellow.png style='height:20px;width:" + this.kitchen[1] + "%;' />";
	var bad = "<img src=img/red.png style='height:20px;width:" + this.kitchen[2] + "%;' />";
	var nothing = "<img src=img/gray.png style='height:20px;width:" + this.kitchen[3] + "%;' />";
	
	return good + ok + bad + nothing;
};

Rating.prototype.getTotal = function() {
	return this.total;
};