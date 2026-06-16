<?php

namespace Tests\Feature;

use App\Enums\OrgRole;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SupportAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Izolacja organizacji oraz zasoby prywatne w AssetPolicy.
 */
class AssetPolicyTest extends TestCase
{
    use RefreshDatabase;

    private function assetFor(Organization $organization): Asset
    {
        return Asset::factory()
            ->forOrganization($organization)
            ->forCategory(AssetCategory::factory()->create())
            ->create();
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

    public function test_support_can_view_and_update_asset_of_supported_org(): void
    {
        $organization = Organization::factory()->create();
        $asset = $this->assetFor($organization);
        $support = $this->supportOf($organization);

        $this->assertTrue($support->can('view', $asset));
        $this->assertTrue($support->can('update', $asset));
        $this->assertTrue($support->can('archive', $asset));
    }

    public function test_support_cannot_view_or_update_asset_of_other_org(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $assetA = $this->assetFor($orgA);
        $support = $this->supportOf($orgB);

        $this->assertFalse($support->can('view', $assetA));
        $this->assertFalse($support->can('update', $assetA));
    }

    public function test_manager_can_view_asset_of_their_org(): void
    {
        $organization = Organization::factory()->create();
        $asset = $this->assetFor($organization);

        $manager = User::factory()->manager()->create();
        OrganizationMembership::create([
            'user_id' => $manager->id,
            'organization_id' => $organization->id,
            'role' => OrgRole::Manager,
            'is_active' => true,
        ]);

        $this->assertTrue($manager->fresh()->can('view', $asset));
    }

    public function test_plain_user_cannot_view_private_asset_not_assigned_to_them(): void
    {
        $organization = Organization::factory()->create();
        $asset = Asset::factory()
            ->forOrganization($organization)
            ->forCategory(AssetCategory::factory()->create())
            ->private()
            ->create();

        $user = User::factory()->create(); // domyślna rola: user (klient)
        OrganizationMembership::create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role' => OrgRole::User,
            'is_active' => true,
        ]);

        $this->assertFalse($user->fresh()->can('view', $asset));
    }

    public function test_plain_user_can_view_private_asset_assigned_to_them(): void
    {
        $organization = Organization::factory()->create();
        $asset = Asset::factory()
            ->forOrganization($organization)
            ->forCategory(AssetCategory::factory()->create())
            ->private()
            ->create();

        $user = User::factory()->create();
        OrganizationMembership::create([
            'user_id' => $user->id,
            'organization_id' => $organization->id,
            'role' => OrgRole::User,
            'is_active' => true,
        ]);
        $asset->assignedUsers()->attach($user->id);

        $this->assertTrue($user->fresh()->can('view', $asset->fresh()));
    }

    public function test_client_cannot_create_assets(): void
    {
        $user = User::factory()->create();      // klient (user)
        $manager = User::factory()->manager()->create();

        $this->assertFalse($user->can('create', Asset::class));
        $this->assertFalse($manager->can('create', Asset::class));
    }
}
