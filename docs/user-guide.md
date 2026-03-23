# Benutzerhandbuch – Alias Manager

Dieses Handbuch richtet sich an Redakteure und alle, die Aliase im WordPress-Backend verwalten möchten. Technische Vorkenntnisse sind nicht erforderlich.

---

## Was ist ein Alias?

Ein Alias ist ein alternativer URL-Pfad, der Besucher automatisch auf eine andere Seite weiterleitet. Beispiel:

- Besucher ruft `https://example.com/sommer` auf
- Das Plugin leitet ihn sofort zu `https://example.com/shop/aktionen/sommer-2024` weiter
- Die Weiterleitung erfolgt unsichtbar und im Hintergrund (HTTP 301)

Aliase sind nützlich für:
- Kurze, leicht merkbare URLs für Kampagnen oder Flyer
- Weiterleitungen nach einer Umstrukturierung der Seitenarchitektur
- Mehrere Einstiegspunkte für eine Seite (z. B. `produkte` und `leistungen` zeigen auf dieselbe Seite)

---

## Alias anlegen

1. Im WordPress-Admin auf **Einstellungen → Alias Manager** klicken.
2. Im Formular **„Neuen Alias anlegen"** die Felder ausfüllen:

### Alias-Pfad

Geben Sie nur den Slug ein – also den Teil der URL nach dem Schrägstrich. Beispiel:

- Gewünschte URL: `https://example.com/sommer-aktion`
- Alias-Pfad: `sommer-aktion`

Erlaubte Zeichen: Buchstaben, Zahlen, Bindestriche (`-`), Unterstriche (`_`), Schrägstriche für mehrstufige Pfade (`shop/sommer`).

### Seite auswählen (optional)

Wählen Sie eine veröffentlichte WordPress-Seite aus dem Dropdown. Das Ziel-URL-Feld wird automatisch ausgefüllt.

### Ziel-URL

Die vollständige Adresse, auf die der Alias weiterleiten soll. Muss mit `http://` oder `https://` beginnen. Sie können auch externe URLs eintragen.

3. Auf **„Alias anlegen"** klicken.

Bei Erfolg erscheint eine grüne Bestätigungsmeldung und der neue Alias ist sofort aktiv.

---

## Alias bearbeiten

1. In der Tabelle **„Vorhandene Aliase"** auf **Bearbeiten** neben dem gewünschten Alias klicken.
2. Das Formular öffnet sich mit den bestehenden Werten vor.
3. Änderungen vornehmen und auf **„Alias aktualisieren"** klicken.
4. Mit **„Abbrechen"** kehren Sie ohne Änderungen zur Liste zurück.

> **Hinweis:** Wenn Sie den Alias-Pfad ändern, funktioniert der alte Pfad nicht mehr. Informieren Sie ggf. Personen, die den alten Link kennen.

---

## Alias löschen

1. In der Tabelle auf **Löschen** (rot) neben dem gewünschten Alias klicken.
2. Eine Sicherheitsabfrage erscheint: **„Alias wirklich löschen?"**
3. Mit **OK** bestätigen.

Nach dem Löschen leitet der frühere Alias-Pfad nicht mehr weiter. Besucher, die den Pfad aufrufen, landen auf der WordPress-404-Seite.

---

## Übersichtstabelle

Die Tabelle unten im Bereich „Vorhandene Aliase" zeigt alle angelegten Einträge mit folgenden Spalten:

| Spalte | Beschreibung |
|---|---|
| Alias-Pfad | Der Slug inkl. vollständiger URL als Vorschau |
| Ziel-URL | Das Weiterleitungsziel als anklickbarer Link |
| Erstellt am | Datum der Anlage |
| Aktionen | Bearbeiten- und Löschen-Links |

---

## Häufige Fragen

**Kann ein Alias auf eine externe Website zeigen?**
Ja. Tragen Sie einfach die vollständige externe URL (z. B. `https://partner.de/angebot`) in das Ziel-URL-Feld ein.

**Kann ich mehrere Aliase für dieselbe Seite anlegen?**
Ja, beliebig viele. Jeder Alias muss jedoch einen eindeutigen Pfad haben.

**Was passiert, wenn der Alias denselben Pfad hat wie eine vorhandene Seite?**
Der Alias-Pfad hat Vorrang und die Weiterleitung wird ausgelöst, bevor WordPress die Seite lädt. Vermeiden Sie Konflikte mit vorhandenen Seiten-Slugs.

**Wie schnell ist die Weiterleitung aktiv?**
Sofort nach dem Speichern – kein Cache-Leeren oder sonstige Schritte nötig.
