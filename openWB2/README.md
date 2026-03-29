# openWB2 (IP-Symcon Modul)

## Beschreibung

Dieses Modul integriert eine **openWB Wallbox** über **MQTT (SimpleAPI)** in **IP-Symcon**.

**Das Modul erfordert IP-Symcon ab 8.1 und openWB ab 2.1.9**

Es ermöglicht das **Auslesen von Status- und Energiedaten** sowie das **Steuern von Ladevorgängen** direkt aus IP-Symcon heraus.

Die Kommunikation erfolgt über den MQTT-Client von IP-Symcon und nutzt hauptsächlich die **SimpleAPI Topics** der openWB.

---

## Funktionen

### Statusdaten

Das Modul liest zahlreiche Informationen der Wallbox aus, die zugehörigen Variablen lassen sich über Checkboxen erstellen:

- aktuelle Ladeleistung
- Strom pro Phase
- Spannung pro Phase
- Frequenz
- Leistungsfaktor
- verwendete Phasen
- Ladezustand
- Steckstatus
- Fehlerstatus und Fehlermeldungen
- Fahrzeugname
- RFID
- Fahrzeug-SoC (falls verfügbar)
- Energieverbrauch (Tag / Gesamt)
- und viele mehr...

---

### Steuerfunktionen

Folgende Einstellungen können direkt über IP-Symcon gesteuert werden, auch diese lassen sich an und abwählen:

- Ladepunkt aktivieren
- Lademodus
- Stromstärke
- Phasen Sofortladen
- Sollleistung
- Mindest SoC für das Fahrzeug
- Minimaler Dauerstrom
- Höchstpreis Eco
- LadePriorität
- Begrenzung
- SoC-Limit für das Fahrzeug
- Energie Limit


---

### Automatische Phasenumschaltung

Das Modul unterstützt eine automatische Umschaltung zwischen **einphasigem und dreiphasigem Laden** abhängig von der gewünschten Ladeleistung und ist im Sofort-Lademodus verfügbar. Diese Umschaltung wir automatisch abhängig von der Sollleistung ausgelöst.

---

## Installation
 
1. Modul über den Modul-Store oder als Repository hinzufügen  
2. Eine Instanz des Moduls **openWB** erstellen  
3. Den MQTT-Client als Parent-Instanz auswählen  
4. In der Instanzenkonfiguration des Clientsockets ist die IP-Adresse und der Port (Standard 1883) der openWB zu wählen

---

## Konfiguration

| Einstellung | Beschreibung |
|-------------|--------------|
| MQTT Topic | Basis-Topic der openWB - Standard ist 'openWB'.  |
| Ladepunkt ID | ID des Ladepunktes - Diese ID findet man unter Konfiguration - Ladepunkte und wird zur Kommunikation mit dem Ladepunkt (Wallbox) benötig. |
| Ladepunkt-Profil ID | ID des Charge Templates - Diese ID findet man unter Konfiguration - Ladepunkte und wird zur korrekten Erstellung des Templates benötig. |
| Fahrzeug ID | ID des Fahrzeugs - Diese ID findet man unter Konfiguration - Fahrzeuge und ist für das Standard-Fahrzeug meist 0. Diese ID wird zur korrekten Übermittlung der SOC-Daten an die openWB benötigt. |
| EV-SoC Datenpunkt | Fahrzeug-SoC mit Nachkommastellen (Float) oder Ganzzahl. Der Empfang dieser Daten muss in der openWB unter Konfiguration - Fahrzeuge unter dem Menupunkt 'SoC-Modul des Fahrzeugs' als MQTT konfiguriert werden.|
| SoC Zeitstempel Datenpunkt | Zeitstempel des SoCs in s als Unix-Zeitstempel. Diese Info ist optional. Wird kein Wert für das Topic veröffentlicht, wird bei der Abfrage automatisch der aktuelle Zeitstempel gesetzt. |
| Reichweite Datenpunkt | Reichweite des Fahrzeugs in km mit Nachkommastellen (Float) oder Ganzzahl. Diese Info ist optional.. |
| Minimalstrom pro Phase | minimaler Ladestrom - Diese Einstellung wirkt sich direkt auf das Variablenprofil OWB.TargetPower.<Instanz-ID> und OWB.Ampere.<Instanz-ID> aus,um aus, um den Regelbereich für die vorgegebene Sollleistung und Ampere-Bereich festzulegen.  |
| Maximalstrom pro Phase | maximaler Ladestrom - Diese Einstellung wirkt sich direkt auf das Variablenprofil OWB.TargetPower.<Instanz-ID> und OWB.Ampere.<Instanz-ID> aus,um aus, um den Regelbereich für die vorgegebene Sollleistung und Ampere-Bereich festzulegen. |
| Sperrzeit Phasenumschaltung | Zeit in Sekunden, während der kein erneuter Phasenwechsel erlaubt ist - Der Grund hier ist, eine Störmeldung des angeschlossenen Farhrezuges zu verhindern. Voreingestellt sind 60 sec |

---

## Integratioen in den Energiemanager

Für eine Integration in den Energiemanager ist der Lademodus 'Sofort' zu wählen. Im Energiemanager, unter Konfigurtion der Wallbox, ist bei der Leistung (Soll) die Variable 'Sollleistung' des openWB2- Moduls zu wählen. Ebenfalls ist in der Auswahl für die Variable An/Aus die Variable 'Ladepunkt aktivieren' zu wählen. Ebenfalls ist im Energiemanger eine Mindestlaufzeit zu definieren, da ansonsten ein sehr häufiger Wechsel der Phasen statt findet.

---

## MQTT Kommunikation

Das Modul verwendet hauptsächlich die **SimpleAPI MQTT Topics** der openWB.
Die Umschaltung erfolgt über das Anpassen des Ladeprofil der openWB und ist als einzige Funktion nicht Umfang der SimpleAPI.

### Beispiele gelesener Topics

openWB/simpleAPI/chargepoint/0/power
openWB/simpleAPI/chargepoint/0/charging_current
openWB/simpleAPI/chargepoint/0/phases_in_use

---

## Sollwerte

| Variable | Beschreibung |
|----------|--------------|
| Ladepunkt aktivieren | Ladepunkt aktivieren oder deaktivieren |
| Lademodus | Lademodus am Ladepunkt ändern |
| Stromstärke | Ladestrom für das Sofort laden |
| Phasen Sofortladen | Anzahl Phasen beim Sofort-Laden (1 oder Maximum) |
| Sollleistung | Vorgegebene Ladeleistung beim Sofortladen. Wir die Vorgabe über diesen Wert gemacht, findet die Phasenumschaltung automatisch statt |
| Mindest SoC für das Fahrzeug | Nur ab openWB Revision 2 unterstützt - Minimaler EV SoC im PV Laden |
| Minimaler Dauerstrom | Nur ab openWB Revision 2 unterstützt - Minimaler Dauerstrom für das PV Laden |
| Höchstpreis Eco | Nur ab openWB Revision 2 unterstützt - Maximaler Preis für das ECO Laden |
| LadePriorität |
| Begrenzung | Nur ab openWB Revision 2 unterstützt - Setzt den Typ der Ladebegrenzung für das Sofortladen |
| SoC-Limit für das Fahrzeug | Nur ab openWB Revision 2 unterstützt - Setzt die SoC-Grenze für das Sofortladen (aktiv wenn Limit-Typ „EV-SoC“ ist) |
| Energie Limit | Nur ab openWB Revision 2 unterstützt - Setzt die Energiegrenze für das Sofortladen (aktiv wenn Limit-Typ „Energie“ ist) |

---

## Istwerte

| Variable | Beschreibung |
|----------|--------------|
| EV-SoC | Ladezustand des Fahrzeugs in Prozent |
| Pro-SoC | Prognostizierter SoC des Fahrzeugs |
| Pro-SoC Zeitstempel | Zeitstempel der letzten Pro-SoC Aktualisierung |
| EVSE Aktuell | Aktuell von der Wallbox vorgegebener Ladestrom |
| Strom Phase 1 | Strom auf Phase 1 |
| Strom Phase 2 | Strom auf Phase 2 |
| Strom Phase 3 | Strom auf Phase 3 |
| Spannung Phase 1 | Spannung auf Phase 1 |
| Spannung Phase 2 | Spannung auf Phase 2 |
| Spannung Phase 3 | Spannung auf Phase 3 |
| Frequenz | Netzfrequenz |
| Leistung Phase 1 | Leistung auf Phase 1 |
| Leistung Phase 2 | Leistung auf Phase 2 |
| Leistung Phase 3 | Leistung auf Phase 3 |
| Leistungsfaktor Phase 1 | Leistungsfaktor auf Phase 1 |
| Leistungsfaktor Phase 2 | Leistungsfaktor auf Phase 2 |
| Leistungsfaktor Phase 3 | Leistungsfaktor auf Phase 3 |
| Ladeleistung | Aktuelle Ladeleistung des Fahrzeugs |
| Verwendete Phasen | Anzahl der aktuell genutzten Phasen |
| Ladestatus | Gibt an ob das Fahrzeug gerade lädt |
| Stecker Status | Gibt an ob ein Fahrzeug angeschlossen ist |
| Status | Gesamtstatus des Ladepunktes |
| Fehlerstatus | Fehlercode der Wallbox |
| Fehlertext | Beschreibung des aktuellen Fehlers |
| Fehler Zeitstempel | Zeitpunkt des letzten Fehlers |
| Statustext | Textueller Status der Wallbox |
| Fahrzeug Name | Name des verbundenen Fahrzeugs |
| RFID | Letzte erkannte RFID |
| RFID Zeitstempel | Zeitpunkt der letzten RFID-Erkennung |
| Energie Tag | Geladene Energie des aktuellen Tages |
| Energie Tag Export | Exportierte Energie des aktuellen Tages |
| Energie Gesamt | Gesamte geladene Energie |
| Energie Gesamt Export | Gesamte exportierte Energie |
| Seriennummer | Seriennummer der Wallbox |
| Fahrzeug ID | ID des verbundenen Fahrzeugs |
| Version | Softwareversion der Wallbox |
| EVSE Signaling | Signalisierungszustand der EVSE |
| Revision | Revisionsnummer der openWB |
| Aktuelle Ladeleistung | Momentane Ladeleistung während des Ladevorgangs |
| Aktuelle Ladespannung | Aktuelle Spannung während des Ladens |
| Max. Entladeleistung | Maximale mögliche Entladeleistung |
| Max. Ladeleistung | Maximale mögliche Ladeleistung |

---

## Version

Version 1.1 (29.3.26)
- Sporadisches Blockieren der Phasenumschaltung durch anpassen des zugehörigen Timers auf 500ms behoben.
- Voreingstellte Sperre nach Phasenumschaltung auf 120sec erhöht.
- Zum berechnen des Stroms (Ampere) aus der Soll-Leistung wird nun fix 235V verwendet, da sonst der geforderte Strom nicht erreicht wird.
- Der SOC kann nun der openWB durch Einbinden der entsprechenden Datenpunkte mitgeteilt werden.

Version 1.0 (15.3.26)
- Initale Version

---

## Lizenz

MIT License
