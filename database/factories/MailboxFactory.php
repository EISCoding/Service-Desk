<?php

namespace Database\Factories;

use App\Models\Mailbox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mailbox>
 */
class MailboxFactory extends Factory
{
    public function definition(): array
    {
        $local = fake()->unique()->randomElement(['support', 'service', 'technik', 'info', 'hilfe', 'kontakt']).fake()->unique()->numberBetween(1, 999);

        return [
            'name' => 'Support Musterfirma',
            'email' => "{$local}@musterfirma.test",
            'imap_host' => 'imap.musterfirma.test',
            'imap_username' => "{$local}@musterfirma.test",
            'imap_password' => 'imap-secret',
            'smtp_host' => 'smtp.musterfirma.test',
            'smtp_username' => "{$local}@musterfirma.test",
            'smtp_password' => 'smtp-secret',
        ];
    }

    public function default(): static
    {
        return $this->state(fn () => ['is_default' => true]);
    }
}
