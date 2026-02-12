---
author:
  name: Markus Kohlmeyer
  url: https://github.com/JoeUser78
  email: joeuser@rootservice.org
publisher:
  name: RootService Team
  url: https://github.com/RootService
license:
  name: Attribution-NonCommercial-ShareAlike 4.0 International (CC BY-NC-SA 4.0)
  shortname: CC BY-NC-SA 4.0
  url: https://creativecommons.org/licenses/by-nc-sa/4.0/
contributers: []
date: '2010-08-25'
lastmod: '2025-06-28'
title: ClamAV
description: In diesem HowTo wird step-by-step die Installation von ClamAV für ein Hosting System auf Basis von FreeBSD 64Bit auf einem dedizierten Server beschrieben.
keywords:
  - ClamAV
  - mkdocs
  - docs
lang: de
robots: index, follow
hide: []
search:
  exclude: false
---

## Einleitung

Unser Hosting System wird um folgende Dienste erweitert.

- ClamAV

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../intro.md)

## Installation

Wir installieren `security/clamav` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/security_clamav
cat <<'EOF' > /var/db/ports/security_clamav/options
--8<-- "ports/security_clamav/options"
EOF


portmaster -w -B -g --force-config security/clamav  -n


sysrc clamav_clamd_enable="YES"
sysrc clamav_freshclam_enable="YES"
```

## Abschluss

ClamAV kann nun gestartet werden.

```shell

service clamav_freshclam onestart

freshclam

service clamav_clamd start
```
