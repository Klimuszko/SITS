<?php

namespace Tests\Feature;

use App\Enums\AssetFieldType;
use App\Livewire\Assets\ManageForm;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\Organization;
use App\Models\SupportAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetManageFormTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Organization,2:AssetCategory,3:AssetField} */
    private function scenario(): array
    {
        $organization = Organization::factory()->create();

        $support = User::factory()->support()->create();
        SupportAssignment::create([
            'support_user_id' => $support->id,
            'organization_id' => $organization->id,
            'is_primary' => true,
            'scope' => 'all',
            'is_active' => true,
        ]);

        $category = AssetCategory::factory()->create();
        $field = AssetField::factory()->forCategory($category)->required()->create([
            'key' => 'serial',
            'name' => 'Numer seryjny',
            'type' => AssetFieldType::Text,
        ]);

        return [$support, $organization, $category, $field];
    }

    public function test_support_creates_asset_with_required_dynamic_field(): void
    {
        [$support, $organization, $category, $field] = $this->scenario();
        $this->actingAs($support);

        Livewire::test(ManageForm::class)
            ->set('organization_id', $organization->id)
            ->set('asset_category_id', $category->id)
            ->set('name', 'NAS Synology')
            ->set('values.'.$field->id, 'SN-2026-001')
            ->call('save')
            ->assertHasNoErrors();

        $asset = $organization->assets()->where('name', 'NAS Synology')->firstOrFail();

        $this->assertSame($category->id, $asset->asset_category_id);
        $this->assertSame($support->id, $asset->created_by);

        $this->assertDatabaseHas('asset_field_values', [
            'asset_id' => $asset->id,
            'asset_field_id' => $field->id,
            'value' => 'SN-2026-001',
        ]);
    }

    public function test_missing_required_dynamic_field_fails_validation(): void
    {
        [$support, $organization, $category, $field] = $this->scenario();
        $this->actingAs($support);

        Livewire::test(ManageForm::class)
            ->set('organization_id', $organization->id)
            ->set('asset_category_id', $category->id)
            ->set('name', 'Bez numeru')
            ->set('values.'.$field->id, '')
            ->call('save')
            ->assertHasErrors(['values.'.$field->id => 'required']);

        $this->assertDatabaseMissing('assets', ['name' => 'Bez numeru']);
    }

    public function test_support_cannot_create_asset_in_unsupported_org(): void
    {
        [$support, , $category, $field] = $this->scenario();
        $otherOrg = Organization::factory()->create();
        $this->actingAs($support);

        Livewire::test(ManageForm::class)
            ->set('organization_id', $otherOrg->id)
            ->set('asset_category_id', $category->id)
            ->set('name', 'Obcy zasób')
            ->set('values.'.$field->id, 'X')
            ->call('save')
            ->assertHasErrors(['organization_id']);

        $this->assertDatabaseMissing('assets', ['name' => 'Obcy zasób']);
    }
}
