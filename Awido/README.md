# Awido - Abfallwirtschaft

[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-5.2-blue.svg)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-3.0.20210405-orange.svg)](https://github.com/Wilkware/IPSymconAwido)
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

Das Modul nutzt die von awido (www.awido-online.de) bereitgestellten Daten zur Berechnung
der bevorstehenden Entsorgungstermine (Abfallentsorgung).

Derzeit unterstützt das Modul folgende Gebiete:

* Gemeinde Unterhaching
* Lahn-Dill-Kreis
* Landkreis Augsburg
* Landkreis Aichach-Friedberg
* Landkreis Altenkirchen
* Landkreis Ansbach
* Landkreis Bad Dürkheim
* Landkreis Bad Tölz-Wolfratshausen
* Landkreis Berchtesgadener Land
* Landkreis Coburg
* Landkreis Dillingen a.d. Donau und Donau-Ries
* Landkreis Erding
* Landkreis Fürstenfeldbruck
* Landkreis Gießen
* Landkreis Gotha
* Landkreis Günzburg
* Landkreis Hersfeld-Rotenburg
* Landkreis Kelheim
* Landkreis Kronach
* Landkreis Neuburg-Schrobenhausen
* Landkreis Mühldorf am Inn
* Landkreis Rosenheim
* Landkreis Schweinfurt
* Landkreis Südliche Weinstraße
* Landkreis Tirschenreuth
* Landkreis Tübingen
* Landratsamt Dachau
* Neustadt a.d. Waldnaab
* Pullach im Isartal
* Rems-Murr-Kreis
* Stadt Kaufbeuren
* Stadt Memmingen
* Stadt Unterschleissheim
* Zweckverband Isar-Inn
* Zweckverband München-Südost
* Zweckverband Saale-Orla

Wenn jemand noch weitere kennt, bitte einfach bei mir melden!

### 2. Voraussetzungen

* IP-Symvon ab Version 5.2

### 3. Installation

* Über den Modul Store das Modul Abfallwirtschaft (ehem. Awido) installieren.
* Alternativ Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/Wilkware/IPSymconAwido` oder `git://github.com/Wilkware/IPSymconAwido.git`

### 4. Einrichten der Instanzen in IP-Symcon

* Unter "Instanz hinzufügen" ist das _'Awido'_-Modul (Alias: _'Abfallwirtschaft (Awido)'_ oder _'Entsorgungskalender (Awido)'_)  unter dem Hersteller _'(Geräte)'_ aufgeführt.

__Konfigurationsseite__:

Entsprechend der gewählten Auswahl verändert sich das Formular dynamisch.
Eine komplette Neuauswahl erreicht man durch Auswahl "Bitte wählen ..." an der gewüschten Stelle.

VORSTICHT: eine Änderung der Auswahl bedingt ein Update bzw. ein Neuanlegen der Statusvariablen!!!
Alte Variablen, welche es im anderen Landkreis gab werden nicht gelöscht! Hat man diese in einem WF verlinkt muss man danach
selber aufräumen. Ich denke aber mal das ein Umzug nicht so häufig vorkommt ;-)

_Einstellungsbereich:_

> Online Dienste ...

Name                    | Beschreibung
----------------------- | ---------------------------------
Anbieter                | 'AWIDO (awido-online.de)'

> Abfallwirtschaft ...

Name                    | Beschreibung
----------------------- | ---------------------------------
Entsorgungsgebiet       | Liste der verfügbaren Gebiete (siehe oben)
Stadt/Gemeinde          | Ort im Entsorgungsgebiet (kann identisch zum Gebiet sein)
Ortsteil/Strasse        | Ortsteil/Strasse im gewählten Ort
Hausnummer              | Hausnummer von-bis, oder Alle = gesamte Strasse
Entsorgungen            | Entsorgungsarten, d.h. was wird im Gebiet an Entsorgung angeboten

> Erweiterte Einstellungen ...

Name                                                    | Beschreibung
------------------------------------------------------- | ---------------------------------
Tägliche Aktualisierung aktivieren?                     | Status, ob das tägliche Update aktiv oder inaktiv ist
Variablen für nicht ausgewählte Entsorgungen erstellen? | Status, ob für nicht genutzte Entsorgungen auch Variablen angelegt werden sollen, standardmäßig nein
Skript                                                  | Script, welches nach dem Update der Termine ausgeführt wird, z.B. für Sortierung usw.

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

Man kann die Statusvariaben(Strings) direkt im WF verlinken.  
Aber wie bei der Konfiguration beschrieben, muss man aufpassen wenn die Konfiguration geändert wird. Dann müssen gegebenenfalls die Links neu eingerichtet werden.

### 7. PHP-Befehlsreferenz

`void AWIDO_Update(int $InstanzID);`
Holt die nächsten anstehenden Entsorgungstermine für die gewählten Entsorgungsarten.  
Die Funktion liefert keinerlei Rückgabewert.

Beispiel:
`AWIDO_Update(12345);`

### 8. Versionshistorie

v3.0.20210405

* _NEU_: Eigener Webservice (JSON-API) für Bereitstellung der unterstützten Gebiete (aktuell 36 Gebiete)
* _NEU_: Landkreis Augsburg (lose Zuordnungung von verschiedenen Orten)
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

* Heiko Wilknitz ([@wilkware](https://github.com/wilkware))

## Spenden

Die Software ist für die nicht kommzerielle Nutzung kostenlos, Schenkungen als Unterstützung für den Entwickler bitte hier:

[![License](https://img.shields.io/badge/Einfach%20spenden%20mit-PayPal-blue.svg)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166)

## Lizenz

[![Licence](https://licensebuttons.net/i/l/by-nc-sa/transparent/00/00/00/88x31-e.png)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
