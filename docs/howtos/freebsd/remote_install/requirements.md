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
title: Remote Installation
description: In diesem HowTo werden Schritt für Schritt die Voraussetzungen für die Remote Installation des FreeBSD 64 Bit BaseSystem auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# Voraussetzungen

> **Stand:** 2026-02-27  
> **Terminologie:** Einheitlich werden die Begriffe **HowTo**, **HowTos**, **BaseSystem**, **BasePorts** und **BaseTools** verwendet.

## Quick-Check vor Start

- [ ] Voraussetzungen aus den Vorseiten erfüllt
- [ ] DNS/FQDN/IPv4/IPv6 final geprüft
- [ ] Zugriff auf Konsole/Recovery vorhanden
- [ ] Platzhalter (`__USERNAME__`, `__PASSWORD__`, `__IPADDR4__`, `__IPADDR6__`) vorbereitet

---


Die Installation des FreeBSD BaseSystem setzt ein wie in [mfsBSD Image](mfsbsd_image.md) beschriebenes, bereits fertig erstelltes mfsBSD Image voraus.

## Einleitung

Unser BaseSystem wird am Ende folgende Dienste umfassen.

- FreeBSD 14.3-RELEASE 64 Bit
- OpenSSL 3.0.16
- OpenSSH 9.9p2
- Unbound 1.22.0

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

Folgende Punkte sind in allen folgenden HowTos zu beachten.

- Alle Dienste werden mit einem möglichst minimalen und bewährten Funktionsumfang installiert.
- Alle Dienste werden mit einer möglichst sicheren und dennoch flexiblen Konfiguration versehen.
- Alle Konfigurationen sind selbstständig auf notwendige individuelle Anpassungen zu kontrollieren.
- Alle Benutzernamen werden als `__USERNAME__` dargestellt und sind selbstständig passend zu ersetzen.
- Alle Passwörter werden als `__PASSWORD__` dargestellt und sind selbstständig durch sichere Passwörter zu ersetzen.
- Die Domain des Servers lautet `example.com` und ist selbstständig durch die eigene Domain zu ersetzen.
- Der Hostname des Servers lautet `devnull` und ist selbstständig durch den eigenen Hostnamen zu ersetzen (FQDN=devnull.example.com).
- Es wird der FQDN `devnull.example.com` verwendet und ist selbstständig im DNS zu registrieren.
- Die primäre IPv4 Adresse des Systems wird als `__IPADDR4__` dargestellt und ist selbstständig zu ersetzen.
- Die primäre IPv6 Adresse des Systems wird als `__IPADDR6__` dargestellt und ist selbstständig zu ersetzen.

## DNS Records

Für diese HowTos müssen zuvor folgende DNS-Records angelegt werden, sofern sie noch nicht existieren, oder entsprechend geändert werden, sofern sie bereits existieren.

```dns-zone
example.com.            IN  A       __IPADDR4__
example.com.            IN  AAAA    __IPADDR6__

devnull.example.com.    IN  A       __IPADDR4__
devnull.example.com.    IN  AAAA    __IPADDR6__
```

## Das Referenzsystem

Als Referenzsystem für dieses HowTo habe ich mich für eine virtuelle Maschine auf Basis von [Oracle VirtualBox](https://www.virtualbox.org/) unter [Microsoft Windows 11 Pro (64 Bit)](https://www.microsoft.com/en-us/windows/windows-11) entschieden. So lässt sich ohne grösseren Aufwand ein handelsüblicher dedizierter Server simulieren und anschliessend kann diese virtuelle Maschine als kostengünstiges lokales Testsystem weiter genutzt werden.

Trotzdem habe ich dieses HowTo so ausgelegt, dass es sich nahezu unverändert auf handelsübliche dedizierte Server übertragen lässt und dieses auch auf mehreren dedizierten Servern getestet.

Obwohl Microsoft Windows 11 Pro einen eigenen OpenSSH-Client mitbringt, greife ich lieber auf das sehr empfehlenswerte [PuTTY (64 Bit)](https://www.chiark.greenend.org.uk/~sgtatham/putty/) zurück.

VirtualBox (inklusive dem Extensionpack) und PuTTY werden mit den jeweiligen Standardoptionen installiert.

```powershell
winget install PuTTY.PuTTY
winget install Oracle.VirtualBox

$Env:vbox_ver=((winget show Oracle.VirtualBox) -match '^Version:' -split '\s+' | Select-Object -Last 1)
curl -o "Oracle_VM_VirtualBox_Extension_Pack-${Env:vbox_ver}.vbox-extpack" -L "https://download.virtualbox.org/virtualbox/${Env:vbox_ver}/Oracle_VirtualBox_Extension_Pack-${Env:vbox_ver}.vbox-extpack"
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" extpack install --replace Oracle_VirtualBox_Extension_Pack-${Env:vbox_ver}.vbox-extpack
rm Oracle_VirtualBox_Extension_Pack-${Env:vbox_ver}.vbox-extpack
$Env:vbox_ver=''
```

## Die Virtuelle Maschine

Als Erstes öffnen wir eine neue PowerShell und legen manuell eine neue virtuelle Maschine an. Diese virtuelle Maschine bekommt den Namen `FreeBSD` und wird mit einer UEFI-Firmware, einem Quad-Core Prozessor, Intels ICH9-Chipsatz, 8192MB RAM, 64MB VideoRAM, zwei 64GB SSD-Festplatten, einem DVD-Player, einer Netzwerkkarte, einem NVMe-Controller sowie einem AHCI-Controller und einem TPM 2.0 ausgestattet. Zudem setzen wir die RTC (Real-Time Clock) der virtuellen Maschine auf UTC (Coordinated Universal Time), aktivieren den HPET (High Precision Event Timer) und legen die Bootreihenfolge fest.

```powershell
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" createvm --name "FreeBSD" --ostype FreeBSD_64 --register

cd "${Env:USERPROFILE}\VirtualBox VMs\FreeBSD"

& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" createmedium disk --filename "FreeBSD1.vdi" --format VDI --size 64536
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" createmedium disk --filename "FreeBSD2.vdi" --format VDI --size 64536

& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" modifyvm "FreeBSD" --firmware efi --memory 8192 --vram 64 --cpus 4 --hpet on --hwvirtex on --chipset ICH9 --iommu automatic --tpm-type 2.0 --rtc-use-utc on
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" modifyvm "FreeBSD" --cpu-profile host --apic on --ioapic on --x2apic on --pae on --long-mode on --nested-paging on --large-pages on --vtx-vpid on --vtx-ux on
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" modifyvm "FreeBSD" --nic1 nat --nic-type1 virtio --nat-pf1 "VBoxSSH,tcp,,2222,,22" --nat-pf1 "VBoxHTTP,tcp,,8080,,80" --nat-pf1 "VBoxHTTPS,tcp,,8443,,443"
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" modifyvm "FreeBSD" --graphicscontroller vmsvga --audio-enabled off --usb-ehci off --usb-ohci off --usb-xhci off --boot1 dvd --boot2 disk --boot3 none --boot4 none

& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" storagectl "FreeBSD" --name "NVMe Controller" --add pcie --controller NVMe --portcount 4 --bootable on --hostiocache off
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" storagectl "FreeBSD" --name "AHCI Controller" --add sata --controller IntelAHCI --portcount 4 --bootable on --hostiocache off

& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" storageattach "FreeBSD" --storagectl "NVMe Controller" --port 0 --device 0 --type hdd --nonrotational on --medium "FreeBSD1.vdi"
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" storageattach "FreeBSD" --storagectl "NVMe Controller" --port 1 --device 0 --type hdd --nonrotational on --medium "FreeBSD2.vdi"
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" storageattach "FreeBSD" --storagectl "AHCI Controller" --port 0 --device 0 --type dvddrive --medium emptydrive
```

Nachdem die virtuelle Maschine nun konfiguriert ist, wird es Zeit diese zu booten.

## Los geht es

Die einzelnen HowTos bauen aufeinander auf, daher sollten sie in der Reihenfolge von oben nach unten bis zum Ende abgearbeitet werden.

Viel Spass und Erfolg
