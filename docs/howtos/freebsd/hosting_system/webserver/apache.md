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
title: Apache
description: In diesem HowTo wird step-by-step die Installation des Apache Webservers für ein Hosting System auf Basis von FreeBSD 64Bit auf einem dedizierten Server beschrieben.
keywords:
  - Apache
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

- Apache 2.4.63 (MPM-Event, HTTP/2, mod_brotli)

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../intro.md)

## Installation

Wir installieren `www/apache24` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/www_apache24
cat <<'EOF' > /var/db/ports/www_apache24/options
--8<-- "ports/www_apache24/options"
EOF


portmaster -w -B -g --force-config www/apache24  -n


mkdir -p /usr/local/etc/newsyslog.conf.d
cat <<'EOF' > /usr/local/etc/newsyslog.conf.d/apache24
--8<-- "configs/usr/local/etc/newsyslog.conf.d/apache24"

EOF

cp -a /usr/local/www /data/
```

## Konfiguration

Verzeichnisse für die ersten VirtualHosts erstellen.

```shell
mkdir -p /data/www/cache
chmod 1777 /data/www/cache
chown www:www /data/www/cache
mkdir -p /data/www/tmp
chmod 1777 /data/www/tmp
chown www:www /data/www/tmp


mkdir -p /data/www/vhosts/_default_/conf
mkdir -p /data/www/vhosts/_default_/cron
mkdir -p /data/www/vhosts/_default_/logs
mkdir -p /data/www/vhosts/_default_/data/.well-known
chmod 0750 /data/www/vhosts/_default_/data
chown www:www /data/www/vhosts/_default_/data

mkdir -p /data/www/vhosts/_localhost_/conf
mkdir -p /data/www/vhosts/_localhost_/cron
mkdir -p /data/www/vhosts/_localhost_/logs
mkdir -p /data/www/vhosts/_localhost_/data/.well-known
chmod 0750 /data/www/vhosts/_localhost_/data
chown www:www /data/www/vhosts/_localhost_/data

mkdir -p /data/www/vhosts/mail.example.com/conf
mkdir -p /data/www/vhosts/mail.example.com/cron
mkdir -p /data/www/vhosts/mail.example.com/logs
mkdir -p /data/www/vhosts/mail.example.com/data/.well-known
chmod 0750 /data/www/vhosts/mail.example.com/data
chown www:www /data/www/vhosts/mail.example.com/data

mkdir -p /data/www/vhosts/www.example.com/conf
mkdir -p /data/www/vhosts/www.example.com/cron
mkdir -p /data/www/vhosts/www.example.com/logs
mkdir -p /data/www/vhosts/www.example.com/data/.well-known
chmod 0750 /data/www/vhosts/www.example.com/data
chown www:www /data/www/vhosts/www.example.com/data
```

Die folgende Konfiguration verwendet für den localhost den Pfad `/data/www/vhosts/_localhost_`, für den Default-Host
den Pfad `/data/www/vhosts/_default_` und für die regulären Virtual-Hosts den Pfad `/data/www/vhosts/sub.domain.tld`.

`httpd.conf` einrichten.

```shell
cat <<'EOF' > /usr/local/etc/apache24/httpd.conf
--8<-- "configs/usr/local/etc/apache24/httpd.conf"
EOF
```

`vhosts.conf` einrichten.

```shell
cat <<'EOF' > /usr/local/etc/apache24/vhosts.conf
--8<-- "configs/usr/local/etc/apache24/vhosts.conf"
EOF
```

`vhosts-ssl.conf` einrichten.

```shell
cat <<'EOF' > /usr/local/etc/apache24/vhosts-ssl.conf
--8<-- "configs/usr/local/etc/apache24/vhosts-ssl.conf"
EOF
```

`default-endpoint.php` einrichten.

```shell
cat <<'EOF' > /data/www/vhosts/www.example.com/data/default-endpoint.php
--8<-- "configs/data/www/vhosts/www.example.com/data/default-endpoint.php"
EOF
```

`fixperms.sh` einrichten.

```shell
cat <<'EOF' > /data/www/vhosts/_default_/cron/fixperms.sh
--8<-- "configs/data/www/vhosts/_default_/cron/fixperms.sh"
EOF
chmod 0750 /data/www/vhosts/_default_/cron/fixperms.sh

cat <<'EOF' > /data/www/vhosts/_localhost_/cron/fixperms.sh
--8<-- "configs/data/www/vhosts/_localhost_/cron/fixperms.sh"
EOF
chmod 0750 /data/www/vhosts/_localhost_/cron/fixperms.sh

cat <<'EOF' > /data/www/vhosts/mail.example.com/cron/fixperms.sh
--8<-- "configs/data/www/vhosts/mail.example.com/cron/fixperms.sh"
EOF
chmod 0750 /data/www/vhosts/mail.example.com/cron/fixperms.sh

cat <<'EOF' > /data/www/vhosts/www.example.com/cron/fixperms.sh
--8<-- "configs/data/www/vhosts/www.example.com/cron/fixperms.sh"
EOF
chmod 0750 /data/www/vhosts/www.example.com/cron/fixperms.sh
```

## Abschluss

Apache kann nun gestartet werden.

```shell
service apache24 start
```
