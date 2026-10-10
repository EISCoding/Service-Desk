<?php

namespace App\Livewire\Portal\Tickets;

use App\Enums\MessageType;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Services\AttachmentStorage;
use App\Services\TicketStatusService;
use App\Support\Html;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * New ticket from the portal. Group and mailbox come from the default mailbox,
 * no confirmation mail is sent (the ticket is visible in the portal right away).
 */
#[Layout('layouts::portal')]
#[Title('Neues Ticket')]
class Create extends Component
{
    use InteractsWithToasts, WithFileUploads;

    public string $subject = '';

    public string $body = '';

    public string $priority = 'normal';

    /** @var list<TemporaryUploadedFile> */
    public array $files = [];

    /** Closed ticket this one follows up ("Neues Ticket dazu"). */
    #[Url(as: 'bezug')]
    public ?int $previousId = null;

    public function mount(): void
    {
        $this->priority = TicketPriority::Normal->value;

        if ($previous = $this->previous()) {
            $this->subject = $previous->subject;
        }
    }

    /**
     * The referenced ticket, if the contact may see it.
     */
    public function previous(): ?Ticket
    {
        $previous = $this->previousId ? Ticket::find($this->previousId) : null;

        return $previous && auth('contact')->user()->can('viewInPortal', $previous) ? $previous : null;
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
    }

    public function save(AttachmentStorage $storage, TicketStatusService $statuses): void
    {
        $this->authorize('createInPortal', Ticket::class);

        $maxKb = config('servicedesk.attachments.max_kb');

        $this->validate([
            'subject' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'body' => ['required', 'string', 'max:500000', function ($attribute, $value, $fail) {
                if (Html::toText(Html::sanitize($value)) === '') {
                    $fail('Bitte beschreiben Sie Ihr Anliegen.');
                }
            }],
            'files' => ['array', 'max:'.config('servicedesk.attachments.max_files')],
            'files.*' => ['file', 'max:'.$maxKb],
        ], [
            'files.*.max' => 'Eine Datei darf höchstens '.round($maxKb / 1024).' MB groß sein.',
        ], ['subject' => 'Betreff', 'priority' => 'Priorität', 'body' => 'Beschreibung', 'files' => 'Anhänge']);

        $contact = auth('contact')->user();

        $ticket = DB::transaction(function () use ($contact, $storage, $statuses) {
            $mailbox = Mailbox::default();

            $ticket = new Ticket([
                'subject' => trim($this->subject),
                'priority' => $this->priority,
                'source' => TicketSource::Portal,
                'status' => TicketStatus::New,
            ]);
            $ticket->customer()->associate($contact->customer_id);
            $ticket->contact()->associate($contact);
            $ticket->mailbox()->associate($mailbox);
            $ticket->group()->associate($mailbox?->default_group_id);
            $ticket->save();

            $message = new TicketMessage(['type' => MessageType::Public, 'body' => $this->body]);
            $message->ticket()->associate($ticket);
            $message->author()->associate($contact);
            $message->save();

            foreach ($this->files as $file) {
                $storage->storeUpload($file, $message);
            }

            if ($previous = $this->previous()) {
                $statuses->linkFollowUp($previous, $ticket, 'vom Kunden im Portal angelegt.');
            }

            return $ticket;
        });

        $this->flashToast("{$ticket->reference()} wurde angelegt. Wir melden uns bei Ihnen.");
        $this->redirectRoute('portal.tickets.show', $ticket, navigate: true);
    }

    public function render()
    {
        return view('livewire.portal.tickets.create', [
            'priorityOptions' => collect(TicketPriority::cases())->mapWithKeys(fn ($p) => [$p->value => $p->label()])->all(),
        ]);
    }
}
