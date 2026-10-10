# ServiceDesk

Ticketsystem mit Kundenportal für eine Firma. Läuft auf Plesk ohne Docker,
ohne Supervisor und ohne dauerhafte Hintergrundprozesse.

- `/intern` – Mitarbeiterbereich (Login unter `/intern/login`)
- `/intern/admin` – Verwaltung (Benutzer, Gruppen, …)
- `/portal` – Kundenportal für Ansprechpartner der Kunden (Login unter `/portal/login`)

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
- **Einstellungen** (Benutzermenü oben rechts): Darstellung, Standard-Ansicht der Ticketliste,
  Benachrichtigungen, Signatur, Passwort und 2FA (`/intern/einstellungen`)
- **Darkmode**: Benutzermenü → Darstellung (System / Hell / Dunkel)
- **Kunden**: `/intern/kunden` – `db:seed` legt lokal 12 Demo-Kunden mit Ansprechpartnern an
- **Tickets**: `/intern/tickets` (Liste | Ticket), **Termine**: `/intern/termine` – lokal mit Demo-Tickets,
  Gruppen „Support“/„Technik“, einem VOS-Termin und fälligen Wiedervorlagen
- **Postfächer**: Admin → Postfächer (`/intern/admin/postfaecher`) – lokal mit dem inaktiven Demo-Postfach
  „Support (Demo)“
- **Vorlagen**: `/intern/vorlagen` – Systemvorlagen (an/aus, Text bearbeiten) und Textbausteine mit Platzhaltern
- **Glocke** oben rechts: Benachrichtigungen (neues Ticket in der Gruppe, Zuweisung, Kundenantwort),
  wird jede Minute aktualisiert; Mails dazu landen lokal im Log
- **Suche**: `⌘K` / `Strg+K` (Tickets, Kunden, Ansprechpartner); Tastenkürzel in Tickets: `J`/`K`, `R`, `N`, `E`
- **Systemeinstellungen**: Admin → Systemeinstellungen (Firmenname, Logo, Akzentfarbe, Frist)

### Kundenportal lokal

`db:seed` macht lokal den Hauptansprechpartner des ersten Demo-Kunden zum Portal-Nutzer:

| Feld     | Wert                      |
|----------|---------------------------|
| E-Mail   | `kunde@servicedesk.test`  |
| Passwort | `password`                |

Anmeldung unter <http://localhost:8000/portal/login> (am besten in einem privaten Fenster, wenn
parallel ein Mitarbeiter angemeldet ist). Ansprechpartner sehen ihre eigenen Tickets,
Hauptansprechpartner alle Tickets ihrer Firma. Weitere Zugänge: beim Kunden einen Ansprechpartner mit
Portal-Zugang anlegen und einladen; der Einladungslink steht im Log.
- **Design-System**: <http://localhost:8000/intern/styleguide> (nur bei `APP_ENV=local`)

Mails (Passwort-Reset usw.) landen lokal in `storage/logs/laravel-*.log` (`MAIL_MAILER=log`).
Portal-Einladungen laufen über die Queue: Damit sie im Log erscheinen, muss `php artisan schedule:work`
(oder einmalig `php artisan queue:work --stop-when-empty`) laufen.

### E-Mail lokal testen (ohne Mailserver)

Mit `MAILBOX_MAILER=log` (Standard in `.env.example`) gehen Ticket-Mails nicht über das SMTP des
Postfachs, sondern ins Log (`storage/logs/laravel-*.log`). Versendet wird über die Queue, also
`php artisan schedule:work` laufen lassen (oder `php artisan queue:work --stop-when-empty`).

Eingehende Mails lassen sich als `.eml`-Datei importieren, genau so, als kämen sie per IMAP:

```bash
php artisan servicedesk:import-mail tests/Fixtures/mails/new-from-contact.eml
```

Ablauf zum Ausprobieren:

1. Bei einem Demo-Kunden einen Ansprechpartner mit der E-Mail `anna.becker@kunde.test` anlegen
   (Absender der Beispiel-Mail) – nur bekannte Ansprechpartner bekommen die Eingangsbestätigung.
2. Mail importieren → neues Ticket mit Eingangsbestätigung „Ticket eröffnet“ (im Log).
3. Im Ticket antworten, Status ändern („Kunden informieren“) oder einen VOS-Termin planen.
4. Eine Antwort des Kunden importieren: `.eml` mit `[#JJJJ-NNNNN]` im Betreff → landet im selben Ticket.

Mit einem echten Postfach: unter Admin → Postfächer IMAP/SMTP eintragen, „Verbindung testen“,
Postfach aktivieren und `MAILBOX_MAILER` in der `.env` leeren. Der Scheduler holt die Mails jede Minute ab.

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

Täglich um 06:00 markiert der Scheduler Tickets mit fälliger Wiedervorlage. Jede Minute startet
er außerdem `servicedesk:fetch-mail`, das für jedes aktive Postfach einen Abruf-Job einreiht.

## Anhänge

Anhänge liegen in `storage/app/private/attachments` und werden nur über `/intern/anhaenge/{id}` mit
Rechteprüfung ausgeliefert. Maximale Größe pro Datei: `ATTACHMENT_MAX_KB` (Standard 20 MB). In Plesk
müssen `upload_max_filesize` und `post_max_size` (PHP-Einstellungen) mindestens so groß sein.

## Deployment auf Plesk

Die Anwendung läuft auf einem normalen Plesk-Webspace: kein Docker, kein Supervisor, kein dauerhaft
laufender Node- oder Queue-Prozess. Alles Zeitgesteuerte (Queue, Mailabruf, Wiedervorlagen) startet
eine einzige Cron-Aufgabe pro Minute.

### Voraussetzungen

- Plesk Obsidian mit SSH-Zugang für das Abonnement (Plesk → Websites & Domains → Hosting & DNS →
  Hosting → „Zugriff auf den Server über SSH“: `/bin/bash`)
- PHP 8.3 oder neuer (PHP-FPM) mit `pdo_mysql`, `mbstring`, `intl`, `openssl`, `fileinfo`, `zip`
- MariaDB 10.6+ (oder MySQL 8)
- Composer (in Plesk enthalten) und Node.js 20+ (Plesk-Erweiterung „Node.js“) für den Asset-Build;
  ohne Node.js `public/build` einchecken und `SKIP_ASSETS=1` setzen
- Die Git-Erweiterung von Plesk

In den Beispielen steht `servicedesk.example.de` für die Domain und PHP 8.3 unter
`/opt/plesk/php/8.3/bin/php`. Den Pfad zeigt Plesk unter „PHP-Einstellungen“ an.

### 1. Domain und PHP

1. **Domain anlegen** (oder Subdomain) und unter **Hosting-Einstellungen** den **Dokumentstamm** auf
   `httpdocs/public` stellen.
2. **SSL/TLS**: Let’s-Encrypt-Zertifikat ausstellen und „Permanente 301-Weiterleitung auf HTTPS“ aktivieren.
3. **PHP-Einstellungen**: Version 8.3 (FPM), außerdem
   - `memory_limit` = 256M
   - `upload_max_filesize` und `post_max_size` = 25M (mindestens `ATTACHMENT_MAX_KB`)
   - `max_execution_time` = 60

### 2. Datenbank

Plesk → **Datenbanken** → **Datenbank hinzufügen**: MariaDB, Name z. B. `servicedesk`, eigener Benutzer
mit sicherem Passwort. Die Zugangsdaten kommen gleich in die `.env`.

### 3. Code per Git

Plesk → **Git** → **Repository hinzufügen**:

- Remote-Repository: `https://github.com/EISCoding/Service-Desk.git` (bei privatem Repo den angezeigten
  SSH-Schlüssel als Deploy-Key bei GitHub hinterlegen und die SSH-URL nutzen)
- Branch: `main`
- Bereitstellungsmodus: **Automatisch**, Bereitstellungspfad: `httpdocs`
- **Zusätzliche Bereitstellungsaktionen** aktivieren:

  ```bash
  export PATH=/opt/plesk/node/20/bin:/opt/plesk/php/8.3/bin:$PATH
  COMPOSER_BIN=/usr/lib/plesk-9.0/composer.phar bash deploy.sh
  ```

  Der Plesk-Composer liegt auf Debian/Ubuntu unter `/usr/lib/plesk-9.0/composer.phar`, auf
  AlmaLinux/RHEL unter `/usr/lib64/plesk-9.0/composer.phar`. Die Node.js-Version passend zur
  installierten Plesk-Erweiterung wählen (`ls /opt/plesk/node`).

Der erste Abruf scheitert noch an der fehlenden `.env`, das ist erwartet.

### 4. `.env` anlegen

Per SSH im Verzeichnis `httpdocs`:

```bash
cd ~/httpdocs
cp .env.example .env
/opt/plesk/php/8.3/bin/php -r 'echo "base64:".base64_encode(random_bytes(32)), PHP_EOL;'   # ergibt den APP_KEY
```

Dann die `.env` bearbeiten (Plesk-Dateimanager oder `nano .env`):

```dotenv
APP_NAME="ServiceDesk"
APP_ENV=production
APP_KEY=base64:…             # Ausgabe des Befehls oben
APP_DEBUG=false
APP_URL=https://servicedesk.example.de
LOG_LEVEL=warning

DB_CONNECTION=mariadb
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=servicedesk
DB_USERNAME=servicedesk
DB_PASSWORD=…

SESSION_SECURE_COOKIE=true

# System-Mails (Passwort vergessen, Benachrichtigungen an Mitarbeiter, Portal-Einladungen)
MAIL_MAILER=smtp
MAIL_SCHEME=smtps            # Port 465, bei 587 mit STARTTLS: smtp
MAIL_HOST=…
MAIL_PORT=465
MAIL_USERNAME=…
MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=servicedesk@example.de
MAIL_FROM_NAME="${APP_NAME}"

# Ticket-Mails gehen über die Postfächer aus dem Admin-Bereich: hier leer lassen!
MAILBOX_MAILER=
```

`SEED_ADMIN_*` wird nur lokal genutzt (Demo-Daten gibt es in Produktion nicht).

Die Zugangsdaten der Ticket-Postfächer (IMAP/SMTP) stehen nicht in der `.env`, sondern verschlüsselt in
der Datenbank. Der Schlüssel dafür ist `APP_KEY`: nach dem ersten Start **nicht mehr ändern** und sicher
aufbewahren.

### 5. Erstes Deployment

Plesk → **Git** → **Jetzt bereitstellen** (oder per SSH mit denselben zwei Zeilen wie oben).
Das Skript [`deploy.sh`](deploy.sh) erledigt bei jedem Deployment:

1. Wartungsmodus an (`artisan down`)
2. `composer install --no-dev -o`
3. `npm ci && npm run build` (übersprungen mit `SKIP_ASSETS=1`)
4. `migrate --force`, Rollen/Rechte und Systemvorlagen aktualisieren (bestehende Texte bleiben)
5. Config-, Route-, View- und Event-Cache
6. `queue:restart`, Wartungsmodus aus (auch wenn ein Schritt fehlschlägt)

Statt `PATH` lassen sich die Programme auch einzeln setzen: `PHP_BIN`, `COMPOSER_BIN`, `NPM_BIN`.
Ohne Node.js auf dem Server: `public/build` lokal bauen, einchecken und `SKIP_ASSETS=1` setzen.

### 6. Ersten Admin anlegen

```bash
cd ~/httpdocs
/opt/plesk/php/8.3/bin/php artisan servicedesk:create-admin
```

Der Befehl fragt Name, E-Mail und Passwort ab (oder `--name`, `--email`, `--password`). Danach
anmelden unter `https://servicedesk.example.de/intern/login` und unter **Einstellungen → Sicherheit**
die Zwei-Faktor-Anmeldung einrichten.

### 7. Cron-Aufgabe

Plesk → **Geplante Aufgaben** → **Aufgabe hinzufügen**:

- Typ: **Befehl ausführen**
- Befehl: `cd /var/www/vhosts/servicedesk.example.de/httpdocs && /opt/plesk/php/8.3/bin/php artisan schedule:run >> /dev/null 2>&1`
- Ausführen: **Jede Minute** (`* * * * *`)

Der Scheduler startet jede Minute `queue:work --stop-when-empty --max-time=50` (Mails, Benachrichtigungen),
ruft die Postfächer ab und markiert täglich um 06:00 fällige Wiedervorlagen.

### 8. Einrichtung in der Anwendung

1. **Admin → Systemeinstellungen**: Firmenname, Logo, Akzentfarbe, Wiedereröffnungsfrist
2. **Admin → Gruppen** und **Benutzer**: Teams und Mitarbeiter anlegen (Startpasswort vergeben)
3. **Admin → Postfächer**: IMAP/SMTP eintragen, „Verbindung testen“, Standardgruppe wählen, aktivieren
4. **Vorlagen**: Texte der Systemvorlagen prüfen, Textbausteine anlegen
5. **Kunden** anlegen und Ansprechpartner ins Portal einladen (`/portal`)

### Prüfen

- `https://servicedesk.example.de/up` antwortet mit „Application up“.
- Nach einer Minute zeigt `php artisan schedule:list` die Aufgaben; eine Testmail an ein Postfach wird
  zum Ticket.
- Fehler stehen in `storage/logs/laravel-JJJJ-MM-TT.log`. Fehlgeschlagene Jobs zeigt
  `php artisan queue:failed`, erneut einreihen mit `php artisan queue:retry all`.

### Updates

Auf `main` pushen: Plesk zieht den Stand und führt `deploy.sh` aus. Ohne automatische Bereitstellung:
Plesk → Git → **Jetzt bereitstellen**.

### Häufige Probleme

| Problem | Lösung |
|---|---|
| 500 nach dem Deployment | `storage/logs` lesen; `php artisan config:clear`, dann erneut deployen |
| „Permission denied“ in `storage` | Dateien gehören dem Abonnement-Benutzer; in Plesk „Berechtigungen reparieren“ |
| Mails werden nicht abgeholt/verschickt | Läuft die Cron-Aufgabe jede Minute? Fehlermeldung am Postfach im Admin prüfen |
| Uploads brechen ab | `upload_max_filesize`/`post_max_size` in den PHP-Einstellungen erhöhen |
| Seite hängt im Wartungsmodus | `php artisan up` |
