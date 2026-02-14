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
date: '2003-03-02'
lastmod: '2014-09-01'
title: Remote Installation
description: In diesem HowTo wird step-by-step die Remote Installation von Gentoo Linux 64Bit auf einem dedizierten Server beschrieben.
robots: index, follow
lang: de
hide: []
search:
  exclude: false
---

# Remote Installation

## Einleitung

!!! danger
    Dieses HowTo wird seit **2014-09-01** nicht mehr aktiv gepflegt und entspricht daher nicht mehr dem aktuellen Stand.

    Die Verwendung dieses HowTo geschieht somit auf eigene Gefahr!

In diesem HowTo beschreibe ich step-by-step die Remote Installation von [Gentoo Linux Hardened](https://wiki.gentoo.org/wiki/Project:Hardened){: target="\_blank" rel="noopener"} 64Bit auf einem dedizierten Server. Um eine weitere Reproduktion der offiziellen [Gentoo Linux Dokumentation](https://www.gentoo.org/support/documentation/){: target="\_blank" rel="noopener"} zu vermeiden, werde ich in diesem HowTo nicht alle Punkte bis ins Detail erläutern.

Folgende Punkte sind in diesem HowTo zu beachten.

- Alle Dienste werden mit einem möglichst minimalen und bewährten Funktionsumfang installiert.
- Alle Dienste werden mit einer möglichst sicheren und dennoch flexiblen Konfiguration versehen.
- Alle Konfigurationen sind selbstständig auf notwendige individuelle Anpassungen zu kontrollieren.
- Alle Passworte werden als `__PASSWORD__` dargestellt und sind selbstständig durch sichere Passworte zu ersetzen.
- Die Domain des Servers lautet `example.com` und ist selbstständig durch die eigene Domain zu ersetzen.
- Der Hostname des Servers lautet `devnull` und ist selbstständig durch den eigenen Hostnamen zu ersetzen (FQDN=devnull.example.com).
- Es wird der FQDN `devnull.example.com` verwendet und ist selbstständig im DNS zu registrieren.

## Das Referenzsystem

Als Referenzsystem für dieses HowTo habe ich mich für eine virtuelle Maschine auf Basis von [Oracle VM VirtualBox](https://www.virtualbox.org/){: target="\_blank" rel="noopener"} unter [Microsoft Windows 11 Pro (64Bit)](https://www.microsoft.com/en-us/windows/windows-11){: target="\_blank" rel="noopener"} entschieden. So lässt sich ohne grösseren Aufwand ein handelsüblicher dedizierter Server simulieren und anschliessend kann diese virtuelle Maschine als kostengünstiges lokales Testsystem weiter genutzt werden.

Trotzdem habe ich dieses HowTo so ausgelegt, dass es sich nahezu unverändert auf dedizierte Server übertragen lässt und dieses auch auf mehreren dedizierten Servern getestet.

Leider bringt Microsoft Windows keinen eigenen SSH-Client mit, so dass ich auf das sehr empfehlenswerte [PuTTY (64Bit)](https://www.chiark.greenend.org.uk/~sgtatham/putty/){: target="\_blank" rel="noopener"} zurückgreife. Zur Simulation des bei nahezu allen Anbietern dedizierter Server vorhandene Rettungssystem, nachfolgend RescueSystem genannt, wird in diesem HowTo die auf [Gentoo Linux](https://www.gentoo.org/){: target="\_blank" rel="noopener"} basierende [SystemRescueCD](https://www.system-rescue.org/){: target="\_blank" rel="noopener"} eingesetzt.

VirtualBox und PuTTY werden mit den jeweiligen Standardoptionen installiert.

``` powershell
winget install PuTTY.PuTTY
winget install Oracle.VirtualBox
```

## Die Virtuelle Maschine

Als Erstes öffnen wir eine neue PowerShell und legen manuell eine neue virtuelle Maschine an. Diese virtuelle Maschine bekommt den Namen `Gentoo` und wird mit 2048MB RAM, 32MB VideoRAM, zwei 32GB SATA-Festplatte, einem DVD-Player, sowie einer Intel-Netzwerkkarte ausgestattet. Zudem setzen wir die RTC (Real-Time Clock) der virtuellen Maschine auf UTC (Coordinated Universal Time), aktivieren den HPET (High Precision Event Timer) und legen die Bootreihenfolge fest.

``` powershell
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" createvm --name "Gentoo" --ostype Gentoo_64 --register

cd "${Env:USERPROFILE}\VirtualBox VMs\Gentoo"

& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" createhd --filename "Gentoo1.vdi" --size 32768
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" createhd --filename "Gentoo2.vdi" --size 32768

& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" modifyvm "Gentoo" --firmware bios --cpus 2 --cpuexecutioncap 100 --cpuhotplug off
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" modifyvm "Gentoo" --chipset ICH9 --graphicscontroller vmsvga --audio none --usb off
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" modifyvm "Gentoo" --hwvirtex on --ioapic on --hpet on --rtcuseutc on --memory 4096 --vram 64
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" modifyvm "Gentoo" --nic1 nat --nictype1 82540EM --natnet1 "192.168/16" --cableconnected1 on
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" modifyvm "Gentoo" --boot1 dvd --boot2 disk --boot3 none --boot4 none

& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" storagectl "Gentoo" --name "IDE Controller" --add ide --controller ICH6 --portcount 2
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" storagectl "Gentoo" --name "AHCI Controller" --add sata --controller IntelAHCI --portcount 4

& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" storageattach "Gentoo" --storagectl "AHCI Controller" --port 0 --device 0 --type hdd --medium "Gentoo1.vdi"
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" storageattach "Gentoo" --storagectl "AHCI Controller" --port 1 --device 0 --type hdd --medium "Gentoo2.vdi"
```

Die virtuelle Maschine, genauer die virtuelle Netzwerkkarte, kann dank NAT zwar problemlos mit der Aussenwelt, aber leider nicht direkt mit dem Hostsystem kommunizieren. Aus diesem Grund richten wir nun für den SSH-Zugang noch ein Portforwarding ein, welches den Port 2222 des Hostsystems auf den Port 22 der virtuellen Maschine weiterleitet.

``` powershell
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" modifyvm "Gentoo" --natpf1 SSH,tcp,,2222,,22
```

Nachdem die virtuelle Maschine nun konfiguriert ist, wird es Zeit diese zu booten.

## RescueSystem booten

Um unser Gentoo Linux Hardened installieren zu können, müssen wir unsere virtuelle Maschine mit einem RescueSystem booten. Hierfür eignet sich die auf [Gentoo Linux](https://www.gentoo.org/){: target="\_blank" rel="noopener"} basierende [SystemRescueCD](https://www.system-rescue.org/){: target="\_blank" rel="noopener"} am Besten, welche wir mittels des mit Windows mitgelieferten FTP-Client herunterladen und unserer virtuellen Maschine als Bootmedium zuweisen.

``` powershell
cd "${Env:USERPROFILE}\VirtualBox VMs\Gentoo"

ftp -A ftp.halifax.rwth-aachen.de
cd osdn/storage/g/s/sy/systemrescuecd/releases/7.01
binary
get systemrescue-7.01-amd64.iso
quit

& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" storageattach "Gentoo" --storagectl "IDE Controller" --port 0 --device 0 --type dvddrive --medium "systemrescue-7.01-amd64.iso"
```

Wir können das RescueSystem jetzt booten.

``` powershell
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" startvm "Gentoo"
```

Im Bootmenü wählen wir die erste Option "boot with default options" aus.

Wer mit dem amerikanischen Tastaturlayout nicht zurechtkommt, sollte während des Bootens die Frage nach der Keymap mit `de` beantworten.

Ist der Bootvorgang abgeschlossen, wird als Erstes das root-Passwort für das RescueSystem gesetzt.

``` shell
passwd root
```

Jetzt sollten wir uns mittels PuTTY als `root` in das RescueSystem einloggen und mit der Installation unseres Gentoo Linux Hardened fortfahren können.

``` powershell
putty -ssh -P 2222 root@127.0.0.1
```

## Partitionieren der Festplatte

Bevor wir anfangen, bereinigen wir die Festplatten von jeglichen Datenrückständen, indem wir sie mit Nullen überschreiben. Je nach Festplattengrösse kann dies einige Stunden bis Tage in Anspruch nehmen. Aus diesem Grund verlegen wir diese Jobs mittels `nohup` in den Hintergrund, so dass wir uns zwischenzeitlich ausloggen können ohne dass dabei die Jobs automatisch von der Shell abgebrochen werden. Ob die Jobs fertig sind, lässt dann mittels `ps -auxfwww` und `top -atCP` ermitteln.

``` shell
nohup dd if=/dev/zero of=/dev/sda bs=512  &
nohup dd if=/dev/zero of=/dev/sdb bs=512  &
```

Da jeder Administrator andere Präferenzen an sein Partitionslayout stellt und wir andernfalls mit diesem HowTo nicht weiterkommen, verwenden wir im Folgenden ein Standard-Partitionslayout. Fortgeschrittenere Linux-Administratoren können dieses Partitionslayout selbstverständlich an ihre eigenen Bedürfnisse anpassen.

| Partition           | Mountpunkt |  Filesystem  | Grösse |
| :------------------ | :--------- | :----------: | -----: |
| /dev/sda1 /dev/sdb1 | none       | [bootloader] |   2 MB |
| /dev/sda2 /dev/sdb2 | /boot      |     EXT2     | 512 MB |
| /dev/sda3 /dev/sdb3 | /          |     EXT3     |  16 GB |
| /dev/sda4 /dev/sdb4 | /data      |     EXT3     |   8 GB |
| /dev/sda5 /dev/sdb5 | none       |    [swap]    |   4 GB |

Die Partitionen legen wir nun mittels parted an.

``` shell
parted -s -a optimal /dev/sda
parted -s -a optimal /dev/sdb

parted -s /dev/sda mklabel gpt
parted -s /dev/sdb mklabel gpt

parted -s /dev/sda mkpart primary 4096s 8191s
parted -s /dev/sdb mkpart primary 4096s 8191s

parted -s /dev/sda mkpart primary 8192s 1056767s
parted -s /dev/sdb mkpart primary 8192s 1056767s

parted -s /dev/sda mkpart primary 1056768s 34611199s
parted -s /dev/sdb mkpart primary 1056768s 34611199s

parted -s /dev/sda mkpart primary 34611200s 51388415s
parted -s /dev/sdb mkpart primary 34611200s 51388415s

parted -s /dev/sda mkpart primary 51388416s 59777023s
parted -s /dev/sdb mkpart primary 51388416s 59777923s

parted -s /dev/sda name 1 grub
parted -s /dev/sdb name 1 grub

parted -s /dev/sda name 2 boot
parted -s /dev/sdb name 2 boot

parted -s /dev/sda name 3 rootfs
parted -s /dev/sdb name 3 rootfs

parted -s /dev/sda name 4 data
parted -s /dev/sdb name 4 data

parted -s /dev/sda name 5 swap
parted -s /dev/sdb name 5 swap

parted -s /dev/sda set 1 bios_grub on
parted -s /dev/sdb set 1 bios_grub on
```

Für eine leicht erhöhte Datensicherheit legen wir nun noch mittels `mdadm` ein Software-RAID1 an.

``` shell
mknod /dev/md2 b 9 2
mknod /dev/md3 b 9 3
mknod /dev/md4 b 9 4
mknod /dev/md5 b 9 5

cat >> /etc/mdadm.conf << "EOF"
MAILADDR root@localhost
MAILFROM root@localhost
CREATE metadata=1.2
HOMEHOST <none>
DEVICE /dev/sd*[0-9]
EOF

mdadm --create /dev/md2 --name=boot --bitmap=internal --level=raid1 --raid-devices=2 --metadata=1.2 /dev/sd[ab]2
mdadm --create /dev/md3 --name=root --bitmap=internal --level=raid1 --raid-devices=2 --metadata=1.2 /dev/sd[ab]3
mdadm --create /dev/md4 --name=data --bitmap=internal --level=raid1 --raid-devices=2 --metadata=1.2 /dev/sd[ab]4
mdadm --create /dev/md5 --name=swap --bitmap=internal --level=raid1 --raid-devices=2 --metadata=1.2 /dev/sd[ab]5
```

## Formatieren der Partitionen

Die frisch angelegten Partitionen müssen selbstverständlich noch formatiert werden. Normalerweise formatiere ich die Rootpartition mit XFS, da allerdings nicht jeder Administrator XFS für seine Rootpartition verwenden möchte, werden wir ein manuell optimiertes EXT3 anlegen. Dieses ist nötig, da die e2fsprogs bei einigen Distributionen und somit auch RescueSystemen oft veraltet oder suboptimal konfiguriert sind und daher nicht immer ein für Server optimiertes EXT3 erzeugen.

Die Bootpartition sollte grundsätzlich mit EXT2 formatiert weren, da dieses Filesystem als Einziges von allen gängigen Linux-Bootloadern fehlerfrei unterstützt wird.

``` shell
mke2fs -c -b 4096 -t ext2 /dev/md2
tune2fs -c 0 -i 0 /dev/md2
tune2fs -e continue /dev/md2
tune2fs -O dir_index /dev/md2
tune2fs -o user_xattr,acl /dev/md2
e2fsck -D /dev/md2

mke2fs -c -b 4096 -t ext3 -j /dev/md3
tune2fs -c 0 -i 0 /dev/md3
tune2fs -e continue /dev/md3
tune2fs -O dir_index,large_file /dev/md3
tune2fs -o user_xattr,acl,journal_data /dev/md3
e2fsck -D /dev/md3

mke2fs -c -b 4096 -t ext3 -j /dev/md4
tune2fs -c 0 -i 0 /dev/md4
tune2fs -e continue /dev/md4
tune2fs -O dir_index,large_file /dev/md4
tune2fs -o user_xattr,acl,journal_data /dev/md4
e2fsck -D /dev/md4

mkswap -c /dev/md5
```

## Mounten der Partitionen

Nun werden die Partitionen für unsere zur Installation benötigten Chroot-Umgebung gemountet und der Swapspace aktiviert.

``` shell
swapon /dev/md5

mkdir -p /mnt/gentoo
mount -t ext3 -o defaults,relatime,barrier=1 /dev/md3 /mnt/gentoo
```

## Entpacken des Stage-Tarballs

Der Stage3-Tarball enthält ein minimalistisches Gentoo Linux Hardened, welches alle zur Installation notwendigen Tools enthält und uns als Chroot-Umgebung dient. Wir müssen nun den aktuellen Stage3-Tarball ermitteln, wozu wir die entsprechende Angabe vom [Gentoo Linux Master Mirror](https://gentoo.osuosl.org/){: target="\_blank" rel="noopener"} verwenden und diese in dem folgenden zweiten wget-Aufruf entsprechend ersetzen. Den Stage3-Tarball werden wir bereits während des Download direkt nach `/mnt/gentoo` entpacken.

``` shell
wget -q -O - "https://gentoo.osuosl.org/releases/amd64/autobuilds/latest-stage3-amd64-hardened+nomultilib.txt" | tail -n 1 | \
     xargs -I % wget -q -O - "https://gentoo.osuosl.org/releases/amd64/autobuilds/%" | tar xpjvf - -C /mnt/gentoo/
```

## Vorbereiten der Chroot-Umgebung

Vor dem Wechsel in die Chroot-Umgebung müssen wir noch die `resolv.conf` und `mdadm.conf` in die Chroot-Umgebung kopieren und die für eine erfolgreiche Installation noch fehlenden Filesysteme mounten.

``` shell
cp /etc/resolv.conf /mnt/gentoo/etc/resolv.conf

mount -t proc none /mnt/gentoo/proc
mount -o bind /sys /mnt/gentoo/sys
mount -o bind /dev /mnt/gentoo/dev
mount -o bind /dev/pts /mnt/gentoo/dev/pts
mount -o bind /dev/shm /mnt/gentoo/dev/shm
```

## Betreten der Chroot-Umgebung

Beim Betreten der Chroot-Umgebung setzen wir mittels `/bin/env -i` erstmal alle Environment-Variablen zurück. Andererseits benötigen wir aber die Environment-Variablen `HOME`, `TERM`, `PS1` und `PATH`, welche wir manuell auf sinnvolle Defaults setzen.

``` shell
chroot /mnt/gentoo /bin/env -i HOME=/root TERM=$TERM PS1='\u:\w\$ ' PATH=/sbin:/bin:/usr/sbin:/usr/bin /bin/bash +h

mkdir /data

mount -t ext2 -o defaults,relatime /dev/md2 /boot
```

## Setup der Chroot-Umgebung

Wir setzen die Timezone, legen die `mtab` an und aktualisieren das Environment.

``` shell
echo "Europe/Berlin" > /etc/timezone
emerge --config sys-libs/timezone-data

grep -v rootfs /proc/mounts > /etc/mtab

env-update
source /etc/profile
```

## Portage konfigurieren

Mittels der `/etc/portage/make.conf` wird Portage konfiguriert.

``` shell
cat > /etc/portage/make.conf << "EOF"
CHOST="x86_64-pc-linux-gnu"
CFLAGS="-O2 -pipe -fomit-frame-pointer -march=native"
CXXFLAGS="${CFLAGS}"
FFLAGS="${CFLAGS}"
FCFLAGS="${FFLAGS}"
PORTDIR="/usr/portage"
DISTDIR="${PORTDIR}/distfiles"
PKGDIR="${PORTDIR}/packages"
ACCEPT_KEYWORDS="amd64"
MAKEOPTS="-j2"
LINGUAS="en de"
PAX_MARKINGS="XT"
USE="-* acl berkdb bzip2 caps crypt cxx ecdsa fam fdformat filecaps \
    firmware-loader gcrypt gd gdbm gmp hardened iconv icu idn ipc ipv6 \
    ithreads jpeg kmod ldns libedit libssp lzma magic mime mktemp mmx \
    mpfr mudflap multicall ncurses net netifrc newnet nls nptl nscd \
    openmp openssl pax_kernel pci pcre pcre16 pcre32 perl pic png posix \
    ptpax python python2 python3 recursion-limit right_timezone sha512 \
    sharedmem sockets sse sse2 ssh ssl threads tiff tools truetype \
    tty-helpers udev unicode urandom usb utils xattr xml xsl xtpax zlib"
PYTHON_TARGETS="python2_7 python3_3 python3_4"
PYTHON_SINGLE_TARGET="python2_7"
CURL_SSL="openssl"
EOF
```

Mittels `/etc/portage/package.use` werden einzelnen Paketen von der `/etc/portage/make.conf` abweichende USE-Flags zugewiesen.

``` shell
cat > /etc/portage/package.use << "EOF"
EOF
```

Als Nächstes legen wir mittels `emerge-webrsync` den Portage-Tree an.

``` shell
emerge-webrsync
```

## Locales setzen

Da das System später weltweit erreichbar sein wird und die Standardsystemsprache amerikanisch ist, werden die Locales auf `en_US.utf8` gesetzt und neu erzeugt.

``` shell
cat > /etc/env.d/02locale << "EOF"
LC_ALL="en_US.UTF-8"
LANG="en_US.UTF-8"
EOF

cat >> /etc/locale.gen << "EOF"
en_US ISO-8859-1
en_US.UTF-8 UTF-8
de_DE ISO-8859-1
de_DE@euro ISO-8859-15
de_DE.UTF-8 UTF-8
EOF

locale-gen -c /etc/locale.gen

env-update
source /etc/profile
```

## Basissystem kompilieren

Nun muss das komplette Basissystem neu kompiliert werden. Dieser Vorgang ist zwar zeitaufwendig, aber für diese Installationsvariante zwingend notwendig. Auch die Reihenfolge ist sehr wichtig, da das System ansonsten unbrauchbar beziehungsweise instabil wird. Während dieses Vorgangs werden nur wenige Konfigurationsdateien automatisch aktualisiert, alle anderen müssen manuell mittels `dispatch-conf` aktualisiert werden.

!!! info
    Die folgenden Schritte sind nötig, da dieses HowTo eine angepasste Portage-Konfiguration verwendet und zudem seit dem Release des Stage3-Tarballs eventuell ein paar für diese Installationvariante wichtige Basispakete im Portage-Tree aktualisiert wurden.

``` shell
emerge portage portage-utils

emerge -C cracklib pam pambase virtual/pam tcp-wrappers
emerge @preserved-rebuild

emerge glibc

emerge binutils gcc

env-update
source /etc/profile

emerge hardened-sources

emerge -e -D @world

dispatch-conf
```

## Basissystem rekompilieren

Um sicherzustellen, dass das Basissystem ab diesem Punkt keine veraltete Konfiguration oder (speicheresistente) Software nutzt, wird sicherheitshalber kurz die Chroot-Umgebung verlassen und gleich wieder betreten. Im Anschluss wird das Basissystem ein zweites Mal vollständig rekompiliert, damit sichergestellt ist, dass auch wirklich jedes Paket nur noch gegen die aktuell vorhanden Libraries gelinkt ist und somit keine veralteten und/oder nicht mehr vorhandenen Funktionen nutzt.

``` shell
exit

chroot /mnt/gentoo /bin/env -i HOME=/root TERM=$TERM PS1='\u:\w\$ ' PATH=/sbin:/bin:/usr/sbin:/usr/bin /bin/bash +h

env-update
source /etc/profile

emerge -e -D @world

emerge --depclean

dispatch-conf

env-update
source /etc/profile
```

## fstab erstellen

Ohne fstab wird das System später nicht booten ;-)

``` shell
cat > /etc/fstab << "EOF"
/dev/disk/by-id/md-name-root   /          ext3    defaults,relatime,barrier=1    1 1
/dev/disk/by-id/md-name-boot   /boot      ext2    defaults,relatime              1 2
/dev/disk/by-id/md-name-data   /data      ext3    defaults,relatime,barrier=1    2 2
/dev/disk/by-id/md-name-swap   none       swap    sw                             0 0
EOF
```

## OpenSSL konfigurieren

Folgende Optionen müssen mit dem Editor `ee` (`ee /etc/ssl/openssl.cnf`) in der `/etc/ssl/openssl.cnf` im Abschnitt `[req_distinguished_name ]` angepasst beziehungsweise ergänzt werden.

``` text
countryName_default             = DE
stateOrProvinceName_default     = Bundesland
localityName_default            = Ort
0.organizationName_default      = Example Corporation
organizationalUnitName_default  = Certification Authority
emailAddress_default            = admin@example.com
```

Folgende Optionen müssen im Abschnitt `[ CA_default ]` angepasst werden.

``` text
default_days            = 730
default_md              = sha256
```

Folgende Optionen müssen im Abschnitt `[ req ]` angepasst werden.

``` text
default_bits            = 4096
string_mask             = utf8only
```

DH Param Files erzeugen

``` shell
openssl genpkey -genparam -algorithm DH -pkeyopt dh_paramgen_prime_len:4096 -out /etc/ssl/dh_params.pem
openssl genpkey -genparam -algorithm EC -pkeyopt ec_paramgen_curve:secp384r1 -out /etc/ssl/ec_params.pem
```

## OpenSSH konfigurieren

Da wir gerade ein Produktiv-System aufsetzen, werden wir den SSH-Dienst recht restriktiv konfigurieren, unter Anderem werden wir den Login per Passwort verbieten und nur per PublicKey zulassen.

``` shell
sed -e 's/^#\(Protocol\).*$/\1 2/' \
    -e 's/^#\(RekeyLimit\).*$/\1 500M 1h/' \
    -e 's/^#\(LoginGraceTime\).*$/\1 1m/' \
    -e 's/^#\(PermitRootLogin\).*$/\1 yes/' \
    -e 's/^#\(StrictModes\).*$/\1 yes/' \
    -e 's/^#\(MaxAuthTries\).*$/\1 3/' \
    -e 's/^#\(MaxSessions\).*$/\1 10/' \
    -e 's/^#\(RSAAuthentication\).*$/\1 no/' \
    -e 's/^#\(PubkeyAuthentication\).*$/\1 yes/' \
    -e 's/^#\(IgnoreRhosts\).*$/\1 yes/' \
    -e 's/^#\(PasswordAuthentication\).*$/\1 no/' \
    -e 's/^#\(PermitEmptyPasswords\).*$/\1 no/' \
    -e 's/^#\(ChallengeResponseAuthentication\).*$/\1 no/' \
    -e 's/^#\(UsePAM\).*$/\1 no/' \
    -e 's/^#\(AllowAgentForwarding\).*$/\1 no/' \
    -e 's/^#\(AllowTcpForwarding\).*$/\1 no/' \
    -e 's/^#\(GatewayPorts\).*$/\1 no/' \
    -e 's/^#\(X11Forwarding\).*$/\1 no/' \
    -e 's/^#\(PermitTTY\).*$/\1 yes/' \
    -e 's/^#\(UseLogin\).*$/\1 no/' \
    -e 's/^#\(UsePrivilegeSeparation\).*$/\1 sandbox/' \
    -e 's/^#\(PermitUserEnvironment\).*$/\1 no/' \
    -e 's/^#\(UseDNS\).*$/\1 yes/' \
    -e 's/^#\(MaxStartups\).*$/\1 10:30:100/' \
    -e 's/^#\(PermitTunnel\).*$/\1 no/' \
    -e 's/^#\(ChrootDirectory\).*$/\1 %h/' \
    -e 's/^\(Subsystem\).*$/\1 sftp internal-sftp -u 0027/' \
    -i /etc/ssh/sshd_config

cat >> /etc/ssh/sshd_config << "EOF"
AllowGroups wheel admin sftponly users

Match Group wheel
        ChrootDirectory none

Match Group admin
        PasswordAuthentication yes

Match Group sftponly
        ForceCommand internal-sftp
EOF

sed -e '/^# Ciphers and keying/ a\
Ciphers chacha20-poly1305@openssh.com,aes256-gcm@openssh.com,aes256-cbc,aes256-ctr\
Macs hmac-sha2-512-etm@openssh.com,hmac-sha2-256-etm@openssh.com,hmac-sha2-512,hmac-sha2-256\
KexAlgorithms curve25519-sha256@libssh.org,ecdh-sha2-nistp521,ecdh-sha2-nistp384,diffie-hellman-group-exchange-sha256,diffie-hellman-group-exchange-sha1' -i /etc/ssh/sshd_config

cat >> /etc/ssh/ssh_config << "EOF"
Host *
        Protocol 2
        RekeyLimit 500M 1h
        Ciphers chacha20-poly1305@openssh.com,aes256-gcm@openssh.com,aes256-ctr,aes256-cbc
        Macs hmac-sha2-512-etm@openssh.com,hmac-sha2-256-etm@openssh.com,hmac-sha2-512,hmac-sha2-256
        KexAlgorithms curve25519-sha256@libssh.org,ecdh-sha2-nistp521,ecdh-sha2-nistp384,diffie-hellman-group-exchange-sha256,diffie-hellman-group-exchange-sha1
        HostKeyAlgorithms ssh-ed25519-cert-v01@openssh.com,ecdsa-sha2-nistp521-cert-v01@openssh.com,ecdsa-sha2-nistp384-cert-v01@openssh.com,ecdsa-sha2-nistp256-cert-v01@openssh.com,ecdsa-sha2-nistp521,ecdsa-sha2-nistp384,ecdsa-sha2-nistp256
        VisualHostKey yes
EOF

rc-update add sshd default
```

## Systemprogramme installieren

Jetzt werden wichtige Systemprogramme installiert.

``` shell
cat >> /etc/portage/package.use << "EOF"
sys-fs/lvm2  lvm1 lvm2create_initrd
EOF

emerge syslog-ng logrotate cronie iproute2 dhcpcd mdadm lvm2 mcelog

rc-update add mdraid boot
rc-update add cronie default
rc-update add syslog-ng default

cat >> /etc/mdadm.conf << "EOF"
MAILADDR root@localhost
MAILFROM root@localhost
CREATE metadata=1.2
HOMEHOST <none>
DEVICE /dev/sd*[0-9]
EOF
```

## Netzwerk konfigurieren

Das Netzwerk konfigurieren wir statisch

``` shell
sed -e 's/^\(hostname=\).*$/\1"devnull"/' -i /etc/conf.d/hostname

cat >> /etc/conf.d/net << "EOF"
#
# Setup eth0
config_eth0="__IPADDR4__ netmask NETMASK4"
routes_eth0="default via GATEWAY4"
EOF

ln -s net.lo /etc/init.d/net.eth0

rc-update add net.eth0 boot
```

Es folgt ein wenig Voodoo, um die Netzwerkkonfiguration in der `/etc/conf.d/network` zu vervollständigen.

``` shell
# IPv4
ifconfig `ip -f inet route show scope global | awk '/default/ {print $5}'` | \
    awk '/inet/ {print $2}' | xargs -I % sed -e 's/__IPADDR4__/%/g' -i /etc/conf.d/net
ifconfig `ip -f inet route show scope global | awk '/default/ {print $5}'` | \
    awk '/inet/ {print $4}' | xargs -I % sed -e 's/NETMASK4/%/g' -i /etc/conf.d/net
ip -f inet route show scope global | awk '/default/ {print $3}' | \
    xargs -I % sed -e 's/GATEWAY4/%/g' -i /etc/conf.d/net
```

Wir richten die `/etc/hosts` ein.

``` shell
# localhost
sed -e 's/my.domain/example.com/g' -i /etc/hosts

# IPv4
echo '__IPADDR4__   devnull.example.com' >> /etc/hosts

ifconfig `ip -f inet route show scope global | awk '/default/ {print $5}'` | \
    awk '/inet/ {print $2}' | xargs -I % sed -e 's/__IPADDR4__/%/g' -i /etc/hosts
```

## Kernelsourcen installieren

Wir installieren nun die Gentoo Linux Hardened Kernelsourcen und das Gentoo Linux Tool `genkernel` zum automatisierten Erstellen des Kernel und des zugehörigen Initramfs.

``` shell
cat >> /etc/portage/package.keywords << "EOF"
sys-kernel/genkernel  ~amd64
EOF

cat >> /etc/portage/package.use << "EOF"
sys-kernel/genkernel  -crypt
EOF

emerge hardened-sources genkernel

sed -e 's/^#\(SYMLINK=\).*$/\1"no"/' \
    -e 's/^#\(CLEAR_CACHE_DIR=\).*$/\1"yes"/' \
    -e 's/^#\(POSTCLEAR=\).*$/\1"yes"/' \
    -e 's/^#\(LVM=\).*$/\1"no"/' \
    -e 's/^#\(LUKS=\).*$/\1"no"/' \
    -e 's/^#\(GPG=\).*$/\1"no"/' \
    -e 's/^#\(DMRAID=\).*$/\1"no"/' \
    -e 's/^#\(BUSYBOX=\).*$/\1"yes"/' \
    -e 's/^#\(MDADM=\).*$/\1"yes"/' \
    -e 's/^#\(MULTIPATH=\).*$/\1"no"/' \
    -e 's/^#\(ISCSI=\).*$/\1"no"/' \
    -e 's/^#\(E2FSPROGS=\).*$/\1"yes"/' \
    -e 's/^#\(UNIONFS=\).*$/\1"no"/' \
    -e 's/^#\(ZFS=\).*$/\1"no"/' \
    -e 's/^#\(FIRMWARE=\).*$/\1"no"/' \
    -e 's/^#\(BUILD_STATIC=\).*$/\1"yes"/' \
    -i /etc/genkernel.conf
```

## Kernelsourcen konfigurieren

``` shell
mkdir -p /root/kernels

cat <<'EOF' > /root/kernels/MYKERNEL
--8<-- "gentoo/kernel_hardened.config"
EOF


cd /usr/src/linux
make mrproper
cp /root/kernels/MYKERNEL /usr/src/linux/.config
make oldconfig
```

## Kernelsourcen kompilieren

Die Kernelkonfiguration, insbesondere die Hardware-Optionen, muss Abseits der virtuellen Maschine an das eigene System angepasst werden. Dies ermöglicht uns die Angabe der Option `--menuconfig` beim genkernel-Aufruf. Für die Verwendung des Kernels in der virtuelle Maschine ist allerdings kein weiteres Anpassen der Kernelkonfiguration notwendig.

``` shell
make
make firmware_install
make modules_install
make install
cp /usr/src/linux/.config /root/kernels/MYKERNEL
cd /root

genkernel --kernel-config=/root/kernels/MYKERNEL --no-ramdisk-modules --mdadm --install initramfs
```

## Bootloader installieren

Als Bootloader kommt `grub` zum Einsatz.

``` shell
cat >> /etc/portage/make.conf << "EOF"
GRUB_PLATFORMS="emu efi-32 efi-64 pc"
EOF

cat >> /etc/portage/package.use << "EOF"
sys-boot/grub  -truetype efiemu mount
EOF

emerge grub

mount -o remount,rw /boot

chmod -x /etc/grub.d/{20_linux_xen,30_os-prober,40_custom,41_custom}

sed -e 's/^#\(GRUB_CMDLINE_LINUX=\).*$/\1"pax_softmode=1 domdadm"/' \
    -e 's/^#\(GRUB_CMDLINE_LINUX_DEFAULT=\).*$/\1"quiet"/' \
    -e 's/^#\(GRUB_TERMINAL=\).*$/\1console/' \
    -i /etc/default/grub

grub-install --grub-setup=/bin/true /dev/sdb
grub-install --grub-setup=/bin/true /dev/sda
```

## Bootloader konfigurieren

Grub muss noch konfiguriert werden.

``` shell
grub-mkconfig -o /boot/grub/grub.cfg
```

## Systemtools installieren

Als Nächstes installieren wir noch ein paar notwendige beziehungsweise nützliche Systemtools.

``` shell
cat >> /etc/portage/package.use << "EOF"
app-crypt/gnupg  -usb
net-dns/bind-tools  -xml
sys-apps/smartmontools  minimal
EOF

emerge gradm app-crypt/gnupg bind-tools ntp smartmontools
```

## Systemtools konfigurieren

Wir sorgen nun dafür, dass unsere Systemzeit mittels `cron` stündlich mit dem Timeserver der PTB in Braunschweig, dem Betreiber der deutschen Atomuhr, synchronisiert wird. Zudem aktivieren die regelmässige Überwachung der SMART-Werte unserer Festplatten.

``` shell
cat > /etc/cron.hourly/ntpdate << "EOF"
#!/bin/sh
/usr/sbin/ntpdate -4 -b -s ptbtime2.ptb.de
EOF

chmod 0755 /etc/cron.hourly/ntpdate

echo '/dev/sda -a -o on -S on -s (S/../.././02|L/../../6/03)' >> /etc/smartd.conf
echo '/dev/sdb -a -o on -S on -s (S/../.././02|L/../../6/03)' >> /etc/smartd.conf
```

## sysctl.conf einrichten

Mit diesem `sed` werden ein paar Kernelparameter für die Netzwersicherheit gesetzt.

``` shell
sed -e 's/^#net.ipv4/net.ipv4/g' -i /etc/sysctl.conf
```

## Root-Passwort setzen

Das Passwort für root sollte mindestens 8 Zeichen lang sein und neben Gross/Klein-Schreibung auch Ziffern und/oder Sonderzeichen enthalten.

``` shell
passwd root
```

## Arbeitsuser anlegen

Wir legen uns nun einen Arbeitsuser für administrative Aufgaben an. Diesen Arbeitsuser stecken wir in die Systemgruppen `admin`, `users` und `wheel`. Das Passwort für den Arbeitsuser sollte wie das root-Passwort aufgebaut sein, sich von diesem aber deutlich unterscheiden.

``` shell
groupadd -g 1000 admin
useradd -u 1000 -g admin -G users,wheel -c 'Administrator' -m -s /bin/bash admin

passwd admin
```

## SSH-Keys installieren

Für den eben angelegten Arbeitsuser müssen nun noch die SSH-Keys erzeugt werden.

``` shell
su - admin

ssh-keygen -t ed25519 -O clear -O permit-pty
cat .ssh/id_ed25519.pub >> .ssh/authorized_keys
ssh-keygen -t ecdsa -b 384 -O clear -O permit-pty
cat .ssh/id_ecdsa.pub >> .ssh/authorized_keys
ssh-keygen -t rsa -b 4096 -O clear -O permit-pty
cat .ssh/id_rsa.pub >> .ssh/authorized_keys

exit
```

Um uns künftig mit unserem Arbeitsuser einloggen zu können, müssen wir uns dessen SSH-Key (id_rsa) auf unser lokales System kopieren und ihn dann mit Hilfe der [PuTTYgen Dokumentation](https://the.earth.li/~sgtatham/putty/latest/htmldoc/Chapter8.html){: target="\_blank" rel="noopener"} in einen für PuTTY lesbaren Key umwandeln.

``` powershell
pscp -P 2222 -r root@127.0.0.1:/mnt/gentoo/home/admin/.ssh "${Env:USERPROFILE}\VirtualBox VMs\Gentoo\ssh"

puttygen "${Env:USERPROFILE}\VirtualBox VMs\Gentoo\ssh\id_rsa"
```

## Reboot ins neue System

Die Basisinstallation ist nun endlich abgeschlossen und wir können das neue System zum ersten Mal booten.

``` shell
umount /boot

exit

umount /mnt/gentoo/dev/shm
umount /mnt/gentoo/dev/pts
umount /mnt/gentoo/dev
umount /mnt/gentoo/sys
umount /mnt/gentoo/proc
umount /mnt/gentoo

shutdown -P now
```

``` powershell
& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" storageattach "Gentoo" --storagectl "IDE Controller" --port 0 --device 0 --type dvddrive --medium emptydrive

& "${Env:ProgramFiles}\Oracle\VirtualBox\VBoxManage.exe" startvm "Gentoo"

putty -ssh -P 2222 admin@127.0.0.1
```

``` shell
su - root

gradm -P

emerge -e -D @world

dispatch-conf
```

## Wie geht es weiter?

Natürlich mit dem [Hosting System](hosting_system.md)

Viel Spass mit dem neuen Gentoo Basissystem.
