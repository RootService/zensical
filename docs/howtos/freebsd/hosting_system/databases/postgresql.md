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
lastmod: '2026-02-27'
title: PostgreSQL
description: In diesem HowTo wird step-by-step die Installation des PostgreSQL Datenbanksystem für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# PostgreSQL

> **Stand:** 2026-02-27  
> **Terminologie:** Einheitlich werden die Begriffe **HowTo**, **HowTos**, **BaseSystem**, **BasePorts** und **BaseTools** verwendet.


## Einleitung

Unser Hosting System wird um folgende Dienste erweitert.

- PostgreSQL 17.5 (SSL, ZSTD)

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

## Installation

Wir installieren `databases/postgresql17-server` und dessen Abhängigkeiten.

``` shell
mkdir -p /var/db/ports/databases_postgresql17-client
cat <<'EOF' > /var/db/ports/databases_postgresql7-client/options
--8<-- "freebsd/ports/databases_postgresql17-client/options"
EOF

mkdir -p /var/db/ports/databases_postgresql17-server
cat <<'EOF' > /var/db/ports/databases_postgresql17-server/options
--8<-- "freebsd/ports/databases_postgresql17-server/options"
EOF


portmaster -w -B -g --force-config databases/postgresql17-server  -n

portmaster -w -B -g --force-config databases/postgresql17-contrib  -n


cp -a /var/db/postgres* /data/db/

sysrc postgresql_enable=YES
sysrc postgresql_data="/data/db/postgres/data17"
sysrc postgresql_initdb_flags="--encoding=utf-8 --lc-collate=C --auth=scram-sha-256 --pwprompt"
```

## Konfiguration

PostgreSQL wird nun zum ersten Mal gestartet, was einige Minuten dauern kann.

``` shell
# Password erzeugen und in /root/_passwords speichern
chmod 0600 /root/_passwords
newpw="`openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | cut -c 2-17`"
echo "Password for PostgreSQL initdb: $newpw" >> /root/_passwords
chmod 0400 /root/_passwords
echo "Password: $newpw"
unset newpw


service postgresql initdb

service postgresql start
```

## Sicherheit

``` shell
# Password erzeugen und in /root/_passwords speichern
chmod 0600 /root/_passwords
newpw="`openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | cut -c 2-17`"
echo "Password for PostgreSQL user postges: $newpw" >> /root/_passwords
chmod 0400 /root/_passwords
echo "Password: $newpw"
unset newpw


passwd postgres


cat <<'EOF' >> /data/db/postgres/data17/pg_hba.conf
#
# test_db databases
#
# TYPE  DATABASE        USER            ADDRESS                 METHOD
#
local   test_db         admin                                   scram-sha-256
host    test_db         admin           127.0.0.1/32            scram-sha-256
host    test_db         admin           ::1/128                 scram-sha-256
#
EOF

su - postgres

# Password erzeugen und in /root/_passwords speichern
chmod 0600 /root/_passwords
newpw="`openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | cut -c 2-17`"
echo "Password for PostgreSQL user admin: $newpw" >> /root/_passwords
chmod 0400 /root/_passwords
echo "Password: $newpw"
unset newpw


createuser -U postgres -S -D -R -P -e admin

createdb -U postgres -E unicode -O admin test_db

psql test_db

GRANT ALL PRIVILEGES ON DATABASE test_db TO admin;
QUIT;

exit
```

## Abschluss

PostgreSQL sollte abschliessend einmal neu gestartet werden.

``` shell
service postgresql restart
```
