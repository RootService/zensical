---
author:
  name: Markus Kohlmeyer
  url: https://github.com/JoeUser78
  email: joeuser@rootservice.org
publisher:
  name: RootService Team
  url: https://github.com/RootService
license:
  name: Attribution-NonCommercial-ShareAlike 4.0 International (CC BY-NC-SA 4.0)
  shortname: CC BY-NC-SA 4.0
  url: https://creativecommons.org/licenses/by-nc-sa/4.0/
contributers: []
date: '2010-08-25'
lastmod: '2025-06-28'
title: Amavisd
description: In diesem HowTo wird step-by-step die Installation von Amavisd für ein Hosting System auf Basis von FreeBSD 64Bit auf einem dedizierten Server beschrieben.
keywords:
  - Amavisd
  - mkdocs
  - docs
lang: de
robots: index, follow
hide: []
search:
  exclude: false
---

## Einleitung

Unser Hosting System wird um folgende Dienste erweitert.

- Amavisd

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../intro.md)

## Installation

Wir installieren `security/amavisd-new` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/archivers_7-zip
cat <<'EOF' > /var/db/ports/archivers_7-zip/options
--8<-- "ports/archivers_7-zip/options"
EOF

mkdir -p /var/db/ports/archivers_arc
cat <<'EOF' > /var/db/ports/archivers_arc/options
--8<-- "ports/archivers_arc/options"
EOF

mkdir -p /var/db/ports/archivers_arj
cat <<'EOF' > /var/db/ports/archivers_arj/options
--8<-- "ports/archivers_arj/options"
EOF

mkdir -p /var/db/ports/archivers_cabextract
cat <<'EOF' > /var/db/ports/archivers_cabextract/options
--8<-- "ports/archivers_cabextract/options"
EOF

mkdir -p /var/db/ports/archivers_lzop
cat <<'EOF' > /var/db/ports/archivers_lzop/options
--8<-- "ports/archivers_lzop/options"
EOF

mkdir -p /var/db/ports/archivers_lzo2
cat <<'EOF' > /var/db/ports/archivers_lzo2/options
--8<-- "ports/archivers_lzo2/options"
EOF

mkdir -p /var/db/ports/archivers_unarj
cat <<'EOF' > /var/db/ports/archivers_unarj/options
--8<-- "ports/archivers_unarj/options"
EOF

mkdir -p /var/db/ports/archivers_unrar
cat <<'EOF' > /var/db/ports/archivers_unrar/options
--8<-- "ports/archivers_unrar/options"
EOF

mkdir -p /var/db/ports/net-mgmt_p0f
cat <<'EOF' > /var/db/ports/net-mgmt_p0f/options
--8<-- "ports/net-mgmt_p0f/options"
EOF

mkdir -p /var/db/ports/security_amavisd-new
cat <<'EOF' > /var/db/ports/security_amavisd-new/options
--8<-- "ports/security_amavisd-new/options"
EOF


portmaster -w -B -g --force-config security/amavisd-new  -n


sysrc amavisd_enable="YES"
sysrc amavisd_pidfile="/var/amavis/var/amavisd.pid"
sysrc amavis_milter_enable="YES"
sysrc amavis_p0fanalyzer_enable="YES"
sysrc amavis_p0fanalyzer_p0f_filter="tcp dst port 25"
```

Datenbanken installieren.

```shell
mkdir -p /data/db/postgres

cat <<'EOF' > /tmp/amavisd_mail_prefs_shema.sql
CREATE TABLE policy (
  id            serial PRIMARY KEY, -- 'id' is the _only_ required field
  policy_name   varchar(32),        -- not used by amavis, a comment

  virus_lover           char(1) default NULL,     -- Y/N
  spam_lover            char(1) default NULL,     -- Y/N
  unchecked_lover       char(1) default NULL,     -- Y/N
  banned_files_lover    char(1) default NULL,     -- Y/N
  bad_header_lover      char(1) default NULL,     -- Y/N

  bypass_virus_checks   char(1) default NULL,     -- Y/N
  bypass_spam_checks    char(1) default NULL,     -- Y/N
  bypass_banned_checks  char(1) default NULL,     -- Y/N
  bypass_header_checks  char(1) default NULL,     -- Y/N

  virus_quarantine_to      varchar(64) default NULL,
  spam_quarantine_to       varchar(64) default NULL,
  banned_quarantine_to     varchar(64) default NULL,
  unchecked_quarantine_to  varchar(64) default NULL,
  bad_header_quarantine_to varchar(64) default NULL,
  clean_quarantine_to      varchar(64) default NULL,
  archive_quarantine_to    varchar(64) default NULL,

  spam_tag_level  real default NULL, -- higher score inserts spam info headers
  spam_tag2_level real default NULL, -- inserts 'declared spam' header fields
  spam_tag3_level real default NULL, -- inserts 'blatant spam' header fields
  spam_kill_level real default NULL, -- higher score triggers evasive actions
                                     -- e.g. reject/drop, quarantine, ...
                                     -- (subject to final_spam_destiny setting)

  spam_dsn_cutoff_level        real default NULL,
  spam_quarantine_cutoff_level real default NULL,

  addr_extension_virus      varchar(64) default NULL,
  addr_extension_spam       varchar(64) default NULL,
  addr_extension_banned     varchar(64) default NULL,
  addr_extension_bad_header varchar(64) default NULL,

  warnvirusrecip      char(1)     default NULL, -- Y/N
  warnbannedrecip     char(1)     default NULL, -- Y/N
  warnbadhrecip       char(1)     default NULL, -- Y/N
  newvirus_admin      varchar(64) default NULL,
  virus_admin         varchar(64) default NULL,
  banned_admin        varchar(64) default NULL,
  bad_header_admin    varchar(64) default NULL,
  spam_admin          varchar(64) default NULL,
  spam_subject_tag    varchar(64) default NULL,
  spam_subject_tag2   varchar(64) default NULL,
  spam_subject_tag3   varchar(64) default NULL,
  message_size_limit  integer     default NULL, -- max size in bytes, 0 disable
  banned_rulenames    varchar(64) default NULL, -- comma-separated list of ...
        -- names mapped through %banned_rules to actual banned_filename tables
  disclaimer_options  varchar(64) default NULL,
  forward_method      varchar(64) default NULL,
  sa_userconf         varchar(64) default NULL,
  sa_username         varchar(64) default NULL
);

-- local users
CREATE TABLE users (
  id         serial  PRIMARY KEY,  -- unique id
  priority   integer NOT NULL DEFAULT 7,  -- sort field, 0 is low prior.
  policy_id  integer NOT NULL DEFAULT 1 CHECK (policy_id >= 0) REFERENCES policy(id),
  email      bytea   NOT NULL UNIQUE,     -- email address, non-rfc2822-quoted
  fullname   varchar(255) DEFAULT NULL    -- not used by amavis
  -- local   char(1)      -- Y/N  (optional, see SQL section in README.lookups)
);

-- any e-mail address (non- rfc2822-quoted), external or local,
-- used as senders in wblist
CREATE TABLE mailaddr (
  id         serial  PRIMARY KEY,
  priority   integer NOT NULL DEFAULT 9,  -- 0 is low priority
  email      bytea   NOT NULL UNIQUE
);

-- per-recipient whitelist and/or blacklist,
-- puts sender and recipient in relation wb  (white or blacklisted sender)
CREATE TABLE wblist (
  rid        integer NOT NULL CHECK (rid >= 0) REFERENCES users(id),
  sid        integer NOT NULL CHECK (sid >= 0) REFERENCES mailaddr(id),
  wb         varchar(10) NOT NULL,  -- W or Y / B or N / space=neutral / score
  PRIMARY KEY (rid,sid)
);

-- grant usage rights:
GRANT select ON policy   TO vscan;
GRANT select ON users    TO vscan;
GRANT select ON mailaddr TO vscan;
GRANT select ON wblist   TO vscan;
EOF

cat <<'EOF' > /tmp/amavisd_mail_log_shema.sql
-- R/W part of the dataset (optional)
--   May reside in the same or in a separate database as lookups database;
--   REQUIRES SUPPORT FOR TRANSACTIONS; specified in @storage_sql_dsn
--
--  Please create additional indexes on keys when needed, or drop suggested
--  ones as appropriate to optimize queries needed by a management application.
--  See your database documentation for further optimization hints.

-- provide unique id for each e-mail address, avoids storing copies
CREATE TABLE maddr (
  id         serial       PRIMARY KEY,
  partition_tag integer   DEFAULT 0,   -- see $partition_tag
  email      bytea        NOT NULL,    -- full e-mail address
  domain     varchar(255) NOT NULL,    -- only domain part of the email address
                                       -- with subdomain fields in reverse
  CONSTRAINT part_email UNIQUE (partition_tag,email)
);

-- information pertaining to each processed message as a whole;
-- NOTE: records with a NULL msgs.content should be ignored by utilities,
--   as such records correspond to messages just being processed, or were lost
CREATE TABLE msgs (
  partition_tag integer     DEFAULT 0,  -- see $partition_tag
  mail_id     bytea         NOT NULL,   -- long-term unique mail id, dflt 12 ch
  secret_id   bytea         DEFAULT '', -- authorizes release of mail_id, 12 ch
  am_id       varchar(20)   NOT NULL,   -- id used in the log
  time_num    integer NOT NULL CHECK (time_num >= 0),
                                        -- rx_time: seconds since Unix epoch
  time_iso timestamp WITH TIME ZONE NOT NULL,-- rx_time: ISO8601 UTC ascii time
  sid         integer NOT NULL CHECK (sid >= 0), -- sender: maddr.id
  policy      varchar(255)  DEFAULT '', -- policy bank path (like macro %p)
  client_addr varchar(255)  DEFAULT '', -- SMTP client IP address (IPv4 or v6)
  size        integer NOT NULL CHECK (size >= 0), -- message size in bytes
  originating char(1) DEFAULT ' ' NOT NULL,  -- sender from inside or auth'd
  content     char(1),                   -- content type: V/B/U/S/Y/M/H/O/T/C
    -- virus/banned/unchecked/spam(kill)/spammy(tag2)/
    -- /bad-mime/bad-header/oversized/mta-err/clean
    -- is NULL on partially processed mail
    -- (prior to 2.7.0 the CC_SPAMMY was logged as 's', now 'Y' is used;
    --- to avoid a need for case-insenstivity in queries)
  quar_type  char(1),                   -- quarantined as: ' '/F/Z/B/Q/M/L
                                        --  none/file/zipfile/bsmtp/sql/
                                        --  /mailbox(smtp)/mailbox(lmtp)
  quar_loc   varchar(255)  DEFAULT '',  -- quarantine location (e.g. file)
  dsn_sent   char(1),                   -- was DSN sent? Y/N/q (q=quenched)
  spam_level real,                      -- SA spam level (no boosts)
  message_id varchar(255)  DEFAULT '',  -- mail Message-ID header field
  from_addr  varchar(255)  DEFAULT '',  -- mail From header field,    UTF8
  subject    varchar(255)  DEFAULT '',  -- mail Subject header field, UTF8
  host       varchar(255)  NOT NULL,    -- hostname where amavisd is running
  CONSTRAINT msgs_partition_mail UNIQUE (partition_tag,mail_id),
  PRIMARY KEY (partition_tag,mail_id)
--FOREIGN KEY (sid) REFERENCES maddr(id) ON DELETE RESTRICT
);
CREATE INDEX msgs_idx_sid      ON msgs (sid);
CREATE INDEX msgs_idx_mess_id  ON msgs (message_id); -- useful with pen pals
CREATE INDEX msgs_idx_time_iso ON msgs (time_iso);
CREATE INDEX msgs_idx_time_num ON msgs (time_num);   -- optional

-- per-recipient information related to each processed message;
-- NOTE: records in msgrcpt without corresponding msgs.mail_id record are
--  orphaned and should be ignored and eventually deleted by external utilities
CREATE TABLE msgrcpt (
  partition_tag integer DEFAULT 0,  -- see $partition_tag
  mail_id    bytea    NOT NULL,     -- (must allow duplicates)
  rseqnum    integer  DEFAULT 0   NOT NULL, -- recip's enumeration within msg
  rid        integer  NOT NULL,     -- recipient: maddr.id (duplicates allowed)
  is_local   char(1)  DEFAULT ' ' NOT NULL, -- recip is: Y=local, N=foreign
  content    char(1)  DEFAULT ' ' NOT NULL, -- content type V/B/U/S/Y/M/H/O/T/C
  ds         char(1)  NOT NULL,     -- delivery status: P/R/B/D/T
                                    -- pass/reject/bounce/discard/tempfail
  rs         char(1)  NOT NULL,     -- release status: initialized to ' '
  bl         char(1)  DEFAULT ' ',  -- sender blacklisted by this recip
  wl         char(1)  DEFAULT ' ',  -- sender whitelisted by this recip
  bspam_level real,                 -- per-recipient (total) spam level
  smtp_resp  varchar(255) DEFAULT '', -- SMTP response given to MTA
  CONSTRAINT msgrcpt_partition_mail_rseq UNIQUE (partition_tag,mail_id,rseqnum),
  PRIMARY KEY (partition_tag,mail_id,rseqnum)
--FOREIGN KEY (rid)     REFERENCES maddr(id)     ON DELETE RESTRICT,
--FOREIGN KEY (mail_id) REFERENCES msgs(mail_id) ON DELETE CASCADE
);
CREATE INDEX msgrcpt_idx_mail_id  ON msgrcpt (mail_id);
CREATE INDEX msgrcpt_idx_rid      ON msgrcpt (rid);

-- mail quarantine in SQL, enabled by $*_quarantine_method='sql:'
-- NOTE: records in quarantine without corresponding msgs.mail_id record are
--  orphaned and should be ignored and eventually deleted by external utilities
CREATE TABLE quarantine (
  partition_tag integer  DEFAULT 0,      -- see $partition_tag
  mail_id    bytea   NOT NULL,           -- long-term unique mail id
  chunk_ind  integer NOT NULL CHECK (chunk_ind >= 0), -- chunk number, 1..
  mail_text  bytea   NOT NULL,           -- store mail as chunks of octects
  PRIMARY KEY (partition_tag,mail_id,chunk_ind)
--FOREIGN KEY (mail_id) REFERENCES msgs(mail_id) ON DELETE CASCADE
);

-- field msgrcpt.rs is primarily intended for use by quarantine management
-- software; the value assigned by amavisd is a space;
-- a short _preliminary_ list of possible values:
--   'V' => viewed (marked as read)
--   'R' => released (delivered) to this recipient
--   'p' => pending (a status given to messages when the admin received the
--                   request but not yet released; targeted to banned parts)
--   'D' => marked for deletion; a cleanup script may delete it

-- grant usage rights:
GRANT select,insert,update,delete ON maddr        TO vscan;
GRANT usage,update                ON maddr_id_seq TO vscan;
GRANT select,insert,update,delete ON msgs         TO vscan;
GRANT select,insert,update,delete ON msgrcpt      TO vscan;
GRANT select,insert,update,delete ON quarantine   TO vscan;
EOF


cat <<'EOF' >> /data/db/postgres/data17/pg_hba.conf
#
# amavisd databases
#
# TYPE  DATABASE        USER            ADDRESS                 METHOD
#
local   mail_prefs      vscan                                   scram-sha-256
host    mail_prefs      vscan           127.0.0.1/32            scram-sha-256
host    mail_prefs      vscan           ::1/128                 scram-sha-256
local   mail_log        vscan                                   scram-sha-256
host    mail_log        vscan           127.0.0.1/32            scram-sha-256
host    mail_log        vscan           ::1/128                 scram-sha-256
#
EOF

su - postgres

# Password erzeugen und in /root/_passwords speichern
chmod 0600 /root/_passwords
newpw="`openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | cut -c 2-17`"
echo "Password for PostgreSQL user vscan: $newpw" >> /root/_passwords
chmod 0400 /root/_passwords
echo "Password: $newpw"
unset newpw


createuser -U postgres -S -D -R -P -e vscan


createdb -U postgres -E unicode -O vscan mail_prefs

psql mail_prefs

GRANT ALL PRIVILEGES ON DATABASE mail_prefs TO vscan;
QUIT;

psql -U vscan mail_prefs < /tmp/amavisd_mail_prefs_shema.sql


createdb -U postgres -E unicode -O vscan mail_log

psql mail_log

GRANT ALL PRIVILEGES ON DATABASE mail_log TO vscan;
QUIT;

psql -U vscan mail_log < /tmp/amavisd_mail_log_shema.sql


exit
```

## Konfigurieren

`amavisd.conf` einrichten.

```shell
cat <<'EOF' > /usr/local/etc/amavisd.conf
--8<-- "configs/usr/local/etc/amavisd.conf"
EOF

# IPv4
ifconfig -u -f cidr `route -n get -inet default | awk '/interface/ {print $2}'` inet | \
    awk 'tolower($0) ~ /inet[\ \t]+((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/ {if(substr($2,1,3)!=127) print $2}' | \
    head -n 1 | cut -d'/' -f 1 | xargs -I % sed -e 's|__IPADDR4__|%|g' -i '' /usr/local/etc/amavisd.conf

# IPv6
ifconfig -u -f cidr `route -n get -inet6 default | awk '/interface/ {print $2}'` inet6 | \
    awk 'tolower($0) ~ /inet6[\ \t]+(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/ {if(substr($2,1,1)!="f") print $2}' | \
    head -n 1 | cut -d'/' -f 1 | xargs -I % sed -e 's|__IPADDR6__|%|g' -i '' /usr/local/etc/amavisd.conf

awk '/^Password for PostgreSQL user vscan:/ {print $NF}' /root/_passwords | \
    xargs -I % sed -e 's|__PASSWORD_VSCAN__|%|g' -i '' /usr/local/etc/amavisd.conf


mkdir -p /var/amavis/db/keys

amavisd genrsa /var/amavis/db/keys/example.com/20250426.pem 2048

chown -R vscan:vscan /var/amavis

amavisd showkeys
```

## Abschluss

Amavisd kann nun gestartet werden.

```shell
service amavisd start
service amavis_p0fanalyzer start
```
