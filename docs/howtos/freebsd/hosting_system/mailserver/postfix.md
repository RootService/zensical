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
title: Postfix
description: In diesem HowTo wird Schritt für Schritt die Installation des Postfix Mailservers für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# Postfix

## Inhalt

* Postfix 3.10.6
* Dovecot-SASL
* postscreen
* PostgreSQL-Lookup-Maps
* Python-SPF-Engine 3.1.0

---

## Einleitung

Dieses HowTo beschreibt die Installation und Konfiguration von Postfix auf FreeBSD 15+.

Postfix wird in diesem Setup als SMTP-Server mit **Dovecot-SASL**, **postscreen** und **PostgreSQL-Lookup-Maps** betrieben. Der aktuelle FreeBSD-Port `mail/postfix` steht auf **3.10.6** und bietet unter anderem den Flavor **`pgsql`**, der für dieses Setup die richtige Basis ist. `postscreen` ist der vorgelagerte SMTP-Schutzdienst für eingehende Verbindungen, und Postfix unterstützt Dovecot-SASL offiziell für SMTP AUTH. Für SQL-Maps gibt es mit `pgsql` den passenden PostgreSQL-Backend-Typ. ([FreshPorts][1])

Zusätzlich wird in diesem HowTo die **Python-SPF-Engine** verwendet. Dieses Projekt stellt sowohl einen **Postfix-Policy-Service** als auch einen **Milter** für SPF-Prüfungen bereit. Auf FreeBSD wird dafür der Port `mail/py-spf-engine` in Version **3.1.0** verwendet. ([FreshPorts][2])

---

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

Zusätzlich wird vorausgesetzt:

* PostgreSQL ist bereits installiert und die Datenbankzugänge für den Benutzer `postfix` existieren bereits.
* Dovecot ist bereits installiert und für **SASL** sowie **LMTP** vorbereitet.
* Die Dovecot-Sockets unter `/var/spool/postfix/private/` sind vorgesehen.
* Die TLS-Zertifikate für `mail.example.com` existieren bereits.
* Der Server soll virtuelle Domains und Mailboxen über PostgreSQL-Lookups bedienen.

Für Dovecot-SASL und Dovecot-LMTP ist genau dieser Aufbau üblich: Postfix greift auf Unix-Sockets innerhalb von `/var/spool/postfix/private/` zu, weil der Zugriff von Postfix auf dieses Verzeichnis begrenzt ist. ([doc.dovecot.org][3])

---

## Vorbereitungen

### DNS Records

Für dieses HowTo müssen zuvor folgende DNS-Records angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` dns-zone
example.com.            IN  MX      10 mail.example.com.
mail.example.com.       IN  A       __IPADDR4__
mail.example.com.       IN  AAAA    __IPADDR6__
```

### Verzeichnisse / Dateien

Für dieses HowTo müssen zuvor folgende Verzeichnisse angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` sh
install -d -m 0755 /usr/local/etc/mail
install -d -m 0755 -g postfix /usr/local/etc/postfix/pgsql
```

Für diese HowTos müssen zuvor folgende Dateien angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` sh
install -b -m 0640 -g postfix /etc/mail/aliases /usr/local/etc/postfix/aliases
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/postscreen_access.cidr
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/postscreen_whitelist.cidr
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/body_checks.pcre
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/header_checks.pcre
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/command_filter.pcre
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/helo_access.pcre
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/recipient_checks.pcre
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/sender_access.pcre
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/submission_header_checks.pcre
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/postscreen_dnsbl_reply
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/dnsbl_reply_map
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/mx_access
```

### Gruppen / Benutzer / Passwörter

Für dieses HowTo sind keine zusätzlichen Systemgruppen oder Systembenutzer erforderlich.

Für dieses HowTo muss jedoch das Passwort für den PostgreSQL-Benutzer `postfix` bereits vorhanden sein, da die `pgsql:`-Maps in den Konfigurationsdateien darauf zugreifen.

``` sh
# Passwort für PostgreSQL-Benutzer "postfix" prüfen
cat /var/db/passwords/user_postgresql_postfix
```

---

## Installation

### Wir installieren `mail/postfix@pgsql` und dessen Abhängigkeiten.

``` sh
install -d -m 0755 /var/db/ports/mail_postfix
cat <<'EOF' > /var/db/ports/mail_postfix/options
--8<-- "freebsd/ports/mail_postfix/options"
EOF

portmaster -w -B -g -U --force-config mail/postfix@pgsql -n
```

### Dienst in `rc.conf` eintragen

Der Dienst wird mittels `sysrc` in der `rc.conf` eingetragen und dadurch beim Systemstart automatisch gestartet.

``` sh
sysrc postfix_enable=YES
```

### Mailwrapper auf Postfix umstellen

``` sh
install -d -m 0755 /usr/local/etc/mail
install -b -m 0644 /usr/local/share/postfix/mailer.conf.postfix /usr/local/etc/mail/mailer.conf
```

Das FreeBSD-Handbook beschreibt für Postfix genau diesen Schritt mit `/usr/local/etc/mail/mailer.conf`, damit die Mailwrapper-Kommandos sauber auf Postfix zeigen. ([docs.freebsd.org][4])

---

## Konfiguration

### Konfigurationsdatei `main.cf`

``` sh
install -b -m 0644 -g postfix /dev/null /usr/local/etc/postfix/main.cf
cat <<'EOF' > /usr/local/etc/postfix/main.cf
--8<-- "freebsd/configs/usr/local/etc/postfix/main.cf"
EOF

# 1. Get Default Interface
DEF_IF="$(route -n get -inet default | awk '/interface:/ {print $2}')"

# 2. Get IPv4 IP
IP4="$(ifconfig "$DEF_IF" inet | awk '/inet / && $2 !~ /^127\./ {print $2}' | head -n 1)"
[ -n "$IP4" ] && sed -e "s|__IPADDR4__|$IP4|g" -i '' /usr/local/etc/postfix/main.cf

# 3. Get IPv6 IP
IP6="$(ifconfig "$DEF_IF" inet6 | awk '/inet6 / && $2 !~ /^fe80:/ && $2 !~ /^::1/ {print $2}' | head -n 1)"
[ -n "$IP6" ] && sed -e "s|__IPADDR6__|$IP6|g" -i '' /usr/local/etc/postfix/main.cf
```

### Konfigurationsdatei `master.cf`

``` sh
install -b -m 0644 -g postfix /dev/null /usr/local/etc/postfix/master.cf
cat <<'EOF' > /usr/local/etc/postfix/master.cf
--8<-- "freebsd/configs/usr/local/etc/postfix/master.cf"
EOF
```

### PostgreSQL-Lookup-Dateien `pgsql/*.cf`

``` sh
install -d -m 0755 -g postfix /usr/local/etc/postfix/pgsql

install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/pgsql/recipient_bcc_maps.cf
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/pgsql/relay_domains.cf
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/pgsql/sender_bcc_maps.cf
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/pgsql/sender_dependent_relayhost_maps.cf
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/pgsql/sender_login_maps.cf
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/pgsql/transport_maps.cf
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/pgsql/virtual_alias_maps.cf
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/pgsql/virtual_alias_domains_maps.cf
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/pgsql/virtual_alias_domains_catchall_maps.cf
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/pgsql/virtual_alias_domains_mailbox_maps.cf
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/pgsql/virtual_mailbox_domains.cf
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/pgsql/virtual_mailbox_limits.cf
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/pgsql/virtual_mailbox_maps.cf

cat <<'EOF' > /usr/local/etc/postfix/pgsql/recipient_bcc_maps.cf
--8<-- "freebsd/configs/usr/local/etc/postfix/pgsql/recipient_bcc_maps.cf"
EOF

cat <<'EOF' > /usr/local/etc/postfix/pgsql/relay_domains.cf
--8<-- "freebsd/configs/usr/local/etc/postfix/pgsql/relay_domains.cf"
EOF

cat <<'EOF' > /usr/local/etc/postfix/pgsql/sender_bcc_maps.cf
--8<-- "freebsd/configs/usr/local/etc/postfix/pgsql/sender_bcc_maps.cf"
EOF

cat <<'EOF' > /usr/local/etc/postfix/pgsql/sender_dependent_relayhost_maps.cf
--8<-- "freebsd/configs/usr/local/etc/postfix/pgsql/sender_dependent_relayhost_maps.cf"
EOF

cat <<'EOF' > /usr/local/etc/postfix/pgsql/sender_login_maps.cf
--8<-- "freebsd/configs/usr/local/etc/postfix/pgsql/sender_login_maps.cf"
EOF

cat <<'EOF' > /usr/local/etc/postfix/pgsql/transport_maps.cf
--8<-- "freebsd/configs/usr/local/etc/postfix/pgsql/transport_maps.cf"
EOF

cat <<'EOF' > /usr/local/etc/postfix/pgsql/virtual_alias_maps.cf
--8<-- "freebsd/configs/usr/local/etc/postfix/pgsql/virtual_alias_maps.cf"
EOF

cat <<'EOF' > /usr/local/etc/postfix/pgsql/virtual_alias_domains_maps.cf
--8<-- "freebsd/configs/usr/local/etc/postfix/pgsql/virtual_alias_domains_maps.cf"
EOF

cat <<'EOF' > /usr/local/etc/postfix/pgsql/virtual_alias_domains_catchall_maps.cf
--8<-- "freebsd/configs/usr/local/etc/postfix/pgsql/virtual_alias_domains_catchall_maps.cf"
EOF

cat <<'EOF' > /usr/local/etc/postfix/pgsql/virtual_alias_domains_mailbox_maps.cf
--8<-- "freebsd/configs/usr/local/etc/postfix/pgsql/virtual_alias_domains_mailbox_maps.cf"
EOF

cat <<'EOF' > /usr/local/etc/postfix/pgsql/virtual_mailbox_domains.cf
--8<-- "freebsd/configs/usr/local/etc/postfix/pgsql/virtual_mailbox_domains.cf"
EOF

cat <<'EOF' > /usr/local/etc/postfix/pgsql/virtual_mailbox_limits.cf
--8<-- "freebsd/configs/usr/local/etc/postfix/pgsql/virtual_mailbox_limits.cf"
EOF

cat <<'EOF' > /usr/local/etc/postfix/pgsql/virtual_mailbox_maps.cf
--8<-- "freebsd/configs/usr/local/etc/postfix/pgsql/virtual_mailbox_maps.cf"
EOF

cat /var/db/passwords/user_postgresql_postfix | xargs -I % \
  sed -e "s|__PASSWORD_POSTFIX__|%|g" -i '' /usr/local/etc/postfix/pgsql/*.cf

cat /var/db/passwords/user_postgresql_postfix
```

Postfix beschreibt `pgsql:`-Maps offiziell als passenden Weg, Postfix mit PostgreSQL-Lookup-Tabellen zu verbinden. ([postfix.org][5])

### Restriktionen und Lookup-Dateien

``` sh
install -b -m 0640 -g postfix /etc/mail/aliases /usr/local/etc/postfix/aliases
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/postscreen_access.cidr
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/postscreen_whitelist.cidr
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/body_checks.pcre
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/header_checks.pcre
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/command_filter.pcre
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/helo_access.pcre
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/recipient_checks.pcre
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/sender_access.pcre
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/submission_header_checks.pcre
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/postscreen_dnsbl_reply
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/dnsbl_reply_map
install -b -m 0640 -g postfix /dev/null /usr/local/etc/postfix/mx_access

cat <<'EOF' > /usr/local/etc/postfix/postscreen_access.cidr
--8<-- "freebsd/configs/usr/local/etc/postfix/postscreen_access.cidr"
EOF

cat <<'EOF' > /usr/local/etc/postfix/postscreen_whitelist.cidr
--8<-- "freebsd/configs/usr/local/etc/postfix/postscreen_whitelist.cidr"
EOF

cat <<'EOF' > /usr/local/etc/postfix/body_checks.pcre
--8<-- "freebsd/configs/usr/local/etc/postfix/body_checks.pcre"
EOF

cat <<'EOF' > /usr/local/etc/postfix/header_checks.pcre
--8<-- "freebsd/configs/usr/local/etc/postfix/header_checks.pcre"
EOF

cat <<'EOF' > /usr/local/etc/postfix/command_filter.pcre
--8<-- "freebsd/configs/usr/local/etc/postfix/command_filter.pcre"
EOF

cat <<'EOF' > /usr/local/etc/postfix/helo_access.pcre
--8<-- "freebsd/configs/usr/local/etc/postfix/helo_access.pcre"
EOF

cat <<'EOF' > /usr/local/etc/postfix/recipient_checks.pcre
--8<-- "freebsd/configs/usr/local/etc/postfix/recipient_checks.pcre"
EOF

cat <<'EOF' > /usr/local/etc/postfix/sender_access.pcre
--8<-- "freebsd/configs/usr/local/etc/postfix/sender_access.pcre"
EOF

cat <<'EOF' > /usr/local/etc/postfix/submission_header_checks.pcre
--8<-- "freebsd/configs/usr/local/etc/postfix/submission_header_checks.pcre"
EOF

cat <<'EOF' > /usr/local/etc/postfix/postscreen_dnsbl_reply
--8<-- "freebsd/configs/usr/local/etc/postfix/postscreen_dnsbl_reply"
EOF

cat <<'EOF' > /usr/local/etc/postfix/dnsbl_reply_map
--8<-- "freebsd/configs/usr/local/etc/postfix/dnsbl_reply_map"
EOF

cat <<'EOF' > /usr/local/etc/postfix/mx_access
--8<-- "freebsd/configs/usr/local/etc/postfix/mx_access"
EOF

# 1. Get Default Interface
DEF_IF="$(route -n get -inet default | awk '/interface:/ {print $2}')"

# 2. Get IPv4 IP
IP4="$(ifconfig "$DEF_IF" inet | awk '/inet / && $2 !~ /^127\./ {print $2}' | head -n 1)"
[ -n "$IP4" ] && sed -e "s|__IPADDR4__|$IP4|g" -i '' /usr/local/etc/postfix/postscreen_access.cidr

# 3. Get IPv6 IP
IP6="$(ifconfig "$DEF_IF" inet6 | awk '/inet6 / && $2 !~ /^fe80:/ && $2 !~ /^::1/ {print $2}' | head -n 1)"
[ -n "$IP6" ] && sed -e "s|__IPADDR6__|$IP6|g" -i '' /usr/local/etc/postfix/postscreen_access.cidr

postmap /usr/local/etc/postfix/postscreen_dnsbl_reply
postmap /usr/local/etc/postfix/dnsbl_reply_map
postmap /usr/local/etc/postfix/mx_access

/usr/local/bin/newaliases
```

`postscreen` ist genau für vorgeschaltete SMTP-Prüfungen auf eingehenden Verbindungen gedacht. Lookup-Tabellen und lokale Maps werden mit `postmap` gebaut; `newaliases` aktualisiert die Alias-Tabelle. ([postfix.org][6])

### Konfiguration prüfen

Vor dem ersten Start sollte die Konfiguration immer geprüft werden. Bei Postfix ist dafür `postfix check` der passende Weg. Zusätzlich ist `postconf -n` sinnvoll, um die aktiven Nicht-Default-Parameter auszugeben. Für Dovecot-SASL ist `postconf -a` nützlich, weil damit die unterstützten SASL-Servertypen sichtbar werden. ([postfix.org][7])

``` sh
postconf -a
postconf -n
postfix check
```

---

## Datenbanken

Für dieses HowTo sind **keine neuen Datenbanken** erforderlich.

Postfix nutzt in diesem Setup bestehende PostgreSQL-Tabellen über `pgsql:`-Lookup-Dateien. ([postfix.org][5])

---

## Zusatzsoftware

Mögliche Zusatzsoftware wird hier installiert und konfiguriert.

### Postscreen-Whitelist-Helfer

``` sh
portmaster -w -B -g -U --force-config dns/rubygem-dnsruby -n
portmaster -w -B -g -U --force-config net/rubygem-ipaddress -n
portmaster -w -B -g -U --force-config devel/rubygem-optparse -n
portmaster -w -B -g -U --force-config devel/rubygem-pp -n

install -b -m 0755 /dev/null /usr/local/etc/postfix/postscreen_whitelist.rb
cat <<'EOF' > /usr/local/etc/postfix/postscreen_whitelist.rb
--8<-- "freebsd/configs/usr/local/etc/postfix/postscreen_whitelist.rb"
EOF

/usr/local/etc/postfix/postscreen_whitelist.rb -f
```

### Wir installieren `mail/libmilter` und dessen Abhängigkeiten.

`mail/py-pymilter`, das von `mail/py-spf-engine` verwendet wird, hängt auf FreeBSD standardmäßig an `mail/libmilter`. Daher ist diese Installation in deinem Aufbau fachlich konsistent. ([FreshPorts][8])

``` sh
install -d -m 0755 /var/db/ports/mail_libmilter
cat <<'EOF' > /var/db/ports/mail_libmilter/options
--8<-- "freebsd/ports/mail_libmilter/options"
EOF

portmaster -w -B -g -U --force-config mail/libmilter -n
```

### Wir installieren `mail/py-spf-engine` und dessen Abhängigkeiten.

`mail/py-spf-engine` liefert zwei Betriebsarten:
den **Policy-Service** `policyd-spf` und den **Milter** `pyspf-milter`. Auf FreeBSD installiert der Port ein rc.d-Skript für **`pyspf-milter`**; der Policy-Service wird laut pkg-message typischerweise direkt aus `master.cf` heraus von Postfix gespawnt. ([FreshPorts][2])

``` sh
install -d -m 0755 /var/db/ports/mail_py-pymilter
cat <<'EOF' > /var/db/ports/mail_py-pymilter/options
--8<-- "freebsd/ports/mail_py-pymilter/options"
EOF

install -d -m 0755 /var/db/ports/dns_py-dnspython
cat <<'EOF' > /var/db/ports/dns_py-dnspython/options
--8<-- "freebsd/ports/dns_py-dnspython/options"
EOF

install -d -m 0755 /var/db/ports/devel_py-pyasn1-modules
cat <<'EOF' > /var/db/ports/devel_py-pyasn1-modules/options
--8<-- "freebsd/ports/devel_py-pyasn1-modules/options"
EOF

install -d -m 0755 /var/db/ports/www_py-httpcore
cat <<'EOF' > /var/db/ports/www_py-httpcore/options
--8<-- "freebsd/ports/www_py-httpcore/options"
EOF

install -d -m 0755 /var/db/ports/devel_py-anyio
cat <<'EOF' > /var/db/ports/devel_py-anyio/options
--8<-- "freebsd/ports/devel_py-anyio/options"
EOF

install -d -m 0755 /var/db/ports/www_py-httpx
cat <<'EOF' > /var/db/ports/www_py-httpx/options
--8<-- "freebsd/ports/www_py-httpx/options"
EOF

install -d -m 0755 /var/db/ports/mail_py-spf-engine
cat <<'EOF' > /var/db/ports/mail_py-spf-engine/options
--8<-- "freebsd/ports/mail_py-spf-engine/options"
EOF

portmaster -w -B -g -U --force-config mail/py-spf-engine -n
```

### Dienst in `rc.conf` eintragen

``` sh
sysrc pyspf_milter_enable=YES
```

### Konfigurationsdateien für SPF einrichten

``` sh
install -d -m 0755 /usr/local/etc/pyspf-milter
install -d -m 0755 /usr/local/etc/python-policyd-spf

install -b -m 0644 /dev/null /usr/local/etc/pyspf-milter/pyspf-milter.conf
cat <<'EOF' > /usr/local/etc/pyspf-milter/pyspf-milter.conf
--8<-- "freebsd/configs/usr/local/etc/pyspf-milter/pyspf-milter.conf"
EOF

install -b -m 0644 /dev/null /usr/local/etc/python-policyd-spf/policyd-spf.conf
cat <<'EOF' > /usr/local/etc/python-policyd-spf/policyd-spf.conf
--8<-- "freebsd/configs/usr/local/etc/python-policyd-spf/policyd-spf.conf"
EOF
```

Wichtig: Der FreeBSD-Port hat 2023 den Standardpfad für `pyspf-milter` geändert. Falls dein Setup einen abweichenden Pfad verwenden soll, kannst du ihn per `pyspf_milter_conffile` in `rc.conf` überschreiben. ([FreeBSD Git][9])

### Zusatzsoftware Konfiguration prüfen

``` sh
service pyspf-milter start
service pyspf-milter status
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

Postfix kann nun gestartet werden.

``` sh
service pyspf-milter start
service postfix start
```

Für spätere Änderungen:

``` sh
service pyspf-milter restart
service postfix reload
service postfix restart
```

Für Funktionstests danach:

``` sh
sockstat -4 -6 -l | egrep 'master|smtpd|submission|pyspf'
postqueue -p
```

---

## Referenzen

* FreeBSD Handbook: Electronic Mail / Postfix / `mailer.conf` ([docs.freebsd.org][4])
* FreshPorts: `mail/postfix` ([FreshPorts][1])
* Postfix: SASL Howto ([postfix.org][10])
* Dovecot CE: Postfix and Dovecot SASL ([doc.dovecot.org][3])
* Dovecot CE: Postfix and Dovecot LMTP ([doc.dovecot.org][11])
* Postfix: Postscreen Howto ([postfix.org][6])
* Postfix: PostgreSQL Howto / `pgsql_table(5)` / Database Readme ([postfix.org][5])
* Postfix: `postfix(1)`, `postconf(1)`, `postmap(1)` ([postfix.org][7])
* FreshPorts: `mail/py-spf-engine` ([FreshPorts][2])
* PyPI: `spf-engine` 3.1.0 ([PyPI][12])
* FreshPorts: `mail/py-pymilter` / `mail/libmilter` ([FreshPorts][8])
* FreeBSD Ports `UPDATING`: geänderter `pyspf-milter`-Konfigurationspfad ([FreeBSD Git][9])

[1]: https://www.freshports.org/mail/postfix "FreshPorts -- mail/postfix: Secure alternative to widely-used Sendmail "
[2]: https://www.freshports.org/mail/py-spf-engine/ "FreshPorts -- mail/py-spf-engine: SPF engine for Postfix policy server and milter implemented in Python"
[3]: https://doc.dovecot.org/main/howto/sasl/postfix.html "Postfix and Dovecot SASL"
[4]: https://docs.freebsd.org/en/books/handbook/mail/ "Chapter 31. Electronic Mail"
[5]: https://www.postfix.org/PGSQL_README.html "Postfix PostgreSQL Howto"
[6]: https://www.postfix.org/POSTSCREEN_README.html "Postfix Postscreen Howto"
[7]: https://www.postfix.org/postfix.1.html "Postfix manual - postfix(1)"
[8]: https://www.freshports.org/mail/py-pymilter/ "FreshPorts -- mail/py-pymilter: Python interface to Sendmail milter API"
[9]: https://cgit.freebsd.org/ports/tree/UPDATING "UPDATING - ports - FreeBSD ports tree"
[10]: https://www.postfix.org/SASL_README.html "Postfix SASL Howto"
[11]: https://doc.dovecot.org/main/howto/lmtp/postfix.html "Postfix and Dovecot LMTP"
[12]: https://pypi.org/project/spf-engine/ "spf-engine 3.1.0"
