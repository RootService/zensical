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
title: PostfixAdmin
description: In diesem HowTo wird Schritt für Schritt die Installation von PostfixAdmin für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# PostfixAdmin

## Inhalt

* PostfixAdmin 4.0.x
* PostgreSQL
* Vacation
* webbasierte Verwaltung für Domains, Aliases und Mailboxen

---

## Einleitung

Dieses HowTo beschreibt die Installation und Konfiguration von PostfixAdmin auf FreeBSD 15+.

PostfixAdmin ist ein webbasiertes Verwaltungswerkzeug für Postfix-Setups mit virtuellen Domains und Benutzern. Es unterstützt unter anderem **PostgreSQL** als Datenbank-Backend sowie **Vacation / Autoresponder**. Für FreeBSD ist aber wichtig: Der Port `mail/postfixadmin` hängt aktuell noch auf einem älteren 3.4-dev-Stand, während Upstream die stabile 4.0-Linie separat pflegt. Dieses HowTo folgt deshalb deinem Ausgangstext und kombiniert den Port mit einem Upstream-Checkout des 4.0-Zweigs. ([GitHub][2])

---

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

Zusätzlich wird vorausgesetzt:

* PostgreSQL ist bereits installiert und erreichbar.
* PHP-FPM und ein Webserver wie Apache oder Nginx sind bereits vorhanden.
* Der Webserver kann PostfixAdmin unter einer URL wie `/postfixadmin` ausliefern.
* Die veröffentlichte URL `/postfixadmin` muss auf das Verzeichnis `public/` der Anwendung zeigen.
* Für dieses Setup wird PostgreSQL als Datenbank-Backend verwendet.

Die offiziellen Installationshinweise nennen genau diese Basis: Webserver, PHP, Datenbank und ein Mapping von `/postfixadmin` auf das Verzeichnis `public/`. ([GitHub][3])

---

## Vorbereitungen

### DNS Records

Für dieses HowTo müssen zuvor folgende DNS-Records angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` dns-zone
mail.example.com.       IN  A       __IPADDR4__
mail.example.com.       IN  AAAA    __IPADDR6__
```

### Gruppen / Benutzer / Passwörter

Für dieses HowTo müssen **keine zusätzlichen** Systemgruppen oder Systembenutzer angelegt werden.

Für dieses HowTo müssen zuvor folgende Passwörter angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` sh
```

### Verzeichnisse / Dateien

Für dieses HowTo müssen zuvor folgende Verzeichnisse angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` sh
mkdir -p /usr/local/www/apps
```

---

## Installation

### Wir installieren `mail/postfixadmin@php84` und dessen Abhängigkeiten.

Der FreeBSD-Port ist aktuell PHP-geflavored. Für dein PHP-8.4-Setup ist deshalb `mail/postfixadmin@php84` die saubere Portangabe. In den Portoptionen ist **PGSQL** die relevante Datenbankoption. ([FreshPorts][4])

``` sh
mkdir -p /var/db/ports/mail_postfixadmin
cat <<'EOF' > /var/db/ports/mail_postfixadmin/options
--8<-- "freebsd/ports/mail_postfixadmin/options"
EOF

portmaster -w -B -g -U --force-config mail/postfixadmin@php84 -n
```

### Dienst in `rc.conf` eintragen

Nicht erforderlich.

PostfixAdmin ist **kein eigener Systemdienst** mit rc.d-Skript, sondern eine PHP-Webanwendung hinter Apache oder Nginx. ([GitHub][2])

### Upstream-Quellstand `postfixadmin_4.0` ausrollen

Upstream empfiehlt für stabile Git-Deployments den Branch `postfixadmin_4.0`. Seit PostfixAdmin 4.0 muss danach zusätzlich `install.sh` ausgeführt werden, um Composer und die benötigten Drittbibliotheken lokal zu installieren. ([GitHub][2])

``` sh
git clone -o postfixadmin -b postfixadmin_4.0 --depth 1 https://github.com/postfixadmin/postfixadmin.git /usr/local/www/apps/postfixadmin
git -C /usr/local/www/apps/postfixadmin pull --rebase

cd /usr/local/www/apps/postfixadmin

sed -e 's|\(/bin/bash.*\)$|/usr/local\1|' \
    -e 's|/usr/bin/\(.*\)$|/usr/local/bin/\1|g' \
    -i '' /usr/local/www/apps/postfixadmin/install.sh

/usr/local/bin/bash /usr/local/www/apps/postfixadmin/install.sh
```

---

## Konfiguration

### Webroot und Schreibrechte

Laut offizieller Installationsanleitung soll der Webserver **nicht** auf das Projektwurzelverzeichnis zeigen, sondern auf `public/`. Schreibzugriff braucht PostfixAdmin im Normalfall nur auf `templates_c`; die übrigen Dateien können lesbar bleiben. ([GitHub][3])

``` sh
chmod 750 /usr/local/www/apps/postfixadmin/templates_c
chown www:www /usr/local/www/apps/postfixadmin/templates_c

chown -R www:www /usr/local/www/apps/postfixadmin/public
```

### `config.local.php` einrichten

Die offizielle Konfiguration sagt klar: **`config.inc.php` nicht direkt ändern**. Eigene Einstellungen gehören in **`config.local.php`** im PostfixAdmin-Webroot. ([GitHub][5])

``` sh
cat <<'EOF' > /usr/local/www/apps/postfixadmin/config.local.php
--8<-- "freebsd/configs/usr/local/www/apps/postfixadmin/config.local.php"
EOF
chown www:www /usr/local/www/apps/postfixadmin/config.local.php
chmod 640 /usr/local/www/apps/postfixadmin/config.local.php
```

### Setup-Hash erzeugen und eintragen

`setup.php` wird laut Upstream für Installation und Setup verwendet. Dafür braucht PostfixAdmin einen gültigen `setup_password`-Hash in `config.local.php`. ([GitHub][6])

``` sh
# Passwort für PostfixAdmin "setup_hash" erzeugen und
# in /var/db/passwords/postfixadmin_setup_hash speichern
install -b -m 0600 /dev/null /var/db/passwords/postfixadmin_setup_hash
openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | \
  cut -c 2-17 | tee /var/db/passwords/postfixadmin_setup_hash | xargs -I % \
  php -r "echo password_hash('%', PASSWORD_DEFAULT);" | xargs -I % \
  sed -e "s|__SETUP_HASH__|%|g" -i '' /usr/local/www/apps/postfixadmin/config.local.php
```

### PostgreSQL-Passwort in `config.local.php` eintragen

``` sh
cat /var/db/passwords/postgresql_user_postfix | xargs -I % \
  sed -e "s|__PASSWORD_POSTFIX__|%|g" -i '' /usr/local/www/apps/postfixadmin/config.local.php
```

### Konfiguration prüfen

Vor dem ersten Browser-Setup sollte zumindest geprüft werden, ob die lokale Konfigurationsdatei existiert und die Anwendung vollständig ausgerollt wurde.

``` sh
test -f /usr/local/www/apps/postfixadmin/config.local.php && echo OK
test -d /usr/local/www/apps/postfixadmin/public && echo OK
test -d /usr/local/www/apps/postfixadmin/templates_c && echo OK
```

---

## Datenbanken

### PostgreSQL-Benutzer `postfix` anlegen

Für PostgreSQL reicht hier ein normaler Login-Role-User. Zusätzliche Rechte wie `CREATEROLE` oder `CREATEDB` sind für PostfixAdmin selbst nicht nötig. Die offizielle Installationsanleitung verlangt nur Benutzer plus Datenbank. ([GitHub][3])

``` sh
# Passwort für PostgreSQL-Benutzer "postfix" erzeugen und
# in /var/db/passwords/postgresql_user_postfix speichern
install -b -m 0600 -o postgres -g postgres /dev/null /var/db/passwords/postgresql_user_postfix
openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | \
  cut -c 2-17 | tee /var/db/passwords/postgresql_user_postfix

# PostgreSQL-Benutzer "postfix" mit Passwort anlegen
su -l postgres -c "psql <<'EOF'
\set content `cat /var/db/passwords/postgresql_user_postfix`
DROP ROLE IF EXISTS \"postfix\";
CREATE ROLE \"postfix\";
ALTER ROLE \"postfix\" WITH NOSUPERUSER INHERIT CREATEROLE CREATEDB LOGIN NOREPLICATION NOBYPASSRLS PASSWORD :'content';
\unset content
EOF"
```

### PostgreSQL-Datenbank `postfixadmin` für `postfix` anlegen

Die offizielle Anleitung zeigt für PostgreSQL genau das Muster „Benutzer anlegen, Datenbank anlegen, Owner setzen“. ([GitHub][3])

``` sh
su -l postgres -c "psql <<'EOF'
DROP DATABASE IF EXISTS \"postfixadmin\";
CREATE DATABASE \"postfixadmin\";
ALTER DATABASE \"postfixadmin\" OWNER TO \"postfix\";
EOF"
```

### PostgreSQL-Verbindung als `postfix` testen

``` sh
psql -h 127.0.0.1 -U postfix -d postfixadmin -c 'SELECT current_user, current_database();'
```

### Datenbankschema initialisieren

Im aktuellen Upstream-Setup ist das manuelle Einspielen eines riesigen statischen SQL-Dumps nicht mehr der saubere Primärweg. `setup.php` ist ausdrücklich für **Installation/Setup** da, und der Port verweist für Schema-Updates ebenfalls auf den Web-Setup-Weg. Deshalb wird das Schema in diesem HowTo **nicht** manuell aus einem fest eingebrannten SQL-Dump importiert, sondern im Abschluss über das Web-Setup initialisiert. ([GitHub][6])

---

## Zusatzsoftware

Mögliche Zusatzsoftware wird hier installiert und konfiguriert.

Für dieses HowTo ist **keine zusätzliche Software** erforderlich.

Vacation ist eine PostfixAdmin-Funktion und kein separates Zusatzpaket. ([GitHub][2])

---

## Aufräumen

Überflüssige oder temporäre Verzeichnisse und Dateien entsorgen.

### Zusatzsoftware Installation

Nicht erforderlich.

### Zusatzsoftware Konfiguration

Nicht erforderlich.

---

## Abschluss

Das PostfixAdmin-Setup muss nun im Browser gestartet und abgeschlossen werden.

Voraussetzung dafür ist, dass dein Webserver die URL `/postfixadmin` auf `/usr/local/www/apps/postfixadmin/public` abbildet. Genau diesen Aufbau beschreibt die offizielle Installationsanleitung. `setup.php` ist dabei der vorgesehene Einstieg für Installation und Setup. ([GitHub][3])

``` sh
https://mail.example.com/postfixadmin/setup.php
```

Für spätere Schema-Updates:

``` sh
https://mail.example.com/postfixadmin/upgrade.php
```

---

## Referenzen

* FreshPorts: `mail/postfixadmin` — Beschreibung, Flavors, Optionen, pkg-message. ([FreshPorts][7])
* FreeBSD Ports Tree: `mail/postfixadmin/Makefile` — aktueller Portstand `3.4.dev.20211018`. ([FreeBSD Git][1])
* PostfixAdmin GitHub-README — Stable-Release `4.0.1`, Branch `postfixadmin_4.0`, Hosting-Anforderungen. ([GitHub][2])
* PostfixAdmin `INSTALL.md` — `install.sh`, Webserver-Mapping auf `public/`, Datenbankanlage, `config.local.php`, `templates_c`. ([GitHub][3])
* PostfixAdmin `config.inc.php` — lokale Overrides gehören in `config.local.php`. ([GitHub][5])
* PostfixAdmin `public/setup.php` — Setup-Einstieg für Installation und Initialisierung. ([GitHub][6])

[1]: https://cgit.freebsd.org/ports/plain/mail/postfixadmin/Makefile "cgit.freebsd.org"
[2]: https://github.com/postfixadmin/postfixadmin "GitHub - postfixadmin/postfixadmin: PostfixAdmin - web based virtual user administration interface for Postfix mail servers · GitHub"
[3]: https://github.com/postfixadmin/postfixadmin/raw/refs/heads/master/INSTALL.md "raw.githubusercontent.com"
[4]: https://www.freshports.org/mail/postfixadmin/ "FreshPorts -- mail/postfixadmin: PHP web-based management tool for Postfix virtual domains and users"
[5]: https://github.com/postfixadmin/postfixadmin/blob/master/config.inc.php "postfixadmin/config.inc.php at master · postfixadmin/postfixadmin · GitHub"
[6]: https://github.com/postfixadmin/postfixadmin/blob/master/public/setup.php "postfixadmin/public/setup.php at master · postfixadmin/postfixadmin · GitHub"
[7]: https://www.freshports.org/mail/postfixadmin/ "FreshPorts -- mail/postfixadmin: PHP web-based management tool for Postfix virtual domains and users"
