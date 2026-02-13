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
title: Postfix
description: In diesem HowTo wird step-by-step die Installation des Postfix Mailservers für ein Hosting System auf Basis von FreeBSD 64Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# Postfix

## Einleitung

Unser Hosting System wird um folgende Dienste erweitert.

- Postfix 3.10.6 (Dovecot-SASL, postscreen)

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

## Installation

Wir installieren `mail/postfix` und dessen Abhängigkeiten.

``` shell
mkdir -p /var/db/ports/mail_postfix
cat <<'EOF' > /var/db/ports/mail_postfix/options
--8<-- "freebsd/ports/mail_postfix/options"
EOF


portmaster -w -B -g --force-config mail/postfix@default  -n


sysrc postfix_enable=YES


install -d /usr/local/etc/mail
install -m 0644 /usr/local/share/postfix/mailer.conf.postfix /usr/local/etc/mail/mailer.conf
```

## Konfiguration

`main.cf` einrichten.

cat <<'EOF' > /usr/local/etc/postfix/main.cf
--8<-- "freebsd/configs/usr/local/etc/postfix/main.cf"
EOF

``` shell
# IPv4
ifconfig -u -f cidr `route -n get -inet default | awk '/interface/ {print $2}'` inet | \
    awk 'tolower($0) ~ /inet[\ \t]+((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/ {if(substr($2,1,3)!=127) print $2}' | \
    head -n 1 | cut -d'/' -f 1 | xargs -I % sed -e 's|__IPADDR4__|%|g' -i '' /usr/local/etc/postfix/main.cf

# IPv6
ifconfig -u -f cidr `route -n get -inet6 default | awk '/interface/ {print $2}'` inet6 | \
    awk 'tolower($0) ~ /inet6[\ \t]+(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/ {if(substr($2,1,1)!="f") print $2}' | \
    head -n 1 | cut -d'/' -f 1 | xargs -I % sed -e 's|__IPADDR6__|%|g' -i '' /usr/local/etc/postfix/main.cf
```

`master.cf` einrichten.

``` shell
cat <<'EOF' > /usr/local/etc/postfix/master.cf
--8<-- "freebsd/configs/usr/local/etc/postfix/master.cf"
EOF
```

`pgsql/*.cf` einrichten.

``` shell
mkdir -p /usr/local/etc/postfix/pgsql

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

awk '/^Password for PostgreSQL user postfix:/ {print $NF}' /root/_passwords | \
    xargs -I % sed -e 's|__PASSWORD_POSTFIX__|%|g' -i '' /usr/local/etc/postfix/pgsql/*.cf


chmod 0640 /usr/local/etc/postfix/pgsql/*.cf
chown root:postfix /usr/local/etc/postfix/pgsql/*.cf
```

Restriktionen einrichten.

``` shell
cp -a /etc/mail/aliases /usr/local/etc/postfix/aliases

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

# IPv4
ifconfig -u -f cidr `route -n get -inet default | awk '/interface/ {print $2}'` inet | \
    awk 'tolower($0) ~ /inet[\ \t]+((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/ {if(substr($2,1,3)!=127) print $2}' | \
    head -n 1 | cut -d'/' -f 1 | xargs -I % sed -e 's|__IPADDR4__|%|g' -i '' /usr/local/etc/postfix/postscreen_access.cidr

# IPv6
ifconfig -u -f cidr `route -n get -inet6 default | awk '/interface/ {print $2}'` inet6 | \
    awk 'tolower($0) ~ /inet6[\ \t]+(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/ {if(substr($2,1,1)!="f") print $2}' | \
    head -n 1 | cut -d'/' -f 1 | xargs -I % sed -e 's|__IPADDR6__|%|g' -i '' /usr/local/etc/postfix/postscreen_access.cidr


chmod 0640 /usr/local/etc/postfix/*.pcre
chown root:postfix /usr/local/etc/postfix/*.pcre


postmap /usr/local/etc/postfix/postscreen_dnsbl_reply
postmap /usr/local/etc/postfix/dnsbl_reply_map
postmap /usr/local/etc/postfix/mx_access

/usr/local/bin/newaliases
```

Abschliessende Arbeiten.

``` shell
portmaster -w -B -g --force-config dns/rubygem-dnsruby  -n

portmaster -w -B -g --force-config net/rubygem-ipaddress  -n

portmaster -w -B -g --force-config devel/rubygem-optparse  -n

portmaster -w -B -g --force-config devel/rubygem-pp  -n


cat <<'EOF' > /usr/local/etc/postfix/postscreen_whitelist.rb
--8<-- "freebsd/configs/usr/local/etc/postfix/postscreen_whitelist.rb"
EOF

chmod 0755 /usr/local/etc/postfix/postscreen_whitelist.rb

/usr/local/etc/postfix/postscreen_whitelist.rb -f
```

Wir installieren `mail/libmilter` und dessen Abhängigkeiten.

``` shell
mkdir -p /var/db/ports/mail_libmilter
cat <<'EOF' > /var/db/ports/mail_libmilter/options
--8<-- "freebsd/ports/mail_libmilter/options"
EOF


portmaster -w -B -g --force-config mail/libmilter  -n
```

Wir installieren `mail/py-spf-engine` und dessen Abhängigkeiten.

``` shell
mkdir -p /var/db/ports/devel_py-anyio
cat <<'EOF' > /var/db/ports/devel_py-anyio/options
--8<-- "freebsd/ports/devel_py-anyio/options"
EOF

mkdir -p /var/db/ports/devel_py-pyasn1-modules
cat <<'EOF' > /var/db/ports/devel_py-pyasn1-modules/options
--8<-- "freebsd/ports/devel_py-pyasn1-modules/options"
EOF

mkdir -p /var/db/ports/dns_py-dnspython
cat <<'EOF' > /var/db/ports/dns_py-dnspython/options
--8<-- "freebsd/ports/dns_py-dnspython/options"
EOF

mkdir -p /var/db/ports/mail_py-pymilter
cat <<'EOF' > /var/db/ports/mail_py-pymilter/options
--8<-- "freebsd/ports/mail_py-pymilter/options"
EOF

mkdir -p /var/db/ports/www_py-httpcore
cat <<'EOF' > /var/db/ports/www_py-httpcore/options
--8<-- "freebsd/ports/www_py-httpcore/options"
EOF

mkdir -p /var/db/ports/www_py-httpx
cat <<'EOF' > /var/db/ports/www_py-httpx/options
--8<-- "freebsd/ports/www_py-httpx/options"
EOF

mkdir -p /var/db/ports/www_py-requests
cat <<'EOF' > /var/db/ports/www_py-requests/options
--8<-- "freebsd/ports/www_py-requests/options"
EOF

mkdir -p /var/db/ports/mail_py-spf-engine
cat <<'EOF' > /var/db/ports/mail_py-spf-engine/options
--8<-- "freebsd/ports/mail_py-spf-engine/options"
EOF


portmaster -w -B -g --force-config mail/py-spf-engine  -n


sysrc pyspf_milter_enable="YES"


cat <<'EOF' > /usr/local/etc/pyspf-milter/pyspf-milter.conf
--8<-- "freebsd/configs/usr/local/etc/pyspf-milter/pyspf-milter.conf"
EOF

cat <<'EOF' > /usr/local/etc/python-policyd-spf/policyd-spf.conf
--8<-- "freebsd/configs/usr/local/etc/python-policyd-spf/policyd-spf.conf"
EOF


service pyspf-milter start
```

## Abschluss

Postfix kann nun gestartet werden.

``` shell
service postfix start
```
