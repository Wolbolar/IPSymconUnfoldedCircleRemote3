Unfolded Circle Remote 3 for IP-Symcon
===

[![IP-Symcon Version](https://img.shields.io/badge/IP--Symcon-%3E%208.2-blue.svg)](https://www.symcon.de/)
[![Module Type](https://img.shields.io/badge/Type-IPSModuleStrict-success.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Status](https://img.shields.io/badge/Status-Beta-orange.svg)]()

## Overview

This module provides a native integration of the **Unfolded Circle Remote 3** ecosystem into IP-Symcon.

It enables:

- Bidirectional communication with the Remote 3 via WebSocket
- Reception of real-time events (activities, battery state, display state, remote events, etc.)
- Triggering activities and system actions from IP-Symcon
- Integration into automations, scripts, Alexa, HomeKit and custom dashboards
- Optional discovery of Remotes and Dock devices (mDNS)

The module is based on **IPSModuleStrict** and requires IP-Symcon 8.2 or newer.

---

## Architecture

The module consists of multiple instances:

- **Remote 3 Core Manager** – Central WebSocket communication layer
- **Remote 3 Device** – Represents the physical Remote
- **Remote 3 Dock** – Represents a Dock device
- **Remote Dock 3 Manager** – Advanced Dock management
- **Remote 3 Integration Driver** – JSON-RPC integration endpoint
- **Remote 3 Discovery** – mDNS based device discovery
- **Remote 3 Configurator** – Instance configuration helper
- **Remote 3 Media Player** – Media related device abstraction

All communication is handled through the IP-Symcon WebSocket Client.

---

## Requirements

- IP-Symcon >= 8.2
- Network access to the Remote 3 and/or Dock
- WebSocket Client instance in Symcon

---

## Documentation

- [Deutsche Dokumentation](docs/de/README.md)
- [English Documentation](docs/en/README.md)

---

## Development Status

This module is currently in **Beta** state.

The goal is to provide a stable and production-ready integration of the Remote 3 platform into IP-Symcon.

Feedback, bug reports and feature suggestions are welcome.

---

## License

See repository license file.
