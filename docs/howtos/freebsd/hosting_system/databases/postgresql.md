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
title: PostgreSQL
description: In diesem HowTo wird Schritt für Schritt die Installation des PostgreSQL Datenbanksystem für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# PostgreSQL

## Inhalt

- PostgreSQL 18.3
- FreeBSD Ports: `databases/postgresql18-server`
- Zusatzmodule: `databases/postgresql18-contrib`
- sichere Initialisierung mit `peer` lokal und `scram-sha-256` für TCP/IP
- Beispiele für Rollen, Datenbanken, Dumps und Base-Backups

---

## Einleitung

Dieses HowTo beschreibt die Installation und Konfiguration von PostgreSQL 18 auf FreeBSD 15+.

Die Installation erfolgt bewusst über den FreeBSD-Ports-Tree. Die aktuelle Version von `databases/postgresql18-server` ist 18.3. Im aktuellen Port sind unter anderem OpenSSL- und ZSTD-Unterstützung aktiviert. Das bedeutet aber noch nicht, dass verschlüsselte Client-Verbindungen automatisch aktiv sind. Dafür brauchst du zusätzlich die passende Laufzeitkonfiguration in PostgreSQL.

---

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

---

## Vorbereitungen

### DNS Records

Für dieses HowTo sind keine zusätzlichen DNS-Records erforderlich.

### Verzeichnisse / Dateien

Vor der Installation müssen keine zusätzlichen Verzeichnisse oder Dateien manuell angelegt werden.

Die benötigten Verzeichnisse für Daten, Backups und Passwortdateien werden in den folgenden Schritten eingerichtet.

### Gruppen / Benutzer / Passwörter

Für dieses HowTo müssen keine zusätzlichen Systemgruppen oder Systembenutzer manuell angelegt werden.

Der Ports-Tree arbeitet hier mit dem Systembenutzer `postgres`.

---

## Installation

### Wir installieren `databases/postgresql18-server` und dessen Abhängigkeiten.

``` sh
install -d -m 0755 /var/db/ports/databases_postgresql18-client
cat <<'EOF' > /var/db/ports/databases_postgresql18-client/options
--8<-- "freebsd/ports/databases_postgresql18-client/options"
EOF

install -d -m 0755 /var/db/ports/databases_postgresql18-server
cat <<'EOF' > /var/db/ports/databases_postgresql18-server/options
--8<-- "freebsd/ports/databases_postgresql18-server/options"
EOF

portmaster -w -B -g -U --force-config databases/postgresql18-server -n
portmaster -w -B -g -U --force-config databases/postgresql18-contrib -n
```

### Dienst in `rc.conf` eintragen

Der Dienst wird mittels `sysrc` in der `rc.conf` eingetragen und dadurch beim Systemstart automatisch gestartet.

``` sh
sysrc postgresql_enable=YES
sysrc postgresql_login_class="postgres"
sysrc postgresql_data="/var/db/postgres/data18"
sysrc postgresql_initdb_flags="--locale=C.UTF-8 --encoding=UTF8 --auth=scram-sha-256 --auth-local=peer --auth-host=scram-sha-256 --data-checksums --pwfile=/var/db/passwords/postgresql_superuser_postgres"
```

---

## Konfiguration

### Login-Klasse `postgres` einrichten

Die Login-Klasse `postgres` ist in diesem Setup sinnvoll, damit Locale- und Collation-Vorgaben sauber an den PostgreSQL-Dienst übergeben werden.

``` sh
cat <<'EOF' >> /etc/login.conf

postgres:\
        :lang=C.UTF-8:\
        :setenv=LC_COLLATE=C:\
        :tc=default:
EOF

cap_mkdb /etc/login.conf
```

### Verzeichnisse für Backups und Passwortdateien anlegen

``` sh
install -d -m 0700 -o postgres -g postgres /var/db/passwords
install -d -m 0750 -o postgres -g postgres /var/db/backups/postgresql
```

### Passwort für den PostgreSQL-Superuser `postgres` erzeugen

``` sh
install -b -m 0600 -o postgres -g postgres /dev/null /var/db/passwords/postgresql_superuser_postgres
openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | \
  cut -c 2-17 | tee /var/db/passwords/postgresql_superuser_postgres
```

### Cluster initialisieren und Dienst starten

``` sh
service postgresql initdb
service postgresql start
```

### Funktionstest

``` sh
su -l postgres -c "psql -d postgres -c 'SELECT version();'"
```

Ohne weitere Änderungen lauscht PostgreSQL standardmäßig nur auf `localhost`. Externe TCP-Verbindungen sind damit noch nicht automatisch offen.

---

## Datenbanken

### PostgreSQL-Benutzer `admin` anlegen

Rollen sind in PostgreSQL clusterweit. Ein „Benutzer“ ist praktisch eine Rolle mit `LOGIN`.

``` sh
# Passwort für PostgreSQL-Benutzer "admin" erzeugen und
# in /var/db/passwords/postgresql_user_admin speichern
install -b -m 0600 -o postgres -g postgres /dev/null /var/db/passwords/postgresql_user_admin
openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | \
  cut -c 2-17 | tee /var/db/passwords/postgresql_user_admin

# PostgreSQL-Benutzer "admin" mit Passwort anlegen
su -l postgres -c "psql <<'EOF'
\set content `cat /var/db/passwords/postgresql_user_admin`
DROP ROLE IF EXISTS \"admin\";
CREATE ROLE \"admin\";
ALTER ROLE \"admin\" WITH NOSUPERUSER INHERIT CREATEROLE CREATEDB LOGIN NOREPLICATION NOBYPASSRLS PASSWORD :'content';
\unset content
EOF"
```

Das Passwort bitte **sicher** notieren, du wirst es bei jeder externen Verbindung über TCP benötigen.

### PostgreSQL-Datenbank `test_db` für `admin` anlegen

``` sh
su -l postgres -c "psql <<'EOF'
DROP DATABASE IF EXISTS \"test_db\";
CREATE DATABASE \"test_db\";
ALTER DATABASE \"test_db\" OWNER TO \"admin\";

\connect \"test_db\"

CREATE TABLE \"public\".\"kunden\" (
    \"id\" bigint NOT NULL,
    \"name\" text NOT NULL,
    \"email\" text,
    \"created_at\" timestamp with time zone DEFAULT now() NOT NULL
);

ALTER TABLE ONLY \"public\".\"kunden\"
    ADD CONSTRAINT \"kunden_email_key\" UNIQUE (\"email\");

ALTER TABLE ONLY \"public\".\"kunden\"
    ADD CONSTRAINT \"kunden_pkey\" PRIMARY KEY (\"id\");

ALTER TABLE \"public\".\"kunden\" OWNER TO \"admin\";
EOF"
```

### PostgreSQL-Verbindung als `admin` testen

Weil `--auth-local=peer` gesetzt wurde, nutzt eine Verbindung **ohne** `-h` normalerweise den Unix-Domain-Socket und damit lokale Peer-Authentifizierung. Für einen echten Passworttest wird deshalb bewusst TCP gegen `127.0.0.1` verwendet.

``` sh
psql -h 127.0.0.1 -U admin -d test_db -W -c 'SELECT current_user, current_database();'
```

### Einzelne Datenbank sichern

``` sh
su -l postgres -c "pg_dump -Fc -f /var/db/backups/postgresql/test_db-$(date +%F).dump test_db"
```

Wiederherstellung:

``` sh
su -l postgres -c "createdb -O admin test_db_restore"
su -l postgres -c "pg_restore -d test_db_restore /var/db/backups/postgresql/test_db-2026-03-21.dump"
```

### Einzelne Tabelle sichern

``` sh
su -l postgres -c "pg_dump -Fc -t public.kunden -f /var/db/backups/postgresql/kunden-$(date +%F).dump test_db"
```

Wiederherstellung:

``` sh
su -l postgres -c "pg_restore -d test_db_restore -t public.kunden /var/db/backups/postgresql/kunden-2026-03-21.dump"
```

### Rollen und globale Objekte sichern

`pg_dump` sichert Rollen nicht mit. Dafür ist `pg_dumpall --globals-only` da.

``` sh
su -l postgres -c "pg_dumpall --clean --column-inserts --attribute-inserts --if-exists --inserts --no-table-access-method --no-tablespaces --no-toast-compression --no-unlogged-table-data --quote-all-identifiers --rows-per-insert=1 --globals-only > /var/db/backups/postgresql/globals-$(date +%F).sql"
```

Wiederherstellung:

``` sh
su -l postgres -c "psql -X -v ON_ERROR_STOP=1 -d postgres -f /var/db/backups/postgresql/globals-2026-03-21.sql"
```

### Gesamten Cluster logisch sichern

``` sh
su -l postgres -c "pg_dumpall --clean --column-inserts --attribute-inserts --if-exists --inserts --no-table-access-method --no-tablespaces --no-toast-compression --no-unlogged-table-data --quote-all-identifiers --rows-per-insert=1 > /var/db/backups/postgresql/cluster-$(date +%F).sql"
```

Wiederherstellung:

``` sh
su -l postgres -c "psql -X -v ON_ERROR_STOP=1 -d postgres -f /var/db/backups/postgresql/cluster-2026-03-21.sql"
```

### Physisches Base-Backup des gesamten Clusters

`pg_basebackup` arbeitet immer auf Clusterebene. Für produktive Nutzung braucht der verwendete Benutzer passende Rechte und eine passende `pg_hba.conf`-Regel für Replikationsverbindungen.

``` sh
su -l postgres -c "pg_basebackup -D /var/db/backups/postgresql/basebackup-$(date +%F_%H%M) -Fp -X stream -c fast"
```

Für Point-in-Time-Recovery brauchst du zusätzlich eine funktionierende und getestete WAL-Archivierung.

### Minor-Updates und Major-Upgrades

Minor-Updates innerhalb derselben Major-Version sind normale PostgreSQL-Update-Releases.

Major-Upgrades, zum Beispiel von 17 auf 18, brauchen ein eigenes Verfahren wie `pg_upgrade`, Dump/Restore oder einen Replikationsweg. Im FreeBSD-Ports-Tree wurde die Default-Version auf PostgreSQL 18 umgestellt. Bei produktiven Systemen sollte dafür immer der dokumentierte Upgrade-Pfad aus `UPDATING` und den PostgreSQL-Upgrade-Dokumenten verwendet werden.

---

## Zusatzsoftware

Mögliche Zusatzsoftware wird hier installiert und konfiguriert.

Für dieses HowTo ist keine zusätzliche Software erforderlich.

---

## Aufräumen

Überflüssige oder temporäre Verzeichnisse und Dateien entsorgen.

### Zusatzsoftware Installation

Nicht erforderlich.

### Zusatzsoftware Konfiguration

Nicht erforderlich.

---

## Abschluss

Zum Schluss den Dienst einmal sauber neu starten:

``` sh
service postgresql restart
```

Für spätere Änderungen:

``` sh
service postgresql reload
service postgresql restart
```

---

## Referenzen

* FreeBSD Handbook: Installing Applications – Ports
* FreshPorts: `databases/postgresql18-server`
* FreshPorts: `databases/postgresql18-contrib`
* PostgreSQL Documentation: `initdb`
* PostgreSQL Documentation: `pg_hba.conf`
* PostgreSQL Documentation: `psql`
* PostgreSQL Documentation: `pg_dump`
* PostgreSQL Documentation: `pg_dumpall`
* PostgreSQL Documentation: `pg_restore`
* PostgreSQL Documentation: `pg_basebackup`
* PostgreSQL Documentation: Backups and Restore
* PostgreSQL Documentation: Upgrading a PostgreSQL Cluster
* FreeBSD Ports `UPDATING`
