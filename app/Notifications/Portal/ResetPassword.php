<?php

namespace App\Notifications\Portal;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Link to reset the portal password (valid 60 minutes).
 */
class ResetPassword extends Notification implements ShouldQueue
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
        return route('portal.password.reset', ['token' => $this->token, 'email' => $contact->email]);
    }

    public function toMail(Contact $contact): MailMessage
    {
        return (new MailMessage)
            ->subject('Passwort für das Kundenportal zurücksetzen')
            ->greeting($contact->formalGreeting().',')
            ->line('wir haben eine Anfrage zum Zurücksetzen Ihres Passworts für unser Kundenportal erhalten.')
            ->action('Neues Passwort festlegen', $this->url($contact))
            ->line('Der Link ist 60 Minuten gültig. Falls Sie die Anfrage nicht gestellt haben, können Sie diese E-Mail ignorieren.')
            ->salutation("Mit freundlichen Grüßen\n".config('app.name'));
    }
}
