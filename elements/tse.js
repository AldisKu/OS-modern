function TSE() {

	this.outputelem = null;
	
	this.getTseParams = function() {
		var tseurl = $("#tseurl").val();
		var tsepass = $("#tsepass").val();
		var tseclientid = $("#tseclientid").val();
		var tsepin = $("#tsepin").val();
		var tsepuk = $("#tsepuk").val();
		var tsecredseed = $("#tsecredseed").val();

		var data = {
			url: tseurl,
			pass: tsepass,
			clientid: tseclientid,
			pin: tsepin,
			puk: tsepuk,
			credseed: tsecredseed
		};
		return data;
	};
	
	this.binding = function(outputelem) {
		var instance = this;
		this.outputelem = outputelem;
		
		$("#dotsesetup").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
                        setTimeout(function(){
                                $(instance.outputelem).val("Ersteinrichtung der TSE in Ausführung:...\n");
                        },250);
			var data = instance.getTseParams();
                        
                        let pinparts = data.pin.split(",");
                        if (pinparts.length != 5) {
                                alert("Die PIN muss aus 5 Zahlen bestehen");
                                return;
                        }
                        for (let i=0;i<5;i++) {
                                if ((pinparts[i] < 1) || (pinparts[i] > 255)) {
                                        alert("Die Zahlen der PIN müssen im Bereich von 1-255 sein!");
                                        return;
                                }
                        }
                        
			data["request"]= "setup";
			doAjax("POST","php/contenthandler.php?module=tse&command=tsecmd",data,instance.tseAnswerToOutputTextArea.bind(instance),null,true);
		});
		
		$("#submittsecmd").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
			
			var request = $("#tsecmdsel").val();
			if (request === "decom") {
				var answer = confirm("TSE wirklich stillegen?");
				if (!answer) {
					return;
				}
			}
                        if (request === "userunblock") {
				var answer = confirm("Mit der PUK wird eine neue PIN gesetzt. Achtung: Nicht mehrfach mit einer falschen PUK verwenden! Unblocking fortsetzen");
				if (!answer) {
					return;
				}
			}
			if (request === "exportdownload") {
				var tseData = instance.getTseParams();
				postForm('php/contenthandler.php?module=tse&command=tsecmd', 
						{
								pass: tseData.pass,
								pin: tseData.pin.split(","),
								puk: tseData.puk.split(","),
								clientid: "nothing",
								request: "exportdownload"	
						}
				);
			} else if (request === "exportdownloadlastyear") {
				var tseData = instance.getTseParams();
				postForm('php/contenthandler.php?module=tse&command=tsecmd', 
						{
								pass: tseData.pass,
								pin: tseData.pin.split(","),
								puk: tseData.puk.split(","),
								clientid: "nothing",
								request: "exportdownloadlastyear"	
						}
				);        
			} else {
                                setTimeout(function(){
					$(instance.outputelem).val('Kommando in Ausführung: ' + request);
				},250);
				

				var data = instance.getTseParams();
				data["request"]= request;

				doAjax("POST","php/contenthandler.php?module=tse&command=tsecmd",data,instance.tseAnswerToOutputTextArea.bind(instance),null,true);
			}
		});
	};
	
	this.tseAnswerToOutputTextArea = function(answer) {
                var elemName = this.outputelem;
                setTimeout(function(){
                        var existingText = $(elemName).val();
                        var newText = existingText + "\n\nErgebnis:\n" + answer.msg;
                        if (answer.status !== "OK") {
                                newText = existingText + "\n\nFEHLER:\n" + answer.msg;
                        }
                        $(elemName).val(newText);
                },500);
	};
}