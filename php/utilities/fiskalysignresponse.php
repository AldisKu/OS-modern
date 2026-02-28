<?php

class Fiskalysignresponse {
        private string $fiskaly_receipt_number = ""; // to be printed as: RKSV-Beleg: 123
        private string $time_signature = ""; // to be printed as: RKSV-Datum: 2021-12-01 13:14:15
        private string $cash_register_serial_number = ""; // to be printed as: RKSV-Kassen-ID: 123
        private string $qr_code_data = ""; // also to be printed as QRCode
        private bool $signed = false; // all unsigned receipts must have the following, additional text visible on the printed receipt: Sicherheitseinrichtung ausgefallen
        
        public function createFromFiskalyOutput(array $serverAnswer) {
                $this->fiskaly_receipt_number = $serverAnswer['receipt_number'];
                $this->time_signature = $serverAnswer['time_signature'];
                $this->cash_register_serial_number = $serverAnswer['cash_register_serial_number'];
                $this->qr_code_data = $serverAnswer['qr_code_data'];
                $this->signed = $serverAnswer['signed'];
        }
        
        public function saveIntoBill($pdo,int $billid) {
                $sql = "UPDATE %bill% SET fiskalyreceiptnumber=?,fiskalytimesignature=?,fiskalyregserno=?,fiskalyqrcode=?,fiskalysigned=? WHERE id=?";
                $isSigned = 0;
                if ($this->signed) {
                        $isSigned = 1;
                }
                $epoch = $this->time_signature;
                $dt = new DateTime("@$epoch");  // convert UNIX timestamp to PHP DateTime
                $timestamp = $dt->format('Y-m-d H:i:s');

                CommonUtils::execSql($pdo, $sql, array($this->fiskaly_receipt_number,$timestamp,$this->cash_register_serial_number,$this->qr_code_data,$isSigned,$billid));
        }
        
        public function fetchValuesFromDb($pdo,int $billId) {
                $sql = "SELECT fiskalyreceiptnumber,fiskalytimesignature,fiskalyregserno,fiskalyqrcode,fiskalysigned FROM %bill% WHERE id=?";
                $result = CommonUtils::fetchSqlAll($pdo, $sql, array($billId));
                if (count($result) == 1) {
                        $valueSet = $result[0];
                        $this->signed = ($valueSet['fiskalysigned'] == 1 ? true : false);
                        $this->fiskaly_receipt_number = (is_null($valueSet['fiskalyreceiptnumber']) ? "" : $valueSet['fiskalyreceiptnumber']);
                        $this->time_signature = (is_null($valueSet['fiskalytimesignature']) ? "" : $valueSet['fiskalytimesignature']);
                        $this->cash_register_serial_number = (is_null($valueSet['fiskalyregserno']) ? "" : $valueSet['fiskalyregserno']);
                        $this->qr_code_data = (is_null($valueSet['fiskalyqrcode']) ? "" : $valueSet['fiskalyqrcode']);
                }
        }
        
        public function isSigned() {
                return ($this->signed);
        }
        public function getReceiptNumber() {
                return $this->fiskaly_receipt_number;
        }
        public function getTimeSignature() {
                return $this->time_signature;
        }
        public function getCashRegSerNo() {
                return $this->cash_register_serial_number;
        }
        public function getQrCode() {
                return $this->qr_code_data;
        }
}
