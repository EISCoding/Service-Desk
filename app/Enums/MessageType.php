<?php

namespace App\Enums;

enum MessageType: string
{
    case Public = 'public';
    case Internal = 'internal';
    case MailIn = 'mail_in';
    case MailOut = 'mail_out';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Antwort',
            self::Internal => 'Interne Notiz',
            self::MailIn => 'E-Mail eingegangen',
            self::MailOut => 'E-Mail gesendet',
        };
    }

    /**
     * Customers (portal, mails) only ever see these; internal notes never.
     */
    public function isVisibleToCustomer(): bool
    {
        return $this !== self::Internal;
    }

    /**
     * @return list<self>
     */
    public static function visibleToCustomer(): array
    {
        return array_values(array_filter(self::cases(), fn (self $type) => $type->isVisibleToCustomer()));
    }
}
