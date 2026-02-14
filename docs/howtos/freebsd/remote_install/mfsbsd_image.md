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
lastmod: '2025-06-28'
title: mfsBSD Image
description: In diesem HowTo wird step-by-step die Erstellung eines mfsBSD Images zur Remote Installation von FreeBSD 64Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# mfsBSD Image

## Einleitung

In diesem HowTo beschreibe ich step-by-step das Erstellen eines [mfsBSD](https://mfsbsd.vx.sk/) Images mit dem die [Remote Installation](requirements.md) von [FreeBSD 64Bit](https://www.freebsd.org/){: target="\_blank" rel="noopener"} auf einem dedizierten Server durchgeführt werden kann.

## Das Referenzsystem

Als Referenzsystem für dieses HowTo habe ich mich für eine virtuelle Maschine auf Basis von [Oracle VirtualBox](https://www.virtualbox.org/){: target="\_blank" rel="noopener"} unter [Microsoft Windows 11 Pro (64Bit)](https://www.microsoft.com/en-us/windows/windows-11){: target="\_blank" rel="noopener"} entschieden. So lässt sich ohne grösseren Aufwand ein handelsüblicher dedizierter Server simulieren und anschliessend kann diese virtuelle Maschine als kostengünstiges lokales Testsystem weiter genutzt werden.

Trotzdem habe ich dieses HowTo so ausgelegt, dass es sich nahezu unverändert auf handelsübliche dedizierte Server übertragen lässt und dieses auch auf mehreren dedizierten Servern getestet.

Obwohl Microsoft Windows 11 Pro einen eigenen OpenSSH-Client mitbringt, greife ich lieber auf das sehr empfehlenswerte [PuTTY (64Bit)](https://www.chiark.greenend.org.uk/~sgtatham/putty/){: target="\_blank" rel="noopener"} zurück.

VirtualBox (inklusive dem Extensionpack) und PuTTY werden mit den jeweiligen Standardoptionen installiert.

``` powershell
winget install PuTTY.PuTTY
winget install Oracle.VirtualBox

$Env:vbox_ver=((winget show Oracle.VirtualBox) -match '^Version:' -split '\s+' | Select-Object -Last 1)
curl -o "Oracle_VM_VirtualBox_Extension_Pack-${Env:vbox_ver}.vbox-extpack" -L "https://download.virtualbox.org/virtualbox/${Env:vbox_ver}/Oracle_VirtualBox_Extension_Pack-${Env:vbox_ver}.vbox-extpack"
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" extpack install --replace Oracle_VirtualBox_Extension_Pack-${Env:vbox_ver}.vbox-extpack
rm Oracle_VirtualBox_Extension_Pack-${Env:vbox_ver}.vbox-extpack
$Env:vbox_ver=''
```

## Die Virtuelle Maschine

Als Erstes öffnen wir eine neue PowerShell und legen manuell eine neue virtuelle Maschine an. Diese virtuelle Maschine bekommt den Namen `mfsBSD` und wird mit einer UEFI-Firmware, einem Quad-Core Prozessor, Intels ICH9-Chipsatz, 8192MB RAM, 64MB VideoRAM, einer 64GB SSD-Festplatte, einem DVD-Player, einer Netzwerkkarte, einem NVMe-Controller sowie einem AHCI-Controller und einem TPM 2.0 ausgestattet. Zudem setzen wir die RTC (Real-Time Clock) der virtuellen Maschine auf UTC (Coordinated Universal Time), aktivieren den HPET (High Precision Event Timer) und legen die Bootreihenfolge fest.

``` powershell
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" createvm --name "mfsBSD" --ostype FreeBSD_64 --register

cd "${Env:USERPROFILE}\VirtualBox VMs\mfsBSD"

& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" createmedium disk --filename "mfsBSD1.vdi" --format VDI --size 64536

& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" modifyvm "mfsBSD" --firmware efi --memory 8192 --vram 64 --cpus 4 --hpet on --hwvirtex on --chipset ICH9 --iommu automatic --tpm-type 2.0 --rtc-use-utc on
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" modifyvm "mfsBSD" --cpu-profile host --apic on --ioapic on --x2apic on --pae on --long-mode on --nested-paging on --large-pages on --vtx-vpid on --vtx-ux on
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" modifyvm "mfsBSD" --nic1 nat --nic-type1 virtio --nat-pf1 "VBoxSSH,tcp,,2222,,22" --nat-pf1 "VBoxHTTP,tcp,,8080,,80" --nat-pf1 "VBoxHTTPS,tcp,,8443,,443"
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" modifyvm "mfsBSD" --graphicscontroller vmsvga --audio-enabled off --usb-ehci off --usb-ohci off --usb-xhci off --boot1 dvd --boot2 disk --boot3 none --boot4 none

& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" storagectl "mfsBSD" --name "NVMe Controller" --add pcie --controller NVMe --portcount 4 --bootable on --hostiocache off
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" storagectl "mfsBSD" --name "AHCI Controller" --add sata --controller IntelAHCI --portcount 4 --bootable on --hostiocache off

& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" storageattach "mfsBSD" --storagectl "NVMe Controller" --port 0 --device 0 --type hdd --nonrotational on --medium "mfsBSD1.vdi"
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" storageattach "mfsBSD" --storagectl "AHCI Controller" --port 0 --device 0 --type dvddrive --medium emptydrive
```

Als nächstes benötigen wir die FreeBSD 64Bit Installations-CD, welche wir mittels des mit Windows mitgelieferten cURL-Client herunterladen und unserer virtuellen Maschine als Bootmedium zuweisen.

``` powershell
cd "${Env:USERPROFILE}\VirtualBox VMs\mfsBSD"

curl -o "FreeBSD-14.3-RELEASE-amd64-disc1.iso" -L "https://download.freebsd.org/releases/ISO-IMAGES/14.3/FreeBSD-14.3-RELEASE-amd64-disc1.iso"

& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" storageattach "mfsBSD" --storagectl "AHCI Controller" --port 0 --device 0 --type dvddrive --medium "FreeBSD-14.3-RELEASE-amd64-disc1.iso"
```

Nachdem die virtuelle Maschine nun fertig konfiguriert ist, wird es Zeit diese zu booten.

``` powershell
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" startvm "mfsBSD"
```

Im Bootmenü wird die erste Option durch Drücken der Taste "Enter" beziehungsweise "Return", oder automatisch nach 10 Sekunden gewählt.

Es würde für unsere Zwecke durchaus genügen, einfach stumpf dem Installationsprogramm BSDInstall zu folgen, aber wir werden die Installation manuell durchführen, um ein paar Optionen zu nutzen, welche mit BSDInstall derzeit nicht verfügbar sind.

Aus diesem Grund werden wir, wenn der Bootvorgang abgeschlossen ist und wir den ersten Auswahldialog präsentiert bekommmen, "Shell" auswählen und bestätigen.

!!! info
    Diese Shell nutzt das amerikanische Tastaturlayout, welches einige Tasten anders belegt als das deutsche Tastaturlayout.

    Um auf das deutsche Tastaturlayout zu wechseln, wählen wir mittels `kbdmap` das Layout "German (accent keys)" aus:

    ```shell
    /usr/sbin/kbdmap -K
    ```

## Minimalsystem installieren

Als Erstes müssen wir die Festplatte partitionieren, was wir mittels `gpart` erledigen werden. Zuvor müssen wir dies aber dem Kernel mittels `sysctl` mitteilen, da er uns andernfalls dazwischenfunken würde.

Wir werden vier Partitionen anlegen, die Erste für den GPT-Bootcode, die Zweite für den EFI-Bootcode, die Dritte als Swap und die Vierte als Systempartition. Dabei werden wir die Partitionen auch gleich für modernere Festplatten mit 4K-Sektoren optimieren und statt den veralteten "MBR Partition Tables" die aktuelleren "GUID Partition Tables (GPT)" verwenden.

``` shell
sysctl kern.geom.debugflags=0x10

gpart create -s gpt nvd0

gpart add -t freebsd-boot  -b      40 -s     216 -l bootfs nvd0
gpart add -t efi           -b     256 -s    3840 -l efiesp nvd0
gpart add -t freebsd-swap  -b    4096 -s 8388608 -l swapfs nvd0
gpart add -t freebsd-ufs   -b 8392704            -l rootfs nvd0

gpart set -a bootme -i 4 nvd0
```

Nun müssen wir noch die Systempartition mit "UFS2" und einer 4K-Blockgrösse formatieren und aktivieren auch gleich die "soft-updates".

``` shell
newfs -U -l -t /dev/gpt/rootfs
```

Die Systempartition mounten wir nach `/mnt` und entpacken darauf ein FreeBSD-Minimalsystem mit dem wir problemlos weiterarbeiten können.

``` shell
mount -t ufs /dev/gpt/rootfs /mnt

tar Jxpvf /usr/freebsd-dist/base.txz   -C /mnt/
tar Jxpvf /usr/freebsd-dist/kernel.txz -C /mnt/
tar Jxpvf /usr/freebsd-dist/lib32.txz  -C /mnt/
tar Jxpvf /usr/freebsd-dist/src.txz    -C /mnt/

cp -a /usr/freebsd-dist /mnt/usr/
```

Unser System soll natürlich auch von der Festplatte booten können, weshalb wir jetzt den Bootcode und Bootloader in der Bootpartittion installieren.

``` shell
newfs_msdos /dev/gpt/efiesp

mount -t msdosfs /dev/gpt/efiesp /mnt/boot/efi

mkdir -p /mnt/boot/efi/EFI/BOOT
cp /mnt/boot/loader.efi /mnt/boot/efi/EFI/BOOT/BOOTX64.efi
efibootmgr -a -c -l vtbd0p2:/EFI/BOOT/BOOTX64.efi -L FreeBSD
umount /mnt/boot/efi

gpart bootcode -b /mnt/boot/pmbr -p /mnt/boot/gptboot -i 1 nvd0
```

Vor dem Wechsel in die Chroot-Umgebung müssen wir noch die `resolv.conf` erstellen und in die Chroot-Umgebung kopieren und das Device-Filesysteme dorthin mounten.

``` shell
cat <<'EOF' > /etc/resolv.conf
--8<-- "freebsd/configs/etc/resolv.conf"
EOF

cp /etc/resolv.conf /mnt/etc/resolv.conf

mount -t devfs devfs /mnt/dev
```

Das neu installierte System selbstverständlich noch konfiguriert werden, bevor wir es nutzen können. Dazu werden wir jetzt in das neue System chrooten und eine minimale Grundkonfiguration vornehmen.

``` shell
chroot /mnt /usr/bin/env -i HOME=/root TERM=$TERM /bin/tcsh
```

Zunächst setzen wir die Systemzeit (CMOS clock) mittels `tzsetup` auf "UTC" (Universal Time Code).

``` shell
/usr/sbin/tzsetup UTC
```

Wir bringen etwas Farbe in die Console, passen den Prompt an und legen `ee` statt `vi` als Default-Editor fest:

``` shell
cat <<'EOF' > /usr/share/skel/dot.cshrc
--8<-- "freebsd/configs/usr/share/skel/dot.cshrc"
EOF

cat <<'EOF' > /usr/share/skel/dot.shrc
--8<-- "freebsd/configs/usr/share/skel/dot.shrc"
EOF

cat <<'EOF' > /usr/share/skel/dot.mailrc
--8<-- "freebsd/configs/usr/share/skel/dot.mailrc"
EOF

cat <<'EOF' > /usr/share/skel/dot.profile
--8<-- "freebsd/configs/usr/share/skel/dot.profile"
EOF
```

Jetzt nochmal für `root`:

``` shell
cat <<'EOF' > /root/.cshrc
--8<-- "freebsd/configs/usr/share/skel/dot.cshrc"
EOF

cat <<'EOF' > /root/.shrc
--8<-- "freebsd/configs/usr/share/skel/dot.shrc"
EOF

cat <<'EOF' > /root/.mailrc
--8<-- "freebsd/configs/usr/share/skel/dot.mailrc"
EOF

cat <<'EOF' > /root/.profile
--8<-- "freebsd/configs/usr/share/skel/dot.profile"
EOF
```

Wir setzen ein paar Defaults für "root" neu:

``` shell
pw useradd -D -g '' -M 0700 -s sh -w no
pw usermod -n root -s sh -w none
```

Das Home-Verzeichnis des Users root ist standardmässig leider nicht ausreichend restriktiv in seinen Zugriffsrechten, was wir mit einem entsprechenden Aufruf von `chmod` schnell ändern. Bevor wir es vergessen, setzen wir bei dieser Gelegenheit gleich ein sicheres Passwort für root.

``` shell
chmod 0700 /root

# Password erzeugen und in /root/_passwords speichern
chmod 0600 /root/_passwords
newpw="`openssl rand -hex 64 | openssl passwd -5 -stdin | tr -cd '[[:print:]]' | cut -c 2-17`"
echo "Password for systemuser root: $newpw" >> /root/_passwords
chmod 0400 /root/_passwords
echo "Password: $newpw"
unset newpw

passwd root
```

Die aliases-Datenbank für FreeBSDs DMA müssen wir mittels `newaliases` anlegen, auch wenn wir später DMA gar nicht verwenden möchten.

``` shell
cat <<'EOF' > /etc/mail/aliases
--8<-- "freebsd/configs/etc/mail/aliases"
EOF

newaliases
```

Die `fstab` ist bei unserem minimalistischen Partitionslayout zwar nicht zwingend nötig, aber wir möchten später keine unerwarteten Überraschungen erleben, also legen wir sie vorsichtshalber an.

``` shell
cat <<'EOF' > /etc/fstab
--8<-- "freebsd/configs/etc/fstab"
EOF
```

In der `rc.conf` werden diverse Grundeinstellungen für das System und die installierten Dienste vorgenommen. Wir legen sie daher mittela `ee /etc/rc.conf` mit folgendem Inhalt an.

``` shell
cat <<'EOF' > /etc/rc.conf
--8<-- "freebsd/configs/etc/rc.conf"
EOF
```

Es folgt ein wenig Voodoo, um die Netzwerkkonfiguration in der `/etc/rc.conf` zu vervollständigen.

``` shell
# Default Interface
route -n get -inet default | awk '/interface/ {print $2}' | \
    xargs -I % sed -e 's|DEFAULT|%|g' -i '' /etc/rc.conf

# IPv4
route -n get -inet default | awk '/gateway/ {print $2}' | \
    xargs -I % sed -e 's|__GATEWAY4__|%|g' -i '' /etc/rc.conf
ifconfig -u -f cidr `route -n get -inet default | awk '/interface/ {print $2}'` inet | \
    awk 'tolower($0) ~ /inet[\ \t]+((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/ {if(substr($2,1,3)!=127) print $2}' | \
    head -n 1 | xargs -I % sed -e 's|__IPADDR4__|%|g' -i '' /etc/rc.conf

# IPv6
route -n get -inet6 default | awk '/gateway/ {print $2}' | \
    xargs -I % sed -e 's|__GATEWAY6__|%|g' -i '' /etc/rc.conf
ifconfig -u -f cidr `route -n get -inet6 default | awk '/interface/ {print $2}'` inet6 | \
    awk 'tolower($0) ~ /inet6[\ \t]+(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/ {if(substr($2,1,1)!="f") print $2}' | \
    head -n 1 | xargs -I % sed -e 's|__IPADDR6__|%|g' -i '' /etc/rc.conf
```

Wir richten die `/etc/hosts` ein.

``` shell
# localhost
sed -e 's|my.domain/example.com/g' -i '' /etc/hosts

# IPv4
echo '__IPADDR4__   devnull.example.com   devnull' >> /etc/hosts
ifconfig -u -f cidr `route -n get -inet default | awk '/interface/ {print $2}'` inet | \
    awk 'tolower($0) ~ /inet[\ \t]+((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/ {if(substr($2,1,3)!=127) print $2}' | \
    head -n 1 | xargs -I % sed -e 's|__IPADDR4__|%|g' -i '' /etc/hosts

# IPv6
echo '__IPADDR6__   devnull.example.com   devnull' >> /etc/hosts
ifconfig -u -f cidr `route -n get -inet6 default | awk '/interface/ {print $2}'` inet6 | \
    awk 'tolower($0) ~ /inet6[\ \t]+(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))/ {if(substr($2,1,1)!="f") print $2}' | \
    head -n 1 | xargs -I % sed -e 's|__IPADDR6__|%|g' -i '' /etc/hosts
```

Da dies lediglich ein lokales temporäres System zum Erzeugen unseres mfsBSD-Images wird, können wir den OpenSSH-Dienst bedenkenlos etwas komfortabler aber dadurch zwangsläufig auch etwas unsicherer konfigurieren, indem wir den Login per Passwort zulassen.

``` shell
cat <<'EOF' > /etc/ssh/sshd_config
{{~ include "snippets/configs/etc/ssh/sshd_config"
EOF

rm -f /etc/ssh/ssh_host_*_key*
ssh-keygen -q -t rsa -b 4096 -f "/etc/ssh/ssh_host_rsa_key" -N ""
ssh-keygen -l -f "/etc/ssh/ssh_host_rsa_key.pub"
ssh-keygen -q -t ecdsa -b 384 -f "/etc/ssh/ssh_host_ecdsa_key" -N ""
ssh-keygen -l -f "/etc/ssh/ssh_host_ecdsa_key.pub"
ssh-keygen -q -t ed25519 -f "/etc/ssh/ssh_host_ed25519_key" -N ""
ssh-keygen -l -f "/etc/ssh/ssh_host_ed25519_key.pub"

mkdir -p /root/.ssh
chmod 0700 /root/.ssh
ssh-keygen -t ed25519 -O clear -O permit-pty -f "/root/.ssh/id_ed25519" -N ""
cat /root/.ssh/id_ed25519.pub >> /root/.ssh/authorized_keys
ssh-keygen -t ecdsa -b 384 -O clear -O permit-pty -f "/root/.ssh/id_ecdsa" -N ""
cat /root/.ssh/id_ecdsa.pub >> /root/.ssh/authorized_keys
ssh-keygen -t rsa -b 4096 -O clear -O permit-pty -f "/root/.ssh/id_rsa" -N ""
cat /root/.ssh/id_rsa.pub >> /root/.ssh/authorized_keys
```

Das System ist nun für unsere Zwecke ausreichend konfiguriert, so dass wir das Chroot nun verlassen und die Systempartition unmounten können.

``` shell
exit

umount /mnt/dev
umount /mnt

exit
```

Abschliessend beenden wir die virtuelle Maschine und werfen die Installations-DVD aus.

``` powershell
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" controlvm "mfsBSD" acpipowerbutton
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" controlvm "mfsBSD" poweroff

& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" storageattach "mfsBSD" --storagectl "AHCI Controller" --port 0 --device 0 --type dvddrive --medium emptydrive
```

## Einloggen ins virtuelle System

Nachdem wir unser frisch installiertes System gebootet haben, sollten wir uns mittels PuTTY als `root` einloggen können.

``` powershell
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" startvm "mfsBSD"

putty -ssh -P 2222 root@127.0.0.1
```

## ports-mgmt/pkg installieren

Wir installieren pkg via pkg.

``` shell
pkg bootstrap -y
```

## mfsBSD erzeugen

Wir werden nun unser mfsBSD-Image erzeugen, um damit später unser eigentliches dediziertes System booten und installieren zu können. Hierzu legen uns zunächst ein Arbeitsverzeichnis an.

``` shell
mkdir -p /usr/local/mfsbsd
```

Nun fehlt noch das mfsBSD-Buildscript, welches wir jetzt mittels `fetch` in unserem Arbeitsverzeichnis downloaden und dann entpacken.

``` shell
cd /usr/local/mfsbsd

fetch -4 -q -o "mfsbsd-master.tar.gz" --no-verify-peer "https://github.com/mmatuska/mfsbsd/archive/master.tar.gz"

tar xf mfsbsd-master.tar.gz
chown -R root:wheel mfsbsd-master
cd mfsbsd-master
```

Um uns später per SSH in unserem mfsBSD-Image einloggen zu können, legen wir das Passwort `mfsroot` für root fest.

``` shell
sed -e 's/^#\(mfsbsd.rootpw=\).*$/\1"mfsroot"/' conf/loader.conf.sample > conf/loader.conf
```

Für unsere Zwecke reicht die Standardkonfiguration des mfsBSD-Buildscripts aus, so dass wir unser mfsBSD-Image direkt erzeugen können.

``` shell
make BASE=/usr/freebsd-dist RELEASE=14.3-RELEASE ARCH=amd64 PKG_STATIC=/usr/local/sbin/pkg-static MFSROOT_MAXSIZE=120m
```

Anschliessend liegt unter `/usr/local/mfsbsd/mfsbsd-master/mfsbsd-14.3-RELEASE-amd64.img` unser fertiges mfsBSD-Image. Dieses kopieren wir nun per PuTTY auf den Windows Host.

``` powershell
pscp -P 2222 root@127.0.0.1:/usr/local/mfsbsd/mfsbsd-master/mfsbsd-14.3-RELEASE-amd64.img "${Env:USERPROFILE}\VirtualBox VMs\mfsBSD\mfsbsd-14.3-RELEASE-amd64.img"
```

Die virtuelle Maschine können wir an dieser Stelle nun beenden.

``` shell
exit
```

``` powershell
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" controlvm "mfsBSD" acpipowerbutton
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" controlvm "mfsBSD" poweroff
```

Fertig.

Viel Spass mit dem neuen mfsBSD Image
