<?php

use App\Http\Controllers\Intern\UpdateThemeController;
use App\Livewire\Intern\Settings\Security;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/intern');

/*
| Staff area. Login, logout, password reset and the two-factor challenge
| are registered by Fortify under the same /intern prefix.
*/
Route::prefix('intern')->name('intern.')->group(function () {
    Route::middleware('auth')->group(function () {
        Route::view('/', 'intern.dashboard')->name('dashboard');

        Route::livewire('einstellungen/sicherheit', Security::class)
            ->middleware('password.confirm')
            ->name('settings.security');

        Route::put('darstellung', UpdateThemeController::class)->name('theme.update');
    });

    if (app()->isLocal()) {
        Route::view('styleguide', 'intern.styleguide')->name('styleguide');
    }
});
