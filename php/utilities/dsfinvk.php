<?php

class Dsfinvk {
	public static function export ($pdo,$dohtml) {		
		$tmpdir = CommonUtils::getConfigValue($pdo, 'tmpdir', '');
		if (is_null($tmpdir) || ($tmpdir == '') ) {
			$tmpdir = sys_get_temp_dir();
		}
		
		$csvtables = array(
		    array("header" => "Bonpos", "fileprefix" => "bonpos_", "csvfilename" => "lines.csv", "fct" => "bonpos"),
		    array("header" => "Bonpos_Ust", "fileprefix" => "bonposust_", "csvfilename" => "lines_vat.csv", "fct" => "bonposust"),
		    array("header" => "Bonpos_Preisfindung", "fileprefix" => "bonpospreis_", "csvfilename" => "itemamounts.csv", "fct" => "bonpospreisfindung"),
		    array("header" => "Bonkopf", "fileprefix" => "bonkopf_", "csvfilename" => "transactions.csv", "fct" => "bonkopf"),
		    array("header" => "Bonkopf_Ust", "fileprefix" => "bonkopfust_", "csvfilename" => "transactions_vat.csv", "fct" => "bonkopfust"),
		    array("header" => "Bonkopf_AbrKreis", "fileprefix" => "bonkopfabrkreis_", "csvfilename" => "allocation_groups.csv", "fct" => "bonkopfabrkreis"),
		    array("header" => "Bonkopf_Zahlarten", "fileprefix" => "bonkopfzahlarten_", "csvfilename" => "datapayment.csv", "fct" => "bonkopfzahlarten"),
		    array("header" => "Bon_Referenzen", "fileprefix" => "bonrefs_", "csvfilename" => "references.csv", "fct" => "bonreferenzen"),
		    array("header" => "TSE_Transaktionen", "fileprefix" => "tsetrans_", "csvfilename" => "transactions_tse.csv", "fct" => "tsetransactions"),
		    array("header" => "Stamm_Abschluss", "fileprefix" => "stammabschluss_", "csvfilename" => "cashpointclosing.csv", "fct" => "stammabschluss"),
		    array("header" => "Stamm_Orte", "fileprefix" => "stammorte_", "csvfilename" => "location.csv", "fct" => "stammorte"),
		    array("header" => "Stamm_Kassen", "fileprefix" => "stammkassen_", "csvfilename" => "cashregister.csv", "fct" => "stammkassen"),
		    array("header" => "Stamm_Terminals", "fileprefix" => "stammterminals_", "csvfilename" => "slaves.csv", "fct" => "stammterminals"),
		    array("header" => "Stamm_Agenturen", "fileprefix" => "stammagenturen_", "csvfilename" => "ps.csv", "fct" => "stammagenturen"),
		    array("header" => "Stamm_USt", "fileprefix" => "stammust_", "csvfilename" => "vat.csv", "fct" => "stammust"),
		    array("header" => "Stamm_TSE", "fileprefix" => "stammtse_", "csvfilename" => "tse.csv", "fct" => "stammtse"),
		    array("header" => "Z_GV_Typ", "fileprefix" => "zgvtyp_", "csvfilename" => "businesscases.csv", "fct" => "zgvtyp"),
		    array("header" => "Z_Zahlart", "fileprefix" => "zzahlart_", "csvfilename" => "payment.csv", "fct" => "zzahlart"),
		    array("header" => "Z_WAEHRUNGEN", "fileprefix" => "zcurrencies_", "csvfilename" => "cash_per_currency.csv", "fct" => "zwaehrungen")
		);
		$arrayToZip = array();
		if (!$dohtml) {
			foreach($csvtables as $aCsvtable) {
				$tempFileName = tempnam($tmpdir,$aCsvtable["fileprefix"]);
				$arrayToZip[] = array("tmpfilename" => $tempFileName,"csvfilename" => $aCsvtable["csvfilename"]);
				$fct = $aCsvtable["fct"];
				$params = [$pdo, $tempFileName];
				call_user_func_array(["Dsfinvk", $fct], $params);
			}

			$tmpindexxml = file_get_contents(dirname(__FILE__) . "/dsfinvkindex.xml");
			$dsfinvkname = CommonUtils::getConfigValue($pdo, 'dsfinvk_name', '');
			$dsfinvkpostalcode = CommonUtils::getConfigValue($pdo, 'dsfinvk_postalcode', '');
			$dsfinvkcity = CommonUtils::getConfigValue($pdo, 'dsfinvk_city', '');
			$tmpindexxml = str_replace('{NAME}', $dsfinvkname, $tmpindexxml);
			$tmpindexxml = str_replace('{LOCATION}', "$dsfinvkpostalcode $dsfinvkcity", $tmpindexxml);
			$tempFileName = tempnam($tmpdir,"indexxml");
			unlink($tempFileName);
			file_put_contents($tempFileName, $tmpindexxml);
			$arrayToZip[] = array("tmpfilename" => $tempFileName,"csvfilename" => "index.xml");
			
			$tmpdsfinvkdtd = file_get_contents(dirname(__FILE__) . "/dsfinvk.dtd");
			$tempFileName = tempnam($tmpdir,"dsfinvkdtd");
			unlink($tempFileName);
			file_put_contents($tempFileName, $tmpdsfinvkdtd);
			$arrayToZip[] = array("tmpfilename" => $tempFileName,"csvfilename" => "index.dtd");

			$zipfile = tempnam($tmpdir,"zip");
			$zip = new ZipArchive();
			if ($zip->open($zipfile, ZipArchive::CREATE)!==TRUE) {
				exit("cannot open <$zipfile>\n");
			}
			foreach($arrayToZip as $aCsvtable) {
				$zip->addFile($aCsvtable["tmpfilename"],$aCsvtable["csvfilename"]);
			}
			$zip->close();
			foreach($arrayToZip as $aCsvtable) {
				unlink($aCsvtable["tmpfilename"]);
			}
		
			header('Content-Description: File Transfer');
			header('Content-Type: application/zip');
			header('Content-Disposition: attachment; filename="dsfinvk-export.zip"');
			header('Expires: 0');
			header('Cache-Control: must-revalidate');
			header('Pragma: public');
			header('Content-Length: ' . filesize($zipfile));
			
			readfile($zipfile);
			unlink($zipfile);
		} else {
			echo self::getPage();
			//echo "<h1>DSFinv-k Export</h1>";
			foreach($csvtables as $aCsvtable) {
				$fct = $aCsvtable["fct"];
				$params = [$pdo, null];
				$tablecontent = call_user_func_array(["Dsfinvk", $fct], $params);
				//echo self::getPage(); // self::getStyle() . self::getScript();
				echo "<h2>" . $aCsvtable["header"] . " (" . $aCsvtable["csvfilename"] . ")</h2><p />"; // <pre>" . $tablecontent . "</pre>";
				echo "<table class='viewtable'>" . $tablecontent . "</table>";
			}
			echo "</body>\n</html>";
		}	
	}
	
	private static function combineSqlSelectQueries($cols) {
		$sqltxt = [];
		foreach($cols as $c) {
			$sqltxt[] = $c["select"] . " as " . $c["colname"];
		}
		return implode(",",$sqltxt);
	}

	
	private static function bonpos($pdo,$fileToSave) {
		$paydeskid = CommonUtils::getConfigValue($pdo, 'paydeskid', 1);
		$ztime = "IF(C.closingdate is not null,CONCAT(DATE(C.closingdate),'T',TIME(C.closingdate)),'')";
		$bonid = "CONCAT(CASE WHEN O.typerange='" . RANGE_ORDER . "' THEN 'BESTL-' WHEN O.typerange='" . RANGE_BILL . "' THEN 'BELEG-' WHEN O.typerange='" . RANGE_CLOSING . "' THEN 'SONST-'  ELSE 'X' END,bonid)";
		
		$unitSelect = "(CASE ";
		$unitSelect .= " WHEN unit is null OR unit='0' OR unit='1' THEN 'Stück' ";
		$unitSelect .= " WHEN unit='2' THEN 'kg' ";
		$unitSelect .= " WHEN unit='3' THEN 'gr' ";
		$unitSelect .= " WHEN unit='4' THEN 'mg' ";
		$unitSelect .= " WHEN unit='5' THEN 'l' ";
		$unitSelect .= " WHEN unit='6' THEN 'ml' ";
		$unitSelect .= " WHEN unit='7' THEN 'm' ";
                $unitSelect .= " WHEN unit='10' THEN 'h' ";
		$unitSelect .= " ELSE 'Stück' ";
		$unitSelect .= "END)";
		
		$gvtyp = "(CASE ";
		$gvtyp .= " WHEN unit='8' THEN 'EinzweckgutscheinKauf' ";
		$gvtyp .= " WHEN unit='9' THEN 'EinzweckgutscheinEinloesung' ";
		$gvtyp .= " ELSE 'Umsatz' ";
		$gvtyp .= "END)";
		
		
		$colsPaidUnpaid = array(
		    "SIGN" => array("select" => "O.signtxt","colname" => "signtxt","quote" => true,"suspect" => 1),
		    "MARKER" => array("select" => "'paid.unpaid'","colname" => "marker","quote" => true,"suspect" => 1),
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "closingdate","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "BON_ID" => array("select" => $bonid,"colname" => "oid","quote" => false),
		    "POS_ZEILE" => array("select" => "Q.poszeile","colname" => "position","quote" => false),
		    "GUTSCHEIN_NR" => array("select" => "COALESCE(V.id,'')","colname" => "voucherid","quote" => false),
		    "ARTIKELTEXT" => array("select" => "PN.name","colname" => "productname","quote" => true),
		    "POS_TERMINAL_ID" => array("select" => "O.terminalid","colname" => "terminalid","quote" => false),
		    "GV_TYP" => array("select" => "$gvtyp","colname" => "gvtyp","quote" => true),
		    "GV_NAME" => array("select" => "'Rechnungsartikel'","colname" => "gvname","quote" => true),
		    "INHAUS" => array("select" => "CASE WHEN Q.tablenr is null THEN 0 ELSE (CASE WHEN Q.togo is null THEN 1 ELSE (1-Q.togo) END) END","colname" => "inhaus","quote" => false),
		    "P_STORNO" => array("select" => "'0'","colname" => "pstorno","quote" => false),
		    "AGENTUR_ID" => array("select" => "'0'","colname" => "agenturid","quote" => false),
		    "ART_NR" => array("select" => "Q.productid","colname" => "productid","quote" => false),
		    "GTIN" => array("select" => "''","colname" => "gtin","quote" => true),
		    "WARENGR_ID" => array("select" => "''","colname" => "warengrid","quote" => true),
		    "WARENGR" => array("select" => "''","colname" => "warengr","quote" => true),
		    "MENGE" => array("select" => "ROUND(IF(B.status='s',-1,1) * Q.unitamount,3)","colname" => "unitamount","quote" => false),
		    "FAKTOR" => array("select" => "'1.000'","colname" => "faktor","quote" => false), 
		    "EINHEIT" => array("select" => "$unitSelect","colname" => "einheit","quote" => true),
		    "STK_BR" => array("select" => "ROUND(price / unitamount,2)","colname" => "stkbr","quote" => false)
		);

		$sqltxt = self::createSqlSelects($colsPaidUnpaid, $fileToSave);

		$sql = "SELECT " . implode(",",$sqltxt) . " FROM %operations% O ";
		$sql .= " INNER JOIN %queue% Q ON Q.opidok = O.id ";
                $sql .= " INNER JOIN %prodnames% PN ON Q.prodnameid=PN.id ";
		$sql .= " LEFT JOIN %billproducts% BP ON BP.queueid=Q.id ";
		$sql .= " LEFT JOIN %bill% B ON BP.billid=B.id ";
		$sql .= " LEFT JOIN %closing% C ON B.closingid=C.id ";
		$sql .= " LEFT JOIN %vouchers% V ON Q.voucherid=V.id ";
		$sql .= " WHERE B.status is null OR B.status='x' ";
		
		
		$colsProdCancelled = array(
		    "SIGN" => array("select" => "O.signtxt","colname" => "signtxt","quote" => true,"suspect" => 1),
		    "MARKER" => array("select" => "'prodcancelled'","colname" => "marker","quote" => true,"suspect" => 1),
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "closingdate","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "BON_ID" => array("select" => $bonid,"colname" => "oid","quote" => false),
		    "POS_ZEILE" => array("select" => "'1'","colname" => "position","quote" => false),
		    "GUTSCHEIN_NR" => array("select" => "COALESCE(V.id,'')","colname" => "voucherid","quote" => false),
		    "ARTIKELTEXT" => array("select" => "PN.name","colname" => "productname","quote" => true),
		    "POS_TERMINAL_ID" => array("select" => "O.terminalid","colname" => "terminalid","quote" => false),
		    "GV_TYP" => array("select" => "$gvtyp","colname" => "gvtyp","quote" => true),
		    "GV_NAME" => array("select" => "'Artikelstorno'","colname" => "gvname","quote" => true),
		    "INHAUS" => array("select" => "CASE WHEN Q.tablenr is null THEN 0 ELSE (CASE WHEN Q.togo is null THEN 1 ELSE (1-Q.togo) END) END","colname" => "inhaus","quote" => false),
		    "P_STORNO" => array("select" => "'0'","colname" => "pstorno","quote" => false),
		    "AGENTUR_ID" => array("select" => "'0'","colname" => "agenturid","quote" => false),
		    "ART_NR" => array("select" => "Q.productid","colname" => "productid","quote" => false),
		    "GTIN" => array("select" => "''","colname" => "gtin","quote" => true),
		    "WARENGR_ID" => array("select" => "''","colname" => "warengrid","quote" => true),
		    "WARENGR" => array("select" => "''","colname" => "warengr","quote" => true),
		    "MENGE" => array("select" => "ROUND(((-1) * Q.unitamount),3)","colname" => "unitamount","quote" => false),
		    "FAKTOR" => array("select" => "'1.000'","colname" => "faktor","quote" => false), 
		    "EINHEIT" => array("select" => "$unitSelect","colname" => "einheit","quote" => true),
		    "STK_BR" => array("select" => "ROUND(price / unitamount,2)","colname" => "stkbr","quote" => false)
		);
		
		$sqltxt = self::createSqlSelects($colsProdCancelled, $fileToSave);
		
		$sql .= " UNION ALL ";
		$sql .= " SELECT " . implode(",",$sqltxt) . " FROM %operations% O ";
		$sql .= " INNER JOIN %queue% Q ON Q.opidcancel = O.id ";
                $sql .= " INNER JOIN %prodnames% PN ON Q.prodnameid=PN.id ";
		$sql .= " LEFT JOIN %closing% C ON Q.clsid=C.id ";
		$sql .= " LEFT JOIN %vouchers% V ON Q.voucherid=V.id ";

		
		
		$sql = "SELECT * FROM ($sql) a ORDER BY CAST(SUBSTRING_INDEX(a.oid,'-',-1) AS UNSIGNED),a.clsid";

		$dataPart = self::createCsvDataPartByCursor($pdo, $sql, $colsPaidUnpaid, $fileToSave, 'bonpos');
		return $dataPart;
	}
	
	private static function bonposust($pdo,$fileToSave) {
		
		$paydeskid = CommonUtils::getConfigValue($pdo, 'paydeskid', 1);
		$ztime = "IF(C.closingdate is not null,CONCAT(DATE(C.closingdate),'T',TIME(C.closingdate)),'')";
		$bonid = "CONCAT(CASE WHEN O.typerange='" . RANGE_ORDER . "' THEN 'BESTL-' WHEN O.typerange='" . RANGE_BILL . "' THEN 'BELEG-' WHEN O.typerange='" . RANGE_CLOSING . "' THEN 'SONST-'  ELSE 'X' END,bonid)";

		$cols = array(
		    "MARKER" => array("select" => "'paid.unpaid'","colname" => "marker","quote" => true,"suspect" => 1),
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "closingdate","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "BON_ID" => array("select" => $bonid,"colname" => "oid","quote" => false),
		    "POS_ZEILE" => array("select" => "Q.poszeile","colname" => "position","quote" => false),
		    "UST_SCHLUESSEL" => array("select" => "Q.taxkey","colname" => "ustid","quote" => false),
		    "POS_BRUTTO" => array("select" => "ROUND(Q.price,5)","colname" => "brutto","quote" => false),
		    "POS_NETTO" => array("select" => "ROUND(Q.price / (1.0 + Q.tax/100.0),5)","colname" => "netto","quote" => false),
		    "POS_UST" => array("select" => "ROUND(Q.price-price / (1.0 + Q.tax/100.0),5)","colname" => "tax","quote" => false)
		);
		
		$sqltxt = self::createSqlSelects($cols, $fileToSave);
		
		$sql = "SELECT " . implode(",",$sqltxt) . " FROM %operations% O ";
		$sql .= " INNER JOIN %queue% Q ON Q.opidok = O.id ";
		$sql .= " LEFT JOIN %billproducts% BP ON BP.queueid=Q.id ";
		$sql .= " LEFT JOIN %bill% B ON BP.billid=B.id ";
		$sql .= " LEFT JOIN %closing% C ON B.closingid=C.id ";
		$sql .= " WHERE B.status is null OR B.status='x' ";
		$cols = array(
		    "MARKER" => array("select" => "'prodcancelled'","colname" => "marker","quote" => true,"suspect" => 1),
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "closingdate","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "BON_ID" => array("select" => $bonid,"colname" => "oid","quote" => false),
		    "POS_ZEILE" => array("select" => "'1'","colname" => "position","quote" => false),
		    "UST_SCHLUESSEL" => array("select" => "Q.taxkey","colname" => "ustid","quote" => false),
		    "POS_BRUTTO" => array("select" => "ROUND(0-Q.price,5)","colname" => "brutto","quote" => false),
		    "POS_NETTO" => array("select" => "ROUND(Q.price / (1.0 + Q.tax/100.0),5)","colname" => "netto","quote" => false),
		    "POS_UST" => array("select" => "ROUND(Q.price-price / (1.0 + Q.tax/100.0),5)","colname" => "tax","quote" => false)
		);
		$sqltxt = self::createSqlSelects($cols, $fileToSave);
		
		$sql .= " UNION ALL ";
		$sql .= " SELECT " . implode(",",$sqltxt) . " FROM %operations% O ";
		$sql .= " INNER JOIN %queue% Q ON Q.opidcancel = O.id ";
		$sql .= " LEFT JOIN %closing% C ON Q.clsid=C.id ";
		
		$sql = "SELECT * FROM ($sql) a ORDER BY CAST(SUBSTRING_INDEX(a.oid,'-',-1) AS UNSIGNED),a.clsid,a.position";
		$dataPart = self::createCsvDataPartByCursor($pdo, $sql, $cols, $fileToSave, 'bonposust');
		return $dataPart;
	}
	
	private static function bonpospreisfindung($pdo,$fileToSave) {
		$paydeskid = CommonUtils::getConfigValue($pdo, 'paydeskid', 1);
		$ztime = "IF(C.closingdate is not null,CONCAT(DATE(C.closingdate),'T',TIME(C.closingdate)),'')";
		$bonid = "CONCAT(CASE WHEN O.typerange='" . RANGE_ORDER . "' THEN 'BESTL-' WHEN O.typerange='" . RANGE_BILL . "' THEN 'BELEG-' WHEN O.typerange='" . RANGE_CLOSING . "' THEN 'SONST-'  ELSE 'X' END,bonid)";
		$priceTypeSelect = "(CASE ";
		$priceTypeSelect .= " WHEN Q.pricetype is null OR Q.pricetype='0' THEN 'base_amount' ";
		$priceTypeSelect .= " WHEN Q.pricetype='1' THEN 'discount' ";
		$priceTypeSelect .= " WHEN Q.pricetype='2' THEN 'extra_amount' ";
		$priceTypeSelect .= " ELSE 'base_amount' ";
		$priceTypeSelect .= "END)";
		
		$cols = array(
		    "MARKER" => array("select" => "'paid.unpaid'","colname" => "marker","quote" => true,"suspect" => 1),
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "closingdate","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "BON_ID" => array("select" => $bonid,"colname" => "oid","quote" => false),
		    "POS_ZEILE" => array("select" => "Q.poszeile","colname" => "position","quote" => false),
		    "TYP" => array("select" => "$priceTypeSelect","colname" => "pricetype","quote" => false),
		    "UST_SCHLUESSEL" => array("select" => "Q.taxkey","colname" => "ustid","quote" => false),
		    "PF_BRUTTO" => array("select" => "ROUND(Q.price,5)","colname" => "brutto","quote" => false),
		    "PF_NETTO" => array("select" => "round(price / (1.0 + Q.tax/100.0),5)","colname" => "netto","quote" => false),
		    "PF_UST" => array("select" => "ROUND(Q.price-price / (1.0 + Q.tax/100.0),5)","colname" => "tax","quote" => false)
		);
		
		$sqltxt = self::createSqlSelects($cols, $fileToSave);
		
		$sql = "SELECT " . implode(",",$sqltxt) . " FROM %operations% O ";
		$sql .= " INNER JOIN %queue% Q ON Q.opidok = O.id ";
		$sql .= " LEFT JOIN %billproducts% BP ON BP.queueid=Q.id ";
		$sql .= " LEFT JOIN %bill% B ON BP.billid=B.id ";
		$sql .= " LEFT JOIN %closing% C ON B.closingid=C.id ";
		$sql .= " WHERE B.status is null OR B.status='x' ";
		$cols = array(
		    "MARKER" => array("select" => "'prodcancelled'","colname" => "marker","quote" => true,"suspect" => 1),
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "closingdate","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "BON_ID" => array("select" => $bonid,"colname" => "oid","quote" => false),
		    "POS_ZEILE" => array("select" => "'1'","colname" => "position","quote" => false),
		    "TYP" => array("select" => "$priceTypeSelect","colname" => "pricetype","quote" => false),
		    "UST_SCHLUESSEL" => array("select" => "Q.taxkey","colname" => "ustid","quote" => false),
		    "PF_BRUTTO" => array("select" => "ROUND(0-Q.price,5)","colname" => "brutto","quote" => false),
		    "PF_NETTO" => array("select" => "round(price / (1.0 + Q.tax/100.0),5)","colname" => "netto","quote" => false),
		    "PF_UST" => array("select" => "ROUND(Q.price-price / (1.0 + Q.tax/100.0),5)","colname" => "tax","quote" => false)
		);
		$sqltxt = self::createSqlSelects($cols, $fileToSave);
		
		$sql .= " UNION ALL ";
		$sql .= " SELECT " . implode(",",$sqltxt) . " FROM %operations% O ";
		$sql .= " INNER JOIN %queue% Q ON Q.opidcancel = O.id ";
		$sql .= " LEFT JOIN %closing% C ON Q.clsid=C.id ";
		
		$sql = "SELECT * FROM ($sql) a ORDER BY CAST(SUBSTRING_INDEX(a.oid,'-',-1) AS UNSIGNED),a.clsid,a.position";
		$dataPart = self::createCsvDataPartByCursor($pdo, $sql, $cols, $fileToSave, 'bonpospreisfindung');
		return $dataPart;
	}

        
	private static function bonkopf($pdo,$fileToSave) {
		$paydeskid = CommonUtils::getConfigValue($pdo, 'paydeskid', 1);
		$ztime = "IF(C.closingdate is not null,CONCAT(DATE(C.closingdate),'T',TIME(C.closingdate)),'')";
		$bonid = "CONCAT(CASE WHEN O.typerange='" . RANGE_ORDER . "' THEN 'BESTL-' WHEN O.typerange='" . RANGE_BILL . "' THEN 'BELEG-' WHEN O.typerange='" . RANGE_CLOSING . "' THEN 'SONST-'  ELSE 'X' END,bonid)";
		
		$minTime = "CASE WHEN Qsub.logtime='0' THEN NULL ELSE Qsub.logtime END";
		$minlogTime = "(SELECT MIN($minTime) as starttime FROM %queue% Qsub,%billproducts% BPsub WHERE BPsub.billid=B.id AND BPsub.queueid=Qsub.id)";
		$dsfinDateTimeFormat = "CONCAT(DATE(from_unixtime($minlogTime)),'T',TIME(from_unixtime($minlogTime)))";
		$startLogTime = "COALESCE($dsfinDateTimeFormat,'')";
		
		$bonEnde = "(IF(B.logtime is not null AND (B.logtime <> '0'),CONCAT(DATE(from_unixtime(B.logtime)),'T',TIME(from_unixtime(B.logtime))),''))";
                $bonName = "(CASE WHEN B.status='c' AND B.cashtype='" . Bill::$CASHTYPE_TrinkgeldAG["value"] . "' THEN 'TrinkgeldAG' "
                        . "WHEN B.status='c' AND B.cashtype='" . Bill::$CASHTYPE_TrinkgeldAN["value"] . "' THEN 'TrinkgeldAN' "
                        . "WHEN B.status='c' AND B.cashtype='" . Bill::$CASHTYPE_Geldtransit["value"] . "' THEN 'Geldtransit' "
                        . "WHEN B.status='c' AND B.cashtype='" . Bill::$CASHTYPE_DifferenzSollIst["value"] . "' THEN 'DifferenzSollIst' "
                        . "ELSE '' END)";
                $bonNotiz = "(CASE "
                        . "WHEN B.status='c' AND B.cashtype='" . Bill::$CASHTYPE_TrinkgeldAG["value"] . "' AND brutto>='0.00' THEN 'TrinkgeldAG Einnahme' "
                        . "WHEN B.status='c' AND B.cashtype='" . Bill::$CASHTYPE_TrinkgeldAG["value"] . "' AND brutto<'0.00' THEN 'TrinkgeldAG Storno' "
                        . "WHEN B.status='c' AND B.cashtype='" . Bill::$CASHTYPE_TrinkgeldAN["value"] . "' AND brutto>='0.00' THEN 'TrinkgeldAN Einnahme' "
                        . "WHEN B.status='c' AND B.cashtype='" . Bill::$CASHTYPE_TrinkgeldAN["value"] . "' AND brutto<'0.00' THEN 'TrinkgeldAN Storno' "
                        . "WHEN B.status='c' AND B.cashtype='" . Bill::$CASHTYPE_Geldtransit["value"] . "' AND brutto<'0.00' THEN 'Geldtransit Entnahme aus Kasse' "
                        . "WHEN B.status='c' AND B.cashtype='" . Bill::$CASHTYPE_Geldtransit["value"] . "' AND brutto>='0.00' THEN 'Geldtransit' "
                        . "ELSE '' END)";
		
                
                $billuid = Bill::getBillUidSqlPart();
		$cols = array(
		    "SIGN" => array("select" => "O.signtxt","colname" => "signtxt","quote" => true,"suspect" => 1),
		    "MARKER" => array("select" => "'bills'","colname" => "marker","quote" => true,"suspect" => 1),
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "closingdate","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "BON_ID" => array("select" => $bonid,"colname" => "oid","quote" => false),
		    "BON_NR" => array("select" => "$billuid","colname" => "billuid","quote" => false),
		    "BON_TYP" => array("select" => "'Beleg'","colname" => "billtype","quote" => false),
		    "BON_NAME" => array("select" => "$bonName","colname" => "billname","quote" => false),
		    "TERMINAL_ID" => array("select" => "'1'","colname" => "terminalid","quote" => false),
		    "BON_STORNO" => array("select" => "''","colname" => "billstorno","quote" => false),
		    "BON_START" => array("select" => "$startLogTime","colname" => "billstart","quote" => false),
		    "BON_ENDE" => array("select" => "$bonEnde","colname" => "billend","quote" => false),
		    "BEDIENER_ID" => array("select" => "B.userid","colname" => "userid","quote" => false),
		    "BEDIENER_NAME" => array("select" => "U.username","colname" => "username","quote" => true),
		    "UMS_BRUTTO" => array("select" => "B.brutto","colname" => "brutto","quote" => false),
		    "KUNDE_NAME" => array("select" => "''","colname" => "customername","quote" => true),
		    "KUNDE_ID" => array("select" => "''","colname" => "customerid","quote" => false),
		    "KUNDE_TYP" => array("select" => "''","colname" => "customertype","quote" => false),
		    "KUNDE_STRASSE" => array("select" => "''","colname" => "customerstreet","quote" => true),
		    "KUNDE_PLZ" => array("select" => "''","colname" => "customerplz","quote" => true),
		    "KUNDE_ORT" => array("select" => "''","colname" => "customerort","quote" => true),
		    "KUNDE_LAND" => array("select" => "''","colname" => "customerland","quote" => true),
		    "KUNDE_USTID" => array("select" => "''","colname" => "customerustid","quote" => true),
		    "BON_NOTIZ" => array("select" => "$bonNotiz","colname" => "bonnotiz","quote" => true)
		);
		
		$sqltxt = self::createSqlSelects($cols, $fileToSave);
		
		$sql = " SELECT " . implode(",",$sqltxt) . " FROM %operations% O ";
		$sql .= " INNER JOIN %bill% B ON B.opid = O.id ";
		$sql .= " LEFT JOIN %user% U ON B.userid = U.id ";
		$sql .= " LEFT JOIN %closing% C ON B.closingid=C.id ";
		
		$sql = "SELECT * FROM ($sql) a ORDER BY CAST(SUBSTRING_INDEX(a.oid,'-',-1) AS UNSIGNED),a.clsid";
		$dataPart = self::createCsvDataPartByCursor($pdo, $sql, $cols, $fileToSave, 'bonkopf');
		return $dataPart;
	}
	
	private static function bonkopfust($pdo,$fileToSave) {
		$paydeskid = CommonUtils::getConfigValue($pdo, 'paydeskid', 1);
		$ztime = "IF(C.closingdate is not null,CONCAT(DATE(C.closingdate),'T',TIME(C.closingdate)),'')";
		$bonid = "CONCAT(CASE WHEN O.typerange='" . RANGE_ORDER . "' THEN 'BESTL-' WHEN O.typerange='" . RANGE_BILL . "' THEN 'BELEG-' WHEN O.typerange='" . RANGE_CLOSING . "' THEN 'SONST-'  ELSE 'X' END,bonid)";
		
		$cols = array(
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "closingdate","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "BON_ID" => array("select" => $bonid,"colname" => "oid","quote" => false),
		    "ARTIKEL" => array("select" => "GROUP_CONCAT(PN.name)","colname" => "prodname","quote" => false, "suspect" => 1),
		    "STATUS" => array("select" => "B.status","colname" => "bstatus","quote" => true, "suspect" => 1),
		    "TAXVAL" => array("select" => "Q.tax","colname" => "taxval","quote" => false, "suspect" => 1),
		    "UST_SCHLUESSEL" => array("select" => "taxkey","colname" => "ustid","quote" => false),
		    "BON_BRUTTO" => array("select" => "ROUND(SUM(Q.price),2)","colname" => "billbrutto","quote" => false),
		    "BON_NETTO" => array("select" => "ROUND(SUM(Q.price / (1.0 + Q.tax/100.0)),2)","colname" => "billnetto","quote" => false),
		    "BON_UST" => array("select" => "ROUND(SUM(Q.price - Q.price / (1.0 + Q.tax/100.0)),2)","colname" => "billust","quote" => false)
		);
		$sqltxt = self::createSqlSelects($cols, $fileToSave);
		
		$sql = " SELECT " . implode(",",$sqltxt) . " FROM %operations% O ";
		$sql .= " INNER JOIN %bill% B ON B.opid = O.id ";
		$sql .= " LEFT JOIN %billproducts% BP ON B.id=BP.billid ";
		$sql .= " INNER JOIN %queue% Q ON BP.queueid=Q.id ";
                $sql .= " INNER JOIN %prodnames% PN ON Q.prodnameid=PN.id ";
		$sql .= " LEFT JOIN %closing% C ON B.closingid=C.id ";
		$sql .= " WHERE B.status is null OR B.status <> 'c' ";
		$sql .= " GROUP BY Q.taxkey,O.id ";
		
		$cols = array(
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "closingdate","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "BON_ID" => array("select" => $bonid,"colname" => "oid","quote" => false),
		    "ARTIKEL" => array("select" => "'Bareinlage'","colname" => "prodname","quote" => false, "suspect" => 1),
		    "STATUS" => array("select" => "B.status","colname" => "bstatus","quote" => true, "suspect" => 1),
		    "TAXVAL" => array("select" => "'0.00'","colname" => "taxval","quote" => false, "suspect" => 1),
		    "UST_SCHLUESSEL" => array("select" => "'5'","colname" => "ustid","quote" => false),
		    "BON_BRUTTO" => array("select" => "ROUND(B.brutto,2)","colname" => "billbrutto","quote" => false),
		    "BON_NETTO" => array("select" => "'0.00'","colname" => "billnetto","quote" => false),
		    "BON_UST" => array("select" => "'0.00'","colname" => "billust","quote" => false)
		);
		$sqltxt = self::createSqlSelects($cols, $fileToSave);
		
		$sql .= " UNION ALL ";
		$sql .= " SELECT " . implode(",",$sqltxt) . " FROM %operations% O ";
		$sql .= " INNER JOIN %bill% B ON B.opid = O.id ";
		$sql .= " LEFT JOIN %closing% C ON B.closingid=C.id ";
		$sql .= " WHERE B.status is not null AND B.status = 'c' ";
		
		$sql = "SELECT * FROM ($sql) a ORDER BY CAST(SUBSTRING_INDEX(a.oid,'-',-1) AS UNSIGNED),a.clsid";
		
		$dataPart = self::createCsvDataPartByCursor($pdo, $sql, $cols, $fileToSave, 'bonkopfust');
		return $dataPart;
	}
	
	private static function bonkopfabrkreis($pdo,$fileToSave) {
		$paydeskid = CommonUtils::getConfigValue($pdo, 'paydeskid', 1);
		$ztime = "IF(C.closingdate is not null,CONCAT(DATE(C.closingdate),'T',TIME(C.closingdate)),'')";
		$bonid = "CONCAT(CASE WHEN O.typerange='" . RANGE_ORDER . "' THEN 'BESTL-' WHEN O.typerange='" . RANGE_BILL . "' THEN 'BELEG-' WHEN O.typerange='" . RANGE_CLOSING . "' THEN 'SONST-'  ELSE 'X' END,bonid)";

		$cols = array(
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "closingdate","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "BON_ID" => array("select" => $bonid,"colname" => "oid","quote" => false),
		    "ABRECHNUNGSKREIS" => array("select" => "COALESCE(R.name,CASE WHEN status='c' THEN '' ELSE 'Ausser-Haus' END)","colname" => "resttablename","quote" => true),
		    );
		    
		$sqltxt = self::createSqlSelects($cols, $fileToSave);
		
		$sql = " SELECT " . implode(",",$sqltxt) . " FROM %operations% O ";
		$sql .= " INNER JOIN %bill% B ON B.opid = O.id ";
		$sql .= " LEFT JOIN %closing% C ON B.closingid=C.id ";
		$sql .= " LEFT JOIN %resttables% R ON B.tableid=R.id ";
		$sql = "SELECT * FROM ($sql) a ORDER BY CAST(SUBSTRING_INDEX(a.oid,'-',-1) AS UNSIGNED),a.clsid";
		
		$dataPart = self::createCsvDataPartByCursor($pdo, $sql, $cols, $fileToSave, 'bonkopfabrkreis');
		return $dataPart;

	}
	
	private static function bonkopfzahlarten($pdo,$fileToSave) {
		$paydeskid = CommonUtils::getConfigValue($pdo, 'paydeskid', 1);
		$ztime = "IF(C.closingdate is not null,CONCAT(DATE(C.closingdate),'T',TIME(C.closingdate)),'')";
		$bonid = "CONCAT(CASE WHEN O.typerange='" . RANGE_ORDER . "' THEN 'BESTL-' WHEN O.typerange='" . RANGE_BILL . "' THEN 'BELEG-' WHEN O.typerange='" . RANGE_CLOSING . "' THEN 'SONST-'  ELSE 'X' END,bonid)";
		$paymenttype = "(CASE ";
		$paymenttype .= " WHEN paymentid is null OR paymentid='1' THEN 'Bar' ";
		$paymenttype .= " WHEN paymentid='2' THEN 'ECKarte' ";
		$paymenttype .= " WHEN paymentid='3' THEN 'Kreditkarte' ";
		$paymenttype .= " ELSE 'Unbar' ";
		$paymenttype .= "END)";
		
		$cols = array(
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "closingdate","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "BON_ID" => array("select" => $bonid,"colname" => "oid","quote" => false),
		    "ZAHLART_TYP" => array("select" => "$paymenttype","colname" => "paymenttype","quote" => true),
		    "ZAHLART_NAME"  => array("select" => "PA.name","colname" => "paymentname","quote" => true),
		    "ZAHLWAEH_CODE" => array("select" => "''","colname" => "curcode","quote" => true),
		    "ZAHLWAEH_BETRAG" => array("select" => "''","colname" => "zaehbetrag","quote" => false),
		    "BASISWAEH_BETRAG" => array("select" => "B.brutto","colname" => "basvalue","quote" => false)
		    );
		    
		$sqltxt = self::createSqlSelects($cols, $fileToSave);
		
		$sql = " SELECT " . implode(",",$sqltxt) . " FROM %operations% O ";
		$sql .= " INNER JOIN %bill% B ON B.opid = O.id ";
		$sql .= " INNER JOIN %payment% PA ON PA.id=B.paymentid ";
		$sql .= " LEFT JOIN %closing% C ON B.closingid=C.id ";
		$sql .= " LEFT JOIN %resttables% R ON B.tableid=R.id ";
		$sql = "SELECT * FROM ($sql) a ORDER BY CAST(SUBSTRING_INDEX(a.oid,'-',-1) AS UNSIGNED),a.clsid";
		
		$dataPart = self::createCsvDataPartByCursor($pdo, $sql, $cols, $fileToSave, 'bonkopfzahlarten');
		return $dataPart;
	}
	
	private static function bonreferenzen($pdo,$fileToSave) {
		$paydeskid = CommonUtils::getConfigValue($pdo, 'paydeskid', 1);
		$ztime = "IF(C.closingdate is not null,CONCAT(DATE(C.closingdate),'T',TIME(C.closingdate)),'')";
		$bonid = "CONCAT(CASE WHEN O.typerange='" . RANGE_ORDER . "' THEN 'BESTL-' WHEN O.typerange='" . RANGE_BILL . "' THEN 'BELEG-' WHEN O.typerange='" . RANGE_CLOSING . "' THEN 'SONST-'  ELSE 'X' END,O.bonid)";
		$refTimeFilter = "CASE WHEN OREF.logtime='0' THEN NULL ELSE OREF.logtime END";
		$reftime = "CONCAT(DATE(from_unixtime($refTimeFilter)),'T',TIME(from_unixtime($refTimeFilter)))";
		$refbonid = "CONCAT(CASE WHEN OREF.typerange='" . RANGE_ORDER . "' THEN 'BESTL-' WHEN OREF.typerange='" . RANGE_BILL . "' THEN 'BELEG-' ELSE 'X' END,OREF.bonid)";
		
		$unitSelect = "(CASE ";
		$unitSelect .= " WHEN unit is null OR unit='0' OR unit='1' THEN 'Stück' ";
		$unitSelect .= " WHEN unit='2' THEN 'kg' ";
		$unitSelect .= " WHEN unit='3' THEN 'gr' ";
		$unitSelect .= " WHEN unit='4' THEN 'mg' ";
		$unitSelect .= " WHEN unit='5' THEN 'l' ";
		$unitSelect .= " WHEN unit='6' THEN 'ml' ";
		$unitSelect .= " WHEN unit='7' THEN 'm' ";
                $unitSelect .= " WHEN unit='10' THEN 'h' ";
		$unitSelect .= " ELSE 'Stück' ";
		$unitSelect .= "END)";
		
		$colsPaidUnpaid = array(
		    "SIGN" => array("select" => "O.signtxt","colname" => "signtxt","quote" => true,"suspect" => 1),
		    "MARKER" => array("select" => "'paid.unpaid'","colname" => "marker","quote" => true,"suspect" => 1),
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "closingdate","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "BON_ID" => array("select" => $bonid,"colname" => "oid","quote" => false),
		    "POS_ZEILE" => array("select" => "Q.poszeile","colname" => "position","quote" => false),
		    "REF_TYP" => array("select" => "'Transaktion'","colname" => "reftyp","quote" => true),
		    "REF_NAME" => array("select" => "''","colname" => "refname","quote" => true),
		    "REF_DATUM" => array("select" => "$reftime","colname" => "refdatum","quote" => false),
		    "REF_Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "refzkasseid","quote" => true),
		    "REF_Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "refznr","quote" => false),
		    "REF_BON_ID" => array("select" => $refbonid,"colname" => "refbonid","quote" => false)
		);

		$sqltxt = self::createSqlSelects($colsPaidUnpaid, $fileToSave);

		$sql = "SELECT " . implode(",",$sqltxt) . " FROM %operations% O ";
		$sql .= " INNER JOIN %queue% Q ON Q.opidok = O.id ";
		$sql .= " LEFT JOIN %billproducts% BP ON BP.queueid=Q.id ";
		$sql .= " INNER JOIN %bill% B ON BP.billid=B.id ";
		$sql .= " LEFT JOIN %closing% C ON B.closingid=C.id ";
		$sql .= " LEFT JOIN %operations% OREF ON B.opid=OREF.id ";
		$sql .= " WHERE B.status is null OR B.status='x' ";

		
		
		$sql = "SELECT * FROM ($sql) a ORDER BY CAST(SUBSTRING_INDEX(a.oid,'-',-1) AS UNSIGNED),a.clsid";

		$dataPart = self::createCsvDataPartByCursor($pdo, $sql, $colsPaidUnpaid, $fileToSave, 'bonreferences');
		return $dataPart;

	}
	
	private static function tsetransactions($pdo,$fileToSave) {
		$bonid = "CONCAT(CASE WHEN O.typerange='" . RANGE_ORDER . "' THEN 'BESTL-' WHEN O.typerange='" . RANGE_BILL . "' THEN 'BELEG-' WHEN O.typerange='" . RANGE_CLOSING . "' THEN 'SONST-'  ELSE 'X' END,bonid)";

		$paydeskid = CommonUtils::getConfigValue($pdo, 'paydeskid', 1);
		$ztime = "IF(C.closingdate is not null,CONCAT(DATE(C.closingdate),'T',TIME(C.closingdate)),'')";
		$logTimeFilter = "CASE WHEN O.logtime='0' THEN NULL ELSE O.logtime END";
		$taLogTime = "CONCAT(DATE(from_unixtime($logTimeFilter)),'T',TIME(from_unixtime($logTimeFilter)))";
		$signtxt = "REPLACE(REPLACE(O.signtxt,'\n',' '),'\r','')";
		
		$belegtype = DbUtils::$PROCESSTYPE_BELEG;
		$vorgangtype = DbUtils::$PROCESSTYPE_VORGANG;
		$sonstigtype = DbUtils::$PROCESSTYPE_SONSTIGER_VORGANG;
		
		$processType = "(CASE ";
		$processType .= " WHEN O.processtype='$belegtype' THEN 'Kassenbeleg-V1' ";
		$processType .= " WHEN O.processtype='$vorgangtype' THEN 'Bestellung-V1' ";
		$processType .= " WHEN O.processtype='$sonstigtype' THEN 'SonstigerVorgang' ";
		$processType .= " ELSE 'Kassenbeleg-V1' ";
		$processType .= "END)";
		
		$noTse = DbUtils::$NO_TSE;
		$tseOk = DbUtils::$TSE_OK;
		$tseKnownError = DbUtils::$TSE_KNOWN_ERROR;
		$tseRuntimeError = DbUtils::$TSE_RUNTIME_ERROR;
		$tseMisconfig = DbUtils::$TSE_MISCONFIG;
		$tseerror = "(CASE WHEN O.tseerror is null OR O.tseerror='$tseOk' THEN '' ";
		$tseerror .= " WHEN O.tseerror='$noTse' THEN 'Keine TSE angeschlossen' ";
		$tseerror .= " WHEN O.tseerror='$tseKnownError' THEN 'Bekannter TSE-Fehler' ";
		$tseerror .= " WHEN O.tseerror='$tseRuntimeError' THEN 'Unbekannter TSE-Fehler' ";
		$tseerror .= " WHEN O.tseerror='$tseMisconfig' THEN 'TSE falsch konfiguriert' ";
		$tseerror .= " WHEN O.tseerror='$noTse' THEN 'keine TSE' WHEN O.tseerror='$tseKnownError' THEN 'TSE-Fehler' ";
		$tseerror .= " ELSE '' END)";

		$sql = "SELECT * FROM (";
		$colsBills = array(
		    "MARKER" => array("select" => "'bills'","colname" => "marker","quote" => true,"suspect" => 1),
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "closingdate","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "BON_ID" => array("select" => $bonid,"colname" => "opid","quote" => false),
		    "TSE_ID" => array("select" => '1',"colname" => "tseid","quote" => false),
                    "TSE_TANR" => array("select" => "O.trans","colname" => "trans","quote" => false),
		    "TSE_TA_START" => array("select" => "$taLogTime","colname" => "tastart","quote" => false),
		    "TSE_TA_ENDE" => array("select" => "$taLogTime","colname" => "taende","quote" => false),
		    "TSE_TA_VORGANGSART" => array("select" => "$processType","colname" => "vorgangsart","quote" => true),
		    "TSE_TA_SIGZ" => array("select" => "O.sigcounter","colname" => "sigcounter","quote" => false),
		    "TSE_TA_SIG" => array("select" => "O.tsesignature","colname" => "tsesignature","quote" => false),
		    "TSE_TA_FEHLER" => array("select" => "$tseerror","colname" => "tseerror","quote" => true),
		    "TSE_VORGANGSDATEN" => array("select" => "$signtxt","colname" => "vorgang","quote" => true)
		);

		$billTableStore = DbUtils::$OPERATION_IN_BILL_TABLE;
		$sqlBilltxt = self::createSqlSelects($colsBills, $fileToSave);
		
		$sql .= "SELECT " . implode(",",$sqlBilltxt) . " from %operations% O ";
		$sql .= " LEFT JOIN %bill% B ON B.opid=O.id ";
		$sql .= " LEFT JOIN %closing% C ON B.closingid=C.id ";
		$sql .= " WHERE O.handledintable='$billTableStore' AND O.tseerror <> $noTse ";
		
		
		$colsClosings = array(
		    "MARKER" => array("select" => "'closings'","colname" => "marker","quote" => true,"suspect" => 1),
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "closingdate","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "BON_ID" => array("select" => $bonid,"colname" => "opid","quote" => false),
                    "TSE_ID" => array("select" => '1',"colname" => "tseid","quote" => false),
		    "TSE_TANR" => array("select" => "O.trans","colname" => "trans","quote" => false),
		    "TSE_TA_START" => array("select" => "$taLogTime","colname" => "tastart","quote" => false),
		    "TSE_TA_ENDE" => array("select" => "$taLogTime","colname" => "taende","quote" => false),
		    "TSE_TA_VORGANGSART" => array("select" => "$processType","colname" => "vorgangsart","quote" => true),
		    "TSE_TA_SIGZ" => array("select" => "O.sigcounter","colname" => "sigcounter","quote" => false),
		    "TSE_TA_SIG" => array("select" => "O.tsesignature","colname" => "tsesignature","quote" => false),
		    "TSE_TA_FEHLER" => array("select" => "$tseerror","colname" => "tseerror","quote" => true),
		    "TSE_VORGANGSDATEN" => array("select" => "$signtxt","colname" => "vorgang","quote" => true)
		);

		$closingTableStore = DbUtils::$OPERATION_IN_CLOSING_TABLE;
		$sqlClosingtxt = self::createSqlSelects($colsClosings, $fileToSave);
		
		$sql .= " UNION ALL ";
		$sql .= "SELECT " . implode(",",$sqlClosingtxt) . " from %operations% O ";
		$sql .= " INNER JOIN %closing% C ON O.id=C.opid ";
		$sql .= " WHERE O.handledintable='$closingTableStore' AND O.tseerror <> $noTse ";
		

		$colsQueue = array(
		    "MARKER" => array("select" => "'queue'","colname" => "marker","quote" => true,"suspect" => 1),
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "closingdate","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "BON_ID" => array("select" => "$bonid","colname" => "opid","quote" => false),
                    "TSE_ID" => array("select" => '1',"colname" => "tseid","quote" => false),
		    "TSE_TANR" => array("select" => "O.trans","colname" => "trans","quote" => false),
		    "TSE_TA_START" => array("select" => "$taLogTime","colname" => "tastart","quote" => true),
		    "TSE_TA_ENDE" => array("select" => "$taLogTime","colname" => "taende","quote" => true),
		    "TSE_TA_VORGANGSART" => array("select" => "$processType","colname" => "vorgangsart","quote" => true),
		    "TSE_TA_SIGZ" => array("select" => "O.sigcounter","colname" => "sigcounter","quote" => false),
		    "TSE_TA_SIG" => array("select" => "O.tsesignature","colname" => "tsesignature","quote" => false),
		    "TSE_TA_FEHLER" => array("select" => "$tseerror","colname" => "tseerror","quote" => true),
		    "TSE_VORGANGSDATEN" => array("select" => "$signtxt","colname" => "vorgang","quote" => true)
		);
		$queueTableStore = DbUtils::$OPERATION_IN_QUEUE_TABLE;
		$sqlQueuetxt = self::createSqlSelects($colsQueue, $fileToSave);
		
		$sql .= " UNION ALL ";
		$sql .= "SELECT DISTINCT " . implode(",",$sqlQueuetxt) . " from %operations% O ";
		$sql .= " INNER JOIN %queue% Q ON Q.opidok=O.id ";
		$sql .= " LEFT JOIN %billproducts% BP ON BP.queueid=Q.id ";
		$sql .= " LEFT JOIN %bill% B ON BP.billid=B.id ";
		$sql .= " LEFT JOIN %closing% C ON B.closingid=C.id ";
		$sql .= " WHERE O.handledintable='$queueTableStore' AND O.tseerror <> $noTse ";
                
		
		$sql .= " UNION ALL ";
		$sql .= "SELECT DISTINCT " . implode(",",$sqlQueuetxt) . " from %operations% O ";
		$sql .= " INNER JOIN %queue% Q ON Q.opidcancel=O.id ";
		$sql .= " LEFT JOIN %billproducts% BP ON BP.queueid=Q.id ";
		$sql .= " LEFT JOIN %bill% B ON BP.billid=B.id ";
		$sql .= " LEFT JOIN %closing% C ON B.closingid=C.id ";
		$sql .= " WHERE O.handledintable='$queueTableStore' AND O.tseerror <> $noTse ";
				
		$sql .= " ) AS i ";
		$sql .= " ORDER BY SUBSTRING_INDEX(i.opid,'-',1),CAST(SUBSTRING_INDEX(i.opid,'-',-1) AS UNSIGNED)";
		
		$dataPart = self::createCsvDataPartByCursor($pdo, $sql, $colsBills, $fileToSave, 'tsetransactions');
		return $dataPart;
	}
	
	private static function stammabschluss($pdo,$fileToSave) {
		$paydeskid = CommonUtils::getConfigValue($pdo, 'paydeskid', 1);
		$ztime = "IF(C.closingdate is not null,CONCAT(DATE(C.closingdate),'T',TIME(C.closingdate)),'')";

		$zstartbonid = "select bonid FROM %operations% where id=(SELECT O.id FROM %operations% O WHERE O.clsid=C.id GROUP BY O.id ORDER BY id LIMIT 1)";		
		$zstarttyperange = "(SELECT (CASE WHEN typerange='" . RANGE_ORDER . "' THEN 'BESTL-' WHEN typerange='" . RANGE_BILL . "' THEN 'BELEG-' WHEN typerange='" . RANGE_CLOSING . "' THEN 'SONST-' ELSE 'Unknown' END) FROM %operations% where id=(SELECT O.id FROM %operations% O WHERE O.clsid=C.id GROUP BY O.id ORDER BY id LIMIT 1))";
		$zstartid = "CONCAT($zstarttyperange,($zstartbonid))";
		
		$zendbonid = "select bonid FROM %operations% where id=(SELECT O.id FROM %operations% O WHERE O.clsid=C.id GROUP BY O.id ORDER BY id DESC LIMIT 1)";		
		$zendtyperange = "(SELECT (CASE WHEN typerange='" . RANGE_ORDER . "' THEN 'BESTL-' WHEN typerange='" . RANGE_BILL . "' THEN 'BELEG-' WHEN typerange='" . RANGE_CLOSING . "' THEN 'SONST-' ELSE 'Unknown' END) FROM %operations% where id=(SELECT O.id FROM %operations% O WHERE O.clsid=C.id GROUP BY O.id ORDER BY id DESC LIMIT 1))";
		$zendid = "CONCAT($zendtyperange,($zendbonid))";
		
		$billsum = "ROUND(SUM(B.brutto),2)";
		$billcashsum = "ROUND(SUM(CASE WHEN B.paymentid='1' THEN B.brutto ELSE '0.00' END),2)";

		$loc = self::_getAlpha3CountryCode('C.dsfinvk_country');
		
		$cols = array(
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "zerstellung","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "Z_BUCHUNGSTAG" => array("select" => "DATE(C.closingdate)","colname" => "buchungstag","quote" => false),
		    "TAXONOMIE_VERSION" => array("select" => "COALESCE(C.dsfinvkversion,'')","colname" => "dsfinvkversion","quote" => false),
		    "Z_START_ID" => array("select" => "$zstartid","colname" => "zstartid","quote" => false),
		    "Z_ENDE_ID" => array("select" => "$zendid","colname" => "zendid","quote" => false),
		    "NAME" => array("select" => "C.dsfinvk_name","colname" => "name","quote" => true),
		    "STRASSE" => array("select" => "C.dsfinvk_street","colname" => "street","quote" => true),
		    "PLZ" => array("select" => "C.dsfinvk_postalcode","colname" => "plz","quote" => true),
		    "ORT" => array("select" => "C.dsfinvk_city","colname" => "city","quote" => true),
		    "LAND" => array("select" => $loc,"colname" => "country","quote" => true),
		    "STRN" => array("select" => "C.dsfinvk_stnr","colname" => "strn","quote" => true),
		    "USTID" => array("select" => "C.dsfinvk_ustid","colname" => "ustid","quote" => true),
		    "Z_SE_ZAHLUNGEN" => array("select" => "$billsum","colname" => "billsum","quote" => false),
		    "Z_SE_BARZAHLUNGEN" => array("select" => "$billcashsum","colname" => "billcashsum","quote" => false)
		);
		$sqltxt = self::createSqlSelects($cols, $fileToSave);
		$sql = "SELECT " . implode(",",$sqltxt) . " FROM %closing% C";
		$sql .= " LEFT JOIN %bill% B ON B.closingid=C.id ";
		$sql .= " GROUP BY C.id ";
		$sql .= " ORDER BY clsid";
		
		$dataPart = self::createCsvDataPartByCursor($pdo, $sql, $cols, $fileToSave, 'stammabschluss');
		return $dataPart;
	}
	
	private static function _getAlpha3CountryCode($field) {
		$sql = "(CASE WHEN LOWER(SUBSTRING($field,1,4)) = 'Deut' THEN 'DEU' WHEN LOWER(SUBSTRING($field,1,5)) = 'Germa' THEN 'DEU' ";
		$sql .= "WHEN LOWER(SUBSTRING($field,1,5)) = 'Öster' THEN 'AUT' WHEN LOWER(SUBSTRING($field,1,5)) = 'Austr' THEN 'AUT' ";
		$sql .= "WHEN LOWER(SUBSTRING($field,1,6)) = 'Schwei' THEN 'CHE' WHEN LOWER(SUBSTRING($field,1,5)) = 'Switz' THEN 'CHE' ";
		$sql .= "ELSE $field END)";
		return $sql;
	}
	
	private static function stammorte($pdo,$fileToSave) {
		$paydeskid = CommonUtils::getConfigValue($pdo, 'paydeskid', 1);
		$ztime = "IF(C.closingdate is not null,CONCAT(DATE(C.closingdate),'T',TIME(C.closingdate)),'')";
		$loc = self::_getAlpha3CountryCode('C.dsfinvk_country');

		$cols = array(
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "zerstellung","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "LOC_NAME" => array("select" => "C.dsfinvk_name","colname" => "name","quote" => true),
		    "LOC_STRASSE" => array("select" => "C.dsfinvk_street","colname" => "street","quote" => true),
		    "LOC_PLZ" => array("select" => "C.dsfinvk_postalcode","colname" => "plz","quote" => true),
		    "LOC_ORT" => array("select" => "C.dsfinvk_city","colname" => "city","quote" => true),
		    "LOC_LAND" => array("select" => $loc,"colname" => "country","quote" => true),
		    "LOC_USTID" => array("select" => "C.dsfinvk_ustid","colname" => "ustid","quote" => true)
		);
		$sqltxt = self::createSqlSelects($cols, $fileToSave);
		$sql = "SELECT " . implode(",",$sqltxt) . " FROM %closing% C";
		$sql .= " LEFT JOIN %bill% B ON B.closingid=C.id ";
		$sql .= " GROUP BY C.id ";
		$sql .= " ORDER BY clsid";
		
		$dataPart = self::createCsvDataPartByCursor($pdo, $sql, $cols, $fileToSave, 'stammorte');
		return $dataPart;
	}
	
	private static function stammkassen($pdo,$fileToSave) {
		$paydeskid = CommonUtils::getConfigValue($pdo, 'paydeskid', 1);
		$ztime = "IF(C.closingdate is not null,CONCAT(DATE(C.closingdate),'T',TIME(C.closingdate)),'')";
		$sn = CommonUtils::getConfigValue($pdo, 'sn', '');
		$currency = CommonUtils::getCurrencyAsIsoVal($pdo);
		
		$cols = array(
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "zerstellung","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "KASSE_BRAND" => array("select" => "'OrderSprinter'","colname" => "brand","quote" => true),
		    "KASSE_MODELL" => array("select" => "'OrderSprinter'","colname" => "modell","quote" => true),
		    "KASSE_SERIENNR" => array("select" => "'$sn'","colname" => "sn","quote" => true),
		    "KASSE_SW_BRAND" => array("select" => "'OrderSprinter'","colname" => "swbrand","quote" => true),
		    "KASSE_SW_VERSION" => array("select" => "C.version","colname" => "swversion","quote" => true),
		    "KASSE_BASISWAEH_CODE" => array("select" => "'$currency'","colname" => "basiswae","quote" => false),
		    "KEINE_UST_ZUORDNUNG" => array("select" => "''","colname" => "ustassign","quote" => false)
		);
		$sqltxt = self::createSqlSelects($cols, $fileToSave);
		$sql = "SELECT " . implode(",",$sqltxt) . " FROM %closing% C";
		
		$dataPart = self::createCsvDataPartByCursor($pdo, $sql, $cols, $fileToSave, 'stammkassen');
		return $dataPart;
	}
	
	private static function stammterminals($pdo,$fileToSave) {
		$paydeskid = CommonUtils::getConfigValue($pdo, 'paydeskid', 1);
		$ztime = "IF(C.closingdate is not null,CONCAT(DATE(C.closingdate),'T',TIME(C.closingdate)),'')";
		
		$cols = array(
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "zerstellung","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "TERMINAL_ID" => array("select" => "COALESCE(C.terminalid,'')","colname" => "terminalid","quote" => false),
		    "TERMINAL_BRAND" => array("select" => "'Browser'","colname" => "terminalbrand","quote" => true),
		    "TERMINAL_MODELL" => array("select" => "CONCAT(T.browser,'-',T.platform)","colname" => "terminalmodell","quote" => true),
		    "TERMINAL_SERIENNR" => array("select" => "T.useragent","colname" => "terminalsn","quote" => true),
		    "TERMINAL_SW_BRAND" => array("select" => "''","colname" => "terminalswbrand","quote" => true),
		    "TERMINAL_SW_VERSION" => array("select" => "T.version","colname" => "terminalswversion","quote" => true)
		);
		$sqltxt = self::createSqlSelects($cols, $fileToSave);
		$sql = "SELECT " . implode(",",$sqltxt) . " FROM %closing% C";
		$sql .= " LEFT JOIN %terminals% T ON C.terminalid=T.id ";
		$sql .= " ORDER BY T.id ";
		
		$dataPart = self::createCsvDataPartByCursor($pdo, $sql, $cols, $fileToSave, 'stammterminals');
		return $dataPart;
	}
	
	private static function stammagenturen($pdo,$fileToSave) {
		$paydeskid = CommonUtils::getConfigValue($pdo, 'paydeskid', 1);
		$ztime = "IF(C.closingdate is not null,CONCAT(DATE(C.closingdate),'T',TIME(C.closingdate)),'')";
		
		$cols = array(
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "zerstellung","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "AGENTUR_ID" => array("select" => "''","colname" => "agenturid","quote" => false),
		    "AGENTUR_NAME" => array("select" => "''","colname" => "agenturname","quote" => true),
		    "AGENTUR_STRASSE" => array("select" => "''","colname" => "agenturstreet","quote" => true),
		    "AGENTUR_PLZ" => array("select" => "''","colname" => "agenturplz","quote" => true),
		    "AGENTUR_ORT" => array("select" => "''","colname" => "agenturcity","quote" => true),
		    "AGENTUR_LAND" => array("select" => "''","colname" => "agenturciuntry","quote" => true),
		    "AGENTUR_STNR" => array("select" => "''","colname" => "agenturstnr","quote" => true),
		    "AGENTUR_USTID" => array("select" => "''","colname" => "agenturustid","quote" => true)
		);
		$sqltxt = self::createSqlSelects($cols, $fileToSave);
		$sql = "SELECT " . implode(",",$sqltxt) . " FROM %closing% C";
		$sql .= " LEFT JOIN %terminals% T ON C.terminalid=T.id ";
		$sql .= " ORDER BY T.id,clsid ";
		
		$dataPart = self::createCsvDataPartByCursor($pdo, $sql, $cols, $fileToSave, 'stammagenturen');
		return $dataPart;
	}
	
	private static function _stammust_helper_getTaxPart($pdo,$taxkey,$fileToSave,$paydeskid,$ztime) {
		$taxVal = CommonUtils::getTaxFromKey($pdo, $taxkey);
		$taxDesc = CommonUtils::getTaxDescriptionFromKey($pdo, $taxkey);
		$colsTaxSet5 = array(
			"Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
			"Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "zerstellung","quote" => false),
			"Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
			"UST_SCHLUESSEL" => array("select" => "'$taxkey'","colname" => "akey","quote" => false),
			"UST_SATZ" => array("select" => "ROUND(CAST('$taxVal' as DECIMAL(7,2)),2)","colname" => "avalue","quote" => false),
			"UST_BESCHR" => array("select" => "'$taxDesc'","colname" => "aname","quote" => false),
		);
		return "SELECT " . implode(',',self::createSqlSelects($colsTaxSet5, $fileToSave)) . " FROM %closing% C ";
	}
	
	private static function stammust($pdo,$fileToSave) {
		$paydeskid = CommonUtils::getConfigValue($pdo, 'paydeskid', 1);
		$ztime = "IF(C.closingdate is not null,CONCAT(DATE(C.closingdate),'T',TIME(C.closingdate)),'')";
		
		$taxKey1Descr = CommonUtils::getTaxDescriptionFromKey($pdo, 1);
		$taxKey2Descr = CommonUtils::getTaxDescriptionFromKey($pdo, 2);

		$colsTaxSet1 = array(
			"Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
			"Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "zerstellung","quote" => false),
			"Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
			"UST_SCHLUESSEL" => array("select" => "'1'","colname" => "akey","quote" => false),
			"UST_SATZ" => array("select" => "ROUND(C.taxset1,2)","colname" => "avalue","quote" => false),
			"UST_BESCHR" => array("select" => "'$taxKey1Descr'","colname" => "aname","quote" => false),
		);
		$sqlParts1 = "SELECT " . implode(',',self::createSqlSelects($colsTaxSet1, $fileToSave)) . " FROM %closing% C ";
		$colsTaxSet2 = array(
			"Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
			"Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "zerstellung","quote" => false),
			"Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
			"UST_SCHLUESSEL" => array("select" => "'2'","colname" => "akey","quote" => false),
			"UST_SATZ" => array("select" => "ROUND(C.taxset2,2)","colname" => "avalue","quote" => false),
			"UST_BESCHR" => array("select" => "'$taxKey2Descr'","colname" => "aname","quote" => false),
		);
		$sqlParts2 = "SELECT " . implode(',',self::createSqlSelects($colsTaxSet2, $fileToSave)) . " FROM %closing% C ";
		
		$sqlParts5 = self::_stammust_helper_getTaxPart($pdo, 5, $fileToSave, $paydeskid, $ztime);
		$sqlParts11 = self::_stammust_helper_getTaxPart($pdo, 11, $fileToSave, $paydeskid, $ztime);
		$sqlParts12 = self::_stammust_helper_getTaxPart($pdo, 12, $fileToSave, $paydeskid, $ztime);
		$sqlParts21 = self::_stammust_helper_getTaxPart($pdo, 21, $fileToSave, $paydeskid, $ztime);
		$sqlParts22 = self::_stammust_helper_getTaxPart($pdo, 22, $fileToSave, $paydeskid, $ztime);
		
		$ustkeys = implode(" UNION ALL ",array($sqlParts1,$sqlParts2,$sqlParts5,$sqlParts11,$sqlParts12,$sqlParts21,$sqlParts22));
	
		$sql = "SELECT * FROM (";
		$sql .= $ustkeys;
		$sql .= " ) AS i ";
		$sql .= " ORDER BY (CAST(i.clsid AS UNSIGNED)),(CAST(i.akey AS UNSIGNED))";
		$dataPart = self::createCsvDataPartByCursor($pdo, $sql, $colsTaxSet1, $fileToSave, 'stammust');
		return $dataPart;
	}
	
	private static function stammtse($pdo,$fileToSave) {
		$paydeskid = CommonUtils::getConfigValue($pdo, 'paydeskid', 1);
		$ztime = "IF(C.closingdate is not null,CONCAT(DATE(C.closingdate),'T',TIME(C.closingdate)),'')";
		
		$sigalg = "(SELECT textvalue as sigalg FROM %tsevalues% TSE WHERE TSE.id=O.sigalg)";
		$serialno = "(SELECT textvalue as serialno FROM %tsevalues% TSE WHERE TSE.id=O.serialno)";
		$pubkey = "(SELECT textvalue as publickey FROM %tsevalues% TSE WHERE TSE.id=O.pubkey)";
		$certificate1 = "(SELECT SUBSTRING(textvalue,1,1000) as certificate FROM %tsevalues% TSE WHERE TSE.id=O.certificate)";
		$certificate2 = "(SELECT SUBSTRING(textvalue,1001) as certificate FROM %tsevalues% TSE WHERE TSE.id=O.certificate)";
		$cols = array(
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "zerstellung","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "TSE_ID" => array("select" => "'1'","colname" => "tseid","quote" => false),
		    "TSE_SERIAL" => array("select" => "$serialno","colname" => "tseseial","quote" => true),
		    "TSE_SIG_ALGO" => array("select" => "$sigalg","colname" => "sigalgo","quote" => true),
		    "TSE_ZEITFORMAT" => array("select" => "'unixTime'","colname" => "tsezeitformat","quote" => true),
		    "TSE_PD_ENCODING" => array("select" => "'UTF-8'","colname" => "encoding","quote" => true),
		    "TSE_PUBLIC_KEY" => array("select" => $pubkey,"colname" => "pubkey","quote" => true),
		    "TSE_ZERTIFIKAT_I" => array("select" => $certificate1,"colname" => "cert1","quote" => true),
		    "TSE_ZERTIFIKAT_II" => array("select" => $certificate2,"colname" => "cert2","quote" => true)
		);
		$sqltxt = self::createSqlSelects($cols, $fileToSave);
		$sql = "SELECT " . implode(",",$sqltxt) . " FROM %closing% C ";
		$sql .= " LEFT JOIN %operations% O ON O.id=C.opid ";
		$sql .= " ORDER BY clsid";
		
		$dataPart = self::createCsvDataPartByCursor($pdo, $sql, $cols, $fileToSave, 'stammtse');
		return $dataPart;
	}
	
	private static function _zgvtyp_helper_getonlycols() {
		$cols = array(
		    "Z_KASSE_ID" => array("select" => "''","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "''","colname" => "zerstellung","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "GV_TYP" => array("select" => "''","colname" => "gvtyp","quote" => true),
		    "GV_NAME" => array("select" => "''","colname" => "gvname","quote" => true),
		    "AGENTUR_ID" => array("select" => "'0'","colname" => "agenturid","quote" => true),
		    "UST_SCHLUESSEL" => array("select" => "'5'","colname" => "ustkey","quote" => false),
		    "Z_UMS_BRUTTO" => array("select" => "ROUND(SUM(B.brutto),5)","colname" => "brutto","quote" => false),
		    "Z_UMS_NETTO" => array("select" => "ROUND(SUM(B.brutto),5)","colname" => "netto","quote" => false),
		    "Z_UST" => array("select" => "ROUND('0.00',5)","colname" => "zust","quote" => false)
		);
		return $cols;
	}
	private static function _zgvtyp_helper_billsSums($fileToSave,$paydeskid,$ztime,$gvtyp) {
		$gvtypelabel = $gvtyp['label'];
		$cashtype = $gvtyp['cashtype'];
		$cols = array(
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "zerstellung","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "GV_TYP" => array("select" => "'$gvtypelabel'","colname" => "gvtyp","quote" => true),
		    "GV_NAME" => array("select" => "''","colname" => "gvname","quote" => true),
		    "AGENTUR_ID" => array("select" => "'0'","colname" => "agenturid","quote" => true),
		    "UST_SCHLUESSEL" => array("select" => "'5'","colname" => "ustkey","quote" => false),
		    "Z_UMS_BRUTTO" => array("select" => "ROUND(SUM(B.brutto),5)","colname" => "brutto","quote" => false),
		    "Z_UMS_NETTO" => array("select" => "ROUND(SUM(B.brutto),5)","colname" => "netto","quote" => false),
		    "Z_UST" => array("select" => "'0.00000'","colname" => "zust","quote" => false)
		);
		$sqltxt = self::createSqlSelects($cols, $fileToSave);
		$sql = "SELECT " . implode(",",$sqltxt) . " FROM %closing% C ";
		$sql .= " INNER JOIN %bill% B ON B.closingid=C.id ";
		$sql .= " WHERE B.status='c' AND B.cashtype='$cashtype' ";
		$sql .= " GROUP BY C.id ";
		return $sql;
	}
	private static function _zgvtyp_helper_prodSums($fileToSave,$paydeskid,$ztime,$gvtyp) {
		$gvtypelabel = $gvtyp['label'];
		$taxkey = $gvtyp['taxkey'];
		$ordertype = $gvtyp['ordertype'];
		$cols = array(
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "zerstellung","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "GV_TYP" => array("select" => "'$gvtypelabel'","colname" => "gvtyp","quote" => true),
		    "GV_NAME" => array("select" => "''","colname" => "gvname","quote" => true),
		    "AGENTUR_ID" => array("select" => "'0'","colname" => "agenturid","quote" => true),
		    "UST_SCHLUESSEL" => array("select" => "'$taxkey'","colname" => "ustkey","quote" => false),
		    "Z_UMS_BRUTTO" => array("select" => "ROUND(SUM(Q.price),5)","colname" => "brutto","quote" => false),
		    "Z_UMS_NETTO" => array("select" => "round(SUM(price / (1.0 + Q.tax/100.0)),5)","colname" => "netto","quote" => false),
		    "Z_UST" => array("select" => "ROUND(SUM(Q.price - price / (1.0 + Q.tax/100.0)),5)","colname" => "zust","quote" => false)
		);
		$sqltxt = self::createSqlSelects($cols, $fileToSave);
		$sql = "SELECT " . implode(",",$sqltxt) . " FROM %closing% C ";
		$sql .= " INNER JOIN %bill% B ON B.closingid=C.id ";
		$sql .= " INNER JOIN %billproducts% BP ON BP.billid=B.id ";
		$sql .= " INNER JOIN %queue% Q ON Q.id=BP.queueid ";
		$sql .= " WHERE (B.status is null OR B.status <> 'c') AND Q.taxkey='$taxkey' AND Q.ordertype='$ordertype'";
		$sql .= " GROUP BY C.id ";
		return $sql;
	}
	private static function zgvtyp($pdo,$fileToSave) {
		$paydeskid = CommonUtils::getConfigValue($pdo, 'paydeskid', 1);
		$ztime = "IF(C.closingdate is not null,CONCAT(DATE(C.closingdate),'T',TIME(C.closingdate)),'')";

		$colsPrivateinlage = self::_zgvtyp_helper_billsSums($fileToSave, $paydeskid, $ztime, array("label" => "Privateinlage", "cashtype" => Bill::$CASHTYPE_Privateinlage["value"]));
		$colsPrivatentnahme = self::_zgvtyp_helper_billsSums($fileToSave, $paydeskid, $ztime, array("label" => "Privatentnahme", "cashtype" => Bill::$CASHTYPE_Privatentnahme["value"]));
		$colsGeldtransit = self::_zgvtyp_helper_billsSums($fileToSave, $paydeskid, $ztime, array("label" => "Geldtransit", "cashtype" => Bill::$CASHTYPE_Geldtransit["value"]));
		$colsEinzahlung = self::_zgvtyp_helper_billsSums($fileToSave, $paydeskid, $ztime, array("label" => "Einzahlung", "cashtype" => Bill::$CASHTYPE_Einzahlung["value"]));
		$colsAuszahlung = self::_zgvtyp_helper_billsSums($fileToSave, $paydeskid, $ztime, array("label" => "Auszahlung", "cashtype" => Bill::$CASHTYPE_Auszahlung["value"]));
		$colsTrinkgeldAN= self::_zgvtyp_helper_billsSums($fileToSave, $paydeskid, $ztime, array("label" => "TrinkgeldAN", "cashtype" => Bill::$CASHTYPE_TrinkgeldAN["value"]));
		$colsTrinkgeldAG= self::_zgvtyp_helper_billsSums($fileToSave, $paydeskid, $ztime, array("label" => "TrinkgeldAG", "cashtype" => Bill::$CASHTYPE_TrinkgeldAG["value"]));
		$colsDiffSollIst = self::_zgvtyp_helper_billsSums($fileToSave, $paydeskid, $ztime, array("label" => "DifferenzSollIst", "cashtype" => Bill::$CASHTYPE_DifferenzSollIst["value"]));
		
		$colsProdSumsUmsatzKey1 = self::_zgvtyp_helper_prodSums($fileToSave, $paydeskid, $ztime, array("label" => "Umsatz","taxkey" => 1, "ordertype" => DbUtils::$ORDERTYPE_PRODUCT));
		$colsProdSumsUmsatzKey2 = self::_zgvtyp_helper_prodSums($fileToSave, $paydeskid, $ztime, array("label" => "Umsatz","taxkey" => 2, "ordertype" => DbUtils::$ORDERTYPE_PRODUCT));
		$colsProdSumsUmsatzKey5 = self::_zgvtyp_helper_prodSums($fileToSave, $paydeskid, $ztime, array("label" => "Umsatz","taxkey" => 5, "ordertype" => DbUtils::$ORDERTYPE_PRODUCT));
		$colsProdSumsUmsatzKey11 = self::_zgvtyp_helper_prodSums($fileToSave, $paydeskid, $ztime, array("label" => "Umsatz","taxkey" => 11, "ordertype" => DbUtils::$ORDERTYPE_PRODUCT));
		$colsProdSumsUmsatzKey12 = self::_zgvtyp_helper_prodSums($fileToSave, $paydeskid, $ztime, array("label" => "Umsatz","taxkey" => 12, "ordertype" => DbUtils::$ORDERTYPE_PRODUCT));
		$colsProdSumsUmsatzKey21 = self::_zgvtyp_helper_prodSums($fileToSave, $paydeskid, $ztime, array("label" => "Umsatz","taxkey" => 21, "ordertype" => DbUtils::$ORDERTYPE_PRODUCT));
		$colsProdSumsUmsatzKey22 = self::_zgvtyp_helper_prodSums($fileToSave, $paydeskid, $ztime, array("label" => "Umsatz","taxkey" => 22, "ordertype" => DbUtils::$ORDERTYPE_PRODUCT));
		
		$colsVoucherSumsUmsatzKey1 = self::_zgvtyp_helper_prodSums($fileToSave, $paydeskid, $ztime, array("label" => "EinzweckgutscheinKauf","taxkey" => 1, "ordertype" => DbUtils::$ORDERTYPE_1ZweckKauf));
		$colsVoucherSumsUmsatzKey2 = self::_zgvtyp_helper_prodSums($fileToSave, $paydeskid, $ztime, array("label" => "EinzweckgutscheinKauf","taxkey" => 2, "ordertype" => DbUtils::$ORDERTYPE_1ZweckKauf));
		$colsVoucherSumsUmsatzKey5 = self::_zgvtyp_helper_prodSums($fileToSave, $paydeskid, $ztime, array("label" => "EinzweckgutscheinKauf","taxkey" => 5, "ordertype" => DbUtils::$ORDERTYPE_1ZweckKauf));
		$colsVoucherSumsUmsatzKey11 = self::_zgvtyp_helper_prodSums($fileToSave, $paydeskid, $ztime, array("label" => "EinzweckgutscheinKauf","taxkey" => 11, "ordertype" => DbUtils::$ORDERTYPE_1ZweckKauf));
		$colsVoucherSumsUmsatzKey12 = self::_zgvtyp_helper_prodSums($fileToSave, $paydeskid, $ztime, array("label" => "EinzweckgutscheinKauf","taxkey" => 12, "ordertype" => DbUtils::$ORDERTYPE_1ZweckKauf));
		$colsVoucherSumsUmsatzKey21 = self::_zgvtyp_helper_prodSums($fileToSave, $paydeskid, $ztime, array("label" => "EinzweckgutscheinKauf","taxkey" => 21, "ordertype" => DbUtils::$ORDERTYPE_1ZweckKauf));
		$colsVoucherSumsUmsatzKey22 = self::_zgvtyp_helper_prodSums($fileToSave, $paydeskid, $ztime, array("label" => "EinzweckgutscheinKauf","taxkey" => 22, "ordertype" => DbUtils::$ORDERTYPE_1ZweckKauf));
		
		$colsVoucherEinlSumsUmsatzKey5 = self::_zgvtyp_helper_prodSums($fileToSave, $paydeskid, $ztime, array("label" => "EinzweckgutscheinEinloesung","taxkey" => 5, "ordertype" => DbUtils::$ORDERTYPE_1ZweckEinl));
		

		$allCols = implode(" UNION ALL ",array(
		    $colsPrivateinlage,$colsPrivatentnahme,
		    $colsGeldtransit,
		    $colsEinzahlung,$colsAuszahlung,
		    $colsTrinkgeldAN,$colsTrinkgeldAG,
		    $colsDiffSollIst,
		    $colsProdSumsUmsatzKey1,$colsProdSumsUmsatzKey2,$colsProdSumsUmsatzKey5,$colsProdSumsUmsatzKey11,$colsProdSumsUmsatzKey12,$colsProdSumsUmsatzKey21,$colsProdSumsUmsatzKey22,
		    $colsVoucherSumsUmsatzKey1,$colsVoucherSumsUmsatzKey2,$colsVoucherSumsUmsatzKey5,$colsVoucherSumsUmsatzKey11,$colsVoucherSumsUmsatzKey12,$colsVoucherSumsUmsatzKey21,$colsVoucherSumsUmsatzKey22,
		    $colsVoucherEinlSumsUmsatzKey5
		));
		
		$sql = "SELECT * FROM (";
		$sql .= $allCols;
		$sql .= " ) AS i ";
		$sql .= " ORDER BY (CAST(i.clsid AS UNSIGNED)),(CAST(i.gvtyp AS UNSIGNED)),(CAST(i.ustkey AS UNSIGNED))";
		
		$dataPart = self::createCsvDataPartByCursor($pdo, $sql, self::_zgvtyp_helper_getonlycols(), $fileToSave, 'zgvtyp');
		return $dataPart;
	}
	
	private static function zzahlart($pdo,$fileToSave) {
		$paydeskid = CommonUtils::getConfigValue($pdo, 'paydeskid', 1);
		$ztime = "IF(C.closingdate is not null,CONCAT(DATE(C.closingdate),'T',TIME(C.closingdate)),'')";

		$zahlarttyp = "(CASE WHEN B.paymentid='1' THEN 'Bar' WHEN B.paymentid='2' THEN 'ECKarte' WHEN B.paymentid='3' THEN 'Kreditkarte' ELSE 'Keine' END)";
		$cols = array(
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "zerstellung","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "ZAHLART_TYP" => array("select" => "$zahlarttyp","colname" => "paymenttype","quote" => true),
		    "ZAHLART_NAME" => array("select" => "''","colname" => "paymentname","quote" => true),
		    "Z_ZAHLART_BETRAG" => array("select" => "ROUND(SUM(B.brutto),2)","colname" => "paymentsum","quote" => false)
		);
		$sqltxt = self::createSqlSelects($cols, $fileToSave);
		$allCols = "SELECT " . implode(",",$sqltxt) . " FROM %closing% C ";
		$allCols .= " INNER JOIN %bill% B ON B.closingid=C.id ";
		$allCols .= " WHERE B.paymentid='1' OR B.paymentid='2' OR B.paymentid='3'";
		$allCols .= " GROUP BY C.id,B.paymentid ";
		
		$sql = "SELECT * FROM (";
		$sql .= $allCols;
		$sql .= " ) AS i ";
		$sql .= " ORDER BY (CAST(i.clsid AS UNSIGNED)),paymenttype";
		
		$dataPart = self::createCsvDataPartByCursor($pdo, $sql, $cols, $fileToSave, 'zzahlart');
		return $dataPart;
	}
	
	private static function zwaehrungen($pdo,$fileToSave) {
		$paydeskid = CommonUtils::getConfigValue($pdo, 'paydeskid', 1);
		$ztime = "IF(C.closingdate is not null,CONCAT(DATE(C.closingdate),'T',TIME(C.closingdate)),'')";
		$currency = CommonUtils::getConfigValue($pdo, 'currency', '');
		if (in_array(strtoupper($currency), array("EURO","EUR","E","€"))) {
			$currency = "EUR";
		}
		
		$cols = array(
		    "Z_KASSE_ID" => array("select" => "'$paydeskid'","colname" => "paydeskid","quote" => true),
		    "Z_ERSTELLUNG" => array("select" => "$ztime","colname" => "zerstellung","quote" => false),
		    "Z_NR" => array("select" => "COALESCE(C.id,'')","colname" => "clsid","quote" => false),
		    "ZAHLART_WAEH" => array("select" => "'$currency'","colname" => "paymentcurrency","quote" => true),
		    "ZAHLART_BETRAG_WAEH" => array("select" => "ROUND(counted,2)","colname" => "cashsum","quote" => false)
		);
		$sqltxt = self::createSqlSelects($cols, $fileToSave);
		$allCols = "SELECT " . implode(",",$sqltxt) . " FROM %closing% C ";
		$allCols .= " GROUP BY C.id ";
		
		$sql = "SELECT * FROM (";
		$sql .= $allCols;
		$sql .= " ) AS i ";
		$sql .= " ORDER BY (CAST(i.clsid AS UNSIGNED)),paymentcurrency";
		
		$dataPart = self::createCsvDataPartByCursor($pdo, $sql, $cols, $fileToSave, 'zwaehrungen');
		return $dataPart;
	}
	
	private static function createCsvHeader($cols,$doHtml,$tablename) {
		$startOfRow = "";
		$endOfRow = "";
		$sep = ";";
		if ($doHtml) {
			$startOfRow = '<tr style="display:\"\";" onclick="javascript:showsection(\'' . $tablename . '\'); return false"><th>';
			$endOfRow = "</tr>";
			$sep = "";
		}
		$colArray = array();
		$keys = array_keys($cols);
		foreach($keys as $k) {
			if ($doHtml) {
				$colArray[] = "<th>" . $k;
			} else {
				if (!isset($cols[$k]["suspect"]) || $cols[$k]["suspect"] == 0) {
					$colArray[] = $k;
				}
			}
		}
		
		return $startOfRow . implode($sep,$colArray) . $endOfRow;
	}
	private static function createCsvDataRow($cols,$entry, $doHtml,$tablename,$linenumber) {
		$startOfRow = "";
		$endOfRow = "";
		$sep = ";";
		if ($doHtml) {
			$startOfRow = "<tr class='" . $tablename . "'><td>" . $linenumber;
			$endOfRow = "</tr>";
			$sep = "";
		}
		$colArray = array();
		$keys = array_keys($cols);
		
		foreach($keys as $k) {
			$dataEntry = $entry[$cols[$k]["colname"]];
                        
			if ($cols[$k]["quote"]) {
				$dataEntry = "\"" . str_replace("\"", "\"\"", $dataEntry) . "\"";
			}
			if ($doHtml) {
				if (isset($cols[$k]["suspect"]) && ($cols[$k]["suspect"] == 1)) {
					$colArray[] = "<td class='suspect'>" . $dataEntry;
				} else {
					$colArray[] = "<td>" . $dataEntry;
				}
			} else {
				if (!isset($cols[$k]["suspect"]) || ($cols[$k]["suspect"] == 0)) {
					$colArray[] = $dataEntry;
				}
			}
		}
		return $startOfRow . implode($sep,$colArray) . $endOfRow;
	}

	private static function createCsvDataPartByCursor($pdo,$sql,$cols,$fileToSave,$tablename) {
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql),array(PDO::ATTR_CURSOR => PDO::CURSOR_SCROLL));
		$stmt->execute();
		$doHtml = (is_null($fileToSave) ? true : false);
		$header = self::createCsvHeader($cols,$doHtml,$tablename);
		if (is_null($fileToSave)) {
			$dataRows = array();
			$linenumber = 1;
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
				$data = self::createCsvDataRow($cols, $row, $doHtml,$tablename,$linenumber);
				$dataRows[] = $data;
				$linenumber++;
			}
			$stmt->closeCursor();
			$stmt = null;
			return $header . "\n" . implode("\n",$dataRows);
		} else {
			if (file_exists($fileToSave)) {
				try {
					unlink($fileToSave);
				} catch (Exception $ex) {
					exit("File " . $fileToSave . " could not be deleted");
				}
			}
			file_put_contents($fileToSave, $header . "\n", FILE_APPEND | LOCK_EX);
			while ($row = $stmt->fetch(PDO::FETCH_ASSOC, PDO::FETCH_ORI_NEXT)) {
				$data = self::createCsvDataRow($cols, $row, $doHtml,$tablename,0) . "\n";
				try {
					file_put_contents($fileToSave, $data, FILE_APPEND | LOCK_EX);
				} catch (Exception $ex) {
					exit ("In csv File " . $fileToSave . " kann nicht geschrieben werden: " . $ex->getMessage());
				}
			}
			$stmt->closeCursor();
			$stmt = null;
			return '';
		}
	}
	
	private static function getPage() {
		$txt = "<!doctype html>\n";
		$txt .= "<html><head><meta charset=\"utf-8\">\n";
		$txt .= "<title>DSFinv-k Export</title>\n";
		$txt .= self::getScript();
		$txt .= self::getStyle();
		$txt .= "</head>\n";
		$txt .= "<body>\n";
		return $txt;
	}
	private static function getStyle() {
		$txt = "<style>
			td.suspect {
				color:black !important;
				background-color:#f5b8ad !important;
			}\n
			table.viewtable {
	width: 100%;
	display: block;
	table-layout: fixed;
}
table.viewtable th {
	background:#7abe5f;
	font-size:10pt;
	border-bottom:1px solid rgba(255,255,255,.7);
	-webkit-box-shadow:inset 0 1px 0 rgba(255,255,255,.2);
	-moz-box-shadow:inset 0 1px 0 rgba(255,255,255,.2);
	-o-box-shadow:inset 0 1px 0 rgba(255,255,255,.2);
	box-shadow:inset 0 1px 0 rgba(255,255,255,.2);
	padding:6px 10px;}

table.viewtable td {
	background:#eed33f; 
	font-size:8pt;
	border-bottom:1px solid #fafafa;
	border-bottom:1px solid rgba(255,255,255,.5);
	padding:6px 10px;
	color:rgba(0,0,0,255);}</style>\n";
		return $txt;
	}
	
	private static function getScript() {
		$txt = "<script>
			function showsection(tablename) {
				var elems = document.getElementsByClassName(tablename);
				//elems.item(0).style.display='none';
				
				if (elems.length > 1) {
					var status = elems.item(0).style.display;
					for (var i=0;i<elems.length;i++) {
						elems.item(i).style.display = (status==''?'none':'');
					}
				}				
			};
			
			</script>\n
			<noscript>
				Sie haben JavaScript deaktiviert.
			</noscript>";
		return $txt;
	}
	
	private static function createSqlSelects($cols,$fileToSave) {
		$sqltxt = array();
		foreach($cols as $c) {
			if (!is_null($fileToSave)) {
				if (!isset($c["suspect"]) || $c["suspect"] == 0) {
					$sqltxt[] = $c["select"] . " as " . $c["colname"];
				}
			} else {
				$sqltxt[] = $c["select"] . " as " . $c["colname"];
			}
		}
		return $sqltxt;
	}
}
