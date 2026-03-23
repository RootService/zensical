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
title: Unbound
description: In diesem HowTo wird Schritt für Schritt die Installation von Unbound für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# Unbound

## Inhalt

* Unbound 1.24.2
* validierender rekursiver Resolver
* DNSSEC
* optionale Funktionen für DNSCrypt und TLS-Upstream
* Ports-Version `dns/unbound`

Aktueller Portstand in FreeBSD Ports: `unbound-1.24.2`. Die Ports-Version bringt den eigenen Dienst `unbound` mit; `local_unbound` aus dem Basissystem ist laut FreeBSD-Handbook nur als lokaler caching/forwarding Resolver gedacht. ([FreshPorts][2])

---

## Einleitung

Dieses HowTo beschreibt die Installation und Konfiguration von Unbound auf FreeBSD 15+.

Dieses HowTo verwendet bewusst **Unbound aus den FreeBSD Ports** als **validierenden rekursiven Resolver**. Das ist für Resolver-Dienste über die lokale Maschine hinaus die richtige Basis: Das FreeBSD Handbook empfiehlt für diesen Fall ausdrücklich `dns/unbound` statt `local_unbound` aus dem Basissystem. Die aktuelle Ports-Version basiert auf **Unbound 1.24.2**. ([FreeBSD Dokumentation][3])

Wichtig ist dabei: Dieses HowTo aktiviert **weder TLS-Upstream noch DNSCrypt automatisch**. In der Unbound-Dokumentation ist `tls-upstream` standardmäßig `no`; ebenso ist `dnscrypt-enable` standardmäßig `no`. Die FreeBSD-Portoptionen können DNSCrypt-Unterstützung zwar einkompilieren, aktiv wird sie dadurch aber noch nicht. ([unbound.docs.nlnetlabs.nl][4])

---

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

Zusätzlich sollte bereits klar sein, aus welchen Netzen der Resolver Anfragen annehmen soll und ob er nur lokal oder für das gesamte Netz betrieben wird. Für einen netzweit erreichbaren Resolver ist die Ports-Version vorgesehen, nicht `local_unbound`. ([FreeBSD Dokumentation][3])

---

## Vorbereitungen

### DNS Records

Für dieses HowTo sind **keine DNS-Records** erforderlich.

Unbound arbeitet hier als rekursiver Resolver und nicht als autoritativer Nameserver für eine eigene Zone.

### Verzeichnisse / Dateien

Für dieses HowTo müssen vor der Installation **keine zusätzlichen Verzeichnisse oder Dateien manuell angelegt** werden.

Die Ports-Installation bringt das Konfigurationsverzeichnis `/usr/local/etc/unbound` sowie den Dienst `unbound` selbst mit. ([FreshPorts][2])

### Gruppen / Benutzer / Passwörter

Für dieses HowTo müssen **keine zusätzlichen Systemgruppen, Systembenutzer oder Passwörter manuell angelegt** werden.

---

## Installation

### Wir installieren `dns/unbound` und dessen Abhängigkeiten.

``` sh
install -d -m 0755 /var/db/ports/security_libsodium
cat <<'EOF' > /var/db/ports/security_libsodium/options
--8<-- "freebsd/ports/security_libsodium/options"
EOF

install -d -m 0755 /var/db/ports/dns_unbound
cat <<'EOF' > /var/db/ports/dns_unbound/options
--8<-- "freebsd/ports/dns_unbound/options"
EOF

portmaster -w -B -g -U --force-config dns/unbound -n
```

### Dienst in `rc.conf` eintragen

Anschließend deaktivieren wir `local_unbound` aus dem Basissystem und aktivieren die Ports-Version `unbound`. Genau diese Trennung ist auf FreeBSD für Resolver-Dienste über die lokale Maschine hinaus vorgesehen. Das Ports-`rc.d`-Skript unterstützt außerdem explizit `unbound_enable` und optional `unbound_config`. ([FreeBSD Dokumentation][3])

``` sh
sysrc local_unbound_enable=NO
sysrc unbound_enable=YES
sysrc unbound_config="/usr/local/etc/unbound/unbound.conf"
```

---

## Konfiguration

### Konfigurationsdatei

Die Konfigurationsdatei wird unter `/usr/local/etc/unbound/unbound.conf` abgelegt.

``` sh
install -b -m 0644 /dev/null /usr/local/etc/unbound/unbound.conf
cat <<'EOF' > /usr/local/etc/unbound/unbound.conf
--8<-- "freebsd/configs/usr/local/etc/unbound/unbound.conf"
EOF
```

### Root Hints

Unbound kann ohne separate `root.hints`-Datei mit **eingebauten Root Hints** arbeiten. Wenn deine Konfiguration aber ausdrücklich `root-hints:` auf eine Datei setzt, musst du diese Datei natürlich auch bereitstellen. Laut `unbound.conf(5)` ist der Standard **builtin hints**, eine separate Datei bleibt aber **gute Praxis**. ([unbound.docs.nlnetlabs.nl][4])

Wenn deine `unbound.conf` eine Root-Hints-Datei verwendet:

``` sh
fetch -o "/usr/local/etc/unbound/root.hints" "https://www.internic.net/domain/named.root"
chown root:wheel /usr/local/etc/unbound/root.hints
chmod 0644 /usr/local/etc/unbound/root.hints
```

### DNSSEC Trust Anchor

Für DNSSEC ist `unbound-anchor` der richtige Weg. `unbound-anchor(8)` beschreibt genau dafür die Datei `root.key`; `auto-trust-anchor-file` erwartet außerdem eine Datei, die vom laufenden Unbound-Prozess gelesen und geschrieben werden kann. Wichtig: Das Ports-`rc.d`-Skript ruft vor dem Start selbst bereits `unbound-anchor` auf. Ein manueller Vorab-Lauf ist daher nur dann nötig, wenn du die Trust-Anchor-Datei **bewusst vor dem ersten Start** initialisieren willst. ([unbound.docs.nlnetlabs.nl][5])

Optional vor dem ersten Start:

``` sh
su -m unbound -c 'unbound-anchor -a "/usr/local/etc/unbound/root.key"'
```

### Remote Control nur bei Bedarf

`unbound-control` funktioniert nur dann sinnvoll, wenn deine `unbound.conf` auch wirklich einen `remote-control:`-Block mit `control-enable: yes` enthält. Standardmäßig ist `control-enable` **no**. Die Schlüssel und Zertifikate dafür werden mit `unbound-control-setup` erzeugt. ([unbound.docs.nlnetlabs.nl][4])

Wenn du `remote-control` aktiv nutzt:

``` sh
su -m unbound -c 'unbound-control-setup -d /usr/local/etc/unbound'
```

### Konfiguration prüfen

Vor dem ersten Start sollte die Konfiguration immer geprüft werden. `unbound-checkconf` ist genau dafür vorgesehen. Das Ports-`rc.d`-Skript prüft die Konfiguration zusätzlich ebenfalls vor dem Start und vor `reload`. ([unbound.docs.nlnetlabs.nl][4])

``` sh
unbound-checkconf /usr/local/etc/unbound/unbound.conf
```

---

## Datenbanken

Für dieses HowTo sind **keine Datenbanken** erforderlich.

---

## Zusatzsoftware

Mögliche Zusatzsoftware wird hier installiert und konfiguriert.

### DNSCrypt / TLS-Upstream

Nicht Bestandteil dieses HowTos.

Die Ports-Version kann DNSCrypt-Unterstützung enthalten, und Unbound kennt Optionen für TLS-Upstream, aber beides ist laut aktueller Dokumentation standardmäßig **nicht aktiviert** und braucht zusätzliche Konfiguration. DNSCrypt benötigt außerdem eigene Schlüssel- und Zertifikatsdateien; diese erzeugt Unbound nicht automatisch für dich. ([FreshPorts][2])

---

## Aufräumen

Überflüssige oder temporäre Verzeichnisse und Dateien entsorgen.

### Zusatzsoftware Installation

Nicht erforderlich.

### Zusatzsoftware Konfiguration

Nicht erforderlich.

---

## Abschluss

Falls `local_unbound` aus dem Basissystem aktuell noch läuft, stoppen wir es einmalig. Danach starten wir die Ports-Version von Unbound.

``` sh
service local_unbound onestatus >/dev/null 2>&1 && service local_unbound onestop
service unbound start
```

Für spätere Änderungen:

``` sh
service unbound reload
service unbound restart
```

Das Ports-`rc.d`-Skript unterstützt `reload` explizit. Beim Start führt es außerdem selbst `unbound-anchor` und `unbound-checkconf` aus. ([GitHub][1])

---

## Referenzen

* FreeBSD Handbook – Hinweis zu `local_unbound` und `dns/unbound`. ([FreeBSD Documentation][6])
* FreshPorts – `dns/unbound`, aktueller Portstand, Default-Optionen und Dienstskript. ([FreshPorts][2])
* NLnet Labs – `unbound.conf(5)` zu `root-hints`, `auto-trust-anchor-file`, `tls-upstream`, `control-enable` und DNSCrypt. ([unbound.docs.nlnetlabs.nl][4])
* NLnet Labs – `unbound-anchor(8)` zu `root.key`, Default-Pfaden und Initialisierung des Trust Anchors. ([unbound.docs.nlnetlabs.nl][7])
* FreeBSD Ports `rc.d`-Skript – `unbound_enable`, `unbound_config`, automatischer `unbound-anchor`-Lauf beim Start und unterstütztes `reload`. ([GitHub][1])

[1]: https://github.com/freebsd/freebsd-ports/blob/main/dns/unbound/files/unbound.in "freebsd-ports/dns/unbound/files/unbound.in at main · freebsd/freebsd-ports · GitHub"
[2]: https://www.freshports.org/dns/unbound/ "FreshPorts -- dns/unbound: Validating, recursive, and caching DNS resolver"
[3]: https://docs.freebsd.org/en/books/handbook/book/ "FreeBSD Handbook | FreeBSD Documentation Portal"
[4]: https://unbound.docs.nlnetlabs.nl/en/latest/manpages/unbound.conf.html "unbound.conf(5) — Unbound 1.24.2 documentation"
[5]: https://unbound.docs.nlnetlabs.nl/en/latest/manpages/unbound-anchor.html "unbound-anchor(8) — Unbound 1.24.2 documentation"
[6]: https://docs.freebsd.org/en/books/handbook/book/ "FreeBSD Handbook | FreeBSD Documentation Portal"
[7]: https://unbound.docs.nlnetlabs.nl/en/latest/manpages/unbound-anchor.html "unbound-anchor(8) — Unbound 1.24.2 documentation"
