<?php 
require_once( "../php/config.php" );

function downgrade() {

	echo "<div style='background-color:#eeeeee;padding:50px;'>";
	echo "<h1>Downgrade auf 2.8.9</h1>";
	echo "<p>Starte Downgrade-Vorgang...</p>";

	$pdo = openDbAndReturnPdo(
		$host = MYSQL_HOST,
		$port = MYSQL_PORT,
		$db = MYSQL_DB,
		$user = MYSQL_USER,
		$password = MYSQL_PASSWORD
	);
	if (!$pdo) {
		echo "<p style='color:red;'>Fehler: Verbindungsaufbau zur Datenbank fehlgeschlagen.</p>";
		return;
	}
	$sql = "
		ALTER TABLE %products% DROP COLUMN guestmaxorder;
		ALTER TABLE %histprod% DROP COLUMN guestmaxorder;
		ALTER TABLE %queue% DROP COLUMN isminusarticle;
	";
	execSql($pdo, $sql, null);

	$sql = "
		ALTER TABLE %histuser% DROP COLUMN articletag;
		ALTER TABLE %user% DROP COLUMN articletag;
		ALTER TABLE %histuser% DROP COLUMN right_payallorders;
		ALTER TABLE %roles% DROP COLUMN right_payallorders;
	";
	execSql($pdo, $sql, null);
	updConfig($pdo, "version", "2.8.9");
	echo "<p>Downgrade-Vorgang der Datenbank abgeschlossen.</p>";
	echo "</div>";
	echo "<p><hr /></p>";

	showNextSteps();
}

function showNextSteps() {
	htmlHeader();
	
	echo "<body><div style='background-color:#eeeeee;padding:50px;'>";
	echo "<h1>Nächste Schritte</h1>";

	echo "<p>Wenn Probleme aufgetreten sind, wurden diese in roter Schrift dargestellt. ";
	echo "Nicht jedes Problem ist ein echter Fehler. Wenn beispielsweise eine Spalte nicht existiert, weil das Skript bereits einmal ausgeführt wurde oder das Downgrade nicht von der allerneuesten Version erfolgt, ist das kein Problem, sondern ein Hinweis, dass die Spalte nicht gelöscht werden konnte.</p>";
	echo "<p>Wenn alles in Ordnung ist, kann mit dem Downgrade fortgefahren werden.</p>";
	echo "<p style='font-size:Larger'>Nächste Schritte</p><p><ul>";
	
	echo "<li>Die config.php und config1.php sichern, damit die Einstellungen nicht verloren gehen.</li>";
	echo "<li>Die Dateien der Version 2.8.9 herunterladen (<a href='https://www.ordersprinter.de/ordersprinter-2_8_9.zip'>https://www.ordersprinter.de/ordersprinter-2_8_9.zip</a>) und entpacken.</li>";
	echo "<li>Den Inhalt des emtpackten webapp-Verzeichnisses in das htdocs-Verzeichnis des Servers kopieren ";
	echo "bzw. an die Stelle, an der zuvor OrderSprinter installiert war</li>";
	echo "<li>Die config.php und config1.php aus der Sicherung in das Unterverzeichnis <i>php</i> der OrderSprinter-Installation kopieren.</li>";
	echo "<li>Die Dateien config.php und config1.php schreibbar machen, damit sie von OrderSprinter bzw. dem Webserver gelesen und geschrieben werden können. Z.B. bei Linux: Die Rechte der Dateien config.php und config1.php auf 644 setzen.</li>";
	echo "<li>Ordersprinter im Browser aufrufen. Es sollte die Version 2.8.9 angezeigt werden.</li>";
	echo "<li>Anmelden und die Konfigurationseinstellungen anpassen, insbesondere die Druckeinstellungen für die Arbeitsbons in der Küchen/Baransicht.</li>";
	echo "<li>Über den manuellen Weg kann auf jede Version nach 2.8.9 manuell upgedatet werden. Dazu die gewünschte OrderSprinter-Version dowmloaden (entsprechend ordersprinter.de/ordersprinter-{majorversion]_{minorversion}_{patchversion}.zip) und manuelles Update vornehmen entsprechend PDF-Anleitung.</li>";
	echo "</div></body>";
}

function htmlHeader() {
	echo "<!DOCTYPE html>";
	echo "<html lang='de'>";
	echo "<head>";
	echo "<title>Downgrade auf 2.8.9</title>";
	echo "<meta http-equiv='content-type' content='text/html; charset=utf-8'>";
	echo "<meta name='author' content='Stefan Pichel'>";
	echo "<style>";
	echo "body { font-family: Arial, sans-serif; background-color: #f0f0f0; color: #333; }";
	echo "</style></head>";
}

function htmlView() {
	htmlHeader();

	echo "<body>";
	echo "<div style='background-color:#eeeeee;padding:50px;'>";
	echo "<h1>Downgrade auf 2.8.9</h1>";
	echo "<p>Hiermit lässt sich ein Downgrade bis auf die Version 2.8.9 vornehmen.</p>";
	echo "<p style='color:red;'>Hinweis: Die Druckeinstellungen für die Arbeitsbons in der Küchen/Baransicht werden vom Skript nicht rekonstruiert und müssen händisch nachgearbeitet werden.</p>";


	echo "<p>Das Downgrading erfolgt in mehreren Schritten:</p>";
	echo "<ol>";
	echo "<li>Die Datenbank wird auf den Stand von Version 2.8.9 zurückgesetzt. Dazu dient dieses Interface.</li>";
	echo "<li>Die html-Dateien der Version 2.8.9 müssen eingespielt werden.</li>";
	echo "<li>Die Konfigurationseinstellungen müssen angepasst werden.</li>";
	echo "</ol>";

	echo "<p>Bitte beachten, dass das Downgrade nur funktioniert, wenn die Datenbank auf dem Stand von Version 2.8.9 oder neuer ist!!</p>";
	echo "<p>Es wird dringend empfohlen, vorher ein <strong>Backup der Datenbank</strong> vorzunehmen, bevorzugt über die in OrderSprinter eingebaute Backup-Funktion. Alternativ ist auch ein Dump der DB besser als gar kein Fallback.</p>";

	echo "<p><div style='background-color:yellow;padding:50px;text-align:center;'>";
	echo "<form method='POST'>";
    echo "<p>Soll der Downgrade-Vorgang wirklich gestartet werden?</p>";
    echo "<button type='submit' name='confirm_downgrade' style='width:400px;height:200px;font-size:40px;background-color:#f25130;color:white;'>Starte Downgrade</button>";
    echo "</form>";
	echo "</div>";
	echo "</body>";
}

function openDbAndReturnPdo ($host,$port,$db,$user,$password) {
	$dsn = 'mysql:host=' . $host . ';port=' . $port . ';dbname=' . $db;
	$pdo = null;
	try {
		$pdo = new PDO($dsn, $user, $password);
		$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	}
	catch (PDOException $e) {
		$pdo = null;
	}
	return $pdo;
}

function updConfig($pdo,$name,$value) {
	execSql($pdo, "UPDATE %config% SET setting = ? WHERE name = ?", array($value, $name));
	$configIdRes = fetchSql($pdo, "SELECT id FROM %config% WHERE name = ?", array($name));
	$configId = $configIdRes[0]['id'];

	$sql = "INSERT INTO %histconfig% (configid,setting) VALUES (?,?)";
	execSql($pdo, $sql, array($configId, $value));
	$idInHistConfig = $pdo->lastInsertId();
	date_default_timezone_set('Europe/Berlin');
	$currentTime = date('Y-m-d H:i:s');
	$sql = "INSERT INTO %hist% (date,action,refid) VALUES (?,?,?)";
	execSql($pdo, $sql, array($currentTime, 6, $idInHistConfig));
}

function substTableAliasWithPrefix($sqlString) {
	$out = $sqlString;
	$out = str_replace("%config%",TAB_PREFIX . "config",$out);
	$out = str_replace("%histprod%",TAB_PREFIX . "histprod",$out);
	$out = str_replace("%histconfig%",TAB_PREFIX . "histconfig",$out);
	$out = str_replace("%hist%",TAB_PREFIX . "hist",$out);
	$out = str_replace("%products%",TAB_PREFIX . "products",$out);
	$out = str_replace("%user%",TAB_PREFIX . "user",$out);
	$out = str_replace("%histuser%",TAB_PREFIX . "histuser",$out);
	return ($out);
}

function execSql($pdo,$sql,$params) {
	try {
		$stmt = $pdo->prepare(substTableAliasWithPrefix($sql));
		if (is_null($params)) {
				$stmt->execute();
		} else {
				$stmt->execute($params);
		}
	} catch (PDOException $e) {
		echo "<p style='color:red;'>Error executing SQL: " . $e->getMessage() . "</p>";
	}
}

function fetchSql($pdo,$sql,$params) {
	try {
		$stmt = $pdo->prepare(substTableAliasWithPrefix($sql));
		if (is_null($params)) {
				$stmt->execute();
		} else {
				$stmt->execute($params);
		}
		return $stmt->fetchAll(PDO::FETCH_ASSOC);
	} catch (PDOException $e) {
		echo "<p>Error executing SQL: " . $e->getMessage() . "</p>";
		return [];
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_downgrade'])) {
    downgrade();
} else {
	htmlView();
}

