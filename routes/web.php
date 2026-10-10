<?php

use App\Http\Controllers\Intern\AttachmentController;
use App\Http\Controllers\Intern\TicketPageController;
use App\Http\Controllers\Intern\UpdateThemeController;
use App\Http\Controllers\Portal\InvitationController;
use App\Livewire\Intern\Admin\Groups\Index as GroupIndex;
use App\Livewire\Intern\Admin\Users\Index as UserIndex;
use App\Livewire\Intern\Appointments\Index as AppointmentIndex;
use App\Livewire\Intern\Customers\Index as CustomerIndex;
use App\Livewire\Intern\Customers\Show as CustomerShow;
use App\Livewire\Intern\Settings\Security;
use App\Models\Customer;
use App\Models\Group;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/intern');

/*
| Staff area. Login, logout, password reset and the two-factor challenge
| are registered by Fortify under the same /intern prefix.
*/
Route::prefix('intern')->name('intern.')->middleware(['auth', 'active'])->group(function () {
    // Start page: the first area the user may open.
    Route::get('/', fn () => redirect()->route(Navigation::main(auth()->user())[0]['route'] ?? 'intern.settings.security'))
        ->name('dashboard');

    // Areas built in later phases (placeholders until then).
    Route::get('tickets', [TicketPageController::class, 'index'])->name('tickets.index');
    Route::get('tickets/{ticket}', [TicketPageController::class, 'show'])->name('tickets.show');
    Route::view('vorlagen', 'intern.placeholder', [
        'title' => 'Vorlagen', 'icon' => 'document-duplicate', 'heading' => 'Noch keine Vorlagen',
        'description' => 'Mail-Vorlagen und Textbausteine folgen in Phase 4.',
    ])->name('templates.index');

    Route::livewire('termine', AppointmentIndex::class)->name('appointments.index');

    Route::livewire('kunden', CustomerIndex::class)
        ->middleware('can:viewAny,'.Customer::class)
        ->name('customers.index');
    Route::livewire('kunden/{customer}', CustomerShow::class)
        ->middleware('can:view,customer')
        ->name('customers.show');

    Route::livewire('einstellungen/sicherheit', Security::class)
        ->middleware('password.confirm')
        ->name('settings.security');

    Route::put('darstellung', UpdateThemeController::class)->name('theme.update');

    Route::get('anhaenge/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');

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

/*
| Customer portal (guard "contact"). Login and tickets follow in Phase 5.
*/
Route::prefix('portal')->name('portal.')->group(function () {
    Route::view('willkommen', 'portal.invitation-done')->name('invitation.done');
    Route::get('einladung/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('einladung', [InvitationController::class, 'store'])->middleware('throttle:6,1')->name('invitation.store');
});

if (app()->isLocal()) {
    Route::view('intern/styleguide', 'intern.styleguide')->name('intern.styleguide');
}
