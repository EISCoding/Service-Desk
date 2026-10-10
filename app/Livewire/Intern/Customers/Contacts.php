<?php

namespace App\Livewire\Intern\Customers;

use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Ansprechpartner" tab of the customer page.
 */
class Contacts extends Component
{
    use InteractsWithToasts;

    public Customer $customer;

    /**
     * @return Collection<int, Contact>
     */
    #[Computed]
    public function contacts(): Collection
    {
        return $this->customer->contacts()->get();
    }

    public function render()
    {
        return view('livewire.intern.customers.contacts');
    }
}
