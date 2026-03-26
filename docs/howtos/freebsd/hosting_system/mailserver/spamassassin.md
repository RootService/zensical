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
title: SpamAssassin
description: In diesem HowTo wird Schritt für Schritt die Installation von SpamAssassin für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# SpamAssassin

## Inhalt

* SpamAssassin 4.0.2
* SpamAss-Milter 0.4.0
* `spamd` als Dienst
* PostgreSQL für Bayes und TxRep
* Regelupdates über `sa-update`
* kompilierte Regeln über `sa-compile`

---

## Einleitung

Dieses HowTo beschreibt die Installation und Konfiguration von SpamAssassin auf FreeBSD 15+ für ein Mailsetup mit Postfix.

Die aktuelle FreeBSD-Portbasis ist **`mail/spamassassin` 4.0.2**. Der Port installiert unter anderem `spamd`, `spamc`, `sa-update`, `sa-compile` sowie die Sample-Dateien `local.cf.sample` und `init.pre.sample`. Für die Anbindung an Postfix wird in diesem HowTo zusätzlich **`mail/spamass-milter` 0.4.0** verwendet. ([FreshPorts][1])

Dieses Setup verwendet **PostgreSQL** für die Bayes-Datenbank und für **TxRep**. Für PostgreSQL ist bei Bayes nicht das generische SQL-Backend die richtige Wahl, sondern ausdrücklich **`Mail::SpamAssassin::BayesStore::PgSQL`**. Für TxRep erwartet SpamAssassin standardmäßig eine Tabelle mit dem Namen **`txrep`**. ([spamassassin.apache.org][2])

---

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

Zusätzlich gilt für dieses HowTo:

* PostgreSQL ist bereits installiert und erreichbar.
* Postfix ist bereits installiert.
* Für die spätere Milter-Anbindung ist Postfix bereits für Milter vorbereitet.
* Für dieses HowTo wird PostgreSQL für Bayes und TxRep verwendet.
* Dieses HowTo richtet **keinen** Content-Filter-Proxydienst ein, sondern `spamd` plus `spamass-milter`.

---

## Vorbereitungen

### DNS Records

Für dieses HowTo sind **keine zusätzlichen DNS-Records** erforderlich.

### Gruppen / Benutzer / Passwörter

Für dieses HowTo müssen **keine zusätzlichen Systemgruppen oder Systembenutzer manuell angelegt** werden.

Der Port bringt den Systembenutzer **`spamd`** selbst mit; im Paketinhalt ist außerdem das Laufzeitverzeichnis `/var/run/spamd` bereits mit Eigentümer `spamd:spamd` vorgesehen. ([GitHub][3])

Für dieses HowTo muss zuvor folgendes Passwort angelegt werden, sofern es noch nicht existiert, oder entsprechend geändert werden, sofern es bereits existiert.

``` sh
```

### Verzeichnisse / Dateien

Für dieses HowTo müssen zuvor folgende Verzeichnisse angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` sh
```

---

## Installation

### Wir installieren `mail/spamassassin` und dessen Abhängigkeiten.

Für PostgreSQL-Bayes ist die Portoption **`PGSQL`** relevant. Der aktuelle Port bietet außerdem unter anderem die Optionen `SPF_QUERY`, `DKIM`, `DMARC` und `GNUPG2`. ([FreshPorts][1])

``` sh
mkdir -p /var/db/ports/databases_p5-DBD-SQLite
cat <<'EOF' > /var/db/ports/databases_p5-DBD-SQLite/options
--8<-- "freebsd/ports/databases_p5-DBD-SQLite/options"
EOF

mkdir -p /var/db/ports/databases_p5-DBIx-Simple
cat <<'EOF' > /var/db/ports/databases_p5-DBIx-Simple/options
--8<-- "freebsd/ports/databases_p5-DBIx-Simple/options"
EOF

mkdir -p /var/db/ports/dns_p5-Net-DNS
cat <<'EOF' > /var/db/ports/dns_p5-Net-DNS/options
--8<-- "freebsd/ports/dns_p5-Net-DNS/options"
EOF

mkdir -p /var/db/ports/dns_libidn
cat <<'EOF' > /var/db/ports/dns_libidn/options
--8<-- "freebsd/ports/dns_libidn/options"
EOF

mkdir -p /var/db/ports/devel_p5-Parse-RecDescent
cat <<'EOF' > /var/db/ports/devel_p5-Parse-RecDescent/options
--8<-- "freebsd/ports/devel_p5-Parse-RecDescent/options"
EOF

mkdir -p /var/db/ports/net_p5-Net-Server
cat <<'EOF' > /var/db/ports/net_p5-Net-Server/options
--8<-- "freebsd/ports/net_p5-Net-Server/options"
EOF

mkdir -p /var/db/ports/devel_p5-Test-NoWarnings
cat <<'EOF' > /var/db/ports/devel_p5-Test-NoWarnings/options
--8<-- "freebsd/ports/devel_p5-Test-NoWarnings/options"
EOF

mkdir -p /var/db/ports/www_p5-CGI
cat <<'EOF' > /var/db/ports/www_p5-CGI/options
--8<-- "freebsd/ports/www_p5-CGI/options"
EOF

mkdir -p /var/db/ports/www_p5-HTTP-Tiny
cat <<'EOF' > /var/db/ports/www_p5-HTTP-Tiny/options
--8<-- "freebsd/ports/www_p5-HTTP-Tiny/options"
EOF

mkdir -p /var/db/ports/mail_spamassassin
cat <<'EOF' > /var/db/ports/mail_spamassassin/options
--8<-- "freebsd/ports/mail_spamassassin/options"
EOF

portmaster -w -B -g -U --force-config mail/spamassassin -n
```

### Dienst in `rc.conf` eintragen

Der Dienst wird mittels `sysrc` in der `rc.conf` eingetragen und dadurch beim Systemstart automatisch gestartet.

Das rc.d-Skript heißt **`sa-spamd`**, die passende `rc.conf`-Variable bleibt aber **`spamd_enable`**. ([FreshPorts][1])

``` sh
sysrc spamd_enable="YES"
sysrc spamd_flags="--create-prefs --max-children 5 --helper-home-dir --nouser-config --virtual-config-dir=/var/vmail/%d/%l/spamassassin --username=vmail"
```

---

## Konfiguration

### Konfigurationsdateien

Der Port liefert die Sample-Dateien `local.cf.sample` und `init.pre.sample` bereits mit. Für dieses Setup ist das die saubere Basis. ([FreshPorts][1])

#### `local.cf` einrichten

``` sh
cat <<'EOF' > /usr/local/etc/mail/spamassassin/local.cf
--8<-- "freebsd/configs/usr/local/etc/mail/spamassassin/local.cf"
EOF
```

#### `init.pre` einrichten

``` sh
cat <<'EOF' > /usr/local/etc/mail/spamassassin/init.pre
--8<-- "freebsd/configs/usr/local/etc/mail/spamassassin/init.pre"
EOF
```

Für PostgreSQL-Bayes muss in `local.cf` das PostgreSQL-spezifische Backend verwendet werden. Das generische SQL-Backend ist für PostgreSQL ausdrücklich **nicht** das richtige Modul. Für TxRep gilt zusätzlich: Standardmäßig wird die SQL-Tabelle **`txrep`** erwartet. ([spamassassin.apache.org][2])

#### Platzhalter in `local.cf` ersetzen

``` sh
# 1. Get Default Interface
DEF_IF="$(route -n get -inet default | awk '/interface:/ {print $2}')"

# 2. Get IPv4 IP
IP4="$(ifconfig "$DEF_IF" inet | awk '/inet / && $2 !~ /^127\./ {print $2}' | head -n 1)"
[ -n "$IP4" ] && sed -e "s|__IPADDR4__|$IP4|g" -i '' /usr/local/etc/mail/spamassassin/local.cf

# 3. Get IPv6 IP
IP6="$(ifconfig "$DEF_IF" inet6 | awk '/inet6 / && $2 !~ /^fe80:/ && $2 !~ /^::1/ {print $2}' | head -n 1)"
[ -n "$IP6" ] && sed -e "s|__IPADDR6__|$IP6|g" -i '' /usr/local/etc/mail/spamassassin/local.cf

cat /var/db/passwords/postgresql_user_spamass | xargs -I % \
  sed -e "s|__PASSWORD_SPAMASS__|%|g" -i '' /usr/local/etc/mail/spamassassin/local.cf
```

### Regelupdates und kompilierte Regeln

`sa-update` lädt und installiert aktualisierte Regeln. Der Standardkanal ist **`updates.spamassassin.org`**. `sa-update` startet `spamd` danach **nicht** neu. `sa-compile` kompiliert Regeln, lädt sie aber ebenfalls nicht automatisch neu; zusätzlich muss dafür das Plugin `Rule2XSBody` aktiv sein. Auf FreeBSD gilt außerdem der aktuelle Port-Hinweis: Nach einem Port-Update zuerst `sa-update` laufen lassen und **danach** erst `sa-spamd` neu starten. ([spamassassin.apache.org][4])

``` sh
/usr/local/bin/sa-update --channel updates.spamassassin.org --refreshmirrors --verbose
/usr/local/bin/sa-update --channel updates.spamassassin.org --verbose
/usr/local/bin/sa-compile --quiet
```

Zusätzliche Drittanbieter-Regelkanäle sind möglich, gehören aber nicht in dieses Basis-HowTo. Der Port verweist dafür allgemein auf zusätzliche Drop-in-Regelsätze, SpamAssassin selbst behandelt `updates.spamassassin.org` als Standardkanal. ([FreshPorts][1])

### Wartungsscript für Updates

``` sh
cat <<'EOF' > /usr/local/sbin/update-spamassassin
--8<-- "freebsd/configs/usr/local/sbin/update-spamassassin"
EOF
chmod 755 /usr/local/sbin/update-spamassassin
```

### Konfiguration prüfen

Vor dem ersten Start sollte die Konfiguration immer geprüft werden.

``` sh
spamassassin --lint
service sa-spamd start
sockstat -4 -6 -l | egrep 'spamd|783'
```

---

## Datenbanken

### PostgreSQL-Benutzer `spamass` anlegen

Für SpamAssassin reicht ein normaler Login-Benutzer. Zusätzliche Rechte wie `CREATEROLE` oder `CREATEDB` sind dafür nicht erforderlich.

``` sh
# Passwort für PostgreSQL-Benutzer "spamass" erzeugen und
# in /var/db/passwords/postgresql_user_spamass speichern
install -b -m 0600 -o postgres -g postgres /dev/null /var/db/passwords/postgresql_user_spamass
openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | \
  cut -c 2-17 | tee /var/db/passwords/postgresql_user_spamass

# PostgreSQL-Benutzer "spamass" mit Passwort anlegen
su -l postgres -c "psql <<'EOF'
\set content `cat /var/db/passwords/postgresql_user_spamass`
DROP ROLE IF EXISTS \"spamass\";
CREATE ROLE \"spamass\";
ALTER ROLE \"spamass\" WITH NOSUPERUSER INHERIT CREATEROLE CREATEDB LOGIN NOREPLICATION NOBYPASSRLS PASSWORD :'content';
\unset content
EOF"
```

Das Passwort bitte **sicher** notieren, du wirst es bei jeder externen Verbindung über TCP benötigen.

### PostgreSQL-Datenbank `mail_bayes` für `spamass` anlegen

Für PostgreSQL-Bayes ist das PostgreSQL-spezifische Bayes-Backend zuständig. Das ist genau für BYTEA-basierte Token-Speicherung in PostgreSQL gedacht. ([spamassassin.apache.org][2])

``` sh
su -l postgres -c "psql <<'EOF'
DROP DATABASE IF EXISTS \"mail_bayes\";
CREATE DATABASE \"mail_bayes\";
ALTER DATABASE \"mail_bayes\" OWNER TO \"spamass\";
EOF"
```

### Schema für `mail_bayes` einspielen

``` sh
su -l postgres -c "psql <<'EOF'
\connect \"mail_bayes\"

CREATE OR REPLACE FUNCTION \"public\".\"greatest_int\"(integer, integer) RETURNS integer
    LANGUAGE \"sql\" IMMUTABLE STRICT
    AS \$_\$SELECT CASE WHEN \$1 < \$2 THEN \$2 ELSE \$1 END;\$_\$;

ALTER FUNCTION \"public\".\"greatest_int\"(integer, integer) OWNER TO \"spamass\";

CREATE OR REPLACE FUNCTION \"public\".\"least_int\"(integer, integer) RETURNS integer
    LANGUAGE \"sql\" IMMUTABLE STRICT
    AS \$_\$SELECT CASE WHEN \$1 < \$2 THEN \$1 ELSE \$2 END;\$_\$;

ALTER FUNCTION \"public\".\"least_int\"(integer, integer) OWNER TO \"spamass\";

CREATE OR REPLACE FUNCTION \"public\".\"put_tokens\"(integer, \"bytea\"[], integer, integer, integer) RETURNS \"void\"
    LANGUAGE \"plpgsql\"
    AS \$_\$
DECLARE
  inuserid      ALIAS FOR \$1;
  intokenary    ALIAS FOR \$2;
  inspam_count  ALIAS FOR \$3;
  inham_count   ALIAS FOR \$4;
  inatime       ALIAS FOR \$5;
  _token BYTEA;
  new_tokens INTEGER := 0;
BEGIN
  for i in array_lower(intokenary, 1) .. array_upper(intokenary, 1)
  LOOP
    _token := intokenary[i];
    UPDATE bayes_token
       SET spam_count = greatest_int(spam_count + inspam_count, 0),
           ham_count = greatest_int(ham_count + inham_count, 0),
           atime = greatest_int(atime, inatime)
     WHERE id = inuserid
       AND token = _token;
    IF NOT FOUND THEN
      IF NOT (inspam_count < 0 OR inham_count < 0) THEN
        INSERT INTO bayes_token (id, token, spam_count, ham_count, atime)
        VALUES (inuserid, _token, inspam_count, inham_count, inatime);
        IF FOUND THEN
          new_tokens := new_tokens + 1;
        END IF;
      END IF;
    END IF;
  END LOOP;

  IF new_tokens > 0 AND inatime > 0 THEN
    UPDATE bayes_vars
       SET token_count = token_count + new_tokens,
           newest_token_age = greatest_int(newest_token_age, inatime),
           oldest_token_age = least_int(oldest_token_age, inatime)
     WHERE id = inuserid;
  ELSIF new_tokens > 0 AND NOT inatime > 0 THEN
    UPDATE bayes_vars
       SET token_count = token_count + new_tokens
     WHERE id = inuserid;
  ELSIF NOT new_tokens > 0 AND inatime > 0 THEN
    UPDATE bayes_vars
       SET newest_token_age = greatest_int(newest_token_age, inatime),
           oldest_token_age = least_int(oldest_token_age, inatime)
     WHERE id = inuserid;
  END IF;
  RETURN;
END;
\$_\$;

ALTER FUNCTION \"public\".\"put_tokens\"(integer, \"bytea\"[], integer, integer, integer) OWNER TO \"spamass\";

CREATE TABLE \"public\".\"bayes_expire\" (
    \"id\" integer DEFAULT 0 NOT NULL,
    \"runtime\" integer DEFAULT 0 NOT NULL
);

ALTER TABLE \"public\".\"bayes_expire\" OWNER TO \"spamass\";

CREATE TABLE \"public\".\"bayes_global_vars\" (
    \"variable\" character varying(30) DEFAULT ''::character varying NOT NULL,
    \"value\" character varying(200) DEFAULT ''::character varying NOT NULL
);

ALTER TABLE \"public\".\"bayes_global_vars\" OWNER TO \"spamass\";

CREATE TABLE \"public\".\"bayes_seen\" (
    \"id\" integer DEFAULT 0 NOT NULL,
    \"msgid\" character varying(200) DEFAULT ''::character varying NOT NULL,
    \"flag\" character(1) DEFAULT ''::\"bpchar\" NOT NULL
);

ALTER TABLE \"public\".\"bayes_seen\" OWNER TO \"spamass\";

CREATE TABLE \"public\".\"bayes_token\" (
    \"id\" integer DEFAULT 0 NOT NULL,
    \"token\" \"bytea\" DEFAULT '\\x'::\"bytea\" NOT NULL,
    \"spam_count\" integer DEFAULT 0 NOT NULL,
    \"ham_count\" integer DEFAULT 0 NOT NULL,
    \"atime\" integer DEFAULT 0 NOT NULL
)
WITH (\"fillfactor\"='95');

ALTER TABLE \"public\".\"bayes_token\" OWNER TO \"spamass\";

CREATE TABLE \"public\".\"bayes_vars\" (
    \"id\" integer NOT NULL,
    \"username\" character varying(200) DEFAULT ''::character varying NOT NULL,
    \"spam_count\" integer DEFAULT 0 NOT NULL,
    \"ham_count\" integer DEFAULT 0 NOT NULL,
    \"token_count\" integer DEFAULT 0 NOT NULL,
    \"last_expire\" integer DEFAULT 0 NOT NULL,
    \"last_atime_delta\" integer DEFAULT 0 NOT NULL,
    \"last_expire_reduce\" integer DEFAULT 0 NOT NULL,
    \"oldest_token_age\" integer DEFAULT 2147483647 NOT NULL,
    \"newest_token_age\" integer DEFAULT 0 NOT NULL
);

ALTER TABLE \"public\".\"bayes_vars\" OWNER TO \"spamass\";

CREATE SEQUENCE \"public\".\"bayes_vars_id_seq\"
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

ALTER SEQUENCE \"public\".\"bayes_vars_id_seq\" OWNER TO \"spamass\";
ALTER SEQUENCE \"public\".\"bayes_vars_id_seq\" OWNED BY \"public\".\"bayes_vars\".\"id\";

ALTER TABLE ONLY \"public\".\"bayes_vars\" ALTER COLUMN \"id\" SET DEFAULT \"nextval\"('\"public\".\"bayes_vars_id_seq\"'::\"regclass\");
SELECT pg_catalog.setval('\"public\".\"bayes_vars_id_seq\"', 1, false);

ALTER TABLE ONLY \"public\".\"bayes_global_vars\"
    ADD CONSTRAINT \"bayes_global_vars_pkey\" PRIMARY KEY (\"variable\");

ALTER TABLE ONLY \"public\".\"bayes_seen\"
    ADD CONSTRAINT \"bayes_seen_pkey\" PRIMARY KEY (\"id\", \"msgid\");

ALTER TABLE ONLY \"public\".\"bayes_token\"
    ADD CONSTRAINT \"bayes_token_pkey\" PRIMARY KEY (\"id\", \"token\");

ALTER TABLE ONLY \"public\".\"bayes_vars\"
    ADD CONSTRAINT \"bayes_vars_pkey\" PRIMARY KEY (\"id\");

CREATE INDEX \"bayes_expire_idx1\" ON \"public\".\"bayes_expire\" USING \"btree\" (\"id\");
CREATE INDEX \"bayes_token_idx1\" ON \"public\".\"bayes_token\" USING \"btree\" (\"token\");
CREATE UNIQUE INDEX \"bayes_vars_idx1\" ON \"public\".\"bayes_vars\" USING \"btree\" (\"username\");

INSERT INTO \"bayes_global_vars\" VALUES ('VERSION','3');
EOF"
```

### Verbindung als `spamass` testen

``` sh
psql -h 127.0.0.1 -U spamass -d mail_bayes -c 'SELECT current_user, current_database();'
```

### PostgreSQL-Datenbank `mail_txrep` für `spamass` anlegen

TxRep verwendet dieselbe SQL-Architektur wie das frühere AWL-Plugin, erwartet aber standardmäßig eine Tabelle mit dem Namen **`txrep`**. Für PostgreSQL beschreibt die offizielle Dokumentation dazu den Import über ein PostgreSQL-Schema und empfiehlt außerdem, alte Einträge regelmäßig zu bereinigen. ([spamassassin.apache.org][5])

``` sh
su -l postgres -c "psql <<'EOF'
DROP DATABASE IF EXISTS \"mail_txrep\";
CREATE DATABASE \"mail_txrep\";
ALTER DATABASE \"mail_txrep\" OWNER TO \"spamass\";
EOF"
```

### Schema für `mail_txrep` einspielen

``` sh
su -l postgres -c "psql <<'EOF'
\connect \"mail_txrep\"

CREATE OR REPLACE FUNCTION \"public\".\"update_txrep_last_hit\"() RETURNS \"trigger\"
    LANGUAGE \"plpgsql\"
    AS \$\$
BEGIN
  NEW.last_hit = CURRENT_TIMESTAMP;
  RETURN NEW;
END;
\$\$;

ALTER FUNCTION \"public\".\"update_txrep_last_hit\"() OWNER TO \"spamass\";

CREATE TABLE \"public\".\"txrep\" (
    \"username\" character varying(100) DEFAULT ''::character varying NOT NULL,
    \"email\" character varying(255) DEFAULT ''::character varying NOT NULL,
    \"ip\" character varying(40) DEFAULT ''::character varying NOT NULL,
    \"msgcount\" bigint DEFAULT '0'::bigint NOT NULL,
    \"totscore\" double precision DEFAULT '0'::double precision NOT NULL,
    \"signedby\" character varying(255) DEFAULT ''::character varying NOT NULL,
    \"last_hit\" timestamp without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
)
WITH (\"fillfactor\"='95');

ALTER TABLE \"public\".\"txrep\" OWNER TO \"spamass\";

ALTER TABLE ONLY \"public\".\"txrep\"
    ADD CONSTRAINT \"txrep_pkey\" PRIMARY KEY (\"username\", \"email\", \"signedby\", \"ip\");

CREATE INDEX \"txrep_last_hit\" ON \"public\".\"txrep\" USING \"btree\" (\"last_hit\");

CREATE TRIGGER \"update_txrep_update_last_hit\" BEFORE UPDATE ON \"public\".\"txrep\" FOR EACH ROW EXECUTE FUNCTION \"public\".\"update_txrep_last_hit\"();
EOF"
```

### Verbindung als `spamass` testen

``` sh
psql -h 127.0.0.1 -U spamass -d mail_txrep -c 'SELECT current_user, current_database();'
```

---

## Zusatzsoftware

Mögliche Zusatzsoftware wird hier installiert und konfiguriert.

### Wir installieren `mail/spamass-milter` und dessen Abhängigkeiten.

`mail/spamass-milter` bringt auf FreeBSD ein eigenes rc.d-Skript **`spamass-milter`** mit. Der aktuelle Portstand ist **0.4.0_5**. ([FreshPorts][6])

``` sh
mkdir -p /var/db/ports/mail_spamass-milter
cat <<'EOF' > /var/db/ports/mail_spamass-milter/options
--8<-- "freebsd/ports/mail_spamass-milter/options"
EOF

portmaster -w -B -g -U --force-config mail/spamass-milter -n

mkdir -p /var/spool/postfix/spamass
chown spamd:wheel /var/spool/postfix/spamass
chmod 770 /var/spool/postfix/spamass
pw groupmod spamd -m postfix
```

### Dienst in `rc.conf` eintragen

``` sh
sysrc spamass_milter_enable="YES"
sysrc spamass_milter_user="spamd"
sysrc spamass_milter_group="spamd"
sysrc spamass_milter_socket="/var/spool/postfix/spamass/spamass.sock"
sysrc spamass_milter_socket_owner="postfix"
sysrc spamass_milter_socket_group="postfix"
sysrc spamass_milter_socket_mode="660"
sysrc spamass_milter_localflags="-e example.com -u spamd -i 127.0.0.1 -R REJECTED_AS_SPAM -r 10 -- --max-size=5120000"
```

### Zusatzsoftware Konfiguration prüfen

``` sh
service spamass-milter start
service spamass-milter status
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

SpamAssassin kann nun gestartet werden.

``` sh
service sa-spamd start
service spamass-milter start
```

Für spätere Änderungen:

``` sh
service sa-spamd restart
service spamass-milter restart
```

Für Funktionstests danach:

``` sh
sockstat -4 -6 -l | egrep 'spamd|spamass-milter'
spamc -R < /path/to/testmail.eml
```

Nach einem späteren Port-Update von `mail/spamassassin` gilt auf FreeBSD weiter: **erst `sa-update`, dann `sa-spamd` neu starten**. ([FreshPorts][1])

---

## Referenzen

* FreshPorts: `mail/spamassassin`. ([FreshPorts][1])
* FreshPorts: `mail/spamass-milter`. ([FreshPorts][6])
* Apache SpamAssassin: `sa-update`. ([spamassassin.apache.org][4])
* Apache SpamAssassin: `sa-compile`. ([spamassassin.apache.org][7])
* Apache SpamAssassin: `Mail::SpamAssassin::BayesStore::PgSQL`. ([spamassassin.apache.org][2])
* Apache SpamAssassin: `Mail::SpamAssassin::Plugin::TxRep`. ([spamassassin.apache.org][5])
* Apache SpamAssassin: `sql/README.txrep`. ([svn.apache.org][8])

[1]: https://www.freshports.org/mail/spamassassin/ "FreshPorts -- mail/spamassassin: Highly efficient mail filter for identifying spam"
[2]: https://spamassassin.apache.org/full/4.0.x/doc/Mail_SpamAssassin_BayesStore_PgSQL.html "Mail::SpamAssassin::BayesStore::PgSQL"
[3]: https://github.com/freebsd/freebsd-ports/blob/main/UIDs "freebsd-ports/UIDs at main"
[4]: https://spamassassin.apache.org/full/4.0.x/doc/sa-update.html "sa-update"
[5]: https://spamassassin.apache.org/full/4.0.x/doc/Mail_SpamAssassin_Plugin_TxRep.html "Mail::SpamAssassin::Plugin::TxRep"
[6]: https://www.freshports.org/mail/spamass-milter/ "FreshPorts -- mail/spamass-milter: Sendmail Milter (mail filter) plugin for SpamAssassin"
[7]: https://spamassassin.apache.org/full/4.0.x/doc/sa-compile.html "sa-compile"
[8]: https://svn.apache.org/repos/asf/spamassassin/trunk/sql/README.txrep "svn.apache.org"
