<?php
require_once ('dbutils.php');

class Rksv {
	
	private static $TAX_NORMAL = 1;
	private static $TAX_ERM1 = 2;
	private static $TAX_ERM2 = 3;
	private static $TAX_BESONDERS = 4;
        
        const CBIRD = 1;
        const QRK_R2B = 2;
        const QRK_RECEIPT = 3;
        const FISKALY_SIGN_AT = 4;
        
        const RECEIPT_TYPE_NORMAL = 'NORMAL';
        const RECEIPT_TYPE_CANCELLATION = 'CANCELLATION';
        
        public function createStandardV1Schema($pdo,array $queueIds,string $receiptType):array {
                $commonUtils = new CommonUtils();
                $version = CommonUtils::getConfigValue($pdo, 'version', 'undefined version');
                $currentPriceLevelId = ($commonUtils->getCurrentPriceLevel($pdo))['id'];
                $amountsPerVatRate = array();
                $lineItems = array();
                
                if (count($queueIds) > 0) {
                                
                        $sqlTaxPart = "CASE WHEN Q.taxaustria='0' THEN 'ZERO' WHEN Q.taxaustria='1' THEN 'STANDARD' WHEN Q.taxaustria='2' THEN 'REDUCED_1' WHEN Q.taxaustria='3' THEN 'REDUCED_2' WHEN Q.taxaustria='4' THEN 'SPECIAL' ELSE 'STANDARD' END";
                        $sqlQuantityPart = "Q.unitamount";
                        $sqlArticleTextPart = "PN.name";


                        $sqlPriceDueToPriceLevel = "P.priceA";
                        if ($currentPriceLevelId == 2) {
                                $sqlPriceDueToPricelevel = "P.priceB";
                        } else if ($currentPriceLevelId == 3) {
                                $sqlPriceDueToPricelevel = "P.priceC";
                        }
                        $sqlDivideTotalPrice = "ROUND(Q.price / unitamount,2)";
                        $sqlPricePerUnit = "CASE WHEN pricechanged IS NULL THEN $sqlPriceDueToPriceLevel ELSE $sqlDivideTotalPrice END";

                        $sql = "SELECT '1.00' as amount,"
                                . "($sqlTaxPart) as vatrate,"
                                . "($sqlQuantityPart) as quantity,"
                                . "($sqlArticleTextPart) as articlename,"
                                . "($sqlPricePerUnit) as priceperunit "
                                . "FROM %queue% Q,%prodnames% PN,%products% P WHERE Q.id in (" . implode(',',$queueIds) . ") AND PN.id=Q.prodnameid AND Q.productid=P.id";

                        $items = CommonUtils::fetchSqlAll($pdo, $sql);
                        $sign = '';
                        if ($receiptType == self::RECEIPT_TYPE_CANCELLATION) {
                                $sign = '-';
                        }
                        foreach($items as $anItem) {
                                $amountsPerVatRate[] = array('vat_rate' => $anItem['vatrate'],'amount' => $anItem['amount']);
                                $lineItems[] = array('quantity' => $anItem['quantity'],'text' => $anItem['articlename'],'price_per_unit' => $sign . $anItem['priceperunit']);
                        }
                }
                $standardV1 = array('standard_v1' => array('amounts_per_vat_rate' => $amountsPerVatRate,'line_items' => $lineItems));
                $metadata = array("Kassensoftware" => "OrderSprinter","Version" => $version);
                $completeContent = array('receipt_type' => $receiptType,'schema' => $standardV1,'metadata' => $metadata);
                return $completeContent;
        }
        
	private static function checkEnvironment($pdo) {
		$isAustria = CommonUtils::getConfigValue($pdo, "austria", 0);
		if ($isAustria != 1) {
			return array("status" => "OK");
		}
		
		$rksvserver = CommonUtils::getConfigValue($pdo, "rksvserver", null);
		if (is_null($rksvserver)) {
			return array("status" => "ERROR","msg" => "No RKSV server configured");
		}
		
		$paydeskid = CommonUtils::getConfigValue($pdo, "paydeskid", null);
		if (is_null($paydeskid) || ($paydeskid == '')) {
			return array("status" => "ERROR","msg" => "No Paydesk ID configured");
		}
		
		if (!extension_loaded("curl")) {
			return array("status" => "ERROR","msg" => "PHP curl extension is missing");
		}
		return array("status" => "OK");
	}
	
	public static function doStartBeleg($pdo,$billid,$billdate) {
		$envStatus = self::checkEnvironment($pdo);
		if ($envStatus["status"] != "OK") {
			return $envStatus;
		}
		$rksvserver = CommonUtils::getConfigValue($pdo, "rksvserver", null);
		$paydeskid = CommonUtils::getConfigValue($pdo, "paydeskid", null);
		
		$myvars = 'paydeskid=' . $paydeskid;
		$myvars .= '&billid=' . $billid;
		$myvars .= '&billdate=' . $billdate;
		$myvars .= '&command=startbeleg';
		
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_VERBOSE, true);
		curl_setopt( $ch, CURLOPT_URL, $rksvserver);
		curl_setopt( $ch, CURLOPT_POST, 1);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $myvars);
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt( $ch, CURLOPT_HEADER, 0);
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);

		try {
			$response = curl_exec($ch);
		} catch (Exception $ex) {
			return array("status" => "ERROR","msg" => "RKSV Server call exception: " . $ex->getMessage());
		}
	}
	
	public static function signBill($pdo,$billid) {
		$envStatus = self::checkEnvironment($pdo);
		if ($envStatus["status"] != "OK") {
			return $envStatus;
		}
		
		$rksvserver = CommonUtils::getConfigValue($pdo, "rksvserver", null);
		$paydeskid = CommonUtils::getConfigValue($pdo, "paydeskid", null);
		
		$sql = "SELECT billdate,brutto FROM %bill% WHERE id=?";
		$billrow = CommonUtils::getRowSqlObject($pdo, $sql, array($billid));
		$billdate = $billrow->billdate;
		
		$sql = "SELECT SUM(price) as sumprice FROM %queue% WHERE billid=? AND taxaustria=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		
		$stmt->execute(array($billid,self::$TAX_NORMAL));
		$taxnormalsum = $stmt->fetchObject()->sumprice;
		$taxnormalsum = (is_null($taxnormalsum) ? '0.00' : $taxnormalsum);
		
		$stmt->execute(array($billid,self::$TAX_ERM1));
		$taxerm1sum = $stmt->fetchObject()->sumprice;
		$taxerm1sum = (is_null($taxerm1sum) ? '0.00' : $taxerm1sum);
		
		$stmt->execute(array($billid,self::$TAX_ERM2));
		$taxerm2sum = $stmt->fetchObject()->sumprice;
		$taxerm2sum = (is_null($taxerm2sum) ? '0.00' : $taxerm2sum);
		
		$stmt->execute(array($billid,self::$TAX_BESONDERS));
		$taxbessum = $stmt->fetchObject()->sumprice;
		$taxbessum = (is_null($taxbessum) ? '0.00' : $taxbessum);
		
		$myvars = 'paydeskid=' . $paydeskid;
		$myvars .= '&taxnormalsum=' . $taxnormalsum;
		$myvars .= '&taxerm1sum=' . $taxerm1sum;
		$myvars .= '&taxerm2sum=' . $taxerm2sum;
		$myvars .= '&taxbessum=' . $taxbessum;
		$myvars .= '&billid=' . $billid;
		$myvars .= '&billdate=' . $billdate;

		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_VERBOSE, true);
		curl_setopt( $ch, CURLOPT_URL, $rksvserver);
		curl_setopt( $ch, CURLOPT_POST, 1);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, $myvars);
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt( $ch, CURLOPT_HEADER, 0);
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);

		try {
			$response = curl_exec($ch);
		} catch (Exception $ex) {
			return array("status" => "ERROR","msg" => "RKSV Server call exception: " . $ex->getMessage());
		}
	}
	
	
}

