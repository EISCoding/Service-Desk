<?php

use App\Enums\Theme;
use App\Models\User;

it('defaults new users to the system theme', function () {
    expect(User::factory()->create()->refresh()->theme)->toBe(Theme::System);
});

it('renders the dark class on the server for users who chose dark', function () {
    $this->actingAs(User::factory()->create(['theme' => Theme::Dark]));

    $this->blade('<x-layouts::base>Inhalt</x-layouts::base>')
        ->assertSee('class="h-full dark"', false)
        ->assertSee('data-theme="dark"', false);
});

it('renders no dark class for users who chose light', function () {
    $this->actingAs(User::factory()->create(['theme' => Theme::Light]));

    $this->blade('<x-layouts::base>Inhalt</x-layouts::base>')
        ->assertSee('class="h-full"', false)
        ->assertSee('data-theme="light"', false);
});

it('leaves the choice to the browser for guests', function () {
    $this->blade('<x-layouts::base>Inhalt</x-layouts::base>')
        ->assertDontSee('data-theme=', false)
        ->assertSee("localStorage.getItem('theme')", false);
});

it('applies the theme in the head before any stylesheet to avoid flicker', function () {
    $html = (string) $this->blade('<x-layouts::base>Inhalt</x-layouts::base>');

    $head = str($html)->between('<head>', '</head>')->toString();

    expect($head)->toContain('window.applyTheme')
        ->toContain('prefers-color-scheme: dark')
        // the script must run before the page title and stylesheet are processed
        ->and(strpos($head, 'window.applyTheme'))->toBeLessThan(strpos($head, '<title>'));
});

it('offers System, Hell and Dunkel in the switcher', function () {
    $this->blade('<x-theme-switcher />')
        ->assertSee('role="radiogroup"', false)
        ->assertSeeInOrder(['System', 'Hell', 'Dunkel']);
});

it('stores the chosen theme for the logged-in user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->putJson(route('intern.theme.update'), ['theme' => 'dark'])
        ->assertNoContent();

    expect($user->refresh()->theme)->toBe(Theme::Dark);
});

it('rejects unknown themes', function () {
    $this->actingAs(User::factory()->create())
        ->putJson(route('intern.theme.update'), ['theme' => 'pink'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('theme');
});

it('requires a login to store the theme', function () {
    $this->putJson(route('intern.theme.update'), ['theme' => 'dark'])->assertUnauthorized();
});
