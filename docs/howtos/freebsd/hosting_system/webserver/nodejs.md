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
title: NodeJS
description: In diesem HowTo wird Schritt für Schritt die Installation von Node.js für ein Hosting System auf Basis von FreeBSD 64 Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# NodeJS

## Inhalt

- Node.js 24
- npm
- optional Yarn Classic
- optional Corepack
- kein eigener Systemdienst

---

## Einleitung

Dieses HowTo beschreibt die Installation und Konfiguration von Node.js auf FreeBSD 15+.

Für eine reproduzierbare Installation werden in diesem HowTo bewusst die versionsspezifischen Ports **`www/node`**, **`www/npm`** und optional **`www/yarn`** verwendet. Die Ports **`www/node`**, **`www/npm`** und **`www/yarn`** sind auf FreeBSD nur noch **Meta-Ports**, die auf die jeweils aktuelle Default-Version zeigen. Für eine feste Node-24-Dokumentation ist das die falsche Basis. Auf FreeBSD 15 liegen die aktuellen Stände bei **24.13.0 / 11.7.0 / 1.22.19** im Quarterly-Zweig und **24.14.0 / 11.11.0 / 1.22.19** auf `latest`.

Node.js selbst ist dabei **kein globaler Systemdienst**, sondern eine Laufzeitumgebung für CLI-Tools, Build-Prozesse und einzelne Anwendungsdienste. Der Port `www/node24` bringt ausdrücklich **kein** rc.d-Skript mit.

---

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Hosting System](../requirements.md)

Zusätzlich gilt für dieses HowTo:

- Node.js wird hier als Laufzeitumgebung installiert, nicht als globaler Dienst.
- Produktive Node-Anwendungen brauchen später **pro Anwendung** einen eigenen Startmechanismus.
- Falls projektweise unterschiedliche Paketmanager-Versionen genutzt werden sollen, ist **Corepack** die sauberere Basis als eine globale Mischinstallation.

---

## Vorbereitungen

### DNS Records

Für dieses HowTo sind **keine DNS-Records** erforderlich.

### Gruppen / Benutzer / Passwörter

Für dieses HowTo sind **keine zusätzlichen Systemgruppen, Systembenutzer oder Passwörter** erforderlich.

### Verzeichnisse / Dateien

Für dieses HowTo müssen vor der Installation **keine zusätzlichen Verzeichnisse oder Dateien** angelegt werden.

---

## Installation

### Wir installieren `www/node24` und dessen Abhängigkeiten.

``` sh
mkdir -p /var/db/ports/www_node24
cat <<'EOF' > /var/db/ports/www_node24/options
--8<-- "freebsd/ports/www_node24/options"
EOF

portmaster -w -B -g -U --force-config www/node -n
```

`www/node24` installiert die eigentliche Laufzeit `node`. Der Port hat auf FreeBSD 15 aktuell zusätzlich eine Runtime-Abhängigkeit auf **`www/corepack`**. In der pkg-message des Ports wird außerdem ausdrücklich darauf hingewiesen, dass für `npm` der separate Port **`www/npm`** installiert werden soll. ([FreshPorts][2])

### Dienst in `rc.conf` eintragen

Nicht erforderlich.

Node.js ist in diesem HowTo **kein Systemdienst**, und `www/node` liefert **kein** `USE_RC_SUBR`-Skript mit. Deshalb gibt es hier bewusst **keinen** `sysrc`-Schritt. ([FreshPorts][2])

---

## Konfiguration

### Zentrale Konfigurationsdatei

Für Node.js selbst gibt es in diesem HowTo **keine zentrale System-Konfigurationsdatei** wie bei klassischen Daemons.

Die eigentliche Konfiguration liegt später in den jeweiligen Projekten, zum Beispiel in `package.json`, Lockfiles und projektbezogenen Build- oder Startskripten.

### Installation prüfen

``` sh
node -v
which node
```

### Corepack aktivieren

Corepack ist auf Node-24-Basis verfügbar. Laut Node-Dokumentation ist Corepack seit **Node 14.19 / 16.9** vorhanden und bleibt bis **unter Node 25** Teil der Standardverteilung. Yarn empfiehlt Corepack heute als bevorzugten Weg, um Paketmanager-Versionen **pro Projekt** zu steuern. Corepack ist dabei weiterhin als **experimental** markiert und muss zunächst aktiviert werden. ([Node.js][3])

``` sh
corepack enable
corepack --version
```

Wenn ein Projekt das Feld `packageManager` in der `package.json` setzt, kann Corepack genau daraus die gewünschte Paketmanager-Version ableiten. ([yarnpkg.com][4])

---

## Datenbanken

Für dieses HowTo sind **keine Datenbanken** erforderlich.

---

## Zusatzsoftware

Mögliche Zusatzsoftware wird hier installiert und konfiguriert.

### npm für Node 24 installieren

Wenn `npm` systemweit verfügbar sein soll, installieren wir den zu Node 24 passenden Port **`www/npm`**. Der Port hat keine eigenen Optionen und hängt zur Laufzeit von `www/node` ab. ([FreshPorts][5])

``` sh
portmaster -w -B -g -U --force-config www/npm -n
```

### Yarn Classic für Node 24 installieren

Falls im Projekt **bewusst Yarn Classic 1.x** benötigt wird, installieren wir **`www/yarn`**. Aktuell steht dieser Port auf **1.22.19**. Yarn selbst empfiehlt heute für neue projektbezogene Setups eher **Corepack** statt einer globalen Yarn-Installation. ([FreshPorts][6])

``` sh
mkdir -p /var/db/ports/www_yarn-node24
cat <<'EOF' > /var/db/ports/www_yarn-node24/options
--8<-- "freebsd/ports/www_yarn-node24/options"
EOF

portmaster -w -B -g -U --force-config www/yarn -n
```

### Alternative zu Yarn: Corepack

Corepack ist für neue projektbezogene Paketmanager-Setups in der Regel die sauberere Lösung. Yarn dokumentiert Corepack ausdrücklich als den bevorzugten Weg. Auf FreeBSD ist `www/corepack` bei `www/node` bereits Laufzeitabhängigkeit. ([yarnpkg.com][7])

Beispiel:

``` sh
corepack enable
corepack --version
```

---

## Aufräumen

Überflüssige oder temporäre Verzeichnisse und Dateien entsorgen.

### Zusatzsoftware Installation

Nicht erforderlich.

### Zusatzsoftware Konfiguration

Nicht erforderlich.

---

## Abschluss

Node.js ist nun als Laufzeitumgebung installiert.

``` sh
node -v
```

Für npm, npx, Yarn und Corepack:

``` sh
npm -v
npx -v
yarn -v
corepack --version
```

Einen globalen Dienst gibt es hier bewusst **nicht**. Produktive Node-Anwendungen sollten **pro Anwendung** mit einem eigenen Startmechanismus betrieben werden, nicht über einen pauschalen Systemdienst für `node` selbst. ([FreshPorts][2])

---

## Referenzen

* FreshPorts: `www/node24`
* FreshPorts: `www/npm-node24`
* FreshPorts: `www/yarn-node24`
* FreshPorts: `www/node` (Meta-Port)
* Node.js Dokumentation: Corepack
* GitHub: `nodejs/corepack`
* Yarn Dokumentation: Corepack
* Yarn Dokumentation: `packageManager`

[1]: https://www.freshports.org/www/node24/ "FreshPorts -- www/node24: V8 JavaScript for client and server"
[2]: https://www.freshports.org/www/node24/?branch=2026Q1 "FreshPorts -- www/node24: V8 JavaScript for client and server"
[3]: https://nodejs.org/dist/latest/docs/api/corepack.html "Corepack | Node.js v25.8.1 Documentation"
[4]: https://yarnpkg.com/configuration/manifest "Manifest (package.json)"
[5]: https://www.freshports.org/www/npm-node24/ "FreshPorts -- www/npm-node24: Node package manager"
[6]: https://www.freshports.org/www/yarn-node24 "FreshPorts -- www/yarn-node24: Package manager for node, alternative to npm"
[7]: https://yarnpkg.com/corepack "Corepack"
