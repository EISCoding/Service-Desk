<?php

namespace App\Livewire\Portal;

use App\Enums\Salutation;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\Contact;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Own data of the logged-in contact: name, phone, password, appearance.
 * The e-mail address is the login and can only be changed by us.
 */
#[Layout('layouts::portal')]
#[Title('Profil')]
class Profile extends Component
{
    use InteractsWithToasts;

    public string $salutation = '';

    public string $first_name = '';

    public string $last_name = '';

    public string $phone = '';

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(): void
    {
        $contact = $this->contact;

        $this->salutation = (string) $contact->salutation?->value;
        $this->first_name = (string) $contact->first_name;
        $this->last_name = (string) $contact->last_name;
        $this->phone = (string) $contact->phone;
    }

    #[Computed]
    public function contact(): Contact
    {
        return auth('contact')->user()->loadMissing('customer');
    }

    public function saveProfile(): void
    {
        $validated = $this->validate([
            'salutation' => ['nullable', Rule::enum(Salutation::class)],
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:50'],
        ], attributes: ['salutation' => 'Anrede', 'first_name' => 'Vorname', 'last_name' => 'Nachname', 'phone' => 'Telefon']);

        $this->contact->update(array_map(fn ($value) => is_string($value) && trim($value) === '' ? null : trim($value), $validated));

        $this->toast('Ihre Angaben wurden gespeichert.');
    }

    public function savePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'string', function ($attribute, $value, $fail) {
                if (! Hash::check($value, $this->contact->password)) {
                    $fail('Das aktuelle Passwort ist nicht korrekt.');
                }
            }],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
        ], attributes: ['current_password' => 'Aktuelles Passwort', 'password' => 'Neues Passwort']);

        $this->contact->forceFill(['password' => $this->password])->save();

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->toast('Ihr Passwort wurde geändert.');
    }

    public function render()
    {
        return view('livewire.portal.profile', [
            'salutationOptions' => collect(Salutation::cases())->mapWithKeys(fn ($s) => [$s->value => $s->label()])->all(),
        ]);
    }
}
