<?php

namespace App\Livewire\Intern\Tickets;

use App\Enums\MessageType;
use App\Enums\TicketPriority;
use App\Enums\TicketSource;
use App\Enums\TicketStatus;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\Group;
use App\Models\Mailbox;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Support\Html;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal to create a ticket internally for a customer (and contact).
 * Opened via the browser event "create-ticket" (optionally with customerId).
 */
class CreateTicket extends Component
{
    use InteractsWithToasts;

    public bool $show = false;

    public ?int $customerId = null;

    public string $customerSearch = '';

    public string $contactId = '';

    public string $subject = '';

    public string $priority = 'normal';

    public string $groupId = '';

    public bool $assignToMe = true;

    public string $body = '';

    #[On('create-ticket')]
    public function open(?int $customerId = null): void
    {
        $this->authorize('create', Ticket::class);

        $this->reset();
        $this->resetValidation();
        $this->groupId = (string) (auth()->user()->groupIds()[0] ?? '');

        if ($customerId) {
            $this->selectCustomer($customerId);
        }

        $this->show = true;
    }

    public function selectCustomer(int $customerId): void
    {
        $customer = Customer::findOrFail($customerId);

        $this->customerId = $customer->id;
        $this->customerSearch = '';
        // Preselect the only or the primary contact.
        $contacts = $customer->contacts()->get();
        $this->contactId = (string) ($contacts->count() === 1 ? $contacts->first()->id : ($contacts->firstWhere('is_primary', true)?->id ?? ''));
    }

    public function clearCustomer(): void
    {
        $this->reset('customerId', 'contactId');
    }

    #[Computed]
    public function customer(): ?Customer
    {
        return $this->customerId ? Customer::find($this->customerId) : null;
    }

    /**
     * @return Collection<int, Customer>
     */
    #[Computed]
    public function customerResults(): Collection
    {
        $search = trim($this->customerSearch);

        return mb_strlen($search) < 2 ? new Collection : Customer::active()
            ->where(fn ($query) => $query->where('company', 'like', "%{$search}%")->orWhere('number', 'like', "%{$search}%"))
            ->orderBy('company')->limit(8)->get(['id', 'number', 'company', 'city']);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function contactOptions(): array
    {
        return $this->customer
            ? $this->customer->contacts()->get()->mapWithKeys(fn (Contact $c) => [$c->id => $c->fullName()])->all()
            : [];
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function groupOptions(): array
    {
        return Group::orderBy('name')->pluck('name', 'id')->all();
    }

    public function save(): void
    {
        $this->authorize('create', Ticket::class);

        $this->validate([
            'customerId' => ['required', Rule::exists('customers', 'id')],
            'contactId' => ['nullable', Rule::exists('contacts', 'id')->where('customer_id', $this->customerId)],
            'subject' => ['required', 'string', 'max:255'],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'groupId' => ['nullable', Rule::exists('groups', 'id')],
            'body' => ['required', 'string', 'max:500000', function ($attribute, $value, $fail) {
                if (Html::toText(Html::sanitize($value)) === '') {
                    $fail('Bitte eine Beschreibung eingeben.');
                }
            }],
        ], attributes: [
            'customerId' => 'Kunde', 'contactId' => 'Ansprechpartner', 'subject' => 'Betreff',
            'priority' => 'Priorität', 'groupId' => 'Gruppe', 'body' => 'Beschreibung',
        ]);

        $ticket = DB::transaction(function () {
            $ticket = new Ticket([
                'subject' => trim($this->subject),
                'priority' => $this->priority,
                'source' => TicketSource::Internal,
                'status' => $this->assignToMe ? TicketStatus::InProgress : TicketStatus::New,
            ]);
            $ticket->customer()->associate($this->customerId);
            $ticket->contact()->associate($this->contactId ?: null);
            $ticket->group()->associate($this->groupId ?: null);
            $ticket->assignee()->associate($this->assignToMe ? auth()->user() : null);
            $ticket->mailbox()->associate(Mailbox::default());
            $ticket->save();

            $message = new TicketMessage(['type' => MessageType::Public, 'body' => $this->body]);
            $message->ticket()->associate($ticket);
            $message->author()->associate(auth()->user());
            $message->save();

            return $ticket;
        });

        $this->show = false;
        $this->flashToast("{$ticket->reference()} angelegt.");
        $this->redirectRoute('intern.tickets.show', $ticket, navigate: true);
    }

    public function render()
    {
        return view('livewire.intern.tickets.create-ticket', [
            'priorityOptions' => collect(TicketPriority::cases())->mapWithKeys(fn ($p) => [$p->value => $p->label()])->all(),
        ]);
    }
}
