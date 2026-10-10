<?php

namespace App\Livewire\Intern\Customers;

use App\Models\Customer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * "Tickets" tab of the customer page (only tickets the user may see).
 */
class Tickets extends Component
{
    use WithPagination;

    #[Locked]
    public Customer $customer;

    public function mount(): void
    {
        $this->authorize('view', $this->customer);
    }

    #[Computed]
    public function tickets(): LengthAwarePaginator
    {
        return $this->customer->tickets()
            ->visibleTo(auth()->user())
            ->with(['contact:id,first_name,last_name', 'assignee:id,name'])
            ->latest('last_activity_at')
            ->paginate(20, pageName: 'tickets');
    }

    public function render()
    {
        return view('livewire.intern.customers.tickets');
    }
}
