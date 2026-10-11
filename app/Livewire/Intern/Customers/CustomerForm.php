<?php

namespace App\Livewire\Intern\Customers;

use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Customer;
use App\Services\CustomerNumberGenerator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal to create or edit a customer. Opened via the browser events
 * "create-customer" and "edit-customer" (with the customer id).
 */
class CustomerForm extends Component
{
    use InteractsWithToasts;

    public bool $show = false;

    #[Locked]
    public ?int $customerId = null;

    public string $number = '';

    public string $company = '';

    public string $street = '';

    public string $postal_code = '';

    public string $city = '';

    public string $country = 'DE';

    public string $email = '';

    public string $phone = '';

    public string $website = '';

    public string $nextNumber = '';

    #[On('create-customer')]
    public function create(): void
    {
        $this->authorize('create', Customer::class);

        $this->resetForm();
        $this->nextNumber = app(CustomerNumberGenerator::class)->next();
        $this->show = true;
    }

    #[On('edit-customer')]
    public function edit(int $id): void
    {
        $customer = Customer::findOrFail($id);
        $this->authorize('update', $customer);

        $this->resetForm();
        $this->customerId = $customer->id;
        $this->fill($customer->only(['number', 'company', 'street', 'postal_code', 'city', 'country', 'email', 'phone', 'website']));
        $this->show = true;
    }

    public function save(): void
    {
        $customer = $this->customerId ? Customer::findOrFail($this->customerId) : new Customer;
        $creating = ! $customer->exists;
        $this->authorize($creating ? 'create' : 'update', $creating ? Customer::class : $customer);

        // "www.example.com" → "https://www.example.com"
        $this->website = trim($this->website);
        if ($this->website !== '' && ! preg_match('#^https?://#i', $this->website)) {
            $this->website = 'https://'.$this->website;
        }

        $validated = $this->validate([
            'number' => ['nullable', 'string', 'max:20', Rule::unique('customers', 'number')->ignore($customer->id)],
            'company' => ['required', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['required', Rule::in(array_keys(config('servicedesk.countries')))],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
        ], attributes: [
            'number' => 'Kundennummer', 'company' => 'Firma', 'street' => 'Straße', 'postal_code' => 'PLZ',
            'city' => 'Ort', 'country' => 'Land', 'phone' => 'Telefon', 'website' => 'Website',
        ]);

        // Empty strings are stored as NULL; an empty number means "automatic".
        $customer->fill(array_map(fn ($value) => is_string($value) && trim($value) === '' ? null : (is_string($value) ? trim($value) : $value), $validated));
        $customer->email = $customer->email ? Str::lower($customer->email) : null;
        $customer->save();

        $this->show = false;

        if ($creating) {
            $this->flashToast("Kunde {$customer->number} angelegt.");
            $this->redirectRoute('intern.customers.show', $customer, navigate: true);

            return;
        }

        $this->dispatch('customer-saved');
        $this->toast('Kunde gespeichert.');
    }

    private function resetForm(): void
    {
        $this->reset();
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.intern.customers.customer-form', [
            'countries' => config('servicedesk.countries'),
        ]);
    }
}
