<?php

namespace App\Enums;

enum EmailViewMode: string
{
    case Rendered = 'rendered';
    case Raw      = 'raw';

    public function label(): string
    {
        return match ($this) {
            self::Rendered => __('Rendered'),
            self::Raw      => __('Raw'),
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
