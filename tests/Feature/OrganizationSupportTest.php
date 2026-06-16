<?php

namespace Tests\Feature;

use App\Enums\SupportScope;
use App\Livewire\Organizations\ManageForm;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationSupportTest extends TestCase
{
    use RefreshDatabase;

    /** Aktor z uprawnieniami do tworzenia/edycji organizacji (Gate::before → true). */
    private function actAsSuperAdmin(): User
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        return $admin;
    }

    /** @return array<string,mixed> Komplet poprawnych pól dla aktywnej organizacji. */
    private function validFormData(int $supportUserId): array
    {
        return [
            'name' => 'Klient Testowy',
            'type' => 'company',
            'status' => 'active',
            'default_support_user_id' => $supportUserId,
        ];
    }

    public function test_super_admin_can_be_set_as_default_support_and_creates_primary_assignment(): void
    {
        $this->actAsSuperAdmin();
        $support = User::factory()->superAdmin()->create();

        Livewire::test(ManageForm::class)
            ->set($this->validFormData($support->id))
            ->call('save')
            ->assertHasNoErrors('default_support_user_id');

        $org = Organization::where('name', 'Klient Testowy')->firstOrFail();

        $this->assertSame($support->id, $org->default_support_user_id);
        $this->assertDatabaseHas('support_assignments', [
            'organization_id' => $org->id,
            'support_user_id' => $support->id,
            'is_primary' => true,
            'scope' => SupportScope::All->value,
            'is_active' => true,
        ]);
    }

    public function test_admin_can_be_set_as_default_support_and_creates_primary_assignment(): void
    {
        $this->actAsSuperAdmin();
        $support = User::factory()->admin()->create();

        Livewire::test(ManageForm::class)
            ->set($this->validFormData($support->id))
            ->call('save')
            ->assertHasNoErrors('default_support_user_id');

        $org = Organization::where('name', 'Klient Testowy')->firstOrFail();

        $this->assertDatabaseHas('support_assignments', [
            'organization_id' => $org->id,
            'support_user_id' => $support->id,
            'is_primary' => true,
            'scope' => SupportScope::All->value,
            'is_active' => true,
        ]);
    }

    public function test_support_user_can_be_set_as_default_support(): void
    {
        $this->actAsSuperAdmin();
        $support = User::factory()->support()->create();

        Livewire::test(ManageForm::class)
            ->set($this->validFormData($support->id))
            ->call('save')
            ->assertHasNoErrors('default_support_user_id');

        $org = Organization::where('name', 'Klient Testowy')->firstOrFail();

        $this->assertSame($support->id, $org->default_support_user_id);
        $this->assertDatabaseHas('support_assignments', [
            'organization_id' => $org->id,
            'support_user_id' => $support->id,
            'is_primary' => true,
            'scope' => SupportScope::All->value,
            'is_active' => true,
        ]);
    }

    public function test_client_manager_is_rejected_as_default_support(): void
    {
        $this->actAsSuperAdmin();
        $manager = User::factory()->manager()->create();

        Livewire::test(ManageForm::class)
            ->set($this->validFormData($manager->id))
            ->call('save')
            ->assertHasErrors(['default_support_user_id' => 'exists']);

        $this->assertDatabaseMissing('organizations', ['name' => 'Klient Testowy']);
    }

    public function test_client_user_is_rejected_as_default_support(): void
    {
        $this->actAsSuperAdmin();
        $client = User::factory()->create(); // domyślna rola = user (klient)

        Livewire::test(ManageForm::class)
            ->set($this->validFormData($client->id))
            ->call('save')
            ->assertHasErrors(['default_support_user_id' => 'exists']);

        $this->assertDatabaseMissing('organizations', ['name' => 'Klient Testowy']);
    }

    public function test_inactive_staff_user_is_rejected_as_default_support(): void
    {
        $this->actAsSuperAdmin();
        $inactiveSupport = User::factory()->support()->inactive()->create();

        Livewire::test(ManageForm::class)
            ->set($this->validFormData($inactiveSupport->id))
            ->call('save')
            ->assertHasErrors(['default_support_user_id' => 'exists']);

        $this->assertDatabaseMissing('organizations', ['name' => 'Klient Testowy']);
    }

    public function test_active_organization_requires_default_support(): void
    {
        $this->actAsSuperAdmin();

        Livewire::test(ManageForm::class)
            ->set('name', 'Bez Supporta')
            ->set('type', 'company')
            ->set('status', 'active')
            ->set('default_support_user_id', null)
            ->call('save')
            ->assertHasErrors(['default_support_user_id' => 'required']);
    }
}
