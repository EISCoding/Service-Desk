<?php

namespace App\Enums;

/**
 * Events staff can be notified about, each via bell (database) and/or mail.
 */
enum NotificationEvent: string
{
    case NewTicket = 'new_ticket';
    case Assigned = 'assigned';
    case CustomerReply = 'customer_reply';

    public const CHANNELS = ['database' => 'Glocke', 'mail' => 'E-Mail'];

    public function label(): string
    {
        return match ($this) {
            self::NewTicket => 'Neues Ticket in meiner Gruppe',
            self::Assigned => 'Ticket wurde mir zugewiesen',
            self::CustomerReply => 'Kunde hat geantwortet',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::NewTicket => 'Per Mail, Portal oder intern angelegt. Tickets ohne Gruppe gehen an alle mit Zugriff auf Gruppen-Tickets.',
            self::Assigned => 'Wenn dich jemand anderes als Bearbeiter einträgt.',
            self::CustomerReply => 'Bei Tickets, die du bearbeitest. Ohne Bearbeiter an alle Mitglieder der Gruppe.',
        };
    }

    public function defaultFor(string $channel): bool
    {
        return $channel === 'database' || $this !== self::NewTicket;
    }
}
