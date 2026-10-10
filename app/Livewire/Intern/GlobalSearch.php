<?php

namespace App\Livewire\Intern;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\Ticket;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Search in the topbar (⌘K / Strg+K): tickets, customers and contacts the user may see.
 */
class GlobalSearch extends Component
{
    private const LIMIT = 5;

    public string $query = '';

    /**
     * @return list<array{label: string, items: list<array{title: string, meta: string, url: string}>}>
     */
    #[Computed]
    public function groups(): array
    {
        $term = trim($this->query);
        if (mb_strlen($term) < 2) {
            return [];
        }

        $user = auth()->user();
        $groups = [];

        if ($user->can('viewAny', Ticket::class)) {
            $number = Ticket::normalizeSearch($term);
            $groups[] = ['label' => 'Tickets', 'items' => Ticket::query()
                ->visibleTo($user)
                ->whereNull('merged_into_id')
                ->where(fn ($query) => $query->where('number', 'like', "%{$number}%")->orWhere('subject', 'like', "%{$term}%"))
                ->with('customer:id,company')
                ->latest('last_activity_at')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn (Ticket $ticket) => [
                    'title' => $ticket->subject,
                    'meta' => $ticket->reference().' · '.($ticket->customer?->company ?? 'Ohne Kunde').' · '.$ticket->status->label(),
                    'url' => route('intern.tickets.show', $ticket),
                ])->all()];
        }

        if ($user->can('viewAny', Customer::class)) {
            $groups[] = ['label' => 'Kunden', 'items' => Customer::query()
                ->where(fn ($query) => $query->where('company', 'like', "%{$term}%")->orWhere('number', 'like', "%{$term}%")->orWhere('city', 'like', "%{$term}%"))
                ->orderByRaw('archived_at is not null')
                ->orderBy('company')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn (Customer $customer) => [
                    'title' => $customer->company,
                    'meta' => trim($customer->number.' · '.$customer->city, ' ·').($customer->archived_at ? ' · archiviert' : ''),
                    'url' => route('intern.customers.show', $customer),
                ])->all()];

            $groups[] = ['label' => 'Ansprechpartner', 'items' => Contact::query()
                ->where(fn ($query) => $query
                    ->where('first_name', 'like', "%{$term}%")
                    ->orWhere('last_name', 'like', "%{$term}%")
                    ->orWhere('email', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%"))
                ->with('customer:id,company')
                ->orderBy('last_name')
                ->limit(self::LIMIT)
                ->get()
                ->map(fn (Contact $contact) => [
                    'title' => $contact->fullName(),
                    'meta' => collect([$contact->customer->company, $contact->email])->filter()->join(' · '),
                    'url' => route('intern.customers.show', ['customer' => $contact->customer_id, 'tab' => 'ansprechpartner']),
                ])->all()];
        }

        return array_values(array_filter($groups, fn (array $group) => $group['items'] !== []));
    }

    public function render()
    {
        return view('livewire.intern.global-search');
    }
}
