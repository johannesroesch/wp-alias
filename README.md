# Alias Manager

WordPress-Plugin zum Verwalten von URL-Aliasen mit automatischer 301-Weiterleitung.

## Überblick

Alias Manager erlaubt es, beliebig viele alternative Pfade (Aliase) für bestehende WordPress-Seiten anzulegen. Ruft ein Besucher einen Alias-Pfad auf, wird er transparent per HTTP 301 auf die hinterlegte Zielseite weitergeleitet – suchmaschinenfreundlich und ohne sichtbaren Umweg.

**Beispiel:**
`https://example.com/sommer-aktion` → `https://example.com/shop/aktionen/sommer-2024`

## Dokumentation

| Zielgruppe | Dokument |
|---|---|
| Redakteure / Endnutzer | [Benutzerhandbuch](docs/user-guide.md) |
| Administratoren | [Administrationshandbuch](docs/admin-guide.md) |
| Entwickler | [Entwicklerdokumentation](docs/developer-guide.md) |

## Schnellstart

1. Plugin-Ordner `alias-manager` nach `wp-content/plugins/` kopieren
2. Im WordPress-Admin unter **Plugins** aktivieren
3. Unter **Einstellungen → Alias Manager** Aliase anlegen

## Anforderungen

- PHP 8.1 oder höher
- WordPress 5.9 oder höher
- MySQL 5.7 / MariaDB 10.3 oder höher

## Lizenz

GPL-2.0+
