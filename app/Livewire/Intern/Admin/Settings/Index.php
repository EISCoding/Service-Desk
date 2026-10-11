<?php

namespace App\Livewire\Intern\Admin\Settings;

use App\Enums\Permission;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Support\AccentColor;
use App\Support\AppSettings;
use App\Support\MailBranding;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * System settings: company name, logo, accent color, reopen period.
 */
#[Layout('layouts::intern')]
#[Title('Systemeinstellungen')]
class Index extends Component
{
    use InteractsWithToasts, WithFileUploads;

    public string $companyName = '';

    public string $accent = 'blue';

    public int $reopenDays = 14;

    public string $mailReplyHint = '';

    public string $mailFooter = '';

    public ?TemporaryUploadedFile $logo = null;

    public function mount(AppSettings $settings): void
    {
        $this->authorize(Permission::AdminSettings->value);

        $this->companyName = (string) config('app.name');
        $this->accent = $settings->get('accent');
        $this->reopenDays = (int) config('servicedesk.mail.reopen_days');
        $this->mailReplyHint = app(MailBranding::class)->replyHint();
        $this->mailFooter = (string) $settings->get('mail_footer');
    }

    public function save(AppSettings $settings): void
    {
        $this->authorize(Permission::AdminSettings->value);

        $this->validate([
            'companyName' => ['required', 'string', 'max:100'],
            'accent' => ['required', Rule::in(array_keys(AccentColor::PALETTE))],
            'reopenDays' => ['required', 'integer', 'min:0', 'max:365'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'mailReplyHint' => ['nullable', 'string', 'max:300'],
            'mailFooter' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'companyName' => 'Firmenname', 'accent' => 'Akzentfarbe', 'reopenDays' => 'Wiedereröffnungsfrist', 'logo' => 'Logo',
            'mailReplyHint' => 'Hinweis unter Ticket-Mails', 'mailFooter' => 'Fußzeile',
        ]);

        $values = [
            'company_name' => trim($this->companyName),
            'accent' => $this->accent,
            'reopen_days' => $this->reopenDays,
            'mail_reply_hint' => trim($this->mailReplyHint) ?: null,
            'mail_footer' => trim($this->mailFooter) ?: null,
        ];

        if ($this->logo) {
            $this->deleteLogo($settings);
            $values['logo'] = $this->logo->storeAs('branding', 'logo.'.$this->logo->extension(), 'local');
            $values['logo_version'] = now()->timestamp;
            $this->logo = null;
        }

        $settings->set($values);

        // Full reload so the new name, logo and color apply everywhere.
        $this->flashToast('Systemeinstellungen gespeichert.');
        $this->redirectRoute('intern.admin.settings', navigate: false);
    }

    public function removeLogo(AppSettings $settings): void
    {
        $this->authorize(Permission::AdminSettings->value);

        $this->deleteLogo($settings);
        $settings->set(['logo' => null]);

        $this->flashToast('Logo entfernt.');
        $this->redirectRoute('intern.admin.settings', navigate: false);
    }

    private function deleteLogo(AppSettings $settings): void
    {
        if ($path = $settings->get('logo')) {
            Storage::disk('local')->delete($path);
        }
    }

    public function render()
    {
        return view('livewire.intern.admin.settings.index', [
            'palette' => AccentColor::PALETTE,
            'logoUrl' => app(AppSettings::class)->logoUrl(),
        ]);
    }
}
