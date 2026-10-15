<?php

namespace App\Mail;

use App\Enums\SystemTemplate;
use App\Models\MailTemplate;
use App\Models\Ticket;
use App\Support\Html;
use App\Support\MailBranding;
use App\Support\Placeholders;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Sample ticket mail in the (possibly unsaved) mail design: live preview and test mail.
 */
class MailDesignTestMail extends Mailable
{
    public function __construct(public MailBranding $brand) {}

    /**
     * Sample ticket with the "Ticket opened" text; not stored.
     */
    public static function sampleTicket(): Ticket
    {
        $ticket = new Ticket(['subject' => 'Drucker im Büro druckt nicht', 'requester_name' => 'Erika Muster']);
        $ticket->number = now()->format('Y').'-00042';

        return $ticket;
    }

    public static function sampleBody(Ticket $ticket): string
    {
        $template = MailTemplate::system(SystemTemplate::TicketOpened);

        return (new Placeholders($ticket))->html(Html::sanitize($template->body ?? SystemTemplate::TicketOpened->defaultBody()));
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Testmail: Mail-Design von '.$this->brand->name());
    }

    public function content(): Content
    {
        $ticket = self::sampleTicket();
        $body = self::sampleBody($ticket);

        return new Content(
            html: 'mail.ticket-message',
            text: 'mail.ticket-message-text',
            with: ['body' => $body, 'text' => Html::toText($body), 'ticket' => $ticket, 'brand' => $this->brand],
        );
    }
}
