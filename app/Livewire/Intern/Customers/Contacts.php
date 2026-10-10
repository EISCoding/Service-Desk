<?php

namespace App\Livewire\Intern\Customers;

use App\Enums\Salutation;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Contact;
use App\Models\Customer;
use App\Notifications\Portal\ContactInvitation;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * "Ansprechpartner" tab of the customer page: list, create/edit in a modal,
 * delete and portal invitations.
 */
class Contacts extends Component
{
    use InteractsWithToasts;

    public Customer $customer;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $salutation = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $email = '';

    public string $phone = '';

    public string $position = '';

    public bool $is_primary = false;

    public bool $portal_access = false;

    public bool $sendInvitation = true;

    /**
     * @return Collection<int, Contact>
     */
    #[Computed]
    public function contacts(): Collection
    {
        return $this->customer->contacts()->get();
    }

    #[Computed]
    public function editing(): ?Contact
    {
        return $this->editingId ? $this->findContact($this->editingId) : null;
    }

    public function create(): void
    {
        $this->authorize('create', Contact::class);

        $this->resetForm();
        // The first contact of a customer is its primary contact.
        $this->is_primary = $this->contacts->isEmpty();
        $this->showForm = true;
    }

    public function edit(int $contactId): void
    {
        $contact = $this->findContact($contactId);
        $this->authorize('update', $contact);

        $this->resetForm();
        $this->editingId = $contact->id;
        $this->fill([
            'salutation' => $contact->salutation?->value ?? '',
            'first_name' => (string) $contact->first_name,
            'last_name' => $contact->last_name,
            'email' => (string) $contact->email,
            'phone' => (string) $contact->phone,
            'position' => (string) $contact->position,
            'is_primary' => $contact->is_primary,
            'portal_access' => $contact->portal_access,
        ]);
        $this->showForm = true;
    }

    public function save(): void
    {
        $contact = $this->editingId ? $this->findContact($this->editingId) : new Contact;
        $creating = ! $contact->exists;
        $this->authorize($creating ? 'create' : 'update', $creating ? Contact::class : $contact);

        $canManagePortal = auth()->user()->can('managePortal', $contact);
        $portalAccess = $canManagePortal ? $this->portal_access : $contact->portal_access;

        $this->email = Str::lower(trim($this->email));

        $validated = $this->validate([
            'salutation' => ['nullable', Rule::enum(Salutation::class)],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => [Rule::requiredIf($portalAccess), 'nullable', 'email', 'max:255', Rule::unique('contacts', 'email')->ignore($contact->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:100'],
            'is_primary' => ['boolean'],
        ], [
            'email.required' => 'Für den Portal-Zugang wird eine E-Mail-Adresse benötigt.',
        ], [
            'salutation' => 'Anrede', 'first_name' => 'Vorname', 'last_name' => 'Nachname',
            'phone' => 'Telefon', 'position' => 'Position',
        ]);

        DB::transaction(function () use ($contact, $validated, $portalAccess) {
            $contact->fill(array_map(fn ($value) => is_string($value) && trim($value) === '' ? null : $value, $validated));
            $contact->portal_access = $portalAccess;
            $contact->customer()->associate($this->customer);
            $contact->save();
        });

        $this->showForm = false;

        if ($canManagePortal && $portalAccess && $this->sendInvitation && $contact->portalStatus() !== 'active' && ! $contact->invited_at) {
            $this->sendInvitationTo($contact);
            $this->changed($creating ? 'Ansprechpartner angelegt und eingeladen.' : 'Ansprechpartner gespeichert und eingeladen.');

            return;
        }

        $this->changed($creating ? 'Ansprechpartner angelegt.' : 'Ansprechpartner gespeichert.');
    }

    public function invite(int $contactId): void
    {
        $contact = $this->findContact($contactId);
        $this->authorize('managePortal', $contact);

        if (! $contact->portal_access || ! $contact->email) {
            $this->toast('Für eine Einladung braucht der Ansprechpartner Portal-Zugang und eine E-Mail-Adresse.', 'error');

            return;
        }

        $this->sendInvitationTo($contact);
        $this->changed("Einladung an {$contact->email} verschickt.");
    }

    public function delete(int $contactId): void
    {
        $contact = $this->findContact($contactId);
        $this->authorize('delete', $contact);

        $contact->delete();

        $this->changed('Ansprechpartner gelöscht.');
    }

    private function sendInvitationTo(Contact $contact): void
    {
        $token = Password::broker('contact_invitations')->createToken($contact);

        $contact->forceFill(['invited_at' => now()])->save();
        $contact->notify(new ContactInvitation($token));
        $contact->logActivity('portal_invited', ['email' => ['old' => null, 'new' => $contact->email]]);
    }

    /**
     * Only contacts of this customer can be addressed.
     */
    private function findContact(int $contactId): Contact
    {
        return $this->customer->contacts()->findOrFail($contactId);
    }

    private function changed(string $message): void
    {
        unset($this->contacts, $this->editing);
        $this->dispatch('contacts-changed');
        $this->toast($message);
    }

    private function resetForm(): void
    {
        $this->reset('editingId', 'salutation', 'first_name', 'last_name', 'email', 'phone', 'position', 'is_primary', 'portal_access', 'sendInvitation');
        $this->resetValidation();
    }

    public function render()
    {
        return view('livewire.intern.customers.contacts');
    }
}
