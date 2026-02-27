<?php
error_reporting(E_ERROR);

// Zum Aufbau der Verbindung zur Datenbank
// die Daten erhalten Sie von Ihrem Provider
defined('MYSQL_HOST') || define ( 'MYSQL_HOST','localhost' );
defined('MYSQL_USER') || define ( 'MYSQL_USER',  'dbuser' );
defined('MYSQL_PASSWORD') || define ( 'MYSQL_PASSWORD',  'dbpass' );
defined('MYSQL_DB') || define ( 'MYSQL_DB', 'pos' );
defined('TAB_PREFIX') || define ('TAB_PREFIX', 'ordersprinter_');
defined('LOG') || define ('LOG', false);
defined('INSTALLSTATUS') || define ('INSTALLSTATUS', 'new');
defined('ISDEMO') || define ('ISDEMO', false);
defined('MYSQL_REPLIDBS') || define ('MYSQL_REPLIDBS', '');