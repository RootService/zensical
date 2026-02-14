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
lastmod: '2025-06-28'
title: SpamAssassin
description: In diesem HowTo wird step-by-step die Installation von SpamAssassin für ein Hosting System auf Basis von FreeBSD 64Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# SpamAssassin

## Einleitung

Unser Hosting System wird um folgende Dienste erweitert.

- SpamAssassin 4.0.2 (SpamAss-Milter)

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

## Installation

Wir installieren `mail/spamassassin` und dessen Abhängigkeiten.

``` shell
mkdir -p /var/db/ports/databases_p5-DBD-SQLite
cat <<'EOF' > /var/db/ports/databases_p5-DBD-SQLite/options
--8<-- "freebsd/ports/databases_p5-DBD-SQLite/options"
EOF

mkdir -p /var/db/ports/databases_p5-DBIx-Simple
cat <<'EOF' > /var/db/ports/db/ports/databases_p5-DBIx-Simple/options
--8<-- "freebsd/ports/databases_p5-DBIx-Simple/options"
EOF

mkdir -p /var/db/ports/dns_p5-Net-DNS
cat <<'EOF' > /var/db/ports/dns_p5-Net-DNS/options
--8<-- "freebsd/ports/dns_p5-Net-DNS/options"
EOF

mkdir -p /var/db/ports/devel_p5-Parse-RecDescent
cat <<'EOF' > /var/db/ports/devel_p5-Parse-RecDescent/options
--8<-- "freebsd/ports/devel_p5-Parse-RecDescent/options"
EOF

mkdir -p /var/db/ports/security_p5-IO-Socket-SSL
cat <<'EOF' > /var/db/ports/security_p5-IO-Socket-SSL/options
--8<-- "freebsd/ports/security_p5-IO-Socket-SSL/options"
EOF

mkdir -p /var/db/ports/security_p5-Net-SSLeay
cat <<'EOF' > /var/db/ports/security_p5-Net-SSLeay/options
--8<-- "freebsd/ports/security_p5-Net-SSLeay/options"
EOF

mkdir -p /var/db/ports/security_p5-Authen-SASL
cat <<'EOF' > /var/db/ports/security_p5-Authen-SASL/options
--8<-- "freebsd/ports/security_p5-Authen-SASL/options"
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

mkdir -p /var/db/ports/devel_p5-Data-Dumper-Concise
cat <<'EOF' > /var/db/ports/devel_p5-Data-Dumper-Concise/options
--8<-- "freebsd/ports/devel_p5-Data-Dumper-Concise/options"
EOF

mkdir -p /var/db/ports/mail_spamassassin
cat <<'EOF' > /var/db/ports/mail_spamassassin/options
--8<-- "freebsd/ports/mail_spamassassin/options"
EOF


portmaster -w -B -g --force-config mail/spamassassin  -n


sysrc spamd_enable="YES"
sysrc spamd_flags="-c -u spamd -H /var/spool/spamd"
```

Datenbanken installieren.

``` shell
cat <<'EOF' > /tmp/spamass_mail_bayes_shema.sql
CREATE TABLE bayes_expire (
  id integer NOT NULL default '0',
  runtime integer NOT NULL default '0'
) WITHOUT OIDS;

CREATE INDEX bayes_expire_idx1 ON bayes_expire (id);

CREATE TABLE bayes_global_vars (
  variable varchar(30) NOT NULL default '',
  value varchar(200) NOT NULL default '',
  PRIMARY KEY  (variable)
) WITHOUT OIDS;

INSERT INTO bayes_global_vars VALUES ('VERSION','3');

CREATE TABLE bayes_seen (
  id integer NOT NULL default '0',
  msgid varchar(200) NOT NULL default '',
  flag character(1) NOT NULL default '',
  PRIMARY KEY  (id,msgid)
) WITHOUT OIDS;

CREATE TABLE bayes_token (
  id integer NOT NULL default '0',
  token bytea NOT NULL default '',
  spam_count integer NOT NULL default '0',
  ham_count integer NOT NULL default '0',
  atime integer NOT NULL default '0',
  PRIMARY KEY  (id,token)
) WITHOUT OIDS;

CREATE INDEX bayes_token_idx1 ON bayes_token (token);

ALTER TABLE bayes_token SET (fillfactor=95);

CREATE TABLE bayes_vars (
  id serial NOT NULL,
  username varchar(200) NOT NULL default '',
  spam_count integer NOT NULL default '0',
  ham_count integer NOT NULL default '0',
  token_count integer NOT NULL default '0',
  last_expire integer NOT NULL default '0',
  last_atime_delta integer NOT NULL default '0',
  last_expire_reduce integer NOT NULL default '0',
  oldest_token_age integer NOT NULL default '2147483647',
  newest_token_age integer NOT NULL default '0',
  PRIMARY KEY  (id)
) WITHOUT OIDS;

CREATE UNIQUE INDEX bayes_vars_idx1 ON bayes_vars (username);

CREATE OR REPLACE FUNCTION greatest_int (integer, integer)
 RETURNS INTEGER
 IMMUTABLE STRICT
 AS 'SELECT CASE WHEN $1 < $2 THEN $2 ELSE $1 END;'
 LANGUAGE SQL;

CREATE OR REPLACE FUNCTION least_int (integer, integer)
 RETURNS INTEGER
 IMMUTABLE STRICT
 AS 'SELECT CASE WHEN $1 < $2 THEN $1 ELSE $2 END;'
 LANGUAGE SQL;

CREATE OR REPLACE FUNCTION put_tokens(INTEGER,
                                      BYTEA[],
                                      INTEGER,
                                      INTEGER,
                                      INTEGER)
RETURNS VOID AS '
DECLARE
  inuserid      ALIAS FOR $1;
  intokenary    ALIAS FOR $2;
  inspam_count  ALIAS FOR $3;
  inham_count   ALIAS FOR $4;
  inatime       ALIAS FOR $5;
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
      -- we do not insert negative counts, just return true
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
' LANGUAGE 'plpgsql';
EOF

cat <<'EOF' > /tmp/spamass_mail_awl_shema.sql
CREATE TABLE awl (
  username varchar(100) NOT NULL default '',
  email varchar(255) NOT NULL default '',
  ip varchar(40) NOT NULL default '',
  msgcount bigint NOT NULL default '0',
  totscore float NOT NULL default '0',
  signedby varchar(255) NOT NULL default '',
  last_hit timestamp NOT NULL default CURRENT_TIMESTAMP,
  PRIMARY KEY (username,email,signedby,ip)
);

create index awl_last_hit on awl (last_hit);

create OR REPLACE function update_awl_last_hit()
RETURNS TRIGGER AS $$
BEGIN
  NEW.last_hit = CURRENT_TIMESTAMP;
  RETURN NEW;
END;
$$ language 'plpgsql';

create TRIGGER update_awl_update_last_hit BEFORE UPDATE
ON awl FOR EACH ROW EXECUTE PROCEDURE
update_awl_last_hit();

ALTER TABLE awl SET (fillfactor=95);
EOF


cat <<'EOF' >> /data/db/postgres/data17/pg_hba.conf
#
# spamassassin databases
#
# TYPE  DATABASE        USER            ADDRESS                 METHOD
#
local   mail_bayes      spamass                                 scram-sha-256
host    mail_bayes      spamass         127.0.0.1/32            scram-sha-256
host    mail_bayes      spamass         ::1/128                 scram-sha-256
local   mail_awl        spamass                                 scram-sha-256
host    mail_awl        spamass         127.0.0.1/32            scram-sha-256
host    mail_awl        spamass         ::1/128                 scram-sha-256
#
EOF

su - postgres

# Password erzeugen und in /root/_passwords speichern
chmod 0600 /root/_passwords
newpw="`openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | cut -c 2-17`"
echo "Password for PostgreSQL user spamass: $newpw" >> /root/_passwords
chmod 0400 /root/_passwords
echo "Password: $newpw"
unset newpw


createuser -U postgres -S -D -R -P -e spamass


createdb -U postgres -E unicode -O spamass mail_bayes

psql mail_bayes

GRANT ALL PRIVILEGES ON DATABASE mail_bayes TO spamass;
QUIT;

psql -U spamass mail_bayes < /tmp/spamass_mail_bayes_shema.sql


createdb -U postgres -E unicode -O spamass mail_awl

psql mail_awl

GRANT ALL PRIVILEGES ON DATABASE mail_awl TO spamass;
QUIT;

psql -U spamass mail_awl < /tmp/spamass_mail_awl_shema.sql


exit
```

Wir installieren `mail/spamass-milter` und dessen Abhängigkeiten.

``` shell
mkdir -p /var/db/ports/mail_spamass-milter
cat <<'EOF' > /var/db/ports/mail_spamass-milter/options
--8<-- "freebsd/ports/mail_spamass-milter/options"
EOF


portmaster -w -B -g --force-config mail/spamass-milter  -n


sysrc spamass_milter_enable="YES"
sysrc spamass_milter_user="spamd"
sysrc spamass_milter_group="spamd"
sysrc spamass_milter_socket="/var/run/spamass-milter/spamass-milter.sock"
sysrc spamass_milter_socket_owner="spamd"
sysrc spamass_milter_socket_group="mail"
sysrc spamass_milter_socket_mode="660"
sysrc spamass_milter_localflags="-r 15 -f -u spamd -- -u spamd"
```

## Konfigurieren

`local.cf` einrichten.

``` shell
cat <<'EOF' > /usr/local/etc/mail/spamassassin/local.cf
--8<-- "freebsd/configs/usr/local/etc/mail/spamassassin/local.cf"
EOF

cat <<'EOF' > /usr/local/etc/mail/spamassassin/init.pre
--8<-- "freebsd/configs/usr/local/etc/mail/spamassassin/init.pre"
EOF

cat <<'EOF' > /usr/local/etc/mail/spamassassin/v310.pre
--8<-- "freebsd/configs/usr/local/etc/mail/spamassassin/v310.pre"
EOF

cat <<'EOF' > /usr/local/etc/mail/spamassassin/v312.pre
--8<-- "freebsd/configs/usr/local/etc/mail/spamassassin/v312.pre"
EOF

cat <<'EOF' > /usr/local/etc/mail/spamassassin/v320.pre
--8<-- "freebsd/configs/usr/local/etc/mail/spamassassin/v320.pre"
EOF

cat <<'EOF' > /usr/local/etc/mail/spamassassin/v330.pre
--8<-- "freebsd/configs/usr/local/etc/mail/spamassassin/v330.pre"
EOF

cat <<'EOF' > /usr/local/etc/mail/spamassassin/v340.pre
--8<-- "freebsd/configs/usr/local/etc/mail/spamassassin/v340.pre"
EOF

cat <<'EOF' > /usr/local/etc/mail/spamassassin/v341.pre
--8<-- "freebsd/configs/usr/local/etc/mail/spamassassin/v341.pre"
EOF

cat <<'EOF' > /usr/local/etc/mail/spamassassin/v342.pre
--8<-- "freebsd/configs/usr/local/etc/mail/spamassassin/v342.pre"
EOF

cat <<'EOF' > /usr/local/etc/mail/spamassassin/v343.pre
--8<-- "freebsd/configs/usr/local/etc/mail/spamassassin/v343.pre"
EOF

cat <<'EOF' > /usr/local/etc/mail/spamassassin/v400.pre
--8<-- "freebsd/configs/usr/local/etc/mail/spamassassin/v400.pre"
EOF

cat <<'EOF' > /usr/local/etc/mail/spamassassin/v401.pre
--8<-- "freebsd/configs/usr/local/etc/mail/spamassassin/v401.pre"
EOF

# IPv4
ifconfig -u -f cidr `route -n get -inet default | awk '/interface/ {print $2}'` inet | \
    awk 'tolower($0) ~ /inet[\ \t]+((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/ {if(substr($2,1,3)!=127) print $2}' | \
    head -n 1 | cut -d'/' -f 1 | xargs -I % sed -e 's|__IPADDR4__|%|g' -i '' /usr/local/etc/mail/spamassassin/local.cf

# IPv6
ifconfig -u -f cidr `route -n get -inet6 default | awk '/interface/ {print $2}'` inet6 | \
    awk 'tolower($0) ~ /inet6[\ \t]+(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/ {if(substr($2,1,1)!="f") print $2}' | \
    head -n 1 | cut -d'/' -f 1 | xargs -I % sed -e 's|__IPADDR6__|%|g' -i '' /usr/local/etc/mail/spamassassin/local.cf


awk '/^Password for PostgreSQL user spamass:/ {print $NF}' /root/_passwords | \
    xargs -I % sed -e 's|__PASSWORD_SPAMASS__|%|g' -i '' /usr/local/etc/mail/spamassassin/local.cf
```

SpamAssassin Datenbank anlegen.

``` shell
/usr/local/bin/sa-update --channel updates.spamassassin.org --refreshmirrors --verbose
/usr/local/bin/sa-update --channel updates.spamassassin.org --verbose
/usr/local/bin/sa-update --nogpg --channel kam.sa-channels.mcgrail.com --refreshmirrors --verbose
/usr/local/bin/sa-update --nogpg --channel kam.sa-channels.mcgrail.com --verbose

/usr/local/bin/sa-compile --quiet
```

SpamAssassin Datenbank updaten.

``` shell
cat <<'EOF' > /usr/local/sbin/update-spamassassin
--8<-- "freebsd/configs/usr/local/sbin/update-spamassassin"
EOF

chmod 0755 /usr/local/sbin/update-spamassassin
```

## Abschluss

SpamAssassin kann nun gestartet werden.

``` shell
mkdir -p /var/run/spamass-milter
chown spamd:spamd /var/run/spamass-milter

service sa-spamd start
service spamass-milter start
```
