# ServiceDesk – Projektkontext für Claude Code

Ticketsystem mit Kundenportal für **eine Firma** (kein Mandantensystem). Läuft auf **Plesk** ohne Docker.
Der Arbeitsplan steht in `PLAN.md` – arbeite ihn Phase für Phase ab.

## Stack
- Laravel (aktuelle stabile Version), PHP 8.3+
- Livewire + Alpine.js, Tailwind CSS (Build mit Vite)
- MariaDB (lokal für Tests: SQLite oder MariaDB)
- Pakete: `laravel/fortify` (Login, 2FA), `spatie/laravel-permission` (Rollen/Rechte), `webklex/php-imap` (IMAP)
- Tests: Pest

## Bereiche
- `/portal` – Ansprechpartner der Kunden (eigener Guard `contact`)
- `/intern` – Mitarbeiter (Guard `web`), `/intern/admin` nur für Admins

## Plesk-Rahmen (immer einhalten)
- Document Root zeigt auf `/public`
- Kein Supervisor, kein Websocket-Server, kein dauerhafter Node-Prozess
- Cron: Plesk-Aufgabe jede Minute `php artisan schedule:run`
- Queue läuft über den Scheduler: `queue:work --stop-when-empty --max-time=50`
- Queue-Treiber `database`, Cache/Session `database` oder `file`
- Anhänge nur in `storage/app/private`, Auslieferung über Controller mit Rechteprüfung
- Kompilierte Assets (`public/build`) werden beim Deploy gebaut oder mit eingecheckt

## Konventionen
- UI-Texte auf Deutsch, Code und Kommentare auf Englisch
- Livewire-Komponenten unter `app/Livewire/Intern/...` und `app/Livewire/Portal/...`
- Jede Rechteprüfung über Policies + Permissions, nie über Rollennamen im Code
- Zugangsdaten (IMAP/SMTP) mit Laravels `encrypted` Cast speichern
- Jede Änderung an Tickets und Kunden ins Activity-Log schreiben
- Zu jedem Feature Pest-Tests (Feature-Tests für Rechte und Abläufe)

## Design
Apple-Look (macOS/iOS): System-Schrift (`-apple-system`, SF Pro, Fallback Inter), viel Weißraum,
Radien 12–16 px, dezente Trennlinien, Sidebar mit Blur, eine Akzentfarbe, Status als farbige Pills.
Darkmode per Tailwind `class`-Strategie: Standard = System, umstellbar in den Benutzereinstellungen.
Responsive: Desktop mit Sidebar + geteilter Ansicht (Liste | Ticket), Handy mit Tab-Leiste unten.

## Arbeitsweise
- Eine Aufgabe nach der anderen, danach Tests laufen lassen (`php artisan test`)
- Nach jeder erledigten Aufgabe: Checkbox in `PLAN.md` abhaken und committen
- Bei Unklarheiten nachfragen statt raten
