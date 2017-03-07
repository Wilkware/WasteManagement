# Awido - Abfallwirtschaft

### Inhaltverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)

### 1. Funktionsumfang

Das Modul nutzt die von awido (www.awido-online.de) bereitgestellten Daten zur Berechnung
der bevorstehenden Entsorgungstermine (Abfallentsorgung).

* _NEU_: Landkreis Ansbach und Coburg

Derzeit unterstützt das Modul folgende Gebiete:

* Lahn-Dill-Kreis
* Landkreis Altenkirchen
* Landkreis Ansbach
* Landkreis Bad Dürkheim
* Landkreis Bad Tölz-Wolfratshausen
* Landkreis Coburg
* Landkreis Dillingen a.d. Donau und Donau-Ries
* Landkreis Erding
* Landkreis Günzburg
* Landkreis Hersfeld-Rotenburg
* Landkreis Kelheim
* Landkreis Neuburg-Schrobenhausen
* Landkreis Südliche Weinstraße
* Landratsamt Dachau
* Neustadt a.d. Waldnaab
* Rems-Murr-Kreis
* Stadt Memmingen

Wenn jemand noch weitere kennt, bitte einfach bei mir melden!

### 2. Voraussetzungen

- IP-Symcon ab Version 4.x (getestet mit Version 4.1.534 auf RP3)

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
Aber wie bei der Konfiguration beschrieben, muss man aufpassen wenn die Konfiguration geändert wird. Dann müssen die Links neu eingerichtet werden.


### 7. PHP-Befehlsreferenz

`void AWIDO_Update(int $InstanzID);`
Holt die nächsten anstehenden Entsorgungstermine für die gewählten Entsorgungsarten.
Die Funktion liefert keinerlei Rückgabewert.

Beispiel:
`AWIDO_Update(12345);`