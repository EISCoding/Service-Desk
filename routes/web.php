<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/intern');

if (app()->isLocal()) {
    Route::view('/intern/styleguide', 'intern.styleguide')->name('intern.styleguide');
}
