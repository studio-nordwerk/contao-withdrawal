# Technisches Audit vom 29.09.2026

Geprüft wurde das shopunabhängige Bundle gegen die [amtliche Fassung des § 356a BGB](https://www.gesetze-im-internet.de/bgb/__356a.html), direkt abgerufen am 29.09.2026. Dieses Audit ist keine Rechtsberatung und keine Zertifizierung einer konkreten Händlerwebsite.

## Gesetz und Umsetzung

- Abs. 1: Deutscher Einstieg „Vertrag widerrufen“, ohne Anmeldung erreichbar. Sichtbarkeit, hervorgehobene Gestaltung und durchgängige Einbindung muss der Händler in seinem tatsächlichen Theme sicherstellen. Das Bundle berechnet keine Frist und blendet die Funktion nicht nach einem vermuteten Ablauf aus.
- Abs. 2: Genau drei fachliche Pflichtangaben: Name, freie Angaben zum Vertrag oder Vertragsteil und Bestätigungs-E-Mail. Keine Bestellnummer als zwingendes Format, keine Begründung, Adresse, Anmeldung oder Einwilligungscheckbox. CSRF-/Ablaufkennung sind technische Felder; der Honeypot ist keine fachliche Pflichtangabe.
- Abs. 3: Prüfung ohne Speicherung einer Erklärung, dann separate Schaltfläche „Widerruf bestätigen“. Datenänderungen erzeugen eine neue, unveränderliche Prüfansicht. Zwei Tabs bestätigen ihre jeweils angezeigten Daten.
- Abs. 4: Die Erklärung wird vor dem Mailversuch gespeichert. Beide reinen Textmails enthalten die Erklärung, die drei Angaben sowie Eingangsdatum und -zeit in Europe/Berlin mit UTC-Offset. Der synchrone Transport wird sofort versucht, bevor Integrations-Listener laufen. Fehler bleiben für die Wiederholung gespeichert. SMTP-Annahme ist kein Beweis für Postfachzustellung; Betrieb und Überwachung bleiben erforderlich.
- Abs. 5: Der gespeicherte Zeitpunkt stammt vom PHP-Eingang der Bestätigungsanfrage, vor Routing und Session-Wartezeit. Er ist **kein Beweis des Absendezeitpunkts beim Verbraucher**. Eine vor Mitternacht abgesandte, erst danach am Server eintreffende Erklärung darf nicht allein wegen dieses Zeitstempels automatisch abgelehnt werden. Das Bundle trifft keine solche Fristentscheidung.

## Behobene Befunde

| Schwere  | Befund und Nachweis                                                                                                                                                                                                      | Commit               |
| -------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------- |
| kritisch | Zwei Tabs: Bestätigung von Vertrag A speicherte Vertrag B. Browsertest prüft den tatsächlich gespeicherten Vertrag.                                                                                                      | `876c24c`            |
| kritisch | Alte Snapshots und überlappende Versandläufe erzeugten Doppelmails. Datenbanksperre und erneutes Lesen des Status; Tests mit zwei Verbindungen.                                                                          | `2993764`            |
| kritisch | Mailer konnte nur in eine Queue einreihen und dennoch „versandt“ speichern. Jetzt synchrone Transportannahme, Ablehnung bleibt offen.                                                                                    | `3cb1fe1`            |
| kritisch | Bei aktivem Seiten-Cache war das Formular öffentlich cachebar. Explizites `private, no-store`; Produktions-Browsertests prüfen getrennte Besucher und gleichzeitig cachebare statische Footer-Seiten.                    | `f78efef`            |
| mittel   | Schnelle Nutzer wurden als Spam abgewiesen; Autofill im Honeypot war nicht verständlich korrigierbar.                                                                                                                    | `f5bcd1c`            |
| mittel   | Unicode-Domains wurden abgewiesen, unsichtbar leere Namen und Steuerzeichen akzeptiert.                                                                                                                                  | `5141aee`            |
| mittel   | Ablehnung der Kundenadresse verhinderte auch die Händlernachricht. Empfänger jetzt unabhängig; erfolgreiche Teilzustellungen werden übersprungen.                                                                        | `3ac66e3`            |
| mittel   | SMTP-/Listener-Exceptions konnten personenbezogene Daten in Logs und Cron-Ausgaben übertragen. Nur noch Fehlerklasse und Datensatz-ID.                                                                                   | `1998ddc`            |
| mittel   | Ortszeiten konnten mit falschem UTC-Suffix gespeichert werden; Verarbeitung statt HTTP-Eingang wurde gemessen; Herbst-Doppelstunde war uneindeutig. Sommer/Winter, beide Umstellungen und 23:59:59.123456 sind getestet. | `1cb9746`            |
| mittel   | Integrations-Listener liefen vor der gesetzlichen Eingangsbestätigung und konnten diese verzögern. Reihenfolge korrigiert; Listener-Fehler beeinträchtigen Speicherung und Mailversuch nicht.                            | `fdd2718`            |
| mittel   | Feldbezogene Fehlerzuordnung, Fokus und Korrektur der Prüfansicht fehlten. Browsertests auch mit Tastatur und deaktiviertem JavaScript.                                                                                  | `c4f0f41`            |
| mittel   | Abgelaufener Ablauf erschien als leeres Formular; CSRF-Fehler bot keinen funktionierenden Reload ohne JavaScript. Verständliche Rückmeldung und normaler Link, ohne Umgehung der Prüfung.                                | `c629d99`            |
| mittel   | ESI-Unteranfrage verlor den Installations-Unterpfad im Footer-Link. Hauptanfrage liefert nun den Basispfad.                                                                                                              | `98c96f6`            |
| mittel   | Erklärungstext war auf den gesamten Vertrag verengt. Vertragsteil jetzt ausdrücklich in Prüfung und beiden Mailsprachen enthalten.                                                                                       | `be9dd04`            |
| mittel   | Das Demo-Footerelement hatte keinen gültigen Elterndatensatz und verschwand bei Backend-Bereinigung. Nun korrekt an das Theme gebunden; Rechte und manipulierte Schreibversuche ebenfalls im Browser geprüft.            | `05005ad`            |
| mittel   | `^5.3` versprach ungetestete Kompatibilität. Mindestversion auf Contao `^5.7`/PHP `^8.3` begrenzt und getestete Versionen ausdrücklich dokumentiert.                                                                     | `1230af2`            |
| mittel   | Backend zeigte rohe UTC-Zeiten statt der geforderten Berliner Anzeige. Liste, Bearbeitung und Details jetzt einheitlich mit Offset; gespeicherte Werte unverändert.                                                      | `2e6a05d`            |
| mittel   | HTML-`maxlength` kürzte UTF-16-Sonderzeichen still: 200 Zeichen wurden zu 127. Serverseitige Zeichenlimits und explizite Fehler erhalten den vollständigen eingegebenen Inhalt.                                          | `ddafbf7`            |
| mittel   | Der Browser lehnte internationale E-Mail-Lokalteile trotz gültiger Serverprüfung ab. Serverseitige Syntaxprüfung akzeptiert sie; SMTPUTF8-Anforderung dokumentiert.                                                      | `c5629a2`            |
| klein    | Backend-Liste entfernte HTML-artige Teile der Erklärung statt sie als Text darzustellen. Frontend und Backend auf unveränderte Textanzeige geprüft.                                                                      | `39c2855`            |
| klein    | Falsches Format der Modulübersetzung ließ den Breadcrumb einen Übersetzungsschlüssel anzeigen.                                                                                                                           | `507c2ea`            |
| klein    | Composer-Beschreibung und Suchbegriffe unvollständig. `§ 356a BGB` steht in der Beschreibung; `356a-bgb` ist das gültige Keyword, da Composer `§` in Keywords ablehnt.                                                   | `bd5ccac`            |
| klein    | Export enthielt Demo, Entwicklungsdateien und Tests; CI und ein versioniertes Manager-Artefakt fehlten. Export- und ZIP-Struktur werden geprüft, CI-Matrix für dev/prod ist vorbereitet.                                 | `3f56089`, `55530b8` |

Zusätzliche Absicherung: `643c20f` prüft einen echten, nicht antwortenden lokalen SMTP-Socket mit konfiguriertem Timeout, parallele Bestätigungsanfragen und alte Ansichten nach einer Korrektur. Der Versandfehler erhält den gespeicherten offenen Datensatz. Diese Testverbindungen verwenden ausschließlich die isolierte Demo.

Die Fehler wurden jeweils mit einem fehlgeschlagenen gezielten Test nachgestellt und nach der Korrektur mit `make check` geprüft. Reine zusätzliche Abdeckung und Dokumentation benötigten keinen künstlich fehlschlagenden Test. Die Tests enthalten synthetische Daten; keine echten Kundenmails werden versandt.

## Abdeckung und praktische Grenzen

- Frontend: Browserprüfung mit Chromium; zwei Tabs, paralleles Absenden, Wiederholung, Korrektur alter Ansichten, abgelaufene CSRF-/Ablaufkennung, Autofill, Tastatur und JavaScript aus.
- Barrierefreiheit: Labels, Fehlerzuordnung, Fokus und Korrektur sind automatisiert geprüft. Ein vollständiges WCAG-Audit mit realen Screenreadern und dem späteren Händlertheme ist nicht erfolgt.
- Eingaben: Unicode-Domain, internationaler E-Mail-Lokalteil und ergänzende Unicode-Zeichen, Längen, unsichtbar leere Werte, Steuerzeichen und Header-Injection. HTML-artiger Text wird im Frontend und Backend als Text angezeigt; Mails sind `text/plain`, Betreff und Absender enthalten keine Formulartexte.
- Rechte: Admin-Zugriff und Statusbearbeitung, Ablehnung eines angemeldeten Kontos ohne Modulrecht und eines direkten fremden Tabellenaufrufs; manipulierte readonly-Eingaben verändern die Erklärung nicht.
- Cache: dev und prod, aktivierter Seiten-Cache mit 300 Sekunden, mehrere Besucher sowie Anfragen eines ESI-fähigen Proxys. Unterverzeichnis-Pfad zusätzlich mit der Form der Contao-ESI-Unteranfrage geprüft.
- Datenschutz: Bundle-Datenfelder und Fehlerausgaben geprüft; keine IP-/User-Agent-Speicherung. Suchlauf über versionierte Dateien ohne Treffer für private Schlüssel, verbreitete Tokenformate oder persönliche absolute Entwicklungspfade. Dies ist keine Garantie gegen jeden denkbaren Geheimnistyp.
- Übersetzungen: deutsche Aktionsbeschriftungen, deutscher und englischer Mailinhalt sowie Backend-Modulbezeichnung geprüft. Mehrsprachige Formularpfade pro Installation sind weiterhin eine offene Produktfrage.
- Versionen: Contao 5.7.13, PHP 8.3.35, Doctrine DBAL 4.4.5, Symfony 7.4.19, MariaDB 11.8.9. Keine Prüfung auf Contao 5.3, anderen Browser-Engines oder einer realen produktiven Händlerinstallation.

## Abschlussprüfung

Beide vollständigen Läufe am 29.09.2026 endeten mit Exitcode **0**:

```text
make reset && make check
OK (22 tests, 135 assertions)
19 passed (31.2s)

APP_ENV=prod make reset && APP_ENV=prod make check
OK (22 tests, 135 assertions)
19 passed (21.8s)
```

Paketexport und Manager-ZIP-Struktur, ECS, Twig-CS, Composer validate/normalize, Twig-/YAML-/Container-Lint, PHPStan und Vite Plus ebenfalls grün. Die Demo steht abschließend wieder im Entwicklungsmodus auf 8081/8026.

Vollständige Ausgaben: [Entwicklungsmodus](audit-check-dev.txt), [Produktionsmodus](audit-check-prod.txt). ANSI-Steuerzeichen und der maschinenspezifische Pfad des `make`-Programms wurden normalisiert; es wurden keine Fehler aus den Ausgaben entfernt.

Der CI-Workflow wurde lokal syntaktisch geprüft und seine `make check`-Befehle ausgeführt. Ein tatsächlicher GitHub-Actions-Lauf und der tatsächliche Manager-Upload sind noch nicht durchgeführt.

## Offene Grenzen und Fragen an Arne

1. **Versandbetrieb (kritisch für den Einsatz):** Wer betreibt und überwacht SMTP, zeitnahe Wiederholungen und Bounces? Welcher Prozess korrigiert eine syntaktisch gültige, aber unzustellbare Kundenadresse? Ein dauerhaft fehlerhafter Transport erfüllt die Pflicht zur unverzüglichen Bestätigung nicht allein durch gespeicherte `pending`-Datensätze.
2. **Unklarer Versandabschluss (mittel):** Nach SMTP-Annahme mit anschließendem Prozessabsturz oder Verbindungsabbruch kann eine Wiederholung doppelt zustellen. Datenbank und SMTP haben keine gemeinsame Transaktion. Das Bundle bevorzugt erneute Zustellversuche; eine absolute Exactly-once-Garantie wäre ohne zusätzliche Infrastruktur bzw. Anbieterunterstützung unehrlich. Ist dafür später ein manueller Klärungsprozess oder ein Anbieter mit Idempotenz erforderlich?
3. **Aufbewahrung (mittel):** Wie lange sollen Erklärungen, offene Sitzungsdaten, Backups und technische Logs aufbewahrt werden, und wer darf sie löschen? Keine automatische Löschung ohne diese fachliche Entscheidung ergänzt.
4. **Mandanten und Sprachen (mittel):** Reicht eine globale Händleradresse und ein globaler Formularpfad pro Installation? Mehrere Händler, Domains oder sprachabhängige Zielseiten benötigen eine ausdrücklich festgelegte Zuordnung. Keine neue Multishop-Konfiguration im Audit eingeführt.
5. **Unterstützte Versionen (mittel):** Soll Contao 5.3 tatsächlich unterstützt werden? Eine Freigabe verlangt eine eigene Installations- und Testsuite auf 5.3; der Test mit 5.7 belegt sie nicht.
6. **Freigabe der Händlerwebsite (mittel):** Wer prüft vor dem Einsatz die konkrete Platzierung, Erreichbarkeit, Informationen zum Widerrufsverfahren, die Mailvorlagen und die rechtliche Eignung des gewählten Kommunikationskanals? Das Bundle bietet ausschließlich E-Mail an; § 356a Abs. 2 Nr. 3 formuliert allgemeiner. Ist dieser Kanal für euren konkreten Einsatz ausreichend? Der technische Test der Demo ersetzt diese Prüfung nicht.

## Veröffentlichungsstatus

Keine Remotes angelegt, nichts gepusht, keine Registrierung bei Packagist und kein Manager-Upload. Diese Schritte bleiben in [roadmap.md](roadmap.md) offen. Das benachbarte Mini-Shop-Repository und dessen Container wurden nicht verändert.
