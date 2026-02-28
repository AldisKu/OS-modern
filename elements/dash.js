function Dash() {
	
	this.backColors = [
		'rgba(255, 99, 132, 0.2)',
		'rgba(54, 162, 235, 0.2)',
		'rgba(255, 206, 86, 0.2)',
		'rgba(75, 192, 192, 0.2)',
		'rgba(153, 102, 255, 0.2)',
		'rgba(255, 159, 64, 0.2)'
	];
	
	this.borderColors = [
		'rgba(255,99,132,1)',
		'rgba(54, 162, 235, 1)',
		'rgba(255, 206, 86, 1)',
		'rgba(75, 192, 192, 1)',
		'rgba(153, 102, 255, 1)',
		'rgba(255, 159, 64, 1)'
	];
	
	this.createColsArray = function(inputColors,count) {
		var cols = [];
		for (var i=0;i<count;i++) {
			cols[cols.length] = inputColors[i % inputColors.length];
		}
		return cols;
	};

	this.createTablesReport = function(slotid,tables,currency) {
		var tablestotal = tables.tablestotal;
		var opentables = tables.opentables;
		var emptyTables = tablestotal - opentables;
		var sum = tables.sum;
		
		$(slotid + " h1").html('Leere/abgerechnete Tische','Offene Tische');
		
		var canvas = $(slotid + " canvas");
		
		var backCols = this.createColsArray(this.backColors,2);
		
		Chart.defaults.global.defaultFontColor = '#aaa';
		Chart.defaults.global.defaultColor = '#aaa';
		
		var myChart = new Chart(canvas, {
		    type: 'pie',
		    
		    data: {
			labels: ['Leere/abgerechnete Tische','Offene Tische'],
			datasets: [{
			  backgroundColor: backCols,
			  data: [emptyTables,opentables],
			}]
		      },
		      options: {
			      animation: false,
			      
			      legend: {
					display: true,
					labels: {
					    fontColor: 'rgb(200, 200, 200)'
					}
				}
		      }
		});
	};
	
	this.createDurationReport = function(slotid,hourdata,currency) {
		$(slotid + " h1").html('Verweildauer (Minuten)');
		this.createDashReport(slotid,hourdata,'Verweildauer (Minuten)',"hour","average",'line','Dauer','Uhrzeit');
	};
	
	this.createMonthReport = function(slotid,monthdata,currency) {
		$(slotid + " h1").html('Monatseinnahme (' + currency + ")");
		this.createDashReport(slotid,monthdata.content,'Monatseinnahme (' + currency + ")","iter","sum",'line','Summe','Tag');
	};
	
	this.createDayReport = function(slotid,monthdata,currency) {
		$(slotid + " h1").html('Tagesseinnahmen (' + currency + ")");
		this.createDashReport(slotid,monthdata.content,'Tageseinnahmen (' + currency + ")","iter","sum",'line','Summe','Stunde');
	};
	
	this.createUserCash = function(slotid,usersums,currency) {
		$(slotid + " h1").html('Kellnereinnahmen (' + currency + ")","iter","sum",'bar');
		this.createDashReport(slotid,usersums.content,'Einnahmen (' + currency + ")","iter","sum",'bar','Summe','Benutzer');
	};
	
	this.createProdCountReport = function(slotid,prodcount,currency) {
		$(slotid + " h1").html('Anzahl verkaufte Produkte','longname','value','bar');
		this.createDashReport(slotid,prodcount,'Anzahl verkaufte Produkte','longname','value','bar', 'Anzahl','Produkt');
	};

	this.createProdSumReport = function(slotid,prodcount,currency) {
		$(slotid + " h1").html('Umsatz verkaufte Produkte (' + currency + ')','longname','value','bar');
		this.createDashReport(slotid,prodcount,'Umsatz verkaufte Produkte (' + currency + ')','longname','value','bar', 'Summe','Produkt');
	};

	this.createDashReport = function(slotid,prodcount,label,nameLabel,valueLabel,chartType, ylabel, xlabel) {
		
		var values = [];
		var names = [];
		for (var i=0;i<prodcount.length;i++) {
			var anEntry = prodcount[i];
			values[values.length] = anEntry[valueLabel];
			names[names.length] = anEntry[nameLabel];
		};
		
		// longname, prodcount
		var canvas = $(slotid + " canvas");
		var backCols = this.createColsArray(this.backColors,prodcount.length);
		var borderCols = this.createColsArray(this.borderColors,prodcount.length);
		
		Chart.defaults.global.defaultFontColor = '#aaa';
		Chart.defaults.global.defaultColor = '#aaa';

		var myChart = new Chart(canvas, {
		    type: chartType,
		    
		    data: {
			labels: names,
			datasets: [{
			    label: label,
			    data: values,
			    backgroundColor: backCols,
			    borderColor: borderCols,
			    borderWidth: 1
			}]
		    },
		    options: {
			scales: {
				yAxes: [{
				    ticks: {
					beginAtZero:true,
					fontColor: "#CCC", // this here
				    },
				    scaleLabel: {
					    display: true,
					    labelString: ylabel
				    }
				}],
				xAxes:[{
				    gridLines:{
				      color:"rgba(255,255,255,0.5)",
				      zeroLineColor:"rgba(255,255,255,0.5)"
				    },
				    scaleLabel: {
					    display: true,
					    labelString: xlabel
				    }
				}],
			},			
			
			animation: false,
			legend: {
				display: false
			},
			
		    }
		});
	}
}