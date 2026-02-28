function Grouping(set,hashFct) {
	// initialization during construction of class
	this.set = set;

	// setting by group()
	this.sortedset = [];
	
	this.group = function() {
		this.sortedset = [];
		for (var i=0;i<this.set.length;i++) {
			var anEntry = this.set[i];

			if (this.sortedset.length === 0) {
				// new value
				anEntry["count"] = 1;
				anEntry["ids"] = [ anEntry["id"]];
				this.sortedset[this.sortedset.length] = anEntry;
			} else {
				// check if the entry must be added to an existing entry
				var hashOfValueToInsert = hashFct(anEntry);
				var found = false;
				for (j=0;j<this.sortedset.length;j++) {
					var existingVal = this.sortedset[j];
					var hashOfExistingValue = hashFct(existingVal);
	
					if (hashOfValueToInsert === hashOfExistingValue) {
						existingVal["count"] = existingVal["count"] + 1;
						// now add the id
						var ids = existingVal["ids"];
						ids[ids.length] = anEntry["id"];
						found = true;
						break;
					}
				}
				if (!found) {
					// new value
					anEntry["count"] = 1;
					anEntry["ids"] = [ anEntry["id"]];
					this.sortedset[this.sortedset.length] = anEntry;
				}
			}
		}
	};

	this.outputList = function(outputFct) {
		var txt = "";
		for (var i=0;i<this.sortedset.length;i++) {
			var anEntry = this.sortedset[i];
			txt += outputFct(anEntry);
		}
		return txt;
	};
	this.outputListAdditionalParam = function(outputFct,secondParam) {
		var txt = "";
		for (var i=0;i<this.sortedset.length;i++) {
			var anEntry = this.sortedset[i];
			txt += outputFct(anEntry,secondParam);
		}
		return txt;
	};

	this.getItemsOfRow = function(rowId) {
		var anEntrySet = this.sortedset[rowId];
		var ids = anEntrySet["ids"];
		var items = [];
		for (var j=0;j<ids.length;j++) {
			var anId = ids[j];
			for (var i=0;i<this.set.length;i++) {
				var anEntry = this.set[i];
				if (anEntry.id==anId) {
					items[items.length] = anEntry;
					break;
				}
			}
		}
		return items;
	};
	
	this.popSortedEntry = function(rowId) {
		var anEntry = this.sortedset[rowId];
		var ids = anEntry["ids"];
		var id = ids.pop();
		var aSetEntry = this.popSetEntry(id);
		this.group();
		return aSetEntry;
	};

	this.popSetEntry = function(id) {
		for (var i=0;i<this.set.length;i++) {
			var anEntry = this.set[i];
			if (anEntry.id==id) {
				this.set.splice(i,1);
				return anEntry;
			}
		}
	};

	this.getSourceSet = function() {
		return this.set;
	};
	
	this.getGroupedList = function() {
		return this.sortedset;
	};
}