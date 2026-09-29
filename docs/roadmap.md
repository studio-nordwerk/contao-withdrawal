# Roadmap

## Audit vor Veröffentlichung

- [x] Ausgangszustand und vorhandene Tests geprüft (Contao 5.7, PHP 8.3).
- [x] § 356a BGB an der [Primärquelle](https://www.gesetze-im-internet.de/bgb/__356a.html) gelesen (29.09.2026).
- [x] Formularzustände, mehrere Tabs, Wiederholung, CSRF und Missbrauchsschutz prüfen.
- [x] Zeitstempel, Eingaben, Barrierefreiheit und Übersetzungen prüfen.
- [x] Mailfehler, parallele Wiederholungen und Datenschutz prüfen.
- [x] Backend-Rechte, Cache/ESI und unterstützte Contao-Versionen prüfen.
- [x] Paketinhalt, Dokumentation und CI vorbereiten.
- [x] Abschließend `make reset && make check` ausführen und Befunde dokumentieren.

## Politur-Runde 1

- [x] Widerrufsbestätigung als überschreibbare Multipart-Twig-Mail mit unverändertem Textteil und schlichtem HTML-Teil.
- [x] Händleradresse und öffentlicher Pfad im Backend einstellbar; Umgebungsvariablen bleiben optionale Overrides. Browser- und Injection-Tests grün.
- [x] `make check` lokal grün (23 PHP-Tests, 20 Browser-Tests).
- [x] Politur-Commits bis `4982507` auf `main` gepusht; GitHub Actions für Dev und Prod grün.

## Politur-Runde 2 (Konsistenz mit Mini-Shop und Seminar)

- [x] Optionale Grundgestaltung `withdrawal-base.css` mit den gemeinsamen Design-Tokens (`--nw-*`), im Backend abschaltbar, mit Hell/Dunkel-Test der berechneten Radien und Höhen ([Design-Tokens](design-tokens.md)).
- [x] Der Titel der Funktion ist jetzt eine `h1` (vorher `h2`), damit die Seite eine Überschrift der obersten Ebene hat.
- [x] E2E-Test, dass `/` bei jeder Browsersprache auf die Startseite führt.

## Veröffentlichung

- [x] README deutsch/englisch, MIT-Lizenz und Composer-Metadaten vorbereitet.
- [x] Versionsangabe auf tatsächlich getestete Contao-5.7-/PHP-8.3-Basis begrenzt.
- [x] `.gitattributes` und automatischen Exporttest ergänzt.
- [x] GitHub-Actions-Workflow für `make check` in dev/prod vorbereitet.
- [x] Manager-ZIP-Build mit Version im Archiv und geprüftem Paketinhalt vorbereitet.

- [ ] Arne entscheidet offene Produkt- und Betriebsfragen aus dem Auditbericht.
- [ ] GitHub-Repository anlegen, Lizenz und Release prüfen, dann veröffentlichen (separater Auftrag).
- [ ] GitHub Actions im veröffentlichten Repository erfolgreich ausführen.
- [ ] Version taggen und Paket bei Packagist registrieren, Aktualisierung einrichten.
- [ ] Installation aus Packagist im Contao Manager prüfen.
- [ ] Release-Version festlegen, ZIP mit `make artifact VERSION=…` bauen und echten ZIP-Upload im Contao Manager verifizieren.

Abschluss: Beide frischen Prüfläufe (dev/prod) grün mit 22 PHP-Tests / 135 Assertions und 19 Browsertests. Befunde und Ausgaben: [Auditbericht](audit.md).

Beim ursprünglichen Audit wurde noch kein Remote angelegt oder Push ausgeführt. Die Politur-Runde wurde anschließend auf `main` veröffentlicht.
