<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Http\Middleware\EnsureContactCanUsePortal;
use App\Http\Middleware\EnsureUserIsActive;
use App\Mail\Inbound\ImapMailboxConnector;
use App\Mail\Inbound\MailboxConnector;
use App\Mail\SystemMailer;
use App\Models\Appointment;
use App\Models\Attachment;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\Group;
use App\Models\Mailbox;
use App\Models\MailTemplate;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Support\AppSettings;
use App\Support\QueueAfterResponse;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Queue\Events\JobQueued;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AppSettings::class);
        $this->app->singleton(QueueAfterResponse::class);
        $this->app->bind(MailboxConnector::class, ImapMailboxConnector::class);
    }

    public function boot(): void
    {
        // Staff password reset (Fortify) in the branded mail layout.
        ResetPassword::toMailUsing(fn (User $user, string $token) => SystemMailer::via(new MailMessage)
            ->subject('Passwort zurücksetzen')
            ->greeting("Hallo {$user->name},")
            ->line('für dein Konto wurde das Zurücksetzen des Passworts angefordert.')
            ->action('Passwort zurücksetzen', url(route('password.reset', ['token' => $token, 'email' => $user->getEmailForPasswordReset()], false)))
            ->line('Der Link ist '.config('auth.passwords.users.expire').' Minuten gültig. Falls du das nicht angefordert hast, kannst du diese E-Mail ignorieren.')
            ->salutation(config('app.name'))
            ->view(['mail.notification', 'mail.notification-text']));

        // Mails and notifications queued by a web request go out right after its response.
        if (! $this->app->runningInConsole() && config('servicedesk.queue.after_response')) {
            Event::listen(JobQueued::class, fn () => $this->app->make(QueueAfterResponse::class)->schedule());
        }

        // Company name and reopen period from the admin settings.
        $this->app->make(AppSettings::class)->apply();

        // German dates ("vor 5 Minuten", "3. Okt. 2026").
        Carbon::setLocale(config('app.locale'));

        // Short, stable type names in polymorphic columns (activity log, roles).
        Relation::enforceMorphMap([
            'user' => User::class,
            'group' => Group::class,
            'customer' => Customer::class,
            'contact' => Contact::class,
            'customer_note' => CustomerNote::class,
            'ticket' => Ticket::class,
            'ticket_message' => TicketMessage::class,
            'attachment' => Attachment::class,
            'appointment' => Appointment::class,
            'mailbox' => Mailbox::class,
            'mail_template' => MailTemplate::class,
        ]);

        // Catch N+1 queries and unfillable attributes during development and tests.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Password policy for staff (start passwords, password changes and resets).
        Password::defaults(fn () => Password::min(10)->letters()->numbers());

        // Access to /intern/admin: any of the admin.* permissions.
        Gate::define('access-admin', fn (User $user) => $user->hasAnyPermission(
            array_map(fn (Permission $permission) => $permission->value, Permission::admin())
        ));

        // Re-apply these route middlewares to Livewire requests of the protected pages.
        Livewire::addPersistentMiddleware([RequirePassword::class, EnsureUserIsActive::class, EnsureContactCanUsePortal::class]);
    }
}
