<?php

namespace Tests\Feature;

use App\Enums\ManagerScope;
use App\Enums\OrgRole;
use App\Enums\Role;
use App\Livewire\Users\ManageForm;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

class UserManageFormTest extends TestCase
{
    use RefreshDatabase;

    /** Aktor = Admin (nie Super Admin), żeby guardy UserPolicy były faktycznie wykonywane. */
    private function actAsAdmin(): User
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        return $admin;
    }

    public function test_admin_creates_client_user_with_membership_password_hashed_and_audited(): void
    {
        $this->actAsAdmin();
        $org = Organization::factory()->create();

        Livewire::test(ManageForm::class)
            ->set('name', 'Jan Klient')
            ->set('email', 'jan.klient@example.com')
            ->set('role', Role::User->value)
            ->set('password', 'tajneHaslo1')
            ->set('password_confirmation', 'tajneHaslo1')
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $user = User::where('email', 'jan.klient@example.com')->firstOrFail();

        // Konto utworzone + e-mail zweryfikowany przy utworzeniu.
        $this->assertSame(Role::User, $user->role);
        $this->assertTrue($user->is_active);
        $this->assertNotNull($user->email_verified_at);

        // Hasło zahashowane (cast 'hashed', bez podwójnego hashowania).
        $this->assertNotSame('tajneHaslo1', $user->password);
        $this->assertTrue(Hash::check('tajneHaslo1', $user->password));

        // Audyt utworzenia użytkownika.
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.created',
            'subject_type' => $user->getMorphClass(),
            'subject_id' => $user->id,
        ]);

        // Dodanie członkostwa (osobna akcja na zapisanym użytkowniku).
        Livewire::test(ManageForm::class, ['user' => $user])
            ->set('newOrganizationId', $org->id)
            ->set('newOrgRole', OrgRole::Manager->value)
            ->set('newManagerScope', ManagerScope::OwnUnit->value)
            ->set('newMembershipActive', true)
            ->call('addMembership')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('organization_memberships', [
            'user_id' => $user->id,
            'organization_id' => $org->id,
            'role' => OrgRole::Manager->value,
            'manager_scope' => ManagerScope::OwnUnit->value,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'membership.granted',
            'subject_type' => $org->getMorphClass(),
            'subject_id' => $org->id,
        ]);
    }

    public function test_manager_membership_without_scope_is_rejected(): void
    {
        $this->actAsAdmin();
        $user = User::factory()->create();
        $org = Organization::factory()->create();

        Livewire::test(ManageForm::class, ['user' => $user])
            ->set('newOrganizationId', $org->id)
            ->set('newOrgRole', OrgRole::Manager->value)
            ->set('newManagerScope', null)
            ->call('addMembership')
            ->assertHasErrors(['newManagerScope' => 'required']);

        $this->assertDatabaseMissing('organization_memberships', [
            'user_id' => $user->id,
            'organization_id' => $org->id,
        ]);
    }

    public function test_duplicate_membership_is_rejected(): void
    {
        $this->actAsAdmin();
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $user->memberships()->create([
            'organization_id' => $org->id,
            'role' => OrgRole::User->value,
            'is_active' => true,
        ]);

        Livewire::test(ManageForm::class, ['user' => $user])
            ->set('newOrganizationId', $org->id)
            ->set('newOrgRole', OrgRole::User->value)
            ->call('addMembership')
            ->assertHasErrors('newOrganizationId');

        $this->assertSame(1, $user->memberships()->count());
    }

    public function test_admin_cannot_set_super_admin_role(): void
    {
        $this->actAsAdmin();

        Livewire::test(ManageForm::class)
            ->set('name', 'Próba Eskalacji')
            ->set('email', 'eskalacja@example.com')
            ->set('role', Role::SuperAdmin->value)
            ->set('password', 'tajneHaslo1')
            ->set('password_confirmation', 'tajneHaslo1')
            ->call('save')
            ->assertHasErrors(['role']);

        $this->assertDatabaseMissing('users', ['email' => 'eskalacja@example.com']);
    }

    public function test_editing_self_cannot_change_own_role(): void
    {
        $admin = $this->actAsAdmin();

        Livewire::test(ManageForm::class, ['user' => $admin])
            ->set('role', Role::User->value)
            ->set('name', $admin->name)
            ->set('email', $admin->email)
            ->call('save')
            ->assertHasNoErrors();

        $admin->refresh();
        // Rola własna pozostaje niezmieniona mimo próby.
        $this->assertSame(Role::Admin, $admin->role);
    }

    public function test_editing_self_cannot_deactivate_own_account(): void
    {
        $admin = $this->actAsAdmin();

        Livewire::test(ManageForm::class, ['user' => $admin])
            ->set('is_active', false)
            ->set('name', $admin->name)
            ->set('email', $admin->email)
            ->call('save')
            ->assertHasNoErrors();

        $admin->refresh();
        $this->assertTrue($admin->is_active);
    }

    public function test_editing_user_without_password_keeps_old_password(): void
    {
        $this->actAsAdmin();
        $target = User::factory()->create([
            'password' => Hash::make('stareHaslo1'),
        ]);
        $originalHash = $target->password;

        Livewire::test(ManageForm::class, ['user' => $target])
            ->set('name', 'Nowa Nazwa')
            ->set('email', $target->email)
            ->set('role', Role::User->value)
            ->set('password', null)
            ->set('password_confirmation', null)
            ->call('save')
            ->assertHasNoErrors();

        $target->refresh();
        $this->assertSame('Nowa Nazwa', $target->name);
        $this->assertSame($originalHash, $target->password);
        $this->assertTrue(Hash::check('stareHaslo1', $target->password));
    }

    public function test_email_uniqueness_ignores_self_but_blocks_others(): void
    {
        $this->actAsAdmin();
        $existing = User::factory()->create(['email' => 'zajety@example.com']);
        $target = User::factory()->create(['email' => 'target@example.com']);

        // Zmiana na e-mail innego użytkownika → błąd.
        Livewire::test(ManageForm::class, ['user' => $target])
            ->set('name', $target->name)
            ->set('email', 'zajety@example.com')
            ->set('role', Role::User->value)
            ->call('save')
            ->assertHasErrors(['email']);

        // Zapis z własnym (niezmienionym) e-mailem → bez błędu uniqueness.
        Livewire::test(ManageForm::class, ['user' => $target])
            ->set('name', 'Zmieniona Nazwa')
            ->set('email', 'target@example.com')
            ->set('role', Role::User->value)
            ->call('save')
            ->assertHasNoErrors();

        $target->refresh();
        $this->assertSame('Zmieniona Nazwa', $target->name);
    }

    public function test_remove_membership_deletes_row_and_audits(): void
    {
        $this->actAsAdmin();
        $user = User::factory()->create();
        $org = Organization::factory()->create();
        $membership = $user->memberships()->create([
            'organization_id' => $org->id,
            'role' => OrgRole::User->value,
            'is_active' => true,
        ]);

        Livewire::test(ManageForm::class, ['user' => $user])
            ->call('removeMembership', $membership->id);

        $this->assertDatabaseMissing('organization_memberships', ['id' => $membership->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'membership.revoked',
            'subject_type' => $org->getMorphClass(),
            'subject_id' => $org->id,
        ]);
    }
}
