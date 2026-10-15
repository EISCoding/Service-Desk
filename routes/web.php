<?php

use App\Enums\Permission;
use App\Http\Controllers\BrandingLogoController;
use App\Http\Controllers\Intern\Admin\LogDownloadController;
use App\Http\Controllers\Intern\AttachmentController;
use App\Http\Controllers\Intern\MailPreviewController;
use App\Http\Controllers\Intern\TicketPageController;
use App\Http\Controllers\Intern\UpdateThemeController;
use App\Http\Controllers\ManifestController;
use App\Http\Controllers\Portal\AttachmentController as PortalAttachmentController;
use App\Http\Controllers\Portal\InvitationController;
use App\Http\Controllers\Portal\LoginController;
use App\Http\Controllers\Portal\PasswordResetController;
use App\Livewire\Install\Wizard;
use App\Livewire\Intern\Admin\Groups\Index as GroupIndex;
use App\Livewire\Intern\Admin\Logs\Index as LogIndex;
use App\Livewire\Intern\Admin\Mailboxes\Index as MailboxIndex;
use App\Livewire\Intern\Admin\Settings\Index as AdminSettings;
use App\Livewire\Intern\Admin\Users\Index as UserIndex;
use App\Livewire\Intern\Appointments\Index as AppointmentIndex;
use App\Livewire\Intern\Customers\Index as CustomerIndex;
use App\Livewire\Intern\Customers\Show as CustomerShow;
use App\Livewire\Intern\Dashboard;
use App\Livewire\Intern\Settings\General as GeneralSettings;
use App\Livewire\Intern\Settings\Notifications as NotificationSettings;
use App\Livewire\Intern\Settings\Security;
use App\Livewire\Intern\Settings\Signature;
use App\Livewire\Intern\Templates\Index as TemplateIndex;
use App\Livewire\Intern\Tickets\Board;
use App\Livewire\Portal\Profile as PortalProfile;
use App\Livewire\Portal\Tickets\Create as PortalCreateTicket;
use App\Livewire\Portal\Tickets\Index as PortalTickets;
use App\Livewire\Portal\Tickets\Show as PortalTicket;
use App\Models\Customer;
use App\Models\Group;
use App\Models\Mailbox;
use App\Models\MailTemplate;
use App\Models\User;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/intern');

Route::get('manifest.webmanifest', ManifestController::class)->name('manifest');

// Browser installer of a fresh deployment (gone once installed, see RedirectToInstaller).
Route::livewire('install', Wizard::class)->name('install');

/*
| Staff area. Login, logout, password reset and the two-factor challenge
| are registered by Fortify under the same /intern prefix.
*/
Route::prefix('intern')->name('intern.')->middleware(['auth', 'active'])->group(function () {
    // Start page: overview for ticket users, otherwise the first area the user may open.
    Route::livewire('/', Dashboard::class)->name('dashboard');

    // Areas built in later phases (placeholders until then).
    Route::get('tickets', [TicketPageController::class, 'index'])->name('tickets.index');
    Route::livewire('tickets/board', Board::class)->name('tickets.board');
    Route::get('tickets/{ticket}', [TicketPageController::class, 'show'])
        ->whereNumber('ticket')
        ->missing(fn () => redirect()->route('intern.tickets.index')->with('toast', ['message' => 'Dieses Ticket gibt es nicht mehr.', 'type' => 'info']))
        ->name('tickets.show');

    Route::livewire('termine', AppointmentIndex::class)->name('appointments.index');

    Route::livewire('vorlagen', TemplateIndex::class)
        ->middleware('can:viewAny,'.MailTemplate::class)
        ->name('templates.index');

    Route::livewire('kunden', CustomerIndex::class)
        ->middleware('can:viewAny,'.Customer::class)
        ->name('customers.index');
    Route::livewire('kunden/{customer}', CustomerShow::class)
        ->middleware('can:view,customer')
        ->name('customers.show');

    Route::livewire('einstellungen/sicherheit', Security::class)
        ->middleware('password.confirm')
        ->name('settings.security');

    Route::livewire('einstellungen', GeneralSettings::class)->name('settings.general');
    Route::livewire('einstellungen/benachrichtigungen', NotificationSettings::class)->name('settings.notifications');
    Route::livewire('einstellungen/signatur', Signature::class)->name('settings.signature');

    Route::put('darstellung', UpdateThemeController::class)->name('theme.update');

    Route::get('anhaenge/{attachment}', [AttachmentController::class, 'show'])->name('attachments.show');

    // Admin area: requires at least one admin.* permission; each page checks its own permission.
    Route::prefix('admin')->name('admin.')->middleware('can:access-admin')->group(function () {
        Route::view('/', 'intern.admin.index')->name('index');

        Route::livewire('benutzer', UserIndex::class)
            ->middleware('can:viewAny,'.User::class)
            ->name('users.index');

        Route::livewire('protokolle', LogIndex::class)
            ->middleware('can:'.Permission::AdminLogs->value)
            ->name('logs');
        Route::get('protokolle/datei/{file}', LogDownloadController::class)
            ->middleware('can:'.Permission::AdminLogs->value)
            ->where('file', '[A-Za-z0-9._-]+\\.log')
            ->name('logs.download');

        Route::livewire('einstellungen', AdminSettings::class)
            ->middleware('can:'.Permission::AdminSettings->value)
            ->name('settings');
        Route::get('einstellungen/mail-vorschau', MailPreviewController::class)
            ->middleware('can:'.Permission::AdminSettings->value)
            ->name('settings.mail-preview');

        Route::livewire('postfaecher', MailboxIndex::class)
            ->middleware('can:viewAny,'.Mailbox::class)
            ->name('mailboxes.index');

        Route::livewire('gruppen', GroupIndex::class)
            ->middleware('can:viewAny,'.Group::class)
            ->name('groups.index');
    });
});

Route::get('logo', BrandingLogoController::class)->name('branding.logo');

/*
| Customer portal (guard "contact").
*/
Route::prefix('portal')->name('portal.')->group(function () {
    Route::middleware('guest:contact')->group(function () {
        Route::get('login', [LoginController::class, 'create'])->name('login');
        Route::post('login', [LoginController::class, 'store'])->name('login.store');
        Route::get('passwort-vergessen', [PasswordResetController::class, 'create'])->name('password.request');
        Route::post('passwort-vergessen', [PasswordResetController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
        Route::get('passwort-zuruecksetzen/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
        Route::post('passwort-zuruecksetzen', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
    });

    Route::get('einladung/{token}', [InvitationController::class, 'show'])->name('invitation.show');
    Route::post('einladung', [InvitationController::class, 'store'])->middleware('throttle:6,1')->name('invitation.store');

    Route::middleware(['auth:contact', 'portal'])->group(function () {
        Route::post('logout', [LoginController::class, 'destroy'])->name('logout');
        Route::put('darstellung', UpdateThemeController::class)->name('theme.update');
        Route::livewire('profil', PortalProfile::class)->name('profile');
        Route::livewire('/', PortalTickets::class)->name('home');
        Route::livewire('tickets/neu', PortalCreateTicket::class)->name('tickets.create');
        Route::livewire('tickets/{ticket}', PortalTicket::class)
            ->missing(fn () => redirect()->route('portal.home'))
            ->name('tickets.show');
        Route::get('anhaenge/{attachment}', [PortalAttachmentController::class, 'show'])->name('attachments.show');
    });
});

if (app()->isLocal()) {
    Route::view('intern/styleguide', 'intern.styleguide')->name('intern.styleguide');
}
