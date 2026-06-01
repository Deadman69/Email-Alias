<?php

namespace App\Enums;

enum Locale: string
{
    case En = 'en';
    case Fr = 'fr';

    public function label(): string
    {
        return match ($this) {
            self::En => 'English',
            self::Fr => 'Français',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function valuesForRule(): string
    {
        return implode(',', self::values());
    }
}
