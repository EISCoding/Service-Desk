<?php

use App\Livewire\Intern\Admin\Branding\Index;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\Branding;
use App\Support\IconGenerator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    $this->admin = User::factory()->admin()->create();
});

/**
 * PNG upload drawn with GD: a filled square, optionally on a transparent canvas.
 */
function brandingImage(string $name, int $size, bool $transparent = false): UploadedFile
{
    $image = imagecreatetruecolor($size, $size);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, $transparent ? imagecolorallocatealpha($image, 0, 0, 0, 127) : imagecolorallocate($image, 0, 102, 204));
    $inset = intdiv($size, 4);
    imagefilledrectangle($image, $inset, $inset, $size - $inset, $size - $inset, imagecolorallocate($image, 255, 120, 0));

    ob_start();
    imagepng($image);

    return UploadedFile::fake()->createWithContent($name, ob_get_clean());
}

function freshBranding(): Branding
{
    app()->forgetInstance(AppSettings::class);

    return app(Branding::class);
}

it('is only available with admin.settings', function () {
    $this->actingAs(User::factory()->staff()->create())->get('/intern/admin/erscheinungsbild')->assertForbidden();

    $this->actingAs($this->admin)->get('/intern/admin')->assertSee(route('intern.admin.branding'));
    $this->get('/intern/admin/erscheinungsbild')->assertOk()
        ->assertSee('<title>Erscheinungsbild · ServiceDesk</title>', false)
        ->assertSee(['Logo', 'Für dunkle Flächen', 'App-Icon', 'Favicon', 'Akzentfarbe']);
});

it('applies the accent color', function () {
    $this->actingAs($this->admin);

    Livewire::test(Index::class)->set('accent', 'orange')->call('save')
        ->assertHasNoErrors()->assertRedirect('/intern/admin/erscheinungsbild');

    freshBranding();
    $this->get('/portal/login')
        ->assertSee(':root{--accent:#c93400', false)
        ->assertSee('.dark{--accent:#ff9f0a', false);
});

it('validates uploads and the background color', function () {
    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->set('accent', 'neon')
        ->set('logo', UploadedFile::fake()->create('logo.svg', 10, 'image/svg+xml'))
        ->set('logoDark', UploadedFile::fake()->create('logo.pdf', 10, 'application/pdf'))
        ->set('appIcon', UploadedFile::fake()->image('icon.png', 64, 64))
        ->set('favicon', UploadedFile::fake()->create('favicon.svg', 1, 'image/svg+xml'))
        ->set('iconBackground', 'red; color: blue')
        ->call('save')
        ->assertHasErrors(['accent', 'logo', 'logoDark', 'appIcon', 'favicon', 'iconBackground']);
});

it('uploads, serves and removes the light and dark logo', function () {
    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->set('logo', UploadedFile::fake()->image('logo.png', 128, 128))
        ->set('logoDark', UploadedFile::fake()->image('logo-dark.png', 128, 128))
        ->call('save')
        ->assertHasNoErrors();

    $branding = freshBranding();
    expect($branding->hasDarkLogo())->toBeTrue();
    $this->get($branding->logoUrl())->assertOk()->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
    $this->get($branding->darkLogoUrl())->assertOk()->assertHeader('Content-Type', 'image/png');

    // Both variants are in the page; CSS shows the matching one.
    $this->get('/portal/login')->assertSee($branding->logoUrl(), false)->assertSee($branding->darkLogoUrl(), false);

    $paths = [app(AppSettings::class)->get('logo'), app(AppSettings::class)->get('logo_dark')];
    Livewire::test(Index::class)->call('remove', 'logoDark');
    Livewire::test(Index::class)->call('remove', 'logo');

    Storage::disk('local')->assertMissing($paths);
    $branding = freshBranding();
    expect($branding->logoUrl())->toBeNull()->and($branding->darkLogoUrl())->toBeNull();
    $this->get('/logo')->assertNotFound();
    $this->get('/branding/logo-dark')->assertNotFound();
});

it('falls back to the regular logo in dark mode', function () {
    $this->actingAs($this->admin);

    Livewire::test(Index::class)->set('logo', UploadedFile::fake()->image('logo.png', 64, 64))->call('save');

    $branding = freshBranding();
    expect($branding->hasDarkLogo())->toBeFalse()
        ->and($branding->darkLogoUrl())->toBe($branding->logoUrl());
});

it('generates all app icons from one upload', function () {
    $this->actingAs($this->admin);

    Livewire::test(Index::class)
        ->set('appIcon', brandingImage('icon.png', 600))
        ->set('iconBackground', '#112233')
        ->call('save')
        ->assertHasNoErrors();

    $branding = freshBranding();
    expect($branding->hasAppIcon())->toBeTrue();

    foreach (IconGenerator::ICONS as $file => [$size]) {
        $response = $this->get($branding->iconUrl($file))->assertOk()->assertHeader('Content-Type', 'image/png');
        [$width, $height] = getimagesizefromstring($response->streamedContent());
        expect([$width, $height])->toBe([$size, $size]);
    }

    // Opaque image: no badge silhouette, the bundled one stays.
    expect($branding->iconUrl('badge-96.png'))->toBe(asset('icons/badge-96.png'));

    // Manifest, page head and favicon use the new icons.
    $this->get('/manifest.json')->assertJsonFragment(['src' => $branding->iconUrl('icon-512.png', absolute: false), 'purpose' => 'maskable']);
    $this->get('/portal/login')
        ->assertSee('rel="apple-touch-icon" sizes="180x180" href="'.$branding->iconUrl('apple-touch-icon.png').'"', false)
        ->assertSee('href="'.$branding->iconUrl('icon-any-192.png').'"', false);
});

it('puts transparent logos on the background and builds a push badge', function () {
    $this->actingAs($this->admin);

    Livewire::test(Index::class)->set('appIcon', brandingImage('logo.png', 512, transparent: true))->set('iconBackground', '#ff0000')->call('save')->assertHasNoErrors();

    $branding = freshBranding();
    $apple = imagecreatefromstring($this->get($branding->iconUrl('apple-touch-icon.png'))->streamedContent());
    $any = imagecreatefromstring($this->get($branding->iconUrl('icon-any-512.png'))->streamedContent());

    // Corner: background color on the home screen icon, transparent in the "any" icon.
    expect(imagecolorsforindex($apple, imagecolorat($apple, 2, 2)))->toMatchArray(['red' => 255, 'green' => 0, 'blue' => 0, 'alpha' => 0])
        ->and(imagecolorsforindex($any, imagecolorat($any, 2, 2))['alpha'])->toBe(127);

    $badge = $this->get($branding->iconUrl('badge-96.png'))->assertOk()->streamedContent();
    $image = imagecreatefromstring($badge);
    expect(imagecolorsforindex($image, imagecolorat($image, 48, 48)))->toMatchArray(['red' => 255, 'green' => 255, 'blue' => 255]);
});

it('regenerates the icons when only the background changes', function () {
    $this->actingAs($this->admin);

    Livewire::test(Index::class)->set('appIcon', brandingImage('logo.png', 256, transparent: true))->call('save');
    $before = $this->get(freshBranding()->iconUrl('icon-192.png'))->streamedContent();

    Livewire::test(Index::class)->set('iconBackground', '#000000')->call('save')->assertHasNoErrors();

    $branding = freshBranding();
    expect($branding->iconBackground())->toBe('#000000')
        ->and($this->get($branding->iconUrl('icon-192.png'))->streamedContent())->not->toBe($before);
});

it('removes the app icon and goes back to the bundled icons', function () {
    $this->actingAs($this->admin);

    Livewire::test(Index::class)->set('appIcon', brandingImage('icon.png', 256))->call('save');
    $url = freshBranding()->iconUrl('icon-192.png');

    Livewire::test(Index::class)->call('remove', 'appIcon');

    $branding = freshBranding();
    expect($branding->hasAppIcon())->toBeFalse()
        ->and($branding->iconUrl('icon-192.png'))->toBe(asset('icons/icon-192.png'));
    $this->get($url)->assertNotFound();
    Storage::disk('local')->assertMissing('branding/icons/icon-192.png');
});

it('uses an uploaded favicon, as ICO or scaled PNG', function () {
    $this->actingAs($this->admin);

    Livewire::test(Index::class)->set('favicon', brandingImage('favicon.png', 128))->call('save')->assertHasNoErrors();

    $favicon = freshBranding()->favicon();
    expect($favicon['type'])->toBe('image/png');
    $png = $this->get($favicon['url'])->assertOk()->streamedContent();
    expect(getimagesizefromstring($png)[0])->toBe(32);
    $this->get('/portal/login')->assertSee('<link rel="icon" type="image/png" href="'.$favicon['url'].'">', false);

    Livewire::test(Index::class)
        ->set('favicon', UploadedFile::fake()->createWithContent('favicon.ico', base64_decode('AAABAAEAAQEAAAEAIAAwAAAAFgAAACgAAAABAAAAAgAAAAEAIAAAAAAABAAAAAAAAAAAAAAAAAAAAAAAAAD/AAD/AAAAAA==')))
        ->call('save')
        ->assertHasNoErrors();

    $favicon = freshBranding()->favicon();
    expect($favicon['type'])->toBe('image/x-icon');
    $this->get($favicon['url'])->assertOk()->assertHeader('Content-Type', 'image/x-icon');

    Livewire::test(Index::class)->call('remove', 'favicon');
    expect(freshBranding()->favicon()['url'])->toBe(asset('icons/icon-any-192.png'));
});

it('only serves known branding files', function () {
    $this->get('/branding/app-icon-source')->assertNotFound();
    $this->get('/branding/..%2F.env')->assertNotFound();
    $this->get('/branding/icon-192.png')->assertNotFound(); // no custom icons yet
});

it('sends push notifications with the branded icon and badge', function () {
    $this->actingAs($this->admin);
    Livewire::test(Index::class)->set('appIcon', brandingImage('logo.png', 256, transparent: true))->call('save');

    $branding = freshBranding();
    expect($branding->iconUrl('icon-192.png'))->toContain('/branding/icon-192.png')
        ->and($branding->iconUrl('badge-96.png'))->toContain('/branding/badge-96.png');
});
