# MyMüll.de - Entsorgungskalender

[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-6.0-blue.svg)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-1.0.20221020-orange.svg)](https://github.com/Wilkware/IPSymconAwido)
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

Das Modul nutzt die von Regio IT (www.regioit.de) bereitgestellten Daten zur Berechnung der bevorstehenden Entsorgungstermine (Abfallentsorgung).

Derzeit unterstützt das Modul rund 20 verschiedene Regionen. Wenn jemand noch weitere kennt, bitte einfach bei mir melden!

### 2. Voraussetzungen

* IP-Symcon ab Version 6.0

### 3. Installation

* Über den Modul Store das Modul Abfallwirtschaft (ehem. Awido) installieren.
* Alternativ Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/Wilkware/IPSymconAwido` oder `git://github.com/Wilkware/IPSymconAwido.git`

### 4. Einrichten der Instanzen in IP-Symcon

* Unter "Instanz hinzufügen" ist das _'AbfallNavi'_-Modul (Alias: _'Abfallwirtschaft (AbfallNavi)'_ oder _'Entsorgungskalender (AbfallNavi)'_)  unter dem Hersteller _'(Geräte)'_ aufgeführt.

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
Anbieter                | 'AbfallNavi (regioit.de)'

> Abfallwirtschaft ...

Name                    | Beschreibung
----------------------- | ---------------------------------
Entsorgungsgebiet       | Liste der verfügbaren Gebiete/Regionen
Stadt                   | Stadt im gewählten Gebiet
Straße                  | Strasse im gewählten Ort
Hausnummer              | Hausnummer (exakt/von-bis), oder Alle = gesamte Strasse
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
void REGIO_Update(int $InstanzID);
```

Holt die nächsten anstehenden Entsorgungstermine für die gewählten Entsorgungsarten.  
Die Funktion liefert keinerlei Rückgabewert.

__Beispiel__: `REGIO_Update(12345);`

### 8. Versionshistorie

v1.0.20221020

* _NEU_: Initialversion

## Danksagung

Ich möchte mich für die Unterstützung bei der Entwicklung dieses Moduls bedanken bei ...

* _dixi00_ : für den Hinweis auf die API und das Testen :-)

Vielen Dank!

## Entwickler

Seit nunmehr über 10 Jahren fasziniert mich das Thema Haussteuerung. In den letzten Jahren betätige ich mich auch intensiv in der IP-Symcon Community und steuere dort verschiedenste Skript und Module bei. Ihr findet mich dort unter dem Namen @pitti ;-)

[![GitHub](https://img.shields.io/badge/GitHub-@wilkware-181717.svg?style=for-the-badge&logo=github)](https://wilkware.github.io/)

## Spenden

Die Software ist für die nicht kommerzielle Nutzung kostenlos, über eine Spende bei Gefallen des Moduls würde ich mich freuen.

[![PayPal](https://img.shields.io/badge/PayPal-spenden-00457C.svg?style=for-the-badge&logo=paypal)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166)

## Lizenz

Namensnennung - Nicht-kommerziell - Weitergabe unter gleichen Bedingungen 4.0 International

[![Licence](https://img.shields.io/badge/License-CC_BY--NC--SA_4.0-EF9421.svg?style=for-the-badge&logo=creativecommons)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
