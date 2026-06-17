<?php

namespace Database\Factories;

use App\Models\TicketPriority;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<TicketPriority>
 */
class TicketPriorityFactory extends Factory
{
    protected $model = TicketPriority::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'name' => Str::ucfirst(fake()->unique()->word()),
            'level' => fake()->numberBetween(1, 4),
            'color' => fake()->randomElement(['blue', 'indigo', 'amber', 'orange', 'teal', 'green', 'gray', 'slate', 'red']),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
