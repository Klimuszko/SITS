<?php

namespace Tests\Feature;

use App\Enums\OrgRole;
use App\Enums\Role;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SupportAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Izolacja organizacji w LocationPolicy (separacja danych klientów).
 */
class LocationPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function locationFor(Organization $organization): Location
    {
        return Location::factory()->forOrganization($organization)->create();
    }

    private function supportOf(Organization $organization): User
    {
        $support = User::factory()->support()->create();
        SupportAssignment::create([
            'support_user_id' => $support->id,
            'organization_id' => $organization->id,
            'is_primary' => true,
            'scope' => 'all',
            'is_active' => true,
        ]);

        return $support->fresh();
    }

    private function memberOf(Organization $organization, OrgRole $role): User
    {
        $user = User::factory()->role($role === OrgRole::Manager ? Role::Manager : Role::User)->create();
        OrganizationMembership::create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role' => $role,
            'is_active' => true,
        ]);

        return $user->fresh();
    }

    public function test_support_can_view_and_update_location_of_supported_org(): void
    {
        $organization = Organization::factory()->create();
        $location = $this->locationFor($organization);
        $support = $this->supportOf($organization);

        $this->assertTrue($support->can('view', $location));
        $this->assertTrue($support->can('update', $location));
        $this->assertTrue($support->can('archive', $location));
    }

    public function test_support_cannot_view_or_update_location_of_other_org(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $locationA = $this->locationFor($orgA);
        $support = $this->supportOf($orgB);

        $this->assertFalse($support->can('view', $locationA));
        $this->assertFalse($support->can('update', $locationA));
    }

    public function test_member_can_view_location_of_their_org(): void
    {
        $organization = Organization::factory()->create();
        $location = $this->locationFor($organization);

        $user = $this->memberOf($organization, OrgRole::User);

        $this->assertTrue($user->can('view', $location));
    }

    public function test_member_of_different_org_cannot_view_location(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $locationA = $this->locationFor($orgA);
        $outsider = $this->memberOf($orgB, OrgRole::User);

        $this->assertFalse($outsider->can('view', $locationA));
    }

    public function test_client_cannot_create_locations(): void
    {
        $user = User::factory()->create();          // klient (user)
        $manager = User::factory()->manager()->create();

        $this->assertFalse($user->can('create', Location::class));
        $this->assertFalse($manager->can('create', Location::class));
    }
}
