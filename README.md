[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-5.0%20%3E-blue.svg)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-1.3.20190323-orange.svg)](https://github.com/Wilkware/IPSymconAwido)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![StyleCI](https://github.styleci.io/repos/77854413/shield?style=flat)](https://github.styleci.io/repos/77854413)

# Awido - Abfallwirtschaft
IP-Symcon Modul für die Visualisierung von Entsorgungsterminen.

### Inhaltverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)
8. [Versionshistorie](#8-versionshistorie)

### 1. Funktionsumfang

Das Modul nutzt die von awido (www.awido-online.de) bereitgestellten Daten zur Berechnung
der bevorstehenden Entsorgungstermine (Abfallentsorgung).

Derzeit unterstützt das Modul folgende Gebiete:

* Lahn-Dill-Kreis
* Landkreis Altenkirchen
* Landkreis Ansbach
* Landkreis Bad Dürkheim
* Landkreis Bad Tölz-Wolfratshausen
* Landkreis Berchtesgadener Land
* Landkreis Coburg
* Landkreis Dillingen a.d. Donau und Donau-Ries
* Landkreis Erding
* Landkreis Günzburg
* Landkreis Hersfeld-Rotenburg
* Landkreis Kelheim
* Landkreis Kronach
* Landkreis Neuburg-Schrobenhausen
* Landkreis Rosenheim
* Landkreis Südliche Weinstraße
* Landkreis Tirschenreuth
* Landkreis Tübingen
* Landratsamt Dachau
* Landratsamt Aichach-Friedberg
* Neustadt a.d. Waldnaab
* Pullach im Isartal
* Rems-Murr-Kreis
* Stadt Memmingen
* Stadt Unterschleissheim
* Zweckverband München-Südost

Wenn jemand noch weitere kennt, bitte einfach bei mir melden!

### 2. Voraussetzungen

- IP-Symvon Version 5.0+

### 3. Software-Installation

Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/Wilkware/IPSymconAwido` oder `git://github.com/Wilkware/IPSymconAwido.git`

### 4. Einrichten der Instanzen in IP-Symcon

- Unter "Instanz hinzufügen" ist das 'Awido'-Modul (Alias: Abfallwirtschaft) unter dem Hersteller '(Sonstige)' aufgeführt.

__Konfigurationsseite__:

Die Konfiguration läuft über mehrere Schritte und bedingt pro Konfigurationsschritt(auswahl) ein 'Übernehmen' der Daten.
Bis man zum Schluss die Instanz über die Update-Checkbox aktiv setzt.
Eine Neuauswahl erreicht man durch Auswahl "Bitte wählen ..." an der gewüschten Stelle (immer 'Übernehmen' klicken).

VORSTICHT: eine Änderung der Auswahl bedingt ein Update bzw. ein Neuanlegen der Statusvariablen!!!
Alte Variablen, welche es im anderen Landkreis gab werden nicht gelöscht! Hat man diese in einem WF verlinkt muss man danach 
selber aufräumen. Ich denke aber mal das ein Umzug nicht so häufig vorkommt ;-)

Name               | Beschreibung
------------------ | ---------------------------------
clientID           | Gebiets-Id (siehe Liste oben)
placeGUID          | Ort im Entsorgungsgebiet
streetGUID         | Ortsteil/Strasse im gewählten Ort
addonGUID          | Hausnummer (Alle = gesamte Strasse)
fractionIDs        | Entsorgungs-Ids, d.h. was wird im Gebiet an Entsorgung angeboten
createVariables    | Status, ob für nicht genutzte Entsorgungen auch Variablen angelegt werden sollen, standardmäßig nein(false)
activateAWIDO      | Status, ob das tägliche Update aktiv oder inaktiv ist
ScriptID           | Script, welches nach dem Update der Termine ausgeführt wird, z.B. für Visualisierung, Sortierung usw.


### 5. Statusvariablen und Profile

Die Statusvariablen/Timer werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

Name                 | Typ       | Beschreibung
-------------------- | --------- | ----------------
UpdateTimer          | Timer     | Timmer zum täglichen Update der Entsorgungstermine
Entsorgungsart(1-10) | String    | Abhängig vom Entsorgungsgebiet und den angebotenem Service mehrere Variablen, z.B.: Restmüll, Biotonne usw.

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

### Entwickler
* Heiko Wilknitz ([@wilkware](https://github.com/wilkware))

### Spenden
Die Software ist für die nicht kommzerielle Nutzung kostenlos, Schenkungen als Unterstützung für den Entwickler bitte hier:<br />
<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166" target="_blank"><img src="https://www.paypalobjects.com/de_DE/DE/i/btn/btn_donate_LG.gif" border="0" /></a>

### Lizenz
[![Licence](https://licensebuttons.net/i/l/by-nc-sa/transparent/00/00/00/88x31-e.png)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
