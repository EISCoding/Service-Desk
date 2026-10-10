<?php

namespace Database\Seeders;

use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerNote;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Sample customers with contacts and notes for local development.
 */
class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        if (Customer::exists()) {
            return;
        }

        $author = User::first();

        Customer::factory()->count(12)->create()->each(function (Customer $customer, int $index) use ($author) {
            Contact::factory()->for($customer)->primary()->create();
            Contact::factory()->for($customer)->count(fake()->numberBetween(0, 3))->create();

            if ($index % 3 === 0) {
                CustomerNote::factory()->for($customer)->pinned()->create(['user_id' => $author?->id, 'body' => 'Wartungsvertrag bis Ende des Jahres. Rechnungen bitte an die Buchhaltung.']);
            }
        });

        Customer::factory()->archived()->create(['company' => 'Altkunde GmbH']);
    }
}
