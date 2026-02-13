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
contributors:
  - Jesco Freund
  - Matthias Weiss
date: '2006-03-02'
lastmod: '2014-09-01'
title: Hosting System
description: In diesem HowTo wird step-by-step die Installation eines Hosting Systems auf Basis von Gentoo Linux 64Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# Hosting System

## Einleitung

!!!danger
Dieses HowTo wird seit **2014-09-01** nicht mehr aktiv gepflegt und entspricht daher nicht mehr dem aktuellen Stand.

Die Verwendung dieses HowTo geschieht somit auf eigene Gefahr!
!!!

Dieses HowTo setzt ein wie in [Remote Installation](remote_install.md) beschriebenes, installiertes und konfiguriertes Gentoo Linux Basissystem voraus.

Folgende Punkte sind in diesem HowTo zu beachten:

- Alle Dienste werden mit einem möglichst minimalen und bewährten Funktionsumfang installiert.
- Alle Dienste werden mit einer möglichst sicheren und dennoch flexiblen Konfiguration versehen.
- Alle Konfigurationen sind selbstständig auf notwendige individuelle Anpassungen zu kontrollieren.
- Alle Passworte werden als `__PASSWORD__` dargestellt und sind selbstständig durch sichere Passworte zu ersetzen.
- Alle Domainangaben werden als `example.com` dargestellt und sind selbstständig durch die eigene Domain zu ersetzen.
- Die IP-Adresse des Servers wird als `10.0.2.15` dargestellt und ist selbstständig durch die eigene IP-Adresse zu ersetzen.
- Postfix und Dovecot teilen sich sowohl den Hostnamen `mail.example.com` als auch das SSL-Zertifikat.

Unser Hosting System wird folgende Dienste umfassen:

- MySQL
- Postfix
- Dovecot
- Apache
- mod_php

Desweiteren werden wir folgende Applikationen installieren:

- phpMyAdmin
- PostfixAdmin
- RoundCube

## OpenSSL

### OpenSSL konfigurieren

Sofern noch nicht während der [Remote Installation](remote_install.md) erledigt, müssen folgende Optionen in der `/etc/ssl/openssl.cnf` im Abschnitt `[ req_distinguished_name ]` angepasst werden:

```text
[ req_distinguished_name ]
countryName_default             = DE
stateOrProvinceName_default     = Bundesland
localityName_default            = Stadt
0.organizationName_default      = Example Organization
organizationalUnitName_default  = Administration
commonName_default              = example.com
emailAddress_default            = admin@example.com
```

### OpenSSL CA

Sofern noch nicht während der [Remote Installation](remote_install.md) erledigt, wird als Nächstes ein eigenes CA Zertifikat erstellt und selbst signiert. Hierzu werden jeweils die Default-Werte übernommen und sehr sichere Passworte gewählt. Die Option `A challenge password` sollte jedoch leer gelassen werden, andernfalls kann es zu Problemen mit einigen Diensten kommen:

```shell
cd /etc/ssl
mkdir -p demoCA
mkdir -p demoCA/certs
mkdir -p demoCA/crl
mkdir -p demoCA/newcerts
mkdir -p demoCA/private
touch demoCA/index.txt

openssl req -new -keyout demoCA/private/cakey.pem -out demoCA/careq.pem
openssl ca -create_serial -out demoCA/cacert.pem -batch -keyfile demoCA/private/cakey.pem -selfsign -extensions v3_ca -infiles demoCA/careq.pem
cd
```

### OpenSSL Zertifikate

Wir erstellen uns die nachfolgend benötigten selbstsignierten Zertifikate. Dabei verwenden wir für das Mailserver-Zertifikat `mail.example.com` und für das Webserver-Zertifikat `ssl.example.com` als `Common Name`. Die anderen Fragen beantworten wir jeweils mit den Default-Vorgaben.

```shell
cd /etc/ssl
openssl dhparam -out dh_512.pem -2 -rand /dev/urandom 512
openssl dhparam -out dh_1024.pem -2 -rand /dev/urandom 1024

openssl req -new -keyout mailserver_key.pem -out mailserver_req.pem
openssl ca -policy policy_anything -out mailserver_cert.pem -infiles mailserver_req.pem
openssl rsa -in mailserver_key.pem -out mailserver_keyrsa.pem

openssl req -new -keyout webserver_key.pem -out webserver_req.pem
openssl ca -policy policy_anything -out webserver_cert.pem -infiles webserver_req.pem
openssl rsa -in webserver_key.pem -out webserver_keyrsa.pem
cd
```

## MySQL

MySQL unterstützt mehrere Engines, dieses HowTo beschränkt sich allerdings auf die Beiden am Häufigsten verwendeten: MyISAM und InnoDB. Werden weitere Engines benötigt, müssen die entsprechenden USE-Flags manuell gesetzt werden.

!!!note
Sollen bereits existierende Datenbanken importiert werden, müssen diese, sofern noch nicht geschehen, zuvor nach UTF-8 konvertiert werden. Ist dies nicht möglich, weil beispielsweise eine Client-Applikation noch kein UTF-8 ünterstützt, so ist in der folgenden `/etc/mysql/my.cnf` jeweils `utf8` durch `latin1` zu ersetzen. Desweiteren muss in diesem Fall für `dev-db/mysql` in der `/etc/portage/package.use` zusätzlich das USE-Flag `latin1` gesetzt werden.
!!!

### MySQL installieren

```shell
cat >> /etc/portage/package.use << "EOF"
dev-db/mysql  -ssl
EOF

emerge mysql

rc-update add mysql default
```

### MySQL konfigurieren

```shell
cat > /etc/mysql/my.cnf << "EOF"
[client]
port                            = 3306

[mysql]
prompt                          = \u@\h [\d]>\_
no_auto_rehash

[mysqld_safe]
err_log                         = /var/log/mysql/mysql.err

[mysqld]
user                            = mysql
port                            = 3306
bind-address                    = 127.0.0.1
basedir                         = /usr
datadir                         = /var/lib/mysql
tmpdir                          = /var/tmp
language                        = /usr/share/mysql/english
log_error                       = /var/log/mysql/mysqld.err
server-id                       = 1
lower_case_table_names          = 1
safe-user-create                = 1
EOF

emerge --config dev-db/mysql

cat > /etc/mysql/my.cnf << "EOF"
[client]
character-sets-dir              = /usr/share/mysql/charsets
character-set-server            = utf8
port                            = 3306

[mysql]
character-sets-dir              = /usr/share/mysql/charsets
character-set-server            = utf8
prompt                          = \u@\h [\d]>\_
no_auto_rehash

[mysqladmin]
character-sets-dir              = /usr/share/mysql/charsets
character-set-server            = utf8

[mysqlcheck]
character-sets-dir              = /usr/share/mysql/charsets
character-set-server            = utf8

[mysqldump]
character-sets-dir              = /usr/share/mysql/charsets
character-set-server            = utf8
max_allowed_packet              = 32M
quote_names
quick

[mysqlimport]
character-sets-dir              = /usr/share/mysql/charsets
character-set-server            = utf8

[mysqlshow]
character-sets-dir              = /usr/share/mysql/charsets
character-set-server            = utf8

[isamchk]
character-sets-dir              = /usr/share/mysql/charsets
character-set-server            = utf8
key_buffer_size                 = 256M

[myisamchk]
character-sets-dir              = /usr/share/mysql/charsets
character-set-server            = utf8
key_buffer_size                 = 256M

[myisampack]
character-sets-dir              = /usr/share/mysql/charsets
character-set-server            = utf8

[mysqld_safe]
err_log                         = /var/log/mysql/mysql.err

[mysqld]
user                            = mysql
port                            = 3306
bind-address                    = 127.0.0.1
socket                          = /var/run/mysqld/mysqld.sock
pid_file                        = /var/run/mysqld/mysqld.pid
character-sets-dir              = /usr/share/mysql/charsets
character-set-server            = utf8
collation-server                = utf8_bin
default-storage-engine          = InnoDB
basedir                         = /usr
datadir                         = /var/lib/mysql
tmpdir                          = /var/tmp
slave-load-tmpdir               = /var/tmp
language                        = /usr/share/mysql/english
log_error                       = /var/log/mysql/mysqld.err
log-bin                         = /var/lib/mysql/mysql-bin
relay-log                       = /var/lib/mysql/relay.log
relay-log-index                 = /var/lib/mysql/relay.index
relay-log-info-file             = /var/lib/mysql/relay.info
master-info-file                = /var/lib/mysql/master.info
#master-host                     = <hostname>
#master-user                     = <username>
#master-password                 = <password>
#master-port                     = 3306
#auto_increment_increment        = 10
#auto_increment_offset           = 1
server-id                       = 1
back_log                        = 50
sync_binlog                     = 1
binlog_cache_size               = 1M
max_binlog_size                 = 100M
binlog-format                   = MIXED
expire_logs_days                = 7
slow-query-log                  = 1
slow-query-log-file             = /var/lib/mysql/slow-query.log
slave_compressed_protocol       = 1
lower_case_table_names          = 1
safe-user-create                = 1
delay-key-write                 = ALL
myisam-recover                  = FORCE,BACKUP
key_buffer_size                 = 256M
join_buffer_size                = 2M
sort_buffer_size                = 2M
read_buffer_size                = 2M
read_rnd_buffer_size            = 8M
myisam_sort_buffer_size         = 64M
max_allowed_packet              = 32M
max_heap_table_size             = 64M
tmp_table_size                  = 64M
table_cache                     = 1024
table_definition_cache          = 1024
query_cache_type                = 1
query_cache_size                = 128M
query_cache_limit               = 16M
thread_concurrency              = 8
thread_cache_size               = 24
max_connections                 = 24
ft_max_word_len                 = 20
ft_min_word_len                 = 3
long_query_time                 = 2
local-infile                    = 0
log-warnings                    = 2
log-slave-updates
log-queries-not-using-indexes
skip-external-locking
skip-character-set-client-handshake
innodb_thread_concurrency       = 8
innodb_buffer_pool_size         = 2G
innodb_additional_mem_pool_size = 128M
innodb_data_home_dir            = /var/lib/mysql
innodb_log_group_home_dir       = /var/lib/mysql
innodb_data_file_path           = ibdata1:2000M;ibdata2:10M:autoextend
innodb_log_file_size            = 128M
innodb_log_buffer_size          = 16M
innodb_log_files_in_group       = 2
innodb_flush_log_at_trx_commit  = 2
innodb_max_dirty_pages_pct      = 90
innodb_lock_wait_timeout        = 120
innodb_file_per_table           = 1

[mysqlhotcopy]
interactive_timeout
EOF
```

### MySQL absichern

MySQL wird nun zum ersten Mal gestartet, was durch das Erzeugen der InnoDB-Files einige Minuten dauern und zu einer falschen Fehlermeldung des Init-Scripts führen kann. Daher warten wir bis im `tail -f` eine Zeile ähnlich der folgenden erscheint und beenden `tail` mittels `^C` (STRG+C).

```shell
Version: '5.1.50-log'  socket: '/var/run/mysqld/mysqld.sock'  port: 3306  Gentoo Linux mysql-5.1.50-r1
```

Abschliessend wird MySQL mittels `mysql_secure_installation` abgesichert. Hierzu werden alle Fragen, abgesehen vom zuvor gesetztem root-Passwort, jeweils mit einem beherzten Druck auf die Return-Taste beantwortet.

```shell
/etc/init.d/mysql start

tail -f /var/log/mysql/mysqld.err

mysql_secure_installation
```

## Dovecot

Dovecot wird inklusive MySQL und TLS/SSL Support installiert und für das Zusammenspiel mit PostfixAdmin konfiguriert.

### Dovecot installieren

```shell
cat >> /etc/portage/package.use << "EOF"
net-mail/dovecot  maildir
EOF

emerge dovecot

rc-update add dovecot default
```

### Dovecot konfigurieren

`dovecot.conf` einrichten:

```shell
cat > /etc/dovecot/dovecot.conf << "EOF"
protocols = imap pop3 imaps pop3s
listen = 10.0.2.15
disable_plaintext_auth = no
ssl = yes
ssl_cert_file = /etc/ssl/mailserver_cert.pem
ssl_key_file = /etc/ssl/mailserver_keyrsa.pem
ssl_cipher_list = RSA:!EXP:!NULL:+HIGH:+MEDIUM:-LOW:-SSLv2
login_process_per_connection = yes
login_processes_count = 3
login_max_processes_count = 128
mail_location = maildir:/var/vmail/%d/%n
mail_privileged_group = mail
dotlock_use_excl = yes
verbose_proctitle = yes
first_valid_uid = 207
last_valid_uid = 207
first_valid_gid = 207
last_valid_gid = 207
maildir_copy_with_hardlinks = yes
protocol imap {
  mail_plugins = quota imap_quota
  imap_client_workarounds = delay-newmail netscape-eoh tb-extra-mailbox-sep
}
protocol pop3 {
  pop3_uidl_format = %08Xu%08Xv
  mail_plugins = quota
  pop3_client_workarounds = outlook-no-nuls oe-ns-eoh
}
protocol lda {
  postmaster_address = postmaster@example.com
  hostname = mail.example.com
  quota_full_tempfail = no
  sendmail_path = /usr/sbin/sendmail
}
auth_username_format = %Lu
auth default {
  mechanisms = plain login
  passdb sql {
    args = /etc/dovecot/dovecot-sql.conf
  }
  userdb sql {
    args = /etc/dovecot/dovecot-sql.conf
  }
  user = root
  socket listen {
    master {
      path = /var/run/dovecot/auth-master
      mode = 0600
    }
    client {
      path = /var/spool/postfix/private/auth
      mode = 0660
      user = postfix
      group = postfix
    }
  }
}
dict {
}
plugin {
  quota = maildir
  quota_rule = *:storage=1048576
}
EOF
```

`dovecot-sql.conf` einrichten:

```shell
cat > /etc/dovecot/dovecot-sql.conf << "EOF"
driver = mysql
connect = host=localhost dbname=postfix user=postfix password=__PASSWORD__
default_pass_scheme = MD5-CRYPT
password_query = SELECT password FROM mailbox WHERE username = '%u'
user_query = SELECT maildir, 207 AS uid, 207 AS gid, CONCAT('maildir:storage=', FLOOR( quota / 1024 ) ) AS quota FROM mailbox WHERE username = '%u' AND active = '1'
EOF
```

## Postfix

Postfix wird inklusive MySQL, Dovecot-SASL und TLS/SSL Support installiert und für das Zusammenspiel mit PostfixAdmin konfiguriert. Zudem werden die Empfehlungen aus dem [Postfix Anti-UCE Cheat Sheet](https://jimsun.linxnet.com/misc/postfix-anti-UCE.txt){: target="\_blank" rel="noopener"} umgesetzt. Zusätzlich wird als gute und recht zuverlässige Anti-Spam Lösung `policyd-weight` eingerichtet.

### Postfix installieren

```shell
cat >> /etc/portage/package.use << "EOF"
mail-mta/postfix  dovecot-sasl vda
EOF

emerge -C ssmtp
emerge postfix

rc-update add postfix default
```

### policyd-weight installieren

```shell
cat >> /etc/portage/package.keywords << "EOF"
mail-filter/policyd-weight ~amd64
EOF

emerge policyd-weight

rc-update add policyd-weight default
```

### Postfix konfigurieren

Aliases einrichten:

```shell
cat > /etc/mail/aliases << "EOF"
# Basic system aliases -- these MUST be present.
MAILER-DAEMON:      postmaster
postmaster:         root

# General redirections for pseudo accounts.
adm:                root
bin:                root
daemon:             root
exim:               root
lp:                 root
mail:               root
named:              root
nobody:             root
postfix:            root

# Well-known aliases -- these should be filled in!
root:               admin@example.com
operator:           admin@example.com

# Standard RFC2142 aliases
abuse:              postmaster
ftp:                root
hostmaster:         root
news:               usenet
noc:                root
security:           root
usenet:             root
uucp:               root
webmaster:          root
www:                webmaster

# trap decode to catch security attacks
decode:             /dev/null
EOF

/usr/bin/newaliases
```

`main.cf` einrichten:

```shell
cat > /etc/postfix/main.cf << "EOF"
allow_percent_hack = no
biff = no
broken_sasl_auth_clients = yes
command_directory = /usr/sbin
config_directory = /etc/postfix
daemon_directory = /usr/lib64/postfix
data_directory = /var/lib/postfix
disable_vrfy_command = yes
home_mailbox = .maildir/
#html_directory = /usr/share/doc/postfix-2.6.6/html
inet_interfaces = 127.0.0.1, 10.0.2.15
mail_owner = postfix
mailq_path = /usr/bin/mailq
manpage_directory = /usr/share/man
mydestination = $myhostname, localhost.$mydomain, localhost
mydomain = example.com
myhostname = mail.$mydomain
mynetworks = 127.0.0.0/8, 10.0.2.15/32
myorigin = $mydomain
newaliases_path = /usr/bin/newaliases
proxy_read_maps =
  $local_recipient_maps
  $mydestination
  $virtual_alias_maps
  $virtual_alias_domains
  $virtual_mailbox_maps
  $virtual_mailbox_domains
  $relay_recipient_maps
  $relay_domains
  $canonical_maps
  $sender_canonical_maps
  $recipient_canonical_maps
  $relocated_maps
  $transport_maps
  $mynetworks
  $sender_bcc_maps
  $recipient_bcc_maps
  $smtp_generic_maps
  $lmtp_generic_maps
  $virtual_mailbox_limit_maps
queue_directory = /var/spool/postfix
#readme_directory = /usr/share/doc/postfix-2.6.6/readme
relay_domains = proxy:mysql:/etc/postfix/sql/mysql_relay_domains_maps.cf
sample_directory = /etc/postfix
sendmail_path = /usr/sbin/sendmail
setgid_group = postdrop
smtp_tls_ciphers = high
smtp_tls_exclude_ciphers = aNULL, RC4, MD5
smtp_tls_mandatory_ciphers = high
smtp_tls_mandatory_exclude_ciphers = aNULL, RC4, MD5
smtp_tls_mandatory_protocols = SSLv3, TLSv1, !SSLv2
smtp_tls_note_starttls_offer = yes
smtp_tls_protocols = SSLv3, TLSv1, !SSLv2
smtp_tls_received_header = yes
smtp_tls_security_level = may
smtp_tls_session_cache_database = btree:/var/lib/postfix/smtp_scache
smtp_tls_session_cache_timeout = 3600s
smtpd_client_restrictions =
  permit_mynetworks,
  permit_sasl_authenticated,
  reject_unknown_reverse_client_hostname,
  reject_unauth_pipelining,
  permit
smtpd_data_restrictions =
  permit_mynetworks,
  permit_sasl_authenticated,
  reject_unauth_pipelining,
  permit
smtpd_helo_required = yes
smtpd_helo_restrictions =
  permit_mynetworks,
  permit_sasl_authenticated,
  reject_invalid_helo_hostname,
  reject_non_fqdn_helo_hostname,
  reject_unauth_pipelining,
  permit
smtpd_recipient_restrictions =
  permit_mynetworks,
  permit_sasl_authenticated,
  reject_non_fqdn_recipient,
  reject_unknown_recipient_domain,
  check_recipient_mx_access cidr:/etc/postfix/mx_access,
  reject_unauth_destination,
  check_recipient_access pcre:/etc/postfix/recipient_checks.pcre,
  check_policy_service inet:127.0.0.1:12525,
  reject_unauth_pipelining,
  permit
smtpd_sasl_auth_enable = yes
smtpd_sasl_authenticated_header = yes
smtpd_sasl_path = private/auth
smtpd_sender_restrictions =
  permit_mynetworks,
  permit_sasl_authenticated,
  reject_non_fqdn_sender,
  reject_unknown_sender_domain,
  reject_unauth_pipelining,
  permit
smtpd_tls_CAfile = /etc/ssl/demoCA/cacert.pem
smtpd_tls_auth_only = yes
smtpd_tls_cert_file = /etc/ssl/mailserver_cert.pem
smtpd_tls_ciphers = high
smtpd_tls_dh1024_param_file = /etc/ssl/dh_1024.pem
smtpd_tls_dh512_param_file = /etc/ssl/dh_512.pem
smtpd_tls_exclude_ciphers = aNULL, RC4, MD5
smtpd_tls_key_file = /etc/ssl/mailserver_keyrsa.pem
smtpd_tls_mandatory_ciphers = high
smtpd_tls_mandatory_exclude_ciphers = aNULL, RC4, MD5
smtpd_tls_mandatory_protocols = SSLv3, TLSv1, !SSLv2
smtpd_tls_protocols = SSLv3, TLSv1, !SSLv2
smtpd_tls_received_header = yes
smtpd_tls_security_level = may
smtpd_tls_session_cache_database = btree:/var/lib/postfix/smtpd_scache
smtpd_tls_session_cache_timeout = 3600s
strict_rfc821_envelopes = yes
transport_maps = hash:/etc/postfix/transport
unknown_local_recipient_reject_code = 450
virtual_minimum_uid = 125
virtual_uid_maps = static:125
virtual_gid_maps = static:125
virtual_mailbox_base = /var/vmail
virtual_mailbox_domains = proxy:mysql:/etc/postfix/sql/mysql_virtual_domains_maps.cf
virtual_alias_maps =
   proxy:mysql:/etc/postfix/sql/mysql_virtual_alias_maps.cf,
   proxy:mysql:/etc/postfix/sql/mysql_virtual_alias_domain_maps.cf,
   proxy:mysql:/etc/postfix/sql/mysql_virtual_alias_domain_catchall_maps.cf
virtual_mailbox_maps =
   proxy:mysql:/etc/postfix/sql/mysql_virtual_mailbox_maps.cf,
   proxy:mysql:/etc/postfix/sql/mysql_virtual_alias_domain_mailbox_maps.cf
virtual_create_maildirsize = yes
virtual_mailbox_extended = yes
virtual_mailbox_limit_maps = mysql:/etc/postfix/sql/mysql_virtual_mailbox_limit_maps.cf
virtual_mailbox_limit_override = yes
virtual_maildir_limit_message = Sorry, the user's maildir has overdrawn his diskspace quota, please try again later.
virtual_overquota_bounce = yes
EOF
```

`master.cf` einrichten:

```shell
cat > /etc/postfix/master.cf << "EOF"
#
# Postfix master process configuration file.  For details on the format
# of the file, see the master(5) manual page (command: "man 5 master").
#
# Do not forget to execute "postfix reload" after editing this file.
#
# ==========================================================================
# service type  private unpriv  chroot  wakeup  maxproc command + args
#               (yes)   (yes)   (yes)   (never) (100)
# ==========================================================================
smtp      inet  n       -       n       -       -       smtpd
submission inet n       -       n       -       -       smtpd
  -o smtpd_tls_security_level=encrypt
  -o smtpd_sasl_auth_enable=yes
  -o smtpd_client_restrictions=permit_sasl_authenticated,reject
  -o milter_macro_daemon_name=ORIGINATING
smtps     inet  n       -       n       -       -       smtpd
  -o smtpd_tls_wrappermode=yes
  -o smtpd_sasl_auth_enable=yes
  -o smtpd_client_restrictions=permit_sasl_authenticated,reject
  -o milter_macro_daemon_name=ORIGINATING
#628      inet  n       -       n       -       -       qmqpd
pickup    fifo  n       -       n       60      1       pickup
cleanup   unix  n       -       n       -       0       cleanup
qmgr      fifo  n       -       n       300     1       qmgr
#qmgr     fifo  n       -       n       300     1       oqmgr
tlsmgr    unix  -       -       n       1000?   1       tlsmgr
rewrite   unix  -       -       n       -       -       trivial-rewrite
bounce    unix  -       -       n       -       0       bounce
defer     unix  -       -       n       -       0       bounce
trace     unix  -       -       n       -       0       bounce
verify    unix  -       -       n       -       1       verify
flush     unix  n       -       n       1000?   0       flush
proxymap  unix  -       -       n       -       -       proxymap
proxywrite unix -       -       n       -       1       proxymap
smtp      unix  -       -       n       -       -       smtp
# When relaying mail as backup MX, disable fallback_relay to avoid MX loops
relay     unix  -       -       n       -       -       smtp
        -o smtp_fallback_relay=
#       -o smtp_helo_timeout=5 -o smtp_connect_timeout=5
showq     unix  n       -       n       -       -       showq
error     unix  -       -       n       -       -       error
retry     unix  -       -       n       -       -       error
discard   unix  -       -       n       -       -       discard
local     unix  -       n       n       -       -       local
virtual   unix  -       n       n       -       -       virtual
lmtp      unix  -       -       n       -       -       lmtp
anvil     unix  -       -       n       -       1       anvil
scache    unix  -       -       n       -       1       scache
#
# ====================================================================
# Interfaces to non-Postfix software. Be sure to examine the manual
# pages of the non-Postfix software to find out what options it wants.
#
# Many of the following services use the Postfix pipe(8) delivery
# agent.  See the pipe(8) man page for information about ${recipient}
# and other message envelope options.
# ====================================================================
#
# maildrop. See the Postfix MAILDROP_README file for details.
# Also specify in main.cf: maildrop_destination_recipient_limit=1
#
#maildrop  unix  -       n       n       -       -       pipe
#  flags=DRhu user=vmail argv=/usr/bin/maildrop -d ${recipient}
#
# ====================================================================
#
# The Cyrus deliver program has changed incompatibly, multiple times.
#
#old-cyrus unix  -       n       n       -       -       pipe
#  flags=R user=cyrus argv=/cyrus/bin/deliver -e -m ${extension} ${user}
#
# ====================================================================
#
# Cyrus 2.1.5 (Amos Gouaux)
# Also specify in main.cf: cyrus_destination_recipient_limit=1
#
#cyrus     unix  -       n       n       -       -       pipe
#  user=cyrus argv=/cyrus/bin/deliver -e -r ${sender} -m ${extension} ${user}
#
# ====================================================================
#
# See the Postfix UUCP_README file for configuration details.
#
#uucp      unix  -       n       n       -       -       pipe
#  flags=Fqhu user=uucp argv=uux -r -n -z -a$sender - $nexthop!rmail ($recipient)
#
# ====================================================================
#
# Other external delivery methods.
#
#ifmail    unix  -       n       n       -       -       pipe
#  flags=F user=ftn argv=/usr/lib/ifmail/ifmail -r $nexthop ($recipient)
#
#bsmtp     unix  -       n       n       -       -       pipe
#  flags=Fq. user=bsmtp argv=/usr/sbin/bsmtp -f $sender $nexthop $recipient
#
#scalemail-backend unix -       n       n       -       2       pipe
#  flags=R user=scalemail argv=/usr/lib/scalemail/bin/scalemail-store
#  ${nexthop} ${user} ${extension}
#
#mailman   unix  -       n       n       -       -       pipe
#  flags=FR user=list argv=/usr/lib/mailman/bin/postfix-to-mailman.py
#  ${nexthop} ${user}
EOF
```

`mysql_*_maps.cf` einrichten:

!!!note
Bitte jeweils das gleiche Passwort wie in der `dovecot-sql.conf` aus der Dovecot Konfiguration verwenden.
!!!

```shell
mkdir -p /etc/postfix/sql

cat > /etc/postfix/sql/mysql_relay_domains_maps.cf << "EOF"
user = postfix
password = __PASSWORD__
hosts = localhost
dbname = postfix
query = SELECT domain FROM domain WHERE domain = '%s' AND backupmx = '1' AND active = '1'
EOF

cat > /etc/postfix/sql/mysql_virtual_alias_domain_catchall_maps.cf << "EOF"
user = postfix
password = __PASSWORD__
hosts = localhost
dbname = postfix
query = SELECT goto FROM alias,alias_domain WHERE alias_domain.alias_domain = '%d' and alias.address = CONCAT('@', alias_domain.target_domain) AND alias.active = '1' AND alias_domain.active = '1'
EOF

cat > /etc/postfix/sql/mysql_virtual_alias_domain_mailbox_maps.cf << "EOF"
user = postfix
password = __PASSWORD__
hosts = localhost
dbname = postfix
query = SELECT maildir FROM mailbox,alias_domain WHERE alias_domain.alias_domain = '%d' and mailbox.username = CONCAT('%u', '@', alias_domain.target_domain) AND mailbox.active = '1' AND alias_domain.active = '1'
EOF

cat > /etc/postfix/sql/mysql_virtual_alias_domain_maps.cf << "EOF"
user = postfix
password = __PASSWORD__
hosts = localhost
dbname = postfix
query = SELECT goto FROM alias,alias_domain WHERE alias_domain.alias_domain = '%d' and alias.address = CONCAT('%u', '@', alias_domain.target_domain) AND alias.active = '1' AND alias_domain.active = '1'
EOF

cat > /etc/postfix/sql/mysql_virtual_alias_maps.cf << "EOF"
user = postfix
password = __PASSWORD__
hosts = localhost
dbname = postfix
query = SELECT goto FROM alias WHERE address = '%s' AND active = '1'
EOF

cat > /etc/postfix/sql/mysql_virtual_domains_maps.cf << "EOF"
user = postfix
password = __PASSWORD__
hosts = localhost
dbname = postfix
query = SELECT domain FROM domain WHERE domain = '%s' AND backupmx = '0' AND active = '1'
EOF

cat > /etc/postfix/sql/mysql_virtual_mailbox_limit_maps.cf << "EOF"
user = postfix
password = __PASSWORD__
hosts = localhost
dbname = postfix
query = SELECT quota FROM mailbox WHERE username = '%s' AND active = '1'
EOF

cat > /etc/postfix/sql/mysql_virtual_mailbox_maps.cf << "EOF"
user = postfix
password = __PASSWORD__
hosts = localhost
dbname = postfix
query = SELECT maildir FROM mailbox WHERE username = '%s' AND active = '1'
EOF

chown root:postfix /etc/postfix/sql/mysql_*_maps.cf
chmod 0640 /etc/postfix/sql/mysql_*_maps.cf
```

Transport map einrichten:

```shell
cat >> /etc/postfix/transport << "EOF"
autoreply.example.com   vacation:
EOF

postmap /etc/postfix/transport
```

Restriktionen einrichten:

```shell
cat > /etc/postfix/recipient_checks.pcre << "EOF"
/^\@/             550 Invalid address format.
/[!%\@].*\@/      550 This server disallows weird address syntax.
/^postmaster\@/   OK
/^hostmaster\@/   OK
/^security\@/     OK
/^abuse\@/        OK
/^admin\@/        OK
EOF

cat > /etc/postfix/mx_access << "EOF"
0.0.0.0/8            REJECT MX in RFC 5735 broadcast network
10.0.0.0/8           REJECT MX in RFC 5735 private network
127.0.0.0/8          REJECT MX in RFC 5735 loopback network
169.254.0.0/16       REJECT MX in RFC 5735 link local network
172.16.0.0/12        REJECT MX in RFC 5735 private network
192.0.0.0/24         REJECT MX in RFC 5735 IETF protocol assignments network
192.0.2.0/24         REJECT MX in RFC 5735 TEST-NET-1 network
192.88.99.0/24       REJECT MX in RFC 5735 6to4 relay anycast network
192.168.0.0/16       REJECT MX in RFC 5735 private network
198.18.0.0/15        REJECT MX in RFC 5735 interconnect device benchmark testing network
198.51.100.0/24      REJECT MX in RFC 5735 TEST-NET-2 network
203.0.113.0/24       REJECT MX in RFC 5735 TEST-NET-3 network
224.0.0.0/4          REJECT MX in RFC 5735 multicast network
240.0.0.0/4          REJECT MX in RFC 5735 reserved network
255.255.255.255/32   REJECT MX in RFC 5735 limited broadcast destination address
EOF

postmap /etc/postfix/mx_access
```

Abschliessende Arbeiten:

```shell
gpasswd -a postfix mail

mkdir -p /var/vmail
chmod 0750 /var/vmail
chown postfix:postfix /var/vmail
```

## Apache

Die folgende Konfiguration verwendet für den Default-Host den Pfad `/var/www/vhosts/www.example.com`, für den Default-SSL-Host den Pfad `/var/www/vhosts/ssl.example.com` und für die regulären Virtual-Hosts den Pfad `/var/www/vhosts/sub.domain.tld`.

### Apache installieren

```shell
cat >> /etc/portage/package.use << "EOF"
dev-libs/apr-util  -mysql
www-servers/apache  -threads suexec
EOF

cat >> /etc/make.conf << "EOF"
APACHE2_MODULES="*"
APACHE2_MPMS="prefork"
EOF

emerge apache

rc-update add apache2 default
```

### Apache konfigurieren

Docroots für die Default-Hosts erstellen:

```shell
mkdir -p /var/www/vhosts/www.example.com/logs
mkdir -p /var/www/vhosts/www.example.com/data
chmod 0750 /var/www/vhosts/www.example.com/data
chown apache:apache /var/www/vhosts/www.example.com/data

mkdir -p /var/www/vhosts/ssl.example.com/logs
mkdir -p /var/www/vhosts/ssl.example.com/data
chmod 0750 /var/www/vhosts/ssl.example.com/data
chown apache:apache /var/www/vhosts/ssl.example.com/data
```

Grundkonfiguration:

```shell
emerge --config apache

sed 's@^APACHE2_OPTS\(.*\)@#APACHE2_OPTS\1\
APACHE2_OPTS=""@' -i /etc/conf.d/apache2
sed 's@^#RELOAD_TYPE@RELOAD_TYPE@' -i /etc/conf.d/apache2
```

`httpd.conf` einrichten:

```shell
cat > /etc/apache2/httpd.conf << "EOF"
ServerRoot "/usr/lib64/apache2"
PidFile "/var/run/apache2.pid"
LockFile "/var/run/apache2.lock"
Timeout 30
KeepAlive On
KeepAliveTimeout 2
MaxKeepAliveRequests 100
<IfModule mpm_prefork_module>
    StartServers         10
    MinSpareServers      10
    MaxSpareServers      10
    MaxClients          200
    MaxRequestsPerChild 500
</IfModule>
<IfModule mpm_worker_module>
    StartServers          2
    MinSpareThreads      25
    MaxSpareThreads      75
    ThreadsPerChild      25
    MaxClients          150
    MaxRequestsPerChild 500
</IfModule>
Listen 10.0.2.15:80
LoadModule actions_module modules/mod_actions.so
LoadModule alias_module modules/mod_alias.so
#LoadModule asis_module modules/mod_asis.so
LoadModule auth_basic_module modules/mod_auth_basic.so
#LoadModule auth_digest_module modules/mod_auth_digest.so
#LoadModule authn_alias_module modules/mod_authn_alias.so
#LoadModule authn_anon_module modules/mod_authn_anon.so
#LoadModule authn_dbd_module modules/mod_authn_dbd.so
#LoadModule authn_dbm_module modules/mod_authn_dbm.so
LoadModule authn_default_module modules/mod_authn_default.so
LoadModule authn_file_module modules/mod_authn_file.so
#LoadModule authz_dbm_module modules/mod_authz_dbm.so
LoadModule authz_default_module modules/mod_authz_default.so
LoadModule authz_groupfile_module modules/mod_authz_groupfile.so
LoadModule authz_host_module modules/mod_authz_host.so
#LoadModule authz_owner_module modules/mod_authz_owner.so
LoadModule authz_user_module modules/mod_authz_user.so
LoadModule autoindex_module modules/mod_autoindex.so
#LoadModule cache_module modules/mod_cache.so
#LoadModule cern_meta_module modules/mod_cern_meta.so
LoadModule cgi_module modules/mod_cgi.so
#LoadModule cgid_module modules/mod_cgid.so
#LoadModule charset_lite_module modules/mod_charset_lite.so
#LoadModule dav_module modules/mod_dav.so
#LoadModule dav_fs_module modules/mod_dav_fs.so
#LoadModule dav_lock_module modules/mod_dav_lock.so
#LoadModule dbd_module modules/mod_dbd.so
LoadModule deflate_module modules/mod_deflate.so
LoadModule dir_module modules/mod_dir.so
#LoadModule disk_cache_module modules/mod_disk_cache.so
#LoadModule dumpio_module modules/mod_dumpio.so
LoadModule env_module modules/mod_env.so
LoadModule expires_module modules/mod_expires.so
#LoadModule ext_filter_module modules/mod_ext_filter.so
#LoadModule file_cache_module modules/mod_file_cache.so
#LoadModule filter_module modules/mod_filter.so
LoadModule headers_module modules/mod_headers.so
#LoadModule ident_module modules/mod_ident.so
#LoadModule imagemap_module modules/mod_imagemap.so
LoadModule include_module modules/mod_include.so
LoadModule info_module modules/mod_info.so
LoadModule log_config_module modules/mod_log_config.so
#LoadModule log_forensic_module modules/mod_log_forensic.so
#LoadModule logio_module modules/mod_logio.so
#LoadModule mem_cache_module modules/mod_mem_cache.so
LoadModule mime_module modules/mod_mime.so
#LoadModule mime_magic_module modules/mod_mime_magic.so
#LoadModule negotiation_module modules/mod_negotiation.so
#LoadModule proxy_module modules/mod_proxy.so
#LoadModule proxy_ajp_module modules/mod_proxy_ajp.so
#LoadModule proxy_balancer_module modules/mod_proxy_balancer.so
#LoadModule proxy_connect_module modules/mod_proxy_connect.so
#LoadModule proxy_ftp_module modules/mod_proxy_ftp.so
#LoadModule proxy_http_module modules/mod_proxy_http.so
LoadModule rewrite_module modules/mod_rewrite.so
LoadModule setenvif_module modules/mod_setenvif.so
#LoadModule speling_module modules/mod_speling.so
LoadModule ssl_module modules/mod_ssl.so
LoadModule status_module modules/mod_status.so
#LoadModule substitute_module modules/mod_substitute.so
LoadModule suexec_module modules/mod_suexec.so
LoadModule unique_id_module modules/mod_unique_id.so
#LoadModule userdir_module modules/mod_userdir.so
#LoadModule usertrack_module modules/mod_usertrack.so
#LoadModule version_module modules/mod_version.so
#LoadModule vhost_alias_module modules/mod_vhost_alias.so
User apache
Group apache
ServerTokens OS
ServerSignature On
UseCanonicalName On
TraceEnable off
<Directory "/">
    Options -All +FollowSymLinks
    AllowOverride None
    Order Deny,Allow
    Deny from all
</Directory>
ServerName www.example.com
ServerAdmin webmaster@example.com
DocumentRoot "/var/www/vhosts/www.example.com/data"
<Directory "/var/www/vhosts/www.example.com/data">
    Options -All +FollowSymLinks +ExecCGI
    AllowOverride Options FileInfo AuthConfig Limit
    Order Allow,Deny
    Allow from all
</Directory>
DirectoryIndex index.html index.htm index.php
AccessFileName .htaccess
<FilesMatch "^[\._]ht">
    Order Allow,Deny
    Deny from all
</FilesMatch>
TypesConfig "/etc/mime.types"
DefaultType text/plain
<IfModule mime_magic_module>
    MIMEMagicFile "/etc/apache2/magic"
</IfModule>
HostnameLookups On
<IfModule logio_module>
    LogFormat "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\" %I %O" combinedio
</IfModule>
LogFormat "%h %l %u %t \"%r\" %>s %b \"%{Referer}i\" \"%{User-Agent}i\"" combined
LogFormat "%h %l %u %t \"%r\" %>s %b" common
CustomLog "/var/www/vhosts/www.example.com/logs/access_log" combined
ErrorLog "/var/www/vhosts/www.example.com/logs/error_log"
LogLevel warn
<IfModule cgid_module>
    Scriptsock "/var/run/cgisock"
</IfModule>
ReadmeName README.html
HeaderName HEADER.html
IndexOptions FancyIndexing VersionSort FoldersFirst IgnoreCase IgnoreClient NameWidth=* SuppressDescription XHTML
IndexIgnore .??* *~ *# HEADER* README* RCS CVS *,v *,t .svn
IndexOrderDefault Ascending Name
Alias "/icons/" "/usr/share/apache2/icons/"
<Directory "/usr/share/apache2/icons">
    Options -All +MultiViews
    AllowOverride None
    Order Allow,Deny
    Allow from all
</Directory>
AddIconByEncoding (CMP,/icons/compressed.gif) x-compress x-gzip
AddIconByType (TXT,/icons/text.gif) text/*
AddIconByType (IMG,/icons/image2.gif) image/*
AddIconByType (SND,/icons/sound2.gif) audio/*
AddIconByType (VID,/icons/movie.gif) video/*
AddIcon /icons/binary.gif .bin .exe
AddIcon /icons/binhex.gif .hqx
AddIcon /icons/tar.gif .tar
AddIcon /icons/world2.gif .wrl .wrl.gz .vrml .vrm .iv
AddIcon /icons/compressed.gif .Z .z .tgz .gz .zip
AddIcon /icons/a.gif .ps .ai .eps
AddIcon /icons/layout.gif .html .shtml .htm .pdf
AddIcon /icons/text.gif .txt
AddIcon /icons/c.gif .c
AddIcon /icons/p.gif .pl .py
AddIcon /icons/f.gif .for
AddIcon /icons/dvi.gif .dvi
AddIcon /icons/uuencoded.gif .uu
AddIcon /icons/script.gif .conf .sh .shar .csh .ksh .tcl
AddIcon /icons/tex.gif .tex
AddIcon /icons/bomb.gif core
AddIcon /icons/back.gif ..
AddIcon /icons/hand.right.gif README
AddIcon /icons/folder.gif ^^DIRECTORY^^
AddIcon /icons/blank.gif ^^BLANKICON^^
DefaultIcon /icons/unknown.gif
AddType application/x-httpd-php-source .phps
AddType application/x-httpd-php .php
AddType application/x-gzip .gz .tgz
AddType application/x-compress .Z
AddType text/x-component .htc
AddType text/html .shtml
AddOutputFilter INCLUDES .shtml
AddHandler php5-script .php .phps
AddHandler cgi-script .cgi .pl
FileETag None
Header unset ETag
Header unset Pragma
Header set Vary "Accept-Encoding, User-Agent"
Header set Cache-Control "public, proxy-revalidate, must-revalidate"
ExpiresActive On
ExpiresDefault A604800
ExpiresByType text/cache-manifest A0
<FilesMatch "\.(phps|php|pl|py|cgi)$">
    ExpiresDefault A0
</FilesMatch>
<FilesMatch "favicon\.(ico|png)$">
    AddType image/vnd.microsoft.icon .ico
    ExpiresDefault A2592000
</FilesMatch>
<FilesMatch "\.(ico|gif|png|jpe?g|css)$">
    RequestHeader unset Cookie
    Header unset Set-Cookie
</FilesMatch>
<IfModule dav_module>
    <IfModule dav_fs_module>
        DavLockDB "/var/lib/dav/lockdb"
        Alias "/uploads/" "/var/www/uploads/"
        <Directory "/var/www/uploads">
            Dav On
            AuthType Digest
            AuthName DAV-upload
            AuthUserFile "/var/www/.htpasswd-dav"
            Order allow,deny
            Allow from all
            <LimitExcept GET OPTIONS>
                require user admin
            </LimitExcept>
        </Directory>
    </IfModule>
</IfModule>
<IfModule cache_module>
    <IfModule mem_cache_module>
        CacheEnable mem "/"
        MCacheSize 131072
        MCacheMaxObjectCount 1000
        MCacheMinObjectSize 1
        MCacheMaxObjectSize 2048
    </IfModule>
</IfModule>
<IfModule userdir_module>
    UserDir public_html
    UserDir disabled root toor daemon operator bin tty kmem games news man sshd bind proxy _pflogd _dhcp uucp pop www nobody mailnull smmsp admin
    <Directory "/home/*/public_html">
        AllowOverride FileInfo AuthConfig Limit Indexes
        Options MultiViews Indexes SymLinksIfOwnerMatch IncludesNoExec
        <Limit GET POST OPTIONS>
            Order Allow,Deny
            Allow from all
        </Limit>
        <LimitExcept GET POST OPTIONS>
            Order Deny,Allow
            Deny from all
        </LimitExcept>
    </Directory>
    <Directory /home/*/public_html/cgi-bin>
        Options ExecCGI
        SetHandler cgi-script
    </Directory>
</IfModule>
<IfModule info_module>
    <IfModule status_module>
        <Location "/server-status">
            SetHandler server-status
            Order Deny,Allow
            Deny from all
            Allow from localhost
        </Location>
        ExtendedStatus On
        <Location "/server-info">
            SetHandler server-info
            Order Deny,Allow
            Deny from all
            Allow from localhost
        </Location>
    </IfModule>
</IfModule>
<Location "/">
    SetEnvIfNoCase Request_URI \.(?:ico|gif|jpe?g|png|pdf|flv|bz2|gz|tgz|zip|htc)$ no-gzip
    SetOutputFilter DEFLATE
</Location>
<IfModule ssl_module>
    Listen 10.0.2.15:443
    AddType application/x-x509-ca-cert .crt
    AddType application/x-pkcs7-crl .crl
    SSLRandomSeed startup builtin
    SSLRandomSeed connect builtin
    SSLPassPhraseDialog builtin
    SSLSessionCache "shmcb:/var/run/ssl_scache(512000)"
    SSLSessionCacheTimeout 300
    SSLMutex "file:/var/run/ssl_mutex"
    <VirtualHost 10.0.2.15:443>
        ServerName ssl.example.com
        ServerAdmin webmaster@example.com
        CustomLog "/var/www/vhosts/ssl.example.com/logs/ssl_request_log" "%t %h %{SSL_PROTOCOL}x %{SSL_CIPHER}x \"%r\" %b"
        TransferLog "/var/www/vhosts/ssl.example.com/logs/access_log"
        ErrorLog "/var/www/vhosts/ssl.example.com/logs/error_log"
        DocumentRoot "/var/www/vhosts/ssl.example.com/data"
        <Directory "/var/www/vhosts/ssl.example.com/data">
            Options -All +FollowSymLinks +ExecCGI
            AllowOverride Options FileInfo AuthConfig Limit
            Order Allow,Deny
            Allow from all
        </Directory>
        SSLEngine on
        SSLProtocol all -SSLv2
        SSLCipherSuite RSA:!EXP:!NULL:+HIGH:+MEDIUM:-LOW:-SSLv2
        SSLCertificateFile "/etc/ssl/webserver_cert.pem"
        SSLCertificateKeyFile "/etc/ssl/webserver_keyrsa.pem"
        <FilesMatch "\.(phps|php|pl|py|cgi|shtml)$">
            SSLOptions +StdEnvVars
        </FilesMatch>
        BrowserMatch ".*MSIE.*" nokeepalive ssl-unclean-shutdown downgrade-1.0 force-response-1.0
    </VirtualHost>
    AcceptPathInfo On
</IfModule>
NameVirtualHost 10.0.2.15:80
<VirtualHost 10.0.2.15:80>
    ServerName www.example.com
    CustomLog "/var/www/vhosts/www.example.com/logs/access_log" combined
    ErrorLog "/var/www/vhosts/www.example.com/logs/error_log"
    DocumentRoot "/var/www/vhosts/www.example.com/data"
    <Directory "/var/www/vhosts/www.example.com/data">
        Options -All +FollowSymLinks +ExecCGI
        AllowOverride Options FileInfo AuthConfig Limit
        Order Allow,Deny
        Allow from all
    </Directory>
    AcceptPathInfo On
</VirtualHost>
<VirtualHost 10.0.2.15:80>
    ServerName example.com
    Redirect 301 / https://www.example.com/
</VirtualHost>
EOF
```

## PHP

Die Konfiguration entspricht weitestgehend den Empfehlungen der PHP-Entwickler und ist somit sowohl auf Security als auch auf Performance getrimmt.

### PHP installieren

```shell
cat >> /etc/portage/package.keywords << "EOF"
dev-lang/php ~amd64
EOF

cat >> /etc/portage/package.use << "EOF"
dev-db/sqlite  threadsafe
dev-lang/php  apache2 calendar ctype curl curlwrappers discard-path flatfile fileinfo filter force-cgi-redirect hash inifile intl json pcntl pdo phar simplexml soap sqlite sqlite3 suhosin sysvipc wddx xmlreader xmlwriter zip -gdbm -readline -threads
EOF

emerge php
```

### PHP konfigurieren

`php.ini` einrichten:

```shell
cat > /etc/php/cgi-php5/php.ini << "EOF"
[PHP]
user_ini.filename =
user_ini.cache_ttl = 300
engine = On
short_open_tag = Off
asp_tags = Off
precision = 14
y2k_compliance = On
output_buffering = 4096
output_handler =
zlib.output_compression = Off
zlib.output_compression_level = -1
zlib.output_handler =
implicit_flush = Off
unserialize_callback_func =
serialize_precision = 100
allow_call_time_pass_reference = Off
safe_mode = Off
safe_mode_gid = Off
safe_mode_include_dir =
safe_mode_exec_dir =
safe_mode_allowed_env_vars = PHP_
safe_mode_protected_env_vars = LD_LIBRARY_PATH
open_basedir =
disable_functions =
disable_classes =
highlight.string = #DD0000
highlight.comment = #FF9900
highlight.keyword = #007700
highlight.bg = #FFFFFF
highlight.default = #0000BB
highlight.html = #000000
ignore_user_abort = Off
realpath_cache_size = 16k
realpath_cache_ttl = 120
expose_php = Off
max_execution_time = 30
max_input_time = 60
max_input_nesting_level = 64
memory_limit = 128M
error_reporting = E_ALL & ~E_DEPRECATED
display_errors = Off
display_startup_errors = Off
log_errors = On
log_errors_max_len = 1024
ignore_repeated_errors = Off
ignore_repeated_source = Off
report_memleaks = On
;report_zend_debug = 0
track_errors = Off
;xmlrpc_errors = 0
;xmlrpc_error_number = 0
html_errors = Off
;docref_root = "/phpmanual/"
;docref_ext = .html
;error_prepend_string = "<font color=#ff0000>"
;error_append_string = "</font>"
;error_log = php_errors.log
arg_separator.output = "&"
arg_separator.input = ";&"
variables_order = "GPCS"
request_order = "GP"
register_globals = Off
register_long_arrays = Off
register_argc_argv = Off
auto_globals_jit = On
post_max_size = 8M
magic_quotes_gpc = Off
magic_quotes_runtime = Off
magic_quotes_sybase = Off
auto_prepend_file =
auto_append_file =
default_mimetype = "text/html"
;default_charset = "iso-8859-1"
;always_populate_raw_post_data = On
include_path = ".:/usr/share/php"
doc_root =
user_dir =
;extension_dir = "./"
enable_dl = Off
cgi.force_redirect = 1
;cgi.nph = 1
;cgi.redirect_status_env = ;
cgi.fix_pathinfo=1
;fastcgi.impersonate = 1;
;fastcgi.logging = 0
;cgi.rfc2616_headers = 0
file_uploads = On
upload_tmp_dir = "/tmp"
upload_max_filesize = 2M
max_file_uploads = 20
allow_url_fopen = On
allow_url_include = Off
from = "anonymous@example.com"
user_agent = ""
default_socket_timeout = 60
;auto_detect_line_endings = Off

[Date]
date.timezone = Europe/Berlin
;date.default_latitude = 31.7667
;date.default_longitude = 35.2333
;date.sunrise_zenith = 90.583333
;date.sunset_zenith = 90.583333

[filter]
;filter.default = unsafe_raw
;filter.default_flags =

[iconv]
;iconv.input_encoding = ISO-8859-1
;iconv.internal_encoding = ISO-8859-1
;iconv.output_encoding = ISO-8859-1

[intl]
;intl.default_locale =
;intl.error_level = E_WARNING

[sqlite]
;sqlite.assoc_case = 0

[sqlite3]
;sqlite3.extension_dir =

[Pcre]
;pcre.backtrack_limit = 100000
;pcre.recursion_limit = 100000

[Pdo]
;pdo_odbc.connection_pooling = strict
;pdo_odbc.db2_instance_name =

[Pdo_mysql]
pdo_mysql.cache_size = 2000
pdo_mysql.default_socket =

[Phar]
;phar.readonly = On
;phar.require_hash = On
;phar.cache_list =

[Syslog]
define_syslog_variables  = Off

[mail function]
SMTP = localhost
smtp_port = 25
;sendmail_from = me@example.com
;sendmail_path =
;mail.force_extra_parameters =
mail.add_x_header = On
;mail.log =

[SQL]
sql.safe_mode = Off

[ODBC]
;odbc.default_cursortype =
odbc.allow_persistent = On
odbc.check_persistent = On
odbc.max_persistent = -1
odbc.max_links = -1
odbc.defaultlrl = 4096
odbc.defaultbinmode = 1
;birdstep.max_links = -1

[Interbase]
ibase.allow_persistent = 1
ibase.max_persistent = -1
ibase.max_links = -1
;ibase.default_db =
;ibase.default_user =
;ibase.default_password =
;ibase.default_charset =
ibase.timestampformat = "%Y-%m-%d %H:%M:%S"
ibase.dateformat = "%Y-%m-%d"
ibase.timeformat = "%H:%M:%S"

[MySQL]
mysql.allow_local_infile = On
mysql.allow_persistent = On
mysql.cache_size = 2000
mysql.max_persistent = -1
mysql.max_links = -1
mysql.default_port =
mysql.default_socket =
mysql.default_host =
mysql.default_user =
mysql.default_password =
mysql.connect_timeout = 60
mysql.trace_mode = Off

[MySQLi]
mysqli.max_persistent = -1
mysqli.allow_local_infile = On
mysqli.allow_persistent = On
mysqli.max_links = -1
mysqli.cache_size = 2000
mysqli.default_port = 3306
mysqli.default_socket =
mysqli.default_host =
mysqli.default_user =
mysqli.default_pw =
mysqli.reconnect = Off

[mysqlnd]
mysqlnd.collect_statistics = On
mysqlnd.collect_memory_statistics = Off
;mysqlnd.net_cmd_buffer_size = 2048
;mysqlnd.net_read_buffer_size = 32768

[OCI8]
;oci8.privileged_connect = Off
;oci8.max_persistent = -1
;oci8.persistent_timeout = -1
;oci8.ping_interval = 60
;oci8.connection_class =
;oci8.events = Off
;oci8.statement_cache_size = 20
;oci8.default_prefetch = 100
;oci8.old_oci_close_semantics = Off

[PostgresSQL]
pgsql.allow_persistent = On
pgsql.auto_reset_persistent = Off
pgsql.max_persistent = -1
pgsql.max_links = -1
pgsql.ignore_notice = 0
pgsql.log_notice = 0

[Sybase-CT]
sybct.allow_persistent = On
sybct.max_persistent = -1
sybct.max_links = -1
sybct.min_server_severity = 10
sybct.min_client_severity = 10
;sybct.timeout =
;sybct.packet_size =
;sybct.login_timeout =
;sybct.hostname =
;sybct.deadlock_retry_count =

[bcmath]
bcmath.scale = 0

[browscap]
;browscap = extra/browscap.ini

[Session]
session.save_handler = files
session.save_path = "/tmp"
session.use_cookies = 1
;session.cookie_secure =
session.use_only_cookies = 1
session.name = PHPSESSID
session.auto_start = 0
session.cookie_lifetime = 0
session.cookie_path = /
session.cookie_domain =
session.cookie_httponly =
session.serialize_handler = php
session.gc_probability = 1
session.gc_divisor = 1000
session.gc_maxlifetime = 1440
session.bug_compat_42 = Off
session.bug_compat_warn = Off
session.referer_check =
session.entropy_file = /dev/urandom
session.entropy_length = 16
session.cache_limiter = nocache
session.cache_expire = 180
session.use_trans_sid = 0
session.hash_function = 1
session.hash_bits_per_character = 6
url_rewriter.tags = "a=href,area=href,frame=src,input=src,form=fakeentry,fieldset="

[MSSQL]
mssql.allow_persistent = On
mssql.max_persistent = -1
mssql.max_links = -1
mssql.min_error_severity = 10
mssql.min_message_severity = 10
mssql.compatability_mode = Off
;mssql.connect_timeout = 5
;mssql.timeout = 60
;mssql.textlimit = 4096
;mssql.textsize = 4096
;mssql.batchsize = 0
;mssql.datetimeconvert = On
mssql.secure_connection = Off
;mssql.max_procs = -1
;mssql.charset = "ISO-8859-1"

[Assertion]
;assert.active = On
;assert.warning = On
;assert.bail = Off
;assert.callback = 0
;assert.quiet_eval = 0

[COM]
;com.typelib_file =
;com.allow_dcom = true
;com.autoregister_typelib = true
;com.autoregister_casesensitive = false
;com.autoregister_verbose = true
;com.code_page =

[mbstring]
;mbstring.language = Japanese
;mbstring.internal_encoding = EUC-JP
;mbstring.http_input = auto
;mbstring.http_output = SJIS
;mbstring.encoding_translation = Off
;mbstring.detect_order = auto
;mbstring.substitute_character = none;
;mbstring.func_overload = 0
;mbstring.strict_detection = Off
;mbstring.http_output_conv_mimetype =
;mbstring.script_encoding =

[gd]
;gd.jpeg_ignore_warning = 0

[exif]
;exif.encode_unicode = ISO-8859-15
;exif.decode_unicode_motorola = UCS-2BE
;exif.decode_unicode_intel = UCS-2LE
;exif.encode_jis =
;exif.decode_jis_motorola = JIS
;exif.decode_jis_intel = JIS

[Tidy]
;tidy.default_config = /usr/lib64/php5/default.tcfg
tidy.clean_output = Off

[soap]
soap.wsdl_cache_enabled = 1
soap.wsdl_cache_dir = "/tmp"
soap.wsdl_cache_ttl = 86400
soap.wsdl_cache_limit = 5

[sysvshm]
;sysvshm.init_mem = 10000

[ldap]
ldap.max_links = -1

[mcrypt]
;mcrypt.algorithms_dir =
;mcrypt.modes_dir =

[dba]
;dba.default_handler =
EOF

cp /etc/php/cgi-php5/php.ini /etc/php/cli-php5/php.ini
cp /etc/php/cgi-php5/php.ini /etc/php/apache2-php5/php.ini
```

### PHP-PEAR installieren

```shell
echo "dev-php/pear ~amd64" >> /etc/portage/package.keywords
for pkg in `ls /usr/portage/dev-php | grep PEAR` ; do echo "dev-php/${pkg} ~amd64" >> /etc/portage/package.keywords ; done

emerge pear
```

## phpMyAdmin

### phpMyAdmin installieren

```shell
wget -O 'phpMyAdmin-3.3.7-all-languages.tar.gz' https://files.phpmyadmin.net/phpMyAdmin//3.3.7/phpMyAdmin-3.3.7-all-languages.tar.gz

tar xzf phpMyAdmin-3.3.7-all-languages.tar.gz
mv phpMyAdmin-3.3.7-all-languages /var/www/vhosts/ssl.example.com/data/phpmyadmin
mkdir -p /var/www/vhosts/ssl.example.com/data/phpmyadmin/save
mkdir -p /var/www/vhosts/ssl.example.com/data/phpmyadmin/upload
chown -R www:www /var/www/vhosts/ssl.example.com/data/phpmyadmin
find /var/www/vhosts/ssl.example.com/data/phpmyadmin -type d -print0 | xargs -0 chmod 0750
find /var/www/vhosts/ssl.example.com/data/phpmyadmin -type f -print0 | xargs -0 chmod 0640
```

### phpMyAdmin konfigurieren

```shell
mkdir -p /var/www/vhosts/ssl.example.com/data/phpmyadmin/config
chown www:www /var/www/vhosts/ssl.example.com/data/phpmyadmin/config
```

Nun bitte `https://ssl.example.com/phpmyadmin/setup/index.php` im Browser aufrufen und phpMyAdmin konfigurieren. Danach muss die `config.inc.php` noch installiert werden:

```shell
mv /var/www/vhosts/ssl.example.com/data/phpmyadmin/config/config.inc.php /var/www/vhosts/ssl.example.com/data/phpmyadmin/config.inc.php
chmod 0640 /var/www/vhosts/ssl.example.com/data/phpmyadmin/config.inc.php
rm -r /var/www/vhosts/ssl.example.com/data/phpmyadmin/config
```

## PostfixAdmin

### PostfixAdmin installieren

```shell
# FIXME MIME::EncWords is masked by keyword
emerge Email-Valid Mail-Sender Mail-Sendmail MIME-tools log-dispatch Log-Log4perl TimeDate

wget -O 'postfixadmin-2.3.3.tar.gz' https://github.com/postfixadmin/postfixadmin/archive/postfixadmin-2.3.3.tar.gz

tar xzf postfixadmin-2.3.3.tar.gz
mv postfixadmin-2.3.3 /var/www/vhosts/ssl.example.com/data/postfixadmin
chown -R www:www /var/www/vhosts/ssl.example.com/data/postfixadmin
find /var/www/vhosts/ssl.example.com/data/postfixadmin -type d -print0 | xargs -0 chmod 0750
find /var/www/vhosts/ssl.example.com/data/postfixadmin -type f -print0 | xargs -0 chmod 0640
```

Anlegen der Datenbank und der Datenbank-User:

!!!note
Bitte jeweils das gleiche Passwort wie in der `dovecot-sql.conf` aus der Dovecot Konfiguration verwenden.
!!!

```shell
mysql -uroot -p
CREATE DATABASE postfix DEFAULT CHARACTER SET utf8;
GRANT ALL PRIVILEGES ON postfix.* TO 'postfix'@'localhost' IDENTIFIED BY '__PASSWORD__';
GRANT ALL PRIVILEGES ON postfix.* TO 'postfixadmin'@'localhost' IDENTIFIED BY '__PASSWORD__';
FLUSH PRIVILEGES;
QUIT;
```

### PostfixAdmin konfigurieren

Anlegen der `config.local.php`:

```shell
cat > /var/www/vhosts/ssl.example.com/data/postfixadmin/config.local.php << "EOF"
<?php
$CONF['configured'] = true;
$CONF['postfix_admin_url'] = 'https://ssl.example.com/postfixadmin/';
$CONF['database_type'] = 'mysqli';
$CONF['database_password'] = '__PASSWORD__'; // Password of database-user postfix
$CONF['admin_email'] = 'postmaster@example.com';
$CONF['min_password_length'] = 8;
$CONF['default_aliases'] = array (
    'abuse' => 'abuse@example.com',
    'admin' => 'admin@example.com',
    'hostmaster' => 'hostmaster@example.com',
    'postmaster' => 'postmaster@example.com',
    'webmaster' => 'webmaster@example.com'
);
$CONF['domain_path'] = 'YES';
$CONF['domain_in_mailbox'] = 'NO';
$CONF['aliases'] = '50';
$CONF['mailboxes'] = '50';
$CONF['maxquota'] = '1024';
$CONF['quota'] = 'YES';
$CONF['quota_multiplier'] = '1048576';
$CONF['vacation_domain'] = 'autoreply.example.com';
$CONF['alias_control'] = 'YES';
$CONF['alias_control_admin'] = 'YES';
$CONF['fetchmail'] = 'NO';
$CONF['user_footer_link'] = "https://www.example.com/";
$CONF['show_footer_text'] = 'YES';
$CONF['footer_text'] = 'Return to Homepage';
$CONF['footer_link'] = 'https://www.example.com';
$CONF['show_status'] = 'YES';
$CONF['show_status_key'] = 'YES';
EOF

chmod 0640 /var/www/vhosts/ssl.example.com/data/postfixadmin/config.local.php
chown www:www /var/www/vhosts/ssl.example.com/data/postfixadmin/config.local.php
```

Nun bitte `https://ssl.example.com/postfixadmin/setup.php` im Browser aufrufen, am Ende der Seite das Setup-Passwort generieren und in der `config.local.php` nachtragen:

```shell
cat >> /var/www/vhosts/ssl.example.com/data/postfixadmin/config.local.php << "EOF"
$CONF['setup_password'] = '__PASSWORD__';
EOF
```

Abschliessend mit den Anweisungen auf der Webseite fortfahren.

Die Installation der `vacation.pl` ist Optional und nicht getestet!

`vacation.pl` installieren:

```shell
groupadd -g 65001 vacation
useradd -u 65001 -g vacation -c "virtual vacation" -d /nonexistent -s /sbin/nologin vacation
mkdir -p /var/spool/vacation
cp /var/www/vhosts/ssl.example.com/data/postfixadmin/VIRTUAL_VACATION/vacation.pl /var/spool/vacation/
chmod -R 0750 /var/spool/vacation
chown -R vacation:vacation /var/spool/vacation
```

`vacation.pl` konfigurieren:

```shell
sed -e "s/^\(my \$db_type = 'Pg';\)/#\1/" \
    -e "s/^#\(my \$db_type = 'mysql';\)/\1/" \
    -e "s/^\(my \$db_host =\).*$/\1 'localhost';/" \
    -e "s/^\(my \$db_username =\).*$/\1 'postfix';/" \
    -e "s/^\(my \$db_password =\).*$/\1 '__PASSWORD__';/" \
    -i /var/spool/vacation/vacation.pl
```

## RoundCube

### RoundCube installieren

```shell
wget -O 'roundcubemail-0.4.1.tar.gz' https://github.com/roundcube/roundcubemail/archive/v0.4.1.tar.gz

tar xzf roundcubemail-0.4.1.tar.gz
mv roundcubemail-0.4.1 /var/www/vhosts/ssl.example.com/data/roundcube
chown -R www:www /var/www/vhosts/ssl.example.com/data/roundcube
find /var/www/vhosts/ssl.example.com/data/roundcube -type d -print0 | xargs -0 chmod 0750
find /var/www/vhosts/ssl.example.com/data/roundcube -type f -print0 | xargs -0 chmod 0640
```

Anlegen der Datenbank und des Datenbank-User:

```shell
mysql -uroot -p
CREATE DATABASE roundcube DEFAULT CHARACTER SET utf8;
GRANT ALL PRIVILEGES ON roundcube.* TO 'roundcube'@'localhost' IDENTIFIED BY '__PASSWORD__';
FLUSH PRIVILEGES;
QUIT;
```

### RoundCube konfigurieren

```shell
mysql -uroot -p roundcube < /var/www/vhosts/ssl.example.com/data/roundcube/SQL/mysql.initial.sql
rm /var/www/vhosts/ssl.example.com/data/roundcube/.htaccess
mv /var/www/vhosts/ssl.example.com/data/roundcube/config/db.inc.php.dist /var/www/vhosts/ssl.example.com/data/roundcube/config/db.inc.php
mv /var/www/vhosts/ssl.example.com/data/roundcube/config/main.inc.php.dist /var/www/vhosts/ssl.example.com/data/roundcube/config/main.inc.php
```

Folgende Zeile muss in der `config/db.inc.php` angepasst werden:

```shell
$rcmail_config['db_dsnw'] = 'mysqli://roundcube:__PASSWORD__@localhost/roundcube';
```

Folgende Zeilen müssen in der `config/main.inc.php` angepasst werden:

```shell
$rcmail_config['default_host'] = 'ssl://mail.example.com';
$rcmail_config['default_port'] = 993;
$rcmail_config['imap_auth_type'] = plain;
$rcmail_config['smtp_server'] = 'tls://mail.example.com';
$rcmail_config['smtp_user'] = '%u';
$rcmail_config['smtp_pass'] = '%p';
$rcmail_config['smtp_helo_host'] = 'mail.example.com';
$rcmail_config['force_https'] = true;
$rcmail_config['des_key'] = 'rcmail-!24ByteDESkey*Str';
$rcmail_config['max_recipients'] = 50;
$rcmail_config['max_group_members'] = 50;
$rcmail_config['mime_magic'] = '/usr/share/misc/magic';
$rcmail_config['prefer_html'] = false;
```
