<?php

namespace App\Livewire\Intern\Customers;

use App\Models\Activity;
use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Verlauf" tab: activity log of the customer, its contacts and notes.
 */
class History extends Component
{
    use WithPagination;

    #[Locked]
    public Customer $customer;

    public function mount(): void
    {
        $this->authorize('view', $this->customer);
    }

    #[Computed]
    public function activities(): LengthAwarePaginator
    {
        return Activity::forHistoryOf($this->customer)
            ->with(['causer', 'subject'])
            ->latest('id')
            ->paginate(20, pageName: 'verlauf');
    }

    public function render()
    {
        return view('livewire.intern.customers.history');
    }
}
