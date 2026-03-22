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
title: Amavisd
description: In diesem HowTo wird Schritt für Schritt die Installation von Amavisd für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# Amavisd

## Inhalt

* Amavisd 2.14.0
* PostgreSQL für Lookups, Logging und Quarantäne
* optionale DKIM-Signierung
* optionale p0f-Analyse
* amavisd-milter 1.7.0
* Milter-Anbindung für Postfix
* AM.PDP-Verbindung zu amavisd-new
* Unix-Sockets zwischen Postfix, amavisd-milter und amavisd-new

Der aktuelle FreeBSD-Port `security/amavisd-new` steht auf **2.14.0**. Relevante Portoptionen für dieses Setup sind insbesondere **`PGSQL`** für Lookups/Logging/Quarantäne und optional **`P0F`** für den p0f-Analyzer. Der Port bringt außerdem Beispielkonfigurationen unter `/usr/local/etc` mit. ([FreshPorts][1])

---

## Einleitung

Dieses HowTo beschreibt die Installation und Konfiguration von Amavisd und von **amavisd-milter** auf FreeBSD 15+.

Amavisd ist die Schnittstelle zwischen MTA und Inhaltsprüfern wie Virenscannern und SpamAssassin. In diesem Setup wird Amavisd mit **PostgreSQL** für Policy-Lookups sowie für Logging und optional Quarantäne betrieben. Die dafür relevanten Konfigurationsvariablen sind **`@lookup_sql_dsn`** für Lookups und **`@storage_sql_dsn`** für Reporting/Quarantäne; beide sind unabhängig voneinander. Die PostgreSQL-spezifischen Schemas werden upstream in `README.sql-pg` dokumentiert. ([IJS][2])

`amavisd-milter` ist ein **separater Milter-Adapter** für `amavisd-new`. Er spricht auf der einen Seite das **Milter-Protokoll** mit dem MTA und auf der anderen Seite das **AM.PDP-Protokoll** mit `amavisd-new`. Der aktuelle FreeBSD-Port `security/amavisd-milter` steht bei **1.7.0**. Der Port installiert das Binary `sbin/amavisd-milter`, das rc.d-Skript `amavisd-milter` und legt `/var/run/amavis` an. ([FreshPorts][12])

Dieses HowTo beschreibt bewusst die **Milter-Anbindung von Postfix an Amavis**. Die klassische `content_filter`-Kette `Postfix -> amavisd-new -> Postfix` wird hier **nicht** behandelt. Postfix unterstützt den Sendmail-Milter-Standard ausdrücklich als **before-queue**-Filtermechanismus. ([Postfix][13])

---

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

Zusätzlich gilt für dieses HowTo:

* Postfix ist bereits installiert und als Content-Filter-Frontend vorbereitet.
* PostgreSQL ist bereits installiert und erreichbar.
* Mindestens **ein Virenscanner** ist installiert, zum Beispiel `security/clamav`.
* Falls Spam-Prüfung genutzt werden soll, ist SpamAssassin bereits installiert.
* Für die optionale DKIM-Signierung muss DNS für die Maildomain gepflegt werden können.
* `amavisd-new` ist für **AM.PDP** konfiguriert.
* Postfix ist bereits installiert und für **Milter** vorbereitet.
* Der Postfix-Prozess kann den Milter-Socket erreichen.

Der FreeBSD-Port weist ausdrücklich darauf hin, dass `amavisd-new` mindestens einen Virenscanner benötigt. Außerdem unterstützt der Port SpamAssassin-Integration. ([FreshPorts][1])

Für `amavisd-milter` muss `amavisd-new` auf **AM.PDP** umgestellt sein. Die offizielle Doku nennt dafür in `amavisd.conf` insbesondere `$protocol = "AM.PDP";` und einen Unix-Socket wie `$unix_socketname = "$MYHOME/var/amavisd.sock";`. Der Socket zwischen `amavisd-milter` und `amavisd-new` muss dabei denselben Wert haben wie `$unix_socketname` in `amavisd.conf`. ([Amavisd Milter][14])

---

## Vorbereitungen

### DNS Records

Für dieses HowTo sind zunächst keine zusätzlichen DNS-Records zwingend erforderlich.

Für die **optionale DKIM-Signierung** wird später jedoch ein TXT-Record für den verwendeten Selector benötigt. Amavis kann diesen Record nach der Schlüsselerzeugung selbst mit `showkeys` ausgeben. ([Amavis][3])

### Verzeichnisse / Dateien

Für diese HowTos müssen zuvor folgende Verzeichnisse angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

```shell
install -d -m 0700 /var/db/passwords
install -d -m 0750 -o vscan -g vscan /var/amavis/tmp
```

Für diese HowTos müssen zuvor folgende Dateien angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

```shell
install -b -m 0600 -o postgres -g postgres /dev/null /var/db/passwords/postgresql_user_vscan
```

### Gruppen / Benutzer / Passwörter

Für dieses HowTo müssen keine zusätzlichen Systemgruppen oder Systembenutzer angelegt werden.

Der Amavisd-Port verwendet auf FreeBSD die bestehenden Amavis-Benutzer- und Gruppenpfade unter `/var/amavis`; das rc.d-Skript erwartet standardmäßig die Konfigurationsdatei `/usr/local/etc/amavisd.conf` und die PID-Datei unter `/var/amavis/var/amavisd.pid`. ([FreeBSD Git][4])

---

## Installation

### Wir installieren `security/amavisd-new` und dessen Abhängigkeiten.

```shell
install -d -m 0755 /var/db/ports/archivers_7-zip
cat <<'EOF' > /var/db/ports/archivers_7-zip/options
--8<-- "freebsd/ports/archivers_7-zip/options"
EOF

install -d -m 0755 /var/db/ports/archivers_arc
cat <<'EOF' > /var/db/ports/archivers_arc/options
--8<-- "freebsd/ports/archivers_arc/options"
EOF

install -d -m 0755 /var/db/ports/archivers_arj
cat <<'EOF' > /var/db/ports/archivers_arj/options
--8<-- "freebsd/ports/archivers_arj/options"
EOF

install -d -m 0755 /var/db/ports/archivers_cabextract
cat <<'EOF' > /var/db/ports/archivers_cabextract/options
--8<-- "freebsd/ports/archivers_cabextract/options"
EOF

install -d -m 0755 /var/db/ports/archivers_lzop
cat <<'EOF' > /var/db/ports/archivers_lzop/options
--8<-- "freebsd/ports/archivers_lzop/options"
EOF

install -d -m 0755 /var/db/ports/archivers_lzo2
cat <<'EOF' > /var/db/ports/archivers_lzo2/options
--8<-- "freebsd/ports/archivers_lzo2/options"
EOF

install -d -m 0755 /var/db/ports/archivers_unrar
cat <<'EOF' > /var/db/ports/archivers_unrar/options
--8<-- "freebsd/ports/archivers_unrar/options"
EOF

install -d -m 0755 /var/db/ports/security_amavisd-new
cat <<'EOF' > /var/db/ports/security_amavisd-new/options
--8<-- "freebsd/ports/security_amavisd-new/options"
EOF

portmaster -w -B -g -U --force-config security/amavisd-new -n
```

Seit dem Port-Update 2022 verwendet Amavis auf FreeBSD **`archivers/7-zip`** und nicht mehr das alte `p7zip`/`7zr`. Falls bestehende Konfigurationen noch alte Aufrufnamen enthalten, müssen diese angepasst werden. ([FreshPorts][5])

### Wir installieren `security/amavisd-milter` und dessen Abhängigkeiten.

```shell
install -d -m 0755 /var/db/ports/security_amavisd-milter
cat <<'EOF' > /var/db/ports/security_amavisd-milter/options
--8<-- "freebsd/ports/security_amavisd-milter/options"
EOF

portmaster -w -B -g -U --force-config security/amavisd-milter -n
```

### Dienst in `rc.conf` eintragen

Der Dienst wird mittels `sysrc` in der `rc.conf` eingetragen und dadurch beim Systemstart automatisch gestartet.

```sh
sysrc amavisd_enable="YES"
sysrc amavisd_pidfile="/var/amavis/var/amavisd.pid"
```

---

Das FreeBSD-rc-Skript heißt **`amavisd-milter`**, die `rc.conf`-Variable dazu heißt aber **`amavisd_milter_enable`**. Zusätzlich kennt das Skript unter anderem die Variablen `amavisd_milter_socket`, `amavisd_milter_socket_perm`, `amavisd_am_pdp_socket` und optional `amavisd_milter_pidfile`. Die Standardwerte sind `/var/run/amavis/amavisd-milter.sock`, `0666`, `/var/amavis/var/amavisd.sock` und `/var/run/amavis/amavisd-milter.pid`. ([GitHub][15])

```sh
sysrc amavisd_milter_enable="YES"
sysrc amavisd_milter_socket="local:/var/run/amavis/amavisd-milter.sock"
sysrc amavisd_milter_socket_perm="0666"
sysrc amavisd_am_pdp_socket="local:/var/amavis/var/amavisd.sock"
```

---

## Konfiguration

### Konfigurationsdatei

Der Port installiert die Vorlagen `amavisd.conf.sample`, `amavisd.conf-default` und `amavisd-custom.conf.sample` unter `/usr/local/etc`. Für dieses Setup verwenden wir die produktive Konfigurationsdatei `/usr/local/etc/amavisd.conf`. ([FreeBSD Git][6])

```shell
install -b -m 0644 /usr/local/etc/amavisd.conf.sample /usr/local/etc/amavisd.conf
cat <<'EOF' > /usr/local/etc/amavisd.conf
--8<-- "freebsd/configs/usr/local/etc/amavisd.conf"
EOF

# 1. Get Default Interface
DEF_IF="$(route -n get -inet default | awk '/interface:/ {print $2}')"

# 2. Get IPv4 IP
IP4="$(ifconfig "$DEF_IF" inet | awk '/inet / && $2 !~ /^127\./ {print $2}' | head -n 1)"
[ -n "$IP4" ] && sed -e "s|__IPADDR4__|$IP4|g" -i '' /usr/local/etc/amavisd.conf

# 3. Get IPv6 IP
IP6="$(ifconfig "$DEF_IF" inet6 | awk '/inet6 / && $2 !~ /^fe80:/ && $2 !~ /^::1/ {print $2}' | head -n 1)"
[ -n "$IP6" ] && sed -e "s|__IPADDR6__|$IP6|g" -i '' /usr/local/etc/amavisd.conf

cat /var/db/passwords/postgresql_user_vscan | xargs -I % \
  sed -e "s|__PASSWORD_VSCAN__|%|g" -i '' /usr/local/etc/amavisd.conf
```

### Optionale DKIM-Signierung

Amavis kann selbst DKIM-Schlüssel erzeugen, öffentliche Schlüssel für DNS ausgeben und veröffentlichte Schlüssel testen. Upstream empfiehlt für DKIM-Signing mindestens **1024 Bit**; 2048 Bit ist heute der saubere Standard. `genrsa` erzeugt den privaten Schlüssel, `showkeys` erzeugt den DNS-geeigneten Public-Key-Output, und `testkeys` prüft die Veröffentlichung gegen DNS. ([Amavis][3])

```shell
install -d -m 0755 -o vscan -g vscan /var/amavis/db/keys
install -d -m 0755 -o vscan -g vscan /var/amavis/db/keys/example.com

su -m vscan -c 'amavisd genrsa /var/amavis/db/keys/example.com/20260321.pem 2048'

amavisd showkeys
```

Wenn der DNS-Record veröffentlicht ist, kann die DKIM-Konfiguration anschließend geprüft werden.

```shell
amavisd testkeys
```

### Optionale Policy-Banks

`amavisd-milter` kann optional mit `-B` arbeiten. Dann verwendet es den Milter-Makrowert `{daemon_name}` als Namen einer Amavis-Policy-Bank. Für authentifizierte Clients kann es zusätzlich Policy-Banks wie `SMTP_AUTH` oder `SMTP_AUTH_<MECH>` verwenden. Das ist nur dann sinnvoll, wenn du in `amavisd-new` bewusst mit Policy-Banks arbeitest. ([Amavisd Milter][17])

Optional:

```sh
sysrc amavisd_milter_flags="-B"
```

### Konfiguration prüfen

Für den ersten Test empfiehlt die offizielle Installationsanleitung ausdrücklich einen Start im Debug-Modus. Das ist der saubere Weg, um Konfigurations- oder Rechteprobleme vor dem produktiven Daemon-Start zu sehen. ([Amavis][7])
Vor dem ersten Start sollte geprüft werden, ob `amavisd-new` läuft, der AM.PDP-Socket erreichbar ist und Postfix den Milter-Socket korrekt eingetragen hat.

```sh
postconf smtpd_milters non_smtpd_milters
/usr/local/sbin/amavisd debug
service amavisd status
service amavisd-milter start
sockstat -4 -6 -l | egrep 'amavisd|10024|10025|milter|amavis'
```

Einen eigenen `configtest`-Subcommand bringt `amavisd-milter` nicht mit. Die saubere Funktionsprüfung besteht hier darin, dass `amavisd-new` bereits läuft, `amavisd-milter` seinen Milter-Socket anlegt und Postfix auf diesen Socket zeigt. Die Dokumentation von `amavisd-milter` unterscheidet dabei explizit zwischen dem **Milter-Socket** (`-s`) und dem **AM.PDP-Socket** (`-S`). ([FreeBSD Manual Pages][18])

---

## Datenbanken

Amavis trennt SQL-seitig zwischen **Lookups** und **Storage**.
`@lookup_sql_dsn` ist für die read-only Lookup-Datenbank gedacht, `@storage_sql_dsn` für Reporting/Quarantäne mit Schreibzugriff. Upstream dokumentiert dafür auf PostgreSQL typischerweise **`mail_prefs`** als Lookup-Datenbank und **`mail_log`** als Storage-Datenbank. Für Lookups genügt `SELECT`; für Storage werden `SELECT`, `INSERT` und `UPDATE` benötigt. ([GitHub][8])

### PostgreSQL-Benutzer `vscan` anlegen

```shell
# Passwort für PostgreSQL-Benutzer "vscan" erzeugen und
# in /var/db/passwords/postgresql_user_vscan speichern
install -b -m 0600 -o postgres -g postgres /dev/null /var/db/passwords/postgresql_user_vscan
openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | \
  cut -c 2-17 | tee /var/db/passwords/postgresql_user_vscan

# PostgreSQL-Benutzer "vscan" mit Passwort anlegen
su -l postgres -c "psql <<'EOF'
\set content `cat /var/db/passwords/postgresql_user_vscan`
DROP ROLE IF EXISTS \"vscan\";
CREATE ROLE \"vscan\";
ALTER ROLE \"vscan\" WITH NOSUPERUSER INHERIT CREATEROLE CREATEDB LOGIN NOREPLICATION NOBYPASSRLS PASSWORD :'content';
\unset content
EOF"
```

Das Passwort bitte **sicher** notieren, du wirst es bei jeder externen Verbindung (TCP) benötigen.

### PostgreSQL-Datenbank `mail_prefs` für `vscan` anlegen

Die Lookup-Datenbank enthält die Tabellen für Richtlinien, Benutzer und White-/Blacklist-Zuordnungen. Das ist der read-only Teil des von Amavis dokumentierten SQL-Schemas. ([GitHub][8])

```shell
su -l postgres -c "psql <<'EOF'
DROP DATABASE IF EXISTS \"mail_prefs\";
CREATE DATABASE \"mail_prefs\";
ALTER DATABASE \"mail_prefs\" OWNER TO \"vscan\";
EOF"

su -l postgres -c "psql <<'EOF'
\connect "mail_prefs"
CREATE TABLE \"public\".\"mailaddr\" (
    \"id\" integer NOT NULL,
    \"priority\" integer DEFAULT 9 NOT NULL,
    \"email\" \"bytea\" NOT NULL
);

ALTER TABLE \"public\".\"mailaddr\" OWNER TO \"vscan\";

CREATE SEQUENCE \"public\".\"mailaddr_id_seq\"
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE \"public\".\"mailaddr_id_seq\" OWNER TO \"vscan\";

ALTER SEQUENCE \"public\".\"mailaddr_id_seq\" OWNED BY \"public\".\"mailaddr\".\"id\";

CREATE TABLE \"public\".\"policy\" (
    \"id\" integer NOT NULL,
    \"policy_name\" character varying(32),
    \"virus_lover\" character(1) DEFAULT NULL::\"bpchar\",
    \"spam_lover\" character(1) DEFAULT NULL::\"bpchar\",
    \"unchecked_lover\" character(1) DEFAULT NULL::\"bpchar\",
    \"banned_files_lover\" character(1) DEFAULT NULL::\"bpchar\",
    \"bad_header_lover\" character(1) DEFAULT NULL::\"bpchar\",
    \"bypass_virus_checks\" character(1) DEFAULT NULL::\"bpchar\",
    \"bypass_spam_checks\" character(1) DEFAULT NULL::\"bpchar\",
    \"bypass_banned_checks\" character(1) DEFAULT NULL::\"bpchar\",
    \"bypass_header_checks\" character(1) DEFAULT NULL::\"bpchar\",
    \"virus_quarantine_to\" character varying(64) DEFAULT NULL::character varying,
    \"spam_quarantine_to\" character varying(64) DEFAULT NULL::character varying,
    \"banned_quarantine_to\" character varying(64) DEFAULT NULL::character varying,
    \"unchecked_quarantine_to\" character varying(64) DEFAULT NULL::character varying,
    \"bad_header_quarantine_to\" character varying(64) DEFAULT NULL::character varying,
    \"clean_quarantine_to\" character varying(64) DEFAULT NULL::character varying,
    \"archive_quarantine_to\" character varying(64) DEFAULT NULL::character varying,
    \"spam_tag_level\" real,
    \"spam_tag2_level\" real,
    \"spam_tag3_level\" real,
    \"spam_kill_level\" real,
    \"spam_dsn_cutoff_level\" real,
    \"spam_quarantine_cutoff_level\" real,
    \"addr_extension_virus\" character varying(64) DEFAULT NULL::character varying,
    \"addr_extension_spam\" character varying(64) DEFAULT NULL::character varying,
    \"addr_extension_banned\" character varying(64) DEFAULT NULL::character varying,
    \"addr_extension_bad_header\" character varying(64) DEFAULT NULL::character varying,
    \"warnvirusrecip\" character(1) DEFAULT NULL::\"bpchar\",
    \"warnbannedrecip\" character(1) DEFAULT NULL::\"bpchar\",
    \"warnbadhrecip\" character(1) DEFAULT NULL::\"bpchar\",
    \"newvirus_admin\" character varying(64) DEFAULT NULL::character varying,
    \"virus_admin\" character varying(64) DEFAULT NULL::character varying,
    \"banned_admin\" character varying(64) DEFAULT NULL::character varying,
    \"bad_header_admin\" character varying(64) DEFAULT NULL::character varying,
    \"spam_admin\" character varying(64) DEFAULT NULL::character varying,
    \"spam_subject_tag\" character varying(64) DEFAULT NULL::character varying,
    \"spam_subject_tag2\" character varying(64) DEFAULT NULL::character varying,
    \"spam_subject_tag3\" character varying(64) DEFAULT NULL::character varying,
    \"message_size_limit\" integer,
    \"banned_rulenames\" character varying(64) DEFAULT NULL::character varying,
    \"disclaimer_options\" character varying(64) DEFAULT NULL::character varying,
    \"forward_method\" character varying(64) DEFAULT NULL::character varying,
    \"sa_userconf\" character varying(64) DEFAULT NULL::character varying,
    \"sa_username\" character varying(64) DEFAULT NULL::character varying
);

ALTER TABLE \"public\".\"policy\" OWNER TO \"vscan\";

CREATE SEQUENCE \"public\".\"policy_id_seq\"
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE \"public\".\"policy_id_seq\" OWNER TO \"vscan\";

ALTER SEQUENCE \"public\".\"policy_id_seq\" OWNED BY \"public\".\"policy\".\"id\";

CREATE TABLE \"public\".\"users\" (
    \"id\" integer NOT NULL,
    \"priority\" integer DEFAULT 7 NOT NULL,
    \"policy_id\" integer DEFAULT 1 NOT NULL,
    \"email\" \"bytea\" NOT NULL,
    \"fullname\" character varying(255) DEFAULT NULL::character varying,
    CONSTRAINT \"users_policy_id_check\" CHECK ((\"policy_id\" >= 0))
);

ALTER TABLE \"public\".\"users\" OWNER TO \"vscan\";

CREATE SEQUENCE \"public\".\"users_id_seq\"
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE \"public\".\"users_id_seq\" OWNER TO \"vscan\";

ALTER SEQUENCE \"public\".\"users_id_seq\" OWNED BY \"public\".\"users\".\"id\";

CREATE TABLE \"public\".\"wblist\" (
    \"rid\" integer NOT NULL,
    \"sid\" integer NOT NULL,
    \"wb\" character varying(10) NOT NULL,
    CONSTRAINT \"wblist_rid_check\" CHECK ((\"rid\" >= 0)),
    CONSTRAINT \"wblist_sid_check\" CHECK ((\"sid\" >= 0))
);

ALTER TABLE \"public\".\"wblist\" OWNER TO \"vscan\";

ALTER TABLE ONLY \"public\".\"mailaddr\" ALTER COLUMN \"id\" SET DEFAULT \"nextval\"('\"public\".\"mailaddr_id_seq\"'::\"regclass\");
ALTER TABLE ONLY \"public\".\"policy\" ALTER COLUMN \"id\" SET DEFAULT \"nextval\"('\"public\".\"policy_id_seq\"'::\"regclass\");
ALTER TABLE ONLY \"public\".\"users\" ALTER COLUMN \"id\" SET DEFAULT \"nextval\"('\"public\".\"users_id_seq\"'::\"regclass\");

SELECT pg_catalog.setval('\"public\".\"mailaddr_id_seq\"', 1, false);
SELECT pg_catalog.setval('\"public\".\"policy_id_seq\"', 1, false);
SELECT pg_catalog.setval('\"public\".\"users_id_seq\"', 1, false);

ALTER TABLE ONLY \"public\".\"mailaddr\"
    ADD CONSTRAINT \"mailaddr_email_key\" UNIQUE (\"email\");

ALTER TABLE ONLY \"public\".\"mailaddr\"
    ADD CONSTRAINT \"mailaddr_pkey\" PRIMARY KEY (\"id\");

ALTER TABLE ONLY \"public\".\"policy\"
    ADD CONSTRAINT \"policy_pkey\" PRIMARY KEY (\"id\");

ALTER TABLE ONLY \"public\".\"users\"
    ADD CONSTRAINT \"users_email_key\" UNIQUE (\"email\");

ALTER TABLE ONLY \"public\".\"users\"
    ADD CONSTRAINT \"users_pkey\" PRIMARY KEY (\"id\");

ALTER TABLE ONLY \"public\".\"wblist\"
    ADD CONSTRAINT \"wblist_pkey\" PRIMARY KEY (\"rid\", \"sid\");

ALTER TABLE ONLY \"public\".\"users\"
    ADD CONSTRAINT \"users_policy_id_fkey\" FOREIGN KEY (\"policy_id\") REFERENCES \"public\".\"policy\"(\"id\");

ALTER TABLE ONLY \"public\".\"wblist\"
    ADD CONSTRAINT \"wblist_rid_fkey\" FOREIGN KEY (\"rid\") REFERENCES \"public\".\"users\"(\"id\");

ALTER TABLE ONLY \"public\".\"wblist\"
    ADD CONSTRAINT \"wblist_sid_fkey\" FOREIGN KEY (\"sid\") REFERENCES \"public\".\"mailaddr\"(\"id\");
EOF"
```

### Verbindung als `vscan` testen

```shell
psql -h 127.0.0.1 -U vscan -d mail_prefs -c 'SELECT current_user, current_database();'
```

### PostgreSQL-Datenbank `mail_log` für `vscan` anlegen

Die Storage-Datenbank ist der Schreibteil des Amavis-Schemas und wird für Logging, Reporting und optional Quarantäne verwendet. Upstream weist ausdrücklich darauf hin, dass dieser Teil Transaktionen und Schreibrechte benötigt. ([GitHub][8])

```shell
su -l postgres -c "psql <<'EOF'
DROP DATABASE IF EXISTS \"mail_log\";
CREATE DATABASE \"mail_log\";
ALTER DATABASE \"mail_log\" OWNER TO \"vscan\";
EOF"

su -l postgres -c "psql <<'EOF'
\connect "mail_log"
CREATE TABLE \"public\".\"maddr\" (
    \"id\" integer NOT NULL,
    \"partition_tag\" integer DEFAULT 0,
    \"email\" \"bytea\" NOT NULL,
    \"domain\" character varying(255) NOT NULL
);

ALTER TABLE \"public\".\"maddr\" OWNER TO \"vscan\";

CREATE SEQUENCE \"public\".\"maddr_id_seq\"
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE \"public\".\"maddr_id_seq\" OWNER TO \"vscan\";

ALTER SEQUENCE \"public\".\"maddr_id_seq\" OWNED BY \"public\".\"maddr\".\"id\";

CREATE TABLE \"public\".\"msgrcpt\" (
    \"partition_tag\" integer DEFAULT 0 NOT NULL,
    \"mail_id\" \"bytea\" NOT NULL,
    \"rseqnum\" integer DEFAULT 0 NOT NULL,
    \"rid\" integer NOT NULL,
    \"is_local\" character(1) DEFAULT ' '::\"bpchar\" NOT NULL,
    \"content\" character(1) DEFAULT ' '::\"bpchar\" NOT NULL,
    \"ds\" character(1) NOT NULL,
    \"rs\" character(1) NOT NULL,
    \"bl\" character(1) DEFAULT ' '::\"bpchar\",
    \"wl\" character(1) DEFAULT ' '::\"bpchar\",
    \"bspam_level\" real,
    \"smtp_resp\" character varying(255) DEFAULT ''::character varying
);

ALTER TABLE \"public\".\"msgrcpt\" OWNER TO \"vscan\";

CREATE TABLE \"public\".\"msgs\" (
    \"partition_tag\" integer DEFAULT 0 NOT NULL,
    \"mail_id\" \"bytea\" NOT NULL,
    \"secret_id\" \"bytea\" DEFAULT '\x'::\"bytea\",
    \"am_id\" character varying(20) NOT NULL,
    \"time_num\" integer NOT NULL,
    \"time_iso\" timestamp with time zone NOT NULL,
    \"sid\" integer NOT NULL,
    \"policy\" character varying(255) DEFAULT ''::character varying,
    \"client_addr\" character varying(255) DEFAULT ''::character varying,
    \"size\" integer NOT NULL,
    \"originating\" character(1) DEFAULT ' '::\"bpchar\" NOT NULL,
    \"content\" character(1),
    \"quar_type\" character(1),
    \"quar_loc\" character varying(255) DEFAULT ''::character varying,
    \"dsn_sent\" character(1),
    \"spam_level\" real,
    \"message_id\" character varying(255) DEFAULT ''::character varying,
    \"from_addr\" character varying(255) DEFAULT ''::character varying,
    \"subject\" character varying(255) DEFAULT ''::character varying,
    \"host\" character varying(255) NOT NULL,
    CONSTRAINT \"msgs_sid_check\" CHECK ((\"sid\" >= 0)),
    CONSTRAINT \"msgs_size_check\" CHECK ((\"size\" >= 0)),
    CONSTRAINT \"msgs_time_num_check\" CHECK ((\"time_num\" >= 0))
);

ALTER TABLE \"public\".\"msgs\" OWNER TO \"vscan\";

CREATE TABLE \"public\".\"quarantine\" (
    \"partition_tag\" integer DEFAULT 0 NOT NULL,
    \"mail_id\" \"bytea\" NOT NULL,
    \"chunk_ind\" integer NOT NULL,
    \"mail_text\" \"bytea\" NOT NULL,
    CONSTRAINT \"quarantine_chunk_ind_check\" CHECK ((\"chunk_ind\" >= 0))
);

ALTER TABLE \"public\".\"quarantine\" OWNER TO \"vscan\";

ALTER TABLE ONLY \"public\".\"maddr\" ALTER COLUMN \"id\" SET DEFAULT \"nextval\"('\"public\".\"maddr_id_seq\"'::\"regclass\");

SELECT pg_catalog.setval('\"public\".\"maddr_id_seq\"', 283, true);

ALTER TABLE ONLY \"public\".\"maddr\"
    ADD CONSTRAINT \"maddr_pkey\" PRIMARY KEY (\"id\");

ALTER TABLE ONLY \"public\".\"msgrcpt\"
    ADD CONSTRAINT \"msgrcpt_partition_mail_rseq\" PRIMARY KEY (\"partition_tag\", \"mail_id\", \"rseqnum\");

ALTER TABLE ONLY \"public\".\"msgs\"
    ADD CONSTRAINT \"msgs_partition_mail\" PRIMARY KEY (\"partition_tag\", \"mail_id\");

ALTER TABLE ONLY \"public\".\"maddr\"
    ADD CONSTRAINT \"part_email\" UNIQUE (\"partition_tag\", \"email\");

ALTER TABLE ONLY \"public\".\"quarantine\"
    ADD CONSTRAINT \"quarantine_pkey\" PRIMARY KEY (\"partition_tag\", \"mail_id\", \"chunk_ind\");

CREATE INDEX \"msgrcpt_idx_mail_id\" ON \"public\".\"msgrcpt\" USING \"btree\" (\"mail_id\");
CREATE INDEX \"msgrcpt_idx_rid\" ON \"public\".\"msgrcpt\" USING \"btree\" (\"rid\");
CREATE INDEX \"msgs_idx_mess_id\" ON \"public\".\"msgs\" USING \"btree\" (\"message_id\");
CREATE INDEX \"msgs_idx_sid\" ON \"public\".\"msgs\" USING \"btree\" (\"sid\");
CREATE INDEX \"msgs_idx_time_iso\" ON \"public\".\"msgs\" USING \"btree\" (\"time_iso\");
CREATE INDEX \"msgs_idx_time_num\" ON \"public\".\"msgs\" USING \"btree\" (\"time_num\");
EOF"
```

### Verbindung als `vscan` testen

```shell
psql -h 127.0.0.1 -U vscan -d mail_log -c 'SELECT current_user, current_database();'
```

---

## Zusatzsoftware

### Optional: p0f-Analyzer

Der aktuelle Port liefert ein separates rc.d-Skript **`amavis_p0fanalyzer`** mit. Dieses ist optional und nur sinnvoll, wenn dein Port auch wirklich mit **`P0F`** gebaut wurde. Das rc.d-Skript verwendet die Variablen `amavis_p0fanalyzer_enable`, `amavis_p0fanalyzer_p0f_filter` und optional `amavis_p0fanalyzer_flags`. ([FreeBSD Git][9])

```sh
sysrc amavis_p0fanalyzer_enable="YES"
sysrc amavis_p0fanalyzer_p0f_filter="tcp dst port 25"
```

### Optional: Amavis-Milter

Nicht Bestandteil dieses HowTos.

Für einen Milter-basierten Betrieb gibt es heute auf FreeBSD den **separaten Port** `security/amavisd-milter`. Das aktuelle `security/amavisd-new` liefert dafür **kein** eigenes rc.d-Skript mehr mit. ([FreshPorts][10])

---

## Aufräumen

Überflüssige oder temporäre Verzeichnisse und Dateien entsorgen.

### Zusatzsoftware Installation

Nicht erforderlich.

### Zusatzsoftware Konfiguration

Nicht erforderlich.

---

## Abschluss

Wichtig ist die Reihenfolge: zuerst `amavisd-new`, danach `amavisd-milter`, anschließend Postfix neu laden.

```sh
service amavisd start
service amavisd-milter start
service postfix reload
```

Für spätere Änderungen:

```sh
service amavisd restart
service amavisd-milter restart
service postfix reload
```

Optional bei aktiviertem p0f-Analyzer:

```sh
service amavisd start
service amavis_p0fanalyzer start
service amavisd-milter start
service postfix reload
```

Für spätere Änderungen:

```sh
service amavisd restart
service amavis_p0fanalyzer restart
service amavisd-milter restart
service postfix reload
```

---

## Referenzen

* FreshPorts: `security/amavisd-new` — Portstand, Optionen, Sample-Konfigurationen, pkg-message. ([FreshPorts][1])
* FreshPorts: `security/amavisd-milter`
* FreeBSD Ports Tree: rc.d-Skripte `amavisd`, `amavis_p0fanalyzer`, `amavisd_snmp`. ([FreeBSD Git][11])
* FreeBSD Ports rc-Skript: `security/amavisd-milter/files/amavisd-milter.in`
* FreeBSD Manpage: `amavisd-milter(8)`
* Amavis Dokumentation: Allgemeine Einführung und Postfix-Integration. ([IJS][2])
* Amavis SQL-Dokumentation: `README.sql` und `README.sql-pg` für `@lookup_sql_dsn`, `@storage_sql_dsn`, Rechte und PostgreSQL-Schema. ([GitHub][8])
* Amavis DKIM-Dokumentation: `genrsa`, `showkeys`, `testkeys`. ([Amavis][3])
* Amavis INSTALL: erster Start mit `debug`. ([Amavis][7])
* Postfix: `MILTER_README`

[1]: https://www.freshports.org/security/amavisd-new/ "security/amavisd-new: Mail scanner interface between ..."
[2]: https://www.ijs.si/software/amavisd/ "amavisd-new"
[3]: https://amavis.org/amavisd-new-docs.html "amavisd-new documentation bits and pieces"
[4]: https://cgit.freebsd.org/ports/tree/security/amavisd-new/files/amavisd.in "amavisd.in « files « amavisd-new « security - ports - FreeBSD ports tree"
[5]: https://www.freshports.org/security/amavisd-new/ "FreshPorts -- security/amavisd-new: Mail scanner interface between mailer and content checkers"
[6]: https://cgit.freebsd.org/ports/tree/security/amavisd-new/files/pkg-message.in "pkg-message.in « files « amavisd-new « security - ports - FreeBSD ports tree"
[7]: https://amavis.org/INSTALL.txt "INSTALL.txt"
[8]: https://github.com/srault95/amavisd-new/blob/master/README_FILES/README.sql "amavisd-new/README_FILES/README.sql at master · srault95/amavisd-new · GitHub"
[9]: https://cgit.freebsd.org/ports/tree/security/amavisd-new/files/amavis_p0fanalyzer.in "amavis_p0fanalyzer.in « files « amavisd-new « security - ports - FreeBSD ports tree"
[10]: https://www.freshports.org/security/amavisd-milter/ "pkg install security/amavisd-milter"
[11]: https://cgit.freebsd.org/ports/tree/security/amavisd-new/files "files « amavisd-new « security - ports - FreeBSD ports tree"
[12]: https://www.freshports.org/security/amavisd-milter/ "FreshPorts -- security/amavisd-milter: Milter for amavisd-new"
[13]: https://www.postfix.org/MILTER_README.html "Postfix before-queue Milter support"
[14]: https://amavisd-milter.sourceforge.net/amavisd-milter.html "amavisd-milter(8) manual page"
[15]: https://raw.githubusercontent.com/freebsd/freebsd-ports/main/security/amavisd-milter/files/amavisd-milter.in "raw.githubusercontent.com"
[16]: https://raw.githubusercontent.com/freebsd/freebsd-ports/main/security/amavisd-milter/Makefile "raw.githubusercontent.com"
[17]: https://amavisd-milter.sourceforge.net/amavisd-milter.html "amavisd-milter(8) manual page"
[18]: https://man.freebsd.org/cgi/man.cgi?manpath=freebsd-ports&query=amavisd-milter&sektion=8 "amavisd-milter(8)"
