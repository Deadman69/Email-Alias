<?php

namespace App\Enums;

enum EmailFilter: string
{
    case All    = 'all';
    case Unread = 'unread';
    case Read   = 'read';

    public function label(): string
    {
        return match ($this) {
            self::All    => __('All'),
            self::Unread => __('Unread'),
            self::Read   => __('Read'),
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
