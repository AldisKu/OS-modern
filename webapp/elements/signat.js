function SignAT() {

	this.outputelem = null;
	
	this.getApiParams = function() {
		var apikey = $("#fiskalyapikey").val();
		var apisecret = $("#fiskalyapisecret").val();

		var data = {
			fiskalyapikey: apikey,
			fiskalyapisecret:apisecret
		};
		return data;
	};
	
	this.binding = function() {
		var instance = this;
                
                $("#registerfonbtn").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
                        var data = instance.getApiParams();
                        data['fon_participant_id'] = $("#fonparticipantid").val();
                        data['fon_user_id'] = $("#fonuserid").val();
                        data['fon_user_pin'] = $("#fonuserpin").val();
                        data['request'] = 'authfon';

                        doAjax("POST","php/contenthandler.php?module=signat&command=signatcmd",data,instance.handleAuthFonAnswer.bind(instance),null,true);
                });
                
                $("#createrksvscuidbtn").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
                        var data = instance.getApiParams();
                        data['leinumber'] = $("#rksvleinumber").val();
                        data['leiname'] = $("#rksvleiname").val();
                        data['request'] = 'createrksvscuid';

                        doAjax("POST","php/contenthandler.php?module=signat&command=signatcmd",data,instance.handleCreateRksvSCUID.bind(instance),null,true);
                });
                $("#initializerksvscubtn").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
                        var data = instance.getApiParams();
                        data['request'] = 'initializerksvscu';

                        doAjax("POST","php/contenthandler.php?module=signat&command=signatcmd",data,instance.handleInitializeRksvSCU.bind(instance),null,true);
                });
                $("#initializerksvcashregbtn").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
                        var data = instance.getApiParams();
                        data['request'] = 'initializerksvcashreg';
                        doAjax("POST","php/contenthandler.php?module=signat&command=signatcmd",data,instance.handleInitializeRksvCashReg.bind(instance),null,true);
                });
                $("#registerrksvcashregbtn").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
                        var data = instance.getApiParams();
                        data['request'] = 'registerrksvcashreg';
                        doAjax("POST","php/contenthandler.php?module=signat&command=signatcmd",data,instance.handleInitializeRksvCashReg.bind(instance),null,true);
                });
                $("#createrksvcashregidbtn").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
                        var data = instance.getApiParams();
                        data['request'] = 'createrksvcashregid';
                        doAjax("POST","php/contenthandler.php?module=signat&command=signatcmd",data,instance.handleCreateRksvCashRegId.bind(instance),null,true);
                });
                $("#decomconfiguredrksscubtn").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
                        var data = instance.getApiParams();
                        data['filter'] = 'configured';
                        data['request'] = 'decomscu';
                        doAjax("POST","php/contenthandler.php?module=signat&command=signatcmd",data,instance.handleDecomSCU.bind(instance),null,true);
                });
                $("#decomallrksscubtn").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
                        var data = instance.getApiParams();
                        data['filter'] = 'all';
                        data['request'] = 'decomscu';
                        doAjax("POST","php/contenthandler.php?module=signat&command=signatcmd",data,instance.handleDecomSCU.bind(instance),null,true);
                });
                $("#decomconfiguredcashregbtn").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
                        var data = instance.getApiParams();
                        data['filter'] = 'configured';
                        data['request'] = 'decomcashreg';
                        doAjax("POST","php/contenthandler.php?module=signat&command=signatcmd",data,instance.handleDecomCashReg.bind(instance),null,true);
                });
                $("#decomallrkscashregbtn").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
                        var data = instance.getApiParams();
                        data['filter'] = 'all';
                        data['request'] = 'decomcashreg';
                        doAjax("POST","php/contenthandler.php?module=signat&command=signatcmd",data,instance.handleDecomCashReg.bind(instance),null,true);
                });
                $("#exportdep7btn").off("click").on("click", function (e) {
			e.stopImmediatePropagation();
			e.preventDefault();
                        window.location.href = "php/contenthandler.php?module=signat&command=exportdep7"
                });
	};
        
        this.handleAuthApiAnswer = function(serverAnswer) {
                if (serverAnswer.status !== "OK") {
                        alert("Fehler: " + serverAnswer.msg);
                        $("#fiskalytoken").val("");
                        $("#fiskalyexpiretokenhours").val("");
                        $("#fiskalyexpiretokendate").val("");
                        return;
                } else {
                        $("#fiskalytoken").val(serverAnswer.msg.access_token);
                        $("#fiskalyexpiretokenhours").val(Math.round(serverAnswer.msg.access_token_expires_in / 3600,2));
                        $("#fiskalyexpiretokendate").val(serverAnswer.msg.access_token_expires_at);
                }       
        };
        
        this.handleAuthFonAnswer = function(serverAnswer) {
                if (serverAnswer.status !== "OK") {
                        $("#fonauthenticatedstatus").val("Fehler: " + serverAnswer.msg);
                        return;
                } else {
                        $("#fonauthenticatedstatus").val(serverAnswer.msg);
                }
        };
        
        this.handleCreateRksvSCUID = function(serverAnswer) {
                if (serverAnswer.status !== "OK") {
                        alert("Fehler: " + serverAnswer.msg);
                        $("#rksvscuid").val(serverAnswer.scuid);
                        $("#rksvscustate").val('');
                        $("#rksvscucertificatesn").val(serverAnswer.certsn);
                } else {
                        $("#rksvscuid").val(serverAnswer.scuid);
                        $("#rksvscustate").val('');
                        $("#rksvscucertificatesn").val('');
                        
                }
        };
        this.handleInitializeRksvSCU = function(serverAnswer) {
                if (serverAnswer.status !== "OK") {
                        alert("Fehler: " + serverAnswer.msg);
                } else {                       
                        $("#rksvscustate").val(serverAnswer.msg.state);
                }
        };
        this.handleInitializeRksvCashReg = function(serverAnswer) {
                if (serverAnswer.status !== "OK") {
                        alert("Fehler: " + serverAnswer.msg);
                } else {                       
                        $("#rksvcashregstate").val(serverAnswer.msg.state);
                }
        };
        this.handleCreateRksvCashRegId = function(serverAnswer) {
                if (serverAnswer.status !== "OK") {
                        $("#rksvcashregid").val("Fehler: " . serverAnswer.msg);
                } else {
                        $("#rksvcashregid").val(serverAnswer.msg.rksvcashregid);
                        $("#rksvcashregstate").val(serverAnswer.msg.rksvcashregstate);
                }
        };
        this.handleDecomSCU = function (serverAnswer) {
                if (serverAnswer.status !== "OK") {
                        alert("Fehler: " + serverAnswer.msg);
                } else {
                        $("#rksvscustate").val('');
                        $("#rksvcashregid").val('');
                        $("#rksvscucertificatesn").val('');
                        alert(serverAnswer.msg);
                }
        };
        this.handleDecomCashReg = function (serverAnswer) {
                if (serverAnswer.status !== "OK") {
                        alert("Fehler: " + serverAnswer.msg);
                } else {
                        $("#rksvcashregstate").val('');
                        $("#rksvcashregid").val('');
                        alert(serverAnswer.msg);
                }
        };
}