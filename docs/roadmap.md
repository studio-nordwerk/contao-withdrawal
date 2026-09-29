# Roadmap

## Audit vor Veröffentlichung

- [x] Ausgangszustand und vorhandene Tests geprüft (Contao 5.7, PHP 8.3).
- [x] § 356a BGB an der [Primärquelle](https://www.gesetze-im-internet.de/bgb/__356a.html) gelesen (29.09.2026).
- [ ] Formularzustände, mehrere Tabs, Wiederholung, CSRF und Missbrauchsschutz prüfen.
- [ ] Zeitstempel, Eingaben, Barrierefreiheit und Übersetzungen prüfen.
- [ ] Mailfehler, parallele Wiederholungen und Datenschutz prüfen.
- [ ] Backend-Rechte, Cache/ESI und unterstützte Contao-Versionen prüfen.
- [ ] Paketinhalt, Dokumentation und CI vorbereiten.
- [ ] Abschließend `make reset && make check` ausführen und Befunde dokumentieren.

## Veröffentlichung

- [ ] Arne entscheidet offene Produkt- und Betriebsfragen aus dem Auditbericht.
- [ ] GitHub-Repository anlegen, Lizenz und Release prüfen, dann veröffentlichen (separater Auftrag).
- [ ] GitHub Actions im veröffentlichten Repository erfolgreich ausführen.
- [ ] Version taggen und Paket bei Packagist registrieren, Aktualisierung einrichten.
- [ ] Installation aus Packagist im Contao Manager prüfen.
- [ ] Release-ZIP mit `git archive` bauen; ZIP-Upload im Contao Manager separat verifizieren.

Es wird im Audit kein Remote angelegt und nichts gepusht.
