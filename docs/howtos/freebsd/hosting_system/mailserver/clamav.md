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
title: ClamAV
description: In diesem HowTo wird Schritt für Schritt die Installation von ClamAV für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# ClamAV

## Inhalt

* ClamAV 1.5.2
* `clamd`
* `freshclam`
* optionale Milter-Unterstützung

---

## Einleitung

Dieses HowTo beschreibt die Installation und Konfiguration von ClamAV auf FreeBSD 15+.

Der aktuelle FreeBSD-Port `security/clamav` steht im Quarterly-Zweig bei **1.5.2**. ClamAV bringt dabei den Scan-Daemon **`clamd`**, den Signatur-Updater **`freshclam`** und – wenn der Port mit der standardmäßig aktivierten Option **`MILTER=on`** gebaut wird – auch **`clamav-milter`** mit. Der Port ist ein **Regular Feature Release**; für konservativere Setups existiert zusätzlich `security/clamav-lts` mit **1.4.4**. ([FreshPorts][1])

---

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

Zusätzlich gilt für dieses HowTo:

* ClamAV wird hier als Virenscanner für Mail- und Dateiscans eingesetzt.
* Für die Signaturpflege wird `freshclam` verwendet.
* Falls ClamAV als Milter vor Postfix verwendet werden soll, muss Postfix bereits für Milter vorbereitet sein.
* Für produktive Mailsetups ist die Reihenfolge wichtig: zuerst Signaturen laden, dann `clamd` starten. `freshclam` ist genau das Werkzeug für die Datenbank-Updates. ([FreeBSD Manual Pages][2])

---

## Vorbereitungen

### DNS Records

Für dieses HowTo sind **keine zusätzlichen DNS-Records** erforderlich.

### Verzeichnisse / Dateien

Für dieses HowTo müssen vor der Installation **keine zusätzlichen Verzeichnisse manuell angelegt** werden.

Der Port legt die Laufzeitverzeichnisse `/var/db/clamav`, `/var/log/clamav` und `/var/run/clamav` bereits mit Eigentümer `clamav:clamav` an und installiert außerdem die Sample-Dateien `clamd.conf.sample`, `freshclam.conf.sample` und `clamav-milter.conf.sample`. ([FreshPorts][1])

### Gruppen / Benutzer / Passwörter

Für dieses HowTo müssen **keine zusätzlichen Systemgruppen, Systembenutzer oder Passwörter** manuell angelegt werden.

---

## Installation

### Wir installieren `security/clamav` und dessen Abhängigkeiten.

``` sh
install -d -m 0755 /var/db/ports/security_clamav
cat <<'EOF' > /var/db/ports/security_clamav/options
--8<-- "freebsd/ports/security_clamav/options"
EOF

portmaster -w -B -g -U --force-config security/clamav -n
```

Der Port bietet aktuell unter anderem die Optionen **`MILTER=on`**, **`UNRAR=on`**, **`ARC=on`** und **`ARJ=on`**. Für dieses HowTo bleibt der Standard-Port `security/clamav` die Basis. ([FreshPorts][1])

### Dienst in `rc.conf` eintragen

Der Dienst wird mittels `sysrc` in der `rc.conf` eingetragen und dadurch beim Systemstart automatisch gestartet.

``` sh
sysrc clamav_clamd_enable="YES"
sysrc clamav_freshclam_enable="YES"
```

Seit der Umstellung im Ports-Tree heißen die Dienste auf FreeBSD **mit Unterstrich** und nicht mehr mit Bindestrich. Das betrifft auch ClamAV. ([FreshPorts][1])

---

## Konfiguration

### Konfigurationsdatei `clamd.conf`

ClamAV liefert die Beispielkonfiguration bereits mit. Laut offizieller Dokumentation kannst du entweder die Sample-Datei kopieren oder `clamconf -g` zum Erzeugen verwenden. ([ClamAV Documentation][3])

``` sh
install -b -m 0644 /usr/local/etc/clamd.conf.sample /usr/local/etc/clamd.conf
cat <<'EOF' > /usr/local/etc/clamd.conf
--8<-- "freebsd/configs/usr/local/etc/clamd.conf"
EOF
```

### Konfigurationsdatei `freshclam.conf`

`freshclam` liest seine Einstellungen aus `freshclam.conf`. Genau dafür liefert ClamAV ebenfalls eine Sample-Datei mit. ([FreeBSD Manual Pages][2])

``` sh
install -b -m 0644 /usr/local/etc/freshclam.conf.sample /usr/local/etc/freshclam.conf
cat <<'EOF' > /usr/local/etc/freshclam.conf
--8<-- "freebsd/configs/usr/local/etc/freshclam.conf"
EOF
```

### Konfiguration prüfen

Eine klassische `configtest`-Subcommand-Struktur wie bei anderen Diensten gibt es hier nicht. Für ClamAV ist **`clamconf`** das passende Prüfwerkzeug: Es zeigt die Werte aus `clamd.conf` und `freshclam.conf` an und prüft deren Gültigkeit. ([ClamAV Documentation][4])

``` sh
clamconf
freshclam --verbose
```

---

## Datenbanken

Für dieses HowTo sind **keine Datenbanken** erforderlich.

---

## Zusatzsoftware

Mögliche Zusatzsoftware wird hier installiert und konfiguriert.

### Optionale Milter-Unterstützung

Der Port `security/clamav` bringt bei aktivierter Standardoption **`MILTER=on`** bereits **`clamav-milter`**, die zugehörige Manpage sowie die Sample-Datei `clamav-milter.conf.sample` mit. `clamav-milter` arbeitet direkt mit `clamd` zusammen und setzt deshalb einen funktionierenden `clamd`-Dienst voraus. ([FreshPorts][1])

``` sh
install -b -m 0644 /usr/local/etc/clamav-milter.conf.sample /usr/local/etc/clamav-milter.conf
cat <<'EOF' > /usr/local/etc/clamav-milter.conf
--8<-- "freebsd/configs/usr/local/etc/clamav-milter.conf"
EOF

sysrc clamav_milter_enable="YES"
```

### Zusatzsoftware Konfiguration prüfen

``` sh
clamconf
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

ClamAV kann nun gestartet werden.

Zuerst sollten die Signaturdaten geladen werden, danach werden die Dienste gestartet.

``` sh
freshclam --verbose
service clamav_freshclam start
service clamav_clamd start
```

Falls die Milter-Unterstützung aktiviert wurde:

``` sh
service clamav_milter start
```

Für spätere Änderungen:

``` sh
service clamav_freshclam restart
service clamav_clamd restart
service clamav_milter restart
```

---

## Referenzen

* FreshPorts: `security/clamav` ([FreshPorts][1])
* FreshPorts: `security/clamav-lts` ([FreshPorts][5])
* ClamAV Dokumentation: Configuration / Sample-Dateien / `clamconf` ([ClamAV Documentation][3])
* FreeBSD Manpage: `freshclam(1)` ([FreeBSD Manual Pages][2])
* FreeBSD Manpage: `clamd(8)` ([FreeBSD Manual Pages][6])
* ClamAV Dokumentation: `clamav-milter` benötigt `clamd` ([ClamAV Documentation][4])

[1]: https://www.freshports.org/security/clamav/?branch=2026Q1 "FreshPorts -- security/clamav: Open-source (GPL) anti-virus engine (Regular Feature Release)"
[2]: https://man.freebsd.org/cgi/man.cgi?query=freshclam&sektion=1 "freshclam(1)"
[3]: https://docs.clamav.net/manual/Usage/Configuration.html "Configuration - ClamAV Documentation"
[4]: https://docs.clamav.net/manual/Usage/Configuration.html "Configuration"
[5]: https://www.freshports.org/security/clamav-lts/?branch=2026Q1 "FreshPorts -- security/clamav-lts: Open-source (GPL) anti-virus engine (LTS Feature Release)"
[6]: https://man.freebsd.org/cgi/man.cgi?query=clamd&sektion=8 "clamd(8)"
