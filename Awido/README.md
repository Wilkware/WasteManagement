# Awido

[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg?style=flat-square)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-6.4-blue.svg?style=flat-square)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-4.2.20250107-orange.svg?style=flat-square)](https://github.com/Wilkware/WasteManagement)
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

Das Modul nutzt die von awido (www.awido-online.de) bereitgestellten Daten zur Berechnung
der bevorstehenden Entsorgungstermine (Abfallentsorgung).

Derzeit unterstützt das Modul 42 verschiedene Landkreise und Großstädte. Wenn jemand noch weitere kennt, bitte einfach bei mir melden!

### 2. Voraussetzungen

* IP-Symcon ab Version 6.4

### 3. Installation

* Über den Modul Store das Modul Abfallwirtschaft (ehem. Awido) installieren.
* Alternativ Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/Wilkware/WasteManagement` oder `git://github.com/Wilkware/WasteManagement.git`

### 4. Einrichten der Instanzen in IP-Symcon

* Unter "Instanz hinzufügen" ist das _'Awido'_-Modul (Alias: _'Abfallwirtschaft (Awido)'_ oder _'Entsorgungskalender (Awido)'_)  unter dem Hersteller _'(Geräte)'_ aufgeführt.

__Konfigurationsseite__:

Entsprechend der gewählten Auswahl verändert sich das Formular dynamisch.
Eine komplette Neuauswahl erreicht man durch Auswahl "Bitte wählen ..." an der gewünschten Stelle.

VORSTICHT: eine Änderung der Auswahl bedingt ein Update bzw. ein Neuanlegen der Statusvariablen!!!
Alte Variablen, welche es im anderen Landkreis gab werden nicht gelöscht! Hat man diese in einem WF verlinkt muss man danach
selber aufräumen. Ich denke aber mal das ein Umzug nicht so häufig vorkommt ;-)

_Einstellungsbereich:_

> Online Dienste ...

Name                    | Beschreibung
----------------------- | ---------------------------------
Anbieter                | 'AWIDO (awido-online.de)'
Land                    | Landesauswahl (derzeit nur DE)

> Abfallwirtschaft ...

Name                    | Beschreibung
----------------------- | ---------------------------------
Entsorgungsgebiet       | Liste der verfügbaren Gebiete (siehe oben)
Stadt/Gemeinde          | Ort im Entsorgungsgebiet (kann identisch zum Gebiet sein)
Ortsteil/Strasse        | Ortsteil/Strasse im gewählten Ort
Hausnummer              | Hausnummer von-bis, oder Alle = gesamte Strasse
Entsorgungen            | Entsorgungsarten, d.h. was wird im Gebiet an Entsorgung angeboten

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
void AWIDO_LookAhead(int $InstanzID);
```

Stellt in der Visualisierung den für Folgetage anstehenden Entsorgungstermine dar.  
Die Funktion liefert keinerlei Rückgabewert.

__Beispiel__: `AWIDO_LookAhead(12345);`

```php
void AWIDO_Update(int $InstanzID);
```

Holt die nächsten anstehenden Entsorgungstermine für die gewählten Entsorgungsarten.  
Die Funktion liefert keinerlei Rückgabewert.

__Beispiel__: `AWIDO_Update(12345);`

### 8. Versionshistorie

v4.3.20250107

* _NEU_: Internationalisierte Anbieterauswahl
* _FIX_: Dokumentation verbessert

v4.2.20240702

* _NEU_: Vorrausschauende Anzeige
* _FIX_: URL Prüfung verbessert

v4.1.20240304

* _FIX_: Support für v7 Visualisierung verbessert
* _FIX_: Update für nicht aktivierte Abfallarten korrigiert
* _FIX_: Einige interne Vereinheitlichungen und Anpassungen
* _FIX_: Dokumentation korrigiert

v4.0.20231119

* _NEU_: Kompatibilität auf IPS 6.4 hoch gesetzt
* _NEU_: Support für v7 Visualisierung

v3.4.20230124

* _FIX_: Skripte in den erweiterten Einstellungen werden wieder gespeichert

v3.3.20220309

* _NEU_: Konfigurationsformular angepasst

v3.2.20211212

* _NEU_: Kompatibilität auf IPS 6.0 hoch gesetzt
* _NEU_: Konfigurationsformular an die neuen Möglichkeiten der 6.0 angepasst

v3.1.20210620

* _NEU_: Umstellung auf maximal 30 vewrschiedene Abfallarten
* _FIX_: IPS_SetProperty nicht mehr notwendig
* _FIX_: HTML Entities in Namen der Abfallarten werden jetzt dekodiert

v3.0.20210405

* _NEU_: Eigener Webservice (JSON-API) für Bereitstellung der unterstützten Gebiete (aktuell 36 Gebiete)
* _NEU_: Landkreis Augsburg (lose Zuordnung von verschiedenen Orten)
* _NEU_: Landkreis Gießen
* _NEU_: Landkreis Mühldorf am Inn
* _NEU_: Zweckverband Isar-Inn
* _FIX_: Modul Aliase auf 'Abfallwirtschaft (Awido)' und 'Entsorgungskalender (Awido)' geändert
* _FIX_: Umbau und Vereinheitlichungen des Konfigurationsformulars
* _FIX_: Vereinheitlichungen der Libs

v2.0.20201010

* _NEU_: Umstellung des Konfigurationsformulars auf dynamische Konfiguration
* _NEU_: Gemeinde Unterhaching
* _NEU_: Landkreis Gotha
* _NEU_: Landkreis Schweinfurt
* _NEU_: Stadt Kaufbeuren
* _NEU_: Zweckverband Saale-Orla
* _FIX_: Maximale Anzahl an Entsorgungsarten angepasst
* _FIX_: Dokumentation überarbeitet
* _FIX_: Debug Meldungen erweitert bzw. korrigiert
* _FIX_: Englische Übersetzung korrigiert

v1.4.20190814

* _NEU_: Anpassungen für Module Store
* _NEU_: Landkreis Fürstenfeldbruck

v1.3.20190323

* _NEU_: Vereinheitlichungen, Umstellung auf Libs
* _NEU_: Variablenerstellung kann nun vom Benutzer beeinflusst werden
* _FIX_: RegisterTimer Umstellung wieder verworfen (v1.2)

v1.2.20190320

* _FIX_: Umsetzung Store Richtlinie(6), RegisterTimer wieder verwendet

v1.1.20190312

* _NEU_: Vereinheitlichungen, StyleCI uvm.

v1.0.20181021

* _FIX_: Umstellung auf https

v1.0.20180831

* _NEU_: Landkreis Berchtesgadener Land
* _NEU_: Pullach im Isartal

v1.0.20180725

* _NEU_: Stadt Unterschleissheim

v1.0.20180628

* _NEU_: Landkreis Tirschenreuth
* _NEU_: Landkreis Rosenheim

v1.0.20180405

* _NEU_: Landkreis Kronach
* _NEU_: Landkreis Tübingen

v1.0.20170417

* _NEU_: Zweckverband München-Südost

## Entwickler

Seit nunmehr über 10 Jahren fasziniert mich das Thema Haussteuerung. In den letzten Jahren betätige ich mich auch intensiv in der IP-Symcon Community und steuere dort verschiedenste Skript und Module bei. Ihr findet mich dort unter dem Namen @pitti ;-)

[![GitHub](https://img.shields.io/badge/GitHub-@wilkware-181717.svg?style=for-the-badge&logo=github)](https://wilkware.github.io/)

## Spenden

Die Software ist für die nicht kommerzielle Nutzung kostenlos, über eine Spende bei Gefallen des Moduls würde ich mich freuen.

[![PayPal](https://img.shields.io/badge/PayPal-spenden-00457C.svg?style=for-the-badge&logo=paypal)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166)

## Lizenz

Namensnennung - Nicht-kommerziell - Weitergabe unter gleichen Bedingungen 4.0 International

[![Licence](https://img.shields.io/badge/License-CC_BY--NC--SA_4.0-EF9421.svg?style=for-the-badge&logo=creativecommons)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
