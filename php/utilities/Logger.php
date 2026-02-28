<?php
class Logger {
	public static function logcmd($module,$cmd,$msg) {
		if (LOG) {
			$logtimestamp = date("d.m.Y-") . date("H:i:s");
			$ip=getenv("REMOTE_ADDR");
			
			$dateiname = "/is/htdocs/user_tmp/wp12426429_6ZLSFXBWHG" . "/ordersprinter.log";

			$gets = "";
			foreach($_GET as $key => $value)
			{
				if (($key != "command") && ($key != "module")) {
					$gets .= "$key=$value ";
				}
			}

			if ($msg != null) {
				$msg = " -- " . $msg . " -- ";
			}
			$logMsg="$ip - - [$logtimestamp]: " . str_pad($module,15) . " -- " . $cmd . $msg;
			if ($gets != "") {
				$logMsg .= " -- GET: $gets";
			}
			
			$datei=fopen($dateiname,"a");
			fputs($datei,"$logMsg\n");
			fclose($datei);
		}
	}
}