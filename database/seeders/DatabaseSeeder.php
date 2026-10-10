<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        if (app()->isLocal()) {
            $this->seedLocalAdmin();
        }
    }

    /**
     * Admin account for local development (credentials from .env, see config/servicedesk.php).
     */
    private function seedLocalAdmin(): void
    {
        $config = config('servicedesk.seed_admin');

        $admin = User::firstOrCreate(
            ['email' => $config['email']],
            ['name' => $config['name'], 'password' => $config['password']],
        );

        $admin->assignRole(Role::Admin->value);

        $this->command?->info("Lokaler Admin: {$config['email']} / {$config['password']}");
    }
}
