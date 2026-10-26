# contao-withdrawal

Shopunabhängige elektronische Widerrufsfunktion (§ 356a BGB) als Contao-5.7-Bundle `nordwerk/contao-withdrawal-bundle` (MIT, öffentlich). Mini-Shop und Seminar nutzen es mit (Mail-Editor, `MailOptions`, `MailDocument`, `WithdrawalSettings`, `tl_withdrawal`).
Lokal: `.env.example` nach `.env` kopieren (Secret/Passwort setzen), `make up`, dann `make reset && make check`; Produktionsmodus `APP_ENV=prod make reset && APP_ENV=prod make check`. Demo unter `http://localhost:8081/withdrawal`, Mailpit unter `http://localhost:8026`.
Manager-ZIP: `make artifact VERSION=x.y.z` (aus HEAD, geprüft von `scripts/check-package.py`).

## Zusammenarbeit

- Aufgaben stehen als Issues in [studio-nordwerk/contao-pakete](https://github.com/studio-nordwerk/contao-pakete/issues). Vor dem Start im Issue kurz schreiben, wer es übernimmt, damit nichts doppelt gebaut wird.
- Immer auf einem eigenen Branch arbeiten (`git switch -c <thema>`), nie auf `main`. `main` und `v*`-Tags sind per Ruleset gesperrt; nur Arne merged, taggt und veröffentlicht.
- Vor dem Pull Request lokal `make check` laufen lassen, bis alles grün ist. Auf GitHub laufen die langen Checks absichtlich nicht automatisch (Build-Minuten). Im PR kurz das Ergebnis nennen.
- Pull Request mit `gh pr create`, im Text `Closes studio-nordwerk/contao-pakete#<nr>`. Was geändert wurde, warum, und wie man es im Backend ausprobiert.
- Rechtlich relevante Texte (Widerruf, Pflichtangaben, Preise, Mails, Rechnungen) im PR ausdrücklich markieren. Sie werden vor dem Merge juristisch gegengelesen.
- Neue Abhängigkeiten zu anderen Nordwerk-Paketen oder Packagist-Paketen im PR nennen; Versionen in `composer.json` mit `^`-Bedingung.
- Nicht anfassen: Tags, GitHub-Releases, `.github/workflows/`, Secrets und Tokens, Uploads nach downloads.nordwerk.studio, die Fly-Demos. Das macht Arne.

## Release (nur Arne)

1. Changelog-Eintrag in `contao-pakete` und GitHub-Release-Notes **vor** dem Tag anlegen: Die Update-Mails an Lizenznehmer holen die Notizen von dort.
2. Tag `vX.Y.Z` pushen. Der Workflow veröffentlicht in den Kanal **stable** und löst Update-Mails aus. Jeder Merge nach `main` landet im Kanal **preview**.
3. Danach die Demos aktualisieren (`demo/update.sh` in `contao-themes`).

## Was Kundinnen und Kunden bekommen

Kunden erhalten nie das Repository, sondern pro Release ein Contao-Manager-ZIP (`make artifact`). Es enthält nur den Bundle-Code, `README.md`, `LICENSE` und die Installationsanleitung. Entwicklungsinterna (`app/`, `e2e/`, `docs/`, `scripts/`, Tests, Docker, Berichte) bleiben draußen; gesteuert über `export-ignore` in `.gitattributes` und das Build-Skript. Wer neue Entwicklungsdateien anlegt, ergänzt `export-ignore`. Keine internen Notizen, Kundendaten oder Zugangsdaten in den Bundle-Code.

## Contao-Konventionen (Pflicht)

Ziel: Wer Contao kennt, findet sich ohne unsere Doku zurecht und kann alles mit Bordmitteln pflegen. Abweichungen nur mit Begründung im PR.

- **Contao 5 und Symfony-Weg:** Inhaltselemente und Frontend-Module als Fragment-Controller mit `#[AsContentElement]` / `#[AsFrontendModule]`, Hooks und Callbacks per Attribut, Services per Autowiring. Keine Legacy-`$GLOBALS['TL_CTE']`/`TL_HOOKS`-Registrierung für neuen Code.
- **Templates:** Twig unter `contao/templates/` mit Standard-Namespace (`@Contao/...`), Contao-Namenskonventionen (`content_element/<typ>`, `frontend_module/<typ>`) und Blöcken, damit sie im Template Studio und in `templates/` überschreibbar bleiben. Keine Logik, die Overrides verhindert; keine fest verdrahteten Texte, sondern Übersetzungen.
- **Theme und Layout:** Gestaltung über `tl_theme`/Seitenlayouts im Theme-Manager. Kopf, Fuß und Navigation sind normale Frontend-Module oder Inhaltselemente in Layout-Bereichen, nicht hart im Template.
- **Datenmodell:** DCA-Erweiterungen unter `contao/dca/`, Standardfelder und -paletten (Überschrift, CSS-ID/Klasse, Unsichtbar, Anzeigen ab/bis, Zugriffsschutz) erhalten. Bilder über den Dateimanager (UUID) und Bildgrößen aus `tl_image_size`. Keine eigenen Tabellen, wo Contao-Tabellen reichen.
- **Backend:** Backend-Module im Standardmenü, Rechte über Benutzergruppen (neue Felder und Module in den Gruppenrechten freigebbar). Übersetzungen unter `contao/languages/de|en` bzw. `translations/`.
- **Assistenten** legen ausschließlich normale Contao-Datensätze an (Seiten, Artikel, Module, Layouts), die danach ohne Assistent pflegbar bleiben. Kein verstecktes Konfigurationsformat.
- **Migrationen** über `MigrationInterface`/`AbstractMigration`, idempotent, nie Daten der Nutzer überschreiben.
- **Frontend:** cachefreundlich (keine Session auf cachebaren Seiten), funktioniert ohne JavaScript, Contao-Suchindex, Sitemap, Canonical und `robots` bleiben intakt. CSS/JS als öffentliche Bundle-Assets (`public/`), überschreibbar über CSS-Variablen.
- **Qualität:** ECS, PHPStan, PHPUnit und Playwright über `make check`; neue Funktionen bekommen Tests, Bugfixes einen Test, der den Fehler zeigt.

## Hinweise für dieses Repo

- Bundle-Struktur: Contao-Ressourcen liegen unter `contao/` im Paketroot (`NordwerkWithdrawalBundle::getPath()` zeigt auf den Repo-Root). Kein `Resources/contao/` mehr anlegen; Contao lädt nur einen der beiden Ordner.
- Einstellungen: `tl_withdrawal_settings` ist ein regulärer `DC_Table`-Datensatz mit fester `id = 1` (Singleton, versioniert). Das Modul `withdrawal_settings` leitet per `onload_callback` auf `act=edit&id=1` um und legt den Datensatz bei Bedarf an (`src/Backend/SettingsDataContainer.php`). Mini-Shop, Seminar und die Theme-Demos schreiben direkt `id = 1` mit `merchantEmail` und `path`; diese Spalten und die ID nicht ändern.
- Validierung von Händleradresse und Pfad steckt in `WithdrawalSettings::isValidMerchantEmail()`/`isValidPath()`; `save_callback`, `save()` und Mailer nutzen dieselben Methoden.
- Mailtexte: eigene Ansicht `do=withdrawal_settings&key=mails` (`src/Backend/MailSettingsView.php`), damit gilt dasselbe Modulrecht. Alte Links `?do=withdrawal_settings&mail=…` werden umgeleitet. `mailOptions` ist von der Versionierung ausgenommen (`versionize => false`), damit eine wiederhergestellte Einstellungsversion keine Mailtexte zurückdreht. `MailEditor`, `MailOptions`, `EditableMail` und `MailDocument` sind öffentliche Schnittstellen für Mini-Shop und Seminar: Signaturen nur abwärtskompatibel erweitern.
- Die Felder des Einstellungsdatensatzes sind bewusst `exclude => false` und die Tabelle setzt `permissions => []` (keine Contao-5.7-CUD-Rechte): Zugriff regelt allein das Modulrecht wie vor dem Umbau; sonst sähen bestehende Gruppen nach dem Update ein leeres Formular bzw. bekämen 403 beim Bearbeiten.
- Frontend-Assets: `{% add "…" to head %}` im Block `style` der Elementvorlage; die URL kommt aus dem Asset-Paket `nordwerk_withdrawal` mit `public/manifest.json`. Nach Änderungen an `public/withdrawal-base.css` den Hash im Manifest anpassen (`make check` meldet den erwarteten Wert).
- Der Footer-Link `{{withdrawal_link}}` trägt das Cache-Tag `contao.db.tl_withdrawal_settings.1`; Speichern im Backend invalidiert gecachte Seiten.
- Die Docker-Projekte heißen fest `nordwerk-withdrawal` (Ports 8081/8026); parallel nur ein Stack.
