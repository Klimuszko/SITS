<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Models\Location;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    protected $model = Location::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'parent_id' => null,
            'name' => ucfirst(fake()->unique()->words(2, true)),
            'type' => LocationType::Building,
            'description' => null,
            'status' => 'active',
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn () => ['organization_id' => $organization->id]);
    }

    public function childOf(Location $parent): static
    {
        return $this->state(fn () => [
            'organization_id' => $parent->organization_id,
            'parent_id' => $parent->id,
        ]);
    }

    public function type(LocationType $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }
}
