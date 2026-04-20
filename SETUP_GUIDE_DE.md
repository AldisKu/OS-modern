# OrderSprinter Modern - Installationsanleitung (Deutsch)

## Überblick

OrderSprinter Modern ist ein modernes POS-System mit WebSocket-Broker für Echtzeit-Synchronisation zwischen Kasse und Kundendisplay.

## Systemanforderungen

- **OS**: Linux (Ubuntu 18.04+, Debian 10+, CentOS 7+)
- **Web Server**: Apache mit PHP (bereits vorhanden)
- **Node.js**: 18.x oder höher
- **RAM**: Mindestens 512 MB
- **Speicher**: Mindestens 500 MB verfügbar

## Installation

### Schritt 1: Setup-Skript herunterladen und ausführen

```bash
curl -O https://raw.githubusercontent.com/AldisKu/OS-modern/main/setup-modern-os.sh
bash setup-modern-os.sh
```

Das Skript wird Sie durch folgende Schritte führen:

1. **WEBROOT-Erkennung**: Findet automatisch den Web-Root-Pfad
2. **Git-Repository**: Klont oder aktualisiert das Repository
3. **Abhängigkeiten**: Installiert Node.js und erforderliche Tools
4. **Deployment**: Kopiert Dateien in das Web-Verzeichnis
5. **Broker-Service**: Erstellt und startet den WebSocket-Broker
6. **Konfiguration**: Erstellt Konfigurationsdatei

### Schritt 2: Bestätigung der Einstellungen

Das Skript wird Sie auffordern, folgende Einstellungen zu bestätigen:

- **WEBROOT**: Pfad zum Web-Verzeichnis (z.B. `/var/www/webapp`)
- **Git-Ordner**: Pfad zum Repository (z.B. `/home/aldis/ordersprinter`)
- **Abhängigkeiten**: Installation von git und Node.js

### Schritt 3: Nach der Installation

Nach erfolgreicher Installation:

1. Öffnen Sie den Browser: `http://[SERVER-IP]`
2. Melden Sie sich mit Ihren Anmeldedaten an
3. Konfigurieren Sie die Tischzuordnung
4. Verbinden Sie das Kundendisplay

## Konfiguration

### Lokale Konfiguration (local config)

Die lokale Konfiguration wird im Browser gespeichert und enthält:

- **Tischzuordnung**: Welche Tische in welcher Reihenfolge angezeigt werden
- **Vordefinierte Bemerkungen**: Häufig verwendete Notizen/Kommentare
- **Anzeigeeinstellungen**: Produktbilder, Layout-Optionen

**Speicherort**: Browser LocalStorage (pro Gerät)

### Tischzuordnung (Table Layout)

Die Tischzuordnung definiert, wie Tische auf dem Bildschirm angeordnet werden.

**Datei**: `modern/table-layout.json`

**Format**:
```json
{
  "rooms": {
    "1": {
      "cols": 4,
      "tables": {
        "1": { "row": 1, "col": 1 },
        "2": { "row": 1, "col": 2 },
        "3": { "row": 1, "col": 3 },
        "4": { "row": 1, "col": 4 }
      }
    },
    "default": {
      "cols": 4,
      "tables": {}
    }
  }
}
```

**Erklärung**:
- `rooms`: Objekt mit Raum-IDs als Schlüssel
- `cols`: Anzahl der Spalten im Grid
- `tables`: Tische mit Zeilen- und Spaltenposition
- `default`: Fallback-Layout für Räume ohne spezifische Konfiguration

### Vordefinierte Bemerkungen

Vordefinierte Bemerkungen werden in der lokalen Konfiguration gespeichert und ermöglichen schnelle Auswahl häufig verwendeter Notizen.

**Beispiele**:
- "Ohne Zwiebeln"
- "Extra scharf"
- "Bitte schnell"
- "Allergiker"

Diese können im Browser konfiguriert werden und werden lokal gespeichert.

## Kundendisplay verbinden

### Anforderungen

- Zweites Gerät (Tablet, Monitor mit Browser)
- Gleiche Netzwerk-Verbindung
- Moderner Browser (Chrome, Firefox, Safari)

### Verbindungsschritte

1. **Auf der Kasse**:
   - Öffnen Sie OrderSprinter Modern
   - Melden Sie sich an
   - Der Broker sollte "OK" anzeigen

2. **Auf dem Kundendisplay**:
   - Öffnen Sie: `http://[KASSE-IP]/modern/customer.html`
   - Wählen Sie die Kasse aus der Liste
   - Das Display zeigt aktuelle Bestellungen

3. **Verbindung prüfen**:
   - Broker-Status sollte "OK" sein
   - Display sollte "Bereit" anzeigen
   - Neue Bestellungen sollten sofort angezeigt werden

## Broker-Verwaltung

### Broker-Status prüfen

```bash
sudo systemctl status ordersprinter-broker.service
```

### Broker neu starten

```bash
sudo systemctl restart ordersprinter-broker.service
```

### Broker-Logs anzeigen

```bash
sudo journalctl -u ordersprinter-broker.service -f
```

### Broker-Konfiguration

Die Broker-Konfiguration befindet sich in:
- **Datei**: `modern/config.json`
- **Einstellungen**: Broker-Port, Polling-Intervall, etc.

**Beispiel**:
```json
{
  "broker_port": 3077,
  "client_poll_interval_ms": 120000
}
```

## Updates und Wartung

### Update durchführen

```bash
cd /home/aldis/ordersprinter
git pull
WEBROOT=/var/www/webapp sudo bash deploy-modern.sh --v
sudo systemctl restart ordersprinter-broker.service
```

### Backup erstellen

```bash
sudo cp -a /var/www/webapp/modern /var/www/webapp/modern.backup.$(date +%Y%m%d_%H%M%S)
```

### Logs überprüfen

```bash
# Broker-Logs
sudo journalctl -u ordersprinter-broker.service -n 100

# API-Logs
tail -f /var/log/ordersprinter/modernapi.log
```

## Fehlerbehebung

### Broker startet nicht

1. Prüfen Sie die Logs:
   ```bash
   sudo journalctl -u ordersprinter-broker.service -n 50
   ```

2. Prüfen Sie, ob Port 3077 verfügbar ist:
   ```bash
   sudo netstat -tlnp | grep 3077
   ```

3. Starten Sie den Broker neu:
   ```bash
   sudo systemctl restart ordersprinter-broker.service
   ```

### Kundendisplay verbindet sich nicht

1. Prüfen Sie die Netzwerk-Verbindung
2. Prüfen Sie die Broker-URL in der Konfiguration
3. Öffnen Sie die Browser-Konsole (F12) auf dem Display
4. Prüfen Sie auf Fehler

### Bestellungen werden nicht synchronisiert

1. Prüfen Sie den Broker-Status
2. Prüfen Sie die API-Logs
3. Starten Sie den Broker neu
4. Aktualisieren Sie den Browser

## Konfigurationsdatei

Nach der Installation wird eine Konfigurationsdatei erstellt:

**Datei**: `/home/aldis/ordersprinter/.ordersprinter-config`

Diese Datei enthält:
- Git-Repository-Pfad
- Web-Root-Pfad
- Broker-Einstellungen
- Schnellbefehle

## Häufig gestellte Fragen

### F: Kann ich mehrere Kassen verwenden?

A: Ja! Jede Kasse verbindet sich mit dem gleichen Broker. Der Broker synchronisiert alle Kassen und Displays.

### F: Wie viele Displays kann ich verbinden?

A: Theoretisch unbegrenzt. Ein Display pro Kasse ist üblich, aber Sie können mehrere Displays pro Kasse haben.

### F: Kann ich das System ohne Git installieren?

A: Das Setup-Skript installiert git automatisch, wenn es nicht vorhanden ist.

### F: Wie ändere ich die Tischzuordnung?

A: Bearbeiten Sie `modern/table-layout.json` und starten Sie den Broker neu.

### F: Wo werden die Bestellungen gespeichert?

A: In der Datenbank des bestehenden OrderSprinter-Systems (nicht in Modern).

## Support und Dokumentation

- **Repository**: https://github.com/AldisKu/OS-modern
- **Issues**: https://github.com/AldisKu/OS-modern/issues
- **Dokumentation**: Siehe README.md im Repository

## Lizenz

OrderSprinter Modern ist Teil des OrderSprinter-Projekts.

---

**Version**: 39  
**Letztes Update**: April 2026
