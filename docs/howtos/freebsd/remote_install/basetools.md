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
lastmod: '2026-02-27'
title: BaseTools
description: In diesem HowTo wird Schritt für Schritt die Installation einiger BaseTools für ein FreeBSD 64 Bit BaseSystem auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# BaseTools

> **Stand:** 2026-02-27  
> **Terminologie:** Einheitlich werden die Begriffe **HowTo**, **HowTos**, **BaseSystem**, **BasePorts** und **BaseTools** verwendet.


## Einleitung

In diesem HowTo beschreibe ich Schritt für Schritt die Installation einiger Tools (Ports / Packages / Pakete) welche auf keinem [FreeBSD](https://www.freebsd.org/){: target="\_blank" rel="noopener"} 64 Bit BaseSystem auf einem dedizierten Server fehlen sollten.

Unsere BaseTools werden am Ende folgende Dienste umfassen.

- sudo 1.9.17p2
- bind-tools 9.20.18
- QEMU GuestAgent 10.2.0
- cloud-init 25.2
- smartmontools 7.5
- wget 1.25.0
- GIT 2.52.0
- GnuPG 2.4.9
- SQLite 3.50.4
- Subversion 1.14.5
- Nano 8.7

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Remote Installation](requirements.md)

## Einloggen und zu root werden

```powershell
putty -ssh -P 2222 -i "${Env:USERPROFILE}\VirtualBox VMs\FreeBSD\ssh\id_ed25519.ppk" admin@127.0.0.1
```

```shell
su - root
```

## Software installieren

Wir installieren `security/sudo` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/security_sudo
cat <<'EOF' > /var/db/ports/security_sudo/options
--8<-- "freebsd/ports/security_sudo/options"
EOF


portmaster -w -B -g --force-config security/sudo@default  -n
```

Wir konfigurieren `sudo` und erlauben Mitgliedern der Gruppe `wheel` beliebige Kommandos als beliebiger User ohne Passwortabfrage auszuführen.

```shell
cat <<'EOF' > /usr/local/etc/sudoers.d/00_defaults
## Uncomment to allow any user to run sudo if they know the password
## of the user they are running the command as (root by default).
Defaults targetpw  # Ask for the password of the target user
ALL ALL=(ALL:ALL) ALL  # WARNING: only use this together with 'Defaults targetpw'

## Uncomment to show on password prompt which users' password is being expected
Defaults passprompt="%p's password:"
EOF

cat <<'EOF' > /usr/local/etc/sudoers.d/01_wheel
%wheel ALL = (ALL:ALL) NOPASSWD: ALL
EOF

cat <<'EOF' > /usr/local/etc/sudoers.d/20_joeuser
joeuser ALL = (ALL:ALL) NOPASSWD: ALL
EOF

chmod 0440 /usr/local/etc/sudoers.d/*
```

Wir installieren `dns/bind-tools` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/devel_fstrm
cat <<'EOF' > /var/db/ports/devel_fstrm/options
--8<-- "freebsd/ports/devel_fstrm/options"
EOF

mkdir -p /var/db/ports/devel_libevent
cat <<'EOF' > /var/db/ports/devel_libevent/options
--8<-- "freebsd/ports/devel_libevent/options"
EOF

mkdir -p /var/db/ports/devel_protobuf-c
cat <<'EOF' > /var/db/ports/devel_protobuf-c/options
--8<-- "freebsd/ports/devel_protobuf-c/options"
EOF

mkdir -p /var/db/ports/dns_bind-tools
cat <<'EOF' > /var/db/ports/dns_bind-tools/options
--8<-- "freebsd/ports/dns_bind-tools/options"
EOF


portmaster -w -B -g --force-config dns/bind-tools  -n
```

Wir installieren `emulators/qemu@guestagent` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/devel_glib20
cat <<'EOF' > /var/db/ports/devel_glib20/options
--8<-- "freebsd/ports/devel_glib20/options"
EOF

mkdir -p /var/db/ports/textproc_py-docutils
cat <<'EOF' > /var/db/ports/textproc_py-docutils/options
--8<-- "freebsd/ports/textproc_py-docutils/options"
EOF

mkdir -p /var/db/ports/emulators_qemu
cat <<'EOF' > /var/db/ports/emulators_qemu/options
--8<-- "freebsd/ports/emulators_qemu/options"
EOF


portmaster -w -B -g --force-config emulators/qemu@guestagent  -n
```

Wir installieren `net/cloud-init` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/comms_py-pyserial
cat <<'EOF' > /var/db/ports/comms_py-pyserial/options
--8<-- "freebsd/ports/comms_py-pyserial/options"
EOF

mkdir -p /var/db/ports/devel_py-Jinja2
cat <<'EOF' > /var/db/ports/devel_py-Jinja2/options
--8<-- "freebsd/ports/devel_py-Jinja2/options"
EOF

mkdir -p /var/db/ports/devel_py-babel
cat <<'EOF' > /var/db/ports/devel_py-babel/options
--8<-- "freebsd/ports/devel_py-babel/options"
EOF

mkdir -p /var/db/ports/devel_py-pyyaml
cat <<'EOF' > /var/db/ports/devel_py-pyyaml/options
--8<-- "freebsd/ports/devel_py-pyyaml/options"
EOF

mkdir -p /var/db/ports/security_py-oauthlib
cat <<'EOF' > /var/db/ports/security_py-oauthlib/options
--8<-- "freebsd/ports/security_py-oauthlib/options"
EOF

mkdir -p /var/db/ports/security_py-cryptography
cat <<'EOF' > /var/db/ports/security_py-cryptography/options
--8<-- "freebsd/ports/security_py-cryptography/options"
EOF

mkdir -p /var/db/ports/www_py-pyjwt
cat <<'EOF' > /var/db/ports/www_py-pyjwt/options
--8<-- "freebsd/ports/www_py-pyjwt/options"
EOF

mkdir -p /var/db/ports/www_py-requests
cat <<'EOF' > /var/db/ports/www_py-requests/options
--8<-- "freebsd/ports/www_py-requests/options"
EOF

mkdir -p /var/db/ports/net_py-urllib3
cat <<'EOF' > /var/db/ports/net_py-urllib3/options
--8<-- "freebsd/ports/net_py-urllib3/options"
EOF


portmaster -w -B -g --force-config net/cloud-init  -n
```

Wir installieren `sysutils/smartmontools` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/sysutils_smartmontools
cat <<'EOF' > /var/db/ports/sysutils_smartmontools/options
--8<-- "freebsd/ports/sysutils_smartmontools/options"
EOF


portmaster -w -B -g --force-config sysutils/smartmontools  -n
```

Wir konfigurieren `smartmontools`.

```shell
sed 's/^DEVICESCAN/#DEVICESCAN/' /usr/local/etc/smartd.conf.sample > /usr/local/etc/smartd.conf
echo '/dev/nvme0 -d nvme -a -o on -S on -s (S/../.././02|L/../../6/03)' >> /usr/local/etc/smartd.conf
echo '/dev/nvme1 -d nvme -a -o on -S on -s (S/../.././02|L/../../6/03)' >> /usr/local/etc/smartd.conf


sysrc smartd_enable=YES
```

Die `/etc/periodic.conf` wird um folgenden Inhalt erweitert.

```shell
cat <<'EOF' >> /etc/periodic.conf
daily_status_smart_enable="YES"
daily_status_smart_devices="/dev/nvme0 /dev/nvme1"
EOF
```

Wir installieren `security/expiretable` und dessen Abhängigkeiten.

```shell
portmaster -w -B -g --force-config security/expiretable  -n
```

Wir installieren `ftp/wget` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/ftp_wget
cat <<'EOF' > /var/db/ports/ftp_wget/options
--8<-- "freebsd/ports/ftp_wget/options"
EOF


portmaster -w -B -g --force-config ftp/wget  -n
```

Wir installieren `devel/git@default` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/security_p5-Authen-SASL
cat <<'EOF' > /var/db/ports/security_p5-Authen-SASL/options
--8<-- "freebsd/ports/security_p5-Authen-SASL/options"
EOF

mkdir -p /var/db/ports/security_p5-IO-Socket-SSL
cat <<'EOF' > /var/db/ports/security_p5-IO-Socket-SSL/options
--8<-- "freebsd/ports/security_p5-IO-Socket-SSL/options"
EOF

mkdir -p /var/db/ports/security_p5-Net-SSLeay
cat <<'EOF' > /var/db/ports/security_p5-Net-SSLeay/options
--8<-- "freebsd/ports/security_p5-Net-SSLeay/options"
EOF

mkdir -p /var/db/ports/textproc_xmlto
cat <<'EOF' > /var/db/ports/textproc_xmlto/options
--8<-- "freebsd/ports/textproc_xmlto/options"
EOF

mkdir -p /var/db/ports/misc_getopt
cat <<'EOF' > /var/db/ports/misc_getopt/options
--8<-- "freebsd/ports/misc_getopt/options"
EOF

mkdir -p /var/db/ports/textproc_xmlcatmgr
cat <<'EOF' > /var/db/ports/textproc_xmlcatmgr/options
--8<-- "freebsd/ports/textproc_xmlcatmgr/options"
EOF

mkdir -p /var/db/ports/textproc_docbook-xsl
cat <<'EOF' > /var/db/ports/textproc_docbook-xsl/options
--8<-- "freebsd/ports/textproc_docbook-xsl/options"
EOF

mkdir -p /var/db/ports/textproc_libxml2
cat <<'EOF' > /var/db/ports/textproc_libxml2/options
--8<-- "freebsd/ports/textproc_libxml2/options"
EOF

mkdir -p /var/db/ports/textproc_libxslt
cat <<'EOF' > /var/db/ports/textproc_libxslt/options
--8<-- "freebsd/ports/textproc_libxslt/options"
EOF

mkdir -p /var/db/ports/security_libgcrypt
cat <<'EOF' > /var/db/ports/security_libgcrypt/options
--8<-- "freebsd/ports/security_libgcrypt/options"
EOF

mkdir -p /var/db/ports/security_libgpg-error
cat <<'EOF' > /var/db/ports/security_libgpg-error/options
--8<-- "freebsd/ports/security_libgpg-error/options"
EOF

mkdir -p /var/db/ports/www_w3m
cat <<'EOF' > /var/db/ports/www_w3m/options
--8<-- "freebsd/ports/www_w3m/options"
EOF

mkdir -p /var/db/ports/devel_boehm-gc
cat <<'EOF' > /var/db/ports/devel_boehm-gc/options
--8<-- "freebsd/ports/devel_boehm-gc/options"
EOF

mkdir -p /var/db/ports/devel_libatomic_ops
cat <<'EOF' > /var/db/ports/devel_libatomic_ops/options
--8<-- "freebsd/ports/devel_libatomic_ops/options"
EOF

mkdir -p /var/db/ports/devel_git
cat <<'EOF' > /var/db/ports/devel_git/options
--8<-- "freebsd/ports/devel_git/options"
EOF


portmaster -w -B -g --force-config devel/git@default  -n
```

Wir installieren `security/gnupg` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/security_pinentry
cat <<'EOF' > /var/db/ports/security_pinentry/options
--8<-- "freebsd/ports/security_pinentry/options"
EOF

mkdir -p /var/db/ports/security_pinentry-tty
cat <<'EOF' > /var/db/ports/security_pinentry-tty/options
--8<-- "freebsd/ports/security_pinentry-tty/options"
EOF

mkdir -p /var/db/ports/security_gnupg
cat <<'EOF' > /var/db/ports/security_gnupg/options
--8<-- "freebsd/ports/security_gnupg/options"
EOF


portmaster -w -B -g --force-config security/gnupg  -n
```

Wir installieren `databases/sqlite3` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/databases_sqlite3
cat <<'EOF' > /var/db/ports/databases_sqlite3/options
--8<-- "freebsd/ports/databases_sqlite3/options"
EOF


portmaster -w -B -g --force-config databases/sqlite3@default  -n
```

Wir installieren `devel/subversion` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/databases_db5
cat <<'EOF' > /var/db/ports/databases_db5/options
--8<-- "freebsd/ports/databases_db5/options"
EOF

mkdir -p /var/db/ports/devel_apr1
cat <<'EOF' > /var/db/ports/devel_apr1/options
--8<-- "freebsd/ports/devel_apr1/options"
EOF

mkdir -p /var/db/ports/textproc_utf8proc
cat <<'EOF' > /var/db/ports/textproc_utf8proc/options
--8<-- "freebsd/ports/textproc_utf8proc/options"
EOF

mkdir -p /var/db/ports/devel_subversion
cat <<'EOF' > /var/db/ports/devel_subversion/options
--8<-- "freebsd/ports/devel_subversion/options"
EOF


portmaster -w -B -g --force-config devel/subversion  -n
```

Wir installieren `editors/nano` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/editors_nano
cat <<'EOF' > /var/db/ports/editors_nano/options
--8<-- "freebsd/ports/editors_nano/options"
EOF


portmaster -w -B -g --force-config editors/nano  -n
```

Wenn wir ein Programm nicht kennen, dann finden wir zu jedem Port eine Datei `pkg-descr`, die eine kurze Beschreibung sowie (meistens) einen Link zur Projekt-Homepage der Software enthält. Für `smartmontools` zum Beispiel würden wir die Beschreibung unter `/usr/ports/sysutils/smartmontools/pkg-descr` finden.

## Software updaten

!!! warning
    Da wir die Pakete/Ports nicht als vorkompilierte Binary-Pakete installieren sondern selbst kompilieren, müssen wir natürlich auch die Updates der Ports selbst kompilieren. Um uns das dazu notwendige Auflösen der Abhängigkeiten und etwas Tipparbeit zu ersparen, überlassen wir dies künftig einfach einem kleinen Shell-Script. Dieses Script können wir einfach mittels `update-ports` ausführen und es erledigt dann folgende Arbeiten für uns:

- Aktualisieren des Portstree mittels `git`
- Anzeigen neuer Einträge in `/usr/ports/UPDATING`
- Ermitteln der zu aktualisierenden Ports und deren Abhängigkeiten
- Aktualisieren der Ports und Abhängigkeiten mittels portmaster
- Aufräumen des Portstree und der Distfiles mittels portmaster

```shell
cat <<'EOF' > /usr/local/sbin/update-ports
--8<-- "freebsd/configs/usr/local/sbin/update-ports"
EOF

chmod 0755 /usr/local/sbin/update-ports
```

## Wie geht es weiter?

Viel Spass mit den neuen FreeBSD BaseTools.
