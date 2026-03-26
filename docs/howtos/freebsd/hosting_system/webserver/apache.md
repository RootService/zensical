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
title: Apache
description: In diesem HowTo wird Schritt für Schritt die Installation des Apache Webservers für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# Apache

## Inhalt

* Apache 2.4.66
* MPM-Event
* HTTP/2 über `mod_http2` und `Protocols h2 http/1.1` ([httpd.apache.org][2])
* PHP-FPM über `mod_proxy_fcgi` per Unix-Socket ([httpd.apache.org][3])
* optional Brotli über `mod_brotli` ([httpd.apache.org][4])
* namensbasierte VirtualHosts mit TLS und ACME-Webroot ([httpd.apache.org][5])

---

## Einleitung

Dieses HowTo beschreibt die Installation und Grundkonfiguration von Apache auf FreeBSD 15+ für ein Hosting-System mit namensbasierten VirtualHosts, TLS und PHP-FPM über Unix-Socket.

Die hier verwendete Konfiguration setzt bewusst auf **`mpm_event`**, **HTTP/2**, **`mod_proxy_fcgi`** und optional **Brotli**. Für HTTP/2 muss `mod_http2` geladen sein und das Protokoll per `Protocols` aktiviert werden. Für PHP-FPM unterstützt `mod_proxy_fcgi` auch Unix-Domain-Sockets. `mod_brotli` steht für Brotli-Kompression als eigenes Modul zur Verfügung. Der aktuelle FreeBSD-Port `www/apache24` steht auf **2.4.66**; das ist zugleich die aktuelle stabile Apache-2.4-Version upstream. ([freshports.org][1])

---

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

Zusätzlich gilt für dieses HowTo:

* Apache ist der öffentliche Webserver auf Port **80** und **443**.
* PHP-FPM ist bereits installiert und der verwendete Pool lauscht tatsächlich auf dem konfigurierten Unix-Socket, zum Beispiel `/var/run/fpm_www.sock`.
* Für produktiven TLS-Betrieb liegen die Zertifikate bereits unter `/usr/local/etc/letsencrypt/live/...`.
* Der gemeinsame ACME-Webroot liegt unter `/usr/local/www/.well-known/acme-challenge/`.
* Port **80** bleibt für HTTP-01 erreichbar. Die Let’s-Encrypt-Validierung für HTTP-01 startet auf Port 80; Redirects werden zwar bis zu 10 Sprünge tief akzeptiert, aber nur zu `http:` oder `https:` und nur auf Port 80 oder 443. Wildcard-Zertifikate funktionieren mit HTTP-01 nicht. ([Let's Encrypt][6])
* Der Server-Hostname sollte lokal auflösbar sein, weil der FreeBSD-Port ausdrücklich darauf hinweist, dass Apache sonst je nach Modulen Probleme beim Start haben kann. ([freshports.org][7])

---

## Vorbereitungen

### DNS Records

Für dieses HowTo müssen zuvor folgende DNS-Records angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` dns-zone
example.com.            IN  A       __IPADDR4__
example.com.            IN  AAAA    __IPADDR6__

www.example.com.        IN  A       __IPADDR4__
www.example.com.        IN  AAAA    __IPADDR6__

mail.example.com.       IN  A       __IPADDR4__
mail.example.com.       IN  AAAA    __IPADDR6__
```

Namensbasierte VirtualHosts sind hier die richtige Basis: Apache unterscheidet sie über den Hostnamen der Anfrage, deshalb müssen die Hostnamen sauber auf den Webserver zeigen. `ServerName` und `DocumentRoot` gehören pro VirtualHost explizit gesetzt. ([httpd.apache.org][5])

### Gruppen / Benutzer / Passwörter

Für dieses HowTo müssen zuvor **keine zusätzlichen Systemgruppen, Systembenutzer oder Passwörter** angelegt werden.

Die Verzeichnisse für Webinhalte und Caches werden in diesem Setup mit dem vorhandenen Systembenutzer `www` betrieben.

### Verzeichnisse / Dateien

Für dieses HowTo müssen zuvor folgende Verzeichnisse angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` sh
mkdir -p /usr/local/www/.well-known/acme-challenge

install -d -m 1777 -o www -g www /usr/local/www/cache
install -d -m 1777 -o www -g www /usr/local/www/tmp

mkdir -p /usr/local/www/vhosts/0default0/conf
mkdir -p /usr/local/www/vhosts/0default0/cron
mkdir -p /usr/local/www/vhosts/0default0/logs
mkdir -p /usr/local/www/vhosts/0default0/data/.well-known

mkdir -p /usr/local/www/vhosts/mail.example.com/conf
mkdir -p /usr/local/www/vhosts/mail.example.com/cron
mkdir -p /usr/local/www/vhosts/mail.example.com/logs
mkdir -p /usr/local/www/vhosts/mail.example.com/data/.well-known

mkdir -p /usr/local/www/vhosts/www.example.com/conf
mkdir -p /usr/local/www/vhosts/www.example.com/cron
mkdir -p /usr/local/www/vhosts/www.example.com/logs
mkdir -p /usr/local/www/vhosts/www.example.com/data/.well-known

mkdir -p /usr/local/etc/newsyslog.conf.d
```

Für dieses HowTo müssen zuvor folgende Dateien angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` sh
```

Der eigene Baum unter `/usr/local/www` ist hier die saubere Basis. Apache kann zusätzliche Konfigurationsdateien per `Include` oder `IncludeOptional` einlesen; Änderungen daran werden aber erst nach Start, Reload oder Restart wirksam. `IncludeOptional` ist dabei robuster, wenn optionale Dateien oder Wildcards zeitweise nicht existieren. ([httpd.apache.org][8])

---

## Installation

### Wir installieren `www/apache24` und dessen Abhängigkeiten.

``` sh
mkdir -p /var/db/ports/www_apache24
cat <<'EOF' > /var/db/ports/www_apache24/options
--8<-- "freebsd/ports/www_apache24/options"
EOF

portmaster -w -B -g -U --force-config www/apache24 -n
```

### Dienst in `rc.conf` eintragen

Der Dienst wird mittels `sysrc` in der `rc.conf` eingetragen und dadurch beim Systemstart automatisch gestartet.

``` sh
sysrc apache24_enable=YES
sysrc apache24limits_enable=YES
```

### Logrotation einrichten

``` sh
cat <<'EOF' > /usr/local/etc/newsyslog.conf.d/apache24.conf
--8<-- "freebsd/configs/usr/local/etc/newsyslog.conf.d/apache24"
EOF
```

Der FreeBSD-Port verwendet den Dienstnamen `apache24`. Zusätzlich weist der Port darauf hin, dass bei modular gebautem Apache ohne aktiviertes MPM standardmäßig wieder `mpm_prefork` aktiviert wird, um Kompatibilität zu erhalten. In diesem HowTo wird deshalb bewusst eine Konfiguration mit `mpm_event` vorausgesetzt. ([freshports.org][7])

---

## Konfiguration

### Konfigurationsdateien

Apache wird über Textdateien konfiguriert. Die Hauptkonfiguration liegt in `httpd.conf`; weitere Dateien können per `Include` oder `IncludeOptional` eingebunden werden. Änderungen an diesen Dateien werden erst wirksam, wenn Apache neu geladen oder neu gestartet wird. Für namensbasierte VirtualHosts sollten `ServerName` und `DocumentRoot` pro Host immer explizit gesetzt werden. ([httpd.apache.org][8])

``` sh
cat <<'EOF' > /usr/local/etc/apache24/httpd.conf
--8<-- "freebsd/configs/usr/local/etc/apache24/httpd.conf"
EOF

cat <<'EOF' > /usr/local/etc/apache24/vhosts.conf
--8<-- "freebsd/configs/usr/local/etc/apache24/vhosts.conf"
EOF

cat <<'EOF' > /usr/local/etc/apache24/vhosts-ssl.conf
--8<-- "freebsd/configs/usr/local/etc/apache24/vhosts-ssl.conf"
EOF
```

### Wichtige Punkte der VHost-Konfiguration

Für jeden Host gilt in diesem Setup:

* `ServerName` immer explizit setzen
* `DocumentRoot` immer explizit setzen
* normale HTTP-Requests auf HTTPS umleiten
* `/.well-known/acme-challenge/` nach Möglichkeit direkt ausliefern und nicht unnötig über weitere Redirect-Ketten schicken
* auf Port 443 die TLS-VHosts getrennt halten
* für HTTP/2 `mod_http2` laden und `Protocols h2 http/1.1` setzen
* für PHP-FPM `mod_proxy` und `mod_proxy_fcgi` laden

Apache verlangt für HTTP/2 die Aktivierung per `Protocols`; für TLS-VHosts ist `Protocols h2 http/1.1` das dokumentierte Standardschema. `mod_proxy_fcgi` unterstützt PHP-FPM auch per Unix-Domain-Socket. Brotli steht als eigenes Modul `mod_brotli` zur Verfügung und muss dafür gebaut und geladen sein. ([httpd.apache.org][2])

### Testdatei im Webroot

Für einen ersten Funktionstest reicht eine statische Datei im Webroot völlig aus.

``` sh
touch /usr/local/www/vhosts/www.example.com/data/index.html
cat <<'EOF' > /usr/local/www/vhosts/www.example.com/data/index.html
<!doctype html>
<html lang="de">
<head>
  <meta charset="utf-8">
  <title>Apache funktioniert</title>
</head>
<body>
  <h1>Apache funktioniert</h1>
  <p>Der VirtualHost ist erreichbar.</p>
</body>
</html>
EOF
```

### `fixperms.sh` einrichten

``` sh
cat <<'EOF' > /usr/local/www/vhosts/0default0/cron/fixperms.sh
--8<-- "freebsd/configs/usr/local/www/vhosts/0default0/cron/fixperms.sh"
EOF
chmod 750 /usr/local/www/vhosts/0default0/cron/fixperms.sh

cat <<'EOF' > /usr/local/www/vhosts/mail.example.com/cron/fixperms.sh
--8<-- "freebsd/configs/usr/local/www/vhosts/mail.example.com/cron/fixperms.sh"
EOF
chmod 750 /usr/local/www/vhosts/mail.example.com/cron/fixperms.sh

cat <<'EOF' > /usr/local/www/vhosts/www.example.com/cron/fixperms.sh
--8<-- "freebsd/configs/usr/local/www/vhosts/www.example.com/cron/fixperms.sh"
EOF
chmod 750 /usr/local/www/vhosts/www.example.com/cron/fixperms.sh
```

### Konfiguration prüfen

Vor dem ersten Start sollte die Konfiguration immer geprüft werden. Apache dokumentiert `apachectl configtest` genau für die Syntaxprüfung. Zusätzlich sind `httpd -M` für geladene Module und `httpd -S` für die ausgewerteten VirtualHosts die passenden Kontrollen. ([httpd.apache.org][8])

``` sh
apachectl configtest
/usr/local/sbin/httpd -M | egrep 'mpm_event|http2|brotli|proxy_fcgi|ssl'
/usr/local/sbin/httpd -S
```

### ACME-Webroot testen

``` sh
echo "ok" > /usr/local/www/.well-known/acme-challenge/test.txt
fetch -qo - http://www.example.com/.well-known/acme-challenge/test.txt
rm -f /usr/local/www/.well-known/acme-challenge/test.txt
```

Wenn der letzte Abruf `ok` liefert, ist der Webroot-Pfad für Certbot richtig verdrahtet.

---

## Datenbanken

Für dieses HowTo sind **keine Datenbanken** erforderlich.

---

## Zusatzsoftware

Mögliche Zusatzsoftware wird hier installiert und konfiguriert.

Für dieses HowTo ist **keine zusätzliche Software** enthalten.

PHP-FPM, Certbot und die Zertifikatsbereitstellung sind Voraussetzungen und werden in eigenen HowTos behandelt.

---

## Aufräumen

Überflüssige oder temporäre Verzeichnisse und Dateien entsorgen.

### Zusatzsoftware Installation

Nicht erforderlich.

### Zusatzsoftware Konfiguration

Nicht erforderlich.

Optionale Testdatei wieder entfernen, wenn sie nicht dauerhaft benötigt wird:

``` sh
rm -f /usr/local/www/vhosts/www.example.com/data/index.html
```

---

## Abschluss

Apache kann nun gestartet werden.

``` sh
service apache24 start
```

Für spätere Änderungen:

``` sh
service apache24 reload
service apache24 restart
```

Für den abschließenden TLS-/ACME-Test:

``` sh
certbot renew --dry-run
```

---

## Referenzen

* Apache HTTP Server Project – Download / Release 2.4.66. ([httpd.apache.org][9])
* FreshPorts – `www/apache24`. ([freshports.org][1])
* Apache HTTP Server – Name-based Virtual Host Support. ([httpd.apache.org][5])
* Apache HTTP Server – Configuration Files. ([httpd.apache.org][8])
* Apache HTTP Server – `Include` / `IncludeOptional`. ([httpd.apache.org][10])
* Apache HTTP Server – `mod_http2`. ([httpd.apache.org][2])
* Apache HTTP Server – `mod_proxy_fcgi`. ([httpd.apache.org][3])
* Apache HTTP Server – `mod_brotli`. ([httpd.apache.org][4])
* Let’s Encrypt – Challenge Types. ([Let's Encrypt][6])

[1]: https://www.freshports.org/www/apache24/?branch=2026Q1 "FreshPorts -- www/apache24: Version 2.4.x of Apache web server"
[2]: https://httpd.apache.org/docs/current/mod/mod_http2.html "mod_http2 - Apache HTTP Server Version 2.4"
[3]: https://httpd.apache.org/docs/current/mod/mod_proxy_fcgi.html "mod_proxy_fcgi - Apache HTTP Server Version 2.4"
[4]: https://httpd.apache.org/docs/current/mod/mod_brotli.html "mod_brotli - Apache HTTP Server Version 2.4"
[5]: https://httpd.apache.org/docs/2.4/vhosts/name-based.html "Name-based Virtual Host Support - Apache HTTP Server ..."
[6]: https://letsencrypt.org/docs/challenge-types/ "Challenge Types"
[7]: https://www.freshports.org/www/apache24 "FreshPorts -- www/apache24: Version 2.4.x of Apache web server"
[8]: https://httpd.apache.org/docs/2.4/configuring.html "Configuration Files - Apache HTTP Server Version 2.4"
[9]: https://httpd.apache.org/download.cgi "Download - The Apache HTTP Server Project"
[10]: https://httpd.apache.org/docs/current/mod/core.html "core - Apache HTTP Server Version 2.4"
