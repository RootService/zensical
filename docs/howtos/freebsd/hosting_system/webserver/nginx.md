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
title: NGinx
description: In diesem HowTo wird step-by-step die Installation des NGinx Webservers für ein Hosting System auf Basis von FreeBSD 64Bit auf einem dedizierten Server´ beschrieben.
keywords:
  - NGinx
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

- NGinx 1.28.0 (HTTP/2, mod_brotli)

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../intro.md)

## Installation

<!-- markdownlint-disable MD046 -->

???+ important

    Der Rest des HowTo ist derzeit nicht auf das Zusammenspiel mit NGinx abgestimmt, daher ist die Verwendung von

Apache aktuell zu bevorzugen. NGinx bietet zudem auch keinen wirklichen Mehrwert gegenüber Apache, so dass Apache
generell bevorzugt werden sollte. Die hier gezeigte Konfiguration ist nicht ausreichend getestet, enthält
möglicherweise sicherheitsrelevante Fehler und ist daher vollkommen unsupportet. Die Verwendung von NGinx erfolgt daher
ausschliesslich auf eigenes Risiko und ohne weitere Unterstützung durch dieses HowTo.

<!-- markdownlint-enable MD046 -->

Wir installieren `www/nginx` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/graphics_gd
cat <<'EOF' > /var/db/ports/graphics_gd/options
--8<-- "ports/graphics_gd/options"
EOF

mkdir -p /var/db/ports/graphics_jpeg-turbo
cat <<'EOF' > /var/db/ports/graphics_jpeg-turbo/options
--8<-- "ports/graphics_jpeg-turbo/options"
EOF

mkdir -p /var/db/ports/graphics_png
cat <<'EOF' > /var/db/ports/graphics_png/options
--8<-- "ports/graphics_png/options"
EOF

mkdir -p /var/db/ports/graphics_tiff
cat <<'EOF' > /var/db/ports/graphics_tiff/options
--8<-- "ports/graphics_tiff/options"
EOF

mkdir -p /var/db/ports/archivers_libdeflate
cat <<'EOF' > /var/db/ports/archivers_libdeflate/options
--8<-- "ports/archivers_libdeflate/options"
EOF

mkdir -p /var/db/ports/graphics_jbigkit
cat <<'EOF' > /var/db/ports/graphics_jbigkit/options
--8<-- "ports/graphics_jbigkit/options"
EOF

mkdir -p /var/db/ports/graphics_webp
cat <<'EOF' > /var/db/ports/graphics_webp/options
--8<-- "ports/graphics_webp/options"
EOF

mkdir -p /var/db/ports/graphics_giflib
cat <<'EOF' > /var/db/ports/graphics_giflib/options
--8<-- "ports/graphics_giflib/options"
EOF

mkdir -p /var/db/ports/print_freetype2
cat <<'EOF' > /var/db/ports/print_freetype2/options
--8<-- "ports/print_freetype2/options"
EOF

mkdir -p /var/db/ports/print_libraqm
cat <<'EOF' > /var/db/ports/print_libraqm/options
--8<-- "ports/print_libraqm/options"
EOF

mkdir -p /var/db/ports/converters_fribidi
cat <<'EOF' > /var/db/ports/converters_fribidi/options
--8<-- "ports/converters_fribidi/options"
EOF

mkdir -p /var/db/ports/print_harfbuzz
cat <<'EOF' > /var/db/ports/print_harfbuzz/options
--8<-- "ports/print_harfbuzz/options"
EOF

mkdir -p /var/db/ports/devel_gobject-introspection
cat <<'EOF' > /var/db/ports/devel_gobject-introspection/options
--8<-- "ports/devel_gobject-introspection/options"
EOF

mkdir -p /var/db/ports/x11-fonts_fontconfig
cat <<'EOF' > /var/db/ports/x11-fonts_fontconfig/options
--8<-- "ports/x11-fonts_fontconfig/options"
EOF

mkdir -p /var/db/ports/www_nginx
cat <<'EOF' > /var/db/ports/www_nginx/options
--8<-- "ports/www_nginx/options"
EOF


portmaster -w -B -g --force-config www/nginx  -n


sysrc nginx_enable=YES
sysrc nginxlimits_enable=YES


mkdir -p /usr/local/etc/newsyslog.conf.d
cat <<'EOF' > /usr/local/etc/newsyslog.conf.d/nginx
--8<-- "configs/usr/local/etc/newsyslog.conf.d/nginx"
EOF
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

`nginx.conf` einrichten.

```shell
cat <<'EOF' > /usr/local/etc/nginx/nginx.conf
--8<-- "configs/usr/local/etc/nginx/nginx.conf"
EOF
```

`vhosts.conf` einrichten.

```shell
cat <<'EOF' > /usr/local/etc/nginx/vhosts.conf
--8<-- "configs/usr/local/etc/nginx/vhosts.conf"
EOF
```

`vhosts-ssl.conf` einrichten.

```shell
cat <<'EOF' > /usr/local/etc/nginx/vhosts-ssl.conf
--8<-- "configs/usr/local/etc/nginx/vhosts-ssl.conf"
EOF
```

`defaults.conf` und `headers.conf` einrichten.

```shell
cat <<'EOF' > /usr/local/etc/nginx/defaults.conf
        location ~* /?(.+/)*[\._] { return 403; }
        location ~* /?\.well-known { allow all; }
EOF

cat <<'EOF' > /usr/local/etc/nginx/headers.conf
        add_header  Access-Control-Allow-Methods  "GET, POST, OPTIONS";
#        add_header  Access-Control-Allow-Headers "Origin, X-Requested-With, Content-Type, Accept, Accept-Encoding"
        add_header  Access-Control-Allow-Origin  "*";
        add_header  Access-Control-Max-Age  "600";
        add_header  Content-Security-Policy "\
block-all-mixed-content; \
upgrade-insecure-requests; \
default-src 'self' 'unsafe-inline' https: data: blob: mediastream:; \
script-src 'self' 'unsafe-inline' 'unsafe-eval' https: blob: mediastream:; \
form-action 'self' https:; \
frame-ancestors 'self'; \
sandbox allow-forms allow-modals allow-pointer-lock allow-popups allow-popups-to-escape-sandbox allow-same-origin allow-scripts allow-top-navigation"
        add_header  Referrer-Policy  "strict-origin-when-cross-origin";
        add_header  Timing-Allow-Origin  "*";
        add_header  Upgrade-Insecure-Requests  "1";
        add_header  X-Content-Type-Options  "nosniff";
        add_header  X-DNS-Prefetch-Control  "on";
        add_header  X-Download-Options  "noopen";
        add_header  X-Frame-Options  "SAMEORIGIN";
        add_header  X-Permitted-Cross-Domain-Policies  "none";
        add_header  X-XSS-Protection  "1; mode=block";
EOF
```

## Abschluss

NGinx kann nun gestartet werden.

```shell
service nginx start
```
