<?php

namespace Tests\Feature;

use App\Enums\AssetFieldType;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetGroupEntry;
use App\Models\AssetSection;
use App\Models\Organization;
use App\Models\User;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Step 14b — AssetService: pola pojedyncze + reconcile grup powtarzalnych.
 */
class AssetServiceFormTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AssetService
    {
        return app(AssetService::class);
    }

    /** @return array{0:User,1:Organization,2:AssetCategory} */
    private function actorOrgCategory(): array
    {
        return [
            User::factory()->support()->create(),
            Organization::factory()->create(),
            AssetCategory::factory()->create(),
        ];
    }

    /**
     * Grupa powtarzalna z dwoma polami: name (display) + ip.
     *
     * @return array{0:AssetSection,1:AssetField,2:AssetField}
     */
    private function group(AssetCategory $category, ?int $min = null, ?int $max = null): array
    {
        $section = AssetSection::factory()->forCategory($category)->repeatable()->create([
            'min_entries' => $min,
            'max_entries' => $max,
            'ticket_label' => 'VM',
        ]);

        $nameField = AssetField::factory()->forCategory($category)->required()->create([
            'asset_section_id' => $section->id,
            'key' => 'vm-name',
            'name' => 'Nazwa VM',
            'type' => AssetFieldType::Text,
        ]);

        $ipField = AssetField::factory()->forCategory($category)->create([
            'asset_section_id' => $section->id,
            'key' => 'vm-ip',
            'name' => 'Adres IP',
            'type' => AssetFieldType::Ip,
        ]);

        $section->update(['display_field_id' => $nameField->id]);

        return [$section, $nameField, $ipField];
    }

    public function test_create_persists_single_values_and_group_entries(): void
    {
        [$actor, $organization, $category] = $this->actorOrgCategory();

        $single = AssetField::factory()->forCategory($category)->create([
            'key' => 'serial', 'type' => AssetFieldType::Text,
        ]);

        [$section, $nameField, $ipField] = $this->group($category);

        $asset = $this->service()->create($actor, [
            'organization_id' => $organization->id,
            'asset_category_id' => $category->id,
            'name' => 'Hyperwizor',
            'status' => AssetStatus::Active->value,
        ], [
            $single->id => 'SN-1',
        ], [
            $section->id => [
                ['id' => null, 'values' => [$nameField->id => 'DC01', $ipField->id => '10.0.0.1']],
                ['id' => null, 'values' => [$nameField->id => 'DC02', $ipField->id => '10.0.0.2']],
            ],
        ]);

        // Pojedyncze pole → asset_field_values.
        $this->assertDatabaseHas('asset_field_values', [
            'asset_id' => $asset->id, 'asset_field_id' => $single->id, 'value' => 'SN-1',
        ]);

        // Dwa wpisy grupy.
        $this->assertSame(2, AssetGroupEntry::where('asset_id', $asset->id)
            ->where('asset_section_id', $section->id)->count());

        // Wartości wpisów → asset_group_entry_values (NIE asset_field_values).
        $this->assertDatabaseHas('asset_group_entry_values', [
            'asset_field_id' => $nameField->id, 'value' => 'DC01',
        ]);
        $this->assertDatabaseHas('asset_group_entry_values', [
            'asset_field_id' => $ipField->id, 'value' => '10.0.0.2',
        ]);

        // Grupowe pole NIE trafia do asset_field_values.
        $this->assertDatabaseMissing('asset_field_values', [
            'asset_id' => $asset->id, 'asset_field_id' => $nameField->id,
        ]);

        // Kolejność wpisów wg pozycji w tablicy.
        $orders = AssetGroupEntry::where('asset_id', $asset->id)->orderBy('order')->pluck('order')->all();
        $this->assertSame([0, 1], $orders);
    }

    public function test_update_reconciles_add_remove_and_edit_without_orphans(): void
    {
        [$actor, $organization, $category] = $this->actorOrgCategory();
        [$section, $nameField, $ipField] = $this->group($category);

        $asset = $this->service()->create($actor, [
            'organization_id' => $organization->id,
            'asset_category_id' => $category->id,
            'name' => 'Host',
        ], [], [
            $section->id => [
                ['id' => null, 'values' => [$nameField->id => 'A', $ipField->id => '10.0.0.1']],
                ['id' => null, 'values' => [$nameField->id => 'B', $ipField->id => '10.0.0.2']],
            ],
        ]);

        $entries = AssetGroupEntry::where('asset_id', $asset->id)->orderBy('order')->get();
        $keepId = $entries[0]->id;   // zostaje (edytowany)
        $dropId = $entries[1]->id;   // usuwany

        // Edytuj #0, usuń #1, dodaj nowy.
        $this->service()->update($asset, ['name' => 'Host'], [], $actor, [
            $section->id => [
                ['id' => $keepId, 'values' => [$nameField->id => 'A-edited', $ipField->id => '10.0.0.1']],
                ['id' => null, 'values' => [$nameField->id => 'C', $ipField->id => '10.0.0.3']],
            ],
        ]);

        // Pozostają 2 wpisy: edytowany + nowy.
        $this->assertSame(2, AssetGroupEntry::where('asset_id', $asset->id)->count());

        // Edycja zapisana.
        $this->assertDatabaseHas('asset_group_entry_values', [
            'asset_group_entry_id' => $keepId, 'asset_field_id' => $nameField->id, 'value' => 'A-edited',
        ]);

        // Usunięty wpis i jego wartości zniknęły (brak sierot).
        $this->assertDatabaseMissing('asset_group_entries', ['id' => $dropId]);
        $this->assertDatabaseMissing('asset_group_entry_values', ['asset_group_entry_id' => $dropId]);

        // Nowy wpis powstał.
        $this->assertDatabaseHas('asset_group_entry_values', [
            'asset_field_id' => $nameField->id, 'value' => 'C',
        ]);
    }

    public function test_update_removes_all_entries_when_group_data_empty(): void
    {
        [$actor, $organization, $category] = $this->actorOrgCategory();
        [$section, $nameField] = $this->group($category);

        $asset = $this->service()->create($actor, [
            'organization_id' => $organization->id,
            'asset_category_id' => $category->id,
            'name' => 'Host',
        ], [], [
            $section->id => [
                ['id' => null, 'values' => [$nameField->id => 'A']],
            ],
        ]);

        $this->service()->update($asset, ['name' => 'Host'], [], $actor, [
            $section->id => [],
        ]);

        $this->assertSame(0, AssetGroupEntry::where('asset_id', $asset->id)->count());
        $this->assertSame(0, \App\Models\AssetGroupEntryValue::query()->count());
    }

    public function test_update_ignores_entry_id_owned_by_another_asset(): void
    {
        [$actor, $organization, $category] = $this->actorOrgCategory();
        [$section, $nameField] = $this->group($category);

        $assetA = $this->service()->create($actor, [
            'organization_id' => $organization->id,
            'asset_category_id' => $category->id,
            'name' => 'A',
        ], [], [
            $section->id => [['id' => null, 'values' => [$nameField->id => 'owned-by-A']]],
        ]);

        $assetB = $this->service()->create($actor, [
            'organization_id' => $organization->id,
            'asset_category_id' => $category->id,
            'name' => 'B',
        ], [], []);

        $foreignEntryId = AssetGroupEntry::where('asset_id', $assetA->id)->first()->id;

        // Próba „przejęcia” cudzego wpisu przez podanie jego id w update zasobu B.
        $this->service()->update($assetB, ['name' => 'B'], [], $actor, [
            $section->id => [
                ['id' => $foreignEntryId, 'values' => [$nameField->id => 'hijack']],
            ],
        ]);

        // Cudzy wpis nietknięty.
        $this->assertDatabaseHas('asset_group_entry_values', [
            'asset_group_entry_id' => $foreignEntryId, 'asset_field_id' => $nameField->id, 'value' => 'owned-by-A',
        ]);
        $this->assertDatabaseMissing('asset_group_entry_values', [
            'asset_group_entry_id' => $foreignEntryId, 'value' => 'hijack',
        ]);

        // Wiersz nie został „przeniesiony” do zasobu B.
        $this->assertSame($assetA->id, AssetGroupEntry::find($foreignEntryId)->asset_id);
        $this->assertSame(0, AssetGroupEntry::where('asset_id', $assetB->id)->count());
    }

    public function test_flat_category_path_still_works(): void
    {
        // Kategoria bez grup powtarzalnych → groupData puste, zachowanie jak w Step 3.
        [$actor, $organization, $category] = $this->actorOrgCategory();

        $field = AssetField::factory()->forCategory($category)->create([
            'key' => 'serial', 'type' => AssetFieldType::Text,
        ]);

        $asset = $this->service()->create($actor, [
            'organization_id' => $organization->id,
            'asset_category_id' => $category->id,
            'name' => 'Płaski',
        ], [$field->id => 'X']);

        $this->assertDatabaseHas('asset_field_values', [
            'asset_id' => $asset->id, 'asset_field_id' => $field->id, 'value' => 'X',
        ]);
        $this->assertSame(0, AssetGroupEntry::where('asset_id', $asset->id)->count());
    }
}
