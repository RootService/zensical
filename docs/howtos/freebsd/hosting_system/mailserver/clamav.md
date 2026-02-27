---
author:
  name: Markus Kohlmeyer
  url: https://github.com/JoeUser78
  email: joeuser@rootservice.org
publisher:
  name: RootService Team
  url: https://github.com/RootService
  email: team@rootservice.org
license:
  name: Attribution-NonCommercial-ShareAlike 4.0 International (CC BY-NC-SA 4.0)
  shortname: CC BY-NC-SA 4.0
  url: https://creativecommons.org/licenses/by-nc-sa/4.0/
contributors: []
date: '2010-08-25'
lastmod: '2026-02-27'
title: ClamAV
description: In diesem HowTo wird Schritt für Schritt die Installation von ClamAV für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# ClamAV

> **Stand:** 2026-02-27  
> **Terminologie:** Einheitlich werden die Begriffe **HowTo**, **HowTos**, **BaseSystem**, **BasePorts** und **BaseTools** verwendet.


## Einleitung

Unser Hosting System wird um folgende Dienste erweitert.

- ClamAV 1.5.1 (Milter)

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

## Installation

Wir installieren `security/clamav` und dessen Abhängigkeiten.

``` shell
mkdir -p /var/db/ports/security_clamav
cat <<'EOF' > /var/db/ports/security_clamav/options
--8<-- "freebsd/ports/security_clamav/options"
EOF


portmaster -w -B -g --force-config security/clamav  -n


sysrc clamav_clamd_enable="YES"
sysrc clamav_freshclam_enable="YES"
```

## Abschluss

ClamAV kann nun gestartet werden.

``` shell
service clamav_freshclam onestart

freshclam

service clamav_clamd start
```
