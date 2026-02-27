<?php

class Orders {
        private $name = null;
        private $street = null;
        private $housenumber = null;
        private $postalcode = null;
        private $city = null;
        private $phone = null;
        private $remark = null;
        private $email = null;
        private $sendemail = null;
        private $customerid = null;
        private $creatorid = null;
        private $creationdate = null;
        private $orderstatus = null;
        
        function __construct($postData) {
                date_default_timezone_set(DbUtils::getTimeZone());
                if(session_id() == '') {
			session_start();
		}
		$this->creationdate = date('Y-m-d H:i:s');
                $this->creatorid = $_SESSION['userid'];
                $this->name = (isset($postData['ordername']) ? $postData['ordername'] : null);
                $this->street = (isset($postData['orderstreet']) ? $postData['orderstreet'] : null);
                $this->housenumber = (isset($postData['orderhousenumber']) ? $postData['orderhousenumber'] : null);
                $this->postalcode = (isset($postData['orderpostalcode']) ? $postData['orderpostalcode'] : null);
                $this->city = (isset($postData['ordercity']) ? $postData['ordercity'] : null);
                $this->phone = (isset($postData['orderphone']) ? $postData['orderphone'] : null);
                $this->remark = (isset($postData['orderremark']) ? $postData['orderremark'] : null);
                $this->email = (isset($postData['orderemail']) ? $postData['orderemail'] : null);
                $this->sendemail = (isset($postData['ordersendemail']) ? $postData['ordersendemail'] : null);
        }
        
        public function isOrderSet() {                
                if (!is_null($this->name) && ($this->name != "")) { return true; }
                if (!is_null($this->street) && ($this->street != "")) { return true; }
                if (!is_null($this->housenumber) && ($this->housenumber != "")) { return true; }
                if (!is_null($this->postalcode) && ($this->postalcode != "")) { return true; }
                if (!is_null($this->city) && ($this->city != "")) { return true; }
                if (!is_null($this->phone) && ($this->phone != "")) { return true; }
                if (!is_null($this->remark) && ($this->remark != "")) { return true; }
                if (!is_null($this->email) && ($this->email != "")) { return true; }
                return false;
        }
        
        public function setCreatorid($creatorid) {
                $this->creatorid = $creatorid;
        }
        
        public function createOrderEntryInDb($pdo) {
                $sql = "INSERT INTO %orders% (creationdate,creatorid,name,street,housenumber,postalcode,city,phone,remark,email,sendemail,customerid,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)";
                CommonUtils::execSql($pdo, $sql, array(
                    $this->creationdate,
                    $this->creatorid,
                    $this->name,
                    $this->street,
                    $this->housenumber,
                    $this->postalcode,
                    $this->city,
                    $this->phone,
                    $this->remark,
                    $this->email,
                    $this->sendemail,
                    $this->customerid,
                    $this->orderstatus));
                return $pdo->lastInsertId();
        }

}
