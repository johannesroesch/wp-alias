# Administrationshandbuch – Alias Manager

Dieses Handbuch richtet sich an WordPress-Administratoren, die das Plugin installieren, konfigurieren und warten.

---

## Systemvoraussetzungen

| Komponente | Mindestversion |
|---|---|
| PHP | 8.1 |
| WordPress | 5.9 |
| MySQL | 5.7 |
| MariaDB | 10.3 |

Das Plugin hat keine externen PHP-Abhängigkeiten (kein Composer-Paket im Produktivbetrieb erforderlich).

---

## Installation

### Methode 1: Manuell via FTP / SFTP

1. Den Ordner `alias-manager` (oder den entpackten Inhalt aus `alias-manager.zip`) in das Verzeichnis `wp-content/plugins/` auf dem Server hochladen.
2. Im WordPress-Admin unter **Plugins → Installierte Plugins** das Plugin **Alias Manager** aktivieren.

### Methode 2: Upload über das WordPress-Admin-Backend

1. Im WordPress-Admin auf **Plugins → Installieren** klicken.
2. Den Tab **Plugin hochladen** wählen.
3. Die Datei `alias-manager.zip` auswählen und auf **Jetzt installieren** klicken.
4. Anschließend **Plugin aktivieren** klicken.

### Was bei der Aktivierung passiert

Beim Aktivieren legt das Plugin automatisch die Datenbanktabelle `{prefix}_aliases` an (Standard: `alias_manageres`). Bestehende Tabellen werden durch `dbDelta` nicht überschrieben.

---

## Berechtigungen

Das Plugin prüft die WordPress-Capability `manage_options` für alle Admin-Seiten. Nur Nutzer mit Administratorrolle (oder einer Rolle mit explizit zugewiesener `manage_options`-Berechtigung) können Aliase verwalten.

---

## Datenbanktabelle

```sql
CREATE TABLE alias_manageres (
    id         mediumint(9)  NOT NULL AUTO_INCREMENT,
    alias      varchar(255)  NOT NULL,
    target_url varchar(2000) NOT NULL,
    created_at datetime      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY  (id),
    UNIQUE KEY alias (alias)
);
```

- Der Spalte `alias` liegt ein `UNIQUE KEY` zugrunde – doppelte Alias-Pfade werden auf Datenbankebene verhindert.
- `target_url` unterstützt URLs bis 2000 Zeichen (entspricht der praktischen IE-Grenze und ist für alle gängigen URLs ausreichend).

---

## Plugin deaktivieren und deinstallieren

### Deaktivieren

Das Plugin kann jederzeit deaktiviert werden (**Plugins → Alias Manager → Deaktivieren**). Die Datenbanktabelle und alle gespeicherten Aliase bleiben erhalten, Weiterleitungen sind jedoch inaktiv.

### Deinstallieren / Tabelle entfernen

Das Plugin löscht die Datenbanktabelle **nicht** automatisch (Datenschutz vor versehentlichem Datenverlust). Um die Tabelle manuell zu entfernen:

```sql
DROP TABLE IF EXISTS alias_manageres;
```

Oder via phpMyAdmin / Adminer die Tabelle `alias_manageres` löschen.

---

## WordPress in einem Unterverzeichnis

Ist WordPress in einem Unterverzeichnis installiert (z. B. `https://example.com/blog`), erkennt das Plugin den Basis-Pfad automatisch über `home_url()` und schneidet ihn vom Request-Pfad ab. Es sind keine zusätzlichen Konfigurationsschritte nötig.

---

## Multisite

Das Plugin ist **nicht** für WordPress-Multisite-Netzwerke optimiert. Bei Netzwerkaktivierung wird die Tabelle nur für die Haupt-Site angelegt. Für Multisite-Support sind Anpassungen durch einen Entwickler erforderlich (siehe [Entwicklerdokumentation](developer-guide.md)).

---

## Caching

Da Weiterleitungen per `wp_redirect()` + `exit` vor dem Template-Rendering erfolgen, werden gecachte Seitenantworten (z. B. durch WP Super Cache, W3 Total Cache, LiteSpeed Cache) **nicht** ausgeliefert – der Alias-Check läuft immer. Seiten-Caches sind daher kompatibel mit diesem Plugin.

Wenn Sie einen Reverse-Proxy-Cache (z. B. Varnish, Cloudflare) einsetzen, beachten Sie:
- 301-Antworten werden von Proxies standardmäßig gecacht. Änderungen an einem Alias sind erst sichtbar, wenn der Cache-Eintrag abläuft oder manuell invalidiert wird.

---

## Troubleshooting

### Alias leitet nicht weiter

- Prüfen Sie, ob der Alias-Pfad exakt dem aufgerufenen URL-Pfad entspricht (ohne führenden/nachgestellten Schrägstrich).
- Stellen Sie sicher, dass das Plugin aktiviert ist.
- Prüfen Sie, ob ein anderes Plugin oder die `.htaccess` den Request vor WordPress abfängt.

### Fehlermeldung „Alias-Slug bereits vergeben"

Der eingegebene Pfad ist in der Datenbank bereits vorhanden. Verwenden Sie einen anderen Slug oder bearbeiten Sie den bestehenden Alias.

### Datenbanktabelle fehlt

Falls die Tabelle nach der Aktivierung nicht angelegt wurde (z. B. wegen fehlender Datenbankrechte), kann die Erstellung manuell angestoßen werden, indem das Plugin deaktiviert und erneut aktiviert wird. Alternativ das SQL aus dem Abschnitt [Datenbanktabelle](#datenbanktabelle) direkt ausführen.

### PHP-Fehler nach Update

Stellen Sie sicher, dass PHP 8.1+ aktiv ist. Die Funktion `str_starts_with()` (genutzt im Redirector) ist seit PHP 8.0 verfügbar, PHPUnit 10 setzt jedoch PHP 8.1 voraus.

---

## Sicherheitshinweise

- Alle Formulareingaben werden mit `sanitize_text_field()` bzw. `esc_url_raw()` bereinigt.
- Alle Admin-Aktionen (Speichern, Bearbeiten, Löschen) sind durch WordPress-Nonces (CSRF-Schutz) abgesichert.
- Die Adminseite ist hinter der `manage_options`-Capability geschützt.
