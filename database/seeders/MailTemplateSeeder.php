<?php

namespace Database\Seeders;

use App\Enums\SystemTemplate;
use App\Enums\TemplateType;
use App\Models\MailTemplate;
use App\Support\Html;
use Illuminate\Database\Seeder;

/**
 * Creates missing system templates with their default texts. Edited templates
 * are never overwritten; templates still holding an earlier default text get
 * the current one. Safe to run on every deploy.
 */
class MailTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach (SystemTemplate::cases() as $template) {
            $mailTemplate = MailTemplate::firstOrCreate(['key' => $template->value], [
                'type' => TemplateType::System,
                'name' => $template->label(),
                'subject' => $template->defaultSubject(),
                'body' => $template->defaultBody(),
            ]);

            if ($mailTemplate->body === Html::sanitize($template->previousDefaultBody()) && $template->previousDefaultBody() !== $template->defaultBody()) {
                $mailTemplate->update(['body' => $template->defaultBody()]);
            }
        }
    }
}
