<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

use function Laravel\Prompts\password;
use function Laravel\Prompts\text;

/**
 * First admin after the deployment (or another one later):
 *   php artisan servicedesk:create-admin
 */
class CreateAdmin extends Command
{
    protected $signature = 'servicedesk:create-admin
        {--name= : Name}
        {--email= : E-mail address (login)}
        {--password= : Password (asked for when missing)}';

    protected $description = 'Create a staff user with the Admin role';

    public function handle(): int
    {
        $data = [
            'name' => $this->option('name') ?? text('Name', required: true),
            'email' => Str::lower(trim($this->option('email') ?? text('E-Mail-Adresse', required: true))),
            'password' => $this->option('password') ?? password('Passwort (mindestens 10 Zeichen, Buchstaben und Zahlen)', required: true),
        ];

        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', Password::defaults()],
        ], [], ['name' => 'Name', 'email' => 'E-Mail-Adresse', 'password' => 'Passwort']);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        // Roles exist after deploy.sh; create them here too for a manual setup.
        $this->callSilently('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

        $user = User::create($data);
        $user->assignRole(Role::Admin->value);

        $this->info("Admin {$user->email} angelegt. Anmeldung unter ".route('login'));

        return self::SUCCESS;
    }
}
