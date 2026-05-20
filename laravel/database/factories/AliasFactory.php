<?php

namespace Database\Factories;

use App\Enums\AliasType;
use App\Models\Alias;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Alias>
 */
class AliasFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $domain = config('emailalias.domain', 'example.com');
        $localPart = Str::lower(fake()->unique()->lexify('????-????'));

        return [
            'address'    => "{$localPart}@{$domain}",
            'local_part' => $localPart,
            'type'       => AliasType::Permanent,
            'user_id'    => User::factory(),
            'expires_at' => null,
        ];
    }

    /**
     * Session alias state.
     */
    public function session(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'       => AliasType::Session,
            'expires_at' => now()->addHours(2),
        ]);
    }

    /**
     * Duration alias state with a given duration string.
     */
    public function withDuration(string $duration = '24h'): static
    {
        return $this->state(fn (array $attributes) => [
            'type'       => AliasType::Duration,
            'duration'   => $duration,
            'expires_at' => Alias::expiresAtFromDuration($duration),
        ]);
    }

    /**
     * Expired alias state.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'type'       => AliasType::Duration,
            'duration'   => '1h',
            'expires_at' => now()->subHour(),
        ]);
    }
}
