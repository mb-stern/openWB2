# openWB2 (IP-Symcon Modul)

## Beschreibung

Dieses Modul integriert eine **openWB Wallbox** über **MQTT (SimpleAPI)** in **IP-Symcon**.

Das Modul erfordert IP-Symcon ab 8.1 und openWB ab 2.1.9

Es ermöglicht das **Auslesen von Status- und Energiedaten** sowie das **Steuern von Ladevorgängen** direkt aus IP-Symcon heraus.

Die Kommunikation erfolgt über den MQTT-Client von IP-Symcon und nutzt die **SimpleAPI Topics** der openWB.

Für eine Integration in den Energiemanager ist dort unter Leistung (Soll) die Variable 'Sollleistung' des Moduls zu wählen. Ebenfalls ist in der AUswahl für die Variable An/Aus die Variable 'Ladepunkt aktivieren' zu wählen.

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
- Phasen Sofortladen (1 oder Maximum)
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

Die Phasenumschaltung ist nur im Sofort-Lademodus verfügbar.

Das Modul unterstützt eine automatische Umschaltung zwischen **einphasigem und dreiphasigem Laden** abhängig von der gewünschten Ladeleistung.

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

## MQTT Kommunikation

Das Modul verwendet hauptsächlich die **SimpleAPI MQTT Topics** der openWB.
Die Umschaltung erfolgt über das umschalten des Ladeprofil der openWB und ist als einzige Funktion nicht Umfang der SimpleAPI.

### Beispiele gelesener Topics

openWB/simpleAPI/chargepoint/0/power
openWB/simpleAPI/chargepoint/0/charging_current
openWB/simpleAPI/chargepoint/0/phases_in_use


### Schreibzugriffe


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

Version 1.0 (10.3.26)
- Initale Version

---

## Lizenz

MIT License
