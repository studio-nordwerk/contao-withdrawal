# Contao Withdrawal Bundle

Shopunabhängige elektronische Widerrufsfunktion nach [§ 356a BGB](https://www.gesetze-im-internet.de/bgb/__356a.html): zwei Schritte, kein Login, kein JavaScript, drei fachliche Pflichtangaben. MIT-Lizenz.

**Voraussetzungen:** Contao `^5.7.12`, PHP `^8.3`, MySQL/MariaDB und ein konfigurierter Symfony-Mailtransport. Getestet mit **Contao 5.7.13, PHP 8.3.35, MariaDB 11.8.9**, in `dev` und `prod` mit Seiten-Cache. **Contao 5.3 ist nicht freigegeben und wird nicht getestet.**

## Deutsch

Das Bundle stellt den Ablauf bereit. Ob es für einen konkreten Vertrag und die konkrete Händlerwebsite rechtlich geeignet ist, muss gesondert geprüft werden. **Keine Rechtsberatung.** Die Prüfung der Berechtigung erfolgt nach Eingang; das Formular lehnt keine Erklärung wegen einer vermuteten Fristüberschreitung ab.

### Installation und Einrichtung

**Vorher Contao aktualisieren.** Die Pakete brauchen Contao 5.7.12 oder neuer. Bitte zuerst im Contao Manager alle Pakete aktualisieren und danach die ZIPs hochladen. Hintergrund: Ältere 5.7-Versionen vertragen `league/flysystem-bundle` 3.7 nicht, das der Manager beim Upload mit aktualisiert; `contao-setup` bricht dann mit „Class AdapterDefinitionFactory not found“ ab.

1. `nordwerk/contao-withdrawal-bundle` im Contao Manager suchen oder mit `composer require nordwerk/contao-withdrawal-bundle` installieren und die Contao-Datenbankmigration ausführen.
2. Unter **Inhalte → Widerruf einrichten** die Absender- und Händleradresse speichern; Symfony Mailer mit einem zustellfähigen Transport konfigurieren. `WITHDRAWAL_MERCHANT_EMAIL` ist ein optionaler Override. Der Versand verwendet **synchron den Standardtransport** (`mailer.default_transport`), unabhängig von Messenger-Routing. Ein im Seitenstamm gewählter anderer Transport wird nicht übernommen. Keine `null://`-Konfiguration im Betrieb verwenden.
3. Eine öffentliche, ungeschützte Seite mit dem Inhaltselement **Widerrufsfunktion** anlegen. Den öffentlichen Pfad unter **Widerruf einrichten** speichern, etwa `/withdrawal`, `/service/widerruf` oder `/withdrawal.html`. `WITHDRAWAL_PATH` ist ein optionaler Override. Der Installations-Unterpfad wird automatisch ergänzt. Ein Pfad und eine Händleradresse gelten für die gesamte Installation.
4. `{{withdrawal_link}}` im Footer jeder relevanten Seite einbinden. In Contao 5.7 kann dafür ein Inhaltselement des **Themes** verwendet werden. Lesbarkeit, Kontrast, Hervorhebung und Erreichbarkeit während der gesamten Widerrufsfrist im tatsächlichen Theme prüfen. Keine Anmeldung, Consent-Sperre, zeitgesteuerte Ausblendung oder Cache-Regel vor das Formular setzen.
5. Backend-Benutzern oder Gruppen ausdrücklich das Modul **Widerrufe** erlauben. Administratoren haben Zugriff. Erklärungsdaten sind schreibgeschützt; nur der Prüfstatus wird bearbeitet. Für Aufbewahrung, Löschung und Backups einen betrieblichen Prozess festlegen.
6. `php bin/console withdrawal:resend-pending` zeitnah und regelmäßig ausführen, beispielsweise minütlich per Cron. Exitcode `1` bedeutet mindestens einen Fehler. Offene Zustellungen und Bounces überwachen; ausbleibende Bestätigungen müssen bearbeitet werden.

### Verhalten und Grenzen

Pflichtangaben sind Name, freie Vertrags- oder Vertragsteil-Angaben und Bestätigungs-E-Mail. Eine Bestellnummer ist lediglich ein Beispiel. Es gibt keine Pflicht zu Begründung, Anschrift oder Anmeldung. Die Prüfung speichert noch keine Widerrufserklärung; erst **Widerruf bestätigen** tut dies. Zwei Tabs und alte Prüfansichten bleiben voneinander unabhängig. Wiederholte Bestätigung derselben Ansicht speichert nur einen Datensatz. Ein neu begonnener Ablauf ist eine neue Erklärung, auch bei identischen Angaben.

E-Mail-Adressen werden serverseitig geprüft, auch mit Unicode-Domain oder internationalem Lokalteil. Für Letzteren muss der Mailtransport SMTPUTF8 unterstützen. Es gibt keine Mindestwartezeit. Der Honeypot lässt nach versehentlichem Autofill einen verständlichen erneuten Versuch zu. Contaos CSRF-Prüfung bleibt aktiv; bei abgelaufener Sitzung muss das Formular erneut geöffnet und ausgefüllt werden. Fehlermeldungen, Tastaturbedienung, Fokuswechsel und Korrektur der Prüfansicht funktionieren ohne JavaScript.

Der HTTP-Eingang der Bestätigungsanfrage wird vor der weiteren Verarbeitung erfasst und in UTC mit Mikrosekunden gespeichert. Frontend, Backend und Mails zeigen `Europe/Berlin` mit UTC-Offset, auch in der doppelt vorkommenden Herbststunde. **Dieser Serverzeitpunkt beweist nicht den Absendezeitpunkt beim Verbraucher.** § 356a Abs. 5 berücksichtigt das rechtzeitige Absenden; bei einem Eingang nach Mitternacht bleibt eine individuelle Prüfung erforderlich.

Die Erklärung ist vor jedem Mailversuch dauerhaft gespeichert. Kundenbestätigung und Händlernachricht werden getrennt verarbeitet. Datenbanksperren verhindern überlappende Versandversuche; bereits quittierte Teilzustellungen werden übersprungen. `sent` bedeutet **vom Transport angenommen**, nicht nachweislich im Postfach zugestellt. Bei SMTP-Annahme mit anschließendem Prozessabsturz oder unklarem Transportabschluss kann ein erneuter Versuch dennoch doppelt zustellen. Eine absolute Exactly-once-Garantie besteht nicht.

SMTP braucht endliche Zeitlimits in PHP-FPM **und** CLI (z. B. `default_socket_timeout=5` in der PHP-Konfiguration); andere Transporttypen brauchen passende eigene Timeouts. Der Test mit einem nicht antwortenden SMTP-Server prüft das Timeout-Verhalten und den erhaltenen offenen Datensatz. Versand und Integrations-Listener sind synchron: Lang laufende Listener und Transports können die HTTP-Antwort verzögern. Betreiben und überwachen Sie Wiederholungen und Prozesszeitlimits so, dass Eingangsbestätigungen unverzüglich versandt werden können.

Das Bundle speichert weder IP noch User-Agent. Fehlerausgaben enthalten nur Fehlerklasse und Datensatz-ID. Formulardaten liegen während der Prüfung in der Session; gespeicherte Erklärungen in `tl_withdrawal`. Produktiv den Debug-Modus abschalten: Webserver-, Mailserver-, Symfony-Profiler- und Backup-Daten liegen außerhalb der Datenspeicherung dieses Bundles. Formulare und Bestätigungen senden `private, no-store`; statische Seiten mit Footer-Link dürfen im HTTP-Cache bleiben. ESI-Unteranfragen behalten den Installationspfad.

### Anpassung und Integrationen

Die **Grundgestaltung** (Felder, Buttons, Hinweise) ist unter **Widerruf einrichten** voreingeschaltet und abschaltbar. Sie übernimmt Schrift und Farben der Website und folgt den gemeinsamen CSS-Variablen `--nw-*` von Mini-Shop, Seminar und Widerruf, siehe [Design-Tokens](docs/design-tokens.md). Der Titel der Funktion ist eine `h1`; bei Bedarf die Vorlage `withdrawal.html.twig` im Template Studio überschreiben.

Mailvorlagen: `@NordwerkWithdrawal/mail/consumer.txt.twig` und `merchant.txt.twig`, jeweils auch mit `.en.txt.twig`, sowie die entsprechenden `.html.twig`-Vorlagen. Die Nachrichten sind Multipart-Mails; beide Teile müssen Erklärung, Angaben, Eingangsdatum und -zeit enthalten und mit der Prüfansicht übereinstimmen.

`Nordwerk\WithdrawalBundle\Event\WithdrawalSubmittedEvent` enthält Datensatz-ID, Erklärung und UTC-Eingang. Das Event läuft nach Speicherung und erstem Mailversuch, nur beim neu angelegten Datensatz. Es ist **kein dauerhaftes Event-Outbox-System**; Abstürze und Listener-Fehler erfordern bei Shop-Anbindungen einen eigenen Abgleich. Listener dürfen keine langen oder unbegrenzten Arbeiten im Request ausführen.

### Entwicklung und Prüfung

```sh
cp .env.example .env
# APP_SECRET und CONTAO_ADMIN_PASSWORD durch eigene Werte ersetzen
make up
make reset && make check
# Produktionsmodus inklusive echtem Seiten-Cache:
APP_ENV=prod make reset && APP_ENV=prod make check
make down
```

Benötigt Docker Compose, Python 3, Node 24 und `vp` (Vite Plus 1.0.0). `make up` installiert PHP-/Node-Abhängigkeiten und Chromium. `make check` prüft Paketexport, Coding-Standards, Composer, Twig/YAML/Container, PHPStan, PHP-Tests und Playwright. GitHub Actions führt dieselbe Prüfung in `dev` und `prod` aus; der erste entfernte Lauf steht bis zur Veröffentlichung aus.

Demo: `http://localhost:8081/withdrawal`; Mailpit: `http://localhost:8026`. `make reset` verwirft nur die Datenbank dieser Demo auf tmpfs und baut sie neu auf. Keine echten Kundendaten in der Demo verwenden. `.env` und Entwicklungsartefakte werden nicht veröffentlicht; `.gitattributes` schließt Demo, Tests und Entwicklungswerkzeuge aus dem Composer-Export aus.

### Veröffentlichung und ZIP für den Contao Manager

[Roadmap](docs/roadmap.md) und [Auditbericht](docs/audit.md) dokumentieren Befunde, Tests und offene Betriebsfragen. Veröffentlicht auf GitHub und [Packagist](https://packagist.org/packages/nordwerk/contao-withdrawal-bundle).

Ein Manager-Artefakt benötigt eine `composer.json` **im ZIP-Wurzelverzeichnis mit `version`**. Der Build fügt die Versionsangabe nur dem Archiv hinzu; die Composer-Datei für Git/Packagist bleibt ohne fest eingetragene Version. Das folgt der [offiziellen Contao-Anleitung](https://docs.contao.org/5.x/dev/guides/publishing-bundles/).

```sh
# Erst den gewünschten Release-Stand committen. Beispielversion ersetzen.
make artifact VERSION=0.1.0
# Ergebnis: dist/contao-withdrawal-0.1.0.zip, Inhalt aus HEAD
```

Der Export wird automatisch geprüft. Releases mit Manager-ZIP stehen auf GitHub; Packagist aktualisiert sich über den GitHub-Hook.

## English

This MIT bundle provides a shop-independent two-step withdrawal function under [section 356a BGB](https://www.gesetze-im-internet.de/bgb/__356a.html). **This is not legal advice.** Merchants must assess applicability, placement and operation for their actual website. Eligibility is reviewed after receipt; the form does not reject declarations based on assumed deadlines.

Requires **Contao `^5.7.12`, PHP `^8.3`, MySQL/MariaDB and Symfony Mailer**. Tested with Contao 5.7.13, PHP 8.3.35 and MariaDB 11.8.9 in development and production with page caching. **Contao 5.3 is not tested or advertised as supported.**

After publication, install `nordwerk/contao-withdrawal-bundle`, run Contao's database migration, and set the merchant email and public path under **Content → Withdrawal settings**. Configure a working default Symfony mail transport. `WITHDRAWAL_MERCHANT_EMAIL` and `WITHDRAWAL_PATH` are optional overrides. The bundle sends synchronously through `mailer.default_transport`, bypassing Messenger queues and per-page-root transport selection. Create an unprotected page containing **Withdrawal function**, and place `{{withdrawal_link}}` prominently in the footer of every relevant page. One merchant address and path apply to the whole installation. A subdirectory installation's base path is added automatically, including in ESI subrequests.

The optional base styling (on by default, switchable under the settings) follows the shared `--nw-*` CSS variables described in [docs/design-tokens.md](docs/design-tokens.md); the title of the function is an `h1`. Exactly three business fields are required: name, free contract or partial-contract details, and confirmation email. No login, address, explanation or mandatory order number. The form supports keyboard use, correction and validation without JavaScript. Server validation accepts international email addresses; non-ASCII local parts require an SMTPUTF8-capable transport. There is no minimum dwell time. CSRF/session expiry requires reopening and completing the form. Each review is an immutable snapshot; repeated confirmation of it stores once. Starting a fresh flow creates a new declaration even with identical details.

HTTP arrival is recorded in UTC with microseconds, before routing/session delays. Frontend, backend and mail display Europe/Berlin with the UTC offset. **Server arrival does not prove when the consumer sent the declaration.** Section 356a(5) concerns timely sending; the bundle does not make an automated deadline decision.

Declarations are persisted before mail delivery. Consumer and merchant messages are independent, and retries skip acknowledged deliveries under a database lock. `sent` means transport acceptance, not proven inbox delivery. A crash after SMTP acceptance or an ambiguous transport result can still cause a duplicate on retry. Configure finite transport/process timeouts; for SMTP, PHP's `default_socket_timeout` must apply to both PHP-FPM and CLI. A stalled-SMTP test verifies that the record remains pending after timeout.

Schedule `php bin/console withdrawal:resend-pending` promptly and regularly, for example every minute. Monitor failures (exit code 1), pending records and bounces. Immediate receipt requires a working, supervised delivery setup. Decide retention, deletion and access policies. Administrators and backend users explicitly granted the **Withdrawals** module can access records; declaration fields are read-only.

The bundle stores no IP or user agent and logs only record IDs and failure classes. Review data is held in the session. Production debug mode, infrastructure logs and backups require separate configuration. Form responses use `private, no-store`; static footer pages remain cacheable.

German and English text mail templates can be overridden; retain the declaration, identifying details and receipt date/time. `WithdrawalSubmittedEvent` runs after persistence and the first mail attempt. It is synchronous and is not a durable event outbox; integrations need bounded listeners and their own reconciliation after failures.

Development: copy `.env.example` to `.env`, replace the secret/password, then run `make up` and `make reset && make check`. Production checks: `APP_ENV=prod make reset && APP_ENV=prod make check`. The demo uses ports 8081/8026. `make reset` discards its temporary database. GitHub Actions runs `make check` in both modes. Package exports exclude development files. `make artifact VERSION=0.1.0` builds a versioned Manager ZIP from the last commit; choose the intended release version first. Published on GitHub and Packagist; tags `v*` publish the stable channel.

## Lizenz / License

MIT, siehe / see [LICENSE](LICENSE).
