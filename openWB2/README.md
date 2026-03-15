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

Das Modul unterstützt eine automatische Umschaltung zwischen **einphasigem und dreiphasigem Laden** abhängig von der gewünschten Ladeleistung im Sofort-Lademodus verfügbar. Diese Umschaltung wir auutomatisch abhängig von der Sollleistung ausgelöst.

---

## Voraussetzungen

- **IP-Symcon 8.1 oder neuer**
- **openWB ab 2.1.9**

---

## Installation

1. Repository in das Modulverzeichnis von IP-Symcon kopieren  
2. Modul über den Modul-Store oder als Repository hinzufügen  
3. Eine Instanz des Moduls **openWB** erstellen  
4. Den MQTT-Client als Parent-Instanz auswählen  

---

## Konfiguration

| Einstellung | Beschreibung |
|-------------|--------------|
| MQTT Topic | Basis-Topic der openWB - Standard ist 'openWB'.  |
| Ladepunkt ID | ID des Ladepunktes - Diese ID findet man unter Konfiguration - Ladepunkte und wird zur Kommunikation mit dem Ladepunkt (Wallbox) benötig. |
| Ladepunkt-Profil ID | ID des Charge Templates - Diese ID findet man unter Konfiguration - Ladepunkte und wird zur korrekten Erstellung des Templates benötig. |
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


### Einstellungen

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

## Variablen (Auszug)

| Variable | Beschreibung |
|----------|--------------|
| Power | aktuelle Ladeleistung |
| PhasesInUse | aktive Phasen |
| ChargeState | Ladestatus |
| PlugState | Steckstatus |
| SoC | Fahrzeug-SoC | 
| Imported | Gesamtenergie |
| DailyImported | Energie heute |

---

## Version

Version 1.0 (15.3.26)
- Initale Version

---

## Lizenz

MIT License
