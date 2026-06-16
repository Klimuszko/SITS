<?php

namespace Database\Factories;

use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ticket>
 */
class TicketFactory extends Factory
{
    protected $model = Ticket::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'number' => sprintf('T-%d-%06d', now()->year, fake()->unique()->numberBetween(1, 999999)),
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'requester_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'location_id' => null,
            'asset_id' => null,
            'assigned_support_id' => null,
            'status' => TicketStatus::New,
            'ticket_priority_id' => null,
            'ticket_category_id' => null,
            'first_response_at' => null,
            'last_reply_at' => now(),
            'resolved_at' => null,
            'closed_at' => null,
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn () => ['organization_id' => $organization->id]);
    }

    public function status(TicketStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }
}
