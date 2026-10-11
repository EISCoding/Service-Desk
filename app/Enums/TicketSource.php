<?php

namespace App\Enums;

enum TicketSource: string
{
    case Mail = 'mail';
    case Portal = 'portal';
    case Internal = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::Mail => 'E-Mail',
            self::Portal => 'Kundenportal',
            self::Internal => 'Intern',
        };
    }
}
