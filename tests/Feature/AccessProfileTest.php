<?php

namespace Tests\Feature;

use App\Enums\OrgRole;
use App\Enums\Permission;
use App\Enums\Role;
use App\Models\AccessProfile;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Database\Seeders\AccessProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Krok A1 — fundament profili dostępu (RBAC). Sam mechanizm: katalog, profile
 * systemowe, AccessProfile::grants() i User::hasPermission() (CO × kontekst org).
 * Bramki/Policy jeszcze NIE używają tej warstwy (to kroki A2/A3).
 */
class AccessProfileTest extends TestCase
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

    public function test_seeder_creates_system_profiles(): void
    {
        $this->assertSame(5, AccessProfile::where('is_system', true)->count());
        $this->assertSame(AccessProfile::APPLIES_STAFF, $this->profile(AccessProfile::SUPPORT)->applies_to);
        $this->assertSame(AccessProfile::APPLIES_CLIENT, $this->profile(AccessProfile::MANAGER)->applies_to);
    }

    public function test_profile_grants_only_listed_permissions(): void
    {
        $support = $this->profile(AccessProfile::SUPPORT);

        $this->assertTrue($support->grants(Permission::TicketsManage));
        $this->assertTrue($support->grants('tickets.manage'));     // akceptuje też string
        $this->assertFalse($support->grants(Permission::UsersManage));
        $this->assertFalse($support->grants(Permission::AuditView));

        // Admin = pełny katalog.
        $this->assertTrue($this->profile(AccessProfile::ADMIN)->grants(Permission::AuditView));
    }

    public function test_super_admin_bypasses_all(): void
    {
        $user = User::factory()->create(['role' => Role::SuperAdmin->value]);

        // Bez przypisanego profilu — i tak wszystko (Gate::before / isSuperAdmin).
        $this->assertTrue($user->hasPermission(Permission::SettingsManage));
        $this->assertTrue($user->hasPermission('cokolwiek.nieznane'));
    }

    public function test_staff_uses_global_profile(): void
    {
        $support = User::factory()->support()->create([
            'access_profile_id' => $this->profile(AccessProfile::SUPPORT)->id,
        ]);

        $this->assertTrue($support->hasPermission(Permission::TicketsManage));
        $this->assertFalse($support->hasPermission(Permission::AuditView));
        $this->assertFalse($support->hasPermission(Permission::UsersManage));
    }

    public function test_staff_without_profile_has_no_permissions(): void
    {
        $support = User::factory()->support()->create();

        $this->assertFalse($support->hasPermission(Permission::TicketsManage));
    }

    public function test_client_uses_per_org_membership_profile(): void
    {
        $org = Organization::factory()->create();
        $client = User::factory()->create();   // rola user (klient)

        OrganizationMembership::create([
            'user_id' => $client->id,
            'organization_id' => $org->id,
            'role' => OrgRole::Manager->value,
            'access_profile_id' => $this->profile(AccessProfile::MANAGER)->id,
            'is_active' => true,
        ]);
        $client = $client->fresh();

        // Z kontekstem organizacji — uprawnienia managera (Organization i samo id).
        $this->assertTrue($client->hasPermission(Permission::TicketsView, $org));
        $this->assertTrue($client->hasPermission(Permission::TicketsView, $org->id));
        $this->assertFalse($client->hasPermission(Permission::TicketsManage, $org)); // manage = personel

        // Bez kontekstu — klient nie ma globalnej zdolności.
        $this->assertFalse($client->hasPermission(Permission::TicketsView));

        // Inna organizacja (brak członkostwa) — false.
        $other = Organization::factory()->create();
        $this->assertFalse($client->hasPermission(Permission::TicketsView, $other));
    }

    public function test_context_resolves_from_model_with_organization_id(): void
    {
        $org = Organization::factory()->create();
        $client = User::factory()->create();

        OrganizationMembership::create([
            'user_id' => $client->id,
            'organization_id' => $org->id,
            'role' => OrgRole::User->value,
            'access_profile_id' => $this->profile(AccessProfile::USER)->id,
            'is_active' => true,
        ]);
        $client = $client->fresh();

        $stub = (object) ['organization_id' => $org->id];

        $this->assertTrue($client->hasPermission(Permission::AssetsView, $stub));
        $this->assertFalse($client->hasPermission(Permission::TicketsView, $stub)); // user nie ma tickets.view
    }

    public function test_backfill_assigns_profiles_to_existing_records(): void
    {
        // Dane sprzed wdrożenia profili (access_profile_id NULL), potem ponowny seed.
        $admin = User::factory()->admin()->create(['access_profile_id' => null]);
        $org = Organization::factory()->create();
        $client = User::factory()->create(['access_profile_id' => null]);
        $membership = OrganizationMembership::create([
            'user_id' => $client->id,
            'organization_id' => $org->id,
            'role' => OrgRole::Manager->value,
            'is_active' => true,
        ]);

        $this->seed(AccessProfileSeeder::class); // idempotentny → backfill nowych NULL-i

        $this->assertSame(AccessProfile::ADMIN, $admin->fresh()->accessProfile?->key);
        $this->assertNull($client->fresh()->access_profile_id);        // klient: globalnie null
        $this->assertSame(AccessProfile::MANAGER, $membership->fresh()->accessProfile?->key);
    }
}
