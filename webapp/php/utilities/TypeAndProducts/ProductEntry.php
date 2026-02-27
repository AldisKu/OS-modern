<?php

class ProductEntry {
        
        private array $props = array();

        public function __construct() {
                $prodCols = DbUtils::$prodCols;
                foreach ($prodCols as $aCol) {
                        if (isset($aCol["property"])) {
                                $memberVar = $aCol["property"];
                                $this->props[$memberVar] = $aCol["default"];
                        }
                }
        }

        public function createWithSubsetOfData($prodtypeid, $longname, $shortname, $priceA, $priceB, $priceC) {

                // overwrite the default entries with the args
                $this->props["longname"] = $longname;
                $this->props["shortname"] = $shortname;
                $this->props["priceA"] = $priceA;
                $this->props["priceB"]= $priceB;
                $this->props["priceC"] = $priceC;
                $this->props["category"] = $prodtypeid;
        }

        private function isTypeNumeric(string $type): bool {
                if (($type == "int") || ($type == "float")) {
                        return true;
                } else {
                        return false;
                }
        }
        
        private function yesnoToInt(string $yesno) : int {
                if (($yesno == "ja") || ($yesno == "yes") || ($yesno == "si") || ($yesno == "1")) {
                        return 1;
                } else {
                        return 0;
                }
        }
        
        private function correctTaxes() {
                
                if (!in_array($this->props["tax"], array(1,2,5,11,12,21,22))) {
                        $this->props["tax"] = 1;
                }
                if (!in_array($this->props["togotax"], array(1,2,5,11,12,21,22))) {
                        $this->props["togotax"] = 2;
                }
        }
        
        public function createFromPostData($postData):bool {
                try {
                        $prodCols = DbUtils::$prodCols;
                        foreach ($prodCols as $aCol) {
                                if (isset($aCol["property"])) {
                                        $memberVar = $aCol["property"];
                                        if (isset($postData[$aCol["col"]])) {
                                                $isNumericConstraint = $this->isTypeNumeric($aCol["type"]);
                                                $isYesNo = ($aCol["type"] == "yesno");
                                                if (($isNumericConstraint || $isYesNo) && !is_numeric($postData[$aCol["col"]])) {
                                                        $this->props[$memberVar] = $aCol["default"];
                                                } else {
                                                        $val = $postData[$aCol["col"]];
                                                        if ($val == 'null') {
                                                                $val = null;
                                                        }
                                                        $this->props[$memberVar] = $postData[$aCol["col"]];
                                                }
                                        }
                                }
                        }

                        if (!is_numeric($this->props["unit"])) {
                                $this->props["unit"] = 0;
                        }

                        if (($this->props["tax"] == "null") || ($this->props["tax"] == 0)) {
                                $this->props["tax"] = 1;
                        }
                        if (($this->props["togotax"] == "null") || ($this->props["togotax"] == 0)) {
                                $this->props["togotax"] = 2;
                        }
                        $this->correctTaxes();
                        if ($this->props["audio"] == '') {
                                $this->props["audio"] = null;
                        }
                        if (trim($this->props["shortname"]) == '') {
                                $this->props["shortname"] = $this->props["longname"];
                        }
                        if (is_null($this->props["barcode"]) || (trim($this->props["barcode"]) == "")) {
                                $this->props["barcode"] = "";
                        }
                        return true;
                } catch (Exception $ex) {
                        return false;
                }
        }
        
        public function createProductInDb($pdo) {
                $keyArr = array_keys($this->props);
                $keyStr = implode(',', array_map(function ($val) {
                                return sprintf("`%s`", $val);
                        }, $keyArr));
                $quotes = implode(',', array_fill(0, count($keyArr) - 1, '?'));

                $sql = "INSERT INTO `%products%` ($keyStr) VALUES (NULL,$quotes)";

                if (strlen($this->props["barcode"]) > 25) {
                        $this->props["barcode"] = substr($this->props["barcode"], 0, 25);
                }

                $vals = array_values($this->props);
                array_shift($vals);

                CommonUtils::execSql($pdo, $sql, $vals);

                return ($pdo->lastInsertId());
        }

        public function applyProductInDb($pdo) {
                $keyArr = array_keys($this->props);
                array_shift($keyArr); // without id
                $setSqlPart = implode(',', array_map(function ($val) {
                                return sprintf("%s=?", $val);
                        }, $keyArr));

                $updateSql = "UPDATE %products% SET $setSqlPart WHERE id=?";

                $vals = array_values($this->props);
                $id = array_shift($vals);
                $vals[] = $id;
                CommonUtils::execSql($pdo, $updateSql, $vals);
                return $id;
        }
        
        public function isDifferentFromDB($pdo,$idOfProdToCompare): bool {
                $keyArr = array_keys($this->props);
                $keyStr = implode(',', array_map(function ($val) {
                                return sprintf("`%s`", $val);
                        }, $keyArr));
                
                $sql = "SELECT $keyStr,removed FROM %products% WHERE id=?";
                $result = CommonUtils::fetchSqlAll($pdo, $sql, array($idOfProdToCompare));
                
                if (count($result) < 1) {
                        return true;
                }
                
                foreach($keyArr as $key) {
                        $dbVal = $result[0][$key];
                        $thisProp = $this->props[$key];
                        
                        if ($key == 'category') {
                                continue;
                        }
                        if (is_null($dbVal) && is_null($thisProp)) {
                                continue;
                        }
                        if ($dbVal != $thisProp) {
                                error_log("DIFF: " . $this->props["longname"] . "$key");
                                error_log("    $dbVal <--> $thisProp");
                            return true;
                        }
                }
                return false;
        }

        // 
        public function createProductStr($aProd, $decpoint): array {

                $prodCols = DbUtils::$prodCols;
                foreach ($prodCols as $aCol) {
                        if (isset($aCol["property"])) {
                                if (isset($aProd[$aCol["property"]])) {
                                        // is in the set of values that we get
                                        $val = $aProd[$aCol["property"]];
                                        $this->props[$aCol["property"]] = $val;
                                }
                        }
                }

                $inlineText = $this->props["longname"] . "; " . str_replace('.', $decpoint, $this->props["priceA"]);

                $extArr = array();

                $multilines = array();
                foreach ($prodCols as $aCol) {
                        if (isset($aCol["menu"])) {
                                $menuName = $aCol["menu"];
                                $colName = $aCol["col"];
                                $val = $aProd[$colName];
                                
                                $appearanceInMenu = "inline";
                                if (isset($aCol["appearanceinmenu"])) {
                                        $appearanceInMenu = $aCol["appearanceinmenu"];
                                }
                                $isNumericOrYesNo = $this->isTypeNumeric($aCol["type"]) || ($aCol["type"] == "yesno");
                                if ($isNumericOrYesNo && $val == 'null') {
                                        $val = null;
                                }
                                if ($val == $aCol["default"]) {
                                        $val = null;
                                }

                                switch ($colName) {
                                        case "shortname":
                                                if ($this->props["longname"] == $val) {
                                                        // no need to mention
                                                        $menuName = null;
                                                }
                                                break;
                                        case "tax":
                                                if (is_null($val) || ($val == 'null') || ($val == "1")) {
                                                        $menuName = null;
                                                }
                                                break;
                                        case "togotax":
                                                if (is_null($val) || ($val == 'null') || ($val == "2")) {
                                                        $menuName = null;
                                                }
                                                break;
                                        case "priceB":
                                        case "priceC":
                                                if ($val == $this->props["priceA"]) {
                                                        $menuName = null;
                                                }
                                                break;
                                        case "available":
                                                if (!is_null($val) && ($val == 0)) {
                                                        $val = "nein";
                                                } else {
                                                        $menuName = null;
                                                }
                                                break;
                                        case "unit":
                                                if (!is_null($val)) {
                                                        foreach (CommonUtils::$g_units_arr as $u) {
                                                                if ($u["value"] == $val) {
                                                                        $val = $u["text"];
                                                                }
                                                        }
                                                }
                                                break;
                                        case "display":
                                                if (is_null($val) || (strtoupper($val) == "KG")) {
                                                        $menuName = null;
                                                } else {
                                                        $val = strtoupper($val);
                                                }

                                                break;
                                        case "prodimageid":
                                                if (is_null($val) || ($val == 0)) {
                                                        $menuName = null;
                                                }
                                                break;
                                }
                                if (!is_null($menuName) && !is_null($val) && ($val != "")) {
                                        if ($appearanceInMenu == "inline") {
                                                if ($aCol["type"] == "yesno") {
                                                        if (($val == 0) || ($val == "0")) {
                                                                $val = "nein";
                                                        } else if (($val == 1) || ($val == "1")) {
                                                                $val = "ja";
                                                        }
                                                } else if ($aCol["type"] == "float") {
                                                        $val = str_replace('.', $decpoint, $val);
                                                }
                                                $extArr[] = $menuName . ":" . $val;
                                        } else {
                                                $val = str_replace("\n", "{newline}", $val);
                                                $multilines[] = "\\\\ $menuName:$val";
                                        }
                                }
                        }
                }

                if (count($extArr) > 0) {
                        $inlineText .= " # " . join("; ", $extArr);
                }
                $outLines = array($inlineText);
                foreach($multilines as $aLine) {
                        $outLines[] = $aLine;
                }

                return $outLines;
        }

        public function parse(string $aTextLine, array $multilines) {
                try {
                        $aTextLine = trim($aTextLine);
                        $propertyparts = explode('#', $aTextLine, 2);

                        $shortAndPriceA = $propertyparts[0];
                        $basic = explode(';', $shortAndPriceA);

                        $this->props["longname"] = $basic[0];
                        $this->props["priceA"] = floatval(str_replace(",", ".", (string) $basic[1]));

                        $this->props["shortname"] = $this->props["longname"];
                        $this->props["priceB"] = $this->props["priceA"];
                        $this->props["priceC"] = $this->props["priceA"];

                        $prodCols = DbUtils::$prodCols;
                        $menuDefs = array();
                        foreach ($prodCols as $aDef) {
                                if (isset($aDef['menu'])) {
                                        $menuDefs[$aDef['menu']] = $aDef;
                                }
                        }

                        $optionalParameters = array();
                        if ((count($propertyparts) > 1) && (trim($propertyparts[1]) != "")) {
                                $optionalParameters = explode(";", trim($propertyparts[1]));
                        }
                        
                        if (!is_null($multilines) && (count($multilines) > 0)) {
                                $optionalParameters = array_merge($optionalParameters,$multilines);
                        }

                        foreach ($optionalParameters as $param) {
                                $trimmedParam = trim($param);
                                if (trim($trimmedParam) == '') {
                                        continue;
                                }
                                $paramParts = explode(":", $trimmedParam,2);
                                $identifier = trim($paramParts[0]);
                                $value = trim($paramParts[1]);
                                if (!isset($menuDefs[$identifier])) {
                                        continue;
                                }
                                $definition = $menuDefs[$identifier];
                                $col = $definition["col"];
                                $property = $definition["property"];

                                switch ($col) {
                                        case "unit":
                                                if ($value == "Stück") {
                                                        $value = null;
                                                } else {
                                                        $vlower = strtolower($value);
                                                        foreach (CommonUtils::$g_units_arr as $u) {
                                                                if (strtolower($u["text"]) == $vlower) {
                                                                        $value = $u["value"];
                                                                        break;
                                                                }
                                                        }
                                                }
                                                break;
                                        case "taxaustria":
                                                if (($value < 0) || ($value > 4)) {
                                                        $value = null;
                                                }
                                                break;
                                        case "tax":
                                        case "togotax":
                                                if (!in_array($value, array(1,2,5,11,12,21,22))) {
                                                        $value = $definition["default"];
                                                }
                                                break;
                                        case "prodimageid":
                                                if ($value == 0) {
                                                        $value = null;
                                                }
                                                break;
                                        case "display":
                                                $value = strtoupper($value);
                                                if (($value != 'K') && ($value != 'G') && ($value != 'KG')) {
                                                        $value = null;
                                                }
                                                break;
                                        default:
                                                $type = $definition["type"];

                                                if ($type == "yesno") {
                                                        $value = $this->yesnoToInt($value);
                                                } else if ($type == "float") {
                                                        $value = floatval(str_replace(",", ".", (string) $value));
                                                } else if ($type == "int") {
                                                        $value = intval((string) $value);
                                                }
                                                break;
                                }
                                $this->props[$property] = $value;
                        }
                        
                        return array("status" => "OK");
                } catch (Exception $e) {
                        return array("status" => "ERROR", "code" => PARSE_ERROR, "msg" => PARSE_ERROR_MSG, "line" => $aTextLine);
                }
        }

        function getShortName() {
	return $this->props["shortname"];
    }

    function getPriceA() {
	return $this->props["priceA"];
    }

    function getPriceB() {
	return $this->props["priceB"];
    }

    function getPriceC() {
	return $this->props["priceC"];
    }
    function getPurchasingPrice() {
	return $this->props["purchasingprice"];
    }
    
    function getBarcode() {
	    $this->props["barcode"];
    }
    
    function getUnit() {
	    $this->props["unit"];
    }

    function getTax() {
	return $this->props["tax"];
    }
    function getTogoTax() {
	return $this->props["togotax"];
    }
    function getLongName() {
	return $this->props["longname"];
    }

    function getProdId() {
	return $this->props["id"];
    }

    function getAvailable() {
	return $this->props["available"];
    }
    
    function getTaxAustria() {
	return $this->props["taxaustria"];
    }
    function getAmount() {
	return $this->props["amount"];
    }
    function getGuestmaxorder() {
        return $this->props["guestmaxorder"];
    }

    function getCategory() {
	return $this->props["category"];
    }
    function getAudio() {
	return $this->props["audia"];
    }
    function getFavorite() {
	return $this->props["favorite"];
    }
    function getProdImageId() {
            $prodimgid = $this->props["prodimageid"];
	    return (($prodimgid == 0) ? null : $prodimgid);
    }
    function getDisplay() {
            $d = $this->props["display"];
	    return (($d == 'KG') ? null : $d);
    }

    public function setCategory(int $catId) {
            $this->props["category"] = $catId;
    }
}