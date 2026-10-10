<?php

namespace App\Enums;

enum Theme: string
{
    case System = 'system';
    case Light = 'light';
    case Dark = 'dark';

    public function label(): string
    {
        return match ($this) {
            self::System => 'System',
            self::Light => 'Hell',
            self::Dark => 'Dunkel',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::System => 'computer-desktop',
            self::Light => 'sun',
            self::Dark => 'moon',
        };
    }
}
