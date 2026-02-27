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
title: Unbound
description: In diesem HowTo wird Schritt für Schritt die Installation von Unbound für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# Unbound

> **Stand:** 2026-02-27  
> **Terminologie:** Einheitlich werden die Begriffe **HowTo**, **HowTos**, **BaseSystem**, **BasePorts** und **BaseTools** verwendet.


## Einleitung

Unser Hosting System wird folgende Dienste umfassen.

- Unbound 1.24.2 (DNScrypt, DNS over TLS)

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

## Installation

Wir installieren `dns/unbound` und dessen Abhängigkeiten.

``` shell
mkdir -p /var/db/ports/security_libsodium
cat <<'EOF' > /var/db/ports/security_libsodium/options
--8<-- "freebsd/ports/security_libsodium/options"
EOF

mkdir -p /var/db/ports/devel_libevent
cat <<'EOF' > /var/db/ports/devel_libevent/options
--8<-- "freebsd/ports/devel_libevent/options"
EOF

mkdir -p /var/db/ports/dns_unbound
cat <<'EOF' > /var/db/ports/dns_unbound/options
--8<-- "freebsd/ports/dns_unbound/options"
EOF


portmaster -w -B -g --force-config dns/unbound  -n


sysrc local_unbound_enable=NO
sysrc unbound_enable=YES
```

## Konfiguration

Wir konfigurieren Unbound:

``` shell
cat <<'EOF' > /usr/local/etc/unbound/unbound.conf
--8<-- "freebsd/configs/usr/local/etc/unbound/unbound.conf"
EOF

curl -o "/usr/local/etc/unbound/root.hints" -L "https://www.internic.net/domain/named.root"
chown unbound /usr/local/etc/unbound/root.hints

sudo -u unbound unbound-anchor -a "/usr/local/etc/unbound/root.key"

sudo -u unbound unbound-control-setup
```

## Abschluss

Unbound kann nun gestartet werden.

``` shell
service local_unbound onestop

service unbound start
```
