<?php

namespace App\Console\Commands;

use App\Models\Mailbox;
use App\Models\TicketMessage;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Overview for hosts without SSH (run as a Plesk scheduled task):
 *   php artisan servicedesk:status
 * Shows why mails might not go out: mailers, mailboxes, queue, failed jobs.
 */
class Status extends Command
{
    protected $signature = 'servicedesk:status';

    protected $description = 'Show the state of mail sending, mailboxes and the queue';

    public function handle(): int
    {
        $this->components->twoColumnDetail('<fg=gray>Umgebung</>', app()->environment().(config('app.debug') ? ' (DEBUG an!)' : ''));
        $this->components->twoColumnDetail('<fg=gray>Config-Cache</>', app()->configurationIsCached() ? 'aktiv (nach .env-Änderungen servicedesk:deploy ausführen)' : 'aus');
        $this->components->twoColumnDetail('<fg=gray>proc_open</>', function_exists('proc_open') ? 'verfügbar' : 'GESPERRT: schedule:run kann keine Befehle starten');

        $this->newLine();
        $this->line('<options=bold>Mailversand</>');
        $mailer = config('mail.default');
        $this->components->twoColumnDetail('System-Mails (MAIL_MAILER)', $mailer === 'smtp'
            ? 'smtp '.config('mail.mailers.smtp.host').':'.config('mail.mailers.smtp.port')
            : "{$mailer} – Mails werden NICHT verschickt");
        $this->components->twoColumnDetail('Ticket-Mails (MAILBOX_MAILER)', config('servicedesk.mail.mailer')
            ? config('servicedesk.mail.mailer').' – Ticket-Mails gehen NICHT raus, Zeile in der .env leeren'
            : 'über die Postfächer');

        $this->newLine();
        $this->line('<options=bold>Postfächer</>');
        $mailboxes = Mailbox::orderBy('id')->get();
        if ($mailboxes->isEmpty()) {
            $this->line('  Keine Postfächer angelegt (Admin → Postfächer).');
        }
        foreach ($mailboxes as $mailbox) {
            $state = collect([
                $mailbox->is_active ? 'aktiv' : 'INAKTIV',
                $mailbox->is_default ? 'Standard' : null,
                'SMTP '.$mailbox->smtp_host.':'.$mailbox->smtp_port,
                'abgerufen '.($mailbox->last_fetched_at?->diffForHumans() ?? 'nie'),
            ])->filter()->join(' · ');
            $this->components->twoColumnDetail("{$mailbox->name} <{$mailbox->email}>", $state);
            if ($mailbox->last_error) {
                $this->line('    <fg=red>Letzter Fehler:</> '.Str::limit($mailbox->last_error, 300));
            }
        }

        $this->newLine();
        $this->line('<options=bold>Queue</>');
        $pending = DB::table('jobs')->count();
        $oldest = DB::table('jobs')->min('created_at');
        $age = $oldest ? Carbon::createFromTimestamp($oldest)->diffInMinutes(now()) : 0;
        $this->components->twoColumnDetail('Wartende Jobs', $pending
            ? $pending.' (ältester seit '.round($age).' Min.)'.($age > 2 ? ' – der Worker läuft nicht: Scheduler-Aufgabe prüfen' : '')
            : '0');

        $failed = DB::table('failed_jobs')->latest('failed_at')->first();
        $this->components->twoColumnDetail('Fehlgeschlagene Jobs', (string) DB::table('failed_jobs')->count());
        if ($failed) {
            $this->line('    <fg=red>Zuletzt ('.$failed->failed_at.'):</> '.Str::limit(strtok($failed->exception, "\n"), 300));
        }

        $this->newLine();
        $this->line('<options=bold>Ticket-Mails (letzte 7 Tage)</>');
        $recent = TicketMessage::where('type', 'mail_out')->where('created_at', '>=', now()->subDays(7));
        foreach (['queued' => 'wartend', 'sent' => 'gesendet', 'failed' => 'fehlgeschlagen'] as $status => $label) {
            $this->components->twoColumnDetail(ucfirst($label), (string) (clone $recent)->where('mail_status', $status)->count());
        }
        if ($error = (clone $recent)->where('mail_status', 'failed')->latest('id')->value('mail_error')) {
            $this->line('    <fg=red>Letzter Fehler:</> '.Str::limit($error, 300));
        }

        return self::SUCCESS;
    }
}
