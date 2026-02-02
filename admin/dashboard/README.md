# OpenSimulator Statistik Dashboard

Dieses Projekt bietet ein modernes Statistik-Dashboard für OpenSimulator-Server auf Basis von PHP und MariaDB/MySQL. Die Oberfläche ist an das w3.css Analytics Template angelehnt und zeigt Live-Daten zu Regionen, Benutzern, Gruppen und weiteren Grid-Informationen.

## Features

- Übersicht aller Regionen mit Position, Größe und Serverdaten
- Anzeige aller Gruppen im Grid
- Online-Mitglieder mit Namen und Region
- GridUser mit Online-/Offline-Status und Detailinformationen
- MuteList (stummgeschaltete Nutzer)
- Benutzerinformationen (Avatar, Server-URL)
- Sortierbare Tabellen (Klick auf Spaltenkopf)
- Responsive Design für Desktop und Mobilgeräte
- Einfache Anpassung und Erweiterung

## Installation

1. **Voraussetzungen:**
   - Webserver mit PHP (>=7.4 empfohlen)
   - MariaDB/MySQL-Datenbank mit OpenSimulator-Schema
   - Zugangsdaten zur Datenbank
2. **Dateien kopieren:**
   - Das Verzeichnis `statistics` in das Webverzeichnis legen
3. **Konfiguration:**
   - In der Datei `ssinc.php` die Datenbank-Zugangsdaten eintragen
   - Optional: Anpassungen an SQL-Queries je nach Grid-Struktur
4. **Zugriff:**
   - Die Seite `index.php` im Browser aufrufen

## Anpassungen

- Tabellen und Layout können über w3.css und die PHP-Dateien individuell angepasst werden.
- Weitere Datenquellen können über zusätzliche SQL-Queries eingebunden werden.

## Sicherheitshinweise

- Die Zugangsdaten in `ssinc.php` sollten nicht öffentlich zugänglich sein.
- Die Anwendung ist für interne Grid-Statistiken gedacht und nicht für den produktiven Einsatz im Internet gehärtet.
