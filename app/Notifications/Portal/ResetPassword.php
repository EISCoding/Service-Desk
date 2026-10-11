<?php

namespace App\Notifications\Portal;

use App\Enums\SystemTemplate;
use App\Models\Contact;
use App\Models\MailTemplate;
use App\Support\Html;
use App\Support\Placeholders;
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
        // Text from the system template "Portal: Passwort zurücksetzen"; the default
        // applies when it is switched off (customers must always be able to reset).
        $template = MailTemplate::system(SystemTemplate::PortalPasswordReset);
        $placeholders = new Placeholders(contact: $contact, portalLink: $this->url($contact));
        $body = $placeholders->html($template->body ?? SystemTemplate::PortalPasswordReset->defaultBody());

        return (new MailMessage)
            ->subject($placeholders->text($template->subject ?? SystemTemplate::PortalPasswordReset->defaultSubject()))
            ->view(['mail.template', 'mail.template-text'], ['body' => $body, 'text' => Html::toText($body)]);
    }
}
