# Unfolded Circle Remote 3

[![Version](https://img.shields.io/badge/Symcon-PHPModul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Symcon%20Version-%3E%208.2-green.svg)](https://www.symcon.de/service/dokumentation/installation/)

Modul für IP-Symcon zur Integration der **Unfolded Circle Remote 3** sowie des **Remote Dock 3**.

Das Modul ermöglicht die bidirektionale Kommunikation mit der Remote 3 über WebSocket, das Empfangen von Echtzeit-Events
sowie das Auslösen von Aktivitäten und Befehlen aus IP-Symcon.

---

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)  
2. [Voraussetzungen](#2-voraussetzungen)  
3. [Installation](#3-installation)  
4. [Einrichtung in IP-Symcon](#4-einrichtung-in-ip-symcon)
5. [Instanzenübersicht](#5-instanzenübersicht)
6. [Anhang](#6-anhang)  

---

## 1. Funktionsumfang

Mit der Unfolded Circle Remote 3 ist es möglich AV-Geräte, Aktivitäten und Smart-Home-Funktionen zentral zu steuern.

Dieses Modul ermöglicht:

- Aufbau einer WebSocket-Verbindung zur Remote 3
- Empfang von Echtzeit-Events (z. B. Aktivitätenwechsel, Batteriestatus, Displaystatus, Benutzereingaben)
- Auslösen von Aktivitäten und Systembefehlen
- Integration in Automationen, Skripte, Alexa oder HomeKit
- Verwaltung von Remote 3 und Dock 3 Instanzen
- Optionale automatische Geräteerkennung über Discovery (mDNS)

---

## 2. Voraussetzungen

- IP-Symcon >= 8.2
- Unfolded Circle Remote 3
- Optional: Remote Dock 3
- Netzwerkzugriff zwischen IP-Symcon und Remote 3

---

## 3. Installation

### a. Laden des Moduls

Die Webconsole von IP-Symcon mit _http://{IP-Symcon IP}:3777/console/_ öffnen.

Anschließend oben rechts auf das Symbol für den Modulstore klicken.

![Store](img/store_icon.png?raw=true "open store")

Im Suchfeld nun

```
Unfolded Circle Remote 3
```

eingeben.

![Store](img/module_store_search.png?raw=true "module search")

Das Modul auswählen und auf _Installieren_ klicken.

![Store](img/install.png?raw=true "install")

---

## 4. Einrichtung in IP-Symcon

Nach der Installation wird automatisch eine **Discovery-Instanz** erstellt.

Diese sucht im lokalen Netzwerk nach:

- Remote 3 Geräten
- Remote Dock 3 Geräten

Wird ein Gerät gefunden, kann über _Erstellen_ eine entsprechende Instanz angelegt werden:

- **Remote 3 Instanz**
- **Remote Dock 3 Instanz**

Je nach Konfiguration wird eine WebSocket Client Instanz zur Kommunikation verwendet oder automatisch erstellt.

Weitere Einstellungen erfolgen direkt in der jeweiligen Instanz.

---

## 5. Instanzenübersicht

### Remote 3 Core Manager

Zentrale Kommunikationsinstanz. Baut die WebSocket-Verbindung zur Remote auf und verteilt eingehende Events an
untergeordnete Instanzen.

### Remote 3 Device

Repräsentiert eine physische Remote 3 und verarbeitet gerätespezifische Events.

### Remote Dock 3

Repräsentiert ein Dock 3 Gerät.

### Remote Dock 3 Manager

Erweiterte Verwaltungsinstanz für Dock-spezifische Funktionen.

### Remote 3 Integration Driver

Ermöglicht die Anbindung externer Systeme über JSON-RPC.

### Remote 3 Configurator

Unterstützt bei der Einrichtung und Verwaltung von Geräten.

---

## 6. Anhang

### GUID

Die GUIDs der einzelnen Instanzen sind im Modulcode definiert und können bei Bedarf in der module.json eingesehen
werden.

---

*Hinweis: Diese Dokumentation stellt ein Grundgerüst dar und wird im Rahmen der Weiterentwicklung des Moduls erweitert.*