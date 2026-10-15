<?php

namespace App\Enums;

enum CustomerType: string
{
    case Company = 'company';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Company => 'Firma',
            self::Private => 'Privatperson',
        };
    }
}
