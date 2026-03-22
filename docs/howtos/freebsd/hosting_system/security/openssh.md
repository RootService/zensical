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
title: OpenSSH
description: In diesem HowTo wird Schritt für Schritt die Installation von OpenSSH für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# OpenSSH

## Inhalt

- OpenSSH Portable 10.2p1
- Ports-Version `security/openssh-portable`
- eigener Dienst `openssh`
- getrennt vom FreeBSD-Basisdienst `sshd`
- Host Keys für `rsa`, `ecdsa` und `ed25519`

---

## Einleitung

Dieses HowTo beschreibt die Installation und Konfiguration von OpenSSH Portable auf FreeBSD 15+.

In diesem HowTo wird bewusst die **Ports-Version** `security/openssh-portable` verwendet und nicht ausschließlich das OpenSSH aus dem FreeBSD-Basissystem.

---

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

---

## Vorbereitungen

### DNS Records

Für dieses HowTo sind in der Regel **keine zusätzlichen DNS-Records** erforderlich.

Optional sollte der Servername bereits sauber per DNS auflösbar sein, damit SSH-Clients Hostnamen statt IP-Adressen verwenden können.

``` dns-zone
server.example.com.      IN  A       __IPADDR4__
server.example.com.      IN  AAAA    __IPADDR6__
````

### Verzeichnisse / Dateien

Für dieses HowTo müssen vor der Installation in der Regel **keine zusätzlichen Verzeichnisse oder Dateien manuell angelegt** werden.

Das Konfigurationsverzeichnis `/usr/local/etc/ssh` sowie die mitgelieferte Beispieldatei `sshd_config.sample` werden durch den Port bereitgestellt.

### Gruppen / Benutzer / Passwörter

Für dieses HowTo sind **keine zusätzlichen Systemgruppen, Systembenutzer oder Passwörter** erforderlich.

---

## Installation

### Wir installieren `security/openssh-portable` und dessen Abhängigkeiten.

```shell
install -d -m 0755 /var/db/ports/dns_ldns
cat <<'EOF' > /var/db/ports/dns_ldns/options
--8<-- "freebsd/ports/dns_ldns/options"
EOF

install -d -m 0755 /var/db/ports/security_openssh-portable
cat <<'EOF' > /var/db/ports/security_openssh-portable/options
--8<-- "freebsd/ports/security_openssh-portable/options"
EOF

portmaster -w -B -g -U --force-config security/openssh-portable -n
```

### Dienst in `rc.conf` eintragen

Der Ports-Dienst wird mittels `sysrc` in der `rc.conf` eingetragen und dadurch beim Systemstart automatisch gestartet.

Wichtig ist hier die saubere Trennung zwischen **Ports-OpenSSH** und dem **OpenSSH aus dem FreeBSD-Basissystem**.

```sh
sysrc sshd_enable=NO
sysrc openssh_enable=YES
```

---

## Konfiguration

### Konfigurationsdatei

Die Ports-Version verwendet ihr eigenes Konfigurationsverzeichnis unter `/usr/local/etc/ssh`. Dort legen wir die Konfigurationsdatei `sshd_config` ab.

```sh
install -b -m 0644 /usr/local/etc/ssh/sshd_config.sample /usr/local/etc/ssh/sshd_config
cat <<'EOF' > /usr/local/etc/ssh/sshd_config
--8<-- "freebsd/configs/usr/local/etc/ssh/sshd_config"
EOF
```

### Host Keys

Falls bereits alte oder unerwünschte Host Keys vorhanden sind, können diese entfernt und anschließend gezielt neu erzeugt werden.

```sh
rm -f /usr/local/etc/ssh/ssh_host_rsa_key /usr/local/etc/ssh/ssh_host_rsa_key.pub
rm -f /usr/local/etc/ssh/ssh_host_ecdsa_key /usr/local/etc/ssh/ssh_host_ecdsa_key.pub
rm -f /usr/local/etc/ssh/ssh_host_ed25519_key /usr/local/etc/ssh/ssh_host_ed25519_key.pub

ssh-keygen -q -t rsa -b 4096 -f "/usr/local/etc/ssh/ssh_host_rsa_key" -N ""
ssh-keygen -l -f "/usr/local/etc/ssh/ssh_host_rsa_key.pub"

ssh-keygen -q -t ecdsa -b 384 -f "/usr/local/etc/ssh/ssh_host_ecdsa_key" -N ""
ssh-keygen -l -f "/usr/local/etc/ssh/ssh_host_ecdsa_key.pub"

ssh-keygen -q -t ed25519 -f "/usr/local/etc/ssh/ssh_host_ed25519_key" -N ""
ssh-keygen -l -f "/usr/local/etc/ssh/ssh_host_ed25519_key.pub"
```

### Konfiguration prüfen

Vor dem ersten Start sollte die Konfiguration immer geprüft werden.

```sh
service openssh configtest
/usr/local/sbin/sshd -t
```

---

## Datenbanken

Für dieses HowTo sind **keine Datenbanken** erforderlich.

---

## Zusatzsoftware

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

OpenSSH kann nun gestartet werden.

```sh
service openssh start
```

Für spätere Änderungen:

```sh
service openssh reload
service openssh restart
```

---

## Referenzen

* [FreeBSD Handbook: Configuration Files](https://docs.freebsd.org/en/books/handbook/config/)
* [FreeBSD Handbook: Installing Applications – Ports](https://docs.freebsd.org/en/books/handbook/ports/)
* [FreshPorts: security/openssh-portable](https://www.freshports.org/security/openssh-portable/)
* [OpenSSH Portable](https://www.openssh.com/portable.html)
* [OpenSSH Release Notes](https://www.openssh.com/releasenotes.html)
* [OpenBSD Manual: ssh-keygen(1)](https://man.openbsd.org/ssh-keygen.1)
* [OpenBSD Manual: sshd_config(5)](https://man.openbsd.org/sshd_config)
