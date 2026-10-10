# ServiceDesk

Ticketsystem mit Kundenportal für eine Firma. Läuft auf Plesk ohne Docker,
ohne Supervisor und ohne dauerhafte Hintergrundprozesse.

- `/intern` – Mitarbeiterbereich (Login unter `/intern/login`)
- `/intern/admin` – Verwaltung (Benutzer, Gruppen, …)
- `/portal` – Kundenportal (ab Phase 5)

Arbeitsplan: siehe [`PLAN.md`](PLAN.md), Projektkontext: [`CLAUDE.md`](CLAUDE.md).

## Stack

- Laravel 13, PHP 8.3+
- Livewire 4 + Alpine.js (in Livewire enthalten), Tailwind CSS 4 (Build mit Vite)
- Laravel Fortify (Login, Passwort-Reset, 2FA), spatie/laravel-permission (Rollen/Rechte)
- Pest 4 für Tests
- MariaDB in Produktion, SQLite lokal und in den Tests

## Lokales Setup

Voraussetzungen: PHP 8.3+ (mit `pdo_sqlite`, `intl`, `mbstring`), Composer 2, Node.js 20+ und npm.

```bash
git clone https://github.com/EISCoding/Service-Desk.git
cd Service-Desk

composer install
cp .env.example .env
php artisan key:generate

touch database/database.sqlite      # Windows: type nul > database\database.sqlite
php artisan migrate --seed          # legt Rollen, Rechte und (lokal) einen Admin an

npm install
npm run build                       # oder während der Entwicklung: npm run dev
```

Starten:

```bash
php artisan serve                   # http://localhost:8000
php artisan schedule:work           # optional: Scheduler (verarbeitet auch die Queue)
```

Oder alles zusammen mit `composer dev`.

### Lokaler Admin

`php artisan db:seed` legt bei `APP_ENV=local` einen Admin an. Zugangsdaten aus der `.env`:

| Feld     | Standard                  |
|----------|---------------------------|
| E-Mail   | `admin@servicedesk.test`  |
| Passwort | `password`                |

Anmeldung unter <http://localhost:8000/intern/login>.

Nach dem Login:

- **Benutzer und Gruppen**: Admin → Benutzer / Gruppen (`/intern/admin/benutzer`, `/intern/admin/gruppen`)
- **2FA einrichten / Passwort ändern**: Benutzermenü oben rechts → Sicherheit
- **Darkmode**: Benutzermenü → Darstellung (System / Hell / Dunkel)
- **Kunden**: `/intern/kunden` – `db:seed` legt lokal 12 Demo-Kunden mit Ansprechpartnern an
- **Tickets**: `/intern/tickets` (Liste | Ticket), **Termine**: `/intern/termine` – lokal mit Demo-Tickets,
  Gruppen „Support“/„Technik“, einem VOS-Termin und fälligen Wiedervorlagen
- **Design-System**: <http://localhost:8000/intern/styleguide> (nur bei `APP_ENV=local`)

Mails (Passwort-Reset usw.) landen lokal in `storage/logs/laravel-*.log` (`MAIL_MAILER=log`).
Portal-Einladungen laufen über die Queue: Damit sie im Log erscheinen, muss `php artisan schedule:work`
(oder einmalig `php artisan queue:work --stop-when-empty`) laufen.

### MariaDB statt SQLite

In der `.env`:

```dotenv
DB_CONNECTION=mariadb
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=servicedesk
DB_USERNAME=servicedesk
DB_PASSWORD=geheim
```

## Tests

```bash
php artisan test
```

Die Tests laufen gegen eine SQLite-In-Memory-Datenbank (siehe `phpunit.xml`), es wird keine
lokale Datenbank verändert.

## Queue und Scheduler (Plesk-Prinzip)

Es gibt keinen dauerhaft laufenden Queue-Worker. Stattdessen:

1. Eine Cron-Aufgabe ruft jede Minute `php artisan schedule:run` auf.
2. Der Scheduler startet `queue:work --stop-when-empty --max-time=50` (mit `withoutOverlapping`).
   Der Worker arbeitet die anstehenden Jobs ab und beendet sich spätestens nach 50 Sekunden.

Queue, Cache und Sessions nutzen den Treiber `database`.

Täglich um 06:00 markiert der Scheduler Tickets mit fälliger Wiedervorlage.

## Anhänge

Anhänge liegen in `storage/app/private/attachments` und werden nur über `/intern/anhaenge/{id}` mit
Rechteprüfung ausgeliefert. Maximale Größe pro Datei: `ATTACHMENT_MAX_KB` (Standard 20 MB). In Plesk
müssen `upload_max_filesize` und `post_max_size` (PHP-Einstellungen) mindestens so groß sein.

## Deployment (Plesk)

Das Skript [`deploy.sh`](deploy.sh) wird nach jedem Git-Pull ausgeführt
(Plesk → Git → „Zusätzliche Deployment-Aktionen“). Eine ausführliche Plesk-Anleitung folgt in Phase 6.
