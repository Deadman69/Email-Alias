<?php

namespace Database\Factories;

use App\Models\Alias;
use App\Models\InboundEmail;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InboundEmail>
 */
class InboundEmailFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'alias_id'     => Alias::factory(),
            'from_address' => fake()->safeEmail(),
            'from_name'    => fake()->name(),
            'subject'      => fake()->sentence(4),
            'body_html'    => '<p>' . fake()->paragraphs(3, true) . '</p>',
            'body_text'    => fake()->paragraphs(3, true),
            'headers'      => [
                'message-id' => '<' . fake()->uuid() . '@example.com>',
                'date'       => now()->toRfc2822String(),
            ],
            'size_bytes' => fake()->numberBetween(500, 50000),
            'read_at'    => null,
        ];
    }

    /**
     * Mark email as read.
     */
    public function read(): static
    {
        return $this->state(fn (array $attributes) => [
            'read_at' => now(),
        ]);
    }
}
