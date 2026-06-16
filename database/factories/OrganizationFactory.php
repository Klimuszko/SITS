<?php

namespace Database\Factories;

use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    protected $model = Organization::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'type' => OrganizationType::Company,
            'parent_id' => null,
            'status' => OrganizationStatus::Active,
            'nip' => null,
            'address' => fake()->address(),
            'contact_email' => fake()->companyEmail(),
            'contact_phone' => null,
            'internal_note' => null,
            'default_support_user_id' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => OrganizationStatus::Inactive]);
    }
}
