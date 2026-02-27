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
title: PHP-FPM
description: In diesem HowTo wird step-by-step die Installation von PHP-FPM für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# PHP-FPM

> **Stand:** 2026-02-27  
> **Terminologie:** Einheitlich werden die Begriffe **HowTo**, **HowTos**, **BaseSystem**, **BasePorts** und **BaseTools** verwendet.


## Einleitung

Unser Hosting System wird folgende Dienste umfassen.

- PHP 8.4.17 (PHP-FPM, Composer, PEAR)

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

## Installation

Wir installieren `lang/php84` und dessen Abhängigkeiten.

``` shell
mkdir -p /var/db/ports/lang_php84
cat <<'EOF' > /var/db/ports/lang_php84/options
--8<-- "freebsd/ports/lang_php84/options"
EOF


portmaster -w -B -g --force-config lang/php84  -n


sysrc php_fpm_enable=YES
```

## PHP-Extensions installieren

Wir installieren `lang/php84-extensions` und dessen Abhängigkeiten.

``` shell
mkdir -p /var/db/ports/graphics_gd
cat <<'EOF' > /var/db/ports/graphics_gd/options
--8<-- "freebsd/ports/graphics_gd/options"
EOF

mkdir -p /var/db/ports/graphics_jpeg-turbo
cat <<'EOF' > /var/db/ports/graphics_jpeg-turbo/options
--8<-- "freebsd/ports/graphics_jpeg-turbo/options"
EOF

mkdir -p /var/db/ports/graphics_png
cat <<'EOF' > /var/db/ports/graphics_png/options
--8<-- "freebsd/ports/graphics_png/options"
EOF

mkdir -p /var/db/ports/graphics_tiff
cat <<'EOF' > /var/db/ports/graphics_tiff/options
--8<-- "freebsd/ports/graphics_tiff/options"
EOF

mkdir -p /var/db/ports/archivers_libdeflate
cat <<'EOF' > /var/db/ports/archivers_libdeflate/options
--8<-- "freebsd/ports/archivers_libdeflate/options"
EOF

mkdir -p /var/db/ports/graphics_jbigkit
cat <<'EOF' > /var/db/ports/graphics_jbigkit/options
--8<-- "freebsd/ports/graphics_jbigkit/options"
EOF

mkdir -p /var/db/ports/graphics_webp
cat <<'EOF' > /var/db/ports/graphics_webp/options
--8<-- "freebsd/ports/graphics_webp/options"
EOF

mkdir -p /var/db/ports/graphics_giflib
cat <<'EOF' > /var/db/ports/graphics_giflib/options
--8<-- "freebsd/ports/graphics_giflib/options"
EOF

mkdir -p /var/db/ports/print_freetype2
cat <<'EOF' > /var/db/ports/print_freetype2/options
--8<-- "freebsd/ports/print_freetype2/options"
EOF

mkdir -p /var/db/ports/print_libraqm
cat <<'EOF' > /var/db/ports/print_libraqm/options
--8<-- "freebsd/ports/print_libraqm/options"
EOF

mkdir -p /var/db/ports/converters_fribidi
cat <<'EOF' > /var/db/ports/converters_fribidi/options
--8<-- "freebsd/ports/converters_fribidi/options"
EOF

mkdir -p /var/db/ports/print_harfbuzz
cat <<'EOF' > /var/db/ports/print_harfbuzz/options
--8<-- "freebsd/ports/print_harfbuzz/options"
EOF

mkdir -p /var/db/ports/devel_gobject-introspection
cat <<'EOF' > /var/db/ports/devel_gobject-introspection/options
--8<-- "freebsd/ports/devel_gobject-introspection/options"
EOF

mkdir -p /var/db/ports/x11-fonts_fontconfig
cat <<'EOF' > /var/db/ports/x11-fonts_fontconfig/options
--8<-- "freebsd/ports/x11-fonts_fontconfig/options"
EOF

mkdir -p /var/db/ports/mail_panda-cclient
cat <<'EOF' > /var/db/ports/mail_panda-cclient/options
--8<-- "freebsd/ports/mail_panda-cclient/options"
EOF

mkdir -p /var/db/ports/devel_oniguruma
cat <<'EOF' > /var/db/ports/devel_oniguruma/options
--8<-- "freebsd/ports/devel_oniguruma/options"
EOF

mkdir -p /var/db/ports/textproc_enchant2
cat <<'EOF' > /var/db/ports/textproc_enchant2/options
--8<-- "freebsd/ports/textproc_enchant2/options"
EOF

mkdir -p /var/db/ports/databases_php84-dba
cat <<'EOF' > /var/db/ports/databases_php84-dba/options
--8<-- "freebsd/ports/databases_php84-dba/options"
EOF

mkdir -p /var/db/ports/graphics_php84-gd
cat <<'EOF' > /var/db/ports/graphics_php84-gd/options
--8<-- "freebsd/ports/graphics_php84-gd/options"
EOF

mkdir -p /var/db/ports/converters_php84-mbstring
cat <<'EOF' > /var/db/ports/converters_php84-mbstring/options
--8<-- "freebsd/ports/converters_php84-mbstring/options"
EOF

mkdir -p /var/db/ports/databases_php84-pdo_mysql
cat <<'EOF' > /var/db/ports/databases_php84-pdo_mysql/options
--8<-- "freebsd/ports/databases_php84-pdo_mysql/options"
EOF

mkdir -p /var/db/ports/lang_php84-extensions
cat <<'EOF' > /var/db/ports/lang_php84-extensions/options
--8<-- "freebsd/ports/lang_php84-extensions/options"
EOF


portmaster -w -B -g --force-config lang/php84-extensions  -n
```

## Konfiguration

Die Konfiguration entspricht weitestgehend den Empfehlungen der PHP-Entwickler und ist sowohl auf Security als auch auf Performance getrimmt.

`php.ini` einrichten.

``` shell
cat <<'EOF' > /usr/local/etc/php.ini
--8<-- "freebsd/configs/usr/local/etc/php.ini"
EOF
```

`php-fpm.conf` einrichten.

``` shell
sed -e 's|^;[[:space:]]*\(events.mechanism =\).*$|;\1 kqueue|' \
    /usr/local/etc/php-fpm.conf.default > /usr/local/etc/php-fpm.conf
```

`php-fpm.d/www.conf` einrichten.

``` shell
sed -e 's|^\(listen =\).*$|\1 /var/run/fpm_www.sock|' \
    -e 's|^;\(listen.owner =\).*$|\1 www|' \
    -e 's|^;\(listen.group =\).*$|\1 www|' \
    -e 's|^;\(listen.mode =\).*$|\1 0660|' \
    -e 's|^\(pm.max_children =\).*$|\1 128|' \
    -e 's|^\(pm.start_servers =\).*$|\1 16|' \
    -e 's|^\(pm.min_spare_servers =\).*$|\1 4|' \
    -e 's|^\(pm.max_spare_servers =\).*$|\1 16|' \
    -e 's|^;\(pm.max_requests =\).*$|\1 500|' \
    /usr/local/etc/php-fpm.d/www.conf.default > /usr/local/etc/php-fpm.d/www.conf
```

Abschliessende Arbeiten.

``` shell
touch /var/log/php_error.log
chmod 0664 /var/log/php_error.log
chown root:www /var/log/php_error.log
touch /var/log/php_opcache.log
chmod 0664 /var/log/php_opcache.log
chown root:www /var/log/php_opcache.log
touch /var/log/php_sendmail.log
chmod 0664 /var/log/php_sendmail.log
chown root:www /var/log/php_sendmail.log
```

## PHP Composer installieren

Wir installieren `devel/php-composer` und dessen Abhängigkeiten.

``` shell
mkdir -p /var/db/ports/devel_php-composer
cat <<'EOF' > /var/db/ports/devel_php-composer/options
--8<-- "freebsd/ports/devel_php-composer/options"
EOF


portmaster -w -B -g --force-config devel/php-composer  -n
```

## PHP-PEAR installieren

Wir installieren `devel/pear` und dessen Abhängigkeiten.

``` shell
mkdir -p /var/db/ports/devel_pear
cat <<'EOF' > /var/db/ports/devel_pear/options
--8<-- "freebsd/ports/devel_pear/options"
EOF


portmaster -w -B -g --force-config devel/pear  -n
```

## PHP-PECL-YAML installieren

Wir installieren `textproc/pecl-yaml` und dessen Abhängigkeiten.

``` shell
portmaster -w -B -g --force-config textproc/pecl-yaml  -n
```

## Abschluss

PHP-FPM kann nun gestartet werden.

``` shell
service php-fpm start
```
