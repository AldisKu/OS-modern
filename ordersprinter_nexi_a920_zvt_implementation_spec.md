# Implementierungsspezifikation: OrderSprinter / Broker → Nexi SmartPOS A920 via ZVT

**Status:** Implementierungsauftrag  
**Zielsystem:** lokaler Café-Server/Broker + bestehende POS-Clients  
**Payment-Terminals:** Nexi SmartPOS A920 / SECpos EVO  
**Protokoll:** ZVT über TCP/IP  
**ZVT-Referenz:** ECR-Interface ZVT-Protocol, Revision 13.07  
**Stand der Spezifikation geprüft:** 2026-08-08

---

## 1. Auftrag an den Implementierungs-Agenten

Implementiere eine lokale ZVT-Terminalintegration in die bestehende Anwendung.

Vor jeder Codeänderung:

1. Repository und bestehende Architektur vollständig genug analysieren, um zu verstehen:
   - wo der lokale Broker läuft,
   - wie POS-Clients mit dem Broker kommunizieren,
   - wie Zahlungsarten und Zahlungsaufträge aktuell modelliert sind,
   - wie Konfiguration, Logging und Persistenz im Projekt gelöst sind.
2. Bestehende Kommunikationswege zwischen Client und Broker weiterverwenden.
3. Keine parallele zweite Broker-/HTTP-/WebSocket-Infrastruktur einführen, wenn die vorhandene Infrastruktur die benötigten Nachrichten bereits übertragen kann.
4. Die ZVT-Kommunikation ausschließlich serverseitig im lokalen Broker bzw. in einem vom Broker kontrollierten Modul implementieren.
5. Die POS-Clients dürfen **niemals direkt über IP-Adresse mit dem Kartenterminal sprechen**.
6. OrderSprinter selbst soll nicht für die ZVT-Protokollimplementierung verändert werden. Die Integration erfolgt über die bestehende Client-/Broker-Struktur.

---

# 2. Funktionales Ziel

Im Café gibt es:

- mindestens zwei POS-Clients,
- zwei Nexi SmartPOS A920,
- einen lokalen Broker/Server im selben LAN/WLAN wie die Terminals.

Der Benutzer soll bei einer Kartenzahlung im POS auswählen können:

```text
Terminal 1
Terminal 2
Manuell
```

Die Bezeichnungen `Terminal 1`, `Terminal 2`, ... werden vom Broker automatisch vergeben und bleiben dauerhaft stabil.

Ein POS-Zahlungsauftrag enthält die **logische Broker-Terminal-ID**, zum Beispiel:

```json
{
  "requestId": "8e0cf6f4-4b60-4c1b-84be-6fd2658e2fa4",
  "amountMinor": 1850,
  "currency": "EUR",
  "terminalId": "terminal-1"
}
```

Der Client sendet:

- **keine IP-Adresse**
- **keinen TCP-Port**
- **keine ZVT-Terminal-TID**
- **keine Seriennummer**

Die Zuordnung dieser Werte ist ausschließlich Aufgabe des Brokers.

---

# 3. Zielarchitektur

```text
POS 1 ─────┐
           │
POS 2 ─────┼──── bestehender Broker-Transport ────┐
           │                                      │
POS n ─────┘                                Lokaler Broker
                                                   │
                                  ┌────────────────┴────────────────┐
                                  │                                 │
                             TerminalManager                   PaymentService
                                  │                                 │
                         Registry / Discovery                       │
                                  │                                 │
                           ZVT Status-Enquiry                  ZVT Authorisation
                                  │                                 │
                      ┌───────────┴───────────┐            ┌────────┴────────┐
                      │                       │            │                 │
                 A920 Terminal 1         A920 Terminal 2   ...               │
                 aktuelle IP             aktuelle IP                        │
```

Die IP-Adresse ist lediglich ein **veränderlicher Netzwerk-Endpunkt**.

Die logische Terminal-ID und der Anzeigename sind dagegen dauerhaft.

---

# 4. Grundprinzip der Terminal-Identität

Es müssen vier Dinge sauber getrennt werden:

| Begriff | Beispiel | Änderbar? | Verwendung |
|---|---|---:|---|
| Broker-ID | `terminal-1` | nein | Referenz für Client/Broker |
| Anzeigename | `Terminal 1` | nein, außer explizit manuell | POS-Anzeige |
| Hardware-/ZVT-Identität | Seriennummer / ZVT-TID | normalerweise nein | Identifikation des realen Geräts |
| Netzwerk-Endpunkt | `192.168.1.51:20007` | ja | TCP-Verbindung |

Beispiel:

```text
terminal-1
    name             = Terminal 1
    serialNumber     = 123456789
    zvtTerminalId    = 68123456
    ip               = 192.168.1.51
    port             = 20007
```

Wenn DHCP später die Adressen vertauscht:

```text
vorher:
Terminal 1 / Serial A → 192.168.1.51
Terminal 2 / Serial B → 192.168.1.52

nachher:
Terminal 1 / Serial A → 192.168.1.52
Terminal 2 / Serial B → 192.168.1.51
```

muss der Broker danach weiterhin korrekt wissen:

```text
terminal-1 = Serial A
terminal-2 = Serial B
```

Die IP-Adresse darf **niemals als Terminal-Identität verwendet werden**.

---

# 5. ZVT-Identifikationsdaten

Für die Identifikation ist das ZVT-Kommando:

```text
Status-Enquiry
05 01
```

zu verwenden.

Für die erweiterte Statusantwort muss der Service-Byte so gesetzt werden, dass:

- keine SW-Version im alten LLLVAR-Feld erzwungen wird,
- der TLV-Container mit erweiterten Statusinformationen angefordert wird.

Dafür ist im Status-Enquiry:

```text
service-byte = 0x06
```

zu verwenden.

Bedeutung nach ZVT Revision 13.07:

```text
bit 1 = 1  → keine SW-Version im Completion-Feld
bit 2 = 1  → zusätzliche Statusinformationen als TLV senden
```

Wenn ein Service-Byte übertragen wird, verlangt ZVT auch das 6-stellige ZVT-Passwort als 3 Byte BCD.

Beispiel bei Passwort `000000`:

```text
05 01 05 00 00 00 03 06
```

Aufbau:

```text
05 01       Status-Enquiry
05          Länge = 5 Byte
00 00 00    Passwort 000000 als 3 Byte BCD
03          Bitmap Service-Byte
06          Service-Byte
```

**Wichtig:** `000000` ist nur ein Beispiel. Das Passwort darf nicht hart codiert werden. Es muss aus der bestehenden sicheren Broker-Konfiguration bzw. einer Umgebungsvariable gelesen werden.

Eine korrekte Statusabfrage verläuft grundsätzlich:

```text
Broker → Terminal
05 01 ...

Terminal → Broker
80 00 00
```

danach:

```text
Terminal → Broker
06 0F ... <terminal-status-code> ... <TLV>
```

und der Broker bestätigt das Completion mit:

```text
Broker → Terminal
80 00 00
```

---

# 6. Zu parsende TLV-Felder

Im Completion des erweiterten Status-Enquiry sind insbesondere folgende Daten relevant:

```text
1F44    Terminal identifier, 4 Byte BCD

E4      Device-information container
 ├─1F40 Device name, ASCII
 ├─1F41 Software version, ASCII
 ├─1F42 Serial number, BCD
 └─1F43 Device state, 1 Byte

1F55    Terminal locks, 2 Byte Bitfield
```

Für `1F43 Device state` sind mindestens zu unterstützen:

```text
0x00 Ready
0x01 Initialization needed
0x02 No keys loaded
0x03 Fraud
```

Unbekannte zukünftige Werte dürfen den Parser nicht zerstören. Sie sind als unbekannter Status zu behandeln und im Debug-Log hexadezimal zu protokollieren.

Unbekannte TLV-Tags müssen übersprungen werden können.

---

# 7. Wahl der stabilen Hardware-Identität

Bei jedem erfolgreich identifizierten Terminal speichere:

```text
serialNumber
terminalIdentifier
deviceName
softwareVersion
```

Die Identitätsprüfung erfolgt folgendermaßen:

1. Wenn sowohl gespeicherte als auch aktuell gelesene `serialNumber` vorhanden sind:
   - `serialNumber` ist die primäre physische Identität.
   - Sie muss übereinstimmen.

2. Falls eine Seriennummer nicht verfügbar ist, aber beide Seiten `terminalIdentifier` besitzen:
   - `terminalIdentifier` verwenden.

3. Wenn weder Seriennummer noch Terminal-Identifier zuverlässig verfügbar sind:
   - Terminal nicht automatisch neu registrieren.
   - Status `IDENTITY_UNAVAILABLE`.
   - deutlichen Fehler loggen.

Die ZVT-TID darf weiterhin gespeichert werden, auch wenn die Seriennummer als primärer Identitätsanker benutzt wird.

Ein Wechsel der ZVT-TID bei identischer Seriennummer darf **nicht automatisch** zur Anlage eines neuen logischen Terminals führen. Er ist zu loggen.

Ein Wechsel der Seriennummer bei gleicher ZVT-TID ist sicherheitsrelevant und darf **nicht automatisch** als dasselbe physische Terminal akzeptiert werden.

---

# 8. Terminal-Registry und Konfigurationsdatei

Der Broker benötigt eine persistente Registry, zum Beispiel:

```text
config/payment-terminals.json
```

Wenn das Projekt bereits einen passenden Konfigurations-/Persistenzmechanismus besitzt, diesen benutzen, statt zwingend eine neue Datei einzuführen.

Logisches Datenmodell:

```json
{
  "schemaVersion": 1,
  "nextTerminalNumber": 3,
  "terminals": [
    {
      "id": "terminal-1",
      "name": "Terminal 1",
      "sequence": 1,

      "identity": {
        "serialNumber": "123456789",
        "terminalIdentifier": "68123456"
      },

      "device": {
        "deviceName": "A920",
        "softwareVersion": "..."
      },

      "network": {
        "ip": "192.168.1.51",
        "port": 20007
      },

      "createdAt": "2026-08-08T00:00:00+02:00",
      "lastSeenAt": "2026-08-08T00:15:00+02:00",
      "lastVerifiedAt": "2026-08-08T00:15:00+02:00"
    },

    {
      "id": "terminal-2",
      "name": "Terminal 2",
      "sequence": 2,

      "identity": {
        "serialNumber": "987654321",
        "terminalIdentifier": "68123457"
      },

      "device": {
        "deviceName": "A920",
        "softwareVersion": "..."
      },

      "network": {
        "ip": "192.168.1.52",
        "port": 20007
      },

      "createdAt": "2026-08-08T00:00:00+02:00",
      "lastSeenAt": "2026-08-08T00:15:00+02:00",
      "lastVerifiedAt": "2026-08-08T00:15:00+02:00"
    }
  ]
}
```

Nicht in diese automatisch erzeugte Registry schreiben:

- ZVT-Passwort im Klartext, wenn das bestehende Projekt bereits eine Secret-Konfiguration besitzt,
- PAN,
- Trackdaten,
- PIN-Daten,
- CVV,
- sonstige sensible Karteninformationen.

---

# 9. Vergabe von Broker-ID und Name

Bei erstmaliger Erkennung eines unbekannten Geräts:

```text
sequence = nextTerminalNumber
id       = "terminal-" + sequence
name     = "Terminal " + sequence
```

Danach:

```text
nextTerminalNumber++
```

Beispiel:

```text
terminal-1 → Terminal 1
terminal-2 → Terminal 2
terminal-3 → Terminal 3
```

Regeln:

1. Ein einmal vergebener Name wird durch einen Netzwerkscan niemals geändert.
2. Eine einmal vergebene Broker-ID wird niemals geändert.
3. Nummern gelöschter/entfernter Terminals werden nicht automatisch wiederverwendet.
4. Neue Geräte erhalten immer die nächste freie, bisher nicht verwendete Sequenznummer.
5. Werden beim ersten Scan mehrere neue Geräte gleichzeitig gefunden, müssen sie vor der Namensvergabe nach einem stabilen Identitätsschlüssel sortiert werden, damit die Vergabe reproduzierbar ist.

Empfohlener Sortierschlüssel:

```text
serialNumber, falls vorhanden
sonst terminalIdentifier
```

---

# 10. Atomare Persistenz

Änderungen der Registry müssen crash-sicher erfolgen.

Mindestens:

1. Änderungen unter exklusivem Registry-Lock.
2. Neue Datei zunächst als temporäre Datei schreiben.
3. Nach erfolgreichem Schreiben atomar auf den eigentlichen Dateinamen verschieben/ersetzen.
4. Keine partiell geschriebene JSON-Datei hinterlassen.
5. Schreibfehler dürfen die letzte gültige Registry nicht zerstören.

Wenn das Projekt bereits transaktionale Persistenz besitzt, diese verwenden.

---

# 11. Netzwerk-Discovery

## 11.1 Zweck

Der Netzwerkscan dient dazu:

- neue Terminals zu finden,
- geänderte IP-Adressen zu erkennen,
- geänderte TCP-Ports zu erkennen,
- ein Terminal nach einem Pollingfehler wiederzufinden.

Der Netzwerkscan selbst ist **kein Bestandteil von ZVT**.

Discovery bedeutet:

```text
IP/Port per TCP prüfen
→ bei offenem Socket ZVT Status-Enquiry senden
→ nur bei gültiger ZVT-Antwort als Terminal akzeptieren
```

Ein lediglich offener TCP-Port ist **kein Beweis**, dass es ein ZVT-Terminal ist.

---

# 12. Zu scannende TCP-Ports

Der ZVT-Port des A920/SECpos EVO kann je nach Betreiber-/SECpos-Konfiguration unterschiedlich sein.

Deshalb:

```text
scanPorts = [20007, 20011, 40007]
```

als konfigurierbare Default-Liste vorsehen.

Diese Werte dürfen **nicht** als unveränderliche Protokollkonstante behandelt werden.

Sobald ein Terminal erfolgreich gefunden wurde, wird der tatsächlich funktionierende Port im Terminal-Datensatz gespeichert.

Bei normalen Polls und Zahlungen wird zunächst ausschließlich der gespeicherte Port verwendet.

Erst Recovery/Discovery probiert wieder die konfigurierten Scan-Ports.

---

# 13. Bestimmung des zu scannenden Netzes

Kein `ping` als Voraussetzung verwenden.

ICMP kann blockiert sein.

Stattdessen direkt TCP-Verbindungsversuche auf die konfigurierten Kandidatenports durchführen.

Bevorzugte Konfiguration:

```json
{
  "discovery": {
    "cidrs": ["192.168.1.0/24"],
    "ports": [20007, 20011, 40007]
  }
}
```

Wenn keine CIDR explizit konfiguriert ist:

1. private, aktive IPv4-Netzwerkschnittstelle des lokalen Servers ermitteln,
2. Loopback ausschließen,
3. Docker-/Container-/VPN-Interfaces nach Möglichkeit ausschließen,
4. das tatsächliche Subnetz der LAN-Schnittstelle verwenden.

Keine riesigen Netze unkontrolliert scannen.

Wenn automatisch ein Netz mit mehr als ca. 1024 Hosts entstehen würde, Discovery abbrechen und eine explizite CIDR-Konfiguration verlangen.

---

# 14. Scan-Algorithmus

Pseudocode:

```text
discoverTerminals():

    subnetHosts = determineConfiguredHosts()
    candidatePorts = configuredPorts()

    skip endpoints of healthy, currently active TerminalSessions

    parallel with bounded concurrency:
        for each host:
            for each port:
                if tcpConnect(host, port, connectTimeout):
                    result = tryZvtExtendedStatus(host, port)

                    if result is valid ZVT terminal:
                        collect result

    deduplicate by physical identity

    reconcile collected terminals with registry

    persist changes atomically
```

Empfohlene Grenzen:

```text
connectTimeout: ca. 250–500 ms
ZVT probe read timeout: ca. 1–2 s
max. parallele Verbindungsversuche: ca. 32
```

Diese Werte konfigurierbar machen.

Der Scan darf den Broker nicht blockieren.

Discovery in separatem Worker/Executor ausführen.

---

# 15. Scan-Reconciliation

Für jedes gefundene Gerät:

## Fall A: bekannte Identität, gleiche IP

```text
Registry aktualisieren:
lastSeenAt
lastVerifiedAt
device/software metadata
```

Keine neue Terminal-ID erzeugen.

---

## Fall B: bekannte Identität, neue IP oder neuer Port

Beispiel:

```text
terminal-1 / Serial A
alt: 192.168.1.51:20007
neu: 192.168.1.52:20007
```

Dann:

```text
network.ip   aktualisieren
network.port aktualisieren
lastSeenAt   aktualisieren
```

`id` und `name` bleiben unverändert.

---

## Fall C: unbekannte Identität

Neuen Registry-Datensatz erzeugen:

```text
terminal-N
Terminal N
```

---

## Fall D: gleiche physische Identität wird gleichzeitig an zwei Endpunkten gefunden

Nicht automatisch entscheiden.

Status:

```text
DUPLICATE_IDENTITY
```

Keine Zahlung auf dieses logische Terminal starten.

Fehler deutlich loggen.

---

# 16. Zeitsteuerung Discovery

Gewünschte Logik:

### Beim ersten Start ohne Terminal-Registry

```text
sofort Full Discovery Scan
```

### Normalbetrieb

```text
Full Discovery Scan alle 24 Stunden
```

Kein bestimmter Tageszeitpunkt ist fachlich erforderlich. Implementiere ein 24-Stunden-Intervall bzw. nutze den bestehenden Scheduler.

### Zusätzlich

Bei einem fehlgeschlagenen Poll eines bekannten Terminals:

```text
sofort Recovery Scan auslösen
```

Damit bei einem DHCP-Wechsel nicht bis zum nächsten Tages-Scan gewartet werden muss.

Damit ein länger ausgeschaltetes Terminal nicht jede Minute einen kompletten Netzwerkscan erzeugt, einen Recovery-Scan-Cooldown verwenden, zum Beispiel:

```text
recoveryScanCooldown = 5 Minuten
```

Der **erste** Pollingfehler löst sofort Discovery aus.

Weitere Pollingfehler während des Cooldowns lösen keinen zusätzlichen Full Scan aus.

---

# 17. Polling bekannter Terminals

Bekannte Terminals regelmäßig per:

```text
05 01 Status-Enquiry
```

prüfen.

Default:

```text
pollInterval = 60 Sekunden
```

Der Wert muss konfigurierbar sein.

Die offizielle ZVT-Spezifikation empfiehlt Status-Enquiries ungefähr minütlich oder häufiger.

Während einer laufenden Zahlung darf für dasselbe Terminal kein unabhängiger Poll parallel in dieselbe ZVT-Session geschrieben werden.

Alle ZVT-Kommandos eines Terminals müssen serialisiert sein.

---

# 18. Terminal-Laufzeitstatus

Der Broker stellt den Clients einen vereinfachten Status bereit:

```text
AVAILABLE
BUSY
NOT_READY
OFFLINE
UNKNOWN
ATTENTION
```

Semantik:

### AVAILABLE

```text
letzte Identitätsprüfung erfolgreich
Device state = Ready
keine Zahlung/Operation aktiv
```

### BUSY

```text
Broker-Lock vorhanden / Zahlung läuft
```

### NOT_READY

Zum Beispiel:

```text
Initialization needed
No keys loaded
bekannter blockierender Terminal-Lock
Terminal antwortet, ist aber nicht zahlungsbereit
```

### OFFLINE

```text
TCP/ZVT nicht erreichbar
```

### UNKNOWN

```text
Status veraltet oder nicht eindeutig
```

### ATTENTION

```text
inkonsistente Identität
Duplicate Identity
ungeklärter Zahlungszustand
```

Rohwerte wie:

```text
terminalStatusCode
deviceState
terminalLocks
```

intern zusätzlich speichern/loggen.

---

# 19. Terminal-Liste für die POS-Clients

Der Client fordert die aktuell bekannten Terminals beim Broker an.

Die konkrete Transportart muss dem bestehenden Broker-Protokoll folgen.

Logische Antwort:

```json
{
  "terminals": [
    {
      "id": "terminal-1",
      "name": "Terminal 1",
      "status": "AVAILABLE"
    },
    {
      "id": "terminal-2",
      "name": "Terminal 2",
      "status": "BUSY"
    }
  ],
  "manualAvailable": true
}
```

Der Client darf keine Netzwerkdetails erhalten.

Insbesondere nicht:

```text
IP
Port
Seriennummer
ZVT-TID
ZVT-Passwort
```

---

# 20. POS-Auswahl

Bei Kartenzahlung muss der Client eine Auswahl anbieten:

```text
Terminal 1
Terminal 2
Manuell
```

Nicht verfügbare Terminals sollen sichtbar, aber nicht auswählbar sein oder klar als nicht verfügbar markiert werden.

Beispiel:

```text
Terminal 1    verfügbar
Terminal 2    belegt
Manuell
```

Die Terminal-Liste muss unmittelbar vor der Anzeige der Auswahl frisch vom Broker angefordert bzw. aus einem aktuellen Broker-Push-Status übernommen werden.

Keine automatische Auswahl eines anderen Terminals, wenn der Benutzer ausdrücklich `Terminal 1` gewählt hat.

---

# 21. Zahlungsauftrag

Die Client-Nachricht muss mindestens enthalten:

```json
{
  "requestId": "UUID",
  "amountMinor": 1850,
  "currency": "EUR",
  "terminalId": "terminal-1"
}
```

Optional vorhandene fachliche IDs des bestehenden Systems weiterreichen, z. B.:

```text
orderId
receiptId
posId
```

### Betrag

Immer Integer in kleinster Währungseinheit:

```text
18,50 EUR → 1850
```

Nie `double`/`float` für Geldbeträge verwenden.

---

# 22. Idempotenz

`requestId` ist zwingend.

Der Broker darf denselben Zahlungsauftrag niemals zweimal ausführen.

Regeln:

### gleiche `requestId`, Zahlung läuft

Aktuellen Zustand zurückgeben.

### gleiche `requestId`, bereits abgeschlossen

Gespeichertes Ergebnis zurückgeben.

### gleiche `requestId`, anderer Betrag oder andere Terminal-ID

Request ablehnen:

```text
IDEMPOTENCY_CONFLICT
```

Die Idempotenzdaten müssen mindestens so lange gespeichert werden, dass normale Client-Reconnects/Retry-Szenarien keine Doppelzahlung verursachen können.

Bevorzugt Transaktionsjournal persistent führen.

---

# 23. Terminal-Lock

Vor jeder Zahlung:

```text
atomic lock(logicalTerminalId)
```

Nur eine aktive Operation pro Terminal.

Beispiel:

```text
terminal-1 = BUSY by requestId XYZ
```

Ein zweiter POS erhält:

```text
TERMINAL_BUSY
```

Es darf niemals passieren:

```text
POS 1 ─┐
       ├── gleichzeitig → Terminal 1
POS 2 ─┘
```

Der Lock bezieht sich auf die **logische Broker-ID**, nicht auf die IP-Adresse.

---

# 24. Kritische Sicherheitsregel vor jeder Zahlung

Der Broker darf **niemals direkt auf die gespeicherte IP zahlen, ohne zuvor sicherzustellen, dass dort noch das ausgewählte physische Terminal erreichbar ist.**

Ablauf:

```text
client:
terminalId = terminal-1

broker:
resolve terminal-1
→ erwartete Serial/TID = A
→ gespeicherter Endpoint = 192.168.1.51:20007

Status-Enquiry auf .51
→ tatsächliche Identität prüfen
```

Nur wenn die Identität stimmt, darf `06 01 Authorisation` gesendet werden.

---

# 25. Recovery bei IP-Wechsel unmittelbar vor Zahlung

Wenn beim Preflight:

```text
- Verbindung fehlschlägt
oder
- falsche Identität zurückkommt
```

dann:

1. **keine Authorisation senden**
2. Recovery Discovery Scan starten
3. nach der erwarteten Hardware-Identität suchen
4. neue IP/Port in Registry speichern
5. Identität auf neuem Endpoint erneut prüfen
6. erst danach Authorisation senden

Pseudocode:

```text
payment(terminal-1):

    lock terminal-1

    endpoint = registry.endpoint(terminal-1)

    status = verifyIdentity(endpoint)

    if status != expectedTerminal:
        endpoint = rediscover(expectedIdentity)

        if endpoint not found:
            unlock
            return TERMINAL_UNAVAILABLE

        registry.updateEndpoint(endpoint)

        if verifyIdentity(endpoint) != expectedTerminal:
            unlock
            return TERMINAL_IDENTITY_MISMATCH

    startPayment()
```

Damit wird insbesondere der Fall abgefangen:

```text
Terminal 1 und Terminal 2 haben ihre DHCP-Adressen vertauscht.
```

---

# 26. ZVT-Session

Pro Terminal einen `TerminalSession`/vergleichbaren abstrakten Kommunikationskontext implementieren.

Aufgaben:

```text
TCP Socket
read/write serialization
timeouts
ZVT framing
BCD codec
TLV parser
Registration
Status-Enquiry
Authorisation
Abort/Cancel
ACK handling
connection recovery
```

ZVT-Kommandos innerhalb einer Session niemals parallel schreiben.

---

# 27. Registration

Vor dem produktiven Zahlungsbetrieb soll eine ZVT Registration durchgeführt werden:

```text
06 00
```

Für diesen Use Case soll der Terminaldrucker weiterhin Belege drucken.

Der Broker soll:

- Intermediate Status-Information anfordern,
- den Zahlungsstart über die Kasse kontrollieren,
- den Belegdruck nicht auf die Kasse übernehmen.

Dafür ist als Ausgangskonfiguration vorgesehen:

```text
config-byte = 0x18
```

Bedeutung:

```text
0x08 → Intermediate Status-Information anfordern
0x10 → ECR controls payment function / Betragseingabe am Terminal sperren
```

Nicht gesetzt:

```text
ECR receipt printing
```

Belege bleiben daher grundsätzlich Aufgabe des Terminals.

Für EUR den Currency Code explizit verwenden:

```text
09 78
```

Beispiel bei ZVT-Passwort `000000`:

```text
06 00 06 00 00 00 18 09 78
```

Danach:

```text
Terminal → 80 00 00
Terminal → 06 0F ...
Broker   → 80 00 00
```

Wenn das konkrete A920/SECpos EVO eine geringfügig andere Registration-Konfiguration verlangt, diese hinter einer konfigurierbaren ZVT-Session-Option kapseln und dokumentieren; nicht durch ad-hoc Sondercode im Payment-Flow lösen.

---

# 28. ZVT Authorisation

Normale Kartenzahlung:

```text
06 01 Authorisation
```

Der Broker überträgt:

```text
BMP 04 = amount
BMP 49 = currency
```

Keinen Card-Track/PAN/CVV senden.

Keinen Payment-Type erzwingen, solange kein konkreter fachlicher Grund dafür existiert. Das Terminal soll die passende Karten-/Zahlungsart bestimmen.

---

# 29. BCD-Encoding des Betrags

ZVT BMP `04`:

```text
6 Byte BCD
amount in minor currency units
```

Beispiel:

```text
18,50 EUR
= 1850 Cent
= decimal 000000001850
= BCD 00 00 00 00 18 50
```

Mit explizitem EUR Currency Code:

```text
06 01 0A
04 00 00 00 00 18 50
49 09 78
```

Bedeutung:

```text
06 01       Authorisation
0A          10 Byte Payload

04          Amount bitmap
00 00 00 00 18 50

49          Currency bitmap
09 78       EUR
```

Für BCD-Encoding und -Decoding eigene Unit-Tests schreiben.

---

# 30. Zahlungssequenz

Vereinfachte Sequenz:

```text
Broker                                      A920
  │                                           │
  ├── Registration 06 00 ────────────────────>│
  │<────────────────────────────── 80 00 00 ──┤
  │<──────────────────────────── Completion ──┤
  ├── 80 00 00 ──────────────────────────────>│
  │                                           │
  ├── Authorisation 06 01 / 18,50 EUR ───────>│
  │<────────────────────────────── 80 00 00 ──┤
  │                                           │
  │<──────── Intermediate Status 04 FF ... ───┤
  ├── ACK ────────────────────────────────────>│
  │                                           │
  │<──────── Final Status 04 0F ... ──────────┤
  ├── 80 00 00 ──────────────────────────────>│
  │                                           │
  │<──────────────────────────── Completion ──┤
  ├── 80 00 00 ──────────────────────────────>│
  │                                           │
  └── Ergebnis an POS                         │
```

Der genaue Nachrichtendialog muss sich an ZVT Revision 13.07 halten.

---

# 31. Erfolgskriterium einer Zahlung

Eine Zahlung darf gegenüber dem Client erst als:

```text
SUCCESS
```

gemeldet werden, wenn der relevante ZVT-Ablauf vollständig und konsistent abgeschlossen ist.

Insbesondere nicht schon beim ersten:

```text
80 00 00
```

Das ist nur eine Protokollbestätigung.

Das fachliche Ergebnis kommt über Status-Information und Abschluss des ZVT-Dialogs.

---

# 32. Ergebnisdaten

Broker-intern darf ein PaymentResult beispielsweise enthalten:

```json
{
  "requestId": "...",
  "terminalId": "terminal-1",
  "status": "SUCCESS",
  "amountMinor": 1850,
  "currency": "EUR",

  "zvt": {
    "resultCode": 0,
    "receiptNumber": "...",
    "traceNumber": "...",
    "terminalIdentifier": "..."
  }
}
```

Nur tatsächlich vom Terminal gelieferte Werte setzen.

Keine erfundenen Defaultwerte.

---

# 33. Keine Speicherung sensibler Kartendaten

Nicht speichern und nicht loggen:

```text
vollständige PAN
Track 1
Track 2
Track 3
CVV/CVC
PIN
verschlüsselte PIN-Daten
sonstige PCI-sensitive Kartendaten
```

Falls solche Felder in einer ZVT-Antwort vorkommen:

- Parser muss Frames trotzdem korrekt weiterverarbeiten können,
- Daten aber nicht in normale Application-Objekte übernehmen, sofern sie für diesen Use Case nicht benötigt werden,
- niemals in Logfiles ausgeben.

Benötigt werden nur transaktionsbezogene nicht-sensitive Daten.

---

# 34. Fehlerklassen für den Client

Mindestens folgende fachliche Broker-Ergebnisse bereitstellen:

```text
SUCCESS
DECLINED
CANCELLED

TERMINAL_BUSY
TERMINAL_OFFLINE
TERMINAL_NOT_READY
TERMINAL_UNAVAILABLE
TERMINAL_IDENTITY_MISMATCH

PROTOCOL_ERROR
TIMEOUT
UNKNOWN_TRANSACTION_STATE

IDEMPOTENCY_CONFLICT
INVALID_REQUEST
```

Optional ZVT-Resultcode zusätzlich liefern, aber der POS-Client soll nicht die gesamte ZVT-Fehlermatrix interpretieren müssen.

---

# 35. Verbindungsabbruch und Doppelzahlungs-Schutz

Dies ist zwingend.

Sobald der Broker den Authorisation-Request `06 01` auf den Socket geschrieben hat, darf er bei einem Verbindungsabbruch **nicht automatisch einen zweiten Authorisation-Request senden**.

Denn:

```text
Broker hat gesendet
→ Terminal könnte Zahlung bereits gestartet haben
→ ACK könnte lediglich verloren gegangen sein
```

Automatisches Retry könnte eine Doppelzahlung verursachen.

Daher:

```text
vor Senden von 06 01:
    automatische Recovery erlaubt

nach Senden von 06 01:
    niemals blind erneut authorisieren
```

Bei nicht eindeutig ermittelbarem Ergebnis:

```text
UNKNOWN_TRANSACTION_STATE
```

setzen und deutlich protokollieren.

---

# 36. Verhalten bei UNKNOWN_TRANSACTION_STATE

Wenn die Verbindung während einer bereits gestarteten Authorisation verloren geht:

1. `requestId` weiterhin als bereits verwendet behandeln.
2. Keine neue Authorisation für dieselbe `requestId`.
3. Terminal zunächst als `ATTENTION` markieren.
4. Verbindung wiederherstellen.
5. Status-Enquiry durchführen.
6. Falls Terminal wieder `Ready` ist, darf es technisch wieder freigegeben werden.
7. Der ungeklärte vorherige Zahlungsauftrag bleibt im Transaktionsjournal als `UNKNOWN_TRANSACTION_STATE`.
8. Keine automatische Annahme `FAILED`.
9. Keine automatische Annahme `SUCCESS`.
10. Keine automatische zweite Belastung.

Eine spätere Erweiterung kann gezielte Reconciliation/Reversal-Funktionen implementieren. Diese dürfen nicht improvisiert werden.

---

# 37. Cancel einer laufenden Zahlung

Wenn der bestehende POS-Workflow einen Cancel-Button unterstützt, diesen serverseitig über ZVT `Abort (06 B0)` an die aktuell aktive TerminalSession weiterreichen.

Wichtig:

- Nur für die aktuell laufende Operation dieses Terminals.
- Nur solange der Terminalzustand einen Abort zulässt.
- Das endgültige Ergebnis weiterhin aus dem nachfolgenden ZVT-Dialog ableiten.
- Ein gesendeter Abort bedeutet nicht automatisch, dass die Zahlung sicher nicht stattgefunden hat.

Wenn der bestehende Client aktuell keine Cancel-Funktion besitzt, muss für Version 1 keine neue UI nur für diesen Zweck erfunden werden; die Serverarchitektur soll Abort jedoch sauber unterstützen können.

---

# 38. `Manuell`

Die Client-Auswahl:

```text
Manuell
```

bedeutet:

```text
keine ZVT-Authorisation durch den Broker
```

Der bestehende manuelle Zahlungsworkflow bleibt erhalten.

Der Broker darf für `Manuell` **kein künstliches ZVT-SUCCESS erzeugen**.

Die bestehende Anwendung entscheidet weiterhin, wie eine manuell durchgeführte Kartenzahlung verbucht wird.

---

# 39. Kein automatischer Terminal-Fallback bei Zahlung

Wenn der Benutzer:

```text
Terminal 1
```

ausgewählt hat und Terminal 1 nicht gefunden wird:

```text
nicht automatisch Terminal 2 verwenden
```

Stattdessen:

```text
TERMINAL_UNAVAILABLE
```

an Client zurückgeben.

Der Benutzer kann anschließend bewusst:

```text
Terminal 2
oder
Manuell
```

auswählen.

So reagiert immer das physische Gerät, das das Personal erwartet.

---

# 40. Nebenläufigkeit

Mindestens zwei Ebenen von Synchronisation:

## Registry-Lock

Für:

```text
Discovery
IP-Updates
Neuanlage von Terminals
Persistenz
```

## Per-Terminal Operation Lock

Für:

```text
Status-Enquiry
Registration
Payment
Abort
sonstige ZVT-Kommandos
```

Ein Payment besitzt Priorität.

Ein periodischer Poll wartet oder wird übersprungen, wenn das Terminal gerade `BUSY` ist.

---

# 41. Session-Strategie

Bevorzugt:

```text
eine verwaltete TerminalSession pro bekanntem Terminal
```

mit Reconnect bei Bedarf.

Discovery darf nicht gleichzeitig eine zweite konkurrierende Session auf ein bereits gesund verbundenes bekanntes Terminal öffnen.

Beim Full Scan:

```text
bekannte, aktuell gesunde Endpoints überspringen
```

und nur:

```text
unbekannte Endpoints
offline Endpoints
Recovery-Kandidaten
```

aktiv sondieren.

Wenn die bestehende technische Basis persistente Sockets unpraktisch macht, darf die Session auch connect-on-demand arbeiten, aber weiterhin gilt:

```text
maximal ein aktiver ZVT-Dialog pro Terminal
```

---

# 42. ZVT-Codec

Keine kostenpflichtige ZVT-Bibliothek verwenden.

Implementiere nur die benötigte Protokollschicht.

Mindestens:

```text
APDU framing
readExact()
writeFrame()
BCD encode/decode
Bitmap parsing für benötigte Felder
TLV parser
TLV nested containers
timeouts
ACK / error response handling
```

Der TLV-Parser muss generisch sein.

ZVT TLV Length-Encoding:

```text
0x00–0x7F
    direkte Länge

0x81 <len>
    ein zusätzliches Längenbyte

0x82 <hi> <lo>
    zwei zusätzliche Längenbytes
```

`0x80` ist ungültig.

Unbekannte primitive oder constructed Tags müssen übersprungen werden können.

Nested Container wie:

```text
E4
```

rekursiv parsen.

---

# 43. Protokoll-Implementierungsgrenze Version 1

Für die erste produktive Version mindestens korrekt unterstützen:

```text
05 01  Status-Enquiry
06 00  Registration
06 01  Authorisation
06 B0  Abort, sofern Client-Cancel vorhanden

04 FF  Intermediate Status-Information
04 0F  Status-Information

06 0F  Completion
06 1E  Abort vom Terminal

80 00 00 positive ACK
84 xx .. negative/error responses
```

Zusätzliche PT-Kommandos dürfen den Parser nicht crashen lassen.

Falls das Terminal unerwartet einen nicht unterstützten ZVT-Dialog verlangt:

```text
PROTOCOL_ERROR
```

loggen und Zahlung nicht als erfolgreich deklarieren.

---

# 44. Belegdruck

Version 1:

```text
Belegdruck vollständig am A920
```

Der Broker übernimmt nicht den Druck von Kunden-/Händlerbelegen.

Daher Registration so konfigurieren, dass das Terminal seinen eigenen Drucker verwendet.

Die Implementierung darf dennoch unerwartete Print-Line-Kommandos robust erkennen und protokollieren; sie darf dadurch nicht hängen bleiben.

---

# 45. Logging

Normales Application-Log:

```text
terminal broker-id
terminal display-name
IP/Port
event
requestId
amountMinor
ZVT command
result code
duration
state transition
```

Beispiel:

```text
terminal-1 PAYMENT_START request=abc amount=1850
terminal-1 ENDPOINT_VERIFIED 192.168.1.52:20007 serial=***
terminal-1 PAYMENT_SUCCESS request=abc result=00 durationMs=3240
```

Hardwarekennungen im normalen INFO-Log dürfen optional gekürzt/maskiert werden.

Debug-Hexdumps:

- standardmäßig deaktiviert,
- dürfen keinerlei sensible Kartendaten ungefiltert loggen.

---

# 46. Metriken / Diagnose

Wenn die bestehende Anwendung Metriken unterstützt, mindestens:

```text
terminal_poll_success
terminal_poll_failure
terminal_discovery_runs
terminal_discovery_duration
terminal_endpoint_change
terminal_payment_success
terminal_payment_declined
terminal_payment_error
terminal_payment_unknown
terminal_busy_rejections
```

Keine zusätzliche Monitoring-Infrastruktur nur für dieses Feature einführen, wenn das Projekt keine besitzt.

---

# 47. Lizenzanforderungen

Zwingende Vorgabe:

```text
keine laufenden Lizenzgebühren
keine per-Terminal-Lizenz
keine per-Transaktion-Lizenz
keine kostenpflichtige ZVT-Middleware
kein kommerzielles ZVT-SDK
```

Bevorzugt:

```text
Standardbibliothek der vorhandenen Programmiersprache
```

Neue Bibliotheken nur, wenn:

- frei nutzbar,
- keine Runtime-/Geräte-/Transaktionskosten,
- Lizenz mit dem bestehenden Projekt kompatibel,
- Lizenz im Commit dokumentiert.

ZVT-H wird **nicht** benötigt und darf nicht implementiert werden.

---

# 48. Netzwerk-/Sicherheitsanforderungen

Die Terminals und der Broker befinden sich im lokalen Café-Netz.

Nicht:

```text
ZVT-Port ins Internet freigeben
Port-Forwarding vom Internet
öffentliche ZVT-Verbindung
Cloud-Relay für ZVT
```

Der Broker spricht direkt im LAN/WLAN mit den Terminals.

Firewall-Regeln nur soweit nötig für lokale Broker→Terminal-Verbindungen.

---

# 49. ZVT-Passwort

Das ZVT-Passwort ist konfigurierbar.

Beispiel:

```text
ZVT_PASSWORD=000000
```

aber **nicht hart codieren**.

Validierung:

```text
genau 6 Dezimalziffern
```

Encoding:

```text
"123456" → 12 34 56
```

Der gleiche Discovery-Mechanismus muss auch mit einem anderen konfigurierten Passwort funktionieren.

---

# 50. Beispiel-Konfiguration

Die genaue Integration soll dem vorhandenen Konfigurationssystem folgen.

Logische Konfiguration:

```json
{
  "zvt": {
    "passwordSource": "environment",
    "pollIntervalSeconds": 60,
    "discoveryIntervalHours": 24,
    "recoveryScanCooldownSeconds": 300,
    "connectTimeoutMs": 400,
    "responseTimeoutMs": 2000,
    "transactionTimeoutSeconds": 120,
    "scanPorts": [20007, 20011, 40007],
    "currency": "EUR"
  },

  "discovery": {
    "cidrs": ["192.168.1.0/24"],
    "maxConcurrency": 32
  }
}
```

`transactionTimeoutSeconds` darf nicht dazu führen, dass nach Timeout automatisch eine neue Zahlung gestartet wird.

---

# 51. Datenmodell Payment Transaction

Persistentes oder vorhandenes Transaction-Journal erweitern um mindestens:

```text
requestId
posId, sofern vorhanden
orderId, sofern vorhanden

terminalId
amountMinor
currency

state

createdAt
authorisationSentAt
completedAt

zvtResultCode
receiptNumber
traceNumber

errorCode
errorMessage
```

Mögliche interne States:

```text
REQUESTED
LOCKED
VERIFYING_TERMINAL
DISCOVERING
REGISTERING
AUTHORISATION_SENT
IN_PROGRESS
SUCCESS
DECLINED
CANCELLED
FAILED
UNKNOWN
```

---

# 52. State-Machine Payment

```text
REQUESTED
    │
    ▼
LOCKED
    │
    ▼
VERIFYING_TERMINAL
    │
    ├── endpoint ungültig ──→ DISCOVERING ──→ VERIFYING_TERMINAL
    │
    ├── nicht gefunden ─────────────────────→ FAILED
    │
    ▼
REGISTERING
    │
    ├── Fehler ─────────────────────────────→ FAILED
    │
    ▼
AUTHORISATION_SENT
    │
    ▼
IN_PROGRESS
    │
    ├── Result declined ────────────────────→ DECLINED
    │
    ├── User cancel ────────────────────────→ CANCELLED / FAILED je ZVT-Ergebnis
    │
    ├── Verbindung unklar ──────────────────→ UNKNOWN
    │
    └── Result 00 + sauberer Completion ────→ SUCCESS
```

Wichtig:

```text
FAILED vor AUTHORISATION_SENT
```

kann sicher erneut versucht werden.

```text
UNKNOWN nach AUTHORISATION_SENT
```

darf niemals automatisch erneut authorisiert werden.

---

# 53. Client-Verhalten bei Payment-Status

Beispiel logische Broker-Antworten:

### gestartet

```json
{
  "requestId": "...",
  "state": "IN_PROGRESS",
  "terminalId": "terminal-1"
}
```

### erfolgreich

```json
{
  "requestId": "...",
  "state": "SUCCESS",
  "terminalId": "terminal-1",
  "amountMinor": 1850
}
```

### Terminal belegt

```json
{
  "requestId": "...",
  "state": "FAILED",
  "error": "TERMINAL_BUSY"
}
```

### Terminal nach Recovery nicht gefunden

```json
{
  "requestId": "...",
  "state": "FAILED",
  "error": "TERMINAL_UNAVAILABLE"
}
```

### Ergebnis nach Kommunikationsverlust unklar

```json
{
  "requestId": "...",
  "state": "UNKNOWN",
  "error": "UNKNOWN_TRANSACTION_STATE"
}
```

Die tatsächlichen Nachrichtennamen müssen an das vorhandene Broker-Protokoll angepasst werden.

---

# 54. Tests: ZVT Codec

Unit-Tests mindestens für:

### BCD

```text
"000000" → 00 00 00
"123456" → 12 34 56
1850 → 00 00 00 00 18 50
```

### EUR Currency

```text
978 → 09 78
```

### TLV

```text
1F44
1F42
1F43
E4 nested container
unknown tag skipping
length < 128
0x81 length
0x82 length
```

### Fehler

```text
truncated packet
invalid BCD
invalid TLV length
socket EOF
timeout
```

---

# 55. Tests: Registry / Discovery

Mindestens:

1. Leere Registry + zwei simulierte Terminals:
   ```text
   terminal-1 / Terminal 1
   terminal-2 / Terminal 2
   ```
   werden erzeugt.

2. Broker-Neustart:
   - IDs bleiben gleich.
   - Namen bleiben gleich.

3. IP-Adressen vertauschen:
   ```text
   Serial A: .51 → .52
   Serial B: .52 → .51
   ```
   - Registry aktualisiert nur IPs.
   - Namen/IDs bleiben gleich.

4. Neues drittes Gerät:
   ```text
   terminal-3 / Terminal 3
   ```
   wird erzeugt.

5. Terminal 2 fehlt später:
   - Terminal 1 wird nicht umbenannt.
   - Terminal 3 wird nicht Terminal 2.

6. Gleiche Identität an zwei IPs:
   - `DUPLICATE_IDENTITY`
   - keine Zahlung möglich.

7. Offener Nicht-ZVT-Port:
   - nicht als Terminal registrieren.

---

# 56. Tests: Polling

Simuliere:

```text
AVAILABLE
NOT_READY
OFFLINE
```

und prüfe:

- Statuswechsel wird korrekt im Broker reflektiert.
- Poll während Payment erzeugt keine parallele ZVT-Nachricht.
- erster Pollingfehler löst Recovery Scan aus.
- weitere Fehler während Recovery-Cooldown lösen keinen Scan-Sturm aus.

---

# 57. Tests: Payment

Mindestens:

### Normalfall

```text
Client → terminal-1 / 1850
Broker verifiziert Identität
Broker sendet 18,50 EUR an korrektes Terminal
Terminal meldet Result 00
Completion
Client erhält SUCCESS
```

### Gleichzeitiger Zugriff

```text
POS 1 → terminal-1
POS 2 → terminal-1
```

Ergebnis:

```text
POS 1 läuft
POS 2 = TERMINAL_BUSY
```

### Unterschiedliche Terminals

```text
POS 1 → terminal-1
POS 2 → terminal-2
```

dürfen parallel laufen.

### IP-Tausch direkt vor Payment

Registry:

```text
terminal-1 → .51
```

tatsächlich:

```text
.51 = terminal-2
.52 = terminal-1
```

Erwartung:

```text
Broker darf NICHT an .51 authorisieren.
Broker erkennt Identity mismatch.
Recovery Scan.
Broker findet terminal-1 auf .52.
Broker aktualisiert Registry.
Broker authorisiert .52.
```

Dies ist ein zwingender Acceptance Test.

---

# 58. Tests: Idempotenz

Gleicher Request zweimal:

```text
requestId=A
amount=1850
terminal=terminal-1
```

muss genau **eine** ZVT Authorisation erzeugen.

Auch wenn die zweite Client-Nachricht während der laufenden Zahlung eintrifft.

---

# 59. Tests: Kommunikationsverlust

Fall:

```text
Broker schreibt 06 01 auf Socket
Terminal erhält Request
TCP-Verbindung wird unterbrochen
```

Erwartung:

```text
keine automatische zweite Authorisation
Payment = UNKNOWN_TRANSACTION_STATE
```

Dieser Test ist zwingend.

---

# 60. Test-Terminal-Simulator

Für automatisierte Tests einen kleinen Fake-ZVT-Server implementieren oder bestehende Testmöglichkeiten verwenden.

Der Simulator soll mindestens beherrschen:

```text
05 01 Status-Enquiry
06 00 Registration
06 01 Authorisation

ACK
Intermediate Status
Final Status
Completion
Abort
connection drop
delayed response
identity values
IP/endpoint simulation
```

Der Simulator ist Testcode und kein Produktionsdienst.

---

# 61. Manuelle Integrationstests mit A920

Vor Produktivsetzung:

1. ZVT-Konfiguration des A920 prüfen.
2. IP und aktuellen Port ermitteln.
3. ZVT-Passwort feststellen.
4. Broker im gleichen lokalen Netz.
5. Erst nur Status-Enquiry testen.
6. Seriennummer/TID protokolliert verifizieren.
7. Discovery mit beiden A920 testen.
8. Geräte neu mit WLAN verbinden / DHCP-Lease ändern.
9. prüfen, dass `Terminal 1` und `Terminal 2` nicht vertauscht werden.
10. erst danach echte Payment-Tests durchführen.

Für erste Zahlungstests kleine Testbeträge verwenden und Transaktionen anschließend kontrollieren.

---

# 62. Nicht Bestandteil von Version 1

Nicht ohne gesonderte Anforderung implementieren:

```text
Refund UI
manuelles Reversal UI
End-of-Day aus POS
Terminal-Konfigurationsänderung über ZVT
Set/Reset Terminal-ID
Kartendaten lesen
PAN-Verarbeitung
DCC-spezifische Logik
Tip-Booking über separates ZVT-Kommando
OPI
mAPI
ZVT-H
Cloud-Terminalvermittlung
```

Architektur so halten, dass spätere ZVT-Kommandos ergänzt werden können.

---

# 63. Akzeptanzkriterien

Die Implementierung gilt erst als fertig, wenn alle folgenden Punkte erfüllt sind:

- [ ] Broker findet beide Nexi A920 automatisch im lokalen Netz.
- [ ] Broker identifiziert jedes Terminal über stabile ZVT-Gerätekennung.
- [ ] Erste Erkennung erzeugt automatisch `terminal-1` / `Terminal 1`, `terminal-2` / `Terminal 2`.
- [ ] IDs und Namen bleiben nach Broker-Neustart erhalten.
- [ ] IDs und Namen bleiben bei DHCP-/IP-Wechsel erhalten.
- [ ] IP-Änderungen werden durch Discovery aktualisiert.
- [ ] Full Scan läuft einmal pro 24 Stunden.
- [ ] Polling läuft standardmäßig alle 60 Sekunden.
- [ ] erster Pollingfehler löst Recovery Scan aus.
- [ ] Scan-Cooldown verhindert Scan-Sturm.
- [ ] POS erhält nur logische ID, Name und Status.
- [ ] POS sendet bei Zahlung die logische `terminalId`.
- [ ] Broker prüft **vor jeder Zahlung** die physische Terminal-Identität.
- [ ] Bei vertauschten IPs geht der Betrag garantiert an das ausgewählte physische Terminal.
- [ ] Zwei POS können gleichzeitig unterschiedliche Terminals verwenden.
- [ ] Dasselbe Terminal kann nicht von zwei POS gleichzeitig benutzt werden.
- [ ] Betrag wird als Integer-Cent übergeben.
- [ ] 18,50 EUR wird als 6-Byte-BCD korrekt zu `00 00 00 00 18 50` kodiert.
- [ ] Erfolgsstatus wird erst nach konsistent abgeschlossenem ZVT-Dialog zurückgegeben.
- [ ] Idempotenz verhindert doppelte Zahlungen.
- [ ] Nach Kommunikationsabbruch nach Versand von `06 01` erfolgt kein automatischer Payment-Retry.
- [ ] sensible Kartendaten werden weder gespeichert noch geloggt.
- [ ] `Manuell` funktioniert weiterhin ohne ZVT.
- [ ] keine kostenpflichtige ZVT-/Payment-Middleware oder Runtime-Lizenz wird eingeführt.
- [ ] Unit-Tests und Integrationstests sind vorhanden.
- [ ] Konfiguration und Betriebsanleitung sind dokumentiert.

---

# 64. Erwartete Code-Struktur

An bestehende Projektstruktur anpassen. Logisch sollen Verantwortlichkeiten ungefähr getrennt sein:

```text
payment/
    PaymentService
    PaymentRequest
    PaymentResult
    PaymentState

terminal/
    TerminalManager
    TerminalRegistry
    TerminalRecord
    TerminalStatus
    TerminalDiscovery
    TerminalSession

zvt/
    ZvtCodec
    ZvtFrame
    ZvtBcd
    ZvtTlvParser
    ZvtStatusEnquiry
    ZvtRegistration
    ZvtAuthorisation
    ZvtResultParser
```

Keine God-Class mit Netzwerk, Discovery, Registry, Protokollparser und Payment-Logik in einer Datei.

---

# 65. Reihenfolge der Implementierung

Empfohlene Reihenfolge:

## Phase 1 – Codec

```text
BCD
APDU framing
TLV parser
```

mit Unit-Tests.

## Phase 2 – Status-Enquiry

```text
direkter Test gegen ein A920
```

Ziel:

```text
device name
serial
terminal identifier
device state
```

lesen.

## Phase 3 – Discovery + Registry

```text
Subnet scan
Terminal 1 / Terminal 2
IP update
Polling
```

## Phase 4 – Payment

```text
Registration
Authorisation
Result Handling
Terminal Lock
```

## Phase 5 – Broker-/Client-Integration

```text
Terminal list
Terminal selection
Payment request/result
Manuell
```

## Phase 6 – Failure/Concurrency Tests

```text
IP swap
offline
two POS
duplicate request
connection loss
```

Erst danach produktiv schalten.

---

# 66. Technische Referenzen

## Offizielle ZVT-Spezifikation

Verband der Terminalhersteller in Deutschland e.V.  
**ECR-Interface ZVT-Protocol – Official ZVT ECR Specification, Revision 13.07**

Downloadbereich:

https://www.terminalhersteller.de/Downloads.aspx

Für diese Implementierung besonders relevant:

```text
2.1   Registration (06 00)
2.2   Authorisation (06 01)
2.23  Abort (06 B0)
2.55  Status-Enquiry (05 01)
9     TLV-Container
9.6.13 Configuration TLVs
13    Summary of utilised BMPs
```

Relevante ZVT-Fakten aus Revision 13.07:

```text
BMP 04 = 6 Byte BCD Amount in minor currency units
BMP 49 = 2 Byte BCD Currency
EUR = 09 78

1F40 Device name
1F41 Software version
1F42 Serial number
1F43 Device state
1F44 Terminal identifier
1F55 Terminal locks
```

## Nexi SmartPOS A920

Nexi bestätigt für das SmartPOS A920:

```text
Kassenanbindung über ZVT und O.P.I.
Kommunikation über WLAN bzw. Mobilfunk für Payment-Host-Kommunikation.
Für lokale ZVT-Kassenanbindung muss das Gerät über das lokale IP-Netz erreichbar sein.
```

Produktseite:

https://www.nexi.de/de/bezahlloesungen/kartenlesegeraete/mobile-pos/nexi-smartpos-a920

A920 Bedienungsanleitung:

https://www.nexi.de/content/dam/nexide/downloads/download-center/bedienungsanleitungen-terminals/bedienungsanleitung_DE_A920.pdf

## A920 / SECpos EVO Portvarianten

Je nach Betreiber-/SECpos-EVO-Konfiguration kommen bei externem ZVT insbesondere unterschiedliche Ports vor. Deshalb Discovery nicht auf einen einzigen Port fest verdrahten.

CCV Android Developer Guidelines:

https://developer.myccv.eu/reference/android_app_requirements/android-developer-guidelines-and-faqs-august-2022.pdf

Zusätzlich dokumentieren Betreiberkonfigurationen für A920 unter anderem 20007 bzw. 20011. Daher ist die Portliste konfigurierbar zu halten.

---

# 67. Wichtigste Designregeln in Kurzform

```text
1. Client kennt terminal-1, nicht 192.168.x.x.

2. terminal-1 bleibt immer dasselbe physische Gerät,
   solange es nicht bewusst administrativ ersetzt wird.

3. IP ist nur ein Cache / aktueller Endpoint.

4. Status-Enquiry identifiziert das Gerät.

5. Polling:
   alle ~60 s.

6. Discovery:
   alle 24 h
   + sofort beim ersten Pollingfehler.

7. Vor JEDEM Payment:
   Identity am aktuellen Endpoint erneut verifizieren.

8. Bei falscher/fehlender Identity:
   erst Discovery,
   dann Payment.

9. Nie automatisch auf ein anderes Terminal ausweichen.

10. Ab Versand von 06 01:
    niemals blind retryen.

11. requestId macht Payments idempotent.

12. Keine Kartendaten speichern.

13. Keine kostenpflichtige Middleware.
```

---

# 68. Definition of Done für den KI-Agenten

Am Ende der Arbeit:

1. Implementierung vollständig durchführen.
2. Alle Tests ausführen.
3. Fehler beheben.
4. Keine TODO-Platzhalter für Kernfunktionen hinterlassen.
5. Dokumentieren:
   - geänderte Dateien,
   - neue Konfigurationsparameter,
   - benötigte A920-Einstellungen,
   - wie Discovery gestartet/geprüft wird,
   - wie ein Terminal manuell neu gescannt werden kann,
   - wie ein Payment-Test ausgeführt wird.
6. Einen kurzen Betriebs-/Troubleshooting-Abschnitt ergänzen für:
   - Terminal offline,
   - IP geändert,
   - falsches ZVT-Passwort,
   - Port geändert,
   - `UNKNOWN_TRANSACTION_STATE`.
7. Sicherstellen, dass die Implementation ohne kostenpflichtige SDKs oder Middleware funktioniert.


---

# 69. Zusatzspezifikation: Händler- und Kundenbeleg über ZVT

## Ziel

Bei jeder über ZVT ausgeführten Kartenzahlung soll der Broker vom Zahlungsterminal sowohl den Händlerbeleg als auch den Kundenbeleg anfordern und vollständig empfangen.

Der Belegdruck erfolgt anschließend durch das POS-System (via CUPS auf EPSON TM-T20II) und nicht automatisch durch das Zahlungsterminal.

## Ablauf

```text
Kartenzahlung
    ↓
Broker fordert Händler- und Kundenbeleg an
    ↓
Zahlung wird abgeschlossen
    ↓
Händlerbeleg wird automatisch gedruckt
    ↓
POS fragt: "Kundenbeleg drucken?"
    ↓
Ja  → Kundenbeleg drucken
Nein → kein Kundenbeleg
```

## 69.1 ZVT-Registration

Die ZVT-Registration muss so konfiguriert werden, dass der ECR/Broker den Belegdruck übernimmt. Das Terminal soll die Beleginformationen über die dafür vorgesehenen ZVT-Nachrichten an den Broker übertragen.

Unterstützte Formate:
- **06 D3** – Print Text-Block (bevorzugt)
- **06 D1** – Print Line (Fallback)

Die Implementierung muss beide Formate verarbeiten können.

## 69.2 Belegarten unterscheiden

Bei 06 D3 ist das TLV-Feld **1F07 – Receipt Type** auszuwerten:

```text
01 = Händlerbeleg
02 = Kundenbeleg
03 = Administrationsbeleg
```

Der Broker puffert Händler- und Kundenbeleg getrennt. Administrationsbelege dürfen nicht versehentlich als Kunden- oder Händlerbeleg behandelt werden.

## 69.3 Beleg-Pufferung

Belege können in mehreren ZVT-Nachrichten eintreffen und werden transaktionsbezogen gesammelt (Zuordnung über requestId + terminalId).

## 69.4 Händlerbeleg

Nach erfolgreichem Abschluss automatisch drucken. Keine Benutzerabfrage. Der Beleg wird am POS gedruckt, der die Zahlung ausgelöst hat.

## 69.5 Kundenbeleg

Nicht automatisch drucken. POS zeigt "Kundenbeleg drucken?" mit Ja/Nein. Bei Ja wird der bereits gespeicherte Beleg gedruckt — keine neue Anfrage an das Terminal.

## 69.6 Zahlungsantwort an POS

```json
{
  "requestId": "abc123",
  "state": "SUCCESS",
  "terminalId": "terminal-1",
  "receipts": {
    "merchantReceiptAvailable": true,
    "merchantReceiptPrinted": true,
    "customerReceiptAvailable": true
  }
}
```

## 69.7 Druckstatus ≠ Zahlungsstatus

Ein Druckfehler verändert den SUCCESS-Status der Kartenzahlung nicht. Bei Druckfehler: `warning: "MERCHANT_RECEIPT_PRINT_FAILED"`.

## 69.8 Reprint

Beide Belege sind per `PRINT_MERCHANT_RECEIPT(requestId)` bzw. `PRINT_CUSTOMER_RECEIPT(requestId)` nachdruckbar. Reprint löst niemals eine neue Zahlung aus.

## 69.9 Fehlender Beleg

- Fehlender Händlerbeleg: `MERCHANT_RECEIPT_MISSING` warnen, Zahlung nicht wiederholen.
- Fehlender Kundenbeleg: `customerReceiptAvailable = false`, keine Nachfrage anzeigen.

## 69.10 Beleginhalt nicht neu formatieren

Vom Terminal erzeugter Beleginhalt wird unverändert gedruckt. Keine eigenständige Rekonstruktion von Zahlungsinformationen.

## 69.11 Druck über CUPS

Kartenzahlungsbelege werden direkt über CUPS an den EPSON TM-T20II gedruckt — unabhängig von der OrderSprinter-Druckwarteschlange.

## 69.12 Änderung gegenüber Hauptspezifikation

Das A920 soll nicht mehr selbst die Belege drucken. Der config-byte der Registration wird so gesetzt, dass ECR receipt printing aktiviert ist. Der Wert ist konfigurierbar (Standard: 0x1E).
