<?php

namespace Database\Factories;

use App\Enums\TemplateType;
use App\Models\MailTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MailTemplate>
 */
class MailTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => TemplateType::Snippet,
            'name' => fake()->unique()->words(3, true),
            'body' => '<div>'.fake()->sentence().'</div>',
        ];
    }

    public function system(string $key): static
    {
        return $this->state(fn () => ['type' => TemplateType::System, 'key' => $key, 'subject' => 'Betreff {ticket.nummer}']);
    }
}
