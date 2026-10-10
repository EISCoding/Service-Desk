<?php

namespace Database\Seeders;

use App\Enums\SystemTemplate;
use App\Enums\TemplateType;
use App\Models\MailTemplate;
use Illuminate\Database\Seeder;

/**
 * Creates missing system templates with their default texts. Existing
 * templates are never overwritten, so it is safe to run on every deploy.
 */
class MailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SystemTemplate::cases() as $template) {
            MailTemplate::firstOrCreate(['key' => $template->value], [
                'type' => TemplateType::System,
                'name' => $template->label(),
                'subject' => $template->defaultSubject(),
                'body' => $template->defaultBody(),
            ]);
        }
    }
}
