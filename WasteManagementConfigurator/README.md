# Abfallwirtschafts-Konfigurator (Waste Management Configutrator)

[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg?style=flat-square)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-6.4-blue.svg?style=flat-square)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-2.1.20240304-orange.svg?style=flat-square)](https://github.com/Wilkware/WasteManagement)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg?style=flat-square)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Actions](https://img.shields.io/github/actions/workflow/status/wilkware/WasteManagement/style.yml?branch=main&label=CheckStyle&style=flat-square)](https://github.com/Wilkware/WasteManagement/actions)

IP-Symcon Modul für die Verwaltung von Online Diensten zur Bestimmung von Entsorgungsterminen.

## Inhaltverzeichnis

1. [Funktionsumfang](#user-content-1-funktionsumfang)
2. [Voraussetzungen](#user-content-2-voraussetzungen)
3. [Installation](#user-content-3-installation)
4. [Einrichten der Instanzen in IP-Symcon](#user-content-4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#user-content-5-statusvariablen-und-profile)
6. [WebFront](#user-content-6-webfront)
7. [PHP-Befehlsreferenz](#user-content-7-php-befehlsreferenz)
8. [Versionshistorie](#user-content-8-versionshistorie)

### 1. Funktionsumfang

Mit Hilfe des Konfigurations-Moduls kann man schnell und einfach den zu nutzenden Online-Dienst für die Bereitstellung der Entsorgungstermine auswählen und die dazugehörigen Modul-Instanzen verwalten bzw. anlegen.

Derzeit unterstützt der Konfigurator folgende Anbieter:

* [AWIDO](https://awido-online.de) - "Die Web-Anwendung mit alle wichtigen Entsorgungstermine online!"
* [Abfall+](https://abfallplus.de) - "Die Gesamtlösung für elektronische Bürgerdienste in der Abfallwirtschaft!"
* [MyMüll.de](https://mymuell.de) - "Abfall und Wertstoffe sauber organisiert!"
* [AbfallNavi](https://regioit.de) - "Der digitale Abfallkalender der regio IT für die Abfallentsorgung!"
* [MyMuell](https://muellmax.de) - "Müllmax Abfallkalender barrierefrei online und gedruckt."
* [Abfall.ICS](https://asmium.de) - "Abfalldaten via Kalenderdatei ICS auslesen."

__HINWEIS:__ Über diese [Suchseite](https://asmium.de) kann man ganz schnell herausfinden ob die eigene Stadt/Gemeinde von einem der aufgelisteten Dienste unterstützt wird! :+1:

Wenn jemand noch weitere kennt, bitte einfach bei mir melden!

### 2. Voraussetzungen

* IP-Symcon ab Version 6.4

### 3. Installation

* Über den Modul Store das Modul Abfallwirtschaft (ehem. Awido) installieren.
* Alternativ Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/Wilkware/WasteManagement` oder `git://github.com/Wilkware/WasteManagement.git`

### 4. Einrichten der Instanzen in IP-Symcon

* Unter "Instanz hinzufügen" ist das _'Waste Management Configurator'_-Modul (Alias: _'Abfallwirtschaft Konfigurator'_) unter dem Hersteller '(Konfigurator)' aufgeführt.

__Konfigurationsseite__:

Innerhalb der Konfiguratorliste wird anhand des verfügbaren Dienstes gruppiert.
Man kann pro Dienst mehrere Instanzen anlegen und auch wieder löschen.
Legt man eine entsprechende Zielkategorie fest, werden neu zu erstellende Instanzen unterhalb dieser Kategorie angelegt.

_Einstellungsbereich:_

Name                    | Beschreibung
----------------------- | ---------------------------------
Zielkategorie           | Kategorie unter welcher neue Instanzen erzeugt werden (keine Auswahl im Root). Nur bis Version 7!

_Aktionsbereich:_

Name                    | Beschreibung
----------------------- | ---------------------------------
Anbieter                | Konfigurationsliste zum Verwalten der entsprechenden Instanzen

### 5. Statusvariablen und Profile

Es werden keine zusätzlichen Variablen oder Profile benötigt.

### 6. WebFront

Es ist keine weitere Steuerung oder gesonderte Darstellung integriert.

### 7. PHP-Befehlsreferenz

Das Modul bietet keine direkten Funktionsaufrufe.

### 8. Versionshistorie

v2.1.20240304

* _NEU_: Generischen Abfallkalender via iCal-Detei (ICS) hinzugefügt
* _FIX_: Übersetzungen nachgezogen
* _FIX_: Einige interne Vereinheitlichungen und Anpassungen

v2.0.20231119

* _NEU_: Onlinedienst MuellMax (muellmax.de) hinzugefügt
* _NEU_: Unterstützung für neuen Konfigurator der Version 7

v1.3.20220309

* _NEU_: Konfigurationsformular angepasst
* _NEU_: Onlinedienst AbfallNavi (regioit.de) hinzugefügt

v1.2.20211109

* _NEU_: Kompatibilität auf IPS 6.0 hoch gesetzt
* _NEU_: Konfigurationsformular an die neuen Möglichkeiten der 6.0 angepasst

v1.1.20211109

* _NEU_: Onlinedienst MyMüll.de (www.mymuell.de) hinzugefügt

v1.0.20210404

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
