<?php

require_once (__DIR__ . '/../dbutils.php');
require_once (__DIR__ . '/../commonutils.php');
require_once (__DIR__ . '/../roomtables.php');
require_once (__DIR__ . '/syncerbase.php');
require_once (__DIR__ . '/layouter.php');
class EbonSync extends SyncerBase {
        public function sync($pdo) {
		$url = CommonUtils::getConfigValue($pdo, 'ebonurl', "");
		if (is_null($url)) {
			return array("status" => "OK","msg" => "");
		} else {
			$url = trim($url);
			if ($url == "") {
				return array("status" => "OK","msg" => "");
			}
		}
		$code = trim(CommonUtils::getConfigValue($pdo, 'eboncode', ''));
		
		if ($code == '') {
			return array("status" => "ERROR","msg" => "eBon access code not set - stopping here for security reasons!");
		}
                
                $sql = "SELECT id,billdate FROM %bill% WHERE closingid is null AND needsebonupload=?";
                $billsToStore = CommonUtils::fetchSqlAll($pdo, $sql, array(1));
                
                $billInfos = array();
                foreach($billsToStore as $aBill) {
                        $aBillId = $aBill["id"];
                        $billDate = $aBill["billdate"];
                        $ebonInfo = Bill::getBillEbonInfoForUserLink($pdo,$aBillId);
                        
                        $billdata = (new Bill())->getBillWithIdAsTicket($pdo,$aBillId);
                        $template = file_get_contents(__DIR__ . "/../../customer/ebontemplate.txt");
                        $layoutedTemplate = Layouter::layoutTicket($template, array($billdata), 42 ,true);
                        $previewHtml = $layoutedTemplate['html'];
                        
                        $billInfos[] = array("billdate" => $billDate, "ebonreference" => $ebonInfo,"data" => $previewHtml);
                }
                
		$transferdata = array(
                    "billinfos" => $billInfos,
                    "eboncode" => $code,
		);

		$data = json_encode($transferdata);
		$transferdataBase64 = base64_encode($data);

		$retCode = $this->sendToWebsite($url, $transferdataBase64);
                
                if ($retCode["status"] == "OK") {
                        CommonUtils::execSql($pdo, "UPDATE %bill% SET needsebonupload=? WHERE closingid is null", array(0));
                }
                return $retCode;
	}
}

