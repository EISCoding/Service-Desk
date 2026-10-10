<?php

namespace App\Livewire\Intern\Customers;

use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Customer;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts::intern')]
class Show extends Component
{
    use InteractsWithToasts;

    public const TABS = [
        'uebersicht' => 'Übersicht',
        'ansprechpartner' => 'Ansprechpartner',
        'tickets' => 'Tickets',
        'notizen' => 'Notizen',
        'verlauf' => 'Verlauf',
    ];

    public Customer $customer;

    #[Url(except: 'uebersicht')]
    public string $tab = 'uebersicht';

    public function mount(Customer $customer): void
    {
        $this->authorize('view', $customer);

        $this->customer = $customer;

        if (! array_key_exists($this->tab, self::TABS)) {
            $this->tab = 'uebersicht';
        }
    }

    public function showTab(string $tab): void
    {
        $this->tab = array_key_exists($tab, self::TABS) ? $tab : 'uebersicht';
    }

    /**
     * @return array{contacts: int, notes: int}
     */
    #[Computed]
    public function counts(): array
    {
        return [
            'contacts' => $this->customer->contacts()->count(),
            'notes' => $this->customer->notes()->count(),
        ];
    }

    #[On('contacts-changed')]
    #[On('notes-changed')]
    #[On('customer-saved')]
    public function refreshCustomer(): void
    {
        $this->customer->refresh();
        unset($this->counts);
    }

    public function render()
    {
        return view('livewire.intern.customers.show', [
            'tabs' => self::TABS,
            'primaryContacts' => $this->tab === 'uebersicht' ? $this->customer->contacts()->where('is_primary', true)->get() : collect(),
            'pinnedNotes' => $this->tab === 'uebersicht' ? $this->customer->notes()->where('is_pinned', true)->with('author:id,name')->get() : collect(),
        ])->title($this->customer->company);
    }
}
