# Kurzanleitung - OrderSprinter v32

## Kasse starten
- **URL**: `http://192.168.0.33/modern/` (oder Link auf Startseite)
- Mit Benutzer und Passwort anmelden
- **Kassen-ID** in der Info-Zeile oben merken (z.B. `broker1`)

## Info-Zeile (Kasse oben)
- **Broker**: Zeigt Kassen-ID (z.B. `broker1`)
- **Display**: Zeigt `OK` (verbunden) oder `-` (nicht verbunden)
- **Online**: Zeigt `OK` (immer)
- **Sync**: Zeigt letzte Aktualisierung
- **v**: Zeigt Versionsnummer

## Kundendisplay (Altes Android-Tablet)
- **URL**: `http://192.168.0.33/modern/customer-legacy.html` oder von Home Screen
- **Schritt 1**: Warten bis **Auswahlbildschirm** mit verfügbaren Kassen angezeigt wird
- **Schritt 2**: Kasse aus Dropdown auswählen (muss mit Kassen-ID von der Kasse übereinstimmen)
- **Schritt 3**: **Anwenden** Button klicken
- **Schritt 4**: Display verbindet sich und zeigt Idle-Bildschirm
- **Schritt 5**: Nach oben wischen um Adressleiste zu verstecken (Vollbildmodus)

## Kundendisplay starten
- **URL**: `http://192.168.0.33/modern/customer.html`
- Kasse aus Dropdown auswählen (Broker-ID von Kasse)
- **Anwenden** klicken
- Nach oben wischen um Adressleiste zu verstecken

## Kassen-ID klicken (Kundendisplay)
- Auf Kassen-ID oben im Display klicken
- Zurück zum **Auswahlbildschirm**
- Andere Kasse auswählen möglich

## Logout (Kasse)
- **Logout** Button klicken
- Kasse bekommt neue Kassen-ID beim nächsten Login
- Kundendisplay zeigt **Auswahlbildschirm** wieder
- Muss Kasse manuell neu auswählen

## Fehlerbehebung
- **Display verbindet nicht**: Kassen-ID auf Display mit Kasse abgleichen
- **Display zeigt falsche Kasse**: Auf Kassen-ID klicken um neu auszuwählen
- **Altes Tablet Probleme**: `customer-legacy.html` verwenden
- **Etwas funktioniert nicht**: Alte Version unter `http://192.168.0.33/modern-legacy/` verwenden (funktioniert wie vorher)
