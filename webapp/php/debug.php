<?php

// forward of message to server

require_once ('dbutils.php');
if (isset($_POST["cmd"])) {
	// error message as it was sent from client
	$cmd = $_POST["cmd"];
	$fct = $_POST["fct"];
	$xhr = $_POST["xhr"];
	$errormsg = $_POST["errormsg"];
	$status = $_POST["status"];
	$phpversion = phpversion();

	if (strlen($cmd) > 150) {
		$cmd = substr($cmd, 0,149);
	}
	if (strlen($xhr) > 900) {
		$xhr = substr($xhr, 0,899);
	}
	if (strlen($fct) > 100) {
		$fct = substr($fct, 0,99);
	}
	if (strlen($errormsg) > 150) {
		$errormsg = substr($errormsg, 0,149);
	}
	if (strlen($status) > 150) {
		$status = substr($status, 0,149);
	}
	$version = "2.9.12";
	
	$arr = array("cmd" => $cmd,"fct" => $fct, "xhr" => $xhr,"errormsg" => $errormsg,"status" => $status,"version" => $version,"phpversion" => $phpversion);
} else {
	return;
}

$url = "http://www.ordersprinter.de/debug/save.php?cmd=save";

$query = http_build_query($arr);
$opts = array(
		'http'=>array(
				'header' => "Content-Type: application/x-www-form-urlencoded\r\n".
					"Content-Length: ".strlen($query)."\r\n".
					"User-Agent:MyAgent/1.0\r\n",

				'method'  => 'POST',
				'content' => $query
		)
);

$context = stream_context_create($opts);

$ret = @file_get_contents($url, false, $context);

echo $ret;
