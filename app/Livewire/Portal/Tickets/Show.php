<?php

namespace App\Livewire\Portal\Tickets;

use App\Enums\MessageType;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Appointment;
use App\Models\Contact;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Services\AttachmentStorage;
use App\Services\StaffNotifier;
use App\Services\TicketStatusService;
use App\Support\Html;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Ticket in the portal: public messages and mails only, never internal notes.
 */
#[Layout('layouts::portal')]
class Show extends Component
{
    use InteractsWithToasts, WithFileUploads;

    #[Locked]
    public Ticket $ticket;

    public string $body = '';

    /** @var list<TemporaryUploadedFile> */
    public array $files = [];

    public function mount(Ticket $ticket): void
    {
        // Links to a merged ticket lead to the ticket it was merged into.
        if ($ticket->merged_into_id && ($target = Ticket::find($ticket->merged_into_id)) && $this->contact()->can('viewInPortal', $target)) {
            $this->redirectRoute('portal.tickets.show', $target, navigate: true);

            return;
        }

        $this->authorizeView($ticket);
        $this->ticket = $ticket->loadMissing('contact');
        $ticket->markAsReadBy($this->contact());
    }

    #[Computed]
    public function contact(): Contact
    {
        return auth('contact')->user()->loadMissing('customer');
    }

    /**
     * @return Collection<int, TicketMessage>
     */
    #[Computed]
    public function messages(): Collection
    {
        return $this->ticket->messages()
            ->where('type', '!=', MessageType::Internal->value)
            ->with(['author', 'attachments'])
            ->get();
    }

    /**
     * Upcoming on-site appointments (the internal note is not shown).
     *
     * @return Collection<int, Appointment>
     */
    #[Computed]
    public function appointments(): Collection
    {
        return $this->ticket->appointments()->upcoming()->reorder('starts_at')->with('technician:id,name')->get();
    }

    public function removeFile(int $index): void
    {
        unset($this->files[$index]);
        $this->files = array_values($this->files);
    }

    /**
     * Reply of the contact; "Warte auf Kunden" and closed tickets go back to "In Arbeit".
     */
    public function reply(AttachmentStorage $storage, TicketStatusService $statuses, StaffNotifier $notifier): void
    {
        abort_unless($this->contact->can('replyInPortal', $this->ticket), 403);

        $maxKb = config('servicedesk.attachments.max_kb');

        $this->validate([
            'body' => ['required', 'string', 'max:500000', function ($attribute, $value, $fail) {
                if (Html::toText(Html::sanitize($value)) === '') {
                    $fail('Bitte geben Sie eine Nachricht ein.');
                }
            }],
            'files' => ['array', 'max:'.config('servicedesk.attachments.max_files')],
            'files.*' => ['file', 'max:'.$maxKb],
        ], [
            'files.*.max' => 'Eine Datei darf höchstens '.round($maxKb / 1024).' MB groß sein.',
        ], ['body' => 'Nachricht', 'files' => 'Anhänge']);

        DB::transaction(function () use ($storage, $statuses, $notifier) {
            $message = new TicketMessage(['type' => MessageType::Public, 'body' => $this->body]);
            $message->ticket()->associate($this->ticket);
            $message->author()->associate($this->contact);
            $message->save();

            foreach ($this->files as $file) {
                $storage->storeUpload($file, $message);
            }

            $this->ticket->logActivity('reply_added', count($this->files) ? ['attachments' => ['old' => null, 'new' => count($this->files)]] : []);
            $statuses->afterCustomerReply($this->ticket);
            $notifier->customerReply($this->ticket, $message);
        });

        $this->reset('body', 'files');
        $this->ticket->refresh();
        unset($this->messages);
        $this->dispatch('composer-sent');
        $this->toast('Ihre Antwort wurde gesendet.');
    }

    public function render()
    {
        return view('livewire.portal.tickets.show')->title($this->ticket->reference());
    }

    private function authorizeView(Ticket $ticket): void
    {
        abort_unless($this->contact()->can('viewInPortal', $ticket), 404);
    }
}
