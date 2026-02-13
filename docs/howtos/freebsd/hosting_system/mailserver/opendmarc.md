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
title: OpenDMARC
description: In diesem HowTo wird step-by-step die Installation von OpenDMARC für ein Hosting System auf Basis von FreeBSD 64Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# OpenDMARC

## Einleitung

Unser Hosting System wird um folgende Dienste erweitert.

- OpenDMARC 1.4.2 (SPF2, FailureReports)

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

## Installation

Wir installieren `mail/opendmarc` und dessen Abhängigkeiten.

``` shell
mkdir -p /var/db/ports/databases_p5-DBI
cat <<'EOF' > /var/db/ports/databases_p5-DBI/options
--8<-- "freebsd/ports/databases_p5-DBI/options"
EOF

mkdir -p /var/db/ports/mail_opendmarc
cat <<'EOF' > /var/db/ports/mail_opendmarc/options
--8<-- "freebsd/ports/mail_opendmarc/options"
EOF


portmaster -w -B -g --force-config mail/opendmarc  -n


sysrc opendmarc_enable=YES
sysrc opendmarc_socketspec="inet:8895@localhost"
```

``` shell
mkdir -p /data/db/opendmarc

chown -R mailnull:mailnull /data/db/opendmarc
```

## Konfigurieren

`opendmarc.conf` einrichten.

``` shell
cat <<'EOF' > /usr/local/etc/mail/opendmarc.conf
--8<-- "freebsd/configs/usr/local/etc/mail/opendmarc.conf"
EOF
```

IgnoreHosts anlegen.

``` shell
cat <<'EOF' > /data/db/opendmarc/ignorehosts
::1
127.0.0.1
fe80::/10
ff02::/16
10.0.0.0/8
__IPADDR4__
__IPADDR6__
localhost
example.com
*.example.com
EOF

# IPv4
ifconfig -u -f cidr `route -n get -inet default | awk '/interface/ {print $2}'` inet | \
    awk 'tolower($0) ~ /inet[\ \t]+((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/ {if(substr($2,1,3)!=127) print $2}' | \
    head -n 1 | xargs -I % sed -e 's|__IPADDR4__|%|g' -i '' /data/db/opendmarc/ignorehosts

# IPv6
ifconfig -u -f cidr `route -n get -inet6 default | awk '/interface/ {print $2}'` inet6 | \
    awk 'tolower($0) ~ /inet6[\ \t]+(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/ {if(substr($2,1,1)!="f") print $2}' | \
    head -n 1 | xargs -I % sed -e 's|__IPADDR6__|%|g' -i '' /data/db/opendmarc/ignorehosts
```

``` shell
chown -R mailnull:mailnull /data/db/opendmarc
```

## Abschluss

OpenDMARC kann nun gestartet werden.

``` shell
service opendmarc start
```
