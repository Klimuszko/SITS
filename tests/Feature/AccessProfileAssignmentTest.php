<?php

namespace Tests\Feature;

use App\Enums\OrgRole;
use App\Enums\Permission;
use App\Livewire\Users\ManageForm;
use App\Models\AccessProfile;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\AccessProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Krok B2 — przypisywanie profili: personel (users.access_profile_id) oraz klient
 * per organizacja (organization_memberships.access_profile_id), z zabezpieczeniami.
 */
class AccessProfileAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessProfileSeeder::class);
    }

    private function profile(string $key): AccessProfile
    {
        return AccessProfile::where('key', $key)->firstOrFail();
    }

    public function test_admin_assigns_staff_profile_to_support_user(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $support = User::factory()->support()->create();

        // Własny profil personelu z audit.view ponad domyślny zestaw supporta.
        $profile = AccessProfile::create([
            'key' => 'support-plus', 'name' => 'Support+', 'applies_to' => AccessProfile::APPLIES_STAFF,
            'is_system' => false, 'is_active' => true,
            'permissions' => array_merge(AccessProfile::defaultPermissionsForRole(\App\Enums\Role::Support), [Permission::AuditView->value]),
        ]);

        Livewire::test(ManageForm::class, ['user' => $support])
            ->set('access_profile_id', $profile->id)
            ->call('save')
            ->assertHasNoErrors();

        $support->refresh();
        $this->assertSame($profile->id, $support->access_profile_id);
        $this->assertTrue($support->hasPermission(Permission::AuditView));   // z przypisanego profilu
    }

    public function test_client_role_forces_global_profile_null(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $client = User::factory()->create(); // rola user

        // Próba ustawienia profilu personelu na koncie klienta — zerowane przy zapisie.
        Livewire::test(ManageForm::class, ['user' => $client])
            ->set('role', 'user')
            ->set('access_profile_id', $this->profile(AccessProfile::SUPPORT)->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($client->fresh()->access_profile_id);
    }

    public function test_assigning_client_profile_as_global_fails_validation(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $support = User::factory()->support()->create();

        Livewire::test(ManageForm::class, ['user' => $support])
            ->set('access_profile_id', $this->profile(AccessProfile::MANAGER)->id) // profil klienta
            ->call('save')
            ->assertHasErrors(['access_profile_id']);
    }

    public function test_self_cannot_change_own_profile(): void
    {
        $admin = User::factory()->admin()->create(['access_profile_id' => null]);
        $this->actingAs($admin);

        $other = AccessProfile::create([
            'key' => 'x', 'name' => 'X', 'applies_to' => AccessProfile::APPLIES_STAFF,
            'is_system' => false, 'is_active' => true, 'permissions' => [Permission::AuditView->value],
        ]);

        Livewire::test(ManageForm::class, ['user' => $admin])
            ->set('access_profile_id', $other->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($admin->fresh()->access_profile_id);   // własny profil nietknięty
    }

    public function test_add_membership_with_client_profile(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $client = User::factory()->create();
        $org = Organization::factory()->create();
        $managerProfile = $this->profile(AccessProfile::MANAGER);

        Livewire::test(ManageForm::class, ['user' => $client])
            ->set('newOrganizationId', $org->id)
            ->set('newOrgRole', OrgRole::User->value)
            ->set('newAccessProfileId', $managerProfile->id)
            ->call('addMembership')
            ->assertHasNoErrors();

        $membership = $client->memberships()->firstOrFail();
        $this->assertSame($managerProfile->id, $membership->access_profile_id);
    }

    public function test_save_membership_profile_changes_existing(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $client = User::factory()->create();
        $org = Organization::factory()->create();
        $membership = $client->memberships()->create([
            'organization_id' => $org->id, 'role' => OrgRole::User->value, 'is_active' => true,
        ]);
        $managerProfile = $this->profile(AccessProfile::MANAGER);

        Livewire::test(ManageForm::class, ['user' => $client])
            ->set("membershipProfiles.{$membership->id}", $managerProfile->id)
            ->call('saveMembershipProfile', $membership->id);

        $this->assertSame($managerProfile->id, $membership->fresh()->access_profile_id);

        // I z powrotem do domyślnego (puste = null).
        Livewire::test(ManageForm::class, ['user' => $client])
            ->set("membershipProfiles.{$membership->id}", '')
            ->call('saveMembershipProfile', $membership->id);

        $this->assertNull($membership->fresh()->access_profile_id);
    }
}
