<?php

namespace App\Notifications\Portal;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Invitation to the customer portal with a link to set a password (valid 7 days).
 * Replaced by the editable system template "Portal-Einladung" in Phase 4.
 */
class ContactInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $token) {}

    /**
     * @return list<string>
     */
    public function via(Contact $contact): array
    {
        return ['mail'];
    }

    public function url(Contact $contact): string
    {
        return route('portal.invitation.show', ['token' => $this->token, 'email' => $contact->email]);
    }

    public function toMail(Contact $contact): MailMessage
    {
        return (new MailMessage)
            ->subject('Ihr Zugang zum Kundenportal von '.config('app.name'))
            ->greeting($contact->formalGreeting().',')
            ->line('für Sie wurde ein Zugang zu unserem Kundenportal eingerichtet. Dort können Sie Ihre Anfragen einsehen, neue Tickets erstellen und auf unsere Antworten reagieren.')
            ->line('Bitte legen Sie über den folgenden Link Ihr Passwort fest:')
            ->action('Passwort festlegen', $this->url($contact))
            ->line('Der Link ist 7 Tage gültig. Ihr Benutzername ist Ihre E-Mail-Adresse '.$contact->email.'.')
            ->salutation("Mit freundlichen Grüßen\n".config('app.name'));
    }
}
