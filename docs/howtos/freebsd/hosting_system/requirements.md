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
lastmod: '2025-06-28'
title: Hosting System
description: In diesem HowTo werden step-by-step die Voraussetzungen für ein Hosting System auf Basis von FreeBSD 64Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# Voraussetzungen

Diese HowTos setzen ein wie in [Remote Installation](../remote_install/requirements.md) beschriebenes, installiertes und konfiguriertes FreeBSD Basissystem voraus.

## Einleitung

Unser Hosting System wird am Ende folgende Dienste umfassen.

- CertBot 4.2.0 (LetsEncrypt ACME API 2.0)
- OpenSSH 10.2.p1 (Public-Key-Auth)
- Unbound 1.24.2 (DNScrypt, DNS over TLS)
- PostgreSQL 17.5 (SSL, ZSTD)
- MySQL 8.0.44 (InnoDB, GTID)
- Apache 2.4.66 (MPM-Event, HTTP/2, mod_brotli)
- NGinx 1.28.0 (HTTP/2, HTTP/3, mod_brotli)
- PHP 8.4.17 (PHP-FPM, Composer, PEAR)
- NodeJS 24.13.0 (NPM, YARN)
- Dovecot 2.3.21 (IMAP only, Pigeonhole)
- PostfixAdmin 4.0.0 (PostgreSQL, Vacation)
- Postfix 3.10.6 (Dovecot-SASL, postscreen)
- OpenDKIM 2.10.3 (VBR, 2048 Bit RSA)
- OpenDMARC 1.4.2 (SPF2, FailureReports)
- SpamAssassin 4.0.2 (SpamAss-Milter)
- Amavisd 2.14.0 (PostGreSQL)
- ClamAV 1.5.1 (Milter)

Folgende Punkte sind in allen folgenden HowTos zu beachten.

- Alle Dienste werden mit einem möglichst minimalen und bewährten Funktionsumfang installiert.
- Alle Dienste werden mit einer möglichst sicheren und dennoch flexiblen Konfiguration versehen.
- Alle Konfigurationen sind selbstständig auf notwendige individuelle Anpassungen zu kontrollieren.
- Alle Benutzernamen werden als `__USERNAME__` dargestellt und sind selbstständig passend zu ersetzen.
- Alle Passworte werden als `__PASSWORD__` dargestellt und sind selbstständig durch sichere Passworte zu ersetzen.
- Die Domain des Servers lautet `example.com` und ist selbstständig durch die eigene Domain zu ersetzen.
- Der Hostname des Servers lautet `devnull` und ist selbstständig durch den eigenen Hostnamen zu ersetzen (FQDN=devnull.example.com).
- Es werden die FQDNs `devnull.example.com`, `mail.example.com` und `www.example.com` verwendet und sind selbstständig im DNS zu registrieren.
- Die primäre IPv4 Adresse des Systems wird als `__IPADDR4__` dargestellt und ist selbsttändig zu ersetzen.
- Die primäre IPv6 Adresse des Systems wird als `__IPADDR6__` dargestellt und ist selbsttändig zu ersetzen.
- Postfix und Dovecot teilen sich sowohl den FQDN `mail.example.com` als auch das SSL-Zertifikat.

## Vorbereitungen

!!! warning
    An diesem Punkt müssen wir uns entscheiden, ob wir die Pakete/Ports in Zukunft bequem als vorkompiliertes Binary-Paket per `pkg install <category/portname>` mit den Default-Optionen installieren wollen oder ob wir die Optionen und somit auch den Funktionsumfang beziehungsweise die Features unserer Pakete/Ports selbst bestimmen wollen.

In diesem HowTo werden wir uns für die zweite Variante entscheiden, da uns dies viele Probleme durch unnötige oder fehlende Features und Abhängigkeiten ersparen wird. Andererseits verlieren wir dadurch den Komfort von `pkg` bei der Installation und den Updates der Pakete/Ports. Ebenso müssen wir zwangsweise für alle Pakete/Ports die gewünschten Optionen manuell setzen und die Pakete/Ports auch selbst kompilieren.

Dieses Vorgehen ist deutlich zeitaufwendiger und erfordert auch etwas mehr Wissen über die jeweiligen Pakete/Ports und deren Features, dafür entschädigt es uns aber mit einem schlankeren, schnelleren und stabileren System und bietet uns gegebenenfalls nützliche/erforderliche zusätzliche Funktionen und Sicherheitsfeatures. Auch die potentielle Gefahr für Sicherheitslücken sinkt dadurch, da wir unnütze Pakete/Ports gar nicht erst als Abhängigkeiten mitinstallieren müssen.

Sofern noch nicht geschehen, deaktivieren wir also zuerst das Default-Repository von `pkg`, um versehentlichen Installationen von Binary-Paketen durch `pkg` vorzubeugen.

``` shell
mkdir -p /usr/local/etc/pkg/repos
sed -e 's|quarterly|latest|g' /etc/pkg/FreeBSD.conf > /usr/local/etc/pkg/repos/FreeBSD.conf
sed -e 's|\(enabled:\)[[:space:]]*yes|\1 no|g' -i '' /usr/local/etc/pkg/repos/FreeBSD.conf
```

Die von uns jeweils gewünschten Build-Optionen der Ports legen wir dabei mittels der `options`-Files des Portkonfigurationsframeworks `OptionsNG` fest.

Da wir unsere Nutzdaten weitestgehend unter `/data` ablegen werden, legen wir ein paar hierfür benötigte Verzeichnisse an, sofern nicht bereits geschehen.

``` shell
mkdir -p /data
```

## DNS Records

Für diese HowTos müssen zuvor folgende DNS-Records angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` dns-zone
example.com.                     IN  A       __IPADDR4__
example.com.                     IN  AAAA    __IPADDR6__

devnull.example.com.             IN  A       __IPADDR4__
devnull.example.com.             IN  AAAA    __IPADDR6__
```

## Voraussetzungen für den Abschnitt Security

Es müssen zuerst noch DNS-Records angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` dns-zone
example.com.                     IN  CAA     0 issue "letsencrypt.org"
example.com.                     IN  CAA     0 issuewild "letsencrypt.org"
```

## Voraussetzungen für den Abschnitt Datenbanken

Da wir unsere Nutzdaten weitestgehend unter `/data` ablegen werden, legen wir ein paar hierfür benötigte Verzeichnisse an, sofern nicht bereits geschehen.

``` shell
mkdir -p /data/db
```

## Voraussetzungen für den Abschnitt Webserver

Es müssen zuerst noch DNS-Records angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` dns-zone
www.example.com.                 IN  A       __IPADDR4__
www.example.com.                 IN  AAAA    __IPADDR6__
```

Da wir unsere Nutzdaten weitestgehend unter `/data` ablegen werden, legen wir ein paar hierfür benötigte Verzeichnisse an, sofern nicht bereits geschehen.

``` shell
mkdir -p /data/www
```

## Voraussetzungen für den Abschnitt Mailserver

Es müssen zuerst noch DNS-Records angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` dns-zone
example.com.                     IN  MX  10  mail.example.com.

mail.example.com.                IN  A       __IPADDR4__
mail.example.com.                IN  AAAA    __IPADDR6__

_imap._tcp.example.com.          IN  SRV     0 0 .
_imaps._tcp.example.com.         IN  SRV     1 993 mail.example.com.
_pop3._tcp.example.com.          IN  SRV     0 0 .
_pop3s._tcp.example.com.         IN  SRV     0 0 .
_submission._tcp.example.com.    IN  SRV     1 587 mail.example.com.
_submissions._tcp.example.com.   IN  SRV     1 465 mail.example.com.

example.com.                     IN  TXT     "v=spf1 a mx ptr ~all"

_dmarc.example.com.              IN  TXT     "v=DMARC1; p=none; sp=none; np=reject; adkim=s; aspf=s; fo=1; rua=mailto:postmaster@example.com!50m; ruf=mailto:postmaster@example.com!50m"
_report._dmarc.example.com.      IN  TXT     "v=DMARC1;"

_adsp._domainkey.example.com.    IN  TXT     "dkim=all"
```

Wir benötigen für unsere Nutzdaten einen eigenen Systembenutzer `vmail`, welchen wir nun anlegen, sofern nicht bereits geschehen.

``` shell
pw groupadd -n vmail -g 5000
pw useradd -n vmail -u 5000 -g vmail -c 'Virtual Mailuser' -d /nonexistent -s /usr/sbin/nologin -w no

pw groupadd -n vacation -g 65501
pw useradd -n vacation -u 65501 -g vacation -c 'Vacation Notice' -d /nonexistent -s /usr/sbin/nologin -w no
```

Da wir unsere Nutzdaten weitestgehend unter `/data` ablegen werden, legen wir ein paar hierfür benötigte Verzeichnisse an, sofern nicht bereits geschehen.

``` shell
mkdir -p /data/vmail
chmod 0750 /data/vmail
chown vmail:vmail /data/vmail
```

## Los geht es

Die einzelnen HowTos bauen aufeinander auf, daher sollten sie in der Reihenfolge von oben nach unten bis zum Ende abgearbeitet werden.
