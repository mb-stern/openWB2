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

Das Modul liest zahlreiche Informationen der Wallbox aus, diese lassen sich aber über Checkboxen an- und abwählen:

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

Die Umschaltung erfolgt über das umschalten des Ladeprofil der openWB und ist als einzige Funktion nicht Umfang der SimpleAPI

---

## Voraussetzungen

- **IP-Symcon 7.x oder neuer**
- **MQTT-Client Instanz** in IP-Symcon
- **openWB mit aktivierter MQTT SimpleAPI**

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
| MQTT Topic | Basis-Topic der openWB |
| Ladepunkt ID | ID des Ladepunktes |
| Ladeprofil ID | ID des Charge Templates |
| Minimalstrom pro Phase | minimaler Ladestrom |
| Maximalstrom pro Phase | maximaler Ladestrom |
| Hysterese Phasenumschaltung | Leistungs-Hysterese für den Phasenwechsel |
| Sperrzeit Phasenumschaltung | Zeit in Sekunden, während der kein erneuter Phasenwechsel erlaubt ist |

---

## MQTT Kommunikation

Das Modul verwendet die **SimpleAPI MQTT Topics** der openWB.

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
