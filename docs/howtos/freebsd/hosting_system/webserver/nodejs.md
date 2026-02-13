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
contributers: []
date: '2010-08-25'
lastmod: '2025-06-28'
title: NodeJS
description: In diesem HowTo wird step-by-step die Installation des NodeJS Servers für ein Hosting System auf Basis von FreeBSD 64Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# NodeJS

## Einleitung

Unser Hosting System wird um folgende Dienste erweitert.

- NodeJS 24.13.0 (NPM, YARN)

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

## Installation

Wir installieren `www/node` und dessen Abhängigkeiten.

``` shell
mkdir -p /var/db/ports/dns_c-ares
cat <<'EOF' > /var/db/ports/dns_c-ares/options
--8<-- "freebsd/ports/dns_c-ares/options"
EOF

mkdir -p /var/db/ports/www_node24
cat <<'EOF' > /var/db/ports/www_node24/options
--8<-- "freebsd/ports/www_node24/options"
EOF


portmaster -w -B -g --force-config www/node  -n


sysrc node_enable=YES
```

Wir installieren `www/npm` und dessen Abhängigkeiten.

``` shell
portmaster -w -B -g --force-config www/npm  -n
```

Wir installieren `www/yarn` und dessen Abhängigkeiten.

``` shell
mkdir -p /var/db/ports/www_yarn-node24
cat <<'EOF' > /var/db/ports/www_yarn-node24/options
--8<-- "freebsd/ports/www_yarn-node24/options"
EOF


portmaster -w -B -g --force-config www/yarn  -n
```
