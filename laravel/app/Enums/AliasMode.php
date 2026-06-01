<?php

namespace App\Enums;

enum AliasMode: string
{
    case Random = 'random';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Random => __('Random'),
            self::Custom => __('Custom'),
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
