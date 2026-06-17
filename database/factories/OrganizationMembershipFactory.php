<?php

namespace Database\Factories;

use App\Enums\ManagerScope;
use App\Enums\OrgRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrganizationMembership>
 */
class OrganizationMembershipFactory extends Factory
{
    protected $model = OrganizationMembership::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'role' => OrgRole::User,
            'manager_scope' => null,
            'is_active' => true,
        ];
    }

    public function forUser(User $user): static
    {
        return $this->state(fn () => ['user_id' => $user->id]);
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn () => ['organization_id' => $organization->id]);
    }

    public function manager(ManagerScope $scope = ManagerScope::OwnUnit): static
    {
        return $this->state(fn () => [
            'role' => OrgRole::Manager,
            'manager_scope' => $scope,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
