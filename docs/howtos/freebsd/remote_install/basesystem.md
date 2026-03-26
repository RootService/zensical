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
title: BaseSystem
description: In diesem HowTo wird Schritt für Schritt die Remote Installation des FreeBSD 64 Bit BaseSystem auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# BaseSystem

## Einleitung

In diesem HowTo beschreibe ich Schritt für Schritt die Remote Installation des [FreeBSD 64 Bit](https://www.freebsd.org/){: target="_blank" rel="noopener"} BaseSystem mittels [mfsBSD](https://mfsbsd.vx.sk/) auf einem dedizierten Server. Um eine weitere Reproduktion der offiziellen [FreeBSD Dokumentation](https://docs.freebsd.org/en/books/handbook/){: target="_blank" rel="noopener"} zu vermeiden, werde ich in diesem HowTo nicht alle Punkte bis ins Detail erläutern.

Unser BaseSystem wird folgende Dienste umfassen.

- FreeBSD 15.0-RELEASE 64 Bit
- OpenSSL 3.5.4
- OpenSSH 10.0p2
- Unbound 1.24.1

## Voraussetzungen

Zu den Voraussetzungen für dieses HowTo siehe bitte: [Remote Installation](requirements.md)

## RescueSystem booten

Um unser [mfsBSD Image](mfsbsd_image.md) installieren zu können, müssen wir unsere virtuelle Maschine mit einem RescueSystem booten. Hierfür eignet sich die auf [Arch Linux](https://www.archlinux.org/){: target="_blank" rel="noopener"} basierende [SystemRescueCD](https://www.system-rescue.org/){: target="_blank" rel="noopener"} am Besten, welche wir mittels des mit Windows mitgelieferten cURL-Client herunterladen und unserer virtuellen Maschine als Bootmedium zuweisen.

``` powershell
cd "${Env:USERPROFILE}\VirtualBox VMs\FreeBSD"

curl.exe -o "systemrescue-12.03-amd64.iso" "https://fastly-cdn.system-rescue.org/releases/12.03/systemrescue-12.03-amd64.iso"

& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" storageattach "FreeBSD" --storagectl "AHCI Controller" --port 0 --device 0 --type dvddrive --medium "systemrescue-12.03-amd64.iso"
```

Wir können das RescueSystem jetzt booten.

``` powershell
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" startvm "FreeBSD"
```

Im Bootmenü wählen wir die erste Option "Boot with default options" aus.

Ist der Bootvorgang abgeschlossen, wird als Erstes das root-Passwort für das RescueSystem gesetzt und die Firewall deaktiviert.

``` sh
setkmap de

openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | \
  cut -c 2-17 | tee /root/_your_password_

passwd root

systemctl stop iptables
systemctl stop ip6tables
```

Jetzt sollten wir uns mittels PuTTY als `root` in das RescueSystem einloggen und mit der Installation unseres mfsBSD Image fortfahren können.

``` powershell
putty -ssh -P 2222 root@127.0.0.1
```

## mfsBSD installieren

Um unsere umfangreichen Vorbereitungen nun abzuschliessen, müssen wir nur noch unser [mfsBSD Image](mfsbsd_image.md) installieren und booten.

Als Erstes kopieren wir mittels PuTTYs SCP-Client (`pscp`) das mfsBSD Image in das RescueSystem.

``` powershell
pscp -P 2222 "${Env:USERPROFILE}\VirtualBox VMs\mfsBSD\mfsbsd-15.0-RELEASE-amd64.img" root@127.0.0.1:/tmp/mfsbsd-15.0-RELEASE-amd64.img
```

Jetzt können wir das mfsBSD Image mittels `dd` auf der ersten Festplatte (`/dev/nvme0n1`) unserer virtuellen Maschine installieren und uns anschliessend wieder aus dem RescueSystem ausloggen.

``` sh
dd if=/dev/zero of=/dev/nvme0n1 count=512 bs=1M

dd if=/tmp/mfsbsd-15.0-RELEASE-amd64.img of=/dev/nvme0n1 bs=1M

exit
```

Abschliessend stoppen wir die virtuelle Maschine vorübergehend und entfernen die SystemRescueCD aus dem virtuellen DVD-Laufwerk.

``` powershell
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" controlvm "FreeBSD" poweroff

& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" storageattach "FreeBSD" --storagectl "AHCI Controller" --port 0 --device 0 --type dvddrive --medium emptydrive
```

## FreeBSD installieren

Nachdem nun alle Vorbereitungen abgeschlossen sind, können wir endlich mit der eigentlichen FreeBSD Remote Installation beginnen, indem wir die virtuelle Maschine wieder booten.

``` powershell
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" startvm "FreeBSD"
```

Jetzt sollten wir uns mittels PuTTY als `root` mit dem Passwort `mfsroot` in das mfsBSD Image einloggen und mit der Installation von FreeBSD beginnen können.

``` powershell
putty -ssh -P 2222 root@127.0.0.1
```

!!! info
    Diese Shell nutzt das amerikanische Tastaturlayout, welches einige Tasten anders belegt als das deutsche Tastaturlayout.

    Um auf das deutsche Tastaturlayout zu wechseln, wählen wir mittels `kbdmap` das Layout "German (accent keys)" aus:

    ```shell
    /usr/sbin/kbdmap -K
    ```

Zunächst setzen wir die Systemzeit (CMOS clock) mittels `tzsetup` auf "UTC" (Universal Time Code).

``` sh
/usr/sbin/tzsetup UTC
```

## Partitionieren der Festplatte

Bevor wir anfangen, bereinigen wir die Festplatten von jeglichen Datenrückständen, indem wir sie mit Nullen überschreiben. Je nach Festplattengrösse kann dies einige Stunden bis Tage in Anspruch nehmen. Aus diesem Grund verlegen wir diese Jobs mittels `nohup` in den Hintergrund, so dass wir uns zwischenzeitlich ausloggen können ohne dass dabei die Jobs automatisch von der Shell abgebrochen werden. Ob die Jobs fertig sind, lässt dann mittels `ps -auxfwww` und `top -atCP` ermitteln.

``` sh
nohup dd if=/dev/zero of=/dev/nvd0 bs=1M  &
nohup dd if=/dev/zero of=/dev/nvd1 bs=1M  &
```

Da jeder Administrator andere Präferenzen an sein Partitionslayout stellt und wir andernfalls mit diesem HowTo nicht weiterkommen, verwenden wir im Folgenden ein Standard-Partitionslayout. Fortgeschrittenere FreeBSD-Administratoren können dieses Partitionslayout selbstverständlich an ihre eigenen Bedürfnisse anpassen.

|    Partition     | Mountpunkt | Filesystem | Grösse |
| :--------------: | :--------- | :--------: | -----: |
| /dev/mirror/root | /          |    UFS2    |  60 GB |
| /dev/mirror/swap | none       |    SWAP    |   4 GB |

Als Erstes müssen wir die Festplatte partitionieren, was wir mittels `gpart` erledigen werden. Zuvor müssen wir dies aber dem Kernel mittels `sysctl` mitteilen, da er uns andernfalls dazwischenfunken würde.

Wir werden auf beiden Festplatten jeweils vier Partitionen anlegen, die Erste für den GPT-Bootcode, die Zweite für den EFI-Bootcode, die Dritte als Swap und die Vierte als Systempartition. Dabei werden wir die Partitionen auch gleich für modernere Festplatten mit 4K-Sektoren optimieren und statt den veralteten "MBR Partition Tables" die aktuelleren "GUID Partition Tables (GPT)" verwenden.

``` sh
sysctl kern.geom.debugflags=0x10

gpart destroy -F nvd0
gpart destroy -F nvd1

gpart create -s gpt nvd0
gpart create -s gpt nvd1

gpart add -t freebsd-boot  -b      40 -s     216 -l bootfs0 nvd0
gpart add -t efi           -b     256 -s    3840 -l efiesp0 nvd0
gpart add -t freebsd-swap  -b    4096 -s 8388608 -l swapfs0 nvd0
gpart add -t freebsd-ufs   -b 8392704            -l rootfs0 nvd0

gpart add -t freebsd-boot  -b      40 -s     216 -l bootfs1 nvd1
gpart add -t efi           -b     256 -s    3840 -l efiesp1 nvd1
gpart add -t freebsd-swap  -b    4096 -s 8388608 -l swapfs1 nvd1
gpart add -t freebsd-ufs   -b 8392704            -l rootfs1 nvd1

gpart set -a bootme -i 4 nvd0
gpart set -a bootme -i 4 nvd1
```

Für eine leicht erhöhte Datensicherheit legen wir mittels `gmirror` ein Software-RAID1 an.

``` sh
kldload geom_mirror
kldload zfs

sysctl vfs.zfs.min_auto_ashift=12

gmirror label -b load efiesp nvd0p2 nvd1p2
gmirror label -b load rootfs nvd0p4 nvd1p4
gmirror label -b prefer -F swapfs nvd0p3 nvd1p3
```

## Formatieren der Partitionen

Nun müssen wir noch die Systempartition und die Partition für die Nutzdaten mit "UFS2" und einer 4K-Blockgrösse formatieren und aktivieren auch gleich die "soft-updates".

``` sh
newfs -U -l -t /dev/mirror/rootfs
tunefs -a enable /dev/mirror/rootfs
```

## Mounten der Partitionen

Die Partitionen mounten wir unterhalb von `/mnt`.

``` sh
mount -t ufs /dev/mirror/rootfs /mnt
```

## Installation der Chroot-Umgebung

Auf die gemounteten Partitionen entpacken wir ein FreeBSD Basesystem mit dem wir problemlos weiterarbeiten können. Je nach Auslastung des FreeBSD FTP-Servers kann dies ein wenig dauern, bitte nicht ungeduldig werden.

``` sh
fetch -4 -q -o - --no-verify-peer "https://download.freebsd.org/releases/amd64/15.0-RELEASE/base.txz"   | tar Jxpvf - -C /mnt/
fetch -4 -q -o - --no-verify-peer "https://download.freebsd.org/releases/amd64/15.0-RELEASE/kernel.txz" | tar Jxpvf - -C /mnt/

cp -a /mnt/boot/kernel /mnt/boot/GENERIC
```

Unser System soll natürlich auch von den Festplatten booten können, weshalb wir jetzt den Bootcode und Bootloader in den Bootpartitionen installieren.

Festplatte 1:

``` sh
newfs_msdos /dev/gpt/efiesp0

mkdir -p /mnt/boot/efi

mount -t msdosfs /dev/gpt/efiesp0 /mnt/boot/efi

mkdir -p /mnt/boot/efi/EFI/BOOT

cp /mnt/boot/loader.efi /mnt/boot/efi/EFI/BOOT/BOOTX64.efi
efibootmgr -a -c -l vtbd0p2:/EFI/BOOT/BOOTX64.efi -L FreeBSD

umount /mnt/boot/efi

gpart bootcode -b /mnt/boot/pmbr -p /mnt/boot/gptboot -i 1 nvd0
```

Festplatte 2:

``` sh
newfs_msdos /dev/gpt/efiesp1

mkdir -p /mnt/boot/efi

mount -t msdosfs /dev/gpt/efiesp1 /mnt/boot/efi

mkdir -p /mnt/boot/efi/EFI/BOOT

cp /mnt/boot/loader.efi /mnt/boot/efi/EFI/BOOT/BOOTX64.efi
efibootmgr -a -c -l vtbd0p2:/EFI/BOOT/BOOTX64.efi -L FreeBSD

umount /mnt/boot/efi

gpart bootcode -b /mnt/boot/pmbr -p /mnt/boot/gptboot -i 1 nvd1
```

## Vorbereiten der Chroot-Umgebung

Vor dem Wechsel in die Chroot-Umgebung müssen wir noch die `resolv.conf` in die Chroot-Umgebung kopieren und das Device-Filesysteme dorthin mounten.

``` sh
cat <<'EOF' > /etc/resolv.conf
--8<-- "freebsd/configs/etc/resolv.conf"
EOF

install -b -m 0644 /etc/resolv.conf /mnt/etc/resolv.conf

mount -t devfs devfs /mnt/dev
```

## Betreten der Chroot-Umgebung

Das neu installierte System selbstverständlich noch konfiguriert werden, bevor wir es nutzen können. Dazu werden wir jetzt in das neue System chrooten und eine minimale Grundkonfiguration vornehmen.

Beim Betreten der Chroot-Umgebung setzen wir mittels `/usr/bin/env -i` erstmal alle Environment-Variablen zurück. Andererseits benötigen wir aber die Environment-Variablen `HOME` und `TERM`, welche wir manuell auf sinnvolle Defaults setzen.

Wir bringen etwas Farbe in die Console, passen den Prompt an und legen `ee` statt `vi` als Default-Editor fest:

``` sh
cat <<'EOF' > /usr/share/skel/dot.cshrc
--8<-- "freebsd/configs/usr/share/skel/dot.cshrc"
EOF

cat <<'EOF' > /usr/share/skel/dot.login
--8<-- "freebsd/configs/usr/share/skel/dot.login"
EOF

cat <<'EOF' > /usr/share/skel/dot.mailrc
--8<-- "freebsd/configs/usr/share/skel/dot.mailrc"
EOF

cat <<'EOF' > /usr/share/skel/dot.profile
--8<-- "freebsd/configs/usr/share/skel/dot.profile"
EOF

cat <<'EOF' > /usr/share/skel/dot.shrc
--8<-- "freebsd/configs/usr/share/skel/dot.shrc"
EOF

cp /usr/share/skel/dot.cshrc /root/.cshrc
cp /usr/share/skel/dot.login /root/.login
cp /usr/share/skel/dot.mailrc /root/.mailrc
cp /usr/share/skel/dot.profile /root/.profile
cp /usr/share/skel/dot.shrc /root/.shrc

cp /usr/share/skel/dot.cshrc /mnt/root/.cshrc
cp /usr/share/skel/dot.login /mnt/root/.login
cp /usr/share/skel/dot.mailrc /mnt/root/.mailrc
cp /usr/share/skel/dot.profile /mnt/root/.profile
cp /usr/share/skel/dot.shrc /mnt/root/.shrc

chroot /mnt /usr/bin/env -i HOME=/root TERM=$TERM /bin/sh
```

!!! info
    Diese Shell nutzt das amerikanische Tastaturlayout, welches einige Tasten anders belegt als das deutsche Tastaturlayout.

    Um auf das deutsche Tastaturlayout zu wechseln, wählen wir mittels `kbdmap` das Layout "German (accent keys)" aus:

    ```shell
    /usr/sbin/kbdmap -K
    ```

Zunächst setzen wir die Systemzeit (CMOS clock) mittels `tzsetup` auf "UTC" (Universal Time Code).

``` sh
/usr/sbin/tzsetup UTC
```

Wir setzen ein paar Defaults für "root" neu:

``` sh
pw useradd -D -g '' -M 0700 -s sh -w no
```

Das Home-Verzeichnis des Users root ist standardmässig leider nicht ausreichend restriktiv in seinen Zugriffsrechten, was wir mit einem entsprechenden Aufruf von `chmod` schnell ändern. Bevor wir es vergessen, setzen wir bei dieser Gelegenheit gleich ein sicheres Passwort für root.

``` sh
mkdir -p /var/db/backups
mkdir -p /var/db/passwords

# Passwort erzeugen und speichern für den Systembenutzer "root"
install -b -m 0600 /dev/null /var/db/passwords/system_user_root
openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | \
  cut -c 2-17 | tee /var/db/passwords/system_user_root | \
  pw usermod -s sh -h 0 -n root

cat /var/db/passwords/system_user_root

chmod 700 /root
```

## Shell einrichten

Wir bringen etwas Farbe in die Console, passen den Prompt an und legen `ee` statt `vi` als Default-Editor fest:

``` sh
cat <<'EOF' > /usr/share/skel/dot.cshrc
--8<-- "freebsd/configs/usr/share/skel/dot.cshrc"
EOF

cat <<'EOF' > /usr/share/skel/dot.login
--8<-- "freebsd/configs/usr/share/skel/dot.login"
EOF

cat <<'EOF' > /usr/share/skel/dot.mailrc
--8<-- "freebsd/configs/usr/share/skel/dot.mailrc"
EOF

cat <<'EOF' > /usr/share/skel/dot.profile
--8<-- "freebsd/configs/usr/share/skel/dot.profile"
EOF

cat <<'EOF' > /usr/share/skel/dot.shrc
--8<-- "freebsd/configs/usr/share/skel/dot.shrc"
EOF

cp /usr/share/skel/dot.cshrc /root/.cshrc
cp /usr/share/skel/dot.login /root/.login
cp /usr/share/skel/dot.mailrc /root/.mailrc
cp /usr/share/skel/dot.profile /root/.profile
cp /usr/share/skel/dot.shrc /root/.shrc
```

## Systemsicherheit verstärken

Die hier vorgestellten Massnahmen sind äusserst simple Basics, die aus Hygienegründen auf jedem FreeBSD System selbstverständlich sein sollten. Um ein FreeBSD System richtig zu härten (Hardened), kommt man jedoch nicht an komplexeren Methoden wie Security Event Auditing und Mandatory Access Control vorbei. Diese Themen werden im FreeBSD Handbuch recht ausführlich besprochen; für den Einstieg empfehle ich hier die Lektüre von [Chapter 14. Security](https://docs.freebsd.org/en/books/handbook/security/){: target="_blank" rel="noopener"}, für die weiterführenden Themen die [Chapter 16. Mandatory Access Control](https://docs.freebsd.org/en/books/handbook/mac/){: target="_blank" rel="noopener"} und [Chapter 17. Security Event Auditing](https://docs.freebsd.org/en/books/handbook/audit/){: target="_blank" rel="noopener"}.

### OpenSSH konfigurieren

Da wir gerade ein Produktiv-System aufsetzen, werden wir den OpenSSH-Dienst recht restriktiv konfigurieren, unter Anderem werden wir den Login per Passwort verbieten und nur per PublicKey zulassen.

``` sh
cat <<'EOF' > /etc/ssh/sshd_config
--8<-- "freebsd/configs/etc/ssh/sshd_config"
EOF

rm -f /etc/ssh/ssh_host_*_key*
ssh-keygen -q -t rsa -b 4096 -f "/etc/ssh/ssh_host_rsa_key" -N ""
ssh-keygen -l -f "/etc/ssh/ssh_host_rsa_key.pub"
ssh-keygen -q -t ecdsa -b 384 -f "/etc/ssh/ssh_host_ecdsa_key" -N ""
ssh-keygen -l -f "/etc/ssh/ssh_host_ecdsa_key.pub"
ssh-keygen -q -t ed25519 -f "/etc/ssh/ssh_host_ed25519_key" -N ""
ssh-keygen -l -f "/etc/ssh/ssh_host_ed25519_key.pub"

mkdir -p /root/.ssh
chmod 700 /root/.ssh
ssh-keygen -t ed25519 -O clear -O permit-pty -f "/root/.ssh/id_ed25519" -N ""
cat /root/.ssh/id_ed25519.pub >> /root/.ssh/authorized_keys
ssh-keygen -t ecdsa -b 384 -O clear -O permit-pty -f "/root/.ssh/id_ecdsa" -N ""
cat /root/.ssh/id_ecdsa.pub >> /root/.ssh/authorized_keys
ssh-keygen -t rsa -b 4096 -O clear -O permit-pty -f "/root/.ssh/id_rsa" -N ""
cat /root/.ssh/id_rsa.pub >> /root/.ssh/authorized_keys
```

### Terminals absichern

Um zu verhindern, dass das System im Single User Mode ohne jeglichen Schutz benutzbar ist, ändern wir in der Datei `/etc/ttys` die Zeile `console none...` wie folgt ab.

``` text
sed -e "s|^console\([[:space:]].*[[:space:]]\)secure|console\1insecure|g" -i '' /etc/ttys
```

Dadurch wird die Eingabe des root-Kennworts erforderlich, um das System im Single User Mode booten zu können. Den Rest sollten wir hingegen unverändert lassen.

## System konfigurieren

Wir passen auch unsere Login-Begrüssung (motd) an.

``` sh
cat <<'EOF' > /etc/motd.template
--8<-- "freebsd/configs/etc/motd.template"
EOF
```

Die aliases-Datenbank für FreeBSDs DMA müssen wir mittels `newaliases` anlegen, auch wenn wir später DMA gar nicht verwenden möchten.

``` sh
cat <<'EOF' > /etc/mail/aliases
--8<-- "freebsd/configs/etc/mail/aliases"
EOF

newaliases
```

Die `/etc/nscd.conf` legen wir mit folgendem Inhalt an.

``` sh
cat <<'EOF' > /etc/nscd.conf
--8<-- "freebsd/configs/etc/nscd.conf"
EOF
```

Die `/etc/nsswitch.conf` legen wir mit folgendem Inhalt an.

``` sh
cat <<'EOF' > /etc/nsswitch.conf
--8<-- "freebsd/configs/etc/nsswitch.conf"
EOF
```

Die `/etc/ntp.conf` legen wir mit folgendem Inhalt an.

``` sh
cat <<'EOF' > /etc/ntp.conf
--8<-- "freebsd/configs/etc/ntp.conf"
EOF
```

Die `/etc/resolvconf.conf` legen wir mit folgendem Inhalt an.

``` sh
cat <<'EOF' > /etc/resolvconf.conf
--8<-- "freebsd/configs/etc/resolvconf.conf"
EOF

resolvconf -u
```

Die `/etc/periodic.conf` legen wir mit folgendem Inhalt an.

``` sh
cat <<'EOF' > /etc/periodic.conf
--8<-- "freebsd/configs/etc/periodic.conf"
EOF
```

Die `/etc/fstab` legen wir entsprechend unserem Partitionslayout an.

``` sh
cat <<'EOF' > /etc/fstab
--8<-- "freebsd/configs/etc/fstab_mirror"
EOF

mount -t fdescfs fdescfs /dev/fd
```

In der `/etc/rc.conf` werden diverse Grundeinstellungen für das System und die installierten Dienste vorgenommen.

``` sh
cat <<'EOF' > /etc/rc.conf
--8<-- "freebsd/configs/etc/rc.conf"
EOF
```

Es folgt ein wenig Voodoo, um die Netzwerkkonfiguration in der `/etc/rc.conf` zu vervollständigen.

``` sh
# 1. Get Default Interface
# Looks for the line starting with 'interface:' and captures the second column
DEF_IF="$(route -n get -inet default | awk '/interface:/ {print $2}')"
[ -n "$DEF_IF" ] && sed -e "s|DEFAULT|$DEF_IF|g" -i '' /etc/rc.conf

# 2. Get IPv4 Gateway and IP
# Captures the gateway IP from route output
GW4="$(route -n get -inet default | awk '/gateway:/ {print $2}')"
[ -n "$GW4" ] && sed -e "s|__GATEWAY4__|$GW4|g" -i '' /etc/rc.conf

# Captures the primary IPv4 address (excluding loopback)
# Uses -f cidr to ensure standard CIDR notation for parsing
IP4="$(ifconfig -u -f cidr "$DEF_IF" inet | awk '/inet / && $2 !~ /^127\./ {print $2}' | head -n 1)"
[ -n "$IP4" ] && sed -e "s|__IPADDR4__|$IP4|g" -i '' /etc/rc.conf

# 3. Get IPv6 Gateway and IP
# Captures the IPv6 gateway
GW6="$(route -n get -inet6 default | awk '/gateway:/ {print $2}')"
[ -n "$GW6" ] && sed -e "s|__GATEWAY6__|$GW6|g" -i '' /etc/rc.conf

# Captures the primary Global IPv6 address
# Filters out link-local (fe80::) and loopback (::1) addresses
IP6="$(ifconfig -u -f cidr "$DEF_IF" inet6 | awk '/inet6 / && $2 !~ /^fe80:/ && $2 !~ /^::1/ {print $2}' | head -n 1)"
[ -n "$IP6" ] && sed -e "s|__IPADDR6__|$IP6|g" -i '' /etc/rc.conf
```

Wir richten die `/etc/hosts` ein.

``` sh
sed -e "s|my.domain/example.com/g" -i '' /etc/hosts
echo '__IPADDR4__   devnull.example.com   devnull' >> /etc/hosts
echo '__IPADDR6__   devnull.example.com   devnull' >> /etc/hosts

# 1. Get Default Interface
# Looks for the line starting with 'interface:' and captures the second column
DEF_IF="$(route -n get -inet default | awk '/interface:/ {print $2}')"

# 2. Get IPv4 IP
# Captures the primary IPv4 address (excluding loopback)
IP4="$(ifconfig "$DEF_IF" inet | awk '/inet / && $2 !~ /^127\./ {print $2}' | head -n 1)"
[ -n "$IP4" ] && sed -e "s|__IPADDR4__|$IP4|g" -i '' /etc/hosts

# 3. Get IPv6 IP
# Captures the primary Global IPv6 address
# Filters out link-local (fe80::) and loopback (::1) addresses
IP6="$(ifconfig "$DEF_IF" inet6 | awk '/inet6 / && $2 !~ /^fe80:/ && $2 !~ /^::1/ {print $2}' | head -n 1)"
[ -n "$IP6" ] && sed -e "s|__IPADDR6__|$IP6|g" -i '' /etc/hosts
```

### Systemgruppen anlegen

Zur besseren Trennung beziehungsweise Gruppierung unterschiedlicher Nutzungszwecke legen wir ein paar Gruppen an (admin für rein administrative Nutzer, users für normale Nutzer, sshusers für Nutzer mit SSH-Zugang und sftponly für reine SFTP-Nutzer).

``` sh
pw groupshow admin >/dev/null 2>&1 || pw groupadd -n admin -g 1000
pw groupshow users >/dev/null 2>&1 || pw groupadd -n users -g 2000
pw groupshow sshusers >/dev/null 2>&1 || pw groupadd -n sshusers -g 3000
pw groupshow sftponly >/dev/null 2>&1 || pw groupadd -n sftponly -g 4000
```

### Systembenutzer anlegen

Um nicht ständig mit dem root-User arbeiten zu müssen, legen wir uns einen Administrations-User an, den wir praktischerweise "admin" nennen. Diesem User verpassen wir die Standard-Systemgruppe "admin" und nehmen ihn zusätzlich in die Systemgruppe "wheel" auf, damit dieser User später per `su` zum root-User wechseln kann. Das Home-Verzeichnis des admin-Users lassen wir automatisch anlegen und setzen seine Standard-Shell auf `/bin/tcsh`. Ein sicheres Passwort bekommt er selbstverständlich auch noch.

``` sh
# Passwort erzeugen und speichern für den Systembenutzer "admin"
id -u admin >/dev/null 2>&1 || \
  install -b -m 0600 /dev/null /var/db/passwords/system_user_admin
id -u admin >/dev/null 2>&1 || \
  openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | \
    cut -c 2-17 | tee /var/db/passwords/system_user_admin | \
    pw useradd -h 0 -n admin -u 1000 -g admin -c 'Administrator' -m

cat /var/db/passwords/system_user_admin
```

Wir richten unserem `admin` noch die Shell und die zum zukünftigen Einloggen zwingend nötigten SSH-Keys ein.

``` sh
su - admin

mkdir -p .ssh
chmod 700 .ssh

ssh-keygen -t ed25519 -O clear -O permit-pty -f ".ssh/id_ed25519" -N ""
cat .ssh/id_ed25519.pub >> .ssh/authorized_keys
ssh-keygen -t ecdsa -b 384 -O clear -O permit-pty -f ".ssh/id_ecdsa" -N ""
cat .ssh/id_ecdsa.pub >> .ssh/authorized_keys
ssh-keygen -t rsa -b 4096 -O clear -O permit-pty -f ".ssh/id_rsa" -N ""
cat .ssh/id_rsa.pub >> .ssh/authorized_keys

exit
```

Einen normalen User mit SSH-Zugang legen wir ebenfalls an, ihn nennen wir "joeuser". Diesem User verpassen wir die Standard-Systemgruppe "users" und nehmen ihn zusätzlich in die Systemgruppe "sshusers" auf, damit sich dieser User später per `SSH` einloggen kann. Das Home-Verzeichnis des Users lassen wir automatisch anlegen und setzen seine Standard-Shell auf `/bin/tcsh`. Ein sicheres Passwort bekommt er selbstverständlich auch noch.

``` sh
# Passwort erzeugen und speichern für den Systembenutzer "joeuser"
id -u joeuser >/dev/null 2>&1 || \
  install -b -m 0600 /dev/null /var/db/passwords/system_user_joeuser
id -u joeuser >/dev/null 2>&1 || \
  openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | \
    cut -c 2-17 | tee /var/db/passwords/system_user_joeuser | \
    pw useradd -h 0 -n joeuser -u 2000 -g users -G sshusers -c 'Joe User' -m

cat /var/db/passwords/system_user_joeuser
```

Wir richten unserem `joeuser` noch die Shell und die zum zukünftigen Einloggen zwingend nötigten SSH-Keys ein.

``` sh
su - joeuser

mkdir -p .ssh
chmod 700 .ssh

ssh-keygen -t ed25519 -O clear -O permit-pty -f ".ssh/id_ed25519" -N ""
cat .ssh/id_ed25519.pub >> .ssh/authorized_keys
ssh-keygen -t ecdsa -b 384 -O clear -O permit-pty -f ".ssh/id_ecdsa" -N ""
cat .ssh/id_ecdsa.pub >> .ssh/authorized_keys
ssh-keygen -t rsa -b 4096 -O clear -O permit-pty -f ".ssh/id_rsa" -N ""
cat .ssh/id_rsa.pub >> .ssh/authorized_keys

exit
```

## Cronjobs konfigurieren

Cronjobs zur regelmässigen Syncronisation mit einem Zeitserver einrichten.

``` sh
cat <<'EOF' > /etc/crontab
--8<-- "freebsd/configs/etc/crontab"
EOF
```

## Buildsystem konfigurieren

``` sh
cat <<'EOF' > /etc/make.conf
--8<-- "freebsd/configs/etc/make.conf"
EOF

cat <<'EOF' > /etc/src.conf
--8<-- "freebsd/configs/etc/src.conf"
EOF
```

### /etc/sysctl.conf anpassen

In der `sysctl.conf` können die meisten Kernel-Parameter verändert werden. Wir wollen dies nutzen, um unser System etwas robuster und sicherer zu machen.

``` sh
cat <<'EOF' > /etc/sysctl.conf
--8<-- "freebsd/configs/etc/sysctl.conf"
EOF
```

## Kernel konfigurieren

Kernel Parameter in `/boot/loader.conf` setzen.

``` sh
cat <<'EOF' > /boot/loader.conf
--8<-- "freebsd/configs/boot/loader.conf"
EOF
```

## Packet Filter (PF) einrichten

``` sh
touch /etc/pf.badhosts_drop
touch /etc/pf.badhosts_torp
touch /etc/pf.badhosts_misc
touch /etc/pf.badhosts_scan
touch /etc/pf.badhosts_dns
touch /etc/pf.badhosts_ntpd
touch /etc/pf.badhosts_sshd
touch /etc/pf.badhosts_mail
touch /etc/pf.badhosts_http
touch /etc/pf.badhosts_priv

cat <<'EOF' > /etc/pf.conf
--8<-- "freebsd/configs/etc/pf.conf"
EOF

cat <<'EOF' > /etc/pf.internal
--8<-- "snippets/configs/etc/pf.internal"
EOF

# 1. Get Default Interface
# Looks for the line starting with 'interface:' and captures the second column
DEF_IF="$(route -n get -inet default | awk '/interface:/ {print $2}')"
[ -n "$DEF_IF" ] && sed -e "s|__EXTERNAL__|$DEF_IF|g" -i '' /etc/pf.conf

# 2. Get IPv4 IP
# Captures the primary IPv4 address (excluding loopback)
IP4="$(ifconfig -u -f cidr "$DEF_IF" inet | awk '/inet / && $2 !~ /^127\./ {print $2}' | head -n 1)"
[ -n "$IP4" ] && sed -e "s|__IPADDR4__|$IP4|g" -i '' /etc/pf.internal

# 3. Get IPv6 IP
# Captures the primary Global IPv6 address
# Filters out link-local (fe80::) and loopback (::1) addresses
IP6="$(ifconfig -u -f cidr "$DEF_IF" inet6 | awk '/inet6 / && $2 !~ /^fe80:/ && $2 !~ /^::1/ {print $2}' | head -n 1)"
[ -n "$IP6" ] && sed -e "s|__IPADDR6__|$IP6|g" -i '' /etc/pf.internal
```

## Abschluss der Installation

Um uns künftig mit unserem Arbeitsuser einloggen zu können, müssen wir uns dessen SSH-Key (id_ed25519) auf unser lokales System kopieren und ihn dann mit Hilfe der [PuTTYgen Dokumentation](https://the.earth.li/~sgtatham/putty/latest/htmldoc/Chapter8.html){: target="_blank" rel="noopener"} in einen für PuTTY lesbaren Private Key umwandeln (id_ed25519.ppk).

``` powershell
pscp -P 2222 -r root@127.0.0.1:/mnt/home/admin/.ssh "${Env:USERPROFILE}\VirtualBox VMs\FreeBSD\ssh"

puttygen "${Env:USERPROFILE}\VirtualBox VMs\FreeBSD\ssh\id_ed25519"
```

Nun ist es endlich soweit: Wir verlassen das Chroot, unmounten die Partitionen und rebooten zum ersten Mal in unser neues FreeBSD Basis-System.

``` sh
exit

rm /mnt/root/.sh_history*
rm /mnt/home/*/.sh_history*

umount /mnt/dev
umount /mnt

shutdown -r now
```

### Einloggen und zu `root` werden

Einloggen ab hier nur noch mit Public-Key

``` powershell
putty -ssh -P 2222 -i "${Env:USERPROFILE}\VirtualBox VMs\FreeBSD\ssh\id_ed25519.ppk" admin@127.0.0.1
```

``` sh
su - root
```

## System aktualisieren

Nach dem Reboot aktualisieren und entschlacken wir das System.

### ports-mgmt/pkg installieren

Wir installieren als Erstes `pkg`.

``` sh
sed -e "s|quarterly|latest|g" -i '' /etc/pkg/FreeBSD.conf

pkg bootstrap -y
```

### Git installieren

Wir installieren als Nächstes `git` und seine Abhängigkeiten.

``` sh
pkg install -y devel/git@tiny
```

### Source Tree auschecken

Am Besten funktioniert bei FreeBSD immer noch die Aktualisierung über die System-Sourcen. Auf diesem Wege kann man ein System über viele Release-Generationen hinweg aktuell halten, ohne eine Neuinstallation durchzuführen. Das Verfahren ist zwar etwas zeitaufwändig, aber erprobt und führt bei richtiger Anwendung zu einem sauberen, aktuellen System.

Zunächst wird hierzu das aktuelle Quellenverzeichnis von FreeBSD benötigt, weshalb wir es mittels [git](https://www.freebsd.org/cgi/man.cgi?query=git){: target="_blank" rel="noopener"} auschecken.

``` sh
# Neues Quellenverzeichnis anlegen (clone)
rm -r /usr/src
git clone -o freebsd -b releng/`/bin/freebsd-version -u | cut -d- -f1` https://git.FreeBSD.org/src.git /usr/src
git -C /usr/src pull --rebase
etcupdate extract
etcupdate diff

# Vorhandenes Quellenverzeichnis aktualisieren (pull)
git -C /usr/src pull --rebase


# Vorhandenes Quellenverzeichnis zu FreeBSD 15-STABLE wechseln (checkout)
git -C /usr/src checkout stable/15
etcupdate extract
etcupdate diff
```

### Portstree auschecken

Um unser Basissystem später um sinnvolle Programme erweitern zu können, fehlt uns noch der sogenannte Portstree. Diesen checken wir nun ebenfalls mittels `git` aus (kann durchaus eine Stunde oder länger dauern).

``` sh
rm -r /usr/ports
git clone --depth 1 https://git.FreeBSD.org/ports.git /usr/ports
git -C /usr/ports pull --rebase
make -C /usr/ports fetchindex
```

Damit ist der Portstree einsatzbereit. Um den Tree künftig zu aktualisieren genügt der folgende Befehl.

``` sh
git -C /usr/ports pull --rebase
make -C /usr/ports fetchindex
```

Wichtige Informationen zu neuen Paketversionen finden sich in `/usr/ports/UPDATING` und sollten dringend beachtet werden.

``` sh
less /usr/ports/UPDATING
```

### Git deinstallieren

Wir deinstallieren `git` und seine Abhängigkeiten nun vorerst wieder.

``` sh
pkg delete -y -a
pkg clean -y -a
pkg delete -y -f \*
```

### Konfiguration anpassen

In den Abschnitten [Buildsystem konfigurieren](#buildsystem-konfigurieren) und [Kernel konfigurieren](#kernel-konfigurieren) haben wir uns bereits eine geeignete `make.conf` und gegebenenfalls auch eine individuelle Kernel-Konfiguration erstellt. Dennoch sei an dieser Stelle nochmals auf das FreeBSD Handbuch verwiesen. Insbesondere [Chapter 8. Configuring the FreeBSD Kernel](https://docs.freebsd.org/en/books/handbook/kernelconfig/){: target="_blank" rel="noopener"} und [24.6. Updating FreeBSD from Source](https://docs.freebsd.org/en/books/handbook/cutting-edge/#makeworld){: target="_blank" rel="noopener"} seien Jedem FreeBSD Administratoren ans Herz gelegt.

Ausserdem empfiehlt es sich vor einem Update des Basissystems die Datei `/usr/src/UPDATING` zu lesen. Alle Angaben und Hinweise in dieser Datei sind aktueller und zutreffender als das Handbuch und sollten unbedingt befolgt werden.

### Vorbereitende Arbeiten

!!! info
    Für die spätere Installation des neu kompilierten Basissystems darf `/tmp` nicht mit der Option `noexec` gemounted sein. Da zwischendrin noch mal ein Reboot erfolgt, können wir bei Bedarf bereits jetzt die entsprechende Zeile in der `fstab` anpassen, sofern vorhanden.

Zunächst müssen eventuell vorhandene Object-Dateien im Verzeichnis `/usr/obj` gelöscht werden, damit `make` später wirklich das gesamte System neu erstellt.

``` sh
cd /usr/src

make cleanuniverse

git -C /usr/src pull --rebase
```

### Basissystem rekompilieren

Das Kompilieren des Basissystems kann durchaus eine Stunde oder länger dauern.

``` sh
make -j`sysctl -n hw.ncpu | awk '{print int($1 / 4 * 3)}'` buildworld
```

### Kernel rekompilieren und installieren

Wenn die eigene Kernel-Konfiguration wie bei uns bereits in der `/etc/make.conf` eingetragen ist, wird sie automatisch verwendet, andernfalls wird die Konfiguration des generischen FreeBSD-Kernels verwendet. Das Kompilieren des Kernels kann durchaus eine Stunde oder länger dauern.

``` sh
mkdir -p /root/kernels

cat <<'EOF' > /root/kernels/MYKERNEL
include         GENERIC
ident           MYKERNEL
EOF

ln -s /root/kernels/MYKERNEL /usr/src/sys/amd64/conf/
ln -s /root/kernels/MYKERNEL /usr/src/sys/arm64/conf/

make -j`sysctl -n hw.ncpu | awk '{print int / 4 * 3}'` KERNCONF=GENERIC INSTALLKERNEL=GENERIC INSTKERNNAME=GENERIC kernel
make -j`sysctl -n hw.ncpu | awk '{print int / 4 * 3}'` KERNCONF=MYKERNEL INSTALLKERNEL=MYKERNEL INSTKERNNAME=MYKERNEL kernel

sed -e 's/^#*\(kernels=\).*$/\1"MYKERNEL GENERIC"/' -i '' /boot/loader.conf
sed -e 's/^#*\(kernel=\).*$/\1"MYKERNEL"/' -i '' /boot/loader.conf

rm -r /boot/kernel /boot/kernel.old
```

Normalerweise wäre nun ein Reboot in den Single User Mode an der Reihe. Da sich ein Remote-System in diesem Modus ohne KVM-Lösung aber nicht bedienen lässt, begnügen wir uns damit, das System regulär neu zu starten.

``` sh
shutdown -r now
```

Wenn wir unser System zu einem späteren Zeitpunkt nochmals aktualisieren, sollten wir zuden zuvor alle Dienste ausser OpenSSH, sowie sämtliche Jails in der Datei `/etc/rc.conf` deaktivieren.

Einloggen und zu `root` werden

``` powershell
putty -ssh -P 2222 -i "${Env:USERPROFILE}\VirtualBox VMs\FreeBSD\ssh\id_ed25519.ppk" admin@127.0.0.1
```

``` sh
su - root
```

### Basissystem installieren

Wir installieren das neue Basissystem.

Ausserdem sollte [etcupdate](https://www.freebsd.org/cgi/man.cgi?query=etcupdate){: target="_blank" rel="noopener"} im Pre-Build-Mode angeworfen werden, damit es während der Aktualisierung nicht zu Fehlern kommt, weil z. B. bestimmte User oder Gruppen noch nicht vorhanden sind.

``` sh
cd /usr/src

etcupdate -p

make installworld
```

Als letzten Schritt müssen nun noch die Neuerungen in den Konfigurationsdateien gemerged werden. Dabei unterstützt uns das Tool `etcupdate`. Wir müssen selbstverständlich darauf achten, dass wir hierbei nicht versehentlich unsere zuvor gemachten Anpassungen an den diversen Konfigurationsdateien wieder rückgängig machen.

``` sh
etcupdate -B
```

Wir entsorgen nun noch eventuell vorhandene veraltete und überflüssige Dateien.

``` sh
make BATCH_DELETE_OLD_FILES=yes delete-old-files
make BATCH_DELETE_OLD_FILES=yes delete-old-libs
make BATCH_DELETE_OLD_FILES=yes delete-old-dirs
make BATCH_DELETE_OLD_FILES=yes delete-old
```

Anschliessend müssen wir noch die für die Installation gegebenenfalls vorgenommenen Änderungen in der `fstab` sowie `rc.conf` rückgängig machen und das System nochmals durchstarten.

``` sh
shutdown -r now
```

Viel Spass mit dem neuen FreeBSD BaseSystem.

## Referenzen
