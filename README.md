# Contao Withdrawal Bundle

Kostenloses, shopunabhängiges MIT-Bundle für eine elektronische Widerrufsfunktion in Contao 5.3+. Entwickelt und lokal getestet mit Contao 5.7. Es enthält ein Inhaltselement, einen Footer-Insert-Tag, eine Backend-Liste, E-Mails und ein Event für Shop-Integrationen. Kein Login und kein JavaScript nötig.

## Deutsch

[§ 356a BGB](https://www.gesetze-im-internet.de/bgb/__356a.html) verlangt für einschlägige B2C-Fernabsatzverträge eine ständig verfügbare und leicht zugängliche Funktion „Vertrag widerrufen“, die drei Angaben ermöglicht, dann eine gesonderte Funktion „Widerruf bestätigen“ anbietet und den Eingang mit Inhalt, Datum und Uhrzeit unverzüglich auf einem dauerhaften Datenträger bestätigt. Ob ein Widerruf im Einzelfall berechtigt ist, prüft der Händler danach. **Dies ist keine Rechtsberatung.**

1. Paket `nordwerk/contao-withdrawal-bundle` installieren und die Contao-Migration ausführen.
2. `WITHDRAWAL_MERCHANT_EMAIL` auf eine gültige Händleradresse setzen und Symfony Mailer konfigurieren.
3. Eine öffentlich erreichbare Contao-Seite mit Alias `withdrawal` anlegen und das Inhaltselement **Widerrufsfunktion** einfügen. Bei anderem Pfad `WITHDRAWAL_PATH` setzen.
4. `{{withdrawal_link}}` in einem Footer-Inhaltselement oder Footer-Template jeder Seite einsetzen. Der Link ist bewusst deutlich zu gestalten; das mitgelieferte Demo-Layout zeigt ein Beispiel.
5. `withdrawal:resend-pending` regelmäßig per Cron ausführen. Fehlschlagende Mails bleiben als `pending` gespeichert und werden einzeln nachgesendet. Teilweise zugestellte Mails werden nicht doppelt versendet.

Die einzige verpflichtende Eingabe ist Name, Vertragsangabe und Bestätigungs-E-Mail. Ein unsichtbares Honeypot-Feld, eine kurze Zeitprüfung und Contao-CSRF schützen das Formular. Der Absendemoment wird beim Eingang der Bestätigungsanfrage als UTC-ISO-Zeitstempel mit Mikrosekunden gespeichert. Die Anzeige und beide Mails verwenden `Europe/Berlin`. Es werden weder IP noch User-Agent gespeichert. Die Erfolgsanzeige hängt nicht vom Erfolg des Mailversands ab.

Die Mailvorlagen `@NordwerkWithdrawal/mail/consumer.txt.twig` und `merchant.txt.twig` (sowie `.en.txt.twig`) lassen sich über Symfony-Bundle-Template-Overrides anpassen. Für Shop-Anbindungen wird nach dem Speichern `Nordwerk\WithdrawalBundle\Event\WithdrawalSubmittedEvent` ausgelöst. Das Event enthält Datensatz-ID, Erklärung und UTC-Zeitpunkt. Event-Listener dürfen die gesetzlich erforderliche Eingangsbestätigung nicht blockieren; Listener-Fehler werden protokolliert.

### Lokale Demo

```sh
cp .env.example .env
# CONTAO_ADMIN_PASSWORD und APP_SECRET durch eigene Werte ersetzen
make up
make check
make down
```

Die Demo liegt auf `http://localhost:8081/withdrawal`, Mailpit auf `http://localhost:8026`. `make reset` erstellt die Demo-Datenbank auf tmpfs frisch. `.env` bleibt lokal und wird nicht versioniert. `make e2e` startet nur `vp check` und die Playwright-Tests. Die Prüfung benötigt Docker, `vp` und einen installierten Chromium-Browser (`vp exec playwright install chromium`).

## English

This free MIT bundle adds a shop-independent withdrawal flow to Contao 5.3+. [Section 356a BGB](https://www.gesetze-im-internet.de/bgb/__356a.html) calls for an accessible withdrawal function, a separate confirmation action and an immediate receipt on a durable medium containing the declaration and receipt date and time. The merchant reviews eligibility later. **This is not legal advice.**

Install `nordwerk/contao-withdrawal-bundle`, run Contao's database migration, set `WITHDRAWAL_MERCHANT_EMAIL`, and configure Symfony Mailer. Create a public page with alias `withdrawal` containing the **Withdrawal function** content element. Put `{{withdrawal_link}}` in the footer on every page. Set `WITHDRAWAL_PATH` if the page uses a different path. Schedule `withdrawal:resend-pending` for failed delivery. The `WithdrawalSubmittedEvent` lets shops attach order-specific processing without coupling this bundle to a shop. German is the default; English interface and mail templates are included.

The form uses exactly three required fields, works without JavaScript, records the server receipt time in UTC with microsecond precision and shows it in the Europe/Berlin time zone. No IP address or user agent is stored.

## Lizenz / License

MIT, siehe [LICENSE](LICENSE).
