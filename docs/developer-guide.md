# Entwicklerdokumentation – Alias Manager

Diese Dokumentation richtet sich an PHP-Entwickler, die das Plugin verstehen, erweitern oder in eigene Projekte integrieren möchten.

---

## Projektstruktur

```
alias-manager/
├── alias-manager.php                    # Plugin-Header, Einstiegspunkt, Hook-Registrierung
├── composer.json                   # Dev-Abhängigkeiten (PHPUnit, Brain\Monkey)
├── phpunit.xml                     # PHPUnit-Konfiguration
├── README.md
├── docs/
│   ├── user-guide.md
│   ├── admin-guide.md
│   └── developer-guide.md          # Diese Datei
├── includes/
│   ├── class-alias-db.php          # Datenbankschicht (CRUD)
│   └── class-alias-redirector.php  # Request-Interception und Redirect
└── admin/
    └── class-alias-admin.php       # Admin-UI (Menü, Formular, Tabelle)
└── tests/
    ├── bootstrap.php               # PHPUnit-Bootstrap
    └── Unit/
        ├── AliasDBTest.php
        ├── AliasRedirectorTest.php
        └── AliasAdminTest.php
```

---

## Architektur

### Schichtenmodell

```
┌─────────────────────────────────────────────┐
│              alias-manager.php                   │  Einstiegspunkt
│  register_activation_hook / add_action      │  Hook-Verdrahtung
└──────────┬───────────────────┬──────────────┘
           │                   │
  ┌────────▼──────┐   ┌────────▼────────┐
  │  Redirector   │   │   Admin-UI      │
  │  (init hook)  │   │ (admin_menu)    │
  └────────┬──────┘   └────────┬────────┘
           │                   │
           └─────────┬─────────┘
                ┌────▼─────┐
                │  DB-Layer │
                │ WP_Alias_DB│
                └─────┬─────┘
                      │
               ┌──────▼──────┐
               │  $wpdb / DB  │
               └─────────────┘
```

### Klassen

#### `WP_Alias_DB` (`includes/class-alias-db.php`)

Statische Utility-Klasse. Kapselt alle Datenbankoperationen. Kein Zustand – alle Methoden sind `static`.

| Methode | Beschreibung |
|---|---|
| `table()` | Gibt den vollständigen Tabellennamen mit WordPress-Präfix zurück |
| `create_table()` | Erstellt die Tabelle via `dbDelta` (idempotent) |
| `all()` | Gibt alle Aliase sortiert nach `alias ASC` zurück |
| `get(int $id)` | Gibt einen einzelnen Alias-Datensatz zurück |
| `find_by_alias(string $alias)` | Gibt `target_url` für einen Alias-Pfad zurück oder `null` |
| `insert(string $alias, string $target_url)` | Legt neuen Datensatz an |
| `update(int $id, string $alias, string $target_url)` | Aktualisiert bestehenden Datensatz |
| `delete(int $id)` | Löscht einen Datensatz |

#### `WP_Alias_Redirector` (`includes/class-alias-redirector.php`)

Läuft auf dem `init`-Hook. Prüft, ob der aktuelle Request-Pfad einem Alias entspricht, und führt ggf. einen 301-Redirect aus.

**Ablauf in `maybe_redirect()`:**

1. Frühe Rückgabe bei Admin, Ajax und Cron-Requests
2. Request-Pfad aus `$_SERVER['REQUEST_URI']` extrahieren
3. WordPress-Basis-Pfad (Unterverzeichnis-Installationen) abschneiden
4. Leerer Pfad → kein Redirect
5. `WP_Alias_DB::find_by_alias()` aufrufen
6. Bei Treffer: `wp_redirect($target, 301)` + `exit`

#### `WP_Alias_Admin` (`admin/class-alias-admin.php`)

Registriert eine Unterseite unter **Einstellungen** und stellt die komplette CRUD-UI zur Verfügung.

| Methode | Hook | Beschreibung |
|---|---|---|
| `init()` | `plugins_loaded` | Registriert `admin_menu`-Hook |
| `register_menu()` | `admin_menu` | Fügt Menüpunkt unter Einstellungen hinzu |
| `render_page()` | Callback | Rendert die komplette Admin-Seite |

---

## Datenbankschema

```sql
CREATE TABLE {prefix}_aliases (
    id         mediumint(9)  NOT NULL AUTO_INCREMENT,
    alias      varchar(255)  NOT NULL,        -- Alias-Pfad (Slug), z. B. "sommer-aktion"
    target_url varchar(2000) NOT NULL,        -- Vollständige Ziel-URL
    created_at datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    UNIQUE KEY alias (alias)                  -- Unique-Constraint verhindert Duplikate
);
```

---

## Hooks & Filter

Das Plugin stellt folgende WordPress-Hooks zur Verfügung, über die das Verhalten angepasst werden kann.

### Filter: `alias_manager_redirect_status`

Ändert den HTTP-Statuscode der Weiterleitung (Standard: 301).

```php
add_filter( 'alias_manager_redirect_status', function ( int $status, string $alias, string $target ): int {
    // Temporäre Weiterleitung für bestimmte Aliase
    if ( str_starts_with( $alias, 'temp-' ) ) {
        return 302;
    }
    return $status;
}, 10, 3 );
```

> **Hinweis:** Dieser Filter muss im Plugin selbst ergänzt werden (siehe [Erweiterungen](#erweiterungen)).

### Filter: `alias_manager_target_url`

Ermöglicht die Manipulation der Ziel-URL vor dem Redirect.

```php
add_filter( 'alias_manager_target_url', function ( string $target_url, string $alias ): string {
    // UTM-Parameter anhängen
    return add_query_arg( 'utm_source', 'alias', $target_url );
}, 10, 2 );
```

### Action: `alias_manager_before_redirect`

Wird aufgerufen, kurz bevor der Redirect ausgeführt wird.

```php
add_action( 'alias_manager_before_redirect', function ( string $alias, string $target_url ): void {
    // Redirect in eigenem Log erfassen
    error_log( "Alias Manager: {$alias} → {$target_url}" );
}, 10, 2 );
```

> **Hinweis:** Die obigen Filter und Actions sind Beispiele für eine mögliche Erweiterung. Sie müssen in der `WP_Alias_Redirector`-Klasse eingebaut werden (s. u.).

---

## Erweiterungen

### Filter in den Redirector einbauen

Um eigene Filter zu unterstützen, `maybe_redirect()` in `class-alias-redirector.php` wie folgt anpassen:

```php
if ( $target ) {
    $target = apply_filters( 'alias_manager_target_url', $target, $request_path );
    $status = apply_filters( 'alias_manager_redirect_status', 301, $request_path, $target );
    do_action( 'alias_manager_before_redirect', $request_path, $target );
    wp_redirect( $target, $status );
    exit;
}
```

### Eigene Admin-Spalten hinzufügen

Die Tabelle in `render_page()` kann durch Erweiterung der `WP_List_Table`-Klasse auf das WordPress-Standard-UI umgestellt werden. Das bietet u. a. sortierbare Spalten, Pagination und Bulk-Aktionen.

### Multisite-Support

Für Multisite-Netzwerke muss `create_table()` für jede Sub-Site separat ausgeführt werden:

```php
// In alias-manager.php statt register_activation_hook:
add_action( 'wpmu_new_blog', function ( int $blog_id ): void {
    switch_to_blog( $blog_id );
    WP_Alias_DB::create_table();
    restore_current_blog();
} );
```

---

## Tests ausführen

### Voraussetzungen

```bash
composer install
```

### Unit-Tests starten

```bash
composer test
# oder direkt:
./vendor/bin/phpunit
```

### Einzelnen Test ausführen

```bash
./vendor/bin/phpunit tests/Unit/AliasDBTest.php
./vendor/bin/phpunit --filter test_redirect_called_with_correct_url_and_status
```

### Test-Coverage (HTML-Report)

```bash
composer test-coverage
# Report liegt in: coverage/index.html
```

Erfordert Xdebug oder PCOV als PHP-Extension.

---

## Test-Architektur

Die Unit-Tests verwenden [Brain\Monkey](https://brain-wp.github.io/BrainMonkey/) zum Mocken von WordPress-Funktionen und [Mockery](http://docs.mockery.io/) für Objekt-Mocks (insbesondere `$wpdb`).

**Herausforderung `exit`:** Der Redirector ruft nach `wp_redirect()` `exit` auf. In Tests wird `wp_redirect` als Stub registriert, der stattdessen eine `RuntimeException` wirft. So wird der `exit`-Aufruf nie erreicht und PHPUnit kann die Exception abfangen und prüfen.

```php
Functions\expect('wp_redirect')
    ->once()
    ->with('https://example.com/pageA', 301)
    ->andReturnUsing(function () {
        throw new \RuntimeException('redirect_called');
    });

$this->expectException(\RuntimeException::class);
WP_Alias_Redirector::maybe_redirect();
```

---

## Code-Konventionen

- WordPress Coding Standards (WPCS) werden empfohlen.
- Alle Datenbankwerte werden mit `sanitize_text_field()` / `esc_url_raw()` bereinigt.
- Prepare-Statements via `$wpdb->prepare()` für alle parametrisierten Queries.
- Nonces für alle schreibenden Admin-Aktionen.
- Kein direkter Zugriff ohne `defined('ABSPATH')` Guard.

---

## Beitragen

1. Repository forken
2. Feature-Branch anlegen: `git checkout -b feature/meine-funktion`
3. Tests schreiben und alle bestehenden Tests grün halten: `composer test`
4. Pull Request erstellen

Vor dem PR sicherstellen, dass keine PHPUnit-Fehler vorliegen und der Code den WordPress-Coding-Standards entspricht.
