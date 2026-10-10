<?php

namespace App\Models;

use App\Enums\TemplateType;
use App\Models\Concerns\LogsActivity;
use App\Support\Html;
use Database\Factories\MailTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * System templates (automatic mails, can be switched off) and text snippets
 * for replies. Bodies are HTML with placeholders like {ticket.nummer}.
 */
class MailTemplate extends Model
{
    /** @use HasFactory<MailTemplateFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = ['type', 'key', 'name', 'subject', 'body', 'is_active'];

    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'type' => TemplateType::class,
            'is_active' => 'boolean',
        ];
    }

    protected function body(): Attribute
    {
        return Attribute::make(set: fn (?string $value) => Html::sanitize($value));
    }

    #[Scope]
    protected function snippets(Builder $query): void
    {
        $query->where('type', TemplateType::Snippet->value)->orderBy('name');
    }

    public function isSystem(): bool
    {
        return $this->type === TemplateType::System;
    }

    /**
     * Active system template by key (null when missing or switched off).
     */
    public static function system(string $key): ?self
    {
        return static::where('type', TemplateType::System->value)->where('key', $key)->where('is_active', true)->first();
    }
}
