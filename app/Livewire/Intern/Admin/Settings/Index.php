<?php

namespace App\Livewire\Intern\Admin\Settings;

use App\Enums\Permission;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Support\AppSettings;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * System settings: company name and reopen period.
 * Logos and colors are under "Erscheinungsbild", mail texts under "Mail-Design".
 */
#[Layout('layouts::intern')]
#[Title('Systemeinstellungen')]
class Index extends Component
{
    use InteractsWithToasts;

    public string $companyName = '';

    public int $reopenDays = 14;

    public function mount(AppSettings $settings): void
    {
        $this->authorize(Permission::AdminSettings->value);

        $this->companyName = (string) config('app.name');
        $this->reopenDays = (int) config('servicedesk.mail.reopen_days');
    }

    public function save(AppSettings $settings): void
    {
        $this->authorize(Permission::AdminSettings->value);

        $this->validate([
            'companyName' => ['required', 'string', 'max:100'],
            'reopenDays' => ['required', 'integer', 'min:0', 'max:365'],
        ], attributes: [
            'companyName' => 'Firmenname', 'reopenDays' => 'Wiedereröffnungsfrist',
        ]);

        $values = [
            'company_name' => trim($this->companyName),
            'reopen_days' => $this->reopenDays,
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
