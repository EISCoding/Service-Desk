# ServiceDesk – Aufgabenplan

Sechs Phasen. Jede Phase endet mit einem lauffähigen, getesteten Stand.
Abhaken mit `[x]`, sobald eine Aufgabe fertig und getestet ist.

## Festgelegte Annahmen
- Ticketnummer: `JJJJ-NNNNN` (z. B. `2026-00123`), Zähler pro Jahr
- Prioritäten: Niedrig, Normal, Hoch, Dringend (ohne SLA-Zeiten)
- Kundenantwort auf geschlossenes Ticket: innerhalb von 14 Tagen → Ticket wieder „In Arbeit“, danach neues Ticket (Frist im Admin einstellbar)
- Status (fest, als PHP-Enum): Neu, In Arbeit, Warte auf Kunden, Warte auf Intern, Warte auf Extern, VOS (Vor-Ort-Service/Kundentermin), Geschlossen

---

## Phase 1 – Grundgerüst

- [x] Laravel-Projekt anlegen, Pest, Tailwind, Livewire, Alpine einrichten
- [x] `.env.example` mit allen benötigten Variablen, README mit lokalem Setup
- [x] Plesk-Deploy-Skript `deploy.sh`: `composer install --no-dev -o`, `npm ci && npm run build`, `php artisan migrate --force`, `config:cache`, `route:cache`, `view:cache`
- [x] Scheduler-Eintrag für die Queue (`queue:work --stop-when-empty --max-time=50`, jede Minute, `withoutOverlapping`)
- [x] Mitarbeiter-Login unter `/intern/login` mit Fortify, Passwort-Reset, 2FA (TOTP) optional pro Benutzer
- [x] Rollen und Rechte mit spatie/laravel-permission; Seeder mit Rollen `Admin`, `Mitarbeiter` und allen Rechten (siehe unten)
- [x] Gruppen (`groups`, Pivot `group_user`), Verwaltung unter `/intern/admin/gruppen`
- [x] Benutzerverwaltung unter `/intern/admin/benutzer` (anlegen, deaktivieren, Rollen, Gruppen)
- [x] Layout `/intern`: Sidebar (Tickets, Kunden, Termine, Vorlagen, Admin), Topbar mit Suche und Benutzermenü
- [x] Design-System als Blade-Komponenten: Button, Input, Select, Card, Badge/Pill, Modal, Tabelle, Leerzustand, Toast
- [x] Darkmode: System/Hell/Dunkel, ohne Flackern beim Laden
- [x] Activity-Log-Grundlage (`activity_log`: Benutzer, Objekt, Aktion, Änderungen als JSON, Zeit)

**Rechte:** `tickets.view.own`, `tickets.view.group`, `tickets.view.all`, `tickets.edit`, `tickets.delete`, `tickets.merge`, `notes.manage`, `customers.manage`, `contacts.portal`, `templates.manage`, `admin.users`, `admin.groups`, `admin.mailboxes`, `admin.settings`

**Fertig wenn:** Admin kann sich mit 2FA anmelden, Benutzer und Gruppen anlegen, Darkmode funktioniert, Tests für Login und Rechte laufen grün.

---

## Phase 2 – Kunden

- [x] Migration + Model `customers`: Kundennummer (automatisch, überschreibbar, eindeutig), Firma, Straße, PLZ, Ort, Land, E-Mail, Telefon, Website, aktiv/archiviert
- [x] Migration + Model `contacts`: Kunde, Anrede, Vorname, Nachname, E-Mail (eindeutig), Telefon, Position, Portal-Zugang ja/nein, Hauptansprechpartner ja/nein
- [x] Migration + Model `customer_notes`: Kunde, Autor, Text, angeheftet ja/nein
- [x] Kundenliste mit Suche, Filter (aktiv/archiviert), Sortierung, Pagination
- [x] Kundenansicht mit Tabs: Übersicht, Ansprechpartner, Tickets, Notizen, Verlauf
- [x] Kunden anlegen, bearbeiten, archivieren (nicht löschen, wenn Tickets existieren)
- [x] Ansprechpartner anlegen/bearbeiten, Portal-Einladung per Mail (Link zum Passwort setzen)
- [x] Policies für alle Aktionen, Activity-Log-Einträge

**Fertig wenn:** Kunden und Ansprechpartner sind vollständig pflegbar, Rechte greifen, Tests grün.

---

## Phase 3 – Tickets

- [x] Enums `TicketStatus` und `TicketPriority` mit Label und Farbe
- [x] Migration + Model `tickets`: Nummer, Betreff, Status, Priorität, Kunde, Ansprechpartner, Gruppe, Bearbeiter, Wiedervorlage, Quelle (Mail/Portal/Intern), geschlossen_am
- [x] Ticketnummern-Generator (transaktionssicher, Zähler pro Jahr)
- [x] Migration + Model `ticket_messages`: Typ (`public`, `internal`, `mail_in`, `mail_out`), Text (HTML, bereinigt), Autor (Mitarbeiter oder Kontakt), Message-ID, In-Reply-To
- [x] Migration + Model `attachments`: polymorph an Nachrichten, privat gespeichert, Download über Controller mit Policy
- [x] Ticketliste: Ansichten „Meine“, „Meine Gruppen“, „Alle“, „Nicht zugeordnet“; Filter nach Status, Priorität, Gruppe, Bearbeiter, Kunde; Volltextsuche in Betreff und Nummer
- [x] Geteilte Ansicht auf Desktop (Liste links, Ticket rechts), getrennte Seiten auf dem Handy
- [x] Ticketansicht: Verlauf chronologisch, interne Notizen farblich abgesetzt, Seitenleiste mit Status, Priorität, Gruppe, Bearbeiter, Kunde, Ansprechpartner
- [x] Ticket intern anlegen (für Kunde + Ansprechpartner)
- [x] Antworten (öffentlich) und interne Notiz schreiben, mit Anhängen
- [x] Status-Logik: Übernehmen → „In Arbeit“; Wiedervorlage-Datum bei „Warte auf Intern/Extern“
- [x] VOS: Wechsel auf VOS öffnet Termin-Dialog (Datum, Uhrzeit von–bis, Techniker, Einsatzort vorbelegt mit Kundenadresse, Notiz); Migration `appointments`
- [x] Terminliste `/intern/termine` (kommende VOS-Termine, Filter nach Techniker)
- [x] Wiedervorlage: täglicher Job, der fällige Tickets in der Liste markiert
- [x] Tickets zusammenführen (Recht `tickets.merge`)
- [x] Policies für Sichtbarkeit (eigene/Gruppe/alle), Activity-Log

**Fertig wenn:** Tickets lassen sich intern komplett bearbeiten, VOS-Termine funktionieren, Sichtbarkeit nach Rechten ist getestet.

---

## Phase 4 – Mail (IMAP/SMTP) und Vorlagen

- [x] Migration + Model `mailboxes`: Name, Adresse, IMAP (Host, Port, Verschlüsselung, Benutzer, Passwort verschlüsselt, Ordner), SMTP (dito), Standardgruppe, aktiv
- [x] Admin-Oberfläche für Postfächer mit Button „Verbindung testen“ (IMAP und SMTP)
- [x] Job `FetchMailbox` pro Postfach, jede Minute per Scheduler, `withoutOverlapping`
- [x] Zuordnung: 1) `[#JJJJ-NNNNN]` im Betreff, 2) `In-Reply-To`/`References`, 3) sonst neues Ticket
- [x] Absender über E-Mail einem Ansprechpartner zuordnen, sonst Ticket „nicht zugeordnet“ (Kunde nachträglich wählbar)
- [x] Antwort auf geschlossenes Ticket: Frist prüfen (Annahmen oben)
- [x] Antwort auf „Warte auf Kunden“ → „In Arbeit“
- [x] Anhänge und Inline-Bilder speichern, HTML bereinigen (z. B. mit `mews/purifier` oder `symfony/html-sanitizer`), zitierten Verlauf einklappbar
- [x] Loop-Schutz: `Auto-Submitted`, `X-Auto-Response-Suppress`, Abwesenheitsnotizen → keine automatische Antwort
- [x] Verarbeitete Mails als gelesen markieren oder in Ordner verschieben (pro Postfach einstellbar)
- [x] Versand über das SMTP des Postfachs, an dem das Ticket hängt; Ticketnummer im Betreff; Message-ID speichern; Versand über Queue
- [x] Migration + Model `mail_templates`: Typ (System/Textbaustein), Name, Betreff, Text
- [x] Platzhalter-Engine: `{ticket.nummer}`, `{ticket.betreff}`, `{ticket.status}`, `{kontakt.anrede}`, `{kontakt.name}`, `{kunde.firma}`, `{termin.datum}`, `{termin.zeit}`, `{mitarbeiter.name}`, `{portal.link}`
- [x] System-Vorlagen: Ticket eröffnet, Status geändert, Termin bestätigt, Ticket geschlossen, Portal-Einladung (jeweils aktivierbar)
- [x] Textbausteine im Antwort-Editor einfügbar
- [x] Signatur pro Mitarbeiter automatisch anhängen

**Fertig wenn:** Eine Mail an ein Postfach erzeugt ein Ticket, eine Antwort daraus landet im selben Ticket, Vorlagen werden korrekt befüllt. Tests mit Fixture-Mails (.eml) grün.

---

## Phase 5 – Kundenportal

- [x] Guard `contact`, Login `/portal/login`, Passwort setzen über Einladungslink, Passwort vergessen
- [x] Ticketliste: Ansprechpartner sieht eigene Tickets, Hauptansprechpartner alle Tickets seiner Firma
- [x] Ticket erstellen: Betreff, Beschreibung, Priorität (optional), Anhänge
- [x] Ticketansicht: nur öffentliche Nachrichten und Mails, niemals interne Notizen oder Kundennotizen
- [x] Antworten im Portal (setzt „Warte auf Kunden“ → „In Arbeit“)
- [x] VOS-Termin im Portal anzeigen
- [x] Profil: Name, Telefon, Passwort, Darkmode
- [x] Gleiches Design-System, reduzierte Navigation
- [ ] Tests: Portal-Nutzer sieht keine fremden Tickets und keine internen Inhalte

**Fertig wenn:** Kunden können Tickets anlegen, einsehen und beantworten; Isolationstests grün.

---

## Phase 6 – Feinschliff

- [ ] Benutzereinstellungen `/intern/einstellungen`: Darstellung, Signatur, Benachrichtigungen (neues Ticket in Gruppe, Zuweisung, Kundenantwort), Standard-Ansicht der Ticketliste, Passwort, 2FA
- [ ] Benachrichtigungen per Mail und im Glockensymbol (Laravel Notifications, Datenbank-Kanal)
- [ ] Globale Suche mit `⌘K` / `Strg+K` (Tickets, Kunden, Ansprechpartner)
- [ ] Tastenkürzel in `/intern`: `J`/`K` durch die Liste, `R` antworten, `N` interne Notiz, `E` Status
- [ ] Activity-Log im Ticket und beim Kunden als Tab anzeigen
- [ ] Systemeinstellungen im Admin: Firmenname, Logo, Akzentfarbe, Wiedereröffnungsfrist
- [ ] Leerzustände, Ladezustände, Fehlerseiten (403, 404, 500) im Design
- [ ] Barrierefreiheit prüfen (Kontraste in Hell und Dunkel, Fokus-Rahmen, Labels)
- [ ] Performance: Eager Loading in Listen, Indizes auf Status, Kunde, Bearbeiter, Nummer
- [ ] Deploy-Anleitung für Plesk in `README.md` (Domain, PHP-Version, Git, Cron, `.env`, erster Admin per `php artisan servicedesk:create-admin`)

**Fertig wenn:** Alle Tests grün, Deployment auf Plesk nach README in einem Durchgang möglich.
