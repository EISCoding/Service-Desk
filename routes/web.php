<?php

use App\Http\Controllers\Intern\UpdateThemeController;
use App\Livewire\Intern\Admin\Groups\Index as GroupIndex;
use App\Livewire\Intern\Admin\Users\Index as UserIndex;
use App\Livewire\Intern\Settings\Security;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/intern');

/*
| Staff area. Login, logout, password reset and the two-factor challenge
| are registered by Fortify under the same /intern prefix.
*/
Route::prefix('intern')->name('intern.')->middleware(['auth', 'active'])->group(function () {
    Route::redirect('/', '/intern/tickets')->name('dashboard');

    // Areas built in later phases (placeholders until then).
    Route::view('tickets', 'intern.placeholder', [
        'title' => 'Tickets', 'icon' => 'inbox-stack', 'heading' => 'Noch keine Tickets',
        'description' => 'Die Ticketverwaltung folgt in Phase 3.',
    ])->name('tickets.index');
    Route::view('kunden', 'intern.placeholder', [
        'title' => 'Kunden', 'icon' => 'building-office-2', 'heading' => 'Noch keine Kunden',
        'description' => 'Kunden und Ansprechpartner folgen in Phase 2.',
    ])->name('customers.index');
    Route::view('termine', 'intern.placeholder', [
        'title' => 'Termine', 'icon' => 'calendar-days', 'heading' => 'Keine anstehenden Termine',
        'description' => 'VOS-Termine folgen in Phase 3.',
    ])->name('appointments.index');
    Route::view('vorlagen', 'intern.placeholder', [
        'title' => 'Vorlagen', 'icon' => 'document-duplicate', 'heading' => 'Noch keine Vorlagen',
        'description' => 'Mail-Vorlagen und Textbausteine folgen in Phase 4.',
    ])->name('templates.index');

    Route::livewire('einstellungen/sicherheit', Security::class)
        ->middleware('password.confirm')
        ->name('settings.security');

    Route::put('darstellung', UpdateThemeController::class)->name('theme.update');

    // Admin area: requires at least one admin.* permission; each page checks its own permission.
    Route::prefix('admin')->name('admin.')->middleware('can:access-admin')->group(function () {
        Route::view('/', 'intern.admin.index')->name('index');

        Route::livewire('benutzer', UserIndex::class)
            ->middleware('can:viewAny,'.User::class)
            ->name('users.index');

        Route::livewire('gruppen', GroupIndex::class)
            ->middleware('can:viewAny,'.Group::class)
            ->name('groups.index');
    });
});

if (app()->isLocal()) {
    Route::view('intern/styleguide', 'intern.styleguide')->name('intern.styleguide');
}
