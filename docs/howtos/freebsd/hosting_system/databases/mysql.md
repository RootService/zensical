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
title: MySQL
description: In diesem HowTo wird Schritt für Schritt die Installation des MySQL-Datenbanksystems für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# MySQL

## Inhalt

* MySQL 8.0 über `databases/mysql80-server`
* aktueller Portstand: `8.0.44` auf `2026Q1`, `8.0.45` auf `latest`
* InnoDB als primäre Engine
* Basishärtung mit `mysql_secure_installation`
* Zugangsdaten mit `mysql_config_editor`
* logische Backups mit `mysqldump`
* optionale GTID-/Binlog-Basis für spätere Replikation

---

## Einleitung

Dieses HowTo beschreibt die Installation und Konfiguration von MySQL auf FreeBSD 15+.

Dieses HowTo verwendet bewusst **`databases/mysql80-server`** für bestehende 8.0-Bestandsumgebungen. Stand heute liegt der Port im FreeBSD-Quarterly-Zweig `2026Q1` bei **8.0.44** und im `latest`-Zweig bei **8.0.45**. Gleichzeitig ist wichtig: **MySQL 8.0 erreicht im April 2026 EoL**. Für neue Installationen solltest du deshalb prüfen, ob **MySQL 8.4 LTS** die bessere Wahl ist. ([freshports.org][1])

Dieses HowTo konzentriert sich auf **InnoDB** als primäre Engine. **GTID** ist hier bewusst **nicht standardmäßig aktiv**, sondern wird weiter unten als optionale Produktionsfunktion getrennt behandelt. Das ist Absicht, weil GTID in MySQL explizit konfiguriert werden muss und **nicht** zur Standardinstallation gehört. ([dev.mysql.com][3])

---

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

---

## Vorbereitungen

### DNS Records

Für dieses HowTo sind **keine zusätzlichen DNS-Records** erforderlich.

### Gruppen / Benutzer / Passwörter

Für dieses HowTo müssen **keine zusätzlichen Systemgruppen oder Systembenutzer** manuell angelegt werden.

Der Dienst verwendet den Systembenutzer `mysql`. ([GitHub][4])

### Verzeichnisse / Dateien

Für dieses HowTo müssen vor der Installation **keine zusätzlichen Verzeichnisse oder Dateien manuell angelegt** werden.

Der Port bringt bereits die Beispieldatei `my.cnf.sample` unter `/usr/local/etc/mysql/` sowie das FreeBSD-Service-Skript `mysql-server` mit. ([freshports.org][1])

---

## Installation

### Wir installieren `databases/mysql80-server` und dessen Abhängigkeiten.

``` sh
mkdir -p /var/db/ports/comms_hidapi
cat <<'EOF' > /var/db/ports/comms_hidapi/options
--8<-- "freebsd/ports/comms_hidapi/options"
EOF

mkdir -p /var/db/ports/security_libfido2
cat <<'EOF' > /var/db/ports/security_libfido2/options
--8<-- "freebsd/ports/security_libfido2/options"
EOF

mkdir -p /var/db/ports/textproc_groff
cat <<'EOF' > /var/db/ports/textproc_groff/options
--8<-- "freebsd/ports/textproc_groff/options"
EOF

mkdir -p /var/db/ports/print_gsfonts
cat <<'EOF' > /var/db/ports/print_gsfonts/options
--8<-- "freebsd/ports/print_gsfonts/options"
EOF

mkdir -p /var/db/ports/databases_mysql80-client
cat <<'EOF' > /var/db/ports/databases_mysql80-client/options
--8<-- "freebsd/ports/databases_mysql80-client/options"
EOF

mkdir -p /var/db/ports/databases_mysql80-server
cat <<'EOF' > /var/db/ports/databases_mysql80-server/options
--8<-- "freebsd/ports/databases_mysql80-server/options"
EOF

portmaster -w -B -g -U --force-config databases/mysql80-server -n
```

### Dienst in `rc.conf` eintragen

Der Dienst wird mittels `sysrc` in der `rc.conf` eingetragen und dadurch beim Systemstart automatisch gestartet.

Das FreeBSD-rc-Skript unterstützt hier unter anderem `mysql_enable`, `mysql_dbdir` und `mysql_optfile`. Es initialisiert ein noch leeres Datenverzeichnis beim ersten Start außerdem automatisch mit `mysqld --initialize-insecure`. ([GitHub][4])

``` sh
sysrc mysql_enable=YES
```

---

## Konfiguration

### Verzeichnisse anlegen

Für Passwortdateien und Backups legen wir die benötigten Verzeichnisse jetzt an.

``` sh
install -d -m 0750 -o mysql -g mysql /var/db/backups/mysql
```

### Konfigurationsdatei

MySQL sollte dauerhaft über eine Option-Datei konfiguriert werden. Das rc-Skript verwendet standardmäßig `/usr/local/etc/mysql/my.cnf`, wenn dort eine Datei vorhanden ist; alternativ sucht es im Datenverzeichnis. Der Port liefert dafür bereits `my.cnf.sample` mit. ([GitHub][4])

``` sh
cat <<'EOF' > /usr/local/etc/mysql/my.cnf
--8<-- "freebsd/configs/usr/local/etc/mysql/my.cnf"
EOF
```

Falls deine `my.cnf` selbst eine `datadir`-Direktive enthält, muss sie zur `rc.conf`-Einstellung `mysql_dbdir="/var/db/mysql"` passen. Das rc-Skript übergibt den Datenpfad explizit an `mysqld_safe`. ([GitHub][4])

### Server zum ersten Mal starten

Wenn das Datenverzeichnis noch nicht initialisiert ist, erzeugt das FreeBSD-rc-Skript es beim ersten Start mit `--initialize-insecure`. Danach existiert der initiale `root`-Account **ohne Passwort**, bis du es selbst setzt. Genau dafür ist der erste Start hier absichtlich getrennt vom nächsten Schritt. ([GitHub][4])

``` sh
service mysql-server start
```

Erster Funktionstest:

``` sh
mysql --protocol=socket -u root --skip-password -e "SELECT VERSION();"
```

### Root-Passwort sauber setzen

Nach einer Initialisierung mit `--initialize-insecure` setzt du das Root-Passwort sauber mit `ALTER USER`. Für MySQL 8.0 ist `caching_sha2_password` der moderne Standard-Mechanismus; Legacy-Authentifizierung mit `mysql_native_password` solltest du nur noch für nachweislich alte Clients überhaupt in Betracht ziehen. In MySQL 8.4 ist `mysql_native_password` bereits **nicht mehr standardmäßig aktiviert**. ([dev.mysql.com][5])

``` sh
# Passwort für den MySQL-Superuser "root" erzeugen und
# in /var/db/passwords/mysql_superuser_root speichern
install -b -m 0600 -o mysql -g mysql /dev/null /var/db/passwords/mysql_superuser_root
openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | \
  cut -c 2-17 | tee /var/db/passwords/mysql_superuser_root | xargs -I % \
  mysql --protocol=socket --user=root --skip-password -e "ALTER USER 'root'@'localhost' IDENTIFIED BY \"%\";"

cat /var/db/passwords/mysql_superuser_root
```

### Zusätzliche Basishärtung

`mysql_secure_installation` ist im Port enthalten und hilft bei den üblichen Basishärtungen. Für dieses Setup ist sinnvoll:

* optional `validate_password` aktivieren
* anonyme Benutzer entfernen
* Remote-Login für `root` verbieten
* Test-Datenbank entfernen
* Privilegien neu laden

``` sh
mysql_secure_installation
```

Das Root-Passwort ist in diesem HowTo bereits gesetzt. In `mysql_secure_installation` überspringst du die Passwortänderung deshalb an dieser Stelle und nutzt das Tool nur noch für die restlichen Härtungsschritte. ([freshports.org][1])

### Zugangsdaten mit `mysql_config_editor` speichern

Ein Login-Path in `.mylogin.cnf` kann nur **einen** Satz aus `host`, `user`, `password`, `port` und `socket` speichern. Genau diese Felder schreibt `mysql_config_editor`. Auf Unix verwendet `localhost` ohne explizites TCP-Protokoll standardmäßig den **Unix-Socket**. Für lokale Root-Administration reicht deshalb zuerst ein einzelner Socket-Login-Path. ([dev.mysql.com][6])

``` sh
mysql_config_editor set \
  --login-path=root-local \
  --socket=/tmp/mysql.sock \
  --user=root \
  --password
```

Falls dein `my.cnf` einen anderen Socket-Pfad setzt, musst du hier denselben Pfad verwenden. ([dev.mysql.com][7])

### Konfiguration prüfen

Eine klassische `configtest`-Funktion wie bei manchen anderen Diensten gibt es hier nicht. Für die Prüfung ist deshalb sinnvoll, sich zuerst die aus Option-Dateien gelesenen Werte anzeigen zu lassen und anschließend die Erreichbarkeit des laufenden Dienstes zu testen. `my_print_defaults` ist genau für das Auslesen solcher Optionen vorgesehen. ([dev.mysql.com][8])

``` sh
my_print_defaults mysqld
mysqladmin --login-path=root-local ping
```

### Optionale Produktionsfunktion: Binärlog und GTID

**GTID** ist in dieser Anleitung absichtlich **nicht standardmäßig aktiv**. Für GTID-basierte Replikation muss die Konfiguration bewusst vorbereitet werden. Oracle dokumentiert dafür insbesondere: **Binary Logging muss aktiv sein**, `enforce_gtid_consistency` muss gesetzt werden und `gtid_mode=ON` ist **nicht** der Default. Das hier ist nur die Grundrichtung, **keine vollständige Replikationsanleitung**. ([dev.mysql.com][3])

``` ini
[mysqld]
# log_bin                     = mysql-bin
# server_id                   = 1
# binlog_expire_logs_seconds  = 604800
# enforce_gtid_consistency    = ON
# gtid_mode                   = ON
```

---

## Datenbanken

### MySQL-Benutzer `admin` anlegen

MySQL-Konten sind immer an **Benutzer + Host** gebunden. Für dieses HowTo legen wir deshalb drei lokale Administrationskonten an: für Socket, IPv4 und IPv6. Das passt sauber zu den späteren Login-Paths. ([dev.mysql.com][2])

``` sh
# Passwort für den MySQL-Benutzer "admin" erzeugen und
# in /var/db/passwords/mysql_user_admin speichern
install -b -m 0600 -o mysql -g mysql /dev/null /var/db/passwords/mysql_user_admin
openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | \
  cut -c 2-17 | tee /var/db/passwords/mysql_user_admin | xargs -I % \
  mysql --login-path=root-local -e "\
DROP USER IF EXISTS 'admin'@'localhost'; \
DROP USER IF EXISTS 'admin'@'127.0.0.1'; \
DROP USER IF EXISTS 'admin'@'::1'; \
CREATE USER 'admin'@'localhost' IDENTIFIED BY '%'; \
CREATE USER 'admin'@'127.0.0.1' IDENTIFIED BY '%'; \
CREATE USER 'admin'@'::1' IDENTIFIED BY '%'; \
GRANT ALL PRIVILEGES ON *.* TO 'admin'@'localhost' WITH GRANT OPTION; \
GRANT ALL PRIVILEGES ON *.* TO 'admin'@'127.0.0.1' WITH GRANT OPTION; \
GRANT ALL PRIVILEGES ON *.* TO 'admin'@'::1' WITH GRANT OPTION; \
FLUSH PRIVILEGES;"

cat /var/db/passwords/mysql_user_admin
```

Passende Login-Paths:

``` sh
mysql_config_editor set \
  --login-path=admin-local \
  --socket=/tmp/mysql.sock \
  --user=admin \
  --password

mysql_config_editor set \
  --login-path=admin-tcp4 \
  --host=127.0.0.1 \
  --port=3306 \
  --user=admin \
  --password

mysql_config_editor set \
  --login-path=admin-tcp6 \
  --host=::1 \
  --port=3306 \
  --user=admin \
  --password
```

### MySQL-Datenbank `test_db` für `admin` anlegen

MySQL 8.0 verwendet `utf8mb4` als Standardzeichensatzlinie. Für neue Datenbanken ist das die richtige Basis. Dieses Beispiel bleibt bewusst bei **InnoDB**. ([dev.mysql.com][9])

``` sh
cat <<'EOF' > /tmp/test_db.sql
DROP DATABASE IF EXISTS test_db;
CREATE DATABASE test_db CHARACTER SET utf8mb4;
GRANT ALL PRIVILEGES ON test_db.* TO 'admin'@'localhost';
GRANT ALL PRIVILEGES ON test_db.* TO 'admin'@'127.0.0.1';
GRANT ALL PRIVILEGES ON test_db.* TO 'admin'@'::1';
FLUSH PRIVILEGES;
USE test_db;
CREATE TABLE kunden (
    id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    name        VARCHAR(255) NOT NULL,
    email       VARCHAR(255) NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_kunden_email (email)
) ENGINE=InnoDB;
EOF

mysql --login-path=admin-local < /tmp/test_db.sql
```

Einfacher Funktionstest:

``` sh
mysql --login-path=admin-local -e "SHOW DATABASES;"
mysql --login-path=admin-local -e "USE test_db; SHOW TABLES;"
mysql --login-path=admin-tcp4 -e "SELECT CURRENT_USER(), @@hostname;"
```

### Einzelne Datenbank sichern

Für InnoDB ist `mysqldump --single-transaction` die richtige logische Backup-Basis, weil dabei ein konsistenter Snapshot für transaktionale Tabellen erzeugt wird, ohne normale Lese-/Schreibvorgänge unnötig hart zu blockieren. `mysqlpump` solltest du für neue Setups nicht mehr einplanen; es ist seit **8.0.34 deprecated**. ([dev.mysql.com][10])

``` sh
mysqldump \
  --login-path=admin-local \
  --single-transaction \
  --routines \
  --events \
  --triggers \
  test_db > /var/db/backups/mysql/test_db-$(date +%F).sql
```

Wiederherstellung:

``` sh
mysql --login-path=admin-local -e "CREATE DATABASE test_db_restore CHARACTER SET utf8mb4;"
mysql --login-path=admin-local test_db_restore < /var/db/backups/mysql/test_db-2026-03-21.sql
```

### Einzelne Tabelle sichern

``` sh
mysqldump \
  --login-path=admin-local \
  --single-transaction \
  test_db kunden > /var/db/backups/mysql/test_db-kunden-$(date +%F).sql
```

Wiederherstellung:

``` sh
mysql --login-path=admin-local test_db_restore < /var/db/backups/mysql/test_db-kunden-2026-03-21.sql
```

### Alle Datenbanken logisch sichern

``` sh
mysqldump \
  --login-path=admin-local \
  --single-transaction \
  --routines \
  --events \
  --triggers \
  --all-databases > /var/db/backups/mysql/all-databases-$(date +%F).sql
```

Wiederherstellung:

``` sh
mysql --login-path=admin-local < /var/db/backups/mysql/all-databases-2026-03-21.sql
```

---

## Zusatzsoftware

Mögliche Zusatzsoftware wird hier installiert und konfiguriert.

Für dieses HowTo ist **keine zusätzliche Software** erforderlich.

---

## Aufräumen

Überflüssige oder temporäre Verzeichnisse und Dateien entsorgen.

### Zusatzsoftware Installation

Nicht erforderlich.

### Zusatzsoftware Konfiguration

Nicht erforderlich.

Temporäre Testdatei entfernen:

``` sh
rm -f /tmp/test_db.sql
```

---

## Abschluss

Abschließend den Dienst einmal sauber neu starten und die Erreichbarkeit prüfen:

``` sh
service mysql-server restart
mysql --login-path=admin-local -e "SELECT VERSION();"
```

Für spätere Änderungen:

``` sh
service mysql-server restart
mysqladmin --login-path=root-local ping
```

Für Updates gilt: **erst Backup, dann Update**. Seit **MySQL 8.0.16** übernimmt der Server die Upgrade-Arbeiten, die früher `mysql_upgrade` erledigt hat; das Tool ist deshalb deprecated und in 8.4 bereits entfernt. Bei Major-Upgrades, insbesondere **8.0 → 8.4**, solltest du dem dokumentierten Upgrade-Pfad folgen und nicht blind nur den Port austauschen. ([freshports.org][11])

---

## Referenzen

* FreeBSD Handbook: Ports und Diensteverwaltung
* FreshPorts: `databases/mysql80-server`
* MySQL Reference Manual: Initializing the Data Directory
* MySQL Reference Manual: Securing the Initial MySQL Account
* MySQL Reference Manual: `mysql_secure_installation`
* MySQL Reference Manual: `mysql_config_editor`
* MySQL Reference Manual: Connection Transport Protocols
* MySQL Reference Manual: `mysqldump`
* MySQL Reference Manual: GTID / Replication
* MySQL Product Support EOL Announcements

[1]: https://www.freshports.org/databases/mysql80-server/?branch=2026Q1 "FreshPorts -- databases/mysql80-server: Multithreaded SQL database (server)"
[2]: https://dev.mysql.com/doc/refman/8.4/en/user-names.html "8.2.1 Account User Names and Passwords"
[3]: https://dev.mysql.com/doc/refman/8.4/en/group-replication-requirements.html "20.3.1 Group Replication Requirements"
[4]: https://raw.githubusercontent.com/freebsd/freebsd-ports/main/databases/mysql80-server/files/mysql-server.in "raw.githubusercontent.com"
[5]: https://dev.mysql.com/doc/refman/8.1/en/default-privileges.html "MySQL :: MySQL 8.4 Reference Manual :: 2.9.4 Securing the Initial MySQL Account"
[6]: https://dev.mysql.com/doc/refman/8.2/en/mysql-config-editor.html "MySQL :: MySQL 8.4 Reference Manual :: 6.6.7 mysql_config_editor — MySQL Configuration Utility"
[7]: https://dev.mysql.com/doc/refman/8.1/en/transport-protocols.html "MySQL :: MySQL 8.4 Reference Manual :: 6.2.7 Connection Transport Protocols"
[8]: https://dev.mysql.com/doc/refman/8.4/en/my-print-defaults.html "6.7.2 my_print_defaults — Display Options from Option Files"
[9]: https://dev.mysql.com/blog-archive/whats-new-in-mysql-8-0-generally-available/ "What's New in MySQL 8.0? (Generally Available)"
[10]: https://dev.mysql.com/doc/refman/8.4/en/backup-policy.html "9.3.1 Establishing a Backup Policy"
[11]: https://www.freshports.org/databases/mysql80-server "FreshPorts -- databases/mysql80-server: Multithreaded SQL database (server)"
