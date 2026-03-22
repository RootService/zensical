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
lastmod: '2026-03-21'
title: PHP-FPM
description: In diesem HowTo wird Schritt für Schritt die Installation von PHP-FPM für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# PHP-FPM

## Inhalt

* PHP 8.4.16
* PHP-FPM
* optionale PHP-Erweiterungen
* optional Composer
* optional PEAR / PECL

---

## Einleitung

Dieses HowTo beschreibt die Installation und Konfiguration von **PHP-FPM** auf FreeBSD 15+ für Apache oder Nginx auf demselben Host.

Der FreeBSD-Port `lang/php84` enthält bereits die CLI, CGI und **FPM** samt Beispielkonfigurationen. Auf dem aktuellen Quarterly-Stand für **FreeBSD 15** liegt der Port bei **8.4.16**. PHP-FPM ist dabei die gebündelte FastCGI-Implementierung von PHP und die saubere Basis für den Betrieb hinter Apache oder Nginx. ([FreshPorts][1])

Diese Anleitung setzt bewusst auf **PHP-FPM über FastCGI** und **nicht** auf `mod_php`. Für Unix-Sockets wird in diesem HowTo der Pfad `/var/run/fpm_www.sock` verwendet.

---

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

Zusätzlich gilt für dieses HowTo:

* Ein Webserver wie Apache oder Nginx ist bereits vorhanden oder wird direkt im Anschluss eingerichtet.
* Der Webserver soll PHP **nicht** per `mod_php`, sondern per **FastCGI über PHP-FPM** anbinden.
* Für Unix-Sockets wird in diesem HowTo der Pfad `/var/run/fpm_www.sock` verwendet.

PHP-FPM ist dafür ausdrücklich gedacht: Es läuft als Daemon im Hintergrund und verarbeitet CGI-/FastCGI-Anfragen, die vom Webserver weitergereicht werden. ([man.freebsd.org][2])

---

## Vorbereitungen

### DNS Records

Für dieses HowTo sind **keine zusätzlichen DNS-Records** erforderlich.

### Verzeichnisse / Dateien

Für dieses HowTo müssen vor der Installation **keine zusätzlichen Verzeichnisse oder Dateien manuell angelegt** werden.

Die benötigten Beispielkonfigurationsdateien werden bereits durch `lang/php84` installiert. Dazu gehören unter anderem `php.ini-production`, `php-fpm.conf.default` und `php-fpm.d/www.conf.default`. ([FreshPorts][1])

### Gruppen / Benutzer / Passwörter

Für dieses HowTo sind **keine zusätzlichen Systemgruppen, Systembenutzer oder Passwörter** erforderlich.

---

## Installation

### Wir installieren `lang/php84` und dessen Abhängigkeiten.

```shell
install -d -m 0755 /var/db/ports/lang_php84
cat <<'EOF' > /var/db/ports/lang_php84/options
--8<-- "freebsd/ports/lang_php84/options"
EOF

portmaster -w -B -g -U --force-config lang/php84 -n
```

### Dienst in `rc.conf` eintragen

Der Dienst wird mittels `sysrc` in der `rc.conf` eingetragen und dadurch beim Systemstart automatisch gestartet.

Wichtig: Der FreeBSD-Dienst heißt **`php_fpm`**. Die frühere Schreibweise `php-fpm` wurde in den Ports-Startskripten umbenannt; die rc.conf-Variable bleibt dabei `php_fpm_enable`. ([FreeBSD Git][3])

```sh
sysrc php_fpm_enable=YES
```

---

## Konfiguration

### Konfigurationsdateien

`lang/php84` installiert die Standarddateien für PHP und PHP-FPM bereits mit. Für produktive Systeme ist `php.ini-production` die saubere Ausgangsbasis. Die globale FPM-Konfiguration liegt unter `/usr/local/etc/php-fpm.conf`, die Standard-Pool-Datei unter `/usr/local/etc/php-fpm.d/www.conf`. ([FreshPorts][1])

#### `php.ini` einrichten

```sh
install -b -m 0644 /usr/local/etc/php.ini-production /usr/local/etc/php.ini

cat <<'EOF' > /usr/local/etc/php.ini
--8<-- "freebsd/configs/usr/local/etc/php.ini"
EOF
```

#### `php-fpm.conf` einrichten

```sh
install -b -m 0644 /usr/local/etc/php-fpm.conf.default /usr/local/etc/php-fpm.conf
```

#### `php-fpm.d/www.conf` einrichten

Für Unix-Sockets sind `listen.owner`, `listen.group` und `listen.mode` die richtigen Parameter. Genau diese Direktiven sind in der PHP-Dokumentation für Socket-Rechte vorgesehen. Außerdem ist `pm.max_requests` sinnvoll, wenn du Worker nach einer bestimmten Anzahl Requests neu starten willst, um Speicherlecks in Drittbibliotheken abzufangen. ([PHP][4])

```sh
install -b -m 0644 /usr/local/etc/php-fpm.d/www.conf.default /usr/local/etc/php-fpm.d/www.conf

cat <<'EOF' > /usr/local/etc/php-fpm.d/www.conf
--8<-- "freebsd/configs/usr/local/etc/php-fpm.d/www.conf"
EOF
```

#### Logdateien anlegen

```sh
install -m 0664 -g www /dev/null /var/log/php_error.log
install -m 0664 -g www /dev/null /var/log/php_opcache.log
install -m 0664 -g www /dev/null /var/log/php_sendmail.log
```

### Konfiguration prüfen

Vor dem ersten Start sollte die Konfiguration immer geprüft werden. `php-fpm -t` ist genau dafür gedacht und testet die FPM-Konfigurationsdatei, ohne den Dienst produktiv zu starten. ([man.freebsd.org][2])

```sh
php -v
php-fpm -t
service php_fpm start
sockstat -4 -6 -l | grep fpm
```

---

## Datenbanken

Für dieses HowTo sind **keine Datenbanken** erforderlich.

---

## Zusatzsoftware

Mögliche Zusatzsoftware wird hier installiert und konfiguriert.

### PHP-Erweiterungen installieren

`lang/php84-extensions` ist ein **Meta-Port**. Er installiert standardmäßig nur einen Kernsatz an Erweiterungen. Zusätzliche Module wie `mbstring`, `gd`, `pdo_mysql` oder anwendungsspezifische Module müssen bewusst über Portoptionen oder eigene Slave-Ports ergänzt werden. ([FreshPorts][5])

```sh
install -d -m 0755 /var/db/ports/converters_php84-mbstring
cat <<'EOF' > /var/db/ports/converters_php84-mbstring/options
--8<-- "freebsd/ports/converters_php84-mbstring/options"
EOF

install -d -m 0755 /var/db/ports/devel_oniguruma
cat <<'EOF' > /var/db/ports/devel_oniguruma/options
--8<-- "freebsd/ports/devel_oniguruma/options"
EOF

install -d -m 0755 /var/db/ports/databases_php84-dba
cat <<'EOF' > /var/db/ports/databases_php84-dba/options
--8<-- "freebsd/ports/databases_php84-dba/options"
EOF

install -d -m 0755 /var/db/ports/databases_php84-pdo_mysql
cat <<'EOF' > /var/db/ports/databases_php84-pdo_mysql/options
--8<-- "freebsd/ports/databases_php84-pdo_mysql/options"
EOF

install -d -m 0755 /var/db/ports/graphics_php84-gd
cat <<'EOF' > /var/db/ports/graphics_php84-gd/options
--8<-- "freebsd/ports/graphics_php84-gd/options"
EOF

install -d -m 0755 /var/db/ports/graphics_gd
cat <<'EOF' > /var/db/ports/graphics_gd/options
--8<-- "freebsd/ports/graphics_gd/options"
EOF

install -d -m 0755 /var/db/ports/graphics_jpeg-turbo
cat <<'EOF' > /var/db/ports/graphics_jpeg-turbo/options
--8<-- "freebsd/ports/graphics_jpeg-turbo/options"
EOF

install -d -m 0755 /var/db/ports/devel_nasm
cat <<'EOF' > /var/db/ports/devel_nasm/options
--8<-- "freebsd/ports/devel_nasm/options"
EOF

install -d -m 0755 /var/db/ports/devel_libgit2
cat <<'EOF' > /var/db/ports/devel_libgit2/options
--8<-- "freebsd/ports/devel_libgit2/options"
EOF

install -d -m 0755 /var/db/ports/security_libssh2
cat <<'EOF' > /var/db/ports/security_libssh2/options
--8<-- "freebsd/ports/security_libssh2/options"
EOF

install -d -m 0755 /var/db/ports/www_libhttp
cat <<'EOF' > /var/db/ports/www_libhttp/options
--8<-- "freebsd/ports/www_libhttp/options"
EOF

install -d -m 0755 /var/db/ports/graphics_png
cat <<'EOF' > /var/db/ports/graphics_png/options
--8<-- "freebsd/ports/graphics_png/options"
EOF

install -d -m 0755 /var/db/ports/graphics_tiff
cat <<'EOF' > /var/db/ports/graphics_tiff/options
--8<-- "freebsd/ports/graphics_tiff/options"
EOF

install -d -m 0755 /var/db/ports/archivers_libdeflate
cat <<'EOF' > /var/db/ports/archivers_libdeflate/options
--8<-- "freebsd/ports/archivers_libdeflate/options"
EOF

install -d -m 0755 /var/db/ports/graphics_jbigkit
cat <<'EOF' > /var/db/ports/graphics_jbigkit/options
--8<-- "freebsd/ports/graphics_jbigkit/options"
EOF

install -d -m 0755 /var/db/ports/graphics_webp
cat <<'EOF' > /var/db/ports/graphics_webp/options
--8<-- "freebsd/ports/graphics_webp/options"
EOF

install -d -m 0755 /var/db/ports/graphics_giflib
cat <<'EOF' > /var/db/ports/graphics_giflib/options
--8<-- "freebsd/ports/graphics_giflib/options"
EOF

install -d -m 0755 /var/db/ports/print_freetype2
cat <<'EOF' > /var/db/ports/print_freetype2/options
--8<-- "freebsd/ports/print_freetype2/options"
EOF

install -d -m 0755 /var/db/ports/print_libraqm
cat <<'EOF' > /var/db/ports/print_libraqm/options
--8<-- "freebsd/ports/print_libraqm/options"
EOF

install -d -m 0755 /var/db/ports/converters_fribidi
cat <<'EOF' > /var/db/ports/converters_fribidi/options
--8<-- "freebsd/ports/converters_fribidi/options"
EOF

install -d -m 0755 /var/db/ports/print_harfbuzz
cat <<'EOF' > /var/db/ports/print_harfbuzz/options
--8<-- "freebsd/ports/print_harfbuzz/options"
EOF

install -d -m 0755 /var/db/ports/devel_gobject-introspection
cat <<'EOF' > /var/db/ports/devel_gobject-introspection/options
--8<-- "freebsd/ports/devel_gobject-introspection/options"
EOF

install -d -m 0755 /var/db/ports/x11-fonts_fontconfig
cat <<'EOF' > /var/db/ports/x11-fonts_fontconfig/options
--8<-- "freebsd/ports/x11-fonts_fontconfig/options"
EOF

install -d -m 0755 /var/db/ports/textproc_enchant2
cat <<'EOF' > /var/db/ports/textproc_enchant2/options
--8<-- "freebsd/ports/textproc_enchant2/options"
EOF

install -d -m 0755 /var/db/ports/lang_php84-extensions
cat <<'EOF' > /var/db/ports/lang_php84-extensions/options
--8<-- "freebsd/ports/lang_php84-extensions/options"
EOF

portmaster -w -B -g -U --force-config lang/php84-extensions -n

install -d -m 0755 /var/db/ports/mail_panda-cclient
cat <<'EOF' > /var/db/ports/mail_panda-cclient/options
--8<-- "freebsd/ports/mail_panda-cclient/options"
EOF

portmaster -w -B -g -U --force-config mail/pecl-imap -n
```

Die IMAP-Erweiterung ist auf FreeBSD als eigener Port `mail/pecl-imap` verfügbar. FreshPorts beschreibt sie als separate PHP-Erweiterung für IMAP, NNTP, POP3 und lokale Mailboxen. ([FreshPorts][6])

### Composer installieren

Composer ist ein **Dependency-Manager für PHP** und damit nützlich, aber **kein Pflichtbestandteil** von PHP-FPM selbst. ([PHP][7])

```sh
install -d -m 0755 /var/db/ports/devel_php-composer
cat <<'EOF' > /var/db/ports/devel_php-composer/options
--8<-- "freebsd/ports/devel_php-composer/options"
EOF

portmaster -w -B -g -U --force-config devel/php-composer -n
```

### PEAR installieren

PEAR ist das klassische **PHP Extension and Application Repository** und bleibt vor allem für ältere oder legacy-lastige Workflows relevant. Für moderne Anwendungen ist es meist optional. ([FreshPorts][8])

```sh
install -d -m 0755 /var/db/ports/devel_pear
cat <<'EOF' > /var/db/ports/devel_pear/options
--8<-- "freebsd/ports/devel_pear/options"
EOF

portmaster -w -B -g -U --force-config devel/pear -n
```

### PECL YAML installieren

Wenn Anwendungen YAML direkt in PHP benötigen, ist `textproc/pecl-yaml` der passende Port. Auf PHP 8.4 heißt das resultierende Paket **`php84-pecl-yaml`**. Auch das ist **optional** und anwendungsabhängig. ([FreshPorts][9])

```sh
portmaster -w -B -g -U --force-config textproc/pecl-yaml -n
```

---

## Aufräumen

Überflüssige oder temporäre Verzeichnisse und Dateien entsorgen.

### Zusatzsoftware Installation

Nicht erforderlich.

### Zusatzsoftware Konfiguration

Nicht erforderlich.

---

## Abschluss

PHP-FPM kann nun gestartet werden.

```sh
service php_fpm start
```

Für spätere Änderungen:

```sh
service php_fpm reload
service php_fpm restart
```

`php-fpm -t` bleibt der saubere Vorab-Test für Änderungen an der FPM-Konfiguration. Der Dienstname auf FreeBSD ist dabei `php_fpm`. ([man.freebsd.org][2])

---

## Referenzen

* FreshPorts: `lang/php84` — Portinhalt, Beispielkonfigurationen, Paketstände. ([FreshPorts][1])
* FreeBSD Ports `UPDATING` — Umbenennung des Dienstnamens von `php-fpm` auf `php_fpm`. ([FreeBSD Git][3])
* PHP Manual — FastCGI Process Manager (FPM). ([PHP][10])
* PHP Manual — FPM-Konfiguration (`listen.owner`, `listen.group`, `listen.mode`, `pm.max_requests`). ([PHP][4])
* FreeBSD Manpage — `php-fpm(8)` (`-t`, Konfigurationspfade, Hintergrunddienst). ([man.freebsd.org][2])
* FreshPorts: `lang/php84-extensions`. ([FreshPorts][5])
* PHP Manual — Composer-Einführung. ([PHP][7])
* FreshPorts: `devel/pear`. ([FreshPorts][8])
* FreshPorts: `textproc/pecl-yaml`. ([FreshPorts][9])
* FreshPorts: `mail/pecl-imap`. ([FreshPorts][6])

[1]: https://www.freshports.org/lang/php84/ "FreshPorts -- lang/php84: PHP Scripting Language (8.4.X branch)"
[2]: https://man.freebsd.org/cgi/man.cgi?query=php-fpm "php-fpm"
[3]: https://cgit.freebsd.org/ports/tree/UPDATING "UPDATING - ports - FreeBSD ports tree"
[4]: https://www.php.net/manual/en/install.fpm.configuration.php "PHP: Configuration - Manual"
[5]: https://www.freshports.org/lang/php84-extensions/ "FreshPorts -- lang/php84-extensions: \"meta-port\" to install PHP extensions (8.4.X branch)"
[6]: https://www.freshports.org/mail/pecl-imap/ "FreshPorts -- mail/pecl-imap: PHP extension to operate with the IMAP protocol"
[7]: https://www.php.net/manual/en/install.composer.intro.php "PHP: Introduction to Composer - Manual"
[8]: https://www.freshports.org/devel/pear/ "FreshPorts -- devel/pear: PEAR framework for PHP"
[9]: https://www.freshports.org/textproc/pecl-yaml/ "FreshPorts -- textproc/pecl-yaml: YAML-1.1 parser and emitter"
[10]: https://www.php.net/manual/en/book.fpm.php "FastCGI Process Manager - Manual"
