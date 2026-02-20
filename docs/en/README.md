# Unfolded Circle Remote 3

[![Version](https://img.shields.io/badge/Symcon-PHPModule-red.svg)](https://www.symcon.de/en/service/documentation/developer-area/sdk-tools/sdk-php/)
[![Version](https://img.shields.io/badge/Symcon%20Version-%3E%208.2-green.svg)](https://www.symcon.de/en/service/documentation/installation/)

Module for IP-Symcon to integrate the **Unfolded Circle Remote 3** and the **Remote Dock 3**.

This module enables bidirectional WebSocket communication with the Remote 3 platform, reception of real-time events and
execution of activities and commands from IP-Symcon.

---

## Documentation

**Table of Contents**

1. [Features](#1-features)
2. [Requirements](#2-requirements)
3. [Installation](#3-installation)
4. [Setup in IP-Symcon](#4-setup-in-ip-symcon)
5. [Instance Overview](#5-instance-overview)
6. [Annex](#6-annex)

---

## 1. Features

The Unfolded Circle Remote 3 is designed to control AV devices, activities and smart home functions.

This module provides:

- WebSocket connection to the Remote 3
- Reception of real-time events (activity changes, battery state, display state, user interactions, etc.)
- Triggering activities and system commands
- Integration into automations, scripts, Alexa or HomeKit
- Management of Remote 3 and Dock 3 instances
- Optional automatic device discovery via mDNS

---

## 2. Requirements

- IP-Symcon >= 8.2
- Unfolded Circle Remote 3
- Optional: Remote Dock 3
- Network access between IP-Symcon and the Remote device

---

## 3. Installation

### a. Loading the module

Open the IP-Symcon web console via:

```
http://{IP-Symcon IP}:3777/console/
```

Click the module store icon in the upper right corner.

![Store](img/store_icon.png?raw=true "open store")

In the search field enter:

```
Unfolded Circle Remote 3
```

![Store](img/module_store_search_en.png?raw=true "module search")

Select the module and click _Install_.

![Store](img/install_en.png?raw=true "install")

---

## 4. Setup in IP-Symcon

After installation, a **Discovery instance** is created automatically.

The discovery searches the local network for:

- Remote 3 devices
- Remote Dock 3 devices

If a device is found, you can create the corresponding instance via _Create_:

- **Remote 3 instance**
- **Remote Dock 3 instance**

Depending on the configuration, a WebSocket Client instance will be used or created automatically for communication.

Further configuration is done directly within the respective instance.

---

## 5. Instance Overview

### Remote 3 Core Manager

Central communication instance. Establishes the WebSocket connection to the Remote and distributes incoming events to
child instances.

### Remote 3 Device

Represents a physical Remote 3 device and processes device-specific events.

### Remote Dock 3

Represents a Dock 3 device.

### Remote Dock 3 Manager

Advanced management instance for dock-specific functionality.

### Remote 3 Integration Driver

Provides an integration endpoint (e.g. JSON-RPC) for external systems.

### Remote 3 Configurator

Supports setup and device management.

---

## 6. Annex

### GUID

The GUIDs of the individual instances are defined in the module source code and can be reviewed in the corresponding
`module.json` files.

---

*Note: This documentation is an initial framework and will be expanded during further development of the module.*