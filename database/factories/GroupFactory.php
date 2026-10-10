<?php

namespace Database\Factories;

use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->randomElement(['Support', 'Technik', 'Vertrieb', 'Buchhaltung', 'Außendienst', 'Netzwerk', 'Entwicklung', 'Einkauf']).' '.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->optional()->sentence(),
        ];
    }
}
