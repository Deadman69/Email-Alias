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
            '1h'  => trans_choice(':count hour|:count hours', 1, ['count' => 1]),
            '12h' => trans_choice(':count hour|:count hours', 12, ['count' => 12]),
            '24h' => trans_choice(':count hour|:count hours', 24, ['count' => 24]),
            '7d'  => trans_choice(':count day|:count days', 7, ['count' => 7]),
            '30d' => trans_choice(':count day|:count days', 30, ['count' => 30]),
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::Session   => __('Session'),
            self::Duration  => __('Duration'),
            self::Permanent => __('Permanent'),
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
