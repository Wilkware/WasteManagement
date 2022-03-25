# Abfall.IO - Abfallwirtschaft

[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-6.0-blue.svg)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-1.3.20211228-orange.svg)](https://github.com/Wilkware/IPSymconAwido)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Actions](https://github.com/Wilkware/IPSymconAwido/workflows/Check%20Style/badge.svg)](https://github.com/Wilkware/IPSymconAwido/actions)

IP-Symcon Modul für die Visualisierung von Entsorgungsterminen.

## Inhaltverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)
8. [Versionshistorie](#8-versionshistorie)

### 1. Funktionsumfang

Das Modul nutzt die von Abfall+ (www.abfallplus.de) bereitgestellten Daten zur Berechnung
der bevorstehenden Entsorgungstermine (Abfallentsorgung).

Derzeit unterstützt das Modul folgende Gebiete:

Entsorgungsgebiet                  | Entsorgungsgebiet             | Entsorgungsgebiet
-----------------------------------|-------------------------------|----------------------
 Hohenlohekreis                    | Freiburg im Breisgau          | Landkreis Bad Kissingen
 Landkreis Bautzen                 | Landkreis Bayreuth            | Landkreis Breisgau-Hochschwarzwald
 Landkreis Böblingen               | Landkreis Calw                | Landkreis Cloppenburg
 Landkreis Cuxhaven                | Landkreis Freudenstadt        | Landkreis Göttingen
 Landkreis Göppingen               | Landkreis Kitzingen           | Landkreis Landsberg am Lech
 Landkreis Landshut                | Landkreis Leipzig             | Landkreis Lindau (Bodensee)
 Landkreis Mayen-Koblenz           | Landkreis Miesbach            | Landkreis Nordsachsen
 Landkreis Oberallgäu              | Landkreis Ostallgäu           | Landkreis Osterholz
 Landkreis Prignitz                | Landkreis Rastatt             | Landkreis Reutlingen
 Landkreis Rotenburg (Wümme)       | Landkreis Rottweil            | Landkreis Sigmaringen
 Landkreis Steinfurt               | Landkreis Traunstein          | Landkreis Tuttlingen
 Landkreis Unterallgäu             | Landkreis Vorpommern-Rügen    | Landkreis Waldshut
 Landkreis Weißenburg-Gunzenhausen | Landkreis Würzburg            | Ortenaukreis
 Rhein-Neckar-Kreis                | Stadt Bad Kissingen           | Stadt Essen
 Stadt Duisburg                    | Stadt Frankfurt(Oder)         | Stadt Hagen
 Stadt Kempten (Allgäu)            | Stadt Landshut                | Stadt Ludwigshafen
 Stadt Mannheim                    | Stadt Metzingen               | Stadt Offenbach
 Schoenmackers                     | Schwarzwald-Baar-Kreis        | Westerwaldkreis

Wenn jemand noch weitere kennt, bitte einfach bei mir melden!

### 2. Voraussetzungen

* IP-Symcon ab Version 6.0

### 3. Installation

* Über den Modul Store das Modul Abfallwirtschaft (ehem. Awido) installieren.
* Alternativ Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/Wilkware/IPSymconAwido` oder `git://github.com/Wilkware/IPSymconAwido.git`

### 4. Einrichten der Instanzen in IP-Symcon

* Unter "Instanz hinzufügen" ist das _'Abfall_IO'_-Modul (Alias: _'Abfallwirtschaft (Abfall_IO)'_ oder _'Entsorgungskalender (Abfall_IO)'_)  unter dem Hersteller _'(Geräte)'_ aufgeführt.

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
Anbieter                | 'Abfall.IO (abfallplus.de)'

> Abfallwirtschaft ...

Name                    | Beschreibung
----------------------- | ---------------------------------
Entsorgungsgebiet       | Liste der verfügbaren Gebiete (siehe oben)
Stadt/Gemeinde          | Ort im Entsorgungsgebiet (kann identisch zum Gebiet sein)
Stadt-/Ortsteil         | In einigen Gegend zusätzliche Gebietseinschränkung
Straße/Abfuhrbezirk     | Strasse bzw. Abfuhrbezirk im gewählten Ort
Hausnummer              | Hausnummer von-bis, oder Alle = gesamte Strasse
Entsorgungen            | Entsorgungsarten, d.h. was wird im Gebiet an Entsorgung angeboten

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

### 6. WebFront

Man kann die Statusvariablen(Strings) direkt im WF verlinken.  
Aber wie bei der Konfiguration beschrieben, muss man aufpassen wenn die Konfiguration geändert wird. Dann müssen gegebenenfalls die Links neu eingerichtet werden.

### 7. PHP-Befehlsreferenz

```php
void ABPIO_Update(int $InstanzID);
```

Holt die nächsten anstehenden Entsorgungstermine für die gewählten Entsorgungsarten.  
Die Funktion liefert keinerlei Rückgabewert.

__Beispiel__: `ABPIO_Update(12345);`

```php
void ABPIO_FixWasteName(int $InstanzID, string $from, string $to);
```

Ändert den in der Konfiguration definierten Namen für eine Abfallart. Die Änderung ist nicht persitent und muss nach Konfigurationsänderungen neu ausgeführt werden.
Die Funktion liefert keinerlei Rückgabewert.

__Beispiel__: `ABPIO_FixWasteName(12345, 'Hausmüll', 'Hausmüll (2 wöchentlich)');`

### 8. Versionshistorie

v1.3.20211228

* _NEU_: Kompatibilität auf IPS 6.0 hoch gesetzt
* _NEU_: Konfigurationsformular an die neuen Möglichkeiten der 6.0 angepasst
* _NEU_: Funktion 'FixWasteName' zur Korrektur von Dateninkonsistenzen eines Anbieters
* _NEU_: Erweiterte Einstellung zur Auswahl des Formates bei der Datenabholung (ICS oder CSV)
* _NEU_: Schalter zum automatischen Match der Namen von Abfallarten (experimentell)
* _FIX_: Daten werden jetzt auch über die Jahresgrenze hinaus aktualisiert

v1.2.20210620

* _NEU_: Umstellung auf maximal 30 vewrschiedene Abfallarten
* _NEU_: Bei Änderung des Standortes werden alle Abfallarten deaktiviert
* _FIX_: IPS_SetProperty nicht mehr notwendig
* _FIX_: Status wird jetzt bei nicht aktivierter Aktualisierung auf 'Inaktiv' gesetzt
* _FIX_: Unter Umständen konnte die Erzeugung der Statusvariablen fehlschlagen

v1.1.20210423

* _FIX_: HotFix für doppelte Abfallarten (fehlerhafte Datenlieferung)

v1.0.20210406

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
