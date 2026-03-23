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
title: Dovecot
description: In diesem HowTo wird Schritt für Schritt die Installation von Dovecot für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# Dovecot

## Inhalt

* Dovecot 2.3.21.1
* Dovecot Pigeonhole 0.5.21.1
* IMAPS, LMTP, Sieve/ManageSieve
* virtuelle Mailuser mit PostgreSQL-Anbindung
* Master User und Quota-Warning-Script ([FreshPorts][1])

---

## Einleitung

Dieses HowTo beschreibt die Installation und Konfiguration von Dovecot auf FreeBSD 15+.

Dovecot dient hier als Backend für **IMAP/IMAPS**, **LMTP** und virtuelle Mailuser. Für Sieve und ManageSieve wird **Dovecot Pigeonhole** verwendet. Da dieses Setup PostgreSQL für `passdb`, `userdb` und weitere SQL-Lookups nutzt, ist auf FreeBSD der **`pgsql`-Flavor** von `mail/dovecot` und `mail/dovecot-pigeonhole` die richtige Basis. Dovecot dokumentiert SQL ausdrücklich als üblichen Weg für virtuelle Benutzer, und Pigeonhole ergänzt Dovecot um **Sieve** und **ManageSieve**. ([FreshPorts][1])

---

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

Zusätzlich wird vorausgesetzt:

* PostgreSQL ist bereits installiert und erreichbar.
* Der Systembenutzer `vmail` mit UID/GID 5000 existiert bereits.
* Die TLS-Zertifikate für `mail.example.com` existieren bereits.
* Postfix ist für die SASL-/LMTP-Sockets unter `/var/spool/postfix/private/` vorbereitet.

---

## Vorbereitungen

### DNS Records

Für dieses HowTo müssen zuvor folgende DNS-Records angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` dns-zone
mail.example.com.       IN  A       __IPADDR4__
mail.example.com.       IN  AAAA    __IPADDR6__
```

### Verzeichnisse / Dateien

Für dieses HowTo müssen zuvor folgende Verzeichnisse angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` sh
install -d -m 0755 -o vmail /var/log/dovecot
```

Für diese HowTos müssen zuvor folgende Dateien angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` sh
install -b -m 0640 -o vmail -g vmail /dev/null /var/log/dovecot/quota-warnings.log
install -b -m 0750 -g vmail /dev/null /usr/local/bin/dovecot-quota-warning.sh
install -b -m 0640 /dev/null /usr/local/etc/dovecot/dovecot-master-users
```

### Gruppen / Benutzer / Passwörter

Für dieses HowTo müssen **keine zusätzlichen** Systemgruppen oder Systembenutzer angelegt werden.

Vorausgesetzt wird der bereits vorhandene Systembenutzer `vmail` mit UID/GID 5000.

``` sh
pw groupshow vmail
id vmail
```

Für dieses HowTo wird zusätzlich ein Passwort für den Dovecot-Master-User erzeugt und unter `/var/db/passwords/user_dovecot_superuser` gespeichert.

``` sh
install -b -m 0640 /dev/null /var/db/passwords/user_dovecot_superuser
```

---

## Installation

### Wir installieren `mail/dovecot@pgsql` und dessen Abhängigkeiten.

``` sh
install -d -m 0755 /var/db/ports/textproc_libexttextcat
cat <<'EOF' > /var/db/ports/textproc_libexttextcat/options
--8<-- "freebsd/ports/textproc_libexttextcat/options"
EOF

install -d -m 0755 /var/db/ports/mail_dovecot
cat <<'EOF' > /var/db/ports/mail_dovecot/options
--8<-- "freebsd/ports/mail_dovecot/options"
EOF

portmaster -w -B -g -U --force-config mail/dovecot@pgsql -n
```

### Wir installieren `mail/dovecot-pigeonhole@pgsql` und dessen Abhängigkeiten.

``` sh
install -d -m 0755 /var/db/ports/mail_dovecot-pigeonhole
cat <<'EOF' > /var/db/ports/mail_dovecot-pigeonhole/options
--8<-- "freebsd/ports/mail_dovecot-pigeonhole/options"
EOF

portmaster -w -B -g -U --force-config mail/dovecot-pigeonhole@pgsql -n
```

Die Ports und Flavors passen genau zu diesem Setup: `mail/dovecot` liefert den rc.d-Dienst `dovecot`, `mail/dovecot-pigeonhole` ergänzt die Sieve-/ManageSieve-Funktionen, und beide Ports bieten einen `pgsql`-Flavor für PostgreSQL-basierte Installationen. ([FreshPorts][1])

### Dienst in `rc.conf` eintragen

Der Dienst wird mittels `sysrc` in der `rc.conf` eingetragen und dadurch beim Systemstart automatisch gestartet.

``` sh
sysrc dovecot_enable=YES
```

---

## Konfiguration

### Konfigurationsdateien

``` sh
install -b -m 0644 /dev/null /usr/local/etc/dovecot/dovecot.conf
cat <<'EOF' > /usr/local/etc/dovecot/dovecot.conf
--8<-- "freebsd/configs/usr/local/etc/dovecot/dovecot.conf"
EOF

install -b -m 0640 /dev/null /usr/local/etc/dovecot/dovecot-pgsql.conf
cat <<'EOF' > /usr/local/etc/dovecot/dovecot-pgsql.conf
--8<-- "freebsd/configs/usr/local/etc/dovecot/dovecot-pgsql.conf"
EOF

install -b -m 0640 /dev/null /usr/local/etc/dovecot/dovecot-dict-quota.conf
cat <<'EOF' > /usr/local/etc/dovecot/dovecot-dict-quota.conf
--8<-- "freebsd/configs/usr/local/etc/dovecot/dovecot-dict-quota.conf"
EOF

install -b -m 0640 /dev/null /usr/local/etc/dovecot/dovecot-last-login.conf
cat <<'EOF' > /usr/local/etc/dovecot/dovecot-last-login.conf
--8<-- "freebsd/configs/usr/local/etc/dovecot/dovecot-last-login.conf"
EOF

install -b -m 0640 /dev/null /usr/local/etc/dovecot/dovecot-share-folder.conf
cat <<'EOF' > /usr/local/etc/dovecot/dovecot-share-folder.conf
--8<-- "freebsd/configs/usr/local/etc/dovecot/dovecot-share-folder.conf"
EOF

install -b -m 0640 /dev/null /usr/local/etc/dovecot/dovecot-used-quota.conf
cat <<'EOF' > /usr/local/etc/dovecot/dovecot-used-quota.conf
--8<-- "freebsd/configs/usr/local/etc/dovecot/dovecot-used-quota.conf"
EOF
```

Dovecot liest seine Konfiguration aus `dovecot.conf`; für virtuelle Benutzer sind SQL-basierte `passdb`- und `userdb`-Lookups ein üblicher Aufbau. `doveconf` ist das vorgesehene Werkzeug, um die tatsächlich geparste Konfiguration auszugeben. ([Dovecot Pro][2])

### Platzhalter in den Konfigurationsdateien ersetzen

``` sh
# Standard-Interface ermitteln
DEF_IF="$(route -n get -inet default | awk '/interface:/ {print $2}')"

# Primäre IPv4-Adresse ermitteln
IP4="$(ifconfig "$DEF_IF" inet | awk '/inet / && $2 !~ /^127\./ {print $2}' | head -n 1)"
[ -n "$IP4" ] && sed -e "s|__IPADDR4__|$IP4|g" -i '' /usr/local/etc/dovecot/*.conf

# Primäre globale IPv6-Adresse ermitteln
IP6="$(ifconfig "$DEF_IF" inet6 | awk '/inet6 / && $2 !~ /^fe80:/ && $2 !~ /^::1/ {print $2}' | head -n 1)"
[ -n "$IP6" ] && sed -e "s|__IPADDR6__|$IP6|g" -i '' /usr/local/etc/dovecot/*.conf

# Passwort für PostgreSQL-User postfix einsetzen
cat /var/db/passwords/user_postgresql_postfix | xargs -I % \
  sed -e "s|__PASSWORD_POSTFIX__|%|g" -i '' /usr/local/etc/dovecot/*.conf

# Passwort anzeigen
cat /var/db/passwords/user_postgresql_postfix
```

### Quota-Warning-Script einrichten

``` sh
cat <<'EOF' > /usr/local/bin/dovecot-quota-warning.sh
--8<-- "freebsd/configs/usr/local/bin/dovecot-quota-warning.sh"
EOF

install -d -m 0755 -o vmail /var/log/dovecot
install -b -m 0640 -o vmail -g vmail /dev/null /var/log/dovecot/quota-warnings.log
```

### Master User einrichten

``` sh
cat <<'EOF' > /usr/local/etc/dovecot/dovecot-master-users
--8<-- "freebsd/configs/usr/local/etc/dovecot/dovecot-master-users"
EOF

# Passwort für den Master-User "superuser" erzeugen und
# in /var/db/passwords/user_dovecot_superuser speichern
install -b -m 0640 /dev/null /var/db/passwords/user_dovecot_superuser
openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | \
  cut -c 2-17 | tee /var/db/passwords/user_dovecot_superuser | xargs -I % \
  doveadm pw -s SSHA512 -p % | xargs -I % echo "superuser:%" | \
  tee /usr/local/etc/dovecot/dovecot-master-users
```

### Konfiguration prüfen

Vor dem ersten Start sollte die effektive Konfiguration geprüft werden. Dovecot empfiehlt dafür `doveconf`, weil damit sichtbar wird, was der Dienst nach dem Parsen der Konfiguration tatsächlich verwendet. ([Dovecot Pro][2])

``` sh
doveconf -n
service dovecot start
sockstat -4 -6 -l | egrep 'dovecot|imap|lmtp|sieve'
```

---

## Datenbanken

### Virtuelle Mailuser anlegen

Bei virtuellen Benutzern sind SQL-Backends üblich. Dovecot nutzt dabei typischerweise SQL für `passdb` und `userdb`; die Datenbank liefert dabei unter anderem Benutzername, Passwort, UID, GID und Mailpfad. Genau dafür ist das folgende Hilfsscript in diesem Setup vorgesehen. ([Dovecot][3])

``` sh
install -b -m 0755 /dev/null /usr/local/etc/dovecot/create_mailuser.sh
cat <<'EOF' > /usr/local/etc/dovecot/create_mailuser.sh
--8<-- "freebsd/configs/usr/local/etc/dovecot/create_mailuser.sh"
EOF
```

Beispiel zum Anlegen eines neuen Mailusers:

``` sh
/usr/local/etc/dovecot/create_mailuser.sh admin@example.com
```

Das Löschen von Mailusern ist in dieser Fassung weiterhin **nicht** implementiert.

---

## Zusatzsoftware

Mögliche Zusatzsoftware wird hier installiert und konfiguriert.

Für dieses HowTo ist **keine zusätzliche Software** erforderlich.

Pigeonhole ist bereits Bestandteil der Installation und ergänzt Dovecot um **Sieve** und **ManageSieve**. ManageSieve wird verwendet, um die Sieve-Skripte der Benutzer zu verwalten; der Interpreter selbst arbeitet mit Dovecots LDA und LMTP zusammen. ([Dovecot][4])

---

## Aufräumen

Überflüssige oder temporäre Verzeichnisse und Dateien entsorgen.

### Zusatzsoftware Installation

Nicht erforderlich.

### Zusatzsoftware Konfiguration

Nicht erforderlich.

---

## Abschluss

Dovecot kann nun gestartet werden.

``` sh
service dovecot start
```

Für spätere Änderungen:

``` sh
service dovecot reload
service dovecot restart
```

---

## Referenzen

* FreshPorts: `mail/dovecot`
* FreshPorts: `mail/dovecot-pigeonhole`
* Dovecot CE: Virtual Users
* Dovecot CE: SQL Authentication
* Dovecot CE: Pigeonhole / Sieve
* Dovecot CE: ManageSieve
* Dovecot CE / Pro: `doveconf(1)` und Konfigurationsprüfung

[1]: https://www.freshports.org/mail/dovecot "FreshPorts -- mail/dovecot: Secure, fast and powerful IMAP and POP3 server"
[2]: https://doc.dovecotpro.com/main/installation/getting_started.html "Getting Started | Dovecot Pro"
[3]: https://doc.dovecot.org/2.3/configuration_manual/authentication/sql/ "SQL — Dovecot documentation"
[4]: https://doc.dovecot.org/2.3/configuration_manual/sieve/pigeonhole_sieve_interpreter/ "Pigeonhole Sieve Interpreter"
