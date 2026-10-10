<?php

use App\Http\Controllers\Intern\UpdateThemeController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/intern');

Route::prefix('intern')->name('intern.')->group(function () {
    Route::middleware('auth')->group(function () {
        Route::put('darstellung', UpdateThemeController::class)->name('theme.update');
    });

    if (app()->isLocal()) {
        Route::view('styleguide', 'intern.styleguide')->name('styleguide');
    }
});
