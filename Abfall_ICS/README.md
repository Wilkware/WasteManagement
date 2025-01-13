# Abfall.ICS

[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg?style=flat-square)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-6.4-blue.svg?style=flat-square)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-1.2.20250107-orange.svg?style=flat-square)](https://github.com/Wilkware/WasteManagement)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg?style=flat-square)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Actions](https://img.shields.io/github/actions/workflow/status/wilkware/WasteManagement/style.yml?branch=main&label=CheckStyle&style=flat-square)](https://github.com/Wilkware/WasteManagement/actions)

IP-Symcon Modul für die Visualisierung von Entsorgungsterminen.

## Inhaltverzeichnis

1. [Funktionsumfang](#user-content-1-funktionsumfang)
2. [Voraussetzungen](#user-content-2-voraussetzungen)
3. [Installation](#user-content-3-installation)
4. [Einrichten der Instanzen in IP-Symcon](#user-content-4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#user-content-5-statusvariablen-und-profile)
6. [Visualisierung](#user-content-6-visualisierung)
7. [PHP-Befehlsreferenz](#user-content-7-php-befehlsreferenz)
8. [Versionshistorie](#user-content-8-versionshistorie)

### 1. Funktionsumfang

Das Modul bietet einen generischen Abfallkalender mittels der Bereitstellung einer iCalendar Datei (ICS).

### 2. Voraussetzungen

* IP-Symcon ab Version 6.4

### 3. Installation

* Über den Modul Store das Modul Abfallwirtschaft (ehem. Awido) installieren.
* Alternativ Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/Wilkware/WasteManagement` oder `git://github.com/Wilkware/WasteManagement.git`

### 4. Einrichten der Instanzen in IP-Symcon

* Unter "Instanz hinzufügen" ist das _'Waste_ICS'_-Modul (Alias: _'Abfallwirtschaft (Abfall_ICS)'_ oder _'Entsorgungskalender (Abfall_ICS)'_)  unter dem Hersteller _'(Geräte)'_ aufgeführt.

__Konfigurationsseite__:

Entsprechend der gewählten Auswahl verändert sich das Formular dynamisch.
Eine komplette Neuauswahl erreicht man durch Auswahl "Bitte wählen ..." an der gewünschten Stelle.

VORSTICHT: eine Änderung der Auswahl bedingt ein Update bzw. ein Neuanlegen der Statusvariablen!!!
Alte Variablen, welche es im anderen Landkreis gab werden nicht gelöscht! Hat man diese in einem WF verlinkt muss man danach
selber aufräumen. Ich denke aber mal das ein Umzug nicht so häufig vorkommt ;-)

_Einstellungsbereich:_

> Online Dienste ...

Name                    | Beschreibung
----------------------- | ----------------------------------
Anbieter                | 'Abfall.ICS (asmium.de)'
Land                    | Landesauswahl (derzeit DE, AT)

> Abfallwirtschaft ...

Name                             | Beschreibung
-------------------------------- | ---------------------------------
Entsorgungsgebiet (vordefiniert) | Liste der vordefinierten Abfallwirtschaften
WEITER                           | Öffnet im neuen Browser-Fenster die selektierte Abfallwirtschafts-Seite
Daten importieren                | Auswahl via File oder Link (URL)
Datei auswählen                  | Auswahl einer Kalender-Datei (ICS) aus dem File-System
Download URL (iCal-File)         | Link zum Herunterladen der Kalender-Datei (ICS)
ANALYSIEREN                      | Analysiert die in der Kalenderdatei enthaltenen Entsorgungsarten
Entsorgungen                     | Entsorgungsarten, d.h. was wird im Gebiet an Entsorgung angeboten

> Visualisierung ...

Name                                                    | Beschreibung
------------------------------------------------------- | ---------------------------------
Unterstützung für Tile Visu aktivieren?                 | Aktivierung, ob HTML für Kacheldarstellung erstellt werden soll
Abfallgruppen                                           | Farbliche Zuordnung der Abfallarten
Vorrausschauende Anzeige für Folgetage aktivieren?      | Aktivierung, ob zu einer bestimmten Zeit die Anzeige umschalten soll auf Folgetermine
Zeitpunkt                                               | Uhrzeit, wo die Umschaltung erfolgen soll

> Erweiterte Einstellungen ...

Name                                                    | Beschreibung
------------------------------------------------------- | ---------------------------------
Tägliche Aktualisierung aktivieren?                     | Status, ob das tägliche Update aktiv oder inaktiv ist
Variablen für nicht ausgewählte Entsorgungen erstellen? | Status, ob für nicht genutzte Entsorgungen auch Variablen angelegt werden sollen, standardmäßig nein
Skript                                                  | Skript, welches nach dem Update der Termine ausgeführt wird, z.B. für Sortierung usw.

_Aktionsbereich:_

Aktion                  | Beschreibung
----------------------- | ---------------------------------
AKTUALISEREN            | Werte werden neu ermittelt und geschrieben

### 5. Statusvariablen und Profile

Die Statusvariablen/Timer werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

Name               | Typ       | Beschreibung
-------------------| --------- | ----------------
Entsorgungsart(en) | String    | Abhängig vom Entsorgungsgebiet und den angebotenem Service mehrere Variablen, z.B.: Restmüll, Biotonne usw.

Es werden keine zusätzlichen Profile benötigt.

### 6. Visualisierung

Man kann die Statusvariablen(Strings) direkt im WF verlinken.  
Aber wie bei der Konfiguration beschrieben, muss man aufpassen wenn die Konfiguration geändert wird. Dann müssen gegebenenfalls die Links neu eingerichtet werden.

### 7. PHP-Befehlsreferenz

```php
void WMICS_LookAhead(int $InstanzID);
```

Stellt in der Visualisierung den für Folgetage anstehenden Entsorgungstermine dar.  
Die Funktion liefert keinerlei Rückgabewert.

__Beispiel__: `WMICS_LookAhead(12345);`

```php
void WMICS_Update(int $InstanzID);
```

Holt die nächsten anstehenden Entsorgungstermine für die gewählten Entsorgungsarten.  
Die Funktion liefert keinerlei Rückgabewert.

__Beispiel__: `WMICS_Update(12345);`


### 8. Versionshistorie

v1.2.20250107

* _NEU_: Internationalisierte Anbieterauswahl
* _NEU_: ICS Datei kann vom Filesystem geladen werden
* _FIX_: Events in ICS werden standardmäßig sortiert
* _FIX_: Dokumentation verbessert

v1.1.20240702

* _NEU_: Vorrausschauende Anzeige
* _FIX_: URL Prüfung verbessert

v1.0.20240304

* _NEU_: Initialversion

## Entwickler

Seit nunmehr über 10 Jahren fasziniert mich das Thema Haussteuerung. In den letzten Jahren betätige ich mich auch intensiv in der IP-Symcon Community und steuere dort verschiedenste Skript und Module bei. Ihr findet mich dort unter dem Namen @pitti ;-)

[![GitHub](https://img.shields.io/badge/GitHub-@wilkware-181717.svg?style=for-the-badge&logo=github)](https://wilkware.github.io/)

## Spenden

Die Software ist für die nicht kommerzielle Nutzung kostenlos, über eine Spende bei Gefallen des Moduls würde ich mich freuen.

[![PayPal](https://img.shields.io/badge/PayPal-spenden-00457C.svg?style=for-the-badge&logo=paypal)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166)

## Lizenz

Namensnennung - Nicht-kommerziell - Weitergabe unter gleichen Bedingungen 4.0 International

[![Licence](https://img.shields.io/badge/License-CC_BY--NC--SA_4.0-EF9421.svg?style=for-the-badge&logo=creativecommons)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
