<?php

namespace Database\Factories;

use App\Enums\Salutation;
use App\Models\Contact;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Contact>
 */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        $salutation = fake()->randomElement(Salutation::cases());

        return [
            'customer_id' => Customer::factory(),
            'salutation' => $salutation,
            'first_name' => $salutation === Salutation::Mr ? fake()->firstNameMale() : fake()->firstNameFemale(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'position' => fake()->optional()->jobTitle(),
        ];
    }

    public function primary(): static
    {
        return $this->state(fn () => ['is_primary' => true]);
    }

    public function withPortalAccess(): static
    {
        return $this->state(fn () => ['portal_access' => true]);
    }
}
