<?php

namespace App\Enums;

enum Salutation: string
{
    case Mr = 'herr';
    case Ms = 'frau';

    public function label(): string
    {
        return match ($this) {
            self::Mr => 'Herr',
            self::Ms => 'Frau',
        };
    }
}
