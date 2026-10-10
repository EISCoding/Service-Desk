<?php

namespace App\Livewire\Intern\Settings;

use App\Livewire\Concerns\InteractsWithToasts;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Laravel\Fortify\Actions\ConfirmTwoFactorAuthentication;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\GenerateNewRecoveryCodes;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Password change and optional two-factor authentication (TOTP) for the
 * logged-in user. The route requires a recently confirmed password.
 */
#[Layout('layouts::intern')]
#[Title('Sicherheit')]
class Security extends Component
{
    use InteractsWithToasts;

    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $code = '';

    public bool $showingRecoveryCodes = false;

    #[Computed]
    public function user(): User
    {
        return Auth::user();
    }

    public function updatePassword(): void
    {
        $this->validate([
            'current_password' => ['required', 'string', 'current_password:web'],
            'password' => ['required', 'string', Password::defaults(), 'confirmed'],
        ]);

        $this->user->update(['password' => $this->password]);

        $this->reset('current_password', 'password', 'password_confirmation');
        $this->toast('Passwort geändert.');
    }

    /**
     * Step 1: create a secret; the user scans the QR code and confirms with a code.
     */
    public function enableTwoFactor(EnableTwoFactorAuthentication $enable): void
    {
        $enable($this->user);

        $this->reset('code');
    }

    /**
     * Step 2: confirm with a code from the authenticator app.
     */
    public function confirmTwoFactor(ConfirmTwoFactorAuthentication $confirm): void
    {
        $this->validate(['code' => ['required', 'string']]);

        $confirm($this->user, $this->code);

        $this->reset('code');
        $this->showingRecoveryCodes = true;
        $this->toast('Zwei-Faktor-Authentifizierung aktiviert.');
    }

    public function cancelTwoFactorSetup(DisableTwoFactorAuthentication $disable): void
    {
        $disable($this->user);

        $this->reset('code');
    }

    public function regenerateRecoveryCodes(GenerateNewRecoveryCodes $generate): void
    {
        $generate($this->user);

        $this->showingRecoveryCodes = true;
        $this->toast('Neue Wiederherstellungscodes erzeugt.');
    }

    public function disableTwoFactor(DisableTwoFactorAuthentication $disable): void
    {
        $disable($this->user);

        $this->showingRecoveryCodes = false;
        $this->toast('Zwei-Faktor-Authentifizierung deaktiviert.');
    }

    public function render()
    {
        return view('livewire.intern.settings.security');
    }
}
