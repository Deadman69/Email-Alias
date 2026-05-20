<?php

namespace App\Enums;

enum AliasType: string
{
    case Session = 'session';
    case Duration = 'duration';
    case Permanent = 'permanent';

    /**
     * @return array<string, string>
     */
    public static function durationsOptions(): array
    {
        return [
            '1h'  => '1 hour',
            '12h' => '12 hours',
            '24h' => '24 hours',
            '7d'  => '7 days',
            '30d' => '30 days',
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Session   => 'Session',
            self::Duration  => 'Duration',
            self::Permanent => 'Permanent',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Session   => 'clock',
            self::Duration  => 'calendar',
            self::Permanent => 'infinity',
        };
    }
}
