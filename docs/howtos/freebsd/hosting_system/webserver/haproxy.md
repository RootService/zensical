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
title: HAProxy
description: In diesem HowTo wird Schritt für Schritt die Installation des HAProxy Reverse Proxy / Load Balancers für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---
# HAProxy

## Inhalt

* HAProxy 3.2.15
* TLS-Termination
* HTTP/2 über ALPN
* ACLs
* lokale Stats-Seite
* Reverse Proxy vor Nginx auf `127.0.0.1:8080`

---

## Einleitung

Dieses HowTo beschreibt die Installation und Konfiguration von HAProxy auf FreeBSD 15+.

HAProxy übernimmt in diesem Aufbau die öffentlich erreichbaren Ports **80** und **443**, terminiert TLS, leitet HTTP sauber auf HTTPS um, kanonisiert Hostnamen und reicht die Anfragen intern an Nginx auf `127.0.0.1:8080` weiter. Nginx bleibt für die eigentlichen VirtualHosts, statische Inhalte und PHP-FPM zuständig. Für FreeBSD ist dafür der Port `net/haproxy` die passende Basis. Aktuell steht dieser Port auf **3.2.15**; die Flavors sind **default**, **lua** und **wolfssl**. Für dieses HowTo wird bewusst der **Default-Flavour** verwendet. ([freshports.org][1])

---

## Voraussetzungen

Was muss bereits erledigt sein?

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

Zusätzlich wird in diesem HowTo vorausgesetzt:

* Nginx ist bereits installiert und lauscht intern auf `127.0.0.1:8080`.
* Die Zertifikate für `devnull.example.com`, `www.example.com` und `mail.example.com` existieren bereits.
* PHP-FPM bleibt hinter Nginx; HAProxy spricht in diesem Aufbau **nicht** direkt mit PHP-FPM.

---

## Vorbereitungen

Was muss vor der Installation vorbereitet werden?

### DNS Records

Für dieses HowTo müssen zuvor folgende DNS-Records angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` dns-zone
devnull.example.com.     IN  A       __IPADDR4__
devnull.example.com.     IN  AAAA    __IPADDR6__

www.example.com.         IN  A       __IPADDR4__
www.example.com.         IN  AAAA    __IPADDR6__

mail.example.com.        IN  A       __IPADDR4__
mail.example.com.        IN  AAAA    __IPADDR6__
```

### Verzeichnisse / Dateien

Für dieses HowTo müssen zuvor folgende Verzeichnisse angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` sh
install -d -m 0750 /usr/local/etc/letsencrypt/certs
```

Für diese HowTos müssen zuvor folgende Dateien angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` sh
install -b -m 0640 /dev/null /usr/local/etc/letsencrypt/certs/crt-list.txt
```

HAProxy kann Zertifikate direkt per `crt` oder über eine `crt-list` laden. Eine `crt-list` ist genau dafür gedacht, mehrere Zertifikatsdateien mit optionalen SSL- oder SNI-Filtern zu verwalten. Für dieses Setup ist das die saubere Basis. ([docs.haproxy.org][2])

### Gruppen / Benutzer / Passwörter

Für dieses HowTo sind **keine zusätzlichen Systemgruppen, Systembenutzer oder Passwörter** erforderlich.

---

## Installation

### Wir installieren `net/haproxy` und dessen Abhängigkeiten.

``` sh
install -d -m 0755 /var/db/ports/net_haproxy
cat <<'EOF' > /var/db/ports/net_haproxy/options
--8<-- "freebsd/ports/net_haproxy/options"
EOF

portmaster -w -B -g -U --force-config net/haproxy -n
```

### Dienst in `rc.conf` eintragen

Der Dienst wird mittels `sysrc` in der `rc.conf` eingetragen und dadurch beim Systemstart automatisch gestartet.

``` sh
sysrc haproxy_enable=YES
sysrc haproxy_config="/usr/local/etc/haproxy.conf"
```

Das FreeBSD-rc-Skript verwendet den Dienstnamen `haproxy`. Standardmäßig erwartet es die Konfigurationsdatei unter `/usr/local/etc/haproxy.conf`; zusätzlich bringt es unter anderem `reload` und `configtest` als Service-Aktionen mit. ([FreeBSD Git][3])

---

## Konfiguration

### Konfigurationsdatei

HAProxy braucht im Kern nur das Binary und eine Konfigurationsdatei. Die Konfiguration wird vor dem Start vollständig eingelesen; fehlerhafte Konfigurationen oder nicht bindbare Listener verhindern den Start. Genau deshalb gehört ein sauberer Konfigurationstest vor jeden Start oder Reload. ([FreeBSD Git][3])

``` sh
install -b -m 0644 /dev/null /usr/local/etc/haproxy.conf
cat <<'EOF' > /usr/local/etc/haproxy.conf
--8<-- "freebsd/configs/usr/local/etc/haproxy.conf"
EOF
```

### Zertifikate für HAProxy vorbereiten

Für HAProxy werden die vorhandenen Let’s-Encrypt-Dateien je Domain zu einer PEM-Datei zusammengeführt. Anschließend werden diese Dateien über eine `crt-list` eingebunden.

``` sh
cat /usr/local/etc/letsencrypt/live/devnull.example.com/fullchain.pem \
    /usr/local/etc/letsencrypt/live/devnull.example.com/privkey.pem \
    > /usr/local/etc/letsencrypt/certs/devnull.example.com.pem

cat /usr/local/etc/letsencrypt/live/www.example.com/fullchain.pem \
    /usr/local/etc/letsencrypt/live/www.example.com/privkey.pem \
    > /usr/local/etc/letsencrypt/certs/www.example.com.pem

cat /usr/local/etc/letsencrypt/live/mail.example.com/fullchain.pem \
    /usr/local/etc/letsencrypt/live/mail.example.com/privkey.pem \
    > /usr/local/etc/letsencrypt/certs/mail.example.com.pem

chmod 0640 /usr/local/etc/letsencrypt/certs/*.pem

cat <<'EOF' > /usr/local/etc/letsencrypt/certs/crt-list.txt
/usr/local/etc/letsencrypt/certs/devnull.example.com.pem
/usr/local/etc/letsencrypt/certs/www.example.com.pem
/usr/local/etc/letsencrypt/certs/mail.example.com.pem
EOF

chmod 0640 /usr/local/etc/letsencrypt/certs/crt-list.txt
```

Die `crt-list` arbeitet mit PEM-Dateien. Pro Zeile können zusätzlich Bind-Optionen und Filter hinterlegt werden; für dieses Basis-Setup reicht eine einfache Liste der Zertifikatsdateien. ([docs.haproxy.org][2])

### Warum die Konfiguration so aufgebaut ist

Dieses Setup macht drei Dinge:

* `fe_http` nimmt Port 80 entgegen und leitet fast alles auf HTTPS um, **außer** ACME-Requests für `/.well-known/acme-challenge/`.
* `fe_https` terminiert TLS auf Port 443, spricht per ALPN HTTP/2 und reicht die Requests an Nginx weiter.
* `stats_local` bietet eine **lokale** Stats-Seite auf `127.0.0.1:8404`.

Für HTTPS ist `alpn h2,http/1.1` hier die richtige Standardwahl. Die HAProxy-Dokumentation beschreibt für reguläre HTTPS-Frontends genau diese ALPN-Kombination als Default, wenn nichts anderes gesetzt ist. `path_beg` ist der dokumentierte Präfix-Matcher für ACLs. `redirect scheme https` baut die Ziel-URL aus Scheme, Host und URI inklusive Querystring. `option forwardfor` ergänzt die Client-IP per Header. Die Stats-Seite wird offiziell über `stats enable` und `stats uri` aktiviert. ([docs.haproxy.org][2])

### Zusammenspiel mit Nginx

Dieses HowTo setzt voraus, dass Nginx intern nur noch auf `127.0.0.1:8080` lauscht und **nicht mehr selbst** öffentlich auf 80 oder 443 bindet.

Wichtig bleibt dabei:

* Nginx intern auf `127.0.0.1:8080`
* PHP-FPM weiter auf `/var/run/fpm_www.sock`
* ACME-Pfade weiter unter `/.well-known/acme-challenge/`
* Redirect-Logik auf Port 80 und 443 in Nginx entfernen oder auf den internen Betrieb anpassen

### Konfiguration prüfen

Vor dem ersten Start sollte die Konfiguration immer geprüft werden.

``` sh
service haproxy configtest
haproxy -c -f /usr/local/etc/haproxy.conf
haproxy -vv | egrep 'OpenSSL|PCRE2|Lua|Prometheus|QUIC'
```

Das rc-Skript bringt `configtest` mit. Zusätzlich meldet `haproxy -c`, ob die Konfiguration sauber gelesen werden kann. Mit `haproxy -vv` kannst du danach die tatsächlich gebauten Features prüfen. ([FreeBSD Git][3])

---

## Datenbanken

Für dieses HowTo sind **keine Datenbanken** erforderlich.

---

## Zusatzsoftware

Mögliche Zusatzsoftware wird hier installiert und konfiguriert.

Für dieses HowTo ist **keine zusätzliche Software** erforderlich.

---

## Aufräumen

Überflüssige oder temporäre Verzeichnisse und Dateien entsorgen.

### Zusatzsoftware Installation

Nicht erforderlich.

### Zusatzsoftware Konfiguration

Nicht erforderlich.

---

## Abschluss

HAProxy kann nun gestartet werden.

``` sh
service haproxy start
```

Für spätere Änderungen:

``` sh
service haproxy reload
service haproxy restart
```

Funktionstest:

``` sh
fetch -qo - http://127.0.0.1:8404/.well-known/haproxy-stats >/dev/null && echo OK
sockstat -4 -6 -l | egrep 'haproxy|nginx'
```

Das rc-Skript unterstützt `reload`; für Änderungen an der Konfiguration ist das der saubere Standardweg, solange kein kompletter Neustart nötig ist. ([FreeBSD Git][3])

---

## Referenzen

* FreshPorts: `net/haproxy`. ([freshports.org][1])
* HAProxy Dokumentation: Versionsübersicht und LTS-Zweige. ([haproxy.org][4])
* HAProxy 3.2 Configuration Manual: `crt-list`, ALPN, Redirects, ACLs, `option forwardfor`, Stats. ([docs.haproxy.org][2])
* FreeBSD Ports rc-Skript: `haproxy_enable`, `haproxy_config`, `configtest`, `reload`. ([FreeBSD Git][3])

[1]: https://www.freshports.org/net/haproxy "https://www.freshports.org/net/haproxy"
[2]: https://docs.haproxy.org/3.2/configuration.html "https://docs.haproxy.org/3.2/configuration.html"
[3]: https://cgit.freebsd.org/ports/plain/net/haproxy/files/haproxy.in "https://cgit.freebsd.org/ports/plain/net/haproxy/files/haproxy.in"
[4]: https://www.haproxy.org/ "https://www.haproxy.org/"
