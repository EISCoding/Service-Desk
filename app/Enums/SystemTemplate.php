<?php

namespace App\Enums;

/**
 * Keys of the system templates with their default texts (seeded once, then
 * editable under /intern/vorlagen).
 */
enum SystemTemplate: string
{
    case TicketOpened = 'ticket_opened';
    case StatusChanged = 'status_changed';
    case AppointmentConfirmed = 'appointment_confirmed';
    case TicketClosed = 'ticket_closed';
    case PortalInvitation = 'portal_invitation';
    case PortalPasswordReset = 'portal_password_reset';

    public function label(): string
    {
        return match ($this) {
            self::TicketOpened => 'Ticket eröffnet',
            self::StatusChanged => 'Status geändert',
            self::AppointmentConfirmed => 'Termin bestätigt',
            self::TicketClosed => 'Ticket geschlossen',
            self::PortalInvitation => 'Portal-Einladung',
            self::PortalPasswordReset => 'Portal: Passwort zurücksetzen',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::TicketOpened => 'Eingangsbestätigung, wenn ein bekannter Ansprechpartner per E-Mail ein neues Ticket eröffnet.',
            self::StatusChanged => 'Optional beim Statuswechsel („Kunden informieren“).',
            self::AppointmentConfirmed => 'Optional beim Planen eines VOS-Termins.',
            self::TicketClosed => 'Optional beim Schließen eines Tickets.',
            self::PortalInvitation => 'Einladung ins Kundenportal mit Link zum Festlegen des Passworts.',
            self::PortalPasswordReset => 'Link zum Zurücksetzen, wenn ein Kunde im Portal „Passwort vergessen“ nutzt (60 Minuten gültig).',
        };
    }

    public function defaultSubject(): string
    {
        return match ($this) {
            self::TicketOpened => 'Ihre Anfrage ist eingegangen: {ticket.betreff}',
            self::StatusChanged => 'Neuer Status „{ticket.status}“: {ticket.betreff}',
            self::AppointmentConfirmed => 'Terminbestätigung für {termin.datum}',
            self::TicketClosed => 'Ihr Ticket wurde abgeschlossen: {ticket.betreff}',
            self::PortalInvitation => 'Ihr Zugang zum Kundenportal',
            self::PortalPasswordReset => 'Passwort für das Kundenportal zurücksetzen',
        };
    }

    public function defaultBody(): string
    {
        $text = match ($this) {
            self::TicketOpened => 'vielen Dank für Ihre Nachricht. Wir haben Ihre Anfrage unter <strong>Ticket#{ticket.nummer}</strong> aufgenommen und melden uns schnellstmöglich bei Ihnen.<br><br>'
                .'Wenn Sie etwas ergänzen möchten, antworten Sie einfach auf diese E-Mail.',
            self::StatusChanged => 'der Status Ihres Tickets Ticket#{ticket.nummer} „{ticket.betreff}“ hat sich geändert.<br><br>'
                .'Neuer Status: <strong>{ticket.status}</strong>',
            self::AppointmentConfirmed => 'hiermit bestätigen wir Ihnen den Vor-Ort-Termin zu Ticket#{ticket.nummer} „{ticket.betreff}“:<br><br>'
                .'<strong>{termin.datum}, {termin.zeit}</strong><br>Techniker: {mitarbeiter.name}<br><br>'
                .'Sollte der Termin nicht passen, antworten Sie bitte auf diese E-Mail.',
            self::TicketClosed => 'wir haben Ihr Ticket Ticket#{ticket.nummer} „{ticket.betreff}“ abgeschlossen.<br><br>'
                .'Falls noch etwas offen ist, antworten Sie einfach auf diese E-Mail.',
            self::PortalInvitation => 'für Sie wurde ein Zugang zu unserem Kundenportal eingerichtet. Dort können Sie Ihre Anfragen einsehen, neue Tickets erstellen und auf unsere Antworten reagieren.<br><br>'
                .'Bitte legen Sie über den folgenden Button Ihr Passwort fest (7 Tage gültig):<br><br>{portal.button}<br><br>'
                .'Ihr Benutzername ist Ihre E-Mail-Adresse.',
            self::PortalPasswordReset => 'wir haben eine Anfrage zum Zurücksetzen Ihres Passworts für unser Kundenportal erhalten.<br><br>'
                .'{portal.button}<br><br>'
                .'Der Link ist 60 Minuten gültig. Falls Sie die Anfrage nicht gestellt haben, können Sie diese E-Mail ignorieren.',
        };

        return "<div>{kontakt.anrede},<br><br>{$text}<br><br>Mit freundlichen Grüßen<br>Ihr Team von {firma.name}</div>";
    }

    /**
     * Mails about a ticket (ticket number in the subject); the others go to a contact directly.
     */
    public function isTicketMail(): bool
    {
        return ! in_array($this, [self::PortalInvitation, self::PortalPasswordReset], true);
    }

    /**
     * Default body of earlier versions; still unchanged templates get the new default.
     */
    public function previousDefaultBody(): string
    {
        return str_replace(
            ['Ihr Team von {firma.name}', 'über den folgenden Button Ihr Passwort fest (7 Tage gültig):<br><br>{portal.button}<br><br>'],
            ['Ihr Service-Team', 'über den folgenden Link Ihr Passwort fest (7 Tage gültig):<br>{portal.link}<br><br>'],
            $this->defaultBody(),
        );
    }
}
