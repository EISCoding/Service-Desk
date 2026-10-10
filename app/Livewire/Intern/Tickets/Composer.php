<?php

namespace App\Livewire\Intern\Tickets;

use App\Enums\MessageType;
use App\Enums\TicketStatus;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\MailTemplate;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Services\AttachmentStorage;
use App\Services\OutgoingMailService;
use App\Services\TicketStatusService;
use App\Support\Html;
use App\Support\Placeholders;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Write a public reply or an internal note (with attachments) below the conversation.
 */
class Composer extends Component
{
    use InteractsWithToasts, WithFileUploads;

    /** Statuses that can be set together with a reply (no extra dialog needed). */
    public const STATUS_AFTER = [TicketStatus::InProgress, TicketStatus::WaitingCustomer, TicketStatus::Closed];

    #[Locked]
    public Ticket $ticket;

    /** reply | note */
    public string $mode = 'reply';

    public string $body = '';

    /** @var list<TemporaryUploadedFile> */
    public array $files = [];

    public string $statusAfter = '';

    /** @var list<array{email: string, name: ?string}> */
    public array $cc = [];

    public string $newCc = '';

    public function mount(OutgoingMailService $mail): void
    {
        $this->authorize('view', $this->ticket);

        if (! auth()->user()->can('reply', $this->ticket)) {
            $this->mode = 'note';
        }

        $this->cc = $mail->suggestedCc($this->ticket);
    }

    public function addCc(): void
    {
        $this->validate(['newCc' => ['required', 'email', 'max:255']], attributes: ['newCc' => 'CC-Adresse']);

        $email = Str::lower(trim($this->newCc));
        if (! collect($this->cc)->contains(fn (array $r) => $r['email'] === $email)) {
            $this->cc[] = ['email' => $email, 'name' => null];
        }

        $this->reset('newCc');
    }

    public function removeCc(int $index): void
    {
        unset($this->cc[$index]);
        $this->cc = array_values($this->cc);
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode === 'note' ? 'note' : 'reply';
        $this->resetValidation();
    }

    /**
     * @return Collection<int, MailTemplate>
     */
    #[Computed]
    public function snippets(): Collection
    {
        return MailTemplate::snippets()->get(['id', 'name']);
    }

    /**
     * Snippet with filled placeholders; the browser inserts it at the cursor.
     */
    public function snippet(int $id): string
    {
        $this->authorize('view', $this->ticket);

        $snippet = MailTemplate::snippets()->findOrFail($id);

        return (new Placeholders($this->ticket, staff: auth()->user()))->html($snippet->body);
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
    }

    public function send(AttachmentStorage $storage, TicketStatusService $statuses, OutgoingMailService $mail): void
    {
        $internal = $this->mode === 'note';
        $this->authorize($internal ? 'addNote' : 'reply', $this->ticket);

        $maxKb = config('servicedesk.attachments.max_kb');

        $this->validate([
            'body' => ['required', 'string', 'max:500000', function ($attribute, $value, $fail) {
                if (Html::toText(Html::sanitize($value)) === '') {
                    $fail('Bitte einen Text eingeben.');
                }
            }],
            'files' => ['array', 'max:'.config('servicedesk.attachments.max_files')],
            'files.*' => ['file', 'max:'.$maxKb],
            'statusAfter' => ['nullable', Rule::in(array_map(fn ($s) => $s->value, self::STATUS_AFTER))],
        ], [
            'files.*.max' => 'Eine Datei darf höchstens '.round($maxKb / 1024).' MB groß sein.',
        ], ['body' => $internal ? 'Notiz' : 'Antwort', 'files' => 'Anhänge']);

        $byMail = ! $internal && $mail->canMail($this->ticket);

        $message = DB::transaction(function () use ($internal, $storage, $statuses, $mail, $byMail) {
            if ($byMail) {
                // Public reply as e-mail through the ticket's mailbox.
                $message = $mail->queue($this->ticket, $this->body, auth()->user(), $this->cc);
            } else {
                $message = new TicketMessage([
                    'type' => $internal ? MessageType::Internal : MessageType::Public,
                    'body' => $this->body,
                ]);
                $message->ticket()->associate($this->ticket);
                $message->author()->associate(auth()->user());
                $message->save();
            }

            foreach ($this->files as $file) {
                $storage->storeUpload($file, $message);
            }

            $this->ticket->logActivity($internal ? 'note_added' : 'reply_added', count($this->files)
                ? ['attachments' => ['old' => null, 'new' => count($this->files)]]
                : []);

            if (! $internal && $this->statusAfter !== '') {
                $statuses->change($this->ticket, TicketStatus::from($this->statusAfter));
            }

            $this->ticket->touchActivity();

            return $message;
        });

        if ($byMail) {
            $mail->dispatch($message);
        }

        $this->reset('body', 'files', 'statusAfter');
        $this->dispatch('ticket-changed');
        $this->dispatch('ticket-updated');
        $this->toast(match (true) {
            $internal => 'Interne Notiz gespeichert.',
            $byMail => 'Antwort wird per E-Mail gesendet.',
            default => 'Antwort gespeichert.',
        });
    }

    public function render()
    {
        return view('livewire.intern.tickets.composer', [
            'recipient' => $this->ticket->recipient(),
            'canMail' => app(OutgoingMailService::class)->canMail($this->ticket),
            'statusOptions' => collect(self::STATUS_AFTER)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(),
        ]);
    }
}
