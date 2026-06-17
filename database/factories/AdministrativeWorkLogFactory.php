<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\AdministrativeWorkLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdministrativeWorkLog>
 */
class AdministrativeWorkLogFactory extends Factory
{
    protected $model = AdministrativeWorkLog::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'location_id' => null,
            'asset_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'work_type' => fake()->randomElement(['Przegląd', 'Aktualizacja', 'Backup', 'Konfiguracja']),
            'performed_by' => User::factory()->support(),
            'performed_at' => now(),
            'duration_minutes' => fake()->numberBetween(15, 240),
            'visible_to_manager' => true,
            'visible_to_user' => false,
            'status' => PublicationStatus::Published,
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn () => ['organization_id' => $organization->id]);
    }

    public function performedBy(User $user): static
    {
        return $this->state(fn () => ['performed_by' => $user->id]);
    }

    public function performedAt(\DateTimeInterface|string $when): static
    {
        return $this->state(fn () => ['performed_at' => $when]);
    }

    public function durationMinutes(?int $minutes): static
    {
        return $this->state(fn () => ['duration_minutes' => $minutes]);
    }

    /** Widoczna dla managera (i opcjonalnie usera). */
    public function visibleToManager(bool $visible = true): static
    {
        return $this->state(fn () => ['visible_to_manager' => $visible]);
    }

    public function visibleToUser(bool $visible = true): static
    {
        return $this->state(fn () => ['visible_to_user' => $visible]);
    }

    public function status(PublicationStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function draft(): static
    {
        return $this->status(PublicationStatus::Draft);
    }
}
