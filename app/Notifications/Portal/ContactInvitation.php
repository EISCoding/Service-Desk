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
 * Invitation to the customer portal with a link to set a password (valid 7 days).
 * Text from the system template "Portal-Einladung", {portal.link} is the invitation link.
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
        // Sending checks that the template is active; the defaults only cover
        // a template switched off while the mail was waiting in the queue.
        $template = MailTemplate::system(SystemTemplate::PortalInvitation);
        $placeholders = new Placeholders(contact: $contact, portalLink: $this->url($contact));
        $body = $placeholders->html($template->body ?? SystemTemplate::PortalInvitation->defaultBody());

        return (new MailMessage)
            ->subject($placeholders->text($template->subject ?? SystemTemplate::PortalInvitation->defaultSubject()))
            ->view(['mail.template', 'mail.template-text'], ['body' => $body, 'text' => Html::toText($body)]);
    }
}
