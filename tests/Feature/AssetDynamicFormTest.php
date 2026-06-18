<?php

namespace Tests\Feature;

use App\Enums\AssetFieldType;
use App\Livewire\Assets\ManageForm;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetGroupEntry;
use App\Models\AssetSection;
use App\Models\Organization;
use App\Models\SupportAssignment;
use App\Models\User;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Step 14b — dynamiczny formularz zasobu (ManageForm): grupy powtarzalne,
 * add/removeRow, walidacja typów, zapis i wczytanie przy edycji.
 */
class AssetDynamicFormTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:User,1:Organization,2:AssetCategory} */
    private function staffOrgCategory(): array
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

        return [$support, $organization, $category];
    }

    /**
     * @return array{0:AssetSection,1:AssetField,2:AssetField}
     */
    private function group(AssetCategory $category, ?int $min = null, ?int $max = null): array
    {
        $section = AssetSection::factory()->forCategory($category)->repeatable()->create([
            'min_entries' => $min,
            'max_entries' => $max,
            'name' => 'Maszyny wirtualne',
            'ticket_label' => 'VM',
        ]);

        $nameField = AssetField::factory()->forCategory($category)->required()->create([
            'asset_section_id' => $section->id,
            'key' => 'vm-name', 'name' => 'Nazwa VM', 'type' => AssetFieldType::Text,
        ]);

        $ipField = AssetField::factory()->forCategory($category)->create([
            'asset_section_id' => $section->id,
            'key' => 'vm-ip', 'name' => 'IP', 'type' => AssetFieldType::Ip,
        ]);

        $section->update(['display_field_id' => $nameField->id]);

        return [$section, $nameField, $ipField];
    }

    public function test_renders_add_button_for_repeatable_group(): void
    {
        [$support, $organization, $category] = $this->staffOrgCategory();
        $this->group($category);
        $this->actingAs($support);

        Livewire::test(ManageForm::class)
            ->set('organization_id', $organization->id)
            ->set('asset_category_id', $category->id)
            ->assertSee('+ Dodaj VM');
    }

    public function test_add_row_and_remove_row(): void
    {
        [$support, $organization, $category] = $this->staffOrgCategory();
        [$section] = $this->group($category);
        $this->actingAs($support);

        $component = Livewire::test(ManageForm::class)
            ->set('organization_id', $organization->id)
            ->set('asset_category_id', $category->id);

        // Brak wierszy na start (brak min_entries → 0 pustych bloków).
        $this->assertCount(0, $component->get('groups')[$section->id] ?? []);

        $component->call('addRow', $section->id);
        $this->assertCount(1, $component->get('groups')[$section->id]);

        $component->call('addRow', $section->id);
        $this->assertCount(2, $component->get('groups')[$section->id]);

        $component->call('removeRow', $section->id, 0);
        $this->assertCount(1, $component->get('groups')[$section->id]);
    }

    public function test_add_row_respects_max_entries(): void
    {
        [$support, $organization, $category] = $this->staffOrgCategory();
        [$section] = $this->group($category, min: null, max: 1);
        $this->actingAs($support);

        $component = Livewire::test(ManageForm::class)
            ->set('organization_id', $organization->id)
            ->set('asset_category_id', $category->id);

        $component->call('addRow', $section->id);
        $component->call('addRow', $section->id); // ponad limit — ignorowane
        $this->assertCount(1, $component->get('groups')[$section->id]);
    }

    public function test_remove_row_respects_min_entries(): void
    {
        [$support, $organization, $category] = $this->staffOrgCategory();
        [$section] = $this->group($category, min: 1);
        $this->actingAs($support);

        $component = Livewire::test(ManageForm::class)
            ->set('organization_id', $organization->id)
            ->set('asset_category_id', $category->id);

        // min_entries=1 → start z 1 wierszem.
        $this->assertCount(1, $component->get('groups')[$section->id]);

        $component->call('removeRow', $section->id, 0); // poniżej min — ignorowane
        $this->assertCount(1, $component->get('groups')[$section->id]);
    }

    public function test_required_single_field_blank_fails_validation(): void
    {
        [$support, $organization, $category] = $this->staffOrgCategory();
        $field = AssetField::factory()->forCategory($category)->required()->create([
            'key' => 'serial', 'name' => 'Numer seryjny', 'type' => AssetFieldType::Text,
        ]);
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

    public function test_invalid_ip_in_group_row_fails_validation(): void
    {
        [$support, $organization, $category] = $this->staffOrgCategory();
        [$section, $nameField, $ipField] = $this->group($category);
        $this->actingAs($support);

        Livewire::test(ManageForm::class)
            ->set('organization_id', $organization->id)
            ->set('asset_category_id', $category->id)
            ->set('name', 'Host')
            ->call('addRow', $section->id)
            ->set('groups.'.$section->id.'.0.values.'.$nameField->id, 'DC01')
            ->set('groups.'.$section->id.'.0.values.'.$ipField->id, 'nie-ip')
            ->call('save')
            ->assertHasErrors(['groups.'.$section->id.'.0.values.'.$ipField->id => 'ip']);
    }

    public function test_invalid_email_single_field_fails_validation(): void
    {
        [$support, $organization, $category] = $this->staffOrgCategory();
        $field = AssetField::factory()->forCategory($category)->create([
            'key' => 'contact', 'name' => 'E-mail', 'type' => AssetFieldType::Email,
        ]);
        $this->actingAs($support);

        Livewire::test(ManageForm::class)
            ->set('organization_id', $organization->id)
            ->set('asset_category_id', $category->id)
            ->set('name', 'Host')
            ->set('values.'.$field->id, 'nie-email')
            ->call('save')
            ->assertHasErrors(['values.'.$field->id => 'email']);
    }

    public function test_save_persists_single_and_group_entries(): void
    {
        [$support, $organization, $category] = $this->staffOrgCategory();
        $single = AssetField::factory()->forCategory($category)->create([
            'key' => 'serial', 'name' => 'SN', 'type' => AssetFieldType::Text,
        ]);
        [$section, $nameField, $ipField] = $this->group($category);
        $this->actingAs($support);

        Livewire::test(ManageForm::class)
            ->set('organization_id', $organization->id)
            ->set('asset_category_id', $category->id)
            ->set('name', 'Hyperwizor')
            ->set('values.'.$single->id, 'SN-9')
            ->call('addRow', $section->id)
            ->set('groups.'.$section->id.'.0.values.'.$nameField->id, 'DC01')
            ->set('groups.'.$section->id.'.0.values.'.$ipField->id, '10.0.0.1')
            ->call('save')
            ->assertHasNoErrors();

        $asset = $organization->assets()->where('name', 'Hyperwizor')->firstOrFail();

        $this->assertDatabaseHas('asset_field_values', [
            'asset_id' => $asset->id, 'asset_field_id' => $single->id, 'value' => 'SN-9',
        ]);
        $this->assertSame(1, AssetGroupEntry::where('asset_id', $asset->id)->count());
        $this->assertDatabaseHas('asset_group_entry_values', [
            'asset_field_id' => $nameField->id, 'value' => 'DC01',
        ]);
    }

    public function test_edit_loads_existing_single_and_group_values(): void
    {
        [$support, $organization, $category] = $this->staffOrgCategory();
        $single = AssetField::factory()->forCategory($category)->create([
            'key' => 'serial', 'name' => 'SN', 'type' => AssetFieldType::Text,
        ]);
        [$section, $nameField, $ipField] = $this->group($category);
        $this->actingAs($support);

        // Utwórz zasób z danymi przez serwis.
        $asset = app(AssetService::class)->create($support, [
            'organization_id' => $organization->id,
            'asset_category_id' => $category->id,
            'name' => 'Istniejący',
        ], [$single->id => 'SN-INIT'], [
            $section->id => [
                ['id' => null, 'values' => [$nameField->id => 'DC-OLD', $ipField->id => '10.0.0.7']],
            ],
        ]);

        $entryId = AssetGroupEntry::where('asset_id', $asset->id)->first()->id;

        $component = Livewire::test(ManageForm::class, ['asset' => $asset]);

        // Pole pojedyncze wczytane.
        $this->assertSame('SN-INIT', $component->get('values')[$single->id]);

        // Wpis grupy wczytany — z id i wartościami.
        $rows = $component->get('groups')[$section->id];
        $this->assertCount(1, $rows);
        $this->assertSame($entryId, $rows[0]['id']);
        $this->assertSame('DC-OLD', $rows[0]['values'][$nameField->id]);
        $this->assertSame('10.0.0.7', $rows[0]['values'][$ipField->id]);
    }

    public function test_non_staff_user_is_forbidden(): void
    {
        // Zwykły użytkownik nie ma prawa create → mount authorize('create') → 403.
        $this->actingAs(User::factory()->create());

        Livewire::test(ManageForm::class)->assertForbidden();
    }
}
