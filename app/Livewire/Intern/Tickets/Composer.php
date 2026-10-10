<?php

namespace App\Livewire\Intern\Tickets;

use App\Enums\MessageType;
use App\Enums\TicketStatus;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Services\AttachmentStorage;
use App\Support\Html;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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

    public function mount(): void
    {
        $this->authorize('view', $this->ticket);

        if (! auth()->user()->can('reply', $this->ticket)) {
            $this->mode = 'note';
        }
    }

    public function setMode(string $mode): void
    {
        $this->mode = $mode === 'note' ? 'note' : 'reply';
        $this->resetValidation();
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
    }

    public function send(AttachmentStorage $storage): void
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

        DB::transaction(function () use ($internal, $storage) {
            $message = new TicketMessage([
                'type' => $internal ? MessageType::Internal : MessageType::Public,
                'body' => $this->body,
            ]);
            $message->ticket()->associate($this->ticket);
            $message->author()->associate(auth()->user());
            $message->save();

            foreach ($this->files as $file) {
                $storage->storeUpload($file, $message);
            }

            $this->ticket->logActivity($internal ? 'note_added' : 'reply_added', count($this->files)
                ? ['attachments' => ['old' => null, 'new' => count($this->files)]]
                : []);

            if (! $internal && $this->statusAfter !== '') {
                $status = TicketStatus::from($this->statusAfter);
                $this->ticket->status = $status;
                $this->ticket->closed_at = $status === TicketStatus::Closed ? now() : null;
                $this->ticket->follow_up_at = null;
                $this->ticket->save();
            }

            $this->ticket->touchActivity();
        });

        $this->reset('body', 'files', 'statusAfter');
        $this->dispatch('ticket-changed');
        $this->dispatch('ticket-updated');
        $this->toast($internal ? 'Interne Notiz gespeichert.' : 'Antwort gespeichert.');
    }

    public function render()
    {
        return view('livewire.intern.tickets.composer', [
            'statusOptions' => collect(self::STATUS_AFTER)->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(),
        ]);
    }
}
