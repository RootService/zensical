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
title: BasePorts
description: In diesem HowTo wird step-by-step die Installation einiger BasePorts für ein FreeBSD 64 Bit BaseSystem auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# BasePorts

> **Stand:** 2026-02-27  
> **Terminologie:** Einheitlich werden die Begriffe **HowTo**, **HowTos**, **BaseSystem**, **BasePorts** und **BaseTools** verwendet.


## Einleitung

In diesem HowTo beschreibe ich step-by-step die Installation einiger Ports (Packages / Pakete) welche auf keinem [FreeBSD](https://www.freebsd.org/){: target="\_blank" rel="noopener"} 64 Bit BaseSystem auf einem dedizierten Server fehlen sollten.

Unsere BasePorts werden am Ende folgende Dienste umfassen.

- Portmaster 3.30
- Perl 5.42.0
- OpenSSL 3.5.5
- Lua 5.4.8
- Tcl 8.6.17
- Python 3.11.14
- Bash 5.3.9
- cURL 8.17.0
- Rust 1.93.0
- Ruby 3.3.10
- Go 1.24.12

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Remote Installation](requirements.md)

## Einloggen und zu `root` werden

```powershell
putty -ssh -P 2222 -i "${Env:USERPROFILE}\VirtualBox VMs\FreeBSD\ssh\id_ed25519.ppk" admin@127.0.0.1
```

```shell
su - root
```

## Software installieren

!!! warning
    An diesem Punkt müssen wir uns entscheiden, ob wir die Pakete/Ports in Zukunft bequem als vorkompiliertes Binary-Paket per `pkg install <category/portname>` mit den Default-Optionen installieren wollen oder ob wir die Optionen und somit auch den Funktionsumfang beziehungsweise die Features unserer Pakete/Ports selbst bestimmen wollen.

In diesem HowTo werden wir uns für die zweite Variante entscheiden, da uns dies viele Probleme durch unnötige oder fehlende Features und Abhängigkeiten ersparen wird. Andererseits verlieren wir dadurch den Komfort von `pkg` bei der Installation und den Updates der Pakete/Ports. Ebenso müssen wir zwangsweise für alle Pakete/Ports die gewünschten Optionen manuell setzen und die Pakete/Ports auch selbst kompilieren.

Dieses Vorgehen ist deutlich zeitaufwendiger und erfordert auch etwas mehr Wissen über die jeweiligen Pakete/Ports und deren Features, dafür entschädigt es uns aber mit einem schlankeren, schnelleren und stabileren System und bietet uns gegebenenfalls nützliche/erforderliche zusätzliche Funktionen und Sicherheitsfeatures. Auch die potentielle Gefahr für Sicherheitslücken sinkt dadurch, da wir unnütze Pakete/Ports gar nicht erst als Abhängigkeiten mitinstallieren müssen.

Wir deaktivieren also zuerst das Default-Repository von `pkg`, um versehentlichen Installationen von Binary-Paketen durch `pkg` vorzubeugen.

```shell
mkdir -p /usr/local/etc/pkg/repos
sed -e 's|quarterly|latest|' /etc/pkg/FreeBSD.conf > /usr/local/etc/pkg/repos/FreeBSD.conf
sed -e 's|\(enabled:\)[[:space:]]*yes|\1 no|g' -i '' /usr/local/etc/pkg/repos/FreeBSD.conf
```

So ganz ohne komfortable Tools ist das Basis-System etwas mühselig zu administrieren. Deshalb werden wir aus den Ports nun ein paar etwas häufiger benötigte Anwendungen installiert.

Die von uns jeweils gewünschten Build-Optionen der Ports legen wir dabei mittels der `options`-Files des Portkonfigurationsframeworks `OptionsNG` fest.

Dieser Cronjob prüft täglich um 7:00 Uhr ob es Updates für die installierten Pakete gibt und ob darin gegebenenfalls wichtige Sicherheitsupdates enthalten sind. Das Ergebnis wird automatisch per Mail an `root` (siehe `/etc/mail/aliases`) gesendet.

```shell
cat <<'EOF' >> /etc/crontab
0       7       *       *       *       root    /usr/local/bin/git -C /usr/ports pull --rebase --quiet && /usr/bin/make -C /usr/ports fetchindex && /usr/local/sbin/pkg version -vIL= && /usr/local/sbin/pkg audit -F
EOF
```

Wir installieren `ports-mgmt/pkg` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/ports-mgmt_pkg
cat <<'EOF' > /var/db/ports/ports-mgmt_pkg/options
--8<-- "freebsd/ports/ports-mgmt_pkg/options"
EOF


make -C /usr/ports/ports-mgmt/pkg/ all install clean-depends clean
```

Wir installieren `ports-mgmt/portmaster` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/ports-mgmt_portmaster
cat <<'EOF' > /var/db/ports/ports-mgmt_portmaster/options
--8<-- "freebsd/ports/ports-mgmt_portmaster/options"
EOF


make -C /usr/ports/ports-mgmt/portmaster/ all install clean-depends clean
```

Wir installieren `ports-mgmt/pkg` und `ports-mgmt/portmaster` und ihre Abhängigkeiten mittels `portmaster` erneut.

```shell
portmaster -w -B -g --force-config ports-mgmt/pkg

portmaster -w -B -g --force-config ports-mgmt/portconfig

portmaster -w -B -g --force-config ports-mgmt/portmaster
```

Wir installieren `lang/perl5.42` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/lang_perl5.42
cat <<'EOF' > /var/db/ports/lang_perl5.42/options
--8<-- "freebsd/ports/lang_perl5.42/options"
EOF


portmaster -w -B -g --force-config lang/perl5.42  -n
```

Wir installieren `security/openssl35` und dessen Abhängigkeiten.

```shell
cat <<'EOF' >> /etc/make.conf
DEFAULT_VERSIONS+=ssl=openssl35
EOF


mkdir -p /var/db/ports/security_openssl35
cat <<'EOF' > /var/db/ports/security_openssl35/options
--8<-- "freebsd/ports/security_openssl35/options"
EOF


portmaster -w -B -g --force-config security/openssl35  -n
```

Wir installieren `security/ca_root_nss` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/security_ca_root_nss
cat <<'EOF' > /var/db/ports/security_ca_root_nss/options
--8<-- "freebsd/ports/security_ca_root_nss/options"
EOF


portmaster -w -B -g --force-config security/ca_root_nss  -n
```

Wir installieren `devel/pcre2` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/devel_autoconf
cat <<'EOF' > /var/db/ports/devel_autoconf/options
--8<-- "freebsd/ports/devel_autoconf/options"
EOF

mkdir -p /var/db/ports/devel_m4
cat <<'EOF' > /var/db/ports/devel_m4/options
--8<-- "freebsd/ports/devel_m4/options"
EOF

mkdir -p /var/db/ports/devel_gettext-runtime
cat <<'EOF' > /var/db/ports/devel_gettext-runtime/options
--8<-- "freebsd/ports/devel_gettext-runtime/options"
EOF

mkdir -p /var/db/ports/devel_gettext-tools
cat <<'EOF' > /var/db/ports/devel_gettext-tools/options
--8<-- "freebsd/ports/devel_gettext-tools/options"
EOF

mkdir -p /var/db/ports/devel_libtextstyle
cat <<'EOF' > /var/db/ports/devel_libtextstyle/options
--8<-- "freebsd/ports/devel_libtextstyle/options"
EOF

mkdir -p /var/db/ports/devel_automake
cat <<'EOF' > /var/db/ports/devel_automake/options
--8<-- "freebsd/ports/devel_automake/options"
EOF

mkdir -p /var/db/ports/devel_gmake
cat <<'EOF' > /var/db/ports/devel_gmake/options
--8<-- "freebsd/ports/devel_gmake/options"
EOF

mkdir -p /var/db/ports/devel_pkgconf
cat <<'EOF' > /var/db/ports/devel_pkgconf/options
--8<-- "freebsd/ports/devel_pkgconf/options"
EOF

mkdir -p /var/db/ports/devel_pcre2
cat <<'EOF' > /var/db/ports/devel_pcre2/options
--8<-- "freebsd/ports/devel_pcre2/options"
EOF


portmaster -w -B -g --force-config devel/pcre2  -n
```

Wir installieren `lang/lua54` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/lang_lua54
cat <<'EOF' > /var/db/ports/lang_lua54/options
--8<-- "freebsd/ports/lang_lua54/options"
EOF


portmaster -w -B -g --force-config lang/lua54  -n
```

Wir installieren `lang/tcl86` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/lang_tcl86
cat <<'EOF' > /var/db/ports/lang_tcl86/options
--8<-- "freebsd/ports/lang_tcl86/options"
EOF


portmaster -w -B -g --force-config lang/tcl86  -n
```

Wir installieren `lang/python` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/devel_readline
cat <<'EOF' > /var/db/ports/devel_readline/options
--8<-- "freebsd/ports/devel_readline/options"
EOF

mkdir -p /var/db/ports/math_mpdecimal
cat <<'EOF' > /var/db/ports/math_mpdecimal/options
--8<-- "freebsd/ports/math_mpdecimal/options"
EOF

mkdir -p /var/db/ports/lang_python311
cat <<'EOF' > /var/db/ports/lang_python311/options
--8<-- "freebsd/ports/lang_python311/options"
EOF


portmaster -w -B -g --force-config lang/python  -n
```

Wir installieren `devel/py-pip` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/devel_py-pip
cat <<'EOF' > /var/db/ports/devel_py-pip/options
--8<-- "freebsd/ports/devel_py-pip/options"
EOF


portmaster -w -B -g --force-config devel/py-pip  -n
```

Wir installieren `devel/re2c` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/devel_cmake-core
cat <<'EOF' > /var/db/ports/devel_cmake-core/options
--8<-- "freebsd/ports/devel_cmake-core/options"
EOF

mkdir -p /var/db/ports/devel_ninja
cat <<'EOF' > /var/db/ports/devel_ninja/options
--8<-- "freebsd/ports/devel_ninja/options"
EOF

mkdir -p /var/db/ports/devel_libunistring
cat <<'EOF' > /var/db/ports/devel_libunistring/options
--8<-- "freebsd/ports/devel_libunistring/options"
EOF

mkdir -p /var/db/ports/misc_help2man
cat <<'EOF' > /var/db/ports/misc_help2man/options
--8<-- "freebsd/ports/misc_help2man/options"
EOF

mkdir -p /var/db/ports/print_texinfo
cat <<'EOF' > /var/db/ports/print_texinfo/options
--8<-- "freebsd/ports/print_texinfo/options"
EOF

mkdir -p /var/db/ports/converters_libiconv
cat <<'EOF' > /var/db/ports/converters_libiconv/options
--8<-- "freebsd/ports/converters_libiconv/options"
EOF

mkdir -p /var/db/ports/devel_p5-Locale-libintl
cat <<'EOF' > /var/db/ports/devel_p5-Locale-libintl/options
--8<-- "freebsd/ports/devel_p5-Locale-libintl/options"
EOF

mkdir -p /var/db/ports/security_rhash
cat <<'EOF' > /var/db/ports/security_rhash/options
--8<-- "freebsd/ports/security_rhash/options"
EOF

mkdir -p /var/db/ports/textproc_expat2
cat <<'EOF' > /var/db/ports/textproc_expat2/options
--8<-- "freebsd/ports/textproc_expat2/options"
EOF

mkdir -p /var/db/ports/devel_re2c
cat <<'EOF' > /var/db/ports/devel_re2c/options
--8<-- "freebsd/ports/devel_re2c/options"
EOF


portmaster -w -B -g --force-config devel/re2c  -n
```

Wir installieren `shells/bash` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/devel_bison
cat <<'EOF' > /var/db/ports/devel_bison/options
--8<-- "freebsd/ports/devel_bison/options"
EOF

mkdir -p /var/db/ports/shells_bash
cat <<'EOF' > /var/db/ports/shells_bash/options
--8<-- "freebsd/ports/shells_bash/options"
EOF


portmaster -w -B -g --force-config shells/bash  -n
```

Wir installieren `ftp/curl` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/archivers_brotli
cat <<'EOF' > /var/db/ports/archivers_brotli/options
--8<-- "freebsd/ports/archivers_brotli/options"
EOF

mkdir -p /var/db/ports/archivers_zstd
cat <<'EOF' > /var/db/ports/archivers_zstd/options
--8<-- "freebsd/ports/archivers_zstd/options"
EOF

mkdir -p /var/db/ports/archivers_liblz4
cat <<'EOF' > /var/db/ports/archivers_liblz4/options
--8<-- "freebsd/ports/archivers_liblz4/options"
EOF

mkdir -p /var/db/ports/security_libssh2
cat <<'EOF' > /var/db/ports/security_libssh2/options
--8<-- "freebsd/ports/security_libssh2/options"
EOF

mkdir -p /var/db/ports/dns_libpsl
cat <<'EOF' > /var/db/ports/dns_libpsl/options
--8<-- "freebsd/ports/dns_libpsl/options"
EOF

mkdir -p /var/db/ports/ftp_curl
cat <<'EOF' > /var/db/ports/ftp_curl/options
--8<-- "freebsd/ports/ftp_curl/options"
EOF


portmaster -w -B -g --force-config ftp/curl  -n
```

Wir installieren `lang/rust` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/devel_binutils
cat <<'EOF' > /var/db/ports/devel_binutils/options
--8<-- "freebsd/ports/devel_binutils/options"
EOF

mkdir -p /var/db/ports/math_gmp
cat <<'EOF' > /var/db/ports/math_gmp/options
--8<-- "freebsd/ports/math_gmp/options"
EOF

mkdir -p /var/db/ports/math_mpfr
cat <<'EOF' > /var/db/ports/math_mpfr/options
--8<-- "freebsd/ports/math_mpfr/options"
EOF

mkdir -p /var/db/ports/lang_rust
cat <<'EOF' > /var/db/ports/lang_rust/options
--8<-- "freebsd/ports/lang_rust/options"
EOF


portmaster -w -B -g --force-config lang/rust  -n
```

Wir installieren `lang/ruby33` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/lang_ruby33
cat <<'EOF' > /var/db/ports/lang_ruby33/options
{{~ include "snippets/ports/lang_ruby33/options"
EOF


portmaster -w -B -g --force-config lang/ruby33  -n
```

Wir installieren `devel/ruby-gems` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/devel_ruby-gems
cat <<'EOF' > /var/db/ports/devel_ruby-gems/options
{{~ include "snippets/ports/devel_ruby-gems/options"
EOF


portmaster -w -B -g --force-config devel/ruby-gems  -n
```

Wir installieren `sysutils/rubygem-bundler` und dessen Abhängigkeiten.

```shell
portmaster -w -B -g --force-config sysutils/rubygem-bundler  -n
```

Wir installieren `lang/go` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/lang_go124
cat <<'EOF' > /var/db/ports/lang_go124/options
{{~ include "snippets/ports/lang_go124/options"
EOF


portmaster -w -B -g --force-config lang/go  -n
```

Wir installieren `sysutils/cpu-microcode` und dessen Abhängigkeiten.

```shell
mkdir -p /var/db/ports/sysutils_cpu-microcode-intel
cat <<'EOF' > /var/db/ports/sysutils_cpu-microcode-intel/options
{{~ include "snippets/ports/sysutils_cpu-microcode-intel/options"
EOF


portmaster -w -B -g --force-config sysutils/cpu-microcode  -n


sysrc microcode_update_enable=YES
```

## Wie geht es weiter?

Viel Spass mit den neuen FreeBSD BasePorts.
