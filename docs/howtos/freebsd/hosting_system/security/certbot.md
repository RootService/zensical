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
title: CertBot
description: In diesem HowTo wird Schritt für Schritt die Installation von CertBot für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# CertBot

## Inhalt

- Certbot 4.2.0
- Let’s Encrypt ACME
- Webroot-Modus für HTTP-01
- automatische Zertifikatserneuerung über FreeBSD periodic
- Deploy-Hooks nach erfolgreicher Erneuerung

---

## Einleitung

Dieses HowTo beschreibt die Installation und Konfiguration von Certbot auf FreeBSD 15+.

Certbot wird in diesem HowTo bewusst im **Webroot-Modus** betrieben. Das ist für eine bereits laufende Apache-Instanz die sauberere Lösung, weil Certbot die Challenge-Dateien direkt unter `/.well-known/acme-challenge/` ablegt und Apache sie ausliefert. Die Apache-Konfiguration bleibt dabei vollständig manuell und kontrolliert.

---

## Voraussetzungen

Für dieses HowTo muss Apache bereits installiert und funktionsfähig sein.

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

Zusätzlich gilt für dieses HowTo:

- Apache ist installiert und läuft bereits.
- Apache liefert `/.well-known/acme-challenge/` korrekt aus.
- Für HTTP-01 muss Port **80/TCP** von außen erreichbar sein.
- Für Wildcard-Zertifikate ist dieses HowTo **nicht** geeignet; dafür ist **DNS-01** erforderlich.

---

## Vorbereitungen

Vor der Installation müssen die DNS-Auflösung, die Webroot-Struktur und die Certbot-Verzeichnisse vorbereitet werden.

### DNS Records

Für dieses HowTo müssen zuvor die benötigten Hostnamen auf den Webserver zeigen.

``` dns-zone
example.com.             IN  A       __IPADDR4__
example.com.             IN  AAAA    __IPADDR6__

www.example.com.         IN  A       __IPADDR4__
www.example.com.         IN  AAAA    __IPADDR6__

mail.example.com.        IN  A       __IPADDR4__
mail.example.com.        IN  AAAA    __IPADDR6__

devnull.example.com.     IN  A       __IPADDR4__
devnull.example.com.     IN  AAAA    __IPADDR6__
```

### Verzeichnisse / Dateien

Für dieses HowTo müssen zuvor folgende Verzeichnisse angelegt werden, sofern sie noch nicht existieren.

``` sh
install -d -m 0755 /var/db/letsencrypt
install -d -m 0700 /var/log/letsencrypt

install -d -m 0755 /usr/local/etc/letsencrypt
install -d -m 0755 /usr/local/etc/letsencrypt/renewal-hooks
install -d -m 0755 /usr/local/etc/letsencrypt/renewal-hooks/pre
install -d -m 0755 /usr/local/etc/letsencrypt/renewal-hooks/post
install -d -m 0755 /usr/local/etc/letsencrypt/renewal-hooks/deploy

install -d -m 0755 /usr/local/www
install -d -m 0755 /usr/local/www/.well-known
install -d -m 0755 /usr/local/www/.well-known/acme-challenge

install -d -m 0755 /usr/local/etc/certs
```

Zusätzliche Dateien müssen vor der Installation noch nicht vorbereitet werden. Die eigentlichen Konfigurationsdateien werden im Abschnitt **Konfiguration** angelegt.

### Gruppen / Benutzer / Passwörter

Für dieses HowTo sind **keine** zusätzlichen Systemgruppen, Systembenutzer oder separaten Passwörter erforderlich.

---

## Installation

### Wir installieren `security/py-certbot` und dessen Abhängigkeiten.

``` sh
install -d -m 0755 /var/db/ports/textproc_py-snowballstemmer
cat <<'EOF' > /var/db/ports/textproc_py-snowballstemmer/options
--8<-- "freebsd/ports/textproc_py-snowballstemmer/options"
EOF

install -d -m 0755 /var/db/ports/security_py-certbot
cat <<'EOF' > /var/db/ports/security_py-certbot/options
--8<-- "freebsd/ports/security_py-certbot/options"
EOF

portmaster -w -B -g -U --force-config security/py-certbot -n
```

### Dienst in `rc.conf` eintragen

Nicht erforderlich.

Certbot ist kein dauerhaft laufender Dienst mit eigenem `rc.d`-Service. Die automatische Erneuerung erfolgt in diesem HowTo über den FreeBSD-Periodic-Mechanismus.

---

## Konfiguration

### Konfigurationsdatei `cli.ini`

Die zentrale Certbot-Konfiguration liegt unter `/usr/local/etc/letsencrypt/cli.ini`. Für dieses Setup ist **Webroot** die richtige Standardwahl.

``` sh
install -b -m 0644 /dev/null /usr/local/etc/letsencrypt/cli.ini
cat <<'EOF' > /usr/local/etc/letsencrypt/cli.ini
--8<-- "freebsd/configs/usr/local/etc/letsencrypt/cli.ini"
EOF
```

ECDSA ist für dieses Setup die sinnvolle Standardwahl, solange keine bewusst sehr alten HTTPS-Clients unterstützt werden müssen.

### Deploy-Hook einrichten

Der Deploy-Hook wird nur nach erfolgreicher Zertifikatserneuerung ausgeführt. In diesem HowTo übernimmt er das Neubauen der benötigten PEM-Dateien und das anschließende Neuladen der betroffenen TLS-Dienste.

``` sh
install -b -m 0750 /dev/null /usr/local/etc/letsencrypt/renewal-hooks/deploy/hook.sh
cat <<'EOF' > /usr/local/etc/letsencrypt/renewal-hooks/deploy/hook.sh
--8<-- "freebsd/configs/usr/local/etc/letsencrypt/renewal-hooks/deploy/hook.sh"
EOF
```

### FreeBSD-Periodic konfigurieren

Für dieses Webroot-Setup genügt der Weekly-Periodic-Job mit Deploy-Hook und den gewünschten Certbot-Argumenten.

``` sh
cat <<'EOF' >> /etc/periodic.conf
weekly_certbot_enable="YES"
weekly_certbot_deploy_hook="/usr/local/etc/letsencrypt/renewal-hooks/deploy/hook.sh"
weekly_certbot_custom_args="--config-dir /usr/local/etc/letsencrypt --work-dir /var/db/letsencrypt --logs-dir /var/log/letsencrypt"
EOF
```

### Test vor der ersten Ausstellung

Vor der ersten Zertifikatsausstellung muss geprüft werden, ob Apache die ACME-Challenge-Dateien wirklich ausliefert.

``` sh
apachectl configtest
service apache24 reload

echo "ok" > /usr/local/www/.well-known/acme-challenge/test.txt
fetch -qo - http://www.example.com/.well-known/acme-challenge/test.txt
rm -f /usr/local/www/.well-known/acme-challenge/test.txt
```

Wenn der letzte Abruf `ok` liefert, ist der kritische Pfad für HTTP-01 korrekt verdrahtet.

### Zertifikate ausstellen

Für dieses Beispiel bleiben die Zertifikatsgruppen wie folgt aufgeteilt:

* `devnull.example.com`
* `mail.example.com`
* `www.example.com` zusammen mit `example.com`

``` sh
certbot register

certbot certonly -d devnull.example.com
certbot certonly -d mail.example.com
certbot certonly -d www.example.com -d example.com
```

`certonly` ist hier absichtlich gewählt. Certbot beschafft nur die Zertifikate; die Apache-Konfiguration bleibt vollständig manuell.

### Konfiguration prüfen

Vor dem produktiven Einsatz sollte zuerst ein trockener Erneuerungstest durchgeführt werden.

``` sh
certbot renew --dry-run
certbot certificates
```

---

## Datenbanken

Für dieses HowTo sind **keine Datenbanken** erforderlich.

---

## Zusatzsoftware

### Ausnahmefall: Standalone-Modus

Falls vorübergehend noch kein Webserver auf Port 80 betrieben wird, kann Certbot im Standalone-Modus verwendet werden.

``` sh
certbot register --standalone
certbot certonly --standalone -d subdomain.example.com
```

Sobald Apache produktiv auf Port 80 läuft, sollte wieder auf den **Webroot-Modus** gewechselt werden.

### Ausnahmefall: Apache-Plugin

Optional existiert auf FreeBSD zusätzlich der separate Port `security/py-certbot-apache`.

Dieses HowTo verwendet das Apache-Plugin trotzdem **nicht**, weil die Apache-Konfiguration hier bewusst manuell und nachvollziehbar bleiben soll.

---

## Aufräumen

Temporäre Dateien aus dem eigentlichen Setup fallen in diesem HowTo nicht an. Die Testdatei unter `/.well-known/acme-challenge/` wurde bereits im Testschritt wieder entfernt.

### Zusatzsoftware Installation

Nicht erforderlich.

### Zusatzsoftware Konfiguration

Nicht erforderlich.

---

## Abschluss

Certbot ist nun eingerichtet und kann für Ausstellung, Prüfung und Erneuerung von Zertifikaten verwendet werden.

``` sh
certbot certificates
periodic weekly
```

Für spätere Änderungen und Funktionstests:

``` sh
certbot renew --dry-run
certbot renew
service apache24 reload
```

---

## Referenzen

* [Certbot User Guide](https://eff-certbot.readthedocs.io/en/stable/using.html)
* [Certbot Manual Page](https://eff-certbot.readthedocs.io/en/stable/man/certbot.html)
* [Let’s Encrypt: Challenge Types](https://letsencrypt.org/docs/challenge-types/)
* [FreeBSD Port `security/py-certbot`](https://www.freshports.org/security/py-certbot/)
* [FreeBSD Port `security/py-certbot-apache`](https://www.freshports.org/security/py-certbot-apache/)
