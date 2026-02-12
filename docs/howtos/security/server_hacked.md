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
date: '2003-02-18'
lastmod: '2020-02-18'
title: Vorgehensweise bei gehacktem Server
description: In diesem HowTo werden ein paar grundlegende Vorgehensweisen bei gehackten Servern gegeben.
keywords:
  - Vorgehensweise bei gehacktem Server
  - mkdocs
  - docs
lang: de
robots: index, follow
hide: []
search:
  exclude: false
---

## Einleitung

In diesem HowTo werden ein paar grundlegende Vorgehensweisen bei gehackten Servern gegeben.

Dein Server wurde gehackt und Du weisst nicht so recht wie Du nun weiter vorgehen sollst? Wir haben Dir nachfolgend ein
paar Punkte zusammengestellt, die Dir unserer Erfahrung nach in diesem Fall am Besten weiterhelfen sollten.

## Ruhe bewahren

Bitte bewahre erstmal Ruhe, denn ein hektischer Admin und ein gehackter Server sind eine gefährliche hochexplosive
Mischung. Das Kind ist ohnehin schon in den Brunnen gefallen, also bringt Dir ein überhastetes Handeln jetzt nur noch
weiteren vermeidbaren Ärger ein. Besorge Dir erstmal eine Kanne Kaffee oder Tee und lies Dir zunächst die folgenden
Punkte mindestens einmal durch, bevor Du anfängst diese nach und nach umzusetzen.

## Beweise sichern

Dieser sehr wichtige Punkt wird gerne vergessen, daher führen wir ihn gleich als Erstes durch. Hierzu bootest Du den
Server mit einem Rescuesystem und legst ein vollständiges Festplatten-Image des Systems an. Dieses lässt sich später in
einer virtuellen Maschine gefahrlos analysieren und so sowohl das Einfallstor als auch die vorgenommenen Manipulationen
aufspüren. Schliesslich nützt es Dir ja Nichts, wenn Du nicht weisst wie der Angreifer in Dein System eingedrungen ist
und was er dort getrieben hat.

Alternativ zum Festplatten-Image kannst Du auch vom Rescuesystem aus ein Vollbackup des Systems mittels `tar` anlegen,
dieses eignet sich zwar ebenfalls zur Analyse, aber nicht mehr für eine rechtliche Verfolgung des Täters. Daher
empfehlen wir grundsätzlich das Festplatten-Image zu bevorzugen.

## Nutzdaten sichern

Als Nächstes sicherst Du die Nutzdaten, also Alles was man nicht installieren kann. Dies sind zum Beispiel statische
Webseiten, Bilder, Videos, Datenbankinhalte, E-Mails und Zugangsdaten. In keinem Fall sicherst Du in diesem Schritt
irgendwelche Software, also Programme und Scripte, denn diese sind nach einem erfolgreichen Einbruch nicht mehr
vertrauenswürdig.

## Server reinstallieren

Nachdem Du die obigen Sicherungen angelegt hast, setzt Du den Server komplett neu auf. Dieser Schritt ist wichtig, da
Du nicht wissen kannst ob und welche Software vom Angreifer manipuliert wurde. Selbst wenn Du glaubst dies zu wissen,
kannst Du Dir nie zu 100% sicher sein, daher gilt es wieder eine vertrauenswürdige Basis, sprich ein sauberes
Grundsystem zu schaffen. Lösche zunächst die Festplatte(n) vom Rescuesystem aus mittels `dd if=/dev/zero of=/dev/sda`
(Linux) beziehungsweise `dd if=/dev/zero of=/dev/ad1` (FreeBSD), wobei /dev/sda beziehungsweise /dev/ad1 durch die
jeweilige Festplattenbezeichnung zu ersetzen ist. Dies führst Du bitte für alle vorhandenen Festplatten mindestens
einmal besser zweimal durch. Danach kannst Du das Betriebssystem in seiner Minimal-Version neu installieren und
konfigurieren. Es sollte nun also maximal das Basissystem installiert und der SSH-Daemon gestartet sein, mehr nicht.
Der Rest folgt später.

## Nutzdaten analysieren

Nun beginnt die eigentliche Arbeit, das Analysieren der gesicherten Daten. Bei den Nutzdaten ist es noch relativ
leicht, denn diese brauchst Du nur mit einem 100% sauberen Backup zu vergleichen und die seitdem Backup hinzugekommenen
Nutzdaten manuell zu sichten. Wenn Du Dir zu 100% sicher bist, das die Nutzdaten sauber sind, kannst Du mit dem
Analysieren der Beweise weitermachen.

## Beweise analysieren

Jetzt kommt der schwierigste und zeitaufwendigste Teil der Arbeit, das Analysieren der Beweissicherung. Dazu legst Du
Dir zunächst ein weiteres Backup des Festplatten-Images an, das benötigen nämlich unter Umständen die
Strafverfolgungsbehörden für eigene Analysen. Du legst Dir nun mittels VirtualBox oder VMWare Workstation eine neue
virtuelle Maschine an und weist dieser das Festplatten-Image zu (Wie das im Einzelnen geht, steht in der jeweiligen
Dokumentation). Achte darauf, das Du der virtuellen Maschine kein Netzwerkinterface zuweist, denn Du weisst ja noch
nicht, was der Angreifer mit Deinem System gemacht hat. Etwaige Bootprobleme musst Du mit Hilfe der Dokumentation zu
Deinem Betriebssystem selbst lösen, das ist im Rahmen dieses Leitfadens nicht möglich. Häufige Stolpersteine sind hier
die Hardwarekonfiguration (z. B. udev unter Linux), der Kernel und seine Module oder das fehlende Netzwerkinterface.
Zum Beheben dieser Probleme empfiehlt sich der Einsatz einer RescueCD wie der
[SystemRescueCD](https://www.system-rescue.org/){: target="\_blank" rel="noopener"}. Nachdem Du diese Hürde gemeistert
hast, fängst Du zunächst damit an, das Einfallstor zu finden, damit Dein neues System darüber nicht gleich wieder
kompromittiert wird. Häufig werden Webapplikationen, also PHP, Perl, Rails und andere Scripts als Einfallstor genutzt,
daher solltest Du dort als Erstes nachsehen. Darüberhinaus werden gerne bekannte Sicherheitslücken in Systemsoftware
ausgenutzt, das wäre also die nächste zu überprüfende Baustelle.

All dies ist eine sehr komplexe und schwere Aufgabe und es ist kein Grund sich zu schämen wenn man sie nicht allein
gelöst bekommt. Selbst viele langjährige professionelle System-Administratoren haben hierbei ihre kleinen und grosse
Probleme. In diesem Fall muss man sich dann aber konsequenterweise auch eingestehen, dass man es selbst nicht schafft
und etwas Geld in die Hand nehmen und ein darauf spezialisiertes Unternehmen mit der Aufgabe betreuen. Das ist
letztendlich immer günstiger als wenn das System in kurzer Zeit erneut kompromittiert wird und darüber illegale
Aktivitäten wie (D)DoS, Brute-Force und andere Angriffe durchgeführt oder Spam, Warez, Musik, Filme oder gar
Kinder-Pornos verbreitet werden. Denn für all dies wirst zunächst Du als Besitzer des Servers haftbar gemacht und das
wird sehr schnell sehr teuer!

## Sicherheitskonzept anpassen

Jeder gute Administrator hat ein eigenes auf den jeweiligen Server angepasstes Sicherheitskonzept. Dieses
Sicherheitskonzept erweitert er ständig, um mit den täglichen Fortschritten der bösen Buben (BlackHats) Schritt zu
halten und so die meisten älteren und aktuellen bekannten Bedrohungen im Vorfeld von seinem Server abwenden zu können.
Auf Basis der Erkenntnisse aus der vorherigen Analyse wirst Du nun also auch Dein Sicherheitskonzept erweitern und
darauf achten, das dieses Sicherheitsproblem künftig auf Deinem Server nicht mehr auftritt.

## Software installieren

Nun kannst Du die von Dir benötigte Software installieren und konfigurieren. Achte aber bitte darauf, wirlich nur die
zwingend benötigte Software zu installieren, denn jede einzelne Software birgt das Risiko einer unbekannten
Sicherheitslücke. Anders ausgedrückt: Jede Datei, die nicht auf Deinem Server existiert, kann auch nicht für einen
neuen Einbruch genutzt werden. Auch bei der Konfiguration lasse bitte Vorsicht walten und informiere Dich vorher was
jede einzelne Konfigurationsoption bewirkt. Auch die Wechselwirkungen zwischen den Anwendungen und Konfigurationen
solltest Du berücksichtigen, denn manche unglückliche Kombinationen machen einen Angriff erst möglich. Vergesse bitte
auch die Softwareupdates nicht, spiele diese immer sofort und regelmässig ein!

## Nutzdaten einspielen

Es ist nun an der Zeit die Nutzdaten wiederherzustellen. Auch hier gilt wieder: Weniger ist mehr. Nutzdaten sind zwar
im Regelfall eher unkritisch, aber bei dieser Gelegenheit kann man sich auch gleich von veralteten, unnötigen Daten
trennen. Das trägt nicht nur zum Datenschutz bei sondern verkürzt auch die Analyse bei einem etwaigen zukünftigen
Einbruch.

## Server online nehmen

Du hast es geschafft und kannst den Server wieder für den Normalbetrieb online nehmen.

## Abschliesssende Worte

Wir hoffen, das wir Dir etwas weiterhelfen konnten.

Dein RootService Team
