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
title: OpenDKIM
description: In diesem HowTo wird Schritt für Schritt die Installation von OpenDKIM für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# OpenDKIM

## Inhalt

- OpenDKIM 2.10.3
- DKIM-Signing als Milter für Postfix
- 2048 Bit RSA
- KeyTable, SigningTable, TrustedHosts
- VBR-Unterstützung im Paket, in diesem HowTo aber nicht separat konfiguriert

---

## Einleitung

Dieses HowTo beschreibt die Installation und Konfiguration von OpenDKIM auf FreeBSD 15+.

OpenDKIM wird in diesem Setup als **Milter** für Postfix verwendet und signiert ausgehende E-Mails per **DKIM**. Der aktuelle FreeBSD-Port `mail/opendkim` steht bei **2.10.3** und beschreibt OpenDKIM als DKIM-Bibliothek plus milter-basierte Filteranwendung. Das Paket enthält außerdem Unterstützung für **VBR**, dieses HowTo konzentriert sich aber bewusst auf **DKIM-Signing mit 2048-Bit-RSA**.

---

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

Zusätzlich wird vorausgesetzt:

- Postfix ist bereits installiert und für Milter vorbereitet.
- Die Absenderdomain `example.com` ist bereits produktiv in Verwendung.
- DNS-Änderungen für `example.com` können veröffentlicht werden.
- Der Server versendet E-Mails über Postfix.
- Für dieses HowTo wird `example.com` als Beispiel verwendet und muss an den passenden Stellen ersetzt werden.

---

## Vorbereitungen

### DNS Records

Für dieses HowTo müssen zuvor folgende DNS-Records angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

``` dns-zone
mail.example.com.        IN  A       __IPADDR4__
mail.example.com.        IN  AAAA    __IPADDR6__
````

Der eigentliche DKIM-Selector-Record wird erst **nach** der Schlüsselerzeugung veröffentlicht, weil er direkt aus dem generierten öffentlichen Schlüssel entsteht. `opendkim-genkey` erzeugt dafür bereits eine passende TXT-Datei. ([FreeBSD Manual Pages][2])

### Verzeichnisse / Dateien

Für dieses HowTo müssen zuvor folgende Verzeichnisse angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

```shell
install -d -m 0750 -o mailnull -g mailnull /var/db/opendkim
install -d -m 0750 -o mailnull -g mailnull /var/db/opendkim/keys
install -d -m 0750 -o mailnull -g mailnull /var/db/opendkim/keys/example.com
```

Für diese HowTos müssen zuvor folgende Dateien angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

```shell
install -b -m 0640 /dev/null /usr/local/etc/mail/opendkim.conf
install -b -m 0640 -o mailnull -g mailnull /dev/null /var/db/opendkim/keytable
install -b -m 0640 -o mailnull -g mailnull /dev/null /var/db/opendkim/signingtable
install -b -m 0640 -o mailnull -g mailnull /dev/null /var/db/opendkim/trustedhosts
```

### Gruppen / Benutzer / Passwörter

Für dieses HowTo müssen **keine zusätzlichen** Systemgruppen, Systembenutzer oder Passwörter angelegt werden.

Das FreeBSD-rc-Skript verwendet standardmäßig den Benutzer und die Gruppe **`mailnull`**. ([GitHub][3])

---

## Installation

### Wir installieren `mail/opendkim` und dessen Abhängigkeiten.

```shell
install -d -m 0755 /var/db/ports/mail_opendkim
cat <<'EOF' > /var/db/ports/mail_opendkim/options
--8<-- "freebsd/ports/mail_opendkim/options"
EOF

portmaster -w -B -g -U --force-config mail/opendkim -n
```

### Dienst in `rc.conf` eintragen

Der Dienst wird mittels `sysrc` in der `rc.conf` eingetragen und dadurch beim Systemstart automatisch gestartet.

```sh
sysrc milteropendkim_enable=YES
sysrc milteropendkim_socket="local:/var/run/milteropendkim/opendkim.sock"
sysrc milteropendkim_pidfile="/var/run/milteropendkim/opendkim.pid"
```

Das FreeBSD-rc-Skript verwendet den Dienstnamen **`milter-opendkim`**, die `rc.conf`-Variable heißt aber **`milteropendkim_enable`**. Die Standard-Konfigurationsdatei liegt unter `/usr/local/etc/mail/opendkim.conf`. Das Socket-Verzeichnis für lokale Sockets wird vom rc-Skript beim Start selbst angelegt, sofern `milteropendkim_socket` gesetzt ist. Eine separate `milteropendkim_pidfile`-Variable ist im rc-Skript dagegen nicht vorgesehen; das Skript liest `PidFile` aus der Konfiguration oder fällt auf `/var/run/milteropendkim/pid` zurück. ([GitHub][3])

---

## Konfiguration

### Konfigurationsdatei

```shell
cat <<'EOF' > /usr/local/etc/mail/opendkim.conf
--8<-- "freebsd/configs/usr/local/etc/mail/opendkim.conf"
EOF
```

### Signing-Key erzeugen

`opendkim-genkey` erzeugt sowohl den privaten Schlüssel als auch direkt die passende DNS-TXT-Datei. Standardmäßig wären **1024 Bit** voreingestellt; für dieses Setup werden bewusst **2048 Bit** verwendet. Mit `-r` wird der Schlüssel auf E-Mail-Signing beschränkt. ([FreeBSD Manual Pages][2])

```shell
opendkim-genkey \
  --append-domain \
  --bits=2048 \
  --directory=/var/db/opendkim/keys/example.com \
  --domain=example.com \
  --hash-algorithms=sha256 \
  --note=example.com \
  --restrict \
  --selector=20260321 \
  --verbose
```

### KeyTable anlegen

```shell
cat <<'EOF' > /var/db/opendkim/keytable
20260321._domainkey.example.com    example.com:20260321:/var/db/opendkim/keys/example.com/20260321.private
EOF
```

### SigningTable anlegen

```shell
cat <<'EOF' > /var/db/opendkim/signingtable
*@example.com      20260321._domainkey.example.com
*@*.example.com    20260321._domainkey.example.com
EOF
```

### TrustedHosts anlegen

```shell
cat <<'EOF' > /var/db/opendkim/trustedhosts
::1
127.0.0.1
fe80::/10
ff02::/16
10.0.0.0/8
__IPADDR4__/32
__IPADDR6__/64
localhost
example.com
*.example.com
EOF

# Standard-Interface ermitteln
DEF_IF="$(route -n get -inet default | awk '/interface:/ {print $2}')"

# Primäre IPv4-Adresse ermitteln
IP4="$(ifconfig "$DEF_IF" inet | awk '/inet / && $2 !~ /^127\./ {print $2}' | head -n 1)"
[ -n "$IP4" ] && sed -e "s|__IPADDR4__|$IP4|g" -i '' /var/db/opendkim/trustedhosts

# Primäre globale IPv6-Adresse ermitteln
IP6="$(ifconfig "$DEF_IF" inet6 | awk '/inet6 / && $2 !~ /^fe80:/ && $2 !~ /^::1/ {print $2}' | head -n 1)"
[ -n "$IP6" ] && sed -e "s|__IPADDR6__|$IP6|g" -i '' /var/db/opendkim/trustedhosts
```

### Rechte setzen

`opendkim-testkey` meldet ausdrücklich, wenn eine private Schlüsseldatei für andere Benutzer lesbar ist. Deshalb sollten die Schlüsseldateien nicht unnötig offen liegen. ([FreeBSD Manual Pages][4])

```shell
chown -R mailnull:mailnull /var/db/opendkim
chmod 0600 /var/db/opendkim/keys/example.com/20260321.private
chmod 0644 /var/db/opendkim/keys/example.com/20260321.txt
chmod 0640 /var/db/opendkim/keytable
chmod 0640 /var/db/opendkim/signingtable
chmod 0640 /var/db/opendkim/trustedhosts
```

### DNS-TXT-Record veröffentlichen

`opendkim-genkey` erzeugt den passenden TXT-Record bereits selbst in der Datei `20260321.txt`. Diese Datei wird als Grundlage für den DNS-Eintrag veröffentlicht. ([FreeBSD Manual Pages][2])

Es muss noch ein DNS-Record angelegt werden, sofern er noch nicht existiert, oder entsprechend geändert werden, sofern er bereits existiert.

``` shell
/usr/local/bin/openssl pkey -pubout -outform pem -in /var/db/opendkim/keys/example.com/20260321.private | \
    awk '\!/^-----/ {printf $0}' | awk 'BEGIN{n=1}\
        {printf "\n20260321._domainkey.example.com.    IN  TXT    ( \"v=DKIM1; h=shs256; k=rsa; s=*; t=*; p=\"";\
            while(substr($0,n,98)){printf "\n        \"" substr($0,n,98) "\"";n+=98};printf " )\n"}'
```

Die Ausgabe sieht ungefähr so aus und muss als DNS-Record für die Domain veröffentlicht werden:

``` dns-zone
#
# The output should look similar to this one, which will be the DNS-Record to publish
#
# Note: The folding of the pubkey is necessary as most nameservers have a line-length limit
#       If you use a DNS-Provider to publish your records, then use their free-text fields
#       to insert the record into their form
#
20260321._domainkey.example.com.    IN  TXT    ( "v=DKIM1; h=shs256; k=rsa; s=*; t=*; p="
        "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA1Up5Z0TkPpE0mNAc9lf7Uug7P/n28Kk6fXC1V8m93dE+NPgsTKp4k+"
        "t2S3EujANO7J8WyBppE+aTbyQjU5TtaIPC8TS3sBg/6JX/QAw73+Hv03lieutmZ0GO4uuvj+QbOuDqNwHR/DZih3BrV7Mtit4F"
        "bILcz+V1QbJ7YssRQRaZ/LTGZ0Q6QLGr6BG9h3Ro4g1bTirFIuvbaVUuzDK/KxHKRAuAhIB7mmrpPRDQlFjgva9vQYsQUcQtVh"
        "Y/z6YvcGNvEWhme3gaTWzdG20aLTxut4Il17OSWiCbF0wdnUn0bnKins14YeHjkDhOhMoEagd3lWWs0k2KNxnbYljPQwIDAQAB" )
```

### Konfiguration prüfen

Vor dem ersten Start sollte die Konfiguration immer geprüft werden. `opendkim-testkey` ist genau für diesen Zweck gedacht: Es prüft Domain, Selector, öffentlichen DNS-Key und bei Bedarf auch den privaten Schlüssel. ([FreeBSD Manual Pages][4])

```sh
opendkim-testkey -d example.com -s 20260321 -k /var/db/opendkim/keys/example.com/20260321.private -vvv
service milter-opendkim start
sockstat -4 -6 -l | egrep 'opendkim|milter'
```

---

## Datenbanken

Für dieses HowTo sind **keine Datenbanken** erforderlich.

---

## Zusatzsoftware

Mögliche Zusatzsoftware wird hier installiert und konfiguriert.

Für dieses HowTo ist **keine zusätzliche Software** erforderlich.

OpenDKIM selbst enthält laut Portbeschreibung zwar auch **VBR**-Unterstützung, dieses HowTo richtet aber nur die **DKIM-Signatur** für ausgehende E-Mails ein. ([FreshPorts][1])

---

## Aufräumen

Überflüssige oder temporäre Verzeichnisse und Dateien entsorgen.

### Zusatzsoftware Installation

Nicht erforderlich.

### Zusatzsoftware Konfiguration

Nicht erforderlich.

---

## Abschluss

OpenDKIM kann nun gestartet werden.

```sh
service milter-opendkim start
```

Für spätere Änderungen:

```sh
service milter-opendkim reload
service milter-opendkim restart
```

---

## Referenzen

* FreshPorts: `mail/opendkim`
* FreeBSD Ports rc-Skript: `milter-opendkim`
* FreeBSD Manpage: `opendkim(8)`
* FreeBSD Manpage: `opendkim-genkey(8)`
* FreeBSD Manpage: `opendkim-testkey(8)`
* OpenDKIM Dokumentation: `opendkim.conf(5)`

[1]: https://www.freshports.org/mail/opendkim/ "FreshPorts -- mail/opendkim: DKIM library and milter implementation"
[2]: https://man.freebsd.org/cgi/man.cgi?manpath=freebsd-ports&query=opendkim-genkey&sektion=8 "opendkim-genkey(8)"
[3]: https://github.com/freebsd/freebsd-ports/blob/main/mail/opendkim/files/milter-opendkim.in "freebsd-ports/mail/opendkim/files/milter-opendkim.in at main · freebsd/freebsd-ports · GitHub"
[4]: https://man.freebsd.org/cgi/man.cgi?manpath=FreeBSD+14.3-RELEASE+and+Ports&query=opendkim-testkey "opendkim-testkey"
