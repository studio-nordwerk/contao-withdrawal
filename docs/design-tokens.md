# Gemeinsame Design-Tokens (`--nw-*`)

Mini-Shop, Seminar und Widerruf gehören zu einer Familie und sehen nebeneinander gleich aus. Dafür teilen sich alle drei Bundles einen kleinen, gleichnamigen Satz CSS-Variablen. Diese Datei ist in allen drei Repositories identisch.

Jedes Bundle bringt sinnvolle Standardwerte mit und funktioniert damit allein. Ein Theme setzt die Werte **einmal** (zum Beispiel auf `body` oder in einer Layout-Klasse), und alle drei Bundles folgen. Die Standardwerte stehen in `:where(:root)` und haben damit die Spezifität null: Jede Theme-Regel gewinnt, egal in welcher Reihenfolge die Stylesheets geladen werden.

## Die Tokens

| Token                  | Wirkung                                                   | Standard                                   |
| ---------------------- | --------------------------------------------------------- | ------------------------------------------ |
| `--nw-accent`          | Hauptfarbe: Buttons, Auswahl, Hervorhebungen              | `currentColor`                             |
| `--nw-accent-contrast` | Textfarbe auf `--nw-accent` (Hauptbutton)                 | `Canvas`                                   |
| `--nw-surface`         | Hintergrund von Feldern, Karten und Hinweisen             | `transparent`                              |
| `--nw-border`          | Linien und Kartenrahmen (dekorativ)                       | `color-mix(currentColor 24%, transparent)` |
| `--nw-field-border`    | Rahmen von Eingabefeldern, mindestens 3:1 zum Hintergrund | `color-mix(currentColor 58%, transparent)` |
| `--nw-focus`           | Farbe des Fokusrings                                      | `currentColor`                             |
| `--nw-focus-width`     | Stärke des Fokusrings                                     | `3px`                                      |
| `--nw-focus-offset`    | Abstand des Fokusrings                                    | `3px`                                      |
| `--nw-border-width`    | Rahmenstärke von Buttons und Feldern                      | `1px`                                      |
| `--nw-radius-control`  | Radius von Buttons **und** Feldern (derselbe Wert)        | `0.75rem`                                  |
| `--nw-radius-card`     | Radius von Karten, Bildern, Boxen und Hinweisen           | `1.125rem`                                 |
| `--nw-control-height`  | Mindesthöhe von Buttons und einzeiligen Feldern           | `2.75rem`                                  |
| `--nw-font-size`       | Schriftgröße in Buttons und Feldern                       | `1rem`                                     |
| `--nw-font-size-small` | Schriftgröße von Beschriftungen, Hinweisen und Bildtexten | `0.9rem`                                   |
| `--nw-space-xs`        | kleinster Abstand (Bildergalerie, Beschriftung)           | `0.5rem`                                   |
| `--nw-space-sm`        | Abstand zwischen Feldern und Absätzen                     | `0.75rem`                                  |
| `--nw-space-md`        | Lücke zwischen Karten und Spalten                         | `1.25rem`                                  |
| `--nw-space-lg`        | Innenabstand von Karten und Boxen                         | `1.5rem`                                   |
| `--nw-space-xl`        | großer Abstand zwischen Abschnitten                       | `2.5rem`                                   |

Schrift und Textfarbe übernehmen die Bundles von der Website (`font: inherit`, `color: inherit`). Eine eigene Schrift gehört ins Theme, nicht in die Tokens.

## Buttons und Felder

- **Hauptbutton:** Fläche `--nw-accent`, Text `--nw-accent-contrast`. Für die eine Handlung, die den Ablauf weiterführt (in den Warenkorb, zur Kasse, Jetzt buchen, Bestellung prüfen, Angaben prüfen, zahlungspflichtig bestellen).
- **Nebenbutton:** durchsichtige Fläche, Rahmen und Text in `--nw-accent`. Für Nebenwege (Warenkorb aktualisieren, Angaben korrigieren).
- Beide haben dieselbe Höhe, denselben Radius, dieselbe Rahmenstärke und Schriftgröße wie die Eingabefelder. Ein Fokusring aus `--nw-focus` gilt für alle bedienbaren Elemente.
- Ein Playwright-Test in der Integration (`integration/e2e/consistency.spec.ts` im Mini-Shop-Repository) misst die berechneten Werte auf den Seiten aller drei Bundles in Hell und Dunkel und schlägt fehl, wenn sie voneinander abweichen.

## Dunkle Darstellung

Die Tokens sind einfache Farbwerte. Ein Theme überschreibt sie unter `@media (prefers-color-scheme: dark)`. Auf dunklen Seiten muss `--nw-accent-contrast` gesetzt werden, weil `Canvas` sonst weiß bleibt, solange die Seite kein `color-scheme` deklariert.

Faustregeln für Kontraste (WCAG 2.2 AA): Text auf Flächen mindestens 4,5:1, Feldrahmen und Fokusring mindestens 3:1 zur Umgebung.

## Beispiel: ein Theme setzt alles einmal

```css
.my-theme {
  --nw-accent: #382f3d;
  --nw-accent-contrast: #fffaf7;
  --nw-surface: #fffaf7;
  --nw-border: #cfc4cb;
  --nw-field-border: #7c727b;
  --nw-focus: #765782;
  --nw-radius-control: 12px;
  --nw-radius-card: 22px;
}

@media (prefers-color-scheme: dark) {
  .my-theme {
    --nw-accent: #f6edf2;
    --nw-accent-contrast: #25202a;
    --nw-surface: #302936;
    --nw-border: #716473;
    --nw-field-border: #a39aa5;
    --nw-focus: #e8c4ed;
  }
}
```

## Ältere Variablen

Der Mini-Shop kennt weiterhin seine `--mini-shop-*`-Variablen (`accent`, `radius`, `gap`, `space`, `line`, `primary-background`, `primary-color`, `button-background`, `button-color`) und das Seminar seine `--seminar-*`-Variablen. Sind sie gesetzt, haben sie Vorrang und wirken wie zuvor; sind sie nicht gesetzt, gelten die `--nw-*`-Tokens. `--mini-shop-radius` steuert nur noch den Kartenradius.

## Wo welches Bundle die Tokens liest

| Bundle    | Stylesheet                                       | Einschalten                                   |
| --------- | ------------------------------------------------ | --------------------------------------------- |
| Mini-Shop | `bundles/minishop/mini-shop-base.css`            | Assistent, Schritt 8: Shop-Grundgestaltung    |
| Seminar   | `bundles/seminar/seminar.css`                    | im Seiten-Layout einbinden                    |
| Widerruf  | `bundles/nordwerkwithdrawal/withdrawal-base.css` | Inhalte, Widerruf einrichten: Grundgestaltung |
