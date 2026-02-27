<?php

class Preview {
        public static function handleCommand($command) {
                if ($command == 'recpreview') {
                        $template = null;
                        $size = 40;
                        if (isset($_POST['template'])) {
                                $template = $_POST['template'];
                        }
                        if (isset($_POST['size'])) {
                                $size = $_POST['size'];
                        }
                        echo json_encode(self::recpreview($template,$size));
                }
        }
        private static function recpreview($template,$size) {
                $data = array();
                
                $products = array(
                    array("count" => "1","productname" => "Pommes","pricelevel" => "A","price" => "4,00","netto" => "3,74","total" => "4,00","totalnetto" => "3,74"),
                    array("count" => "","productname" => " + 1x Majo (0,50)","pricelevel" => "A","price" => "","netto" => "","total" => "","totalnetto" => ""),
                    array("count" => "","productname" => " + 1x Ketchup (0,50)","pricelevel" => "A","price" => "","netto" => "","total" => "","totalnetto" => ""),
                    array("count" => "2","productname" => "Schnitzel","pricelevel" => "A","price" => "4,00","netto" => "3,74","total" => "8,00","totalnetto" => "7,48"),
                    array("count" => "1","productname" => "Holsten","pricelevel" => "A","price" => "1,00","netto" => "0,84","total" => "1,00","totalnetto" => "0,84")
                );
                $taxes = array(
                    array("tax" => "19,00","mwst" => "0,16","netto" => "0,84","brutto" => "1,00"),
                    array("tax" => "7,00","mwst" => "0,79","netto" => "11,21","brutto" => "12,00")
                );
                $data[] = array("id" => 123,"billuid" => 123,"billdate" => "28.05.2021 19:20","billday" => "28","billmonth" => "05","billyear" => "2021","billhour" => "19","billmin" => "20",
                    "brutto" => "16,80","bruttowithcurrency" => "16,80 Euro","currency" => "Euro","netto" => "15,70",
                    "table" => "Tisch 9","username" => "Charlie Chef","fullname" => "Bodo Boss","userid" => 4,"printer" => 0,"host" => "0",
                    "sn" => "ORD1","uid" => "1.6.6-1622216619-8683","version" => "2.9.12",
                    "companyinfo" => "Musterrestaurant\nABC-Straße 123\n12345 Beispielstadt\nDeutschland\nStNR: 123\nUStID:456",
                    "systemid" => "1",
                    "dsfinvk_name" => "Musterrestaurant",
                    "dsfinvk_street" => "ABC-Straße 123",
                    "dsfinvk_postalcode" => "12345",
                    "dsfinvk_city" => "Beispielstadt",
                    "dsfinvk_country" => "Deutschland",
                    "dsfinvk_stnr" => "123",
                    "dsfinvk_ustid" => "456",
                    "hospitality" => '',
                    "tsestatus" => 1,
                    "tseserialno" => "7E5B69CC5E41C803F17242DAC2B3664D33FC2C1039FE892AB0B1D81FF695A7AA",
                    "transnumber" => "21",
                    "sigcounter" => "20",
                    "startlogtime" => "2021-05-28T19:41:45.000",
                    "logtime" => "2021-05-28T19:41:48.000",
                    "sigalg" => "ecdsa-plain-SHA384",
                    "logtimeformat" => "unixTime",
                    "tsesignature" => "JGNc9EDFSF4wdXwWk9JlcaCQNjabgEmpEkG6R78bJ2hHDIDcKO2ubFKyQFcJOXXhaPrMuTMnU6qZF5s6xKlieHe1DWGvSS2JXOCbnUvlhfSlT7rTWCyjlfBm3K4Yx",
                    "pubkey" => "BFa72LLBEGO5ycFraOubP491AuEnnaikkJqt9CcCIMVyyxdbRO7f1QYBsoX4zuuzyOCPhKvohCwm6OrJujdimZQVLqUsYj6KSh/S07bqxZ6d/5npEKEmx9pNRy0b1qQOg==",
                    "guestinfo" => '',
                    "payment" => "Barzahlung",
                    "paymentid" =>"1",
                    "products" => $products,
                    "taxes" => $taxes
                    );
                
                try {
                        $layoutedTemplate = Layouter::layoutTicket($template, $data, $size ,true);
                        $previewHtml = $layoutedTemplate['html'];
                } catch (Exception $ex) {
                        $previewHtml = "Die Vorlage konnte nicht interpretiert werden. Die Fehlermeldung: " . $ex->getMessage();
                }

                return array("status" => "OK","msg" => $previewHtml);
        }
}
