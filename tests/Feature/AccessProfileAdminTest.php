<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Livewire\Settings\AccessProfiles;
use App\Models\AccessProfile;
use App\Models\User;
use Database\Seeders\AccessProfileSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Krok B1 — panel „Profile dostępu". CRUD profili + macierz uprawnień + zabezpieczenia.
 */
class AccessProfileAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AccessProfileSeeder::class);
    }

    private function systemProfile(string $key): AccessProfile
    {
        return AccessProfile::where('key', $key)->firstOrFail();
    }

    public function test_non_admin_cannot_access(): void
    {
        // Bramka access-admin w mount() → 403 na trasie (test HTTP, nie Livewire::test).
        $this->actingAs(User::factory()->support()->create());
        $this->get(route('settings.access-profiles'))->assertForbidden();

        $this->actingAs(User::factory()->create()); // klient
        $this->get(route('settings.access-profiles'))->assertForbidden();
    }

    public function test_admin_can_access(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $this->get(route('settings.access-profiles'))->assertOk();
    }

    public function test_admin_creates_custom_profile_with_auto_key(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(AccessProfiles::class)
            ->set('name', 'Audytor')
            ->set('applies_to', AccessProfile::APPLIES_STAFF)
            ->set('permissions', [Permission::AuditView->value, Permission::TicketsView->value])
            ->call('save')
            ->assertHasNoErrors();

        $profile = AccessProfile::where('name', 'Audytor')->firstOrFail();
        $this->assertFalse($profile->is_system);
        $this->assertSame('audytor', $profile->key);
        $this->assertEqualsCanonicalizing(
            [Permission::AuditView->value, Permission::TicketsView->value],
            $profile->permissions,
        );
    }

    public function test_system_profile_name_locked_but_permissions_editable(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $support = $this->systemProfile(AccessProfile::SUPPORT);

        Livewire::test(AccessProfiles::class)
            ->call('edit', $support->id)
            ->set('name', 'ZMIANA')                                   // próba zmiany nazwy systemowego
            ->set('permissions', [Permission::AuditView->value])      // nowy zestaw uprawnień
            ->call('save')
            ->assertHasNoErrors();

        $support->refresh();
        $this->assertSame('Support', $support->name);                 // nazwa niezmieniona
        $this->assertSame([Permission::AuditView->value], $support->permissions);
    }

    public function test_super_admin_profile_is_locked_from_editing(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $superAdmin = $this->systemProfile(AccessProfile::SUPER_ADMIN);

        Livewire::test(AccessProfiles::class)
            ->call('edit', $superAdmin->id)
            ->assertSet('editingId', null);   // nie wczytany do edycji
    }

    public function test_system_profile_cannot_be_deleted_custom_can(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $support = $this->systemProfile(AccessProfile::SUPPORT);

        $custom = AccessProfile::create([
            'key' => 'tmp', 'name' => 'Tymczasowy', 'applies_to' => AccessProfile::APPLIES_STAFF,
            'is_system' => false, 'is_active' => true, 'permissions' => [Permission::AuditView->value],
        ]);

        Livewire::test(AccessProfiles::class)->call('delete', $support->id);
        $this->assertDatabaseHas('access_profiles', ['id' => $support->id]);

        Livewire::test(AccessProfiles::class)->call('delete', $custom->id);
        $this->assertDatabaseMissing('access_profiles', ['id' => $custom->id]);
    }

    public function test_deleting_profile_detaches_assignment_and_user_falls_back(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $custom = AccessProfile::create([
            'key' => 'po', 'name' => 'PO', 'applies_to' => AccessProfile::APPLIES_STAFF,
            'is_system' => false, 'is_active' => true, 'permissions' => [Permission::AuditView->value],
        ]);
        $support = User::factory()->support()->create(['access_profile_id' => $custom->id]);

        Livewire::test(AccessProfiles::class)->call('delete', $custom->id);

        $this->assertNull($support->fresh()->access_profile_id);
        // Fallback do roli support: ma tickets.manage, nie ma audit.view.
        $this->assertTrue($support->fresh()->hasPermission(Permission::TicketsManage));
        $this->assertFalse($support->fresh()->hasPermission(Permission::AuditView));
    }

    public function test_actor_cannot_grant_permission_it_lacks(): void
    {
        // Admin z własnym profilem: ma settings.manage (dostęp do strony) ale NIE users.manage.
        $limited = AccessProfile::create([
            'key' => 'admin-settings', 'name' => 'Admin ustawień', 'applies_to' => AccessProfile::APPLIES_STAFF,
            'is_system' => false, 'is_active' => true,
            'permissions' => [Permission::SettingsManage->value, Permission::AuditView->value],
        ]);
        $this->actingAs(User::factory()->admin()->create(['access_profile_id' => $limited->id]));

        Livewire::test(AccessProfiles::class)
            ->set('name', 'Próba eskalacji')
            ->set('applies_to', AccessProfile::APPLIES_STAFF)
            ->set('permissions', [Permission::AuditView->value, Permission::UsersManage->value])
            ->call('save')
            ->assertHasNoErrors();

        $created = AccessProfile::where('name', 'Próba eskalacji')->firstOrFail();
        $this->assertContains(Permission::AuditView->value, $created->permissions);      // aktor posiada → OK
        $this->assertNotContains(Permission::UsersManage->value, $created->permissions); // odfiltrowane
    }
}
