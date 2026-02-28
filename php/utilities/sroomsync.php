<?php
require_once (__DIR__ . '/../dbutils.php');
require_once (__DIR__ . '/../commonutils.php');
require_once (__DIR__ . '/../roomtables.php');
require_once (__DIR__ . '/syncerbase.php');
class SRoomSync extends SyncerBase {	
        public function updateWorkload($pdo) {
                $url = CommonUtils::getConfigValue($pdo, 'sroomurl', "");
		if (is_null($url)) {
			return array("status" => "OK","msg" => "");
		} else {
			$url = trim($url);
			if ($url == "") {
				return array("status" => "OK","msg" => "");
			}
		}
		$sroomcode = trim(CommonUtils::getConfigValue($pdo, 'sroomcode', ''));
		
		if ($sroomcode == '') {
			return array("status" => "ERROR","msg" => "Showroom access code not set - stopping here for security reasons!");
		}
                $workfload = Roomtables::getWorkload($pdo);
                $transferdata = array(
                    "sroomcode" => $sroomcode,
                    "workload" => $workfload,
                    "updateworkload" => "1"
		);

		$data = json_encode($transferdata);
		$transferdataBase64 = base64_encode($data);

                return $this->sendToWebsite($url, $transferdataBase64);
        }
        
	public function sync($pdo) {
		$url = CommonUtils::getConfigValue($pdo, 'sroomurl', "");
		if (is_null($url)) {
			return array("status" => "OK","msg" => "");
		} else {
			$url = trim($url);
			if ($url == "") {
				return array("status" => "OK","msg" => "");
			}
		}
		$sroomcode = trim(CommonUtils::getConfigValue($pdo, 'sroomcode', ''));
		
		if ($sroomcode == '') {
			return array("status" => "ERROR","msg" => "Showroom access code not set - stopping here for security reasons!");
		}
                
                $decpoint = CommonUtils::getConfigValue($pdo, 'decpoint', '.');
                $currency = CommonUtils::getConfigValue($pdo, 'currency', '');
                
                $sroomtitle = CommonUtils::getConfigValue($pdo, 'sroomtitle', '');
                $sroomimpressum = CommonUtils::getConfigValue($pdo, 'sroomimpressum', '');
                $sroomprivacy = CommonUtils::getConfigValue($pdo, 'sroomprivacy', '');
                $sroomcss = CommonUtils::getConfigValue($pdo, 'sroomcss', '');
                $sroomabout = CommonUtils::getConfigValue($pdo, 'sroomabout', '');
                $sroomnews = CommonUtils::getConfigValue($pdo, 'sroomnews', '');
                $sroomfood = CommonUtils::getConfigValue($pdo, 'sroomfood', '');
                $sroomdrinks = CommonUtils::getConfigValue($pdo, 'sroomdrinks', '');
                $sroomutilization = CommonUtils::getConfigValue($pdo, 'sroomutilization', '');
                $sroomprodview = CommonUtils::getConfigValue($pdo, 'sroomprodview', '0');
                $sroomshowworkload = CommonUtils::getConfigValue($pdo, 'sroomshowworkload', 1);
                
                $prodTypes = self::getMenu($pdo);
		$types = json_encode($prodTypes["types"]);
		$products = json_encode($prodTypes["products"]);
                $prodimages = self::getImagesForShowroomProducs($pdo);
                $version = CommonUtils::getConfigValue($pdo, 'version', '');

                $titleimg = self::getTitleImg($pdo);

                $workfload = Roomtables::getWorkload($pdo);
                
		$transferdata = array(
                    "sroomcode" => $sroomcode,
                    "decpoint" => $decpoint,
                    "currency" => $currency,
                    "sroomtitle" => $sroomtitle,
                    "sroomabout" => $sroomabout,
                    "sroomnews" => $sroomnews,
		    "sroomimpressum" => $sroomimpressum,
		    "sroomprivacy" => $sroomprivacy,
		    "sroomcss" => $sroomcss,
                    "types" => $types,
                    "products" => $products,
                    "prodimages" => $prodimages,
                    "titleimg" => $titleimg,
                    "sroomfood" => $sroomfood,
                    "sroomdrinks" => $sroomdrinks,
                    "sroomutilization" => $sroomutilization,
                    "sroomprodview" => $sroomprodview,
                    "coreversion" => $version,
                    "workload" => $workfload,
                    "sroomshowworkload" => $sroomshowworkload
		);

		$data = json_encode($transferdata);
		$transferdataBase64 = base64_encode($data);

		return $this->sendToWebsite($url, $transferdataBase64);
	}
        
        private static function getMenu($pdo) {
                $showOnlyGuestArticles = CommonUtils::getConfigValue($pdo, 'sroomonlygarticles', 1);
                $where = '';
                if ($showOnlyGuestArticles == 1) {
                        $where = "  AND (P.display is null OR P.display='G' OR P.display='KG') ";
                }
                
                $sql = "select P.id,T.kind as kind,P.longname,P.description as description,P.category as ref,P.priceA as price,COALESCE(P.unit,0) as unit ";
		$sql .= " from %products% P ,%prodtype% T where P.available='1' AND P.removed is null $where ";
                $sql .= " AND P.category=T.id ";
		$sql .= " ORDER BY P.longname ";
                $prods = CommonUtils::fetchSqlAll($pdo, $sql);
                
                $sql = "SELECT T.name from %prodtype% T WHERE (T.removed is null OR T.removed='0')";
                $types = CommonUtils::fetchSqlAll($pdo, $sql);
                
                return array("types" => $types,"products" => $prods);
        }
        
        private static function getTitleImg($pdo) {
                $sql = "SELECT setting from %logo% WHERE name=?";
                $r = CommonUtils::fetchSqlAll($pdo, $sql, array('sroomtitleimg'));
                if (count($r) == 0) {
                        return "-";
                } else {
                        return base64_encode($r[0]['setting']);
                }
        }
        
	private static function getImagesForShowroomProducs($pdo) {
		$sql = "SELECT P.id as prodid,COALESCE(I.imgm,'-') as imagedata ";
		$sql .= " FROM %products% P LEFT JOIN %prodimages% I ON P.prodimageid=I.id ";
		$sql .= " WHERE P.available='1' AND P.removed is null ";
		
		$allProductImgs = CommonUtils::fetchSqlAll($pdo, $sql);
		

		return $allProductImgs;
	}
}