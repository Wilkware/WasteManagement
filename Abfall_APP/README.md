# ♻️ Abfall.APP

[![Home](https://img.shields.io/badge/Home-wilkware.de-0b1830.svg?style=flat-square)](https://wilkware.de/module/abfallwirtschaft/apapp/)
[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg?style=flat-square)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-8.1-blue.svg?style=flat-square)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-5.3.20261030-orange.svg?style=flat-square)](https://github.com/Wilkware/WasteManagement)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg?style=flat-square)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Actions](https://img.shields.io/github/actions/workflow/status/wilkware/WasteManagement/ci.yml?branch=main&label=CI&style=flat-square)](https://github.com/Wilkware/WasteManagement/actions)

Symcon Modul für die Visualisierung von Entsorgungsterminen.

## Inhaltsverzeichnis

1. [Funktionsumfang](#user-content-1-funktionsumfang)
2. [Voraussetzungen](#user-content-2-voraussetzungen)
3. [Installation](#user-content-3-installation)
4. [Einrichtung](#user-content-4-einrichtung)
5. [Statusvariablen](#user-content-5-statusvariablen)
6. [Darstellungen](#user-content-6-darstellungen)
7. [Visualisierung](#user-content-7-visualisierung)
8. [Befehlsreferenz](#user-content-8-befehlsreferenz)
9. [Versionshistorie](#user-content-9-versionshistorie)

### 1. Funktionsumfang

Das Modul nutzt die für die Abfall+ Apps (app.abfallplus.de) bereitgestellten Daten zur Berechnung der bevorstehenden Entsorgungstermine (Abfallentsorgung).

Derzeit unterstützt das Modul über 90 verschiedene Landkreise und Großstädte. Wenn jemand noch weitere kennt, bitte einfach bei mir melden!

### 2. Voraussetzungen

* Symcon ab Version 8.1

### 3. Installation

* Über den Modul Store die Bibliothek _Abfallwirtschaft_ installieren.
* Alternativ über das Modul Control folgende URL hinzufügen.  
`https://github.com/Wilkware/WasteManagement` oder `git://github.com/Wilkware/WasteManagement.git`

### 4. Einrichtung

* Unter 'Instanz hinzufügen' ist das _Abfall_APP_-Modul (Alias: _Abfallwirtschaft (Abfall.APP)_, _Entsorgungskalender (Abfall.APP)_) unter dem Hersteller '(Geräte)' aufgeführt.

__Konfigurationsseite__:

Entsprechend der gewählten Auswahl verändert sich das Formular dynamisch.
Eine komplette Neuauswahl erreicht man durch eine Landesauswahl oder durch die 
Auswahl "Bitte wählen ..." des Entsorgungsgebietes.  

VORSICHT: Aufgrund gewisser Umstände beim Abruf der Konfigurationsdaten bestehen folgende Einschränkungen:
* Änderungen an Teilen der Konfiguration bedingt ein komplette Neuauswahl der Daten!
* Änderung die verworfen werden ("Änderungen verwerfen") führt zu einem undefinierten Zustand!!

WICHTIG: eine Änderung der Auswahl bedingt ein Update bzw. ein Neuanlegen der Statusvariablen!!!
Alte Variablen, welche es im anderen Landkreis gab werden nicht gelöscht! Hat man diese in einem WF verlinkt muss man danach entsprechend aufräumen bzw. anpassen. Ich denke aber mal das ein Umzug nicht so häufig vorkommt ;-)

__HINWEIS:__ Jede Aktualisierung meldet sich wie die offizielle App mit einer neuen App-Kennung (Client-ID) beim Dienst an.
Einzelne Apps werden vom Dienst derzeit trotzdem abgelehnt (z.B. _AWB Emsland_, Status "Abfrage beim Dienst fehlgeschlagen").
In diesem Fall bitte auf das Modul _Abfall_ICS_ ausweichen, sofern der Entsorger einen Kalender (ICS) anbietet.

Einstellungsbereich:

> 🔗 Online Dienst ...

Name                    | Beschreibung
----------------------- | ----------------------------------
Anbieter                | 'Abfall.APP (abfallplus.de)'
Land                    | Landesauswahl (derzeit DE, AT)

> ♻️ Abfallwirtschaft ...

Name                    | Beschreibung
----------------------- | ---------------------------------
Entsorgungsgebiet       | Liste der verfügbaren Gebiete (siehe oben)
Bundesland              | Auswahl Bundesland (vereinzelt)
Landkreis               | Auswahl Landkreis (vereinzelt)
Stadt/Gemeinde          | Kommune im Entsorgungsgebiet
Bezirk/Ortsteil         | In einigen Gegend zusätzliche Gebietseinschränkung
Straße                  | Strasse im gewählten Ort
Hausnummer              | Hausnummer einzeln, von-bis, oder alle
Entsorgungen            | Entsorgungsarten, d.h. was wird im Gebiet an Entsorgung angeboten

> ✨ Visualisierung ...

Name                                                    | Beschreibung
------------------------------------------------------- | ---------------------------------
Unterstützung für Tile Visu aktivieren?                 | Aktivierung der Darstellung für Kachel-Visualisierung
Abfallgruppen                                           | Farbliche Zuordnung der Abfallarten
Akzentfarbe (Heute)                                     | Highlighting (Kachel-Hintergrund) der heutigen Abholung
Akzentfarbe (Morgen)                                    | Highlighting (Kachel-Hintergrund) der morgigen Abholung
Farbtransparenz (Kleine Kachel)                         | Transparenz (100% Vollfarbe) für Tonnen- bzw. Akzentfarbe(n) 
Die Farbe der Tonne als Hintergrund verwenden (Kleine Kachel)? | Übersteuert gewählte Akzentfarbe für Abholung "HEUTE"!
Größte Ansicht (größere Kacheln)                        | Begrenzt die Darstellung bei größeren Kacheln: nur Symbole, kompakte Liste oder große Ansicht (Standard, automatisch nach Kachelgröße)
Unterstützung für das alte Webfrontend (HTMLBox) aktivieren?   | Weiterhin die Visualisierung über HtmlBox unterstützen!
Vorrausschauende Anzeige für Folgetage aktivieren?      | Aktivierung, ob zu einer bestimmten Zeit die Anzeige umschalten soll auf Folgetermine
Zeitpunkt                                               | Uhrzeit, wo die Umschaltung erfolgen soll

> ⚙️ Erweiterte Einstellungen ...

Name                                                    | Beschreibung
------------------------------------------------------- | ---------------------------------
Tägliche Aktualisierung aktivieren?                     | Status, ob das tägliche Update aktiv oder inaktiv ist
Variablen für nicht ausgewählte Entsorgungen erstellen? | Status, ob für nicht genutzte Entsorgungen auch Variablen angelegt werden sollen, standardmäßig nein
Skript                                                  | Skript, welches nach dem Update der Termine ausgeführt wird, z.B. für Sortierung usw.

_HINWEIS:_ Dem Skript werden die Variablen __'TIMESTAMP'__ und __'DATA'__ mitgegeben. DATA beinhaltet JSON kodiert das Array der ausgewählten Abfuhrtermine. Der Feldindex(key) ist der interne Verarbeitungsname der Müllart. Über den Ident kann der Variablenname klar zugeordnet werden.

__Beispiel:__
```json
{
  "Blaue Tonne": {
    "ident": "BT",
    "date": "29.06.2026"
  },
  "Biotonne": {
    "ident": "BIO",
    "date": "24.06.2026"
  },
  "Restabfalltonne": {
    "ident": "RM",
    "date": "30.06.2026"
  }
}
```

Aktionsbereich:

> 🚀 Aktion ausführen ...

Aktion                  | Beschreibung
----------------------- | ---------------------------------
AKTUALISIEREN           | Werte werden neu ermittelt und geschrieben
VORAUSSCHAU             | Schaltet die Anzeige in der Kachel-Visualisierung auf die Folgetermine um (nur bei aktivierter Tile Visu)

### 5. Statusvariablen

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

Ident                | Name                 | Typ     | Beschreibung
-------------------- | -------------------- | ------- | ------------------------------
_(dynamisch)_        | Entsorgungsart(en)   | String  | Abhängig vom Entsorgungsgebiet und dem angebotenen Service mehrere Variablen, z.B.: Restmüll, Biotonne usw. Der Ident entspricht der Kennung der Entsorgungsart beim Dienst.
Widget               | Abholung             | String  | HTML-Darstellung der Entsorgungstermine für das alte Webfrontend (nur bei aktivierter Unterstützung für Tile Visu und HTMLBox)

### 6. Darstellungen

Die Darstellungen werden direkt an den Statusvariablen hinterlegt, es werden keine Profile angelegt.

Variable             | Darstellung   | Werte
-------------------- | ------------- | ------------------------------
Entsorgungsart(en)   | Wertanzeige   | Datum der nächsten Abholung (TT.MM.JJJJ), Icon Mülltonne
Abholung             | Webinhalt     | HTML-Darstellung der Entsorgungstermine

### 7. Visualisierung

Man kann sowohl das gesamte Modul (HTML-SDK Support) als auch nur die Statusvariablen direkt in der Visualisierung verlinken.  
Aber wie bei der Konfiguration beschrieben, muss man aufpassen wenn die Konfiguration geändert wird. Dann müssen gegebenenfalls die Links neu eingerichtet werden.

#### Hintergrundfarben-Logik (Kleine Kachel):

Tonnenfarbe nutzen | Akzentfarben definiert | Ergebnis
------|------|------------------------------------------------------------------
 Ja   | Nein | Heute und Morgen erhält die Tonnenfarbe, danach keine Hintergrundfarbe
 Ja   | Ja   | Heute und Morgen erhält die Tonnenfarbe, danach keine Hintergrundfarbe
 Nein | Nein | Keine individuellen Farben → transparent → Akzentfarbe des Themes für heute/morgen
 Nein | Ja   | Individuelle Akzentfarben werden für heute/morgen verwenden

#### Bedienung der Kachel:

* __Ansichten:__ Je nach Kachelgröße werden die Termine als Tonnen-Symbole (kleine Kachel), als kompakte Liste oder als große Ansicht mit Banner und Liste dargestellt. Über die Einstellung "Größte Ansicht (größere Kacheln)" kann man die Darstellung begrenzen, z.B. auch bei großen Kacheln nur die Tonnen-Symbole anzeigen.
* __"Heute" antippen:__ Ist heute eine Abholung, erscheint unter den Tonnen-Symbolen die Schaltfläche "Heute". Antippen bzw. Anklicken schaltet die Anzeige sofort auf die nächste(n) Abholung(en) um, genau wie die automatische Vorausschau zur eingestellten Uhrzeit. Ein kurzes Aufhellen der Symbole bestätigt das Antippen. Nach der Umschaltung zeigt die Kachel wieder die Tage bis zur nächsten Abholung an.
* __Abfallarten anzeigen:__ Mit der Maus über den Tonnen-Symbolen (Desktop) bzw. durch Gedrückthalten (Touch) werden die Namen der Abfallarten eingeblendet. Das ist hilfreich, wenn mehrere Tonnen am gleichen Tag gestapelt dargestellt werden.

### 8. Befehlsreferenz

```php
void APAPP_LookAhead(int $InstanzID);
```

Stellt in der Visualisierung den für Folgetage anstehenden Entsorgungstermine dar.  
Die Funktion liefert keinerlei Rückgabewert.

__Beispiel__: `APAPP_LookAhead(12345);`

```php
void APAPP_Update(int $InstanzID);
```

Holt die nächsten anstehenden Entsorgungstermine für die gewählten Entsorgungsarten.  
Die Funktion liefert keinerlei Rückgabewert.

__Beispiel__: `APAPP_Update(12345);`

### 9. Versionshistorie

v5.3.20261030

* _FIX_: Anmeldung beim Dienst an die geänderte Schnittstelle angepasst (401 UNAUTHORIZED)
* _NEU_: Einstellung "Größte Ansicht" für größere Kacheln (z.B. auch große Kacheln nur mit Tonnen-Symbolen)
* _NEU_: Schaltfläche "Vorausschau" im Aktionsbereich
* _NEU_: Bei einem Fehler im Datenabruf erneuter Versuch nach einer Stunde und Status "Abfrage beim Dienst fehlgeschlagen"
* _NEU_: Timeouts für alle Abfragen beim Dienst
* _NEU_: Darstellungen (Wertanzeige, Webinhalt) statt Profile für die Statusvariablen
* _FIX_: Übersetzung der Wochentage und von "Heute"/"Morgen" in der Kachel
* _FIX_: Fehlende Übersetzungen ergänzt
* _FIX_: Interne Vereinheitlichungen (Formularzugriff, Aktionen ohne eval, Fehlerbehandlung)
* _FIX_: Dokumentation überarbeitet und vereinheitlicht

v5.2.20260902

* _FIX_: Ampelfarben im HTML Widget gefixt
* _NEU_: Neuer Visualisierungstyp für TileVisu eingeführt (Maximierung behält Darstellung)

v5.1.20260831

* _NEU_: Automatischer Cache Update für TileVisu (Restart Symcon)
* _FIX_: Support neuer Top-Level-Doamins

v5.0.20260710

* _NEU_: Support für TileVisu (Kachel-Visualisierung)
* _NEU_: Kompatibilität auf Symcon 8.1 vereinheitlicht
* _NEU_: Umstellung auf Strict-Modus (IPSModuleStrict)
* _NEU_: Modulversion wird in Quellcodesektion angezeigt
* _FIX_: Modulkonfiguration überarbeitet und vereinheitlicht
* _FIX_: Interne Bibliotheken und Konfiguration überarbeitet und vereinheitlicht

v4.5.20260622

* _NEU_: Datenübergabe an Skript
* _NEU_: Letzte Version für Support ab Symcon v6.4
* _FIX_: Form Mapping korrigiert
* _FIX_: Look-Ahead Fehler korrigiert

v1.1.20250528

* _NEU_: Strassensuche integriert
* _FIX_: Reihenfolge des Einrichtungsassistenten einbezogen (Skip actions)

v1.0.20250527

* _NEU_: Initialversion

## Entwickler

Seit nunmehr über 10 Jahren fasziniert mich das Thema Haussteuerung. In den letzten Jahren betätige ich mich auch intensiv in der Symcon Community und steuere dort verschiedenste Skript und Module bei. Ihr findet mich dort unter dem Namen @pitti ;-)

[![GitHub](https://img.shields.io/badge/GitHub-@wilkware-181717.svg?style=for-the-badge&logo=github)](https://wilkware.github.io/)

## Spenden

Die Software ist für die nicht kommerzielle Nutzung kostenlos, über eine Spende bei Gefallen des Moduls würde ich mich freuen.

[![PayPal](https://img.shields.io/badge/PayPal-spenden-00457C.svg?style=for-the-badge&logo=paypal)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166)

## Lizenz

Namensnennung - Nicht-kommerziell - Weitergabe unter gleichen Bedingungen 4.0 International

[![Licence](https://img.shields.io/badge/License-CC_BY--NC--SA_4.0-EF9421.svg?style=for-the-badge&logo=creativecommons)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
