<?php

namespace App\Notifications\Staff;

use App\Enums\NotificationEvent;
use App\Mail\SystemMailer;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Bell, mail and/or web push for staff about a ticket (new ticket, assignment, customer reply).
 * The channels follow the user's notification settings.
 */
class TicketNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public NotificationEvent $event,
        public Ticket $ticket,
        public string $title,
        public string $text = '',
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<string>
     */
    public function via(User $user): array
    {
        return array_values(array_filter(
            array_keys(NotificationEvent::CHANNELS),
            fn (string $channel) => $user->wantsNotification($this->event, $channel)
                // Push only to users who enabled it on a device.
                && ($channel !== 'push' || $user->pushSubscriptions()->exists()),
        ));
    }

    public function toMail(User $user): MailMessage
    {
        return SystemMailer::via(new MailMessage)
            ->subject("{$this->title} – {$this->ticket->reference()}")
            ->greeting("Hallo {$user->name},")
            ->line($this->title.': '.$this->ticket->subject)
            ->when($this->text !== '', fn (MailMessage $mail) => $mail->line('„'.$this->text.'“'))
            ->action('Ticket öffnen', route('intern.tickets.show', $this->ticket))
            ->line('Welche Benachrichtigungen du bekommst, stellst du unter Einstellungen → Benachrichtigungen ein.')
            ->salutation(config('app.name'))
            ->view(['mail.notification', 'mail.notification-text']);
    }

    /**
     * @return array{title: string, body: string, url: string, tag: string}
     */
    public function toWebPush(User $user): array
    {
        return [
            'title' => "{$this->title} · {$this->ticket->reference()}",
            'body' => $this->ticket->subject.($this->text !== '' ? "\n„".Str::limit($this->text, 120).'“' : ''),
            'url' => route('intern.tickets.show', $this->ticket),
            'tag' => 'ticket-'.$this->ticket->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(User $user): array
    {
        return [
            'event' => $this->event->value,
            'ticket_id' => $this->ticket->id,
            'reference' => $this->ticket->reference(),
            'subject' => $this->ticket->subject,
            'title' => $this->title,
            'text' => $this->text,
        ];
    }
}
