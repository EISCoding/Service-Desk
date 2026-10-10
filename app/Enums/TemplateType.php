<?php

namespace App\Enums;

enum TemplateType: string
{
    case System = 'system';
    case Snippet = 'snippet';

    public function label(): string
    {
        return match ($this) {
            self::System => 'Systemvorlage',
            self::Snippet => 'Textbaustein',
        };
    }
}
