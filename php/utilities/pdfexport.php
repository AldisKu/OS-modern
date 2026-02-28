<?php

require_once (__DIR__. '/../3rdparty/fpdf/fpdf.php');
require_once (__DIR__. '/../closing.php');


class PdfExport extends FPDF {
	private $tabheader = array();
	private $flengths = array();
	private $maxTableLength = 190;
	private $curtable = "";
	private $lang = 0;
	private $decpoint = ":";
	private $currency = "";
	private $version;
	
	private $osSummary = array("Zusammenfassung","Summary","Todo");
	private $osClosingsPlural = array("Tageserfassungen","Closings","Cerradas");
	private $osSum = array("Summe (alles)","Sum (all)","Suma (todo)");
        private $osSumSales = array("Summe (Verkauf+Trinkg.AG)","Sum (sales+tips)","Suma (venta+propinas)");
	private $osSumAll = array("Gesamtsumme","Total Sum","Todos las sumas");
	private $osClosingTxt = array("Tagesabschluss","Closing","Conclusión");
	private $hintNoEmptyClosings = array("Übersicht der Tageserfassungen mit eingerechneten Geldtransits - detaillierter Umsatzreport über CSV/Excel-Export möglich",
			"Overview of closings incl. transits - detailed sales report possible with csv/Excel export",
			"Conclusiones en este periodo - más informaciones en el csv/Excel-export"
	);
	private $taxTxt = array("Steuersatz (%)","Tax (%)","Impuesto (%)");
	private $taxSumTxt = array("Summierung nach Steuersätzen (alles)","Sums grouped by taxes (all)","Sumas por impuesta (todo)");
        private $salesTaxSumTxt = array("Summierung nach Steuersätzen (nur Verkäufe + Trinkg.AG)","Sums grouped by taxes (only sales + tips)","Sumas por impuesta (solo ventas + propina)");
	private $cashOpTxt = array("Kassenein-/auslagen","Cash inserts/outputs","contribuciones en efectivo");
	
	private static function osGetMonthName($language,$month) {
		$months = array("1" => array("Januar","January","Enero"),
				"2" => array("Februar","February","Febrero"),
				"3" => array("März","March","Marzo"),
				"4" => array("April","April","Abril"),
				"5" => array("Mai","May","Mayo"),
				"6" => array("Juni","June","Junio"),
				"7" => array("Juli","July","Julio"),
				"8" => array("August","August","Agosto"),
				"9" => array("September","September","Septiembre"),
				"10" => array("Oktober","October","Octubre"),
				"11" => array("November","November","Noviembre"),
				"12" => array("Dezember","December","Diciembre")
		);
		return utf8_decode($months["$month"][$language]);
	}
	
	private static function osGetSaleItemName($l,$item) {
		$t = array(
				"TEID" => array("Tag.abschl.","Closing","Cerrada"),
				"ID" => array("Bonid","ID","Nú"),
				"Date" => array("Zahlungsdatum","Pay date","Fecha de pago"),
				"Prod" => array("Produkt","Product","Producto"),
				"Brutto" => array("Bruttopreis","Gross","Bruto"),
				"Netto" => array("Nettopreis","Net","Neto"),
				"Tax" => array("MwSt (%)","Tax (%)","IVA (%)"),
				"PayWay" => array("Zahlungsart","Method of payment","Modo de pago"),
				"Userid" => array("Benutzer-ID","User id","Id del usario"),
				"User" => array("Benutzername","User name","Nombre del usario"),
				"State" => array("Status","State","Estado"),
				"Ref" => array("Ref.-Bon","Ref. Receipt","Tique ref."),
				"ClosId" => array("Tageslosung-ID","Closing id","Número de cerramiento"),
				"ClosDate" => array("Tageslosung-Datum","Closing date","Fecha de cerramiento"),
				"ClosRemark" => array("Tageslosung-Bemerkung","Closing remark","Comentario de cerramiento"),
				"laterCancelled" => array("nachher storniert","later cancelled","anulado después"),
				"storno" => array("Stornierungsbuchung","cancel transaction","acción anulada"),
				"cashact" => array("Bareinlage/-entnahme","cash action","sacar/insertar contado"),
                                "tipact" => array("Trinkgeld","Tip","Propina"),
                                "transit" => array("Geldtransit","Money transit","Transit"),
                                "einzahlung" => array("Einzahlung","Money take in","Einzahlung"),
                                "auszahlung" => array("Auszahlung","Money take out","Auszahlung"),
				"cashaction" => array("Kassenaktion","cash action","sacar/insertar contado"),
                                "diffsollist" => array("DiffSollIst","Difference to target","DiffSollIst"),
				"host" => array("Bew.bon","Guest","Repr."),
				"sum" => array("Summe","Sum","Todo")
				//"host" => array("Bewirtungsbeleg","Guest Invoice","Tique de gastos de representación")
		);
		return utf8_decode($t["$item"][$l]);
	}
	private static function getConfigItem($pdo,$item) {
		$sql = "SELECT setting FROM %config% WHERE name=?";
		$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
		$stmt->execute(array($item));
		$row = $stmt->fetchObject();
		return $row->setting;
	}
	
	private function osInsertHeader($pdo,$lang,$startMonth,$startYear,$endMonth,$endYear) {
		$this->Image("utilities/salesheader.png",30,10,70,40,"PNG","http://www.ordersprinter.de");
		
		$companyInfo = utf8_decode(self::getConfigItem($pdo,"companyinfo"));
		if (is_null($companyInfo)) {
			return;
		}

		$companyInfoParts = explode("\n",$companyInfo);
		
		$fontSizes = array(14,14,14,14,14,10,9,9);
		$boxSizes = array(10,10,10,10,10,9,7,6);
		
		$maxlines = 8;
		if (count($companyInfoParts) > $maxlines) {
			array_splice($companyInfoParts,$maxlines);
		}
		$this->SetFont('Helvetica','B',$fontSizes[count($companyInfoParts)-1]);
		
		$companyInfo = implode("\n",$companyInfoParts);
		$this->MultiCell(190-10,$boxSizes[count($companyInfoParts)-1],$companyInfo,0,"R",0);
		
		$this->SetXY(10,70);
	}
	
	private function calcStartDate($startMonth,$startYear) {
		if ($startMonth < 10) {
			$startMonth = "0" . $startMonth;
		}
		return ( $startYear . "-" . $startMonth . "-01 00:00:00");
	}
	
	private function calcEndDate($endMonth,$endYear) {
		if ($endMonth < 10) {
			$endMonth = "0" . $endMonth;
		}
		$endDate = $endYear . "-" . $endMonth . "-01";
		$lastdayOfMonth = date("t", strtotime($endDate));
		return($endYear . "-" . $endMonth . "-" . $lastdayOfMonth . " 23:59:59");
	}
	
	private function osGetSales($pdo,$l,$startMonth,$startYear,$endMonth,$endYear,$closidstart = null,$closidend = null) {
		$startDate = $this->calcStartDate($startMonth, $startYear);
		$endDate = $this->calcEndDate($endMonth, $endYear);

		$hline = array(
				self::osGetSaleItemName($l,"TEID"),
				self::osGetSaleItemName($l,"ID"),
				self::osGetSaleItemName($l,"Date"),
				self::osGetSaleItemName($l,"Brutto"),
				self::osGetSaleItemName($l,"Netto"),
				self::osGetSaleItemName($l,"State"),
				self::osGetSaleItemName($l,"Ref"),
				self::osGetSaleItemName($l,"host")
		);
		
		$allSaleLines = array();
		$allSaleLines[] = $hline;		
		
		$payment_lang = array("name","name_en","name_esp");
		$payment_col = $payment_lang[$l];
                
                $billuidSql = Bill::getBillUidSqlPart();
		
		if (is_null($closidstart)) {
			$sql = "SELECT DISTINCT B.id as dbbillid,$billuidSql as id,B.cashtype,B.signature,billdate,brutto,netto,IF(tax is not null, tax, '0.00') as tax,status,closingdate,remark,B.host,B.closingid,%payment%.$payment_col as payway,userid,ref,username FROM %bill% B,%closing%,%payment%,%user% ";
			$sql .= "WHERE closingid is not null AND V.closingid=%closing%.id ";
			$sql .= " AND B.paymentid=%payment%.id AND B.paymentid <> ? ";
			$sql .= " AND B.billdate BETWEEN ? AND ? ";
			$sql .= " AND B.userid = %user%.id ";
			$sql .= "ORDER BY billdate";
			
			$allsales = CommonUtils::fetchSqlAll($pdo, $sql, array(DbUtils::$PAYMENT_GUEST,$startDate,$endDate));
		} else {
			$sql = "SELECT DISTINCT B.id as dbbillid,$billuidSql as id,B.cashtype,B.signature,billdate,brutto,netto,IF(tax is not null, tax, '0.00') as tax,status,closingdate,remark,B.host,B.closingid,%payment%.$payment_col as payway,userid,ref,username FROM %bill% B,%closing%,%payment%,%user% ";
			$sql .= "WHERE closingid is not null AND B.closingid=%closing%.id ";
			$sql .= " AND B.paymentid=%payment%.id AND B.paymentid <> ? ";
			$sql .= " AND B.closingid BETWEEN ? AND ? ";
			$sql .= " AND B.userid = %user%.id ";
			$sql .= "ORDER BY billdate";
			
			$allsales = CommonUtils::fetchSqlAll($pdo, $sql, array(DbUtils::$PAYMENT_GUEST,$closidstart,$closidend));
		}

                $bruttosumSales = 0.0;
		$nettosumSales = 0.0;
                $bruttosumTips = 0.0;
		$nettosumTips = 0.0;
                $bruttosumTransits = 0.0;
		$nettosumTransits = 0.0;
                $bruttosumCashes = 0.0;
		$nettosumCashes = 0.0;
                $bruttosumDiffSollIst = 0.0;
		$nettosumDiffSollIst = 0.0;
                
		foreach($allsales as $zeile) {
			$closid = $zeile['closingid'];
			$billid = $zeile['id'];
			$billdate = $zeile['billdate'];
			
			$brutto_orig = $zeile['brutto'];
			$netto_orig = $zeile['netto'];

			$brutto = str_replace(".",$this->decpoint,$brutto_orig);
			$netto = str_replace(".",$this->decpoint,$netto_orig);
                        
			$signature = $zeile['signature'];
			$dbstatus = $zeile['status'];
			$status = $zeile['status'];
                        $cashtype = $zeile['cashtype'];
                        
                        if (is_null($status) || (is_null($cashtype) && (($status == 'c') || ($status == 'x') || ($status == 's') ))) {
                                $bruttosumSales += doubleval($brutto_orig);
                                $nettosumSales += doubleval($netto_orig);
                        } else if (($status == 'c') && (($cashtype == Bill::$CASHTYPE_TrinkgeldAG['value']) || ($cashtype == Bill::$CASHTYPE_TrinkgeldAN['value']))) {
                                $bruttosumTips += doubleval($brutto_orig);
                                $nettosumTips += doubleval($netto_orig);
                        } else if (($status == 'c') && ($cashtype == Bill::$CASHTYPE_Geldtransit['value'])) {
                                $bruttosumTransits += doubleval($brutto_orig);
                                $nettosumTransits += doubleval($netto_orig);
                        } else if (($status == 'c') && ($cashtype == Bill::$CASHTYPE_DifferenzSollIst['value'])) {
                                $bruttosumDiffSollIst += doubleval($brutto_orig);
                                $nettosumDiffSollIst += doubleval($netto_orig);
                        } else if (($status == 'c') && (
                                ($cashtype == Bill::$CASHTYPE_Einzahlung['value']) ||
                                ($cashtype == Bill::$CASHTYPE_Auszahlung['value']) ||
                                ($cashtype == Bill::$CASHTYPE_Privateinlage['value']) ||
                                ($cashtype == Bill::$CASHTYPE_Privatentnahme['value'])
                                )) {
                                $bruttosumCashes += doubleval($brutto_orig);
                                $nettosumCashes += doubleval($netto_orig);
                        } 
                        
			if ($status == 'x') {
				$status = self::osGetSaleItemName($l,"laterCancelled");
			} else if ($status == 's') {
				$status = self::osGetSaleItemName($l,"storno");
			} else if (($status == 'c') && ($cashtype == Bill::$CASHTYPE_TrinkgeldAG['value'] || $cashtype == Bill::$CASHTYPE_TrinkgeldAN['value'])) {
				$status = self::osGetSaleItemName($l,"tipact");
                        } else if (($status == 'c') && ($cashtype == Bill::$CASHTYPE_Geldtransit['value'])) {
				$status = self::osGetSaleItemName($l,"transit");
                        } else if (($status == 'c') && ($cashtype == Bill::$CASHTYPE_Auszahlung['value'])) {
				$status = self::osGetSaleItemName($l,"auszahlung");
                        } else if (($status == 'c') && ($cashtype == Bill::$CASHTYPE_Einzahlung['value'])) {
				$status = self::osGetSaleItemName($l,"einzahlung");
                        } else if (($status == 'c') && (($cashtype == Bill::$CASHTYPE_Privateinlage['value']) || ($cashtype == Bill::$CASHTYPE_Privatentnahme['value']))) {
                                $status = self::osGetSaleItemName($l,"cashact");
			} else if (($status == 'c') && ($cashtype == Bill::$CASHTYPE_DifferenzSollIst['value'])) {
                                $status = self::osGetSaleItemName($l,"diffsollist");
			} else {
				$status = "";
			}
			
			$ref = ($zeile['ref'] == null ? "" : $zeile['ref']);
			$userid = $zeile['userid'];
			$host = ($zeile['host'] == 1 ? "x" : "-");

			if (!CommonUtils::verifyBillByValues($pdo,$billdate, $brutto_orig, $netto_orig, $userid, $signature, $dbstatus)) {
				echo "Database is inconsistent! Bill $billid ";
				if ($zeile['status'] == "c") {
					echo '- a cash operation ("Bareinlage/Barauslage"). ';
				}
				return null;
			}
				
			if ($billid == null) {
				$billid = "-";
			}
			
			$aLine = array($closid,
					$billid,
					$billdate,
					$brutto,
					$netto,
					$status,
					$ref,
					$host
			);
			
			$allSaleLines[count($allSaleLines)] = $aLine;        
                }
		return array("all" => $allSaleLines,
                    "bruttosumSales" => $bruttosumSales,"nettosumSales" => $nettosumSales,
                    "bruttosumTips" => $bruttosumTips, "nettosumTips" => $nettosumTips,
                    "bruttosumTransits" => $bruttosumTransits, "nettosumTransits" => $nettosumTransits,
                    "bruttosumCashes" => $bruttosumCashes, "nettosumCashes" => $nettosumCashes,
                    "bruttosumDiffSollIst" => $bruttosumDiffSollIst, "nettosumDiffSollIst" => $nettosumDiffSollIst
                        );
	}
	
	private function osCalculateColsWidth($lengths) {
		$sum = 0;
		foreach($lengths as $l) {
			$sum += $l;
		}
		
		$f = 190.0 / $sum;
		$this->flengths = array();
		$this->maxTableLength = 0;
		foreach($lengths as $l) {
			$this->flengths[] = intval($l * $f);
			$this->maxTableLength += intval($l * $f);
		}
	}
	
	private function osInsertSales($pdo,$lang,$startMonth,$startYear,$endMonth,$endYear,$closidstart = null,$closidend = null) {
		$this->curtable = "sales";
                $allbills = $this->osGetSales($pdo,$lang,$startMonth,$startYear,$endMonth,$endYear,$closidstart,$closidend);
		$salesArr = $allbills['all'];
		
		$this->osCalculateColsWidth(array(4,4,10,5,5,10,4,4));
		
		$salestxt = array("Umsätze ","Sales ","Venta ");
		
		if (!is_null($closidstart)) {
			$headerLine = utf8_decode($this->osClosingsPlural[$lang]);
			$headerLine .= " $closidstart - $closidend ";
			$headerLine .= " (in " . self::getConfigItem($pdo,"currency") . ")";
		} else {
			$headerLine = utf8_decode($salestxt[$lang]);
			$headerLine .= self::osGetMonthName($lang, $startMonth) . " $startYear - " .  self::osGetMonthName($lang, $endMonth) . " $endYear";
			$headerLine .= " (in " . self::getConfigItem($pdo,"currency") . ")";
		}

		$this->SetFont('Helvetica','B',14);
		$this->Cell($this->maxTableLength,10,$headerLine,1,1,"C");
		
		$this->SetFont('Helvetica','',8);
		$this->tabheader = $salesArr[0];
		$this->setSalesTableHeader();
		
		$this->SetFillColor(230,230,255);
		$fill = 1;
		
                $bruttosum = 0.0;
                $nettosum = 0.0;
 		for ($i=1;$i<count($salesArr);$i++) {
 			$line = $salesArr[$i];

 			$bruttosum += doubleval(str_replace($this->decpoint,".",$line[3]));
 			$nettosum += doubleval(str_replace($this->decpoint,".",$line[4]));
 			for ($j=0;$j<count($line);$j++) {
 				$aVal = $line[$j];	
				if ($j == 4) {
					$aVal = number_format(floatval(str_replace(',','.',$aVal)),2,$this->decpoint,'');
				}
 				$this->Cell($this->flengths[$j],6,$aVal,"LR",0,"R",$fill);
 			}
 			$this->Ln();
 			$fill = 1-$fill;
 		}
 		
 		$bruttosum = number_format($bruttosum, 2, $this->decpoint, '');
 		$nettosum = number_format($nettosum, 2, $this->decpoint, '');
 		
 		$this->SetFillColor(200,200,200);
 		$this->Cell($this->flengths[0] + $this->flengths[1] + $this->flengths[2],10,"Summe Verkauf: ","LRBT",0,"L",1);
 		$this->Cell($this->flengths[3],10,number_format($allbills['bruttosumSales'], 2, $this->decpoint, ''),"LRBT",0,"R",1);
 		$this->Cell($this->flengths[4],10,number_format($allbills['nettosumSales'], 2, $this->decpoint, ''),"LRBT",0,"R",1);
		$this->Cell($this->flengths[5] + $this->flengths[6] + $this->flengths[7],10,"","T",0,"R",0);
 		$this->Ln();
                
                $this->Cell($this->flengths[0] + $this->flengths[1] + $this->flengths[2],10,"Summe Trinkgelder: ","LRBT",0,"L",1);
 		$this->Cell($this->flengths[3],10,number_format($allbills['bruttosumTips'], 2, $this->decpoint, ''),"LRBT",0,"R",1);
 		$this->Cell($this->flengths[4],10,number_format($allbills['nettosumTips'], 2, $this->decpoint, ''),"LRBT",0,"R",1);
		$this->Cell($this->flengths[5] + $this->flengths[6] + $this->flengths[7],10,"","T",0,"R",0);
 		$this->Ln();
                
                $this->Cell($this->flengths[0] + $this->flengths[1] + $this->flengths[2],10,"Summe Ein-/Auslagen: ","LRBT",0,"L",1);
 		$this->Cell($this->flengths[3],10,number_format($allbills['bruttosumCashes'], 2, $this->decpoint, ''),"LRBT",0,"R",1);
 		$this->Cell($this->flengths[4],10,number_format($allbills['nettosumCashes'], 2, $this->decpoint, ''),"LRBT",0,"R",1);
		$this->Cell($this->flengths[5] + $this->flengths[6] + $this->flengths[7],10,"","T",0,"R",0);
 		$this->Ln();
                
                $this->Cell($this->flengths[0] + $this->flengths[1] + $this->flengths[2],10,"Summe Geldtransits: ","LRBT",0,"L",1);
 		$this->Cell($this->flengths[3],10,number_format($allbills['bruttosumTransits'], 2, $this->decpoint, ''),"LRBT",0,"R",1);
 		$this->Cell($this->flengths[4],10,number_format($allbills['nettosumTransits'], 2, $this->decpoint, ''),"LRBT",0,"R",1);
		$this->Cell($this->flengths[5] + $this->flengths[6] + $this->flengths[7],10,"","T",0,"R",0);
 		$this->Ln();
                
                $this->Cell($this->flengths[0] + $this->flengths[1] + $this->flengths[2],10,"Summe DiffSollIst: ","LRBT",0,"L",1);
 		$this->Cell($this->flengths[3],10,number_format($allbills['bruttosumDiffSollIst'], 2, $this->decpoint, ''),"LRBT",0,"R",1);
 		$this->Cell($this->flengths[4],10,number_format($allbills['nettosumDiffSollIst'], 2, $this->decpoint, ''),"LRBT",0,"R",1);
		$this->Cell($this->flengths[5] + $this->flengths[6] + $this->flengths[7],10,"","T",0,"R",0);
 		$this->Ln();
                
		return;
	}
	
	function Header()
	{
		if ($this->curtable == "sales") {
			$this->setSalesTableHeader();
		} else if ($this->curtable == "prods") {
			$this->setProdsTableHeader();
		} else if ($this->curtable == "summary") {
			$this->setSummaryTableHeader();
		} else if ($this->curtable == "taxes") {
			$this->setTaxesTableHeader();
		}
	}
	function Footer()
	{
		$this->SetFont('Helvetica','I',8);
		$x = $this->GetX();
		$y = $this->GetY();
		$this->SetXY(10,280);
		$this->Cell(190,10,"OrderSprinter " . $this->version,0,1,"R");
		$this->SetXY($x,$y);
		
		if ($this->curtable == "sales") {
			$this->Cell($this->maxTableLength,1,"","T",0);
		}
	}
	
	private function setSalesTableHeader() {
		$this->SetFillColor(200,200,200);

		for ($i=0;$i<count($this->tabheader);$i++) {
			$aVal = $this->tabheader[$i];
			$this->Cell($this->flengths[$i],10,$aVal,1,0,"R",1);
		}
		$this->Ln();
	}
	
	private function setProdsTableHeader() {
		$this->SetFont('Helvetica','B',12);
		$this->SetFillColor(200,200,200);
		
		$this->Cell(70,10,$this->osGetSaleItemName($this->lang,"Prod"),"LRTB",0,"R",1);
		$this->Cell($this->maxTableLength-70,10,"Brutto","LRTB",0,"L",1);
		$this->Ln();
	}
	
	private function osInsertProdStat($pdo,$startMonth,$startYear,$endMonth,$endYear,$closidstart=null,$closidend=null) {
		$this->curtable = "";
		
		if (is_null($closidstart)) {
			$prodtxt = array("Produktstatistik ","Product Report ","Venta de productos ");
			$headerLine = utf8_decode($prodtxt[$this->lang]);
			$headerLine .= self::osGetMonthName($this->lang, $startMonth) . " $startYear - " .  self::osGetMonthName($this->lang, $endMonth) . " $endYear";
			$headerLine .= " (in " . $this->currency . ")";
		} else {
			$prodtxt = array("Produktstatistik ","Product Report ","Venta de productos ");
			$headerLine = utf8_decode($prodtxt[$this->lang]);
			$headerLine .=  " " . utf8_decode($this->osClosingsPlural[$this->lang]);
			$headerLine .= " $closidstart - $closidend ";
			$headerLine .= " (in " . self::getConfigItem($pdo,"currency") . ")";
		}
		$this->SetFont('Helvetica','B',14);
		$this->Cell($this->maxTableLength,10,$headerLine,1,1,"C");
		
		$reports = new Reports();

		if (is_null($closidstart)) {
			$startDate = $this->calcStartDate($startMonth, $startYear);
			$endDate = $this->calcEndDate($endMonth, $endYear);

			$prodStat = $reports->sumSortedByProducts($pdo, $startDate, $endDate,null,null,null);
		} else {
			$prodStat = $reports->sumSortedByProducts($pdo, 0,0,$closidstart,$closidend,null);
		}
		
		$this->setProdsTableHeader();
		
		$this->curtable = "prods";
		
		$this->SetFont('Helvetica','',8);
		$this->SetFillColor(180,240,180);
		
		$content = $prodStat["content"];
		$sum = 0.0;
		
		if ($prodStat["max"] != 0) {
			$f = ($this->maxTableLength-70.0) / $prodStat["max"];
			
			foreach($content as $prod) {
				$item = utf8_decode($prod["iter"]);
				$val = $prod["sum"];
				$sum += $val;
				
				$this->Cell(70,6,$item,0,0,"R",0);
				
				$this->Cell(max(intval($val * $f),10.1),6,str_replace(".",$this->decpoint,$val),1,1,"L",1);
			}
		}
		
		$sum = number_format($sum, 2, $this->decpoint, '');
		$this->SetFont('Helvetica','B',12);
		$this->SetFillColor(200,200,200);
		$this->Cell(70,10,$this->osGetSaleItemName($this->lang,"sum") . ": ",0,0,"R");
		$this->SetFillColor(180,180,180);
		$this->Cell(20,10,$sum,0,0,"C",1);
	}
	
	private function daySummary($pdo,$lang,$startMonth,$startYear,$endMonth,$endYear,$closidstart = null,$closidend = null) {
		$day = sprintf("%02s", 1);
		$startMonth = sprintf("%02s", $startMonth);
		$startYear = sprintf("%04s", $startYear);
		$endMonth = sprintf("%02s", $endMonth);
		$endYear = sprintf("%04s", $endYear);
		$startDate = "$startYear-$startMonth-01";

		$endDateDay1 = "$endYear-$endMonth-01";
		$lastdayOfMonth = sprintf("%02s",date("t", strtotime($endDateDay1)));
		$endDate = "$endYear-$endMonth-$lastdayOfMonth";
		
		

		$allbSum = 0.0;
		$allnSum = 0.0;
                $alltSum = 0.0;
		
		$bSum = 0.0;
		$nSum = 0.0;
                $tSum = 0.0;
		
		if (is_null($closidstart)) {
			$sql = "SELECT DISTINCT %closing%.id as id, DATE(closingdate) as datewotime,TIME(closingdate) as thetime FROM %closing% WHERE DATE(closingdate) >= ? AND DATE(closingdate) <= ? ";
			$allClosings = CommonUtils::fetchSqlAll($pdo, $sql, array($startDate,$endDate));
		} else {
			$sql = "SELECT DISTINCT %closing%.id as id, DATE(closingdate) as datewotime,TIME(closingdate) as thetime FROM %closing% WHERE id >= ? AND id <= ? ";
			$allClosings = CommonUtils::fetchSqlAll($pdo, $sql, array($closidstart,$closidend));
		}
		
                $taxEntries = array();
                
                $salesTaxEntries = array();
                
                
		$entry = false;
 		foreach ($allClosings as $aClosing) {
 			$entry = true;
 			$closingid = $aClosing["id"];
 			$date = $this->osDateToGerman($aClosing["datewotime"]) . " " . $aClosing["thetime"];
                        
                        $decpoint = CommonUtils::getConfigValue($pdo, "decpoint", '.');
                        $closing = (new Closing())->getASingleClosing($pdo, $closingid, false,$decpoint);
                        $operationsbytax = $closing["operationsbytax"];

			$bSum = 0.0;
			$nSum = 0.0;
                        $tSum = 0.0;
                        
                        $bSalesSum = 0.0;
                        $nSalesSum = 0.0;
                        $tSalesSum = 0.0;

			$this->SetFont('Helvetica','B',12);
			$this->Cell($this->maxTableLength,10,"ID: $closingid ($date)",0,1,"L",0);
			
                        foreach($operationsbytax as $anOperation) {
                                $operation = $anOperation["thetype"];
                                $tax = str_replace(".",$this->decpoint,$anOperation["tax"]);
                                if (!isset($taxEntries[$tax])) {
                                     $taxEntries[$tax] = array("brutto" => 0.0,"netto" => 0.0, "steuer" => 0.0);   
                                }
                                // now check especially for sales
                                if (($operation == Closing::$SALES_TXT) || ($operation == Closing::$TGAG)){
                                        if (!isset($salesTaxEntries[$tax])) {
                                                $salesTaxEntries[$tax] = array("brutto" => 0.0,"netto" => 0.0, "steuer" => 0.0);   
                                           }
                                }
                                
				$brutto = str_replace(".",$this->decpoint,$anOperation["bruttosum"]);
				$netto = str_replace(".",$this->decpoint,$anOperation["nettosum"]);
                                $steuer = str_replace(".",$this->decpoint,$anOperation["steuer"]);
                                
                                // now add these values - for the summary on all taxes at very end of the pdf summary report
                                $theTaxEntry = $taxEntries[$tax];
                                
                                $theNewTaxEntry = array(
                                    "brutto" => $theTaxEntry["brutto"] + $anOperation["bruttosum"],
                                    "netto" => $theTaxEntry["netto"] + $anOperation["nettosum"],
                                    "steuer" => $theTaxEntry["steuer"] + $anOperation["steuer"]
                                );
                                $taxEntries[$tax] = $theNewTaxEntry;
                                // now check especially for sales
                                if (($operation == Closing::$SALES_TXT) || ($operation == Closing::$TGAG)){
                                        $theSalesTaxEntry = $salesTaxEntries[$tax];
                                        $theNewSalesTaxEntry = array(
                                                "brutto" => $theSalesTaxEntry["brutto"] + $anOperation["bruttosum"],
                                                "netto" => $theSalesTaxEntry["netto"] + $anOperation["nettosum"],
                                                "steuer" => $theSalesTaxEntry["steuer"] + $anOperation["steuer"]
                                            );
                                        
                                        $salesTaxEntries[$tax] = $theNewSalesTaxEntry;
                                }
                                
                                $this->SetFont('Helvetica','',8);
				$this->Cell($this->flengths[0],6,"$operation $tax %",0,0,"R",0);
				$this->Cell($this->flengths[1],6,$netto,0,0,"R",0);
	
				$this->Cell($this->flengths[2],6,$steuer,0,0,"R",0);
				$this->Cell($this->flengths[3],6,$brutto,0,1,"R",0);
				$bSum += doubleval($anOperation["bruttosum"]);
				$nSum += doubleval($anOperation["nettosum"]);
                                $tSum += doubleval($anOperation["steuer"]);
                                
                                if (($operation == Closing::$SALES_TXT) || ($operation == Closing::$TGAG)){
                                        $bSalesSum += doubleval($anOperation["bruttosum"]);
                                        $nSalesSum += doubleval($anOperation["nettosum"]);
                                        $tSalesSum += doubleval($anOperation["steuer"]);
                                }
                        }
                        		
			$allbSum += $bSum;
			$allnSum += $nSum;
		
			if ($entry) {
				$this->osWriteSummarySum($pdo,$this->osSumSales[$lang],$bSalesSum, $nSalesSum,10, $closingid, false);
				//$this->Ln();
			}
                        if ($entry) {
				$this->osWriteSummarySum($pdo,$this->osSum[$lang],$bSum, $nSum,10, $closingid, true);
				$this->Ln();
			}
		}
		
		$this->osWriteSummarySum($pdo,$this->osSumAll[$lang],$allbSum, $allnSum,14, -1, true);

                $this->writeTaxesSummary($salesTaxEntries,$lang,$this->salesTaxSumTxt[$lang]);
		$this->writeTaxesSummary($taxEntries,$lang,$this->taxSumTxt[$lang]);
		
	}
        
        private function writeTaxesSummary($taxEntries,$lang,$headline) {
                $this->Ln(10);
		$this->SetFont('Helvetica','UB',16);
		$this->Cell($this->maxTableLength ,10, utf8_decode($headline . ":"), 0, 1, "L",0);
		$this->Ln(5);
		
		$this->setTaxesTableHeader();
		$this->curtable = "taxes";

		$this->SetFont('Helvetica','',10);
                
                uasort($taxEntries,array($this,'taxSort'));
                $taxesKeys = array_keys($taxEntries);
                foreach($taxesKeys as $aTax) {
                        $nettoFormatted = number_format($taxEntries[$aTax]["netto"], 2, '.', '');
                        $bruttoFormatted = number_format($taxEntries[$aTax]["brutto"], 2, '.', '');
                        $this->Cell(50,10,str_replace(".",$this->decpoint,$aTax),1,0,"C");
                        $this->Cell(50,10,str_replace(".",$this->decpoint,$nettoFormatted),1,0,"C");
                        $this->Cell(50,10,str_replace(".",$this->decpoint,$bruttoFormatted),1,1,"C");
                }
                
		$this->curtable = "empty";
        }
	
        private function taxSort($a,$b) {
                $aAsFloat = doubleval(str_replace(',', '.', $a));
                $bAsFloat = doubleval(str_replace(',', '.', $b));
                if ($a==$b) {
                        return 0;
                }
                if ($a>$b) {
                        return -1;
                } else {
                        return 1;
                }
        }
        
	private function osDateToGerman($dateStr) {
		$d = explode("-",$dateStr);
		return $d[2] . "." . $d[1] . "." . $d[0];
	}
	
	private function osWriteSummarySum($pdo,$itemtxt,$bSum,$nSum,$size,$closingid,bool $doLineAtEnd) {
		
		if ($closingid >= 0) {
			$sql = "SELECT billsum FROM %closing% WHERE id=?";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			$stmt->execute(array($closingid));
			$row = $stmt->fetchObject();
			$billsum = $row->billsum;
//			if (abs($billsum - $bSum) > 1.0) {
//				$this->SetFont('Helvetica','B',16);
//				$this->Cell($this->maxTableLength,10,"$billsum - $bSum : DB inkonsistent",0,0,"R",0);
//				return;
//			}
			if (!Closing::checkForClosingConsistency($pdo, $closingid)) {
				$this->SetFont('Helvetica','B',16);
				$this->Cell($this->maxTableLength,10,"DB inkonsistent - Abbruch!",0,0,"C",0);
				return;
			}
		}
		
		$this->SetFont('Helvetica','BI',$size);
		$this->Cell($this->flengths[0],10,$itemtxt . ":",0,0,"R",0);
		$bSumT = str_replace(".",$this->decpoint,number_format($bSum, 2, $this->decpoint, ''));
		$nSumT = str_replace(".",$this->decpoint,number_format($nSum, 2, $this->decpoint, ''));
		$tSum = number_format($bSum-$nSum, 2, $this->decpoint, '');
		$tSum = str_replace(".",$this->decpoint,$tSum);
		
		$this->SetFont('Helvetica','I',$size);
		$this->Cell($this->flengths[1],10,$nSumT,0,0,"R",0);
		$this->Cell($this->flengths[2],10,$tSum,0,0,"R",0);
		$this->Cell($this->flengths[3],10,$bSumT,0,1,"R",0);
		
                if ($doLineAtEnd) {
                        $this->Cell($this->maxTableLength,1,"","B",1,"C",0);
                }
	}
	
	private function addRestoreStat($pdo, $lang) {
		
		$this->curtable = "";
		
		$sql = "SELECT DATE(date) as day FROM %hist% WHERE action=? ORDER BY date";
		$result = CommonUtils::fetchSqlAll($pdo, $sql, array(10));
		if (count($result) == 0) {
			return;
		}
		
		
		$this->Ln();
		$this->Ln();
		
		$t = array(
				"Restore" => array("Wiederherstellungen der gesamten Datenbank an folgenden Tagen",
						"Restore of complete data base at these days",
						"Restore de la base de datos en estos dias")
		);
		
		$this->SetFont('Helvetica','BI',15);
		$this->SetFillColor(200,200,200);
		$this->Cell($this->maxTableLength,15,$t["Restore"][$lang],1,1,"C",0);

		$this->SetFont('Helvetica','',10);
		$this->SetFillColor(200,200,200);
		
		$allDates = array();
		foreach($result as $aDate) {
			$allDays[] = $this->osDateToGerman($aDate["day"]);
		}
		$allDays = join(", ",$allDays);
	
		$this->MultiCell($this->maxTableLength,7,$allDays,1,"L",1);
	}
	
	
	private function setSummaryTableHeader() {
		$this->SetFillColor(200,200,200);
		$this->SetFont('Helvetica','B',16);
	
		for ($i=0;$i<count($this->tabheader);$i++) {
			$aVal = $this->tabheader[$i];
			//$this->Cell($this->flengths[$i],10,$aVal,1,0,"C",1);
			$this->Cell($this->flengths[$i],10,$aVal,"B",0,"R",0);
		}
		$this->Ln();
	}
	
	private function setTaxesTableHeader() {
		$this->SetFont('Helvetica','B',10);
		$this->SetFillColor(200,200,200);
		$this->Cell(50,10, utf8_decode($this->taxTxt[$this->lang]),1,0,"C",1);
		$this->Cell(50,10,"Netto (" . $this->currency . ")",1,0,"C",1);
		$this->Cell(50,10,"Brutto (" . $this->currency . ")",1,1,"C",1);
	}
	
	private function insertMetaTags($title,$subject) {
		$this->SetAuthor('OrderSprinter');
		$this->SetCreator('OrderSprinter www.ordersprinter.de');
		$this->SetDisplayMode('fullpage');
		$this->SetKeywords( 'OrderSprinter, PDF-Export der Umsatzdaten, www.ordersprinter.de' );
		$this->SetSubject(utf8_decode($subject)); 
		$this->SetTitle(utf8_decode($title)); 
	}
	
	
	public function exportPdfReport($lang,$startMonth,$startYear,$endMonth,$endYear,$closidstart = null,$closidend = null) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$this->decpoint = self::getConfigItem($pdo,"decpoint");
		$this->currency = self::getConfigItem($pdo,"currency");
		$this->version = self::getConfigItem($pdo,"version");
		
		$this->lang = $lang;
		
		$this->insertMetaTags("Umsatzbericht","PDF-Datenexport der Umsätze");
		
 		$this->AddPage();
 		$this->SetFont('Helvetica','B',16);
		
		$this->osInsertHeader($pdo,$lang,0,0,0,0);
		$this->osInsertSales($pdo, $lang, $startMonth, $startYear, $endMonth, $endYear,$closidstart,$closidend);
		
		$this->Ln(10);
		$this->osInsertProdStat($pdo,$startMonth,$startYear,$endMonth,$endYear,$closidstart,$closidend);
		
		$this->addRestoreStat($pdo,$lang);
 		$this->Output();
	}
	
	public function exportPdfSummary($lang,$startMonth,$startYear,$endMonth,$endYear) {
		$pdo = DbUtils::openDbAndReturnPdoStatic();
		$this->exportPdfSummaryCore($pdo, $lang, $startMonth, $startYear, $endMonth, $endYear, null,null);
	}
	
	public function exportPdfSummaryClosPeriod($lang,$closidstart,$closidend) {
		$pdo = DbUtils::openRepliDb();
		$this->exportPdfSummaryCore($pdo, $lang, 0, 0, 0, 0, $closidstart,$closidend);
	}
	
	public function exportCsvSummaryClosPeriod($lang,$closidstart,$closidend) {
		$pdo = DbUtils::openRepliDb();
		$this->exportCsvSummaryCore($pdo, $lang, 0, 0, 0, 0, $closidstart,$closidend);
	}
	
	private function exportCsvSummaryCore($pdo, $lang, $startMonth, $startYear, $endMonth, $endYear, $closidstart = null, $closidend = null) {
		header("Expires: Mon, 20 Dec 1998 01:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Cache-Control: no-cache, must-revalidate");
		header("Pragma: no-cache");
		header("Content-Disposition: attachment; filename='Tageserfassungen.csv'");
		header("Content-Type: text/csv; charset=utf8");

		$decpoint = CommonUtils::getConfigValue($pdo, 'decpoint', '.');
		$currency = CommonUtils::getConfigValue($pdo, 'currency', 'Eur');
		$curTxt = "(" . $currency . ")";
		$txt = "Tageserfassungs-ID;Datum;Steuersatz;Typ;Netto $curTxt;Steuer $curTxt;Brutto $curTxt\r\n";

		if (is_null($closidstart) || is_null($closidend)) {
			$txt .= "Erste und/oder letzte ID der Tageserassungen wurden nicht angegeben!";
		} else {
			$sql = "SELECT DISTINCT %closing%.id as id, DATE(closingdate) as datewotime,TIME(closingdate) as thetime FROM %closing% WHERE id >= ? AND id <= ? ";
			$allClosings = CommonUtils::fetchSqlAll($pdo, $sql, array($closidstart, $closidend));

			foreach ($allClosings as $aClosing) {
				$closingid = $aClosing["id"];
				$date = $this->osDateToGerman($aClosing["datewotime"]) . " " . $aClosing["thetime"];

				$sql = "SELECT %queue%.tax,SUM(price) as brutto,ROUND(SUM(price)/(1 + %queue%.tax/100.0),2) as netto FROM %queue%,%bill% B WHERE billid=B.id AND B.closingid=? AND B.paymentid <> ? GROUP BY %queue%.tax";
				$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
				$stmt->execute(array($closingid,DbUtils::$PAYMENT_GUEST));
				$closingDetails = $stmt->fetchAll(PDO::FETCH_OBJ);

				foreach ($closingDetails as $aClosingDetail) {
					$tax = str_replace(".", $decpoint, $aClosingDetail->tax);
					$brutto = str_replace(".", $decpoint, $aClosingDetail->brutto);
					$netto = str_replace(".", $decpoint, $aClosingDetail->netto);
					$sumtax = number_format($aClosingDetail->brutto - $aClosingDetail->netto, 2, $decpoint, '');
					$sumtax = str_replace(".", $decpoint, $sumtax);

					$txt .= "$closingid;$date;$tax;Umsatz;$netto;$sumtax;$brutto\r\n";
				}

				
				$sql = "SELECT SUM(brutto) as cashsum FROM %bill% WHERE closingid=? AND status=? AND paymentid <> ?";
				$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));

				$stmt->execute(array($closingid, 'c', DbUtils::$PAYMENT_GUEST));
				$row = $stmt->fetchObject();
				$cashsum = $row->cashsum;

				if (!is_null($cashsum) || (abs($cashsum) > 0.01)) {
					$cash = str_replace(".", $decpoint, $cashsum);
					$txt .= "$closingid;$date;0" . $decpoint . "00;Kassenein-/auslage;$cash;0" . $decpoint . "0;$cash\r\n";
				}

				
			}
		}

		echo $txt;
	}

	public function exportPdfSummaryCore($pdo,$lang,$startMonth,$startYear,$endMonth,$endYear,$closidstart = null,$closidend = null) {
	
		$this->decpoint = self::getConfigItem($pdo,"decpoint");
		$this->currency = self::getConfigItem($pdo,"currency");
		$this->version = self::getConfigItem($pdo,"version");
		
		$this->lang = $lang;
		
		$this->insertMetaTags("PDF-Zusammenfassung","PDF-Zusammenfassung der Umsätze");
		
		$this->AddPage();
		$this->SetFont('Helvetica','B',16);
		
		if (!is_null($closidstart)) {
			$headerLine = $this->osClosingsPlural[$lang] . " $closidstart - $closidend ";
			$headerLine .= " (in " . self::getConfigItem($pdo,"currency") . ")";
			$this->osInsertHeader($pdo,$lang,0,0,0,0);
		} else {
			$headerLine = $this->osSummary[$lang] . " " .  self::osGetMonthName($lang, $startMonth) . " $startYear - " .  self::osGetMonthName($lang, $endMonth) . " $endYear";
			$headerLine .= " (in " . self::getConfigItem($pdo,"currency") . ")";
			$this->osInsertHeader($pdo,$lang,$startMonth,$startYear,$endMonth,$endYear);
		}

		$this->Cell($this->maxTableLength ,10,$headerLine,0,1,"C",0);
		
		$this->SetFont('Helvetica','',8);
		$this->Cell($this->maxTableLength ,10, "(" . utf8_decode($this->hintNoEmptyClosings[$lang]) . ")", 0, 1, "C",0);
		$this->Ln(10);
		
		$this->osCalculateColsWidth(array(30,20,20,20));
		$this->tabheader = array($this->osClosingTxt[$lang],"Netto","Steuer","Brutto");
		
		$this->setSummaryTableHeader();
		$this->curtable = "summary";
		$this->daySummary($pdo,$lang, $startMonth, $startYear, $endMonth, $endYear, $closidstart,$closidend);
		
		$this->addRestoreStat($pdo,$lang);
		$this->Output();
	}
}