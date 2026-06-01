<?php

namespace App\Enums;

enum HealthVisibility: string
{
    case Public = 'public';
    case Auth   = 'auth';
    case Admin  = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Public => __('Public'),
            self::Auth   => __('Authenticated users'),
            self::Admin  => __('Admins only'),
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
