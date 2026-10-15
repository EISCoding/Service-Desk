<?php

namespace App\Livewire\Intern\Customers;

use App\Enums\CustomerType;
use App\Enums\Salutation;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Contact;
use App\Models\Customer;
use App\Services\CustomerNumberGenerator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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

    /** company | private */
    public string $type = 'company';

    public string $company = '';

    /** Private person: they are the main contact. */
    public string $salutation = '';

    public string $first_name = '';

    public string $last_name = '';

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
        // Empty columns are NULL, the form fields are strings.
        $this->fill(array_map(fn ($value) => $value ?? '', $customer->only(['number', 'company', 'street', 'postal_code', 'city', 'country', 'email', 'phone', 'website'])));
        $this->type = $customer->type->value;

        if ($person = $customer->person()) {
            $this->salutation = $person->salutation?->value ?? '';
            $this->first_name = (string) $person->first_name;
            $this->last_name = (string) $person->last_name;
        }

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

        $private = $this->type === CustomerType::Private->value;

        $validated = $this->validate([
            'type' => ['required', Rule::enum(CustomerType::class)],
            'number' => ['nullable', 'string', 'max:20', Rule::unique('customers', 'number')->ignore($customer->id)],
            'company' => [Rule::requiredIf(! $private), 'nullable', 'string', 'max:255'],
            'salutation' => ['nullable', Rule::enum(Salutation::class)],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => [Rule::requiredIf($private), 'nullable', 'string', 'max:100'],
            'street' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'city' => ['nullable', 'string', 'max:100'],
            'country' => ['required', Rule::in(array_keys(config('servicedesk.countries')))],
            // A private person's address is also their contact (and portal login) address.
            'email' => ['nullable', 'email', 'max:255', ...($private ? [Rule::unique('contacts', 'email')->ignore($customer->exists ? $customer->person()?->id : null)] : [])],
            'phone' => ['nullable', 'string', 'max:50'],
            'website' => ['nullable', 'url', 'max:255'],
        ], [
            'email.unique' => 'Diese E-Mail-Adresse gehört bereits zu einem anderen Ansprechpartner.',
        ], attributes: [
            'number' => 'Kundennummer', 'company' => 'Firma', 'first_name' => 'Vorname', 'last_name' => 'Nachname', 'street' => 'Straße', 'postal_code' => 'PLZ',
            'city' => 'Ort', 'country' => 'Land', 'phone' => 'Telefon', 'website' => 'Website',
        ]);

        // Empty strings are stored as NULL; an empty number means "automatic".
        $values = array_map(fn ($value) => is_string($value) && trim($value) === '' ? null : (is_string($value) ? trim($value) : $value), $validated);
        $person = Arr::only($values, ['salutation', 'first_name', 'last_name', 'email', 'phone']);

        if ($private) {
            // A private customer is named after the person and has no website.
            $values['company'] = trim("{$person['first_name']} {$person['last_name']}");
            $values['website'] = null;
        }

        DB::transaction(function () use ($customer, $values, $private, $person) {
            $customer->fill(Arr::except($values, ['salutation', 'first_name', 'last_name']));
            $customer->email = $customer->email ? Str::lower($customer->email) : null;
            $customer->save();

            // The person is the main contact (created or kept up to date).
            if ($private) {
                $contact = $customer->person() ?? new Contact;
                $contact->forceFill([...$person, 'email' => $customer->email, 'is_primary' => true]);
                $contact->customer()->associate($customer);
                $contact->save();
            }
        });

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
            'salutations' => collect(Salutation::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(),
        ]);
    }
}
