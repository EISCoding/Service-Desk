<?php

namespace App\Livewire\Intern\Admin\Branding;

use App\Enums\Permission;
use App\Livewire\Concerns\InteractsWithToasts;
use App\Support\AccentColor;
use App\Support\AppSettings;
use App\Support\Branding;
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
 * favicon and accent color.
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

    public function mount(AppSettings $settings, Branding $branding): void
    {
        $this->authorize(Permission::AdminSettings->value);

        $this->accent = $settings->get('accent');
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
        ], [
            'iconBackground.regex' => 'Bitte eine Farbe im Format #RRGGBB angeben.',
        ], [
            'logo' => 'Logo', 'logoDark' => 'Logo für dunkle Flächen', 'appIcon' => 'App-Icon',
            'favicon' => 'Favicon', 'iconBackground' => 'Hintergrundfarbe', 'accent' => 'Akzentfarbe',
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

        $settings->set(['accent' => $this->accent]);
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
