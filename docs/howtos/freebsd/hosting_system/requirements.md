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
title: Hosting System
description: In diesem HowTo werden Schritt für Schritt die Voraussetzungen für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# Hosting System

## Quick-Check vor Start

* [ ] Voraussetzungen aus den Vorseiten erfüllt
* [ ] DNS, FQDN, IPv4 und IPv6 final geprüft
* [ ] Zugriff auf Konsole und Recovery vorhanden
* [ ] Platzhalter (`__USERNAME__`, `__PASSWORD__`, `__IPADDR4__`, `__IPADDR6__`) vorbereitet
* [ ] Entscheidung für Paket- und Build-Strategie getroffen
* [ ] Reverse DNS / PTR für spätere Mailzustellung eingeplant

---

Diese HowTos setzen ein wie in [Remote Installation](../remote_install/requirements.md) beschriebenes, installiertes und konfiguriertes FreeBSD-Basissystem voraus.

## Einleitung

Diese Seite definiert die **gemeinsamen Voraussetzungen** für alle folgenden HowTos. Exakte Minor-Versionen einzelner Dienste werden hier bewusst **nicht** zentral festgeschrieben, weil sie schnell veralten und in den jeweiligen Einzelanleitungen sauberer aufgehoben sind.

Unser Hosting System wird am Ende unter anderem folgende Dienste/Programme umfassen:

* Certbot
* OpenSSH
* Unbound
* PostgreSQL
* MySQL
* Apache HTTP Server
* Nginx
* PHP
* Node.js
* Dovecot
* PostfixAdmin
* Postfix
* OpenDKIM
* OpenDMARC
* SpamAssassin
* Amavis
* ClamAV

## Grundsätze

Für alle folgenden HowTos gelten diese gemeinsamen Regeln:

* Alle Dienste werden mit einem möglichst kleinen, bewährten und nachvollziehbaren Funktionsumfang installiert.
* Alle Konfigurationen sind vor dem produktiven Einsatz auf die eigene Umgebung anzupassen.
* Alle Benutzernamen werden als `__USERNAME__` dargestellt und sind passend zu ersetzen.
* Alle Passwörter werden als `__PASSWORD__` dargestellt und sind durch sichere, individuelle Passwörter zu ersetzen.
* Die Beispiel-Domain lautet `example.com` und der Beispiel-Hostname lautet `devnull`, der FQDN also `devnull.example.com`.
* In den Beispielen werden unter anderem `devnull.example.com`, `mail.example.com` und `www.example.com` verwendet.
* Die primäre IPv4-Adresse wird als `__IPADDR4__` dargestellt.
* Die primäre IPv6-Adresse wird als `__IPADDR6__` dargestellt.
* Postfix und Dovecot teilen sich später den FQDN `mail.example.com` und in der Regel auch das dafür ausgestellte TLS-Zertifikat.

---

## Paket- und Build-Strategie

FreeBSD unterstützt zwei reguläre Wege für Drittsoftware: **Pakete** über `pkg` und **Ports** aus dem Ports-Tree. Pakete sind vorkompiliert, Ports erlauben dir vor allem die gezielte Auswahl von Compile-Time-Optionen. Das ist der eigentliche technische Vorteil von Ports, neben einer pauschalen Garantie auf mehr Sicherheit und Stabilität. Für Sicherheitsprüfungen nennt FreeBSD ausdrücklich **VuXML** und `pkg audit -F`. Für reproduzierbare Eigen-Builds ist ein Paketworkflow mit **poudriere** deutlich sauberer als Ad-hoc-Kompilierung direkt auf Produktivsystemen.

Wichtig ist außerdem: `pkg install` verwendet **Paketnamen**, nicht Port-Origens. Also zum Beispiel `pkg install curl` und **nicht** `pkg install ftp/curl`. Wenn Pakete und Ports parallel genutzt werden, müssen Ports-Tree und `pkg` auf derselben Branch-Basis liegen, sonst drohen Abhängigkeitskonflikte.

### Empfehlung für dieses HowTo

Für dieses Hosting System ist eine konsequente Linie sinnvoll:

* entweder **offizielle Pakete** mit den Default-Optionen,
* oder **eigene Builds** mit festgelegten Port-Optionen und idealerweise eigener Paketbereitstellung.

Wenn das offizielle FreeBSD-Repository bewusst deaktiviert werden soll, reicht dafür eine kleine Override-Datei.

``` shell
sed -e "s|quarterly|latest|g" -i '' /etc/pkg/FreeBSD.conf

install -d -m 0755 /usr/local/etc/pkg
install -d -m 0755 /usr/local/etc/pkg/repos
cat <<'EOF' > /usr/local/etc/pkg/repos/FreeBSD.conf
FreeBSD: {
  enabled: no
}
EOF
```

Vor Installationen und Updates sollte der Systemzustand auf bekannte Schwachstellen geprüft werden:

``` shell
pkg audit -F
```

---

## Grundlegende Verzeichnisstruktur

Da die Nutzdaten in den folgenden HowTos weitgehend unter `/var/db` abgelegt werden, werden die Basisverzeichnisse vorbereitet. Für wiederholbare Anleitungen ist `install -d` sauberer als eine Mischung aus `mkdir`, `chmod` und `chown`.

``` shell
install -d -m 0755 /var/db/backups
install -d -m 0755 /var/db/passwords
```

---

## DNS-Grundlagen

Für alle folgenden HowTos müssen die grundlegenden Forward-DNS-Einträge vorhanden sein:

``` dns-zone
example.com.                     IN  A       __IPADDR4__
example.com.                     IN  AAAA    __IPADDR6__

devnull.example.com.             IN  A       __IPADDR4__
devnull.example.com.             IN  AAAA    __IPADDR6__
```

---

## Voraussetzungen für den Abschnitt Security

Für ACME/Let’s Encrypt ist ein CAA-Record sinnvoll. Wenn normale und Wildcard-Zertifikate von derselben CA ausgestellt werden dürfen, reicht `issue "letsencrypt.org"` aus. Ein zusätzlicher `issuewild`-Record ist nur dann nötig, wenn Wildcard-Zertifikate abweichend geregelt werden sollen.

``` dns-zone
example.com.                     IN  CAA     0 issue "letsencrypt.org"
example.com.                     IN  CAA     0 issuewild "letsencrypt.org"
```

---

## Voraussetzungen für den Abschnitt Datenbanken

---

## Voraussetzungen für den Abschnitt Webserver

Für den späteren Webdienst werden zunächst die DNS-Einträge und das Basisverzeichnis vorbereitet.

``` dns-zone
www.example.com.                 IN  A       __IPADDR4__
www.example.com.                 IN  AAAA    __IPADDR6__
```

``` shell
install -d -m 0755 /usr/local/www
install -d -m 0755 /usr/local/www/apps

```

---

## Voraussetzungen für den Abschnitt Mailserver

Der Mail-Teil ist besonders empfindlich, weil hier DNS, Zustellbarkeit und spätere Authentifizierung zusammenlaufen. Für die Basis müssen mindestens MX-, Host- und Mailzugriffs-Records sauber vorbereitet werden. SRV-Records für IMAPS und Submission sind standardisiert; ein SRV-Target von `.` ist zulässig, um einen Dienst explizit als nicht verfügbar zu markieren. Wichtig ist nur die korrekte SRV-Syntax: **Priority Weight Port Target**.

``` dns-zone
example.com.                     IN  MX      10 mail.example.com.

mail.example.com.                IN  A       __IPADDR4__
mail.example.com.                IN  AAAA    __IPADDR6__

_imap._tcp.example.com.          IN  SRV     0 0 0 .
_imaps._tcp.example.com.         IN  SRV     0 1 993 mail.example.com.
_pop3._tcp.example.com.          IN  SRV     0 0 0 .
_pop3s._tcp.example.com.         IN  SRV     0 0 0 .
_submission._tcp.example.com.    IN  SRV     0 1 587 mail.example.com.
_submissions._tcp.example.com.   IN  SRV     0 1 465 mail.example.com.
```

### SPF

Für ein einfaches Setup, in dem ausschließlich der eigene MX-Host Mails für die Domain versendet, ist ein konservativer Einstieg wie `v=spf1 mx -all` deutlich sauberer. Wenn weitere Systeme senden dürfen, muss der Record entsprechend erweitert werden.

``` dns-zone
example.com.                     IN  TXT     "v=spf1 mx -all"
```

### DMARC

DMARC ist heute faktisch Pflicht für seriöse Mailzustellung. Google verlangt für Bulk-Sender mindestens einen DMARC-Record, und sowohl Google als auch Microsoft machen klar, dass SPF, DKIM, DMARC sowie korrekter Forward-/Reverse-DNS für die Zustellbarkeit relevant sind. Für eine Erstinbetriebnahme ist `p=none` als Beobachtungsmodus sinnvoll; später kann nach Auswertung der Reports auf `quarantine` oder `reject` verschärft werden.

``` dns-zone
_dmarc.example.com.              IN  TXT     "v=DMARC1; p=none; np=none; rua=mailto:postmaster@example.com; ruf=mailto:postmaster@example.com; adkim=s; aspf=s; fo=1"
```

### Reverse DNS / PTR einplanen

Für ausgehende Mailzustellung reichen MX, SPF und DMARC allein nicht. Die sendenden öffentlichen IP-Adressen sollten passende PTR-Records besitzen, die auf einen Hostnamen zeigen, dessen A- bzw. AAAA-Record wieder auf dieselbe IP zurückzeigt. Genau das nennen Google und Microsoft als wichtig für Zustellbarkeit und Authentifizierungsbewertung. Diese PTR-Einträge werden meist beim Provider oder im delegierten Reverse-Zonenbereich gepflegt und sollten **vor** produktivem Mailversand vorbereitet werden.

### Systembenutzer für Maildaten anlegen

Für die Maildaten werden eigene Systemkonten angelegt. Die Anlage sollte idempotent formuliert sein, damit die Befehle bei einer späteren Wiederholung nicht unnötig scheitern.

``` shell
pw groupshow vmail >/dev/null 2>&1 || pw groupadd -n vmail -g 5000
id -u vmail >/dev/null 2>&1 || \
  pw useradd -n vmail -u 5000 -g vmail -c 'Virtual Mailuser' -d /nonexistent -s /usr/sbin/nologin -w no

pw groupshow vacation >/dev/null 2>&1 || pw groupadd -n vacation -g 65501
id -u vacation >/dev/null 2>&1 || \
  pw useradd -n vacation -u 65501 -g vacation -c 'Vacation Notice' -d /nonexistent -s /usr/sbin/nologin -w no
```

### Passwörter für den Abschnitt Mailserver

``` shell
# Passwort für den PostgreSQL-User "postfix" erzeugen und
# in /var/db/passwords/user_postgresql_postfix speichern
install -b -m 0600 /dev/null /var/db/passwords/user_postgresql_postfix
openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | \
  cut -c 2-17 | tee /var/db/passwords/user_postgresql_postfix
```

Das Basisverzeichnis für virtuelle Maildaten wird anschließend mit passenden Rechten angelegt:

``` shell
install -d -m 0750 -o vmail -g vmail /var/vmail
```

---

## Los geht es

Die einzelnen HowTos bauen aufeinander auf und sollten in der vorgesehenen Reihenfolge abgearbeitet werden. Vor den Abschnitten **Security** und **Mailserver** sollte außerdem geprüft sein, dass die DNS-Einträge bereits öffentlich auflösbar sind und die Reverse-DNS-Konfiguration für spätere Mailzustellung vorbereitet wurde.

---

## Referenzen
