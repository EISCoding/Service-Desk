<?php

use App\Models\User;
use App\Support\Icon;
use App\Support\Navigation;
use Illuminate\Support\Facades\File;

it('resolves Boxicons names to regular and solid components', function () {
    expect(Icon::component('home'))->toBe('bx-home')
        ->and(Icon::component('home', solid: true))->toBe('bxs-home')
        // no filled variant: falls back to the regular icon
        ->and(Icon::component('slider-alt', solid: true))->toBe('bx-slider-alt')
        // explicit set prefix stays as it is
        ->and(Icon::component('bxs-hand'))->toBe('bxs-hand');
});

it('renders Boxicons as inline SVG in the current text color', function () {
    $this->blade('<x-bx-envelope class="size-5" />')
        ->assertSee('viewBox="0 0 24 24"', false)
        ->assertSee('fill="currentColor"', false)
        ->assertSee('class="size-5"', false);
});

it('has an icon for every navigation entry', function () {
    $admin = User::factory()->admin()->create();

    foreach ([...Navigation::main($admin), ...Navigation::admin($admin)] as $item) {
        expect(resource_path("svg/boxicons/regular/{$item['icon']}.svg"))->toBeFile();
    }
});

it('uses no Heroicons anymore', function () {
    $files = collect(File::allFiles(resource_path('views')))->merge(File::allFiles(app_path()));

    foreach ($files as $file) {
        expect(File::get($file->getPathname()))->not->toContain('heroicon', $file->getRelativePathname());
    }
});
