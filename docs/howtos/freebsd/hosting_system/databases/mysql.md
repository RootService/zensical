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
title: MySQL
description: In diesem HowTo wird step-by-step die Installation des MySQL Datenbanksystem für ein Hosting System auf Basis von FreeBSD 64Bit auf einem dedizierten Server beschrieben.
keywords:
  - MySQL
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

- MySQL 8.0.42 (InnoDB, GTID)

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../intro.md)

## Installation

MySQL unterstützt mehrere Engines, dieses HowTo beschränkt sich allerdings auf die am Häufigsten verwendete: InnoDB

Wir installieren `databases/mysql80-server` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/comms_hidapi
cat <<'EOF' > /var/db/ports/comms_hidapi/options
--8<-- "ports/comms_hidapi/options"
EOF

mkdir -p /var/db/ports/net_openldap26-client
cat <<'EOF' > /var/db/ports/net_openldap26-client/options
--8<-- "ports/net_openldap26-client/options"
EOF

mkdir -p /var/db/ports/security_cyrus-sasl2
cat <<'EOF' > /var/db/ports/security_cyrus-sasl2/options
--8<-- "ports/security_cyrus-sasl2/options"
EOF

mkdir -p /var/db/ports/textproc_groff
cat <<'EOF' > /var/db/ports/textproc_groff/options
--8<-- "ports/textproc_groff/options"
EOF

mkdir -p /var/db/ports/print_gsfonts
cat <<'EOF' > /var/db/ports/print_gsfonts/options
--8<-- "ports/print_gsfonts/options"
EOF

mkdir -p /var/db/ports/databases_mysql80-client
cat <<'EOF' > /var/db/ports/databases_mysql80-client/options
--8<-- "ports/databases_mysql80-client/options"
EOF

mkdir -p /var/db/ports/databases_mysql80-server
cat <<'EOF' > /var/db/ports/databases_mysql80-server/options
--8<-- "ports/databases_mysql80-server/options"
EOF


portmaster -w -B -g --force-config databases/mysql80-server  -n


cp -a /var/db/mysql* /data/db/

sysrc mysql_enable=YES
sysrc mysql_dbdir="/data/db/mysql"
```

## Konfiguration

<!-- markdownlint-disable MD046 -->

???+ note

    Die Konfiguration orientiert sich an diesem [RootForum Community Forenbeitrag](https://www.rootforum.org/forum/viewtopic.php?t=36343){: target="_blank" rel="noopener"}.

<!-- markdownlint-enable MD046 -->

```shell
cat <<'EOF' > /usr/local/etc/mysql/my.cnf
--8<-- "configs/usr/local/etc/mysql/my.cnf"
EOF
```

## Sicherheit

MySQL wird nun zum ersten Mal gestartet, was durch das Erzeugen der InnoDB-Files einige Minuten dauern kann.

```shell
service mysql-server start
```

Abschliessend wird das MySQL root-Passwort neu gesetzt und mittels `mysql_config_editor` verschlüsselt in
`/root/.mylogin.cnf` gespeichert.

```shell
# Password erzeugen und in /root/_passwords speichern
chmod 0600 /root/_passwords
newpw="`openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | cut -c 2-17`"
echo "Password for MySQL user root: $newpw" >> /root/_passwords
chmod 0400 /root/_passwords
echo "Password: $newpw"
unset newpw


# First set new MySQL-root password
mysqladmin -h localhost -uroot password --default-auth=caching_sha2_password

# Setup /root/.mylogin.cnf
mysql_config_editor set --login-path=client --host=::1 --host=127.0.0.1 --host=127.0.0.1 --socket=/tmp/mysql.sock --user=root --password
```

Wir erlauben dem MySQL-root User das Einloggen von `::1`, `127.0.0.1` und `localhost` mit dem zuvor festgelegtem
Passwort.

```shell
mysql -uroot

ALTER USER 'root'@'localhost' IDENTIFIED WITH caching_sha2_password BY '__PASSWORD__' PASSWORD EXPIRE NEVER;
CREATE USER 'root'@'127.0.0.1' IDENTIFIED WITH caching_sha2_password BY '__PASSWORD__' PASSWORD EXPIRE NEVER;
CREATE USER 'root'@'::1' IDENTIFIED WITH caching_sha2_password BY '__PASSWORD__' PASSWORD EXPIRE NEVER;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'127.0.0.1' WITH GRANT OPTION;
GRANT ALL PRIVILEGES ON *.* TO 'root'@'::1' WITH GRANT OPTION;

FLUSH PRIVILEGES;
QUIT;
```

## Abschluss

MySQL sollte abschliessend einmal neu gestartet werden.

```shell
service mysql-server restart
```
