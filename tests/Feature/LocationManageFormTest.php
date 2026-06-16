<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Livewire\Locations\ManageForm;
use App\Models\Location;
use App\Models\Organization;
use App\Models\SupportAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LocationManageFormTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_support_creates_location_in_supported_org_with_audit(): void
    {
        $organization = Organization::factory()->create();
        $support = $this->supportOf($organization);
        $this->actingAs($support);

        Livewire::test(ManageForm::class)
            ->set('organization_id', $organization->id)
            ->set('name', 'Serwerownia A')
            ->set('type', 'server_room')
            ->set('status', 'active')
            ->call('save')
            ->assertHasNoErrors();

        $location = $organization->locations()->where('name', 'Serwerownia A')->firstOrFail();

        $this->assertSame('server_room', $location->type->value);
        $this->assertSame('active', $location->status);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::LocationCreated->value,
            'subject_type' => $location->getMorphClass(),
            'subject_id' => $location->id,
        ]);
    }

    public function test_support_cannot_create_location_in_unsupported_org(): void
    {
        $organization = Organization::factory()->create();
        $support = $this->supportOf($organization);
        $otherOrg = Organization::factory()->create();
        $this->actingAs($support);

        Livewire::test(ManageForm::class)
            ->set('organization_id', $otherOrg->id)
            ->set('name', 'Obca lokalizacja')
            ->call('save')
            ->assertHasErrors(['organization_id']);

        $this->assertDatabaseMissing('locations', ['name' => 'Obca lokalizacja']);
    }

    public function test_parent_in_different_org_fails_validation(): void
    {
        $organization = Organization::factory()->create();
        $support = $this->supportOf($organization);
        $otherOrg = Organization::factory()->create();
        $foreignParent = Location::factory()->forOrganization($otherOrg)->create();
        $this->actingAs($support);

        Livewire::test(ManageForm::class)
            ->set('organization_id', $organization->id)
            ->set('name', 'Pokój 101')
            ->set('parent_id', $foreignParent->id)
            ->call('save')
            ->assertHasErrors(['parent_id']);

        $this->assertDatabaseMissing('locations', ['name' => 'Pokój 101']);
    }

    public function test_setting_parent_to_self_on_edit_fails_validation(): void
    {
        $organization = Organization::factory()->create();
        $support = $this->supportOf($organization);
        $location = Location::factory()->forOrganization($organization)->create(['name' => 'Budynek X']);
        $this->actingAs($support);

        Livewire::test(ManageForm::class, ['location' => $location])
            ->set('parent_id', $location->id)
            ->call('save')
            ->assertHasErrors(['parent_id']);

        $this->assertNull($location->fresh()->parent_id);
    }

    public function test_setting_parent_to_descendant_on_edit_fails_validation(): void
    {
        $organization = Organization::factory()->create();
        $support = $this->supportOf($organization);

        $root = Location::factory()->forOrganization($organization)->create();
        $child = Location::factory()->childOf($root)->create();
        $this->actingAs($support);

        // Próba ustawienia potomka jako rodzica korzenia tworzyłaby cykl.
        Livewire::test(ManageForm::class, ['location' => $root])
            ->set('parent_id', $child->id)
            ->call('save')
            ->assertHasErrors(['parent_id']);

        $this->assertNull($root->fresh()->parent_id);
    }
}
