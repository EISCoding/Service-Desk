<?php

namespace App\Livewire\Intern\Admin\MailDesign;

use App\Enums\Permission;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Mail\MailDesignTestMail;
use App\Mail\SystemMailer;
use App\Support\AppSettings;
use App\Support\Html;
use App\Support\MailBranding;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Throwable;

/**
 * Look of all mails to customers: colors, font, logo, header, footer and the
 * reply hint, with a live preview and a test mail.
 */
#[Layout('layouts::intern')]
#[Title('Mail-Design')]
class Index extends Component
{
    use InteractsWithToasts;

    public bool $customAccent = false;

    public string $accent = '#0066cc';

    public string $background = '#f2f2f7';

    public string $font = 'system';

    public string $logo = 'left';

    public int $logoSize = 40;

    public bool $showName = true;

    public string $header = '';

    public bool $accentBar = true;

    public bool $rounded = true;

    public string $footer = '';

    public string $replyHint = '';

    public string $testRecipient = '';

    public function mount(AppSettings $settings, MailBranding $brand): void
    {
        $this->authorize(Permission::AdminSettings->value);

        $this->fillFrom($brand->design(), $brand);
        $this->footer = $brand->footer() ?? '';
        $this->replyHint = (string) $settings->get('mail_reply_hint');
        $this->testRecipient = (string) auth()->user()->email;
    }

    public function save(AppSettings $settings): void
    {
        $this->authorize(Permission::AdminSettings->value);
        $this->validate();

        $footer = Html::sanitize($this->footer);

        $settings->set([
            'mail_design' => $this->design(),
            'mail_footer_html' => Html::toText($footer) === '' ? null : $footer,
            'mail_footer' => null, // plain text footer of older versions, replaced by the HTML one
            'mail_reply_hint' => trim($this->replyHint) ?: null,
        ]);

        $this->toast('Mail-Design gespeichert.');
    }

    /**
     * Back to the default look (applies after saving).
     */
    public function restoreDefaults(MailBranding $brand): void
    {
        $this->authorize(Permission::AdminSettings->value);

        $this->fillFrom(MailBranding::DEFAULT_DESIGN, $brand);
        $this->replyHint = '';
        $this->resetValidation();
    }

    public function sendTest(): void
    {
        $this->authorize(Permission::AdminSettings->value);
        $this->validate();
        $this->validate(['testRecipient' => ['required', 'email']], attributes: ['testRecipient' => 'Empfänger']);

        try {
            SystemMailer::send(new MailDesignTestMail($this->draft()), $this->testRecipient);
        } catch (Throwable $exception) {
            Log::warning('Test mail of the mail design failed', ['exception' => $exception->getMessage()]);
            $this->addError('testRecipient', 'Die Testmail konnte nicht gesendet werden: '.$exception->getMessage());

            return;
        }

        $this->toast("Testmail an {$this->testRecipient} gesendet.");
    }

    /**
     * Sample mail in the current (unsaved) design for the preview frame.
     */
    #[Computed]
    public function preview(): string
    {
        $ticket = MailDesignTestMail::sampleTicket();
        $body = MailDesignTestMail::sampleBody($ticket);

        return view('mail.ticket-message', [
            'body' => $body, 'text' => Html::toText($body), 'ticket' => $ticket, 'brand' => $this->draft(),
        ])->render();
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        return [
            'accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'background' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'font' => ['required', Rule::in(array_keys(MailBranding::FONTS))],
            'logo' => ['required', Rule::in(['left', 'center', 'none'])],
            'logoSize' => ['required', Rule::in(array_keys(MailBranding::LOGO_SIZES))],
            'header' => ['nullable', 'string', 'max:2000'],
            'footer' => ['nullable', 'string', 'max:5000'],
            'replyHint' => ['nullable', 'string', 'max:300'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function validationAttributes(): array
    {
        return [
            'accent' => 'Akzentfarbe', 'background' => 'Hintergrund', 'font' => 'Schrift', 'logo' => 'Logo',
            'logoSize' => 'Logo-Größe', 'header' => 'Kopfzeile', 'footer' => 'Fußzeile', 'replyHint' => 'Antwort-Hinweis',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return ['accent.regex' => 'Bitte eine Farbe im Format #RRGGBB angeben.', 'background.regex' => 'Bitte eine Farbe im Format #RRGGBB angeben.'];
    }

    /**
     * @return array<string, mixed>
     */
    private function design(): array
    {
        $header = Html::sanitize($this->header);

        return [
            'accent' => $this->customAccent ? strtolower($this->accent) : null,
            'background' => strtolower($this->background),
            'font' => $this->font,
            'logo' => $this->logo,
            'logo_size' => (int) $this->logoSize,
            'show_name' => $this->showName,
            'header' => Html::toText($header) === '' ? null : $header,
            'accent_bar' => $this->accentBar,
            'rounded' => $this->rounded,
        ];
    }

    /**
     * Branding with the form values; invalid colors fall back to the saved ones.
     */
    private function draft(): MailBranding
    {
        return app(MailBranding::class)->withDraft($this->design(), $this->footer, $this->replyHint);
    }

    /**
     * @param  array<string, mixed>  $design
     */
    private function fillFrom(array $design, MailBranding $brand): void
    {
        $this->customAccent = $design['accent'] !== null;
        $this->accent = $design['accent'] ?? $brand->withDraft(['accent' => null], null, null)->accent();
        $this->background = $design['background'];
        $this->font = $design['font'];
        $this->logo = $design['logo'];
        $this->logoSize = (int) $design['logo_size'];
        $this->showName = (bool) $design['show_name'];
        $this->header = (string) $design['header'];
        $this->accentBar = (bool) $design['accent_bar'];
        $this->rounded = (bool) $design['rounded'];
    }

    public function render()
    {
        return view('livewire.intern.admin.mail-design.index', [
            'fonts' => MailBranding::FONTS,
            'logoSizes' => MailBranding::LOGO_SIZES,
            'hasLogo' => (bool) app(AppSettings::class)->get('logo'),
        ]);
    }
}
