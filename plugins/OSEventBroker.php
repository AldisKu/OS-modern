<?php
namespace Plugin;

class OSEventBroker {
	private static function config() {
		$path = __DIR__ . "/config.json";
		if (!file_exists($path)) {
			return null;
		}
		$raw = file_get_contents($path);
		$cfg = json_decode($raw, true);
		if (!is_array($cfg)) {
			return null;
		}
		return $cfg;
	}

	private static function postEvent($event, $scope) {
		$cfg = self::config();
		if (is_null($cfg)) {
			return;
		}
		if (!isset($cfg["broker_url"])) {
			return;
		}
		$token = "";
		if (isset($cfg["broker_token"])) {
			$token = $cfg["broker_token"];
		}
		$payload = json_encode(array(
			"event" => $event,
			"scope" => $scope,
			"ts" => time()
		));
		$url = $cfg["broker_url"];
		$headers = "Content-Type: application/json\r\n";
		if ($token != "") {
			$headers .= "X-Broker-Token: " . $token . "\r\n";
		}
		$context = stream_context_create(array(
			"http" => array(
				"method" => "POST",
				"header" => $headers,
				"content" => $payload,
				"timeout" => 1
			)
		));
		@file_get_contents($url, false, $context);
	}

	public static function afterOrderSaved($pdo) {
		self::postEvent("ORDER_SAVED", "TABLES");
	}

	public static function afterPayment($pdo) {
		self::postEvent("PAYMENT", "TABLES");
	}
}
