<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once (__DIR__. '/../3rdparty/phpmailer/phpmailer.php');
require_once (__DIR__. '/../3rdparty/phpmailer/smtp.php');

class Emailer {

	public static function sendEmail($pdo,$msg,$to,$subject,$bcc = null) {
		
		$host = self::getConfigItemOrDefault($pdo, "smtphost", "");
		
		$smtpauth = self::getConfigItemOrDefault($pdo, "smtpauth", 1);
		$smtpuser = self::getConfigItemOrDefault($pdo, "smtpuser", "");
                $smtppass = self::getConfigItemOrDefault($pdo, "smtppass", "");
		
		$smtpsecure = self::getConfigItemOrDefault($pdo, "smtpsecure", 1);
		
		$smtpport = self::getConfigItemOrDefault($pdo, "smtpport", 589);
		
                $fromemail = self::getConfigItemOrDefault($pdo, "email", "");
                
		$htmlMsg = str_replace("\n","<br>",$msg);
		
                return self::sendEmailWithTheseSmtpConfig($host,$smtpauth,$bcc,$smtpuser,$smtppass,$smtpsecure,$smtpport,$fromemail,$to,$subject,$msg,$htmlMsg);
	}
        
        public static function sendEmailWithTheseSmtpConfig($host,$smtpauth,$bcc,$smtpuser,$smtppass,$smtpsecure,$smtpport,$fromemail,$toemail,$subject,$msg,$htmlMsg) {
                if (!is_null($bcc)) {
			$bcc = trim($bcc);
		}
		if ($bcc == '') {
			$bcc = null;
		}
		$mail = new PHPMailer;
		
		
		$mail->CharSet = 'utf-8';
		
		$mail->Timeout = 7;
                
                if ($host == "") {
			return false;
		}
		
		$mail->isSMTP(); // Set mailer to use SMTP
		$mail->Host = $host;
		
		if ($smtpauth == 0) {
			$mail->SMTPAuth = false;
		} else {
			$mail->SMTPAuth = true;
		}
		
		$mail->Username = $smtpuser;
		$mail->Password = $smtppass;
		
		if ($smtpsecure == 0) {
			$mail->SMTPSecure = 'ssl';
		} else {
			$mail->SMTPSecure = 'tls';
		}
		$mail->Port = $smtpport;
		
		$mail->setFrom($fromemail);
		$mail->addAddress($toemail);     // Add a recipient
		if (!is_null($bcc)) {
			$mail->addBCC($bcc);
		}
		$mail->isHTML(true);                                  // Set email format to HTML
		
		$mail->Subject = $subject;

		$mail->Body    = $htmlMsg;
		$mail->AltBody = $msg;
		
		$isDemo = false;
                if (ISDEMO) {
                        $isDemo = true;
                }

		if (!$isDemo) {
			$ret = $mail->send();
			return $ret;
		} else {
			return true;
		}
        }
	
	private static function getConfigItemOrDefault($pdo,$item,$default) {
		try {
			$sql = "SELECT count(id) as number,setting FROM %config% WHERE name=?";
			$stmt = $pdo->prepare(DbUtils::substTableAlias($sql));
			
			$stmt->execute(array($item));
			$row = $stmt->fetchObject();
			$ret = $default;
				
			if ($row) {
				if (($row->number) > 0) {
					$ret = $row->setting;
				} else {
					$ret = $default;
				}
			}
		} catch (Exception $e) {
			$ret = $default;
		}
		return $ret;
	}
}