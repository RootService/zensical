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
title: OpenDMARC
description: In diesem HowTo wird Schritt für Schritt die Installation von OpenDMARC für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# OpenDMARC

## Inhalt

- OpenDMARC 1.4.2
- DMARC-Milter für Postfix
- SPF-Unterstützung über `libspf2`
- FailureReports
- IgnoreHosts und PublicSuffixList

---

## Einleitung

Dieses HowTo beschreibt die Installation und Konfiguration von OpenDMARC auf FreeBSD 15+.

OpenDMARC wird in diesem Setup als **Milter** für Postfix verwendet. Der aktuelle FreeBSD-Port `mail/opendmarc` steht bei **1.4.2**, installiert ein rc.d-Skript `opendmarc` und liefert die Beispielkonfiguration `etc/mail/opendmarc.conf.sample` mit. OpenDMARC ist ein DMARC-Filter für milter-fähige MTAs und verarbeitet eingehende Nachrichten anhand von DMARC, DKIM- und SPF-Ergebnissen.

Wichtig ist dabei: OpenDMARC gehört im Mailfluss **hinter** Filter, die DKIM- und SPF-Ergebnisse bereitstellen. Die Upstream-Dokumentation nennt das für Postfix ausdrücklich so. Wenn du Unix-Sockets statt TCP verwendest, muss der Socket für den Postfix-Prozess sichtbar sein.

---

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

Zusätzlich gilt für dieses HowTo:

- Postfix ist bereits installiert und für Milter vorbereitet.
- OpenDKIM und SPF-Prüfung sind bereits eingerichtet oder werden an anderer Stelle im Mailfluss bereitgestellt.
- Für Unix-Sockets muss der Socket-Pfad für Postfix erreichbar sein.
- Für dieses HowTo wird `example.com` als Beispiel verwendet und muss entsprechend ersetzt werden.

---

## Vorbereitungen

### DNS Records

Für dieses HowTo sind **keine zusätzlichen DNS-Records** zwingend erforderlich.

Wenn du für deine eigene Domain zusätzlich eine DMARC-Policy veröffentlichen willst, geschieht das separat über einen TXT-Record unter `_dmarc.example.com`. Dieses HowTo richtet aber zunächst nur den **Prüfdienst** auf dem empfangenden Server ein.

### Gruppen / Benutzer / Passwörter

Für dieses HowTo müssen **keine zusätzlichen** Systemgruppen, Systembenutzer oder Passwörter angelegt werden.

``` sh
pw groupshow opendmarc >/dev/null 2>&1 || pw groupadd -n opendmarc -g 5119
id -u opendmarc >/dev/null 2>&1 || \
  pw useradd -n opendmarc -u 5119 -i 5119 -g opendkim -c 'OpenDMARC User' -d /nonexistent -s /usr/sbin/nologin -w no
pw groupmod opendmarc -m postfix
```

Das FreeBSD-rc-Skript verwendet standardmäßig den Benutzer und die Gruppe **`mailnull`**. ([GitHub][3])

### Verzeichnisse / Dateien

Für diese HowTos müssen zuvor folgende Verzeichnisse angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` sh
mkdir /var/spool/postfix/opendmarc
chown opendmarc:opendmarc /var/spool/postfix/opendmarc
chmod 770 /var/spool/postfix/opendmarc
```

Für diese HowTos müssen zuvor folgende Dateien angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` sh
```

---

## Installation

### Wir installieren `mail/opendmarc` und dessen Abhängigkeiten.

``` sh
mkdir -p /var/db/ports/databases_p5-DBI
cat <<'EOF' > /var/db/ports/databases_p5-DBI/options
--8<-- "freebsd/ports/databases_p5-DBI/options"
EOF

mkdir -p /var/db/ports/mail_opendmarc
cat <<'EOF' > /var/db/ports/mail_opendmarc/options
--8<-- "freebsd/ports/mail_opendmarc/options"
EOF

portmaster -w -B -g -U --force-config mail/opendmarc -n
```

Der Port hat aktuell keine Flavors. Relevante Portoptionen sind vor allem `DOCS` und `SPF`; mit aktivierter SPF-Option wird Unterstützung über `libspf2` eingebaut. Zusätzlich hängt der Port inzwischen an `public_suffix_list`, was für die Berechnung der Organizational Domain wichtig ist. ([FreshPorts][1])

### Dienst in `rc.conf` eintragen

Der Dienst wird mittels `sysrc` in der `rc.conf` eingetragen und dadurch beim Systemstart automatisch gestartet.

``` sh
sysrc opendmarc_enable="YES"
sysrc opendmarc_runas="opendmarc:opendmarc"
sysrc opendmarc_socketspec="unix:/var/spool/postfix/opendmarc/opendmarc.sock"
sysrc opendmarc_socketperms="0700"
```

---

## Konfiguration

### Konfigurationsdatei

Die Beispielkonfiguration wird vom Port unter `etc/mail/opendmarc.conf.sample` installiert. Für dieses Setup verwenden wir die produktive Datei unter `/usr/local/etc/mail/opendmarc.conf`. Außerdem sollte in der Konfiguration ein gültiger `PublicSuffixList`-Pfad gesetzt sein, weil OpenDMARC sonst die Organizational Domain nicht korrekt bestimmen kann. ([FreshPorts][1])

``` sh
cat <<'EOF' > /usr/local/etc/mail/opendmarc.conf
--8<-- "freebsd/configs/usr/local/etc/mail/opendmarc.conf"
EOF
```

### `IgnoreHosts` anlegen

`IgnoreHosts` ist in OpenDMARC der richtige Mechanismus, um lokale oder vertrauenswürdige Quellhosts von der DMARC-Prüfung auszunehmen. Laut `opendmarc.conf(5)` akzeptiert die Datei Hostnamen, IP-Adressen und CIDR-Ausdrücke. Wenn nichts gesetzt ist, wird standardmäßig nur `127.0.0.1` ignoriert. ([FreeBSD Manual Pages][2])

``` sh
cat <<'EOF' > /usr/local/etc/mail/ignorehosts
::1
127.0.0.1
fe80::/10
10.0.0.0/8
__IPADDR4__/32
__IPADDR6__/64
localhost
example.com
*.example.com
EOF

# 1. Get Default Interface
DEF_IF="$(route -n get -inet default | awk '/interface:/ {print $2}')"

# 2. Get IPv4 IP
IP4="$(ifconfig "$DEF_IF" inet | awk '/inet / && $2 !~ /^127\./ {print $2}' | head -n 1)"
[ -n "$IP4" ] && sed -e "s|__IPADDR4__|$IP4|g" -i '' /usr/local/etc/mail/ignorehosts

# 3. Get IPv6 IP
IP6="$(ifconfig "$DEF_IF" inet6 | awk '/inet6 / && $2 !~ /^fe80:/ && $2 !~ /^::1/ {print $2}' | head -n 1)"
[ -n "$IP6" ] && sed -e "s|__IPADDR6__|$IP6|g" -i '' /usr/local/etc/mail/ignorehosts
```

### Konfiguration prüfen

Vor dem ersten Start sollte die Konfiguration immer geprüft werden. `opendmarc -n` parst Konfigurationsdatei und Kommandozeilenoptionen, meldet Fehler und beendet sich danach wieder. Das ist der saubere Vorab-Check. Zusätzlich kann OpenDMARC im Testmodus mit `-t` komplette Nachrichten aus Dateien prüfen, ohne den Dienst zu starten. ([FreeBSD Manual Pages][3])

``` sh
opendmarc -n -c /usr/local/etc/mail/opendmarc.conf
service opendmarc start
sockstat -4 -6 -l | egrep 'opendmarc|milter'
```

---

## Datenbanken

Für dieses HowTo sind **keine Datenbanken** erforderlich.

OpenDMARC bringt zwar Werkzeuge und ein Schema für Reporting mit, dieses Basis-HowTo richtet aber nur den laufenden DMARC-Milter ein und noch keine Reporting-Datenbank. ([FreshPorts][1])

---

## Zusatzsoftware

Mögliche Zusatzsoftware wird hier installiert und konfiguriert.

Für dieses HowTo ist **keine zusätzliche Software** erforderlich.

Die Features **FailureReports** und SPF-Selbstvalidierung werden über die Konfiguration von OpenDMARC selbst gesteuert. `FailureReports` ist die aktuelle Option für RFC6591-konforme Failure Reports; `SPFSelfValidate` aktiviert einen SPF-Fallback, wenn im Header keine SPF-Ergebnisse vorhanden sind. ([FreeBSD Manual Pages][2])

---

## Aufräumen

Überflüssige oder temporäre Verzeichnisse und Dateien entsorgen.

### Zusatzsoftware Installation

Nicht erforderlich.

### Zusatzsoftware Konfiguration

Nicht erforderlich.

---

## Abschluss

OpenDMARC kann nun gestartet werden.

``` sh
service opendmarc start
```

Für spätere Änderungen:

``` sh
service opendmarc restart
```

Wenn nur die Konfigurationsdatei neu eingelesen werden soll, unterstützt OpenDMARC dafür laut Handbuch auch ein Konfigurations-Reload per `SIGUSR1`. In der Praxis ist für dieses Setup ein sauberer `restart` aber der robustere Standardweg. ([FreeBSD Manual Pages][3])

---

## Referenzen

* FreshPorts: `mail/opendmarc` ([FreshPorts][1])
* OpenDMARC README: Aktivierung und Postfix-Integration ([GitHub][4])
* `opendmarc(8)` ([FreeBSD Manual Pages][3])
* `opendmarc.conf(5)` ([FreeBSD Manual Pages][2])

[1]: https://www.freshports.org/mail/opendmarc/ "FreshPorts -- mail/opendmarc: DMARC library and milter implementation"
[2]: https://man.freebsd.org/cgi/man.cgi?manpath=FreeBSD+12.0-RELEASE+and+Ports&query=opendmarc.conf&sektion=5 "opendmarc.conf(5)"
[3]: https://man.freebsd.org/cgi/man.cgi?manpath=FreeBSD+12.0-RELEASE+and+Ports&query=opendmarc&sektion=8 "opendmarc(8)"
[4]: https://github.com/trusteddomainproject/OpenDMARC/blob/master/opendmarc/README "OpenDMARC/opendmarc/README at master · trusteddomainproject/OpenDMARC · GitHub"
