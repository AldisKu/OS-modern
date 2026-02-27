<?php
define ( 'OK', "1");
define ( 'YES', "Yes");
define ( 'NO', "No");
define ( 'ERROR_NOT_AUTHOTRIZED_MSG',      'Benutzer nicht eingeloggt oder fehlende Benutzerrechte' );

define ( 'ERROR_NOT_AUTHOTRIZED',      '2' );
define ( 'ERROR_BILL_NOT_AUTHOTRIZED', '3');
define ( 'ERROR_BILL_NOT_AUTHOTRIZED_MSG', 'Fehlende Benutzerrechte (Bons)');

define ( 'ERROR_PAYDESK_NOT_AUTHOTRIZED', '4');
define ( 'ERROR_PAYDESK_NOT_AUTHOTRIZED_MSG', 'Fehlende Benutzerrechte (Kasse)');

define ( 'ERROR_MANAGER_NOT_AUTHOTRIZED', '5');
define ( 'ERROR_MANAGER_NOT_AUTHOTRIZED_MSG', 'Fehlende Benutzerrechte (Verwaltung)');

define ( 'ERROR_PRODUCTS_NOT_AUTHOTRIZED', '6');
define ( 'ERROR_PRODUCTS_NOT_AUTHOTRIZED_MSG', 'Fehlende Benutzerrechte (Artikel)');

define ( 'ERROR_BILL_NOT_STORNO_CODE', '7');
define ( 'ERROR_BILL_NOT_STORNO_CODE_MSG', 'Stornocode nicht gesetzt');
define ( 'ERROR_BILL_WRONG_STORNO_CODE', '8');
define ( 'ERROR_BILL_WRONG_STORNO_CODE_MSG', 'Falscher Stornocode');
define ( 'ERROR_BILL_WRONG_NUMERIC_VALUE', '9');
define ( 'ERROR_BILL_WRONG_NUMERIC_VALUE_MSG', 'Rechnungsnummer falsch');
define ( 'ERROR_BILL_ALREADY_CLOSED', '10');
define ( 'ERROR_BILL_ALREADY_CLOSED_MSG', 'Bon schon in Tagesabschluss');

define ( 'ERROR_BILL_ALREADY_CANCELLED', '11');
define ( 'ERROR_BILL_ALREADY_CANCELLED_MSG', 'Bon schon storniert');

define ( 'ERROR_BILL_LESS_MONEY_TO_TAKE_OUT', '12');
define ( 'ERROR_BILL_LESS_MONEY_TO_TAKE_OUT_MSG', 'Weniger Geld in Kasse als entnommen werden soll');

define ( 'ERROR_GENERAL_PAYDESK_SUM', '13');
define ( 'ERROR_GENERAL_PAYDESK_SUM_MSG', 'Aktueller Kassenbestand nicht ermittelbar');

define ( 'ERROR_GENERAL_ID_TYPE', '14');
define ( 'ERROR_GENERAL_ID_TYPE_MSG', 'Falscher Typ des Referenzschluessels');

define ( 'ERROR_GENERAL_DB_NOT_READABLE', '15');
define ( 'ERROR_GENERAL_DB_NOT_READABLE_MSG', 'Datenbankleseprozess abgebrochen');

define ( 'ERROR_DB_PAR_ACCESS', '16');
define ( 'ERROR_DB_PAR_ACCESS_MSG', 'Gleichzeitiger DB-Zugriff');

define ( 'ERROR_EMAIL_FAILURE', '17');
define ( 'ERROR_EMAIL_FAILURE_MSG', 'Emailversand fehlgeschlagen');

define ( 'ERROR_INCONSISTENT_DB', '17');
define ( 'ERROR_INCONSISTENT_DB_MSG', 'Inkonsistente Datenbank');

define ( 'ERROR_RES_NOT_AUTHOTRIZED', '18');
define ( 'ERROR_RES_NOT_AUTHOTRIZED_MSG', 'Fehlende Benutzerrechte (Reservierung)');

define ( 'ERROR_DB_PRIVS_MISSING', '19');
define ( 'ERROR_DB_PRIVS_MISSING_MSG', 'Fehlende Datenbankrechte');

define ( 'ERROR_SCRIPT_NOT_EXECUTABLE', '20');
define ( 'ERROR_SCRIPT_NOT_EXECUTABLE_MSG', 'Shutdown nicht ausführbar');

define ( 'ERROR_NAME_EXISTS_ALREADY', '21');
define ( 'ERROR_NAME_EXISTS_ALREADY_MSG', 'Name wurde bereits vergeben');

define ( 'DB_NOT_CHANGED', '22');
define ( 'DB_NOT_CHANGED_MSG', 'Keine Änderung vorgenommen');

define ( 'ERROR_RATE_NOT_AUTHOTRIZED', '23');
define ( 'ERROR_RATE_NOT_AUTHOTRIZED_MSG', 'Fehlende Benutzerrechte (Bewertung)');

define ( 'ERROR_NO_CLOSING', 24);
define ( 'ERROR_NO_CLOSING_MSG', 'Keine Tagesabschluesse verfuegbar');

define ( 'NO_CONTENT', 25);
define ( 'NO_CONTENT_MSG', 'Keine Rueckgabewerte verfuegbar');

define ( 'PARSE_ERROR', 26);
define ( 'PARSE_ERROR_MSG', 'Fehler beim Parsen');

define ( 'NUMBERFORMAT_ERROR', 27);
define ( 'NUMBERFORMAT_ERROR_MSG', 'Falsches Zahlenformat');

define ( 'ERROR_BILL_NOT_WO_HOST', '28');
define ( 'ERROR_BILL_NOT_WO_HOST_MSG', 'Bewirtungseigenschaft falsch');
define ( 'ERROR_BILL_CANCEL_IMOSSIBLE', '7');
define ( 'ERROR_BILL_CANCEL_IMOSSIBLE_MSG', 'Stornierung unmöglich');

define ( 'FOOD_PRINT_TYPE', 1);
define ( 'DRINK_PRINT_TYPE', 2);
define ( 'PAY_PRINT_TYPE', 3);

define ( 'ERROR_COMMAND_NOT_FOUND', 29);
define ( 'ERROR_COMMAND_NOT_FOUND_MSG', 'Rechte für Kommando konnten nicht verifiziert werden');

define ( 'ERROR_COMMAND_NOT_ADMIN', 30);
define ( 'ERROR_COMMAND_NOT_ADMIN_MSG', 'Benutzer besitzt keine Admin-Rechte');

define ( 'ERROR_COMMAND_ERROR', 31);
define ( 'ERROR_COMMAND_ERROR_MSG', 'Kommando konnte nicht korrekt ausgeführt werden');

define ( 'ERROR_BILL_CUSTOMER_PAID', '32');
define ( 'ERROR_BILL_CUSTOMER_PAID_MSG', 'Bon wurde schon von einem Gast bezahlt - siehe Gästeansicht');

define ( 'ERROR_CLOSING_TIME_LIMIT', 33);

define ( 'ERROR_BILL_GUEST_ASSIGNED_AND_PAID', '34');
define ( 'ERROR_BILL_GUEST_ASSIGNED_AND_PAID_MSG', 'Rechnung ist als bezahlt deklariert und einem Gast zugewiesen. Bezahlstatus muss in der Gastansicht geändert werden. Gast: ');

define ( 'ERROR_MASTERDATA', 35);

define ( 'ERROR_UNCLEAR_PAYSTATUS', '36');
define ( 'ERROR_UNCLEAR_PAYSTATUS_MSG', 'Abbruch, da Bezahlstatus einzelner Artikel unklar');