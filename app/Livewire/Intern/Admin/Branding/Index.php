<?php

namespace App\Livewire\Intern\Admin\Branding;

use App\Enums\Permission;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Support\AccentColor;
use App\Support\AppSettings;
use App\Support\Branding;
use App\Support\Html;
use App\Support\IconGenerator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Appearance: logos for light and dark surfaces, app icon (home screen, push),
 * favicon, accent color and the texts of the public home page.
 */
#[Layout('layouts::intern')]
#[Title('Erscheinungsbild')]
class Index extends Component
{
    use InteractsWithToasts, WithFileUploads;

    public ?TemporaryUploadedFile $logo = null;

    public ?TemporaryUploadedFile $logoDark = null;

    public ?TemporaryUploadedFile $appIcon = null;

    public ?TemporaryUploadedFile $favicon = null;

    public string $iconBackground = Branding::DEFAULT_ICON_BACKGROUND;

    public string $accent = 'blue';

    public string $homeHeading = '';

    public string $homeIntro = '';

    public string $homePhone = '';

    public string $homeHours = '';

    public string $homeImprintUrl = '';

    public string $homePrivacyUrl = '';

    public function mount(AppSettings $settings, Branding $branding): void
    {
        $this->authorize(Permission::AdminSettings->value);

        $this->accent = $settings->get('accent');
        $this->homeHeading = (string) $settings->get('home_heading');
        $this->homeIntro = (string) $settings->get('home_intro');
        $this->homePhone = (string) $settings->get('home_phone');
        $this->homeHours = (string) $settings->get('home_hours');
        $this->homeImprintUrl = (string) $settings->get('home_imprint_url');
        $this->homePrivacyUrl = (string) $settings->get('home_privacy_url');
        $this->iconBackground = $branding->iconBackground();
    }

    public function save(AppSettings $settings, Branding $branding): void
    {
        $this->authorize(Permission::AdminSettings->value);

        $this->validate([
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'logoDark' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'appIcon' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048', 'dimensions:min_width=192,min_height=192'],
            'favicon' => ['nullable', 'file', 'mimes:png,ico,jpg,jpeg,webp', 'max:512'],
            'iconBackground' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent' => ['required', Rule::in(array_keys(AccentColor::PALETTE))],
            'homeHeading' => ['nullable', 'string', 'max:120'],
            'homeIntro' => ['nullable', 'string', 'max:5000'],
            'homePhone' => ['nullable', 'string', 'max:40', 'regex:/^[\d\s+()\/-]+$/'],
            'homeHours' => ['nullable', 'string', 'max:120'],
            'homeImprintUrl' => ['nullable', 'url:http,https', 'max:300'],
            'homePrivacyUrl' => ['nullable', 'url:http,https', 'max:300'],
        ], [
            'iconBackground.regex' => 'Bitte eine Farbe im Format #RRGGBB angeben.',
            'homePhone.regex' => 'Bitte nur Ziffern, Leerzeichen und + ( ) / - verwenden.',
        ], [
            'logo' => 'Logo', 'logoDark' => 'Logo für dunkle Flächen', 'appIcon' => 'App-Icon',
            'favicon' => 'Favicon', 'iconBackground' => 'Hintergrundfarbe', 'accent' => 'Akzentfarbe',
            'homeHeading' => 'Überschrift', 'homeIntro' => 'Einleitung', 'homePhone' => 'Telefon', 'homeHours' => 'Erreichbarkeit',
            'homeImprintUrl' => 'Impressum', 'homePrivacyUrl' => 'Datenschutz',
        ]);

        $this->iconBackground = strtolower($this->iconBackground);
        $needsGd = $this->appIcon || ($this->favicon && $this->favicon->extension() !== 'ico')
            || ($branding->hasAppIcon() && $this->iconBackground !== $branding->iconBackground());

        if ($needsGd && ! IconGenerator::available()) {
            $this->addError($this->favicon && ! $this->appIcon ? 'favicon' : 'appIcon', 'Auf dem Server fehlt die PHP-Erweiterung GD. Ohne sie können keine Icons erzeugt werden.');

            return;
        }

        try {
            if ($this->appIcon) {
                $branding->storeAppIcon($this->appIcon, $this->iconBackground);
            } elseif ($branding->hasAppIcon() && $this->iconBackground !== $branding->iconBackground()) {
                $branding->regenerateIcons($this->iconBackground);
            }
        } catch (InvalidArgumentException) {
            $this->addError('appIcon', 'Das Bild konnte nicht gelesen werden.');

            return;
        }

        try {
            if ($this->favicon) {
                $branding->storeFavicon($this->favicon);
            }
        } catch (InvalidArgumentException) {
            $this->addError('favicon', 'Das Bild konnte nicht gelesen werden.');

            return;
        }

        if ($this->logo) {
            $branding->storeLogo($this->logo);
        }

        if ($this->logoDark) {
            $branding->storeLogo($this->logoDark, dark: true);
        }

        $intro = Html::sanitize($this->homeIntro);

        $settings->set([
            'accent' => $this->accent,
            'home_heading' => trim($this->homeHeading) ?: null,
            'home_intro' => Html::toText($intro) === '' ? null : $intro,
            'home_phone' => trim($this->homePhone) ?: null,
            'home_hours' => trim($this->homeHours) ?: null,
            'home_imprint_url' => trim($this->homeImprintUrl) ?: null,
            'home_privacy_url' => trim($this->homePrivacyUrl) ?: null,
        ]);
        $this->reset('logo', 'logoDark', 'appIcon', 'favicon');

        // Full reload: logos, icons and the accent color apply everywhere.
        $this->flashToast('Erscheinungsbild gespeichert.');
        $this->redirectRoute('intern.admin.branding', navigate: false);
    }

    public function remove(string $asset, Branding $branding): void
    {
        $this->authorize(Permission::AdminSettings->value);

        match ($asset) {
            'logo' => $branding->removeLogo(),
            'logoDark' => $branding->removeLogo(dark: true),
            'appIcon' => $branding->removeAppIcon(),
            'favicon' => $branding->removeFavicon(),
            default => abort(404),
        };

        $this->flashToast(match ($asset) {
            'logo' => 'Logo entfernt.',
            'logoDark' => 'Logo für dunkle Flächen entfernt.',
            'appIcon' => 'App-Icon entfernt. Es gilt wieder das Standard-Icon.',
            'favicon' => 'Favicon entfernt.',
        });
        $this->redirectRoute('intern.admin.branding', navigate: false);
    }

    public function render(Branding $branding)
    {
        return view('livewire.intern.admin.branding.index', [
            'branding' => $branding,
            'palette' => AccentColor::PALETTE,
            'gd' => IconGenerator::available(),
        ]);
    }
}
