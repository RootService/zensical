---
author:
  name: Markus Kohlmeyer
  url: https://github.com/JoeUser78
  email: joeuser@rootservice.org
publisher:
  name: RootService Team
  url: https://github.com/RootService
license:
  name: Attribution-NonCommercial-ShareAlike 4.0 International (CC BY-NC-SA 4.0)
  shortname: CC BY-NC-SA 4.0
  url: https://creativecommons.org/licenses/by-nc-sa/4.0/
contributers: []
date: '2002-12-15'
lastmod: '2020-12-15'
title: Pro und Contra dedizierter Server
description: In diesem HowTo wird eine kleine Checkliste pro und contra dedizierter Server gegeben.
keywords:
  - Pro und Contra dedizierter Server
  - mkdocs
  - docs
lang: de
robots: index, follow
hide: []
search:
  exclude: false
---

## Einleitung

In diesem HowTo wird eine kleine Checkliste pro und contra dedizierter Server gegeben.

## Ist ein dedizierter Server das Richtige für mich?

Um Dir die Beantwortung dieser Frage etwas leichter zu machen, haben wir Dir eine kleine Checkliste zusammengestellt.
Diese Checkliste ist dabei lediglich als Entscheidungshilfe gedacht, denn die Entscheidung musst letztendlich Du selbst
treffen, die können und wollen wir Dir nicht abnehmen.

### Probleme

Du wirst früher oder später mit den folgenden Problemen konfrontiert sein, das sollte Dir immer bewusst sein.

### Erstes Problem: Der Zeitaufwand

Unterschätze **niemals** den notwendigen Zeitaufwand um einen Server zu administrieren! Ein Server ist wie ein kleines
Baby, er benötigt **rund um die Uhr** Aufmerksamkeit. Das bedeutet, dass Du selbst bei der Verwendung von guten
Monitoringwerkzeugen circa **zwei Stunden täglich** für die Administration des Servers einplanen musst. Wenn dann auch
noch etwas Unplanmässiges auftaucht, was im IT-Bereich häufiger der Fall ist, werden es schnell mal **vier Stunden und
mehr**.

Kannst Du diese Zeit täglich aufbringen?

### Zweites Problem: Das Grundlagenwissen

Um einen dedizierten Server vernünftig administrieren zu können, genügt es nicht, sich zu Hause eine Linux-Distribution
mit grafischer Oberfläche zu installieren und ein paar Wochen zu nutzen. Ein dedizierter Server hat keine grafische
Oberfläche und auch Adminpanels wie zum Beispiel Plesk können einem die Arbeit an der Shell nicht abnehmen. Spätestens
wenn das Adminpanel nicht mehr so funktioniert wie es soll, oder wenn Du etwas umsetzen möchtest was vom Adminpanel
nicht abgedeckt wird, dann stehst Du ohne hinreichende Shellkenntnisse schnell auf dem Schlauch. Erschwerend kommt
hinzu, das die Shell nur ein sehr kleiner Teil des benötigten Grundlagenwissens ist, dazu kommen zum Beispiel noch die
Verzeichnisstruktur, das Paketmanagment, der Umgang mit Compiler und Patches, Netzwerkprotokolle, etc.

Hast Du Dir dieses Wissen bereits angeeignet?

### Drittes Problem: Die Sicherheit

Ein Server ist kein Desktop, daher kannst Du nahezu Alles was Du über das Absichern eines Desktops weisst schlicht
vergessen. Insbesondere werden Dir Firewalls und Anti-Virus-Programme auf einem Server keine grosse Hilfe sein, im
Gegenteil, sie werden Deinen Server eher unsicherer machen. Diese Produkte sind im Allgemeinen dafür gedacht ein System
so abzuschotten, das weder Gut noch Böse von aussen auf das System zugreifen kann. Bei einem Server willst Du aber
gerade den Zugriff von aussen haben, andernfalls könnte ja Niemand Deine Website besuchen, Dir E-Mails schicken, auf
Deinem Gameserver spielen oder was auch immer Du Deinen Besuchern anbieten willst. Tja und wie sicherst Du jetzt Deinen
Server vor unbefugten Zugriffen? Zunächst einmal mit regelmässigen vollständigen Updates des Systems und aller
installierten Software insbesondere der Webapplikationen. Dazu kommt eine gründliche Konfiguration jeder einzelnen
installierten Software und das ständige Kontrollieren dieser Konfiguration auf notwendige Änderungen. Zudem solltest Du
sämtliche nicht (mehr) verwendete Software rigeros von dem System entfernen, denn was nicht vorhanden ist, kann auch
nicht angegriffen werden.

Stelle Dir einfach vor, das Dein Server ein Schlachtfeld in einem grossen Kriegsgebiet ist und baue Dir dementsprechend
mehrere Verteidigungslinien auf. So kann ein Angreifer durchaus mal die erste Verteidigungslinie durchbrechen ohne
gleich die Schlacht oder gar den Krieg gewonnen zu haben. Aber bedenke: Grosse Feldherren lernen immer aus fremden und
ihren eigenen Fehlern und begehen diese kein zweites Mal. Lege Dir also ein Sicherheitskonzept an und überarbeite
dieses permanent, sonst verlierst Du.

Kannst Du diese Sicherheit gewährleisten?

### Viertes Problem: Die Rechtslage

Fangen wir mit der leichtesten Frage an: Gewerblich oder Privat? Sobald Du auch nur einen Cent mit Deinem Angebot
verdienst und wenn es nur ein Werbebanner oder Partnerprogramm ist, gilt Dein Angebot nach aktueller Rechtssprechung
mehrerer Gerichte bereits als gewerbliches Angebot. Gewerbliche Angebote müssen einige gesetzliche Auflagen erfüllen,
angefangen beim ordnungsgemässen Impressum und gegebenenfalls bis hin zum eigenen Datenschutzbeauftragten und anderen
rechtlichen Stolperfallen. Selbst bei manchem privaten Angebot können die Impressumspflicht und andere gesetzliche
Bestimmungen greifen.

Unabhängig davon bist Du generell für Alles was auf Deinem Server passiert rechtlich in vollem Umfang verantwortlich.
Schafft es bespielsspeise ein Script-Kiddie oder Cracker (in den Medien auch gerne Hacker genannt) in Dein System
einzubrechen und dort Warez, Pornos, Malware oder sonstiges verbotenes Zeugs abzulegen, so bist ausschliesslich Du
dafür rechtlich haftbar. Das Schlimmste daran ist, dass man Dir nicht die Schuld sondern Du Deine Unschuld nachweisen
musst und das ist auch für Profis und mit Hilfe eines guten Anwalts nicht einfach. Rechtsschutzversicherungen helfen
einem hier übrigens in der Regel auch nicht, da diese Fälle meist nicht abgedeckt sind. Im günstigsten Fall kommst Du
einer schmerzhaften Geldstrafe davon und im schlimmsten Fall bedeutet dies eine mehrjährige Gefängnisstrafe.

Bist Du Dir Deiner Verantwortung bewusst?

### Fünftes Problem: Der Mensch

Stelle Dir vor Du willst mit Deiner Familie einen entspannten zweiwöchigen Urlaub in der Karibik verbringen. Oder Du
rutscht unglücklich beim Duschen aus und brichst Dir ein paar Rippen. Oder Du wirst von Deinem Arbeitgeber auf
Dienstreise geschickt. Oder es passiert sonstetwas Unvorhergesehenes und Du kannst Dich daher längere Zeit nicht um
Deinen Server kümmern. Wer betreut in dieser Zeit den Server? Ist Derjenige vertrauenswürdig genug, das Du ihm das
Passwort für root anvertrauen kannst? Kennt er Dein System und Sicherheitskonzept ausreichend um sich schnell
zurechtzufinden und Nichts kaputtzumachen? Wie und wann benachrichtigst Du oder im schlimmsten Fall ein Anderer
(Familienangehöriger, Arbeitskollege, etc.) ihn?

Hast Du an mindestens einen Ersatzadmin gedacht?

## Problemlösungen

So, genug über Probleme geschrieben, kommen wir zu ein paar kleinen Hilfestellungen.

### Wo fange ich mit dem Lernen an?

Diese Frage ist schwer zu beantworten, da wir Deinen aktuellen Wissensstand nicht kennen. Aus diesem Grund fangen wir
mal mit dem Grundaufbau einer Linux-Distribution an. Dies ist bereits ein derart weitreichendes Thema, das wir es hier
gar nicht ausführlich behandeln können. Glücklicherweise brauchen wir das auch gar nicht, denn das haben Andere bereits
vor einigen Jahren begonnen und daraus ein sehr empfehlenswertes Online-Buch gemacht, zu finden unter
[LinuxFromScratch.org](https://www.linuxfromscratch.org/){: target="\_blank" rel="noopener"}. Nimm Dir für dieses Buch
bitte ein paar Wochen Zeit und lerne daraus so viel wie irgend möglich, Du wirst es später garantiert zu schätzen
wissen. Danach solltest Du Deine Shellkenntnisse stark erweitern, denn die Shell wird Dein schweizer Taschenmesser
werden. Auch zu diesem Thema haben wir eine sehr empfehlenswerte und umfangreiche Informationsquelle, nämlich den
[Advanced Bash-Scripting Guide](https://www.tldp.org/LDP/abs/html/){: target="\_blank" rel="noopener"}. Weitere gute
HowTos findest Du unter Anderem beim [Linux Documentation Project](https://www.tldp.org/HOWTO/HOWTO-INDEX/index.html){: target="\_blank" rel="noopener"}
und den diversen Projektseiten der Linux-Distributionen. Stellvertretend für Letztere seien an dieser Stelle die
[Gentoo Linux Documentation Resources](https://www.gentoo.org/support/documentation/){: target="\_blank" rel="noopener"},
die [openSUSE Documentation](https://doc.opensuse.org/){: target="\_blank" rel="noopener"} und die
[Debian Documentation](https://debian.org/doc/){: target="\_blank" rel="noopener"} genannt.

### Wo fange ich mit dem Üben an?

Selbstverständlich wie ein Arzt: Nie am lebenden Objekt! Wenn Du zu Hause noch einen zweiten Computer rumstehen hast
und dieser nicht gerade für die Familie gedacht ist, dann nimm diesen und installiere dort zunächst das bereits
erwähnte LinuxFromScratch. Alternativ zum Zweitrechner kannst Du auch eine virtuelle Maschine wie zum Beispiel
VirtualBox oder VMWare Workstation nutzen. Wenn Du glaubst Dich damit ausreichend auseinandergesetzt zu haben, dann
fängst Du mit dem sogenannten Distro-Hopping an, das heisst Du installierst Dir nach und nach die gängigen
Server-Distributionen (Gentoo, openSUSE, CentOS, Ubuntu, Debian) und lernst diese näher kennen. Du wirst dabei schnell
feststellen, das jede Distribution ihre Vor- und Nachteile hat und Du mit der Einen besser zurecht kommst, als mit der
Anderen. Nimm Dir für die Entscheidung welche Distribution Du letztendlich auf Deinem Server einsetzen möchtest bitte
Zeit, denn ein späterer Wechsel zu einer anderen Distribution ist nicht so unproblematisch, wie es im ersten Moment
scheinen mag.

### Wo finde ich weitere Hilfe?

Zunächst natürlich in der Dokumentation der jeweiligen Software. Wie Du aber schnell feststellen wirst, ist Diese
leider nicht immer ausreichend oder ungenau oder unvollständig oder einfach nur unverständlich formuliert. Auch
Suchmaschinen helfen Dir nur bedingt weiter, da etliche darüber zu findenden Hilfestellungen veraltet sind, oder Du
schlicht nicht die passenden Suchbegriffe findest. In diesen Fällen helfen Dir neben den, sofern vorhanden, direkten
Support-Angeboten der jeweiligen Software-Hersteller auch unabhängige Stellen wie die [RootForum
Community](https://www.rootforum.org/forum/){: target="\_blank" rel="noopener"} gerne weiter.

### Welche Alternativen habe ich?

Du hast zwei Alternativen: Webhosting und managed Server.

### Erste Alternative: Webhosting

Webhosting ist der Klassiker schlechthin und bereitet Dir am wenigsten Arbeit. Beim Webhosting musst Du Dich nur um den
Inhalt Deiner Website und das Updaten Deiner Webapplikationen kümmern, den Rest nimmt Dir Dein Webhosting-Anbieter ab.
Der Nachteil dieser Alternative ist, das Du keinerlei Einfluss auf das zugrundeliegende System hast und nur die Dinge
nutzen kannst, die Dir Dein Webhosting-Anbieter zur Verfügung stellt. Für gut 80% aller privaten und kleinen
gewerblichen Websites ist das Webhosting allerdings mehr als ausreichend und kostengünstig dazu.

### Zweite Alternative: Managed Server

Ein managed Server ist im Prinzip ein Zwitter aus dediziertem Server und dem klassischen Webhosting. Auch hier wird Dir
die Administration des zugrundeliegenden Systems vom Anbieter abgenommen. Im Gegensatz zum Webhosting hast Du hier aber
je nach Anbieter die Möglichkeit ein wenig Einfluss auf das System auszuüben. So kannst Du beispielsweise den Anbieter
darum Bitten eine bestimmte Konfiguration vorzunehmen oder eine von Dir benötigte Software zu installieren. Der
Nachteil dieser Alternative ist, das Du für viele Deiner Sonderwünsche auch gesondert zur Kasse gebeten wirst und Dir
mancher Anbieter nicht jeden Sonderwunsch erfüllen kann oder möchte. Dennoch sind managed Server für weitere 15% aller
privaten und kleinen bis mittleren gewerblichen Websites die richtige Wahl.

## Weitere Hilfe

Abschliessend möchten wir noch auf weitere Entscheidungshilfen verweisen, denn wir stehen mit unserer Sichtweise auf
das Thema Pro und Contra dedizierter Server nicht alleine da und haben vermutlich auch nicht alle Aspekte ausreichend
berücksichtigt. Die Nachfolgende Auflistung erhebt dabei keinen Anspruch auf Vollständigkeit:

- [Missverständnisse über dedizierte root-Server](https://wiki.hostsharing.net/index.php?title=Missverst%C3%A4ndnisse_%C3%BCber_dedizierte_root-Server){: target="\_blank" rel="noopener"}
- [Rootserver Checkliste](https://wiki.hostsharing.net/index.php?title=Rootserver_Checkliste){: target="\_blank" rel="noopener"}
- [Admins haften für ihre Server](https://serverzeit.de/tutorials/admins-haften){: target="\_blank" rel="noopener"}
- [Dein neuer Linux-Server](https://breadfish.de/thread/3568-dein-neuer-linux-server/){: target="\_blank" rel="noopener"}

Wie Du siehst, ist das Thema dedizierter Server sehr komplex und erfordert kontinuirlich viel Arbeit. Es liegt nun an
Dir Dich Pro oder Contra dedizierter Server zu entscheiden.

Wir hoffen, das wir Dir bei Deiner Entscheidung etwas weiterhelfen konnten.

Dein RootService Team
