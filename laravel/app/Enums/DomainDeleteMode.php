<?php

namespace App\Enums;

enum DomainDeleteMode: string
{
    case Keep    = 'keep';
    case Cascade = 'cascade';

    public function label(): string
    {
        return match ($this) {
            self::Keep    => __('Keep aliases'),
            self::Cascade => __('Delete aliases'),
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
