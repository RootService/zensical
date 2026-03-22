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
title: Nginx
description: In diesem HowTo wird Schritt für Schritt die Installation des Nginx-Webservers für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# Nginx

## Inhalt

* Nginx 1.28.2
* Webserver für statische Inhalte, Redirects und TLS
* PHP-FPM-Anbindung per Unix-Socket
* HTTP/2
* optional HTTP/3
* optional Brotli

---

## Einleitung

Dieses HowTo beschreibt die Installation und Konfiguration von Nginx auf FreeBSD 15+ als Webserver für statische Inhalte, Redirects, TLS und PHP-FPM über Unix-Socket.

Dieses HowTo verwendet bewusst den Port **`www/nginx`**. Der aktuelle Portstand ist **1.28.2**. Der Port bringt bereits die üblichen Beispielkonfigurationen wie `nginx.conf-dist`, `fastcgi_params-dist` und `mime.types-dist` mit. In den Default-Optionen sind **HTTPV2** und **HTTPV3** enthalten; **Brotli** gehört dagegen nicht zu den Standardoptionen und muss bewusst zusätzlich gewählt werden. ([freshports.org][1])

Nginx liest seine Hauptkonfiguration aus `nginx.conf`. Änderungen an der Konfiguration werden erst nach **Reload** oder **Restart** wirksam. Für die PHP-Anbindung verwendet Nginx `fastcgi_pass`; dabei kann statt TCP ausdrücklich auch ein **Unix-Domain-Socket** verwendet werden. ([Nginx][2])

---

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

Zusätzlich gilt für dieses HowTo:

* Nginx ist der öffentliche Webserver auf Port **80** und **443**.
* PHP-FPM ist bereits installiert.
* PHP-FPM lauscht tatsächlich auf dem konfigurierten Unix-Socket, zum Beispiel `/var/run/fpm_www.sock`.
* Für produktiven TLS-Betrieb liegen die Zertifikate bereits unter `/usr/local/etc/letsencrypt/live/...`.
* Falls HTTP/3 später aktiviert werden soll, muss zusätzlich **QUIC** konfiguriert werden. Dafür braucht Nginx einen eigenen `listen ... quic`-Listener; QUIC verwendet dabei **UDP** als Transport. ([Nginx][3])

---

## Vorbereitungen

### DNS Records

Für dieses HowTo müssen zuvor folgende DNS-Records angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

```dns-zone
example.com.            IN  A       __IPADDR4__
example.com.            IN  AAAA    __IPADDR6__

www.example.com.        IN  A       __IPADDR4__
www.example.com.        IN  AAAA    __IPADDR6__

mail.example.com.       IN  A       __IPADDR4__
mail.example.com.       IN  AAAA    __IPADDR6__
```

### Verzeichnisse / Dateien

Für dieses HowTo müssen zuvor folgende Verzeichnisse angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

```shell
install -d -m 1777 -o www -g www /usr/local/www/cache
install -d -m 1777 -o www -g www /usr/local/www/tmp

install -d -m 0755 /usr/local/www/vhosts/0default0/conf
install -d -m 0755 /usr/local/www/vhosts/0default0/cron
install -d -m 0755 /usr/local/www/vhosts/0default0/logs
install -d -m 0750 -o www -g www /usr/local/www/vhosts/0default0/data
install -d -m 0750 -o www -g www /usr/local/www/vhosts/0default0/data/.well-known

install -d -m 0755 /usr/local/www/vhosts/mail.example.com/conf
install -d -m 0755 /usr/local/www/vhosts/mail.example.com/cron
install -d -m 0755 /usr/local/www/vhosts/mail.example.com/logs
install -d -m 0750 -o www -g www /usr/local/www/vhosts/mail.example.com/data
install -d -m 0750 -o www -g www /usr/local/www/vhosts/mail.example.com/data/.well-known

install -d -m 0755 /usr/local/www/vhosts/www.example.com/conf
install -d -m 0755 /usr/local/www/vhosts/www.example.com/cron
install -d -m 0755 /usr/local/www/vhosts/www.example.com/logs
install -d -m 0750 -o www -g www /usr/local/www/vhosts/www.example.com/data
install -d -m 0750 -o www -g www /usr/local/www/vhosts/www.example.com/data/.well-known

install -d -m 0755 /usr/local/etc/newsyslog.conf.d
```

Für diese HowTos müssen zuvor folgende Dateien angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

```shell
install -b -m 0644 /dev/null /usr/local/etc/newsyslog.conf.d/nginx.conf
```

### Gruppen / Benutzer / Passwörter

Für dieses HowTo sind **keine zusätzlichen Systemgruppen, Systembenutzer oder Passwörter** erforderlich.

---

## Installation

### Wir installieren `www/nginx` und dessen Abhängigkeiten.

```shell
install -d -m 0755 /var/db/ports/devel_google-perftools
cat <<'EOF' > /var/db/ports/devel_google-perftools/options
--8<-- "freebsd/ports/devel_google-perftools/options"
EOF

install -d -m 0755 /var/db/ports/www_nginx
cat <<'EOF' > /var/db/ports/www_nginx/options
--8<-- "freebsd/ports/www_nginx/options"
EOF

portmaster -w -B -g -U --force-config www/nginx -n
```

### Dienst in `rc.conf` eintragen

Der Dienst wird mittels `sysrc` in der `rc.conf` eingetragen und dadurch beim Systemstart automatisch gestartet.

```sh
sysrc nginx_enable=YES
sysrc nginxlimits_enable=YES
```

### Logrotation einrichten

```sh
cat <<'EOF' > /usr/local/etc/newsyslog.conf.d/nginx.conf
--8<-- "freebsd/configs/usr/local/etc/newsyslog.conf.d/nginx"
EOF
```

---

## Konfiguration

### Konfigurationsdateien

Der Port liefert bereits Beispielkonfigurationen mit. Die zentrale Konfiguration liegt unter `/usr/local/etc/nginx/nginx.conf`; zusätzliche Dateien wie `vhosts.conf` und `vhosts-ssl.conf` können über `include` eingebunden werden. Änderungen werden erst nach `reload` oder `restart` wirksam. ([freshports.org][1])

```sh
install -b -m 0644 /usr/local/etc/nginx/nginx.conf-dist /usr/local/etc/nginx/nginx.conf
cat <<'EOF' > /usr/local/etc/nginx/nginx.conf
--8<-- "freebsd/configs/usr/local/etc/nginx/nginx.conf"
EOF

install -b -m 0644 /dev/null /usr/local/etc/nginx/vhosts.conf
cat <<'EOF' > /usr/local/etc/nginx/vhosts.conf
--8<-- "freebsd/configs/usr/local/etc/nginx/vhosts.conf"
EOF

install -b -m 0644 /dev/null /usr/local/etc/nginx/vhosts-ssl.conf
cat <<'EOF' > /usr/local/etc/nginx/vhosts-ssl.conf
--8<-- "freebsd/configs/usr/local/etc/nginx/vhosts-ssl.conf"
EOF
```

### HTTP/2

Für aktuelles Nginx wird HTTP/2 über die Direktive `http2 on;` aktiviert. Seit **Nginx 1.25.1** ist der alte `http2`-Parameter an `listen` deprecated. Das passt zu deinem ursprünglichen Hinweis und sollte in den VHosts genauso umgesetzt werden. ([Nginx][4])

### HTTP/3 nur bei Bedarf

HTTP/3 ist in Nginx ein eigener Modulpfad. Das offizielle `ngx_http_v3_module` wird weiterhin als **experimental** dokumentiert. Zusätzlich reicht ein Build mit HTTP/3-Unterstützung allein nicht aus: du brauchst einen separaten QUIC-Listener wie `listen 443 quic reuseport;`. Die offizielle Dokumentation empfiehlt außerdem, für HTTP/3 und HTTPS denselben Port zu verwenden. ([Nginx][3])

Wenn du HTTP/3 **nicht** gezielt brauchst, aktiviere zunächst nur HTTP/2 und nimm QUIC später separat in Betrieb.

### PHP-FPM über Unix-Socket

Für PHP-FPM ist die FastCGI-Anbindung per Unix-Socket fachlich korrekt. Nginx unterstützt bei `fastcgi_pass` ausdrücklich Socket-Pfade im Format `unix:/pfad/zur.sock`. Genau deshalb muss der in deiner Nginx-Konfiguration eingetragene Socket-Pfad wirklich zum laufenden PHP-FPM-Pool passen. ([Nginx][5])

### `fixperms.sh` einrichten

```sh
install -b -m 0750 /dev/null /usr/local/www/vhosts/0default0/cron/fixperms.sh
cat <<'EOF' > /usr/local/www/vhosts/0default0/cron/fixperms.sh
--8<-- "freebsd/configs/usr/local/www/vhosts/0default0/cron/fixperms.sh"
EOF

install -b -m 0750 /dev/null /usr/local/www/vhosts/mail.example.com/cron/fixperms.sh
cat <<'EOF' > /usr/local/www/vhosts/mail.example.com/cron/fixperms.sh
--8<-- "freebsd/configs/usr/local/www/vhosts/mail.example.com/cron/fixperms.sh"
EOF

install -b -m 0750 /dev/null /usr/local/www/vhosts/www.example.com/cron/fixperms.sh
cat <<'EOF' > /usr/local/www/vhosts/www.example.com/cron/fixperms.sh
--8<-- "freebsd/configs/usr/local/www/vhosts/www.example.com/cron/fixperms.sh"
EOF
```

### Konfiguration prüfen

Vor dem ersten Start sollte die Konfiguration immer geprüft werden. `nginx -t` prüft Syntax und grundlegende Konsistenz. Änderungen an der Konfiguration werden von Nginx erst nach Reload oder Restart übernommen; beim Reload validiert der Master-Prozess die neue Konfiguration zuerst und rollt bei Fehlern auf die alte zurück. ([Nginx][2])

```sh
nginx -t
service nginx restart
sockstat -4 -6 -l | egrep 'nginx|php-fpm'
```

Für HTTP/2 und optionale HTTP/3-/Brotli-Nutzung solltest du lokal zusätzlich prüfen, ob der Port wirklich mit den gewünschten Modulen gebaut wurde.

---

## Datenbanken

Für dieses HowTo sind **keine Datenbanken** erforderlich.

---

## Zusatzsoftware

Mögliche Zusatzsoftware wird hier installiert und konfiguriert.

Für dieses HowTo ist **keine zusätzliche Software** erforderlich.

**Brotli** bleibt eine bewusste Zusatzentscheidung. Im Standard-Port `www/nginx` ist es nicht Teil der Default-Optionen. ([GitHub][6])

---

## Aufräumen

Überflüssige oder temporäre Verzeichnisse und Dateien entsorgen.

### Zusatzsoftware Installation

Nicht erforderlich.

### Zusatzsoftware Konfiguration

Nicht erforderlich.

---

## Abschluss

Nginx kann nun gestartet werden.

```sh
service nginx start
```

Für spätere Änderungen:

```sh
service nginx reload
service nginx restart
```

Nginx übernimmt Änderungen an Konfigurationsdateien erst nach einem Reload oder Restart. Beim Reload werden neue Worker nur dann gestartet, wenn die neue Konfiguration gültig ist. ([Nginx][2])

---

## Referenzen

* FreeBSD FreshPorts: `www/nginx` ([freshports.org][1])
* Nginx Beginner’s Guide ([Nginx][2])
* Nginx Runtime Control / Reload-Verhalten ([Nginx][7])
* Nginx `ngx_http_fastcgi_module` ([Nginx][5])
* Nginx `ngx_http_v2_module` ([Nginx][4])
* Nginx CHANGES 1.25.1 (`http2`-Direktive, Deprecation von `listen ... http2`) ([Nginx][8])
* Nginx QUIC / HTTP/3 ([Nginx][9])

[1]: https://www.freshports.org/www/nginx/ "FreshPorts -- www/nginx: Robust and small WWW server"
[2]: https://nginx.org/en/docs/beginners_guide.html "Beginner’s Guide"
[3]: https://nginx.org/en/docs/http/ngx_http_v3_module.html "Module ngx_http_v3_module"
[4]: https://nginx.org/en/docs/http/ngx_http_v2_module.html "Module ngx_http_v2_module"
[5]: https://nginx.org/en/docs/http/ngx_http_fastcgi_module.html "Module ngx_http_fastcgi_module"
[6]: https://github.com/freebsd/freebsd-ports/blob/main/www/nginx/Makefile "freebsd-ports/www/nginx/Makefile at main"
[7]: https://nginx.org/en/docs/control.html "Controlling nginx"
[8]: https://nginx.org/en/CHANGES "Changes"
[9]: https://nginx.org/en/docs/quic.html "Support for QUIC and HTTP/3"
