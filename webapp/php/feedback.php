<?php
require_once ('dbutils.php');
require_once ('utilities/Emailer.php');

class Feedback {
	var $dbutils;
	function __construct() {
		$this->dbutils = new DbUtils();
	}
	
	function handleCommand($command) {
		if (!$this->isUserAlreadyLoggedInForPhp()) {
			echo json_encode(array("status" => "ERROR","msg" => "Fehler: Benutzer nicht eingeloggt!"));
			return;
		}
		
		if ($command == 'sendMail') {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$this->sendMail($pdo,$_POST['role'],$_POST['topic'],$_POST['email'],$_POST['tel'],$_POST['allowSendRights'],$_POST['content']);
		} else if ($command == 'getErrorLog') {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$errorlog = $this->getErrorLog($pdo);
			echo json_encode($errorlog);
		} else if ($command == 'sendErrorLog') {
			$pdo = DbUtils::openDbAndReturnPdoStatic();
			$answer = self::sendErrorLog($pdo,$_POST['errorlog'],$_POST['contactinfo'],$_POST['remark']);
			echo $answer;
		} else {
			echo "Kommando nicht unterstuetzt.";
		}
	}
	
	function isUserAlreadyLoggedInForPhp() {
		if(session_id() == '') {
			session_start();
		}
		if (!isset($_SESSION['angemeldet']) || !$_SESSION['angemeldet']) {
			return false;
		} else {
			return true;
		}
	}
	
	function spamcheck($field) {
		// Sanitize e-mail address
		$field=filter_var($field, FILTER_SANITIZE_EMAIL);
		// Validate e-mail address
		if(filter_var($field, FILTER_VALIDATE_EMAIL)) {
			return TRUE;
		} else {
			return FALSE;
		}
	}
	
	function sqlresult($pdo,$sql,$sqlval) {
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute();
		$row =$stmt->fetchObject();
		if ($row != null) {
			return($row->$sqlval);
		} else {
			return 0;
		}
	}
	function getdbinfo($pdo) {	
		$info = "\n\nWaiting print jobs:\n";
		// workprintjobswaiting
		$foodjobs = $this->sqlresult($pdo,"select count(id) as number from %printjobs% where type=1","number");
		$drinkjobs = $this->sqlresult($pdo,"select count(id) as number from %printjobs% where type=2","number");
		$payjobs = $this->sqlresult($pdo,"select count(id) as number from %printjobs% where type=3","number");
		
		$info .= "Fs: $foodjobs\n";
		$info .= "Ds: $drinkjobs\n";
		$info .= "Rs: $payjobs\n\n";
		
		// db sizes
		$info .= $this->getDatabaseSizes($pdo);
		return $info;
	}
	
	function getDatabaseSizes($pdo) {
		$sql = 'SELECT table_schema "Data Base Name",
    			sum( data_length + index_length ) / 1024 / 1024 "Data Base Size in MB",
    			sum( data_free )/ 1024 / 1024 "Free Space in MB"
				FROM information_schema.TABLES
				GROUP BY table_schema';
		$pdo = $this->dbutils->openDbAndReturnPdo();
		$stmt = $pdo->prepare($this->dbutils->resolveTablenamesInSqlString($sql));
		$stmt->execute();
		$result = $stmt->fetchAll();
		$dbInfo = "DB-info:\n";
		
		foreach($result as $row) {
			$dbInfo .= "DB '$row[0]', db (MB): $row[1], free (MB): $row[2]\n";
		}
		return $dbInfo;
	}
	
	function sendMail($pdo,$role,$topic,$email,$tel,$allowSendRights,$content) {

		$rights = "Keine Rechteinformation";
		$version = CommonUtils::getConfigValue($pdo, 'version', '');

		if ($allowSendRights) {
			$rights = "UID=" . $_SESSION['userid'] . "\n";
			$rights .= "UN=" . $_SESSION['currentuser'] . "\n";
			$rights .= " RA=" . ($_SESSION['is_admin'] ? "1" : "0") . "\n";
			$rights .= " RW=" . ($_SESSION['right_waiter'] ? "1" : "0") . "\n";
			$rights .= " RPay=" . ($_SESSION['right_paydesk'] ? "1" : "0") . "\n";
			$rights .= " RB=" . ($_SESSION['right_bill'] ? "1" : "0") . "\n";
			$rights .= " RProd = " . ($_SESSION['right_products'] ? "1" : "0") . "\n";
			$rights .= " RM=" . ($_SESSION['right_manager'] ? "1" : "0");
			$rights .= $this->getdbinfo($pdo);
		}
		$server = $_SERVER['HTTP_USER_AGENT'];
		$msg = "\nEmail:$email\nTel.:$tel\nNachricht:$content\nRolle:$role\nRechte:$rights\nServer:$server\n\nVersion:$version\n";

		$version = CommonUtils::getConfigValue($pdo, 'version', 'no-version');
		$ok = $this->sendFeedbackToServer($topic, $msg, $version);

		echo json_encode($ok);
	}
	
	function sendFeedbackToServer($topic, $msg, $version) {
		$cmd = '';
		$fct = 'Feedback-Form';
		$xhr = $msg;
		$errormsg = $topic;
		$status = '';
		$phpversion = phpversion();

		$arr = array("cmd" => $cmd, "fct" => $fct, "xhr" => $xhr, "errormsg" => $errormsg, "status" => $status, "version" => $version, "phpversion" => $phpversion);
		
		$url = "http://www.ordersprinter.de/debug/save.php?cmd=save";
		
		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL,$url);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 5);
		$query = http_build_query($arr);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
           
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$server_output = curl_exec ($ch);

		if($server_output === false)
		{
			$msg = 'Curl-Fehler: ' . curl_error($ch);
			curl_close ($ch);
			return array("status" => "ERROR","msg" => $msg);
			
		} else {
			curl_close ($ch);
			return array("status" => "OK");
		}
	}
	
	private function getErrorLog($pdo) {

		$showErrorLog = CommonUtils::getConfigValue($pdo, "showerrorlog", 0);
		if ($showErrorLog != 1) {
			return array("status" => "ERROR", "msg" => "Benutzer dürfen entsprechend der eingestellten Konfiguration nicht auf das Error.log des Webservers zugreifen.");
		}
		
		$batchFile = "";
		if (stripos(PHP_OS, 'win') === 0) {
			$batchFile = "errorlog_windows.bat";
		} elseif (stripos(PHP_OS, 'linux') === 0) {
			$batchFile = "errorlog_linux.sh";
		}
		
		if ($batchFile == "") {
			return array("status" => "ERROR", "msg" => "Betriebssystem nicht erkannt oder nicht unterstützt: " . PHP_OS);
		}
		
		$batchOutput = shell_exec("utilities/$batchFile");
		if ($batchOutput == "") {
			return array("status" => "ERROR", "msg" => "Error.log leer oder keine Lesezugriff.");
		}
		
		$maxSize = 20 * 1024;
		if (strlen($batchOutput) > $maxSize) {
			$batchOutput = substr($batchOutput, 0-$maxSize);
		}

		$companyinfo = CommonUtils::getConfigValue($pdo, "companyinfo", "");
		$ret = array("log" => $batchOutput, "companyinfo" => $companyinfo);
		return array("status" => "OK", "msg" => $ret);
	}
	
	private static function sendErrorLog($pdo, $errorlog, $contactinfo, $remark) {
		$companyInfo = CommonUtils::getConfigValue($pdo, 'companyinfo', '');
		$version = CommonUtils::getConfigValue($pdo, 'version', '');
		$phpversion = phpversion();

		$arr = array(
		    "errorlog" => $errorlog,
		    "contactinfo" => $contactinfo,
		    "remark" => $remark,
		    "companyInfo" => $companyInfo,
		    "version" => $version,
		    "phpversion" => $phpversion);

		$url = "http://www.ordersprinter.de/debug/save.php?cmd=saveerrorlog";

		$query = http_build_query($arr);
		$opts = array(
		    'http' => array(
			'header' => "Content-Type: application/x-www-form-urlencoded\r\n" .
			"Content-Length: " . strlen($query) . "\r\n" .
			"User-Agent:MyAgent/1.0\r\n",
			'method' => 'POST',
			'content' => $query
		    )
		);

		$context = stream_context_create($opts);

		$ret = file_get_contents($url, false, $context);

		return $ret;
	}

}
