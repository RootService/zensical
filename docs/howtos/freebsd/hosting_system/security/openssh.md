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
title: OpenSSH
description: In diesem HowTo wird Schritt für Schritt die Installation von OpenSSH für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# OpenSSH

> **Stand:** 2026-02-27  
> **Terminologie:** Einheitlich werden die Begriffe **HowTo**, **HowTos**, **BaseSystem**, **BasePorts** und **BaseTools** verwendet.


## Einleitung

Unser Hosting System wird folgende Dienste umfassen.

- OpenSSH 10.2.p1 (Public-Key-Auth)

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

## Installation

Wir installieren `security/openssh-portable` und dessen Abhängigkeiten.

``` shell
mkdir -p /var/db/ports/dns_ldns
cat <<'EOF' > /var/db/ports/dns_ldns/options
--8<-- "freebsd/ports/dns_ldns/options"
EOF

mkdir -p /var/db/ports/security_libfido2
cat <<'EOF' > /var/db/ports/security_libfido2/options
--8<-- "freebsd/ports/security_libfido2/options"
EOF

mkdir -p /var/db/ports/security_openssh-portable
cat <<'EOF' > /var/db/ports/security_openssh-portable/options
--8<-- "freebsd/ports/security_openssh-portable/options"
EOF


portmaster -w -B -g --force-config security/openssh-portable@default  -n


sysrc openssh_dsa_enable=NO
sysrc openssh_pidfile="/var/run/opensshd.pid"
sysrc openssh_enable=YES
```

## Konfiguration

Wir konfigurieren OpenSSH:

``` shell
cat <<'EOF' > /usr/local/etc/ssh/sshd_config
--8<-- "freebsd/configs/usr/local/etc/ssh/sshd_config"
EOF

rm -f /usr/local/etc/ssh/ssh_host_*_key*
ssh-keygen -q -t rsa -b 4096 -f "/usr/local/etc/ssh/ssh_host_rsa_key" -N ""
ssh-keygen -l -f "/usr/local/etc/ssh/ssh_host_rsa_key.pub"
ssh-keygen -q -t ecdsa -b 384 -f "/usr/local/etc/ssh/ssh_host_ecdsa_key" -N ""
ssh-keygen -l -f "/usr/local/etc/ssh/ssh_host_ecdsa_key.pub"
ssh-keygen -q -t ed25519 -f "/usr/local/etc/ssh/ssh_host_ed25519_key" -N ""
ssh-keygen -l -f "/usr/local/etc/ssh/ssh_host_ed25519_key.pub"
```

## Abschluss

OpenSSH kann nun gestartet werden.

``` shell
service openssh start
```
