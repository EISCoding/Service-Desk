<?php

namespace App\Livewire\Intern\Admin\Settings;

use App\Enums\Permission;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Support\AppSettings;
use App\Support\MailBranding;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * System settings: company name, mail texts, reopen period.
 * Logos, icons and the accent color are under "Erscheinungsbild".
 */
#[Layout('layouts::intern')]
#[Title('Systemeinstellungen')]
class Index extends Component
{
    use InteractsWithToasts;

    public string $companyName = '';

    public int $reopenDays = 14;

    public string $mailReplyHint = '';

    public string $mailFooter = '';

    public function mount(AppSettings $settings): void
    {
        $this->authorize(Permission::AdminSettings->value);

        $this->companyName = (string) config('app.name');
        $this->reopenDays = (int) config('servicedesk.mail.reopen_days');
        $this->mailReplyHint = app(MailBranding::class)->replyHint();
        $this->mailFooter = (string) $settings->get('mail_footer');
    }

    public function save(AppSettings $settings): void
    {
        $this->authorize(Permission::AdminSettings->value);

        $this->validate([
            'companyName' => ['required', 'string', 'max:100'],
            'reopenDays' => ['required', 'integer', 'min:0', 'max:365'],
            'mailReplyHint' => ['nullable', 'string', 'max:300'],
            'mailFooter' => ['nullable', 'string', 'max:1000'],
        ], attributes: [
            'companyName' => 'Firmenname', 'reopenDays' => 'Wiedereröffnungsfrist',
            'mailReplyHint' => 'Hinweis unter Ticket-Mails', 'mailFooter' => 'Fußzeile',
        ]);

        $values = [
            'company_name' => trim($this->companyName),
            'reopen_days' => $this->reopenDays,
            'mail_reply_hint' => trim($this->mailReplyHint) ?: null,
            'mail_footer' => trim($this->mailFooter) ?: null,
        ];

        $settings->set($values);

        // Full reload so the new name applies everywhere.
        $this->flashToast('Systemeinstellungen gespeichert.');
        $this->redirectRoute('intern.admin.settings', navigate: false);
    }

    public function render()
    {
        return view('livewire.intern.admin.settings.index');
    }
}
