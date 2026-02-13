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
contributers: []
date: '2010-08-25'
lastmod: '2025-06-28'
title: Dovecot
description: In diesem HowTo wird step-by-step die Installation des Dovecot Mailservers für ein Hosting System auf Basis von FreeBSD 64Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# Dovecot

## Einleitung

Unser Hosting System wird um folgende Dienste erweitert.

- Dovecot 2.3.21 (IMAP only, Pigeonhole)

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

## Installation

Wir installieren `mail/dovecot` und dessen Abhängigkeiten.

``` shell
mkdir -p /var/db/ports/textproc_libexttextcat
cat <<'EOF' > /var/db/ports/textproc_libexttextcat/options
--8<-- "freebsd/ports/textproc_libexttextcat/options"
EOF

mkdir -p /var/db/ports/mail_dovecot
cat <<'EOF' > /var/db/ports/mail_dovecot/options
--8<-- "freebsd/ports/mail_dovecot/options"
EOF


portmaster -w -B -g --force-config mail/dovecot@default  -n


sysrc dovecot_enable=YES
```

Wir installieren `mail/dovecot-pigeonhole` und dessen Abhängigkeiten.

``` shell
mkdir -p /var/db/ports/mail_dovecot-pigeonhole
cat <<'EOF' > /var/db/ports/mail_dovecot-pigeonhole/options
--8<-- "freebsd/ports/mail_dovecot-pigeonhole/options"
EOF


portmaster -w -B -g --force-config mail/dovecot-pigeonhole@default  -n
```

## Konfiguration

`dovecot.conf` einrichten.

``` shell
cat <<'EOF' > /usr/local/etc/dovecot/dovecot.conf
--8<-- "freebsd/configs/usr/local/etc/dovecot/dovecot.conf"
EOF

cat <<'EOF' > /usr/local/etc/dovecot/dovecot-sql.conf
--8<-- "freebsd/configs/usr/local/etc/dovecot/dovecot-sql.conf"
EOF

cat <<'EOF' > /usr/local/etc/dovecot/dovecot-pgsql.conf
--8<-- "freebsd/configs/usr/local/etc/dovecot/dovecot-pgsql.conf"
EOF

cat <<'EOF' > /usr/local/etc/dovecot/dovecot-dict-quota.conf
--8<-- "freebsd/configs/usr/local/etc/dovecot/dovecot-dict-quota.conf"
EOF

cat <<'EOF' > /usr/local/etc/dovecot/dovecot-last-login.conf
--8<-- "freebsd/configs/usr/local/etc/dovecot/dovecot-last-login.conf"
EOF

cat <<'EOF' > /usr/local/etc/dovecot/dovecot-share-folder.conf
--8<-- "freebsd/configs/usr/local/etc/dovecot/dovecot-share-folder.conf"
EOF

cat <<'EOF' > /usr/local/etc/dovecot/dovecot-used-quota.conf
--8<-- "freebsd/configs/usr/local/etc/dovecot/dovecot-used-quota.conf"
EOF

awk '/^Password for PostgreSQL user postfix:/ {print $NF}' /root/_passwords | \
    xargs -I % sed -e 's|__PASSWORD_POSTFIX__|%|g' -i '' /usr/local/etc/dovecot/*.conf

cat <<'EOF' > /usr/local/bin/dovecot-quota-warning.sh
--8<-- "freebsd/configs/usr/local/bin/dovecot-quota-warning.sh"
EOF

chmod 0755 /usr/local/bin/dovecot-quota-warning.sh

/usr/local/bin/openssl dhparam 4096 > /usr/local/etc/dovecot/dh.pem
```

`/usr/local/etc/dovecot/passwd` einrichten.

Wir legen einen neuen Superuser an.

``` shell
cat <<'EOF' > /usr/local/etc/dovecot/dovecot-master-users
--8<-- "freebsd/configs/usr/local/etc/dovecot/dovecot-master-users"
EOF


# Password erzeugen und in /root/_passwords speichern
chmod 0600 /root/_passwords
newpw="`openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | cut -c 2-17`"
echo "Password for Dovecot user superuser: $newpw" >> /root/_passwords
chmod 0400 /root/_passwords
echo "$newpw" | xargs -I % doveadm pw -s SSHA512 -p % | xargs -I % echo "superuser:%" > /usr/local/etc/dovecot/dovecot-master-users
echo "Password: $newpw"
unset newpw
```

Das Anlegen neuer Mailuser wird mittels Script automatisiert.

``` shell
cat <<'EOF' > /usr/local/etc/dovecot/create_mailuser.sh
--8<-- "freebsd/configs/usr/local/etc/dovecot/create_mailuser.sh"
EOF

chmod 0755 /usr/local/etc/dovecot/create_mailuser.sh

# admin@example.com anlegen
/usr/local/etc/dovecot/create_mailuser.sh admin@example.com

### BUGFIX: Das Löschen von Mailusern muss noch implementiert werden
```

## Abschluss

Dovecot kann nun gestartet werden.

``` shell
service dovecot start
```
