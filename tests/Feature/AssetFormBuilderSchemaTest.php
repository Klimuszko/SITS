<?php

namespace Tests\Feature;

use App\Enums\AssetFieldType;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetGroupEntry;
use App\Models\AssetGroupEntryValue;
use App\Models\AssetSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Krok 14a — warstwa definicji: nowe tabele + relacje muszą działać na sqlite.
 * Buduje Sekcję, niepowtarzalną Podsekcję i Grupę powtarzalną, tworzy wpis
 * grupy z dwiema wartościami i sprawdza relacje oraz displayLabel().
 */
class AssetFormBuilderSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_nested_structure_and_group_entry_relations_resolve(): void
    {
        $category = AssetCategory::factory()->create();

        // Sekcja najwyższego poziomu.
        $section = AssetSection::factory()->forCategory($category)->create([
            'name' => 'Sprzęt',
            'order' => 0,
        ]);

        // Niepowtarzalna podsekcja pod sekcją, z polem pojedynczym.
        $subsection = AssetSection::factory()->subsectionOf($section)->create([
            'name' => 'Identyfikacja',
        ]);

        $serialField = AssetField::factory()->forCategory($category)->create([
            'asset_section_id' => $subsection->id,
            'type' => AssetFieldType::Text,
        ]);

        // Grupa powtarzalna (pod-zasoby), linkowalna w zgłoszeniach.
        $group = AssetSection::factory()->forCategory($category)->repeatable()->create([
            'name' => 'Maszyny wirtualne',
            'is_ticket_linkable' => true,
        ]);

        $hostnameField = AssetField::factory()->forCategory($category)->create([
            'asset_section_id' => $group->id,
            'type' => AssetFieldType::Text,
        ]);

        $ipField = AssetField::factory()->forCategory($category)->create([
            'asset_section_id' => $group->id,
            'type' => AssetFieldType::Ip,
        ]);

        // Pole etykietujące pod-zasób = hostname.
        $group->update(['display_field_id' => $hostnameField->id]);

        // --- Relacje struktury ---
        $this->assertTrue($subsection->parent->is($section));
        $this->assertTrue($section->children->contains($subsection));
        $this->assertTrue($group->is_repeatable);
        $this->assertTrue($group->is_ticket_linkable);
        $this->assertTrue($group->displayField->is($hostnameField));
        $this->assertCount(2, $group->fields);

        // --- Wpis grupy = kandydat na pod-zasób ---
        $asset = Asset::factory()->forCategory($category)->create();

        $entry = AssetGroupEntry::factory()
            ->forAsset($asset)
            ->forSection($group)
            ->create();

        $hostValue = AssetGroupEntryValue::create([
            'asset_group_entry_id' => $entry->id,
            'asset_field_id' => $hostnameField->id,
            'value' => 'DC01',
        ]);

        AssetGroupEntryValue::create([
            'asset_group_entry_id' => $entry->id,
            'asset_field_id' => $ipField->id,
            'value' => '10.0.0.5',
        ]);

        $entry->refresh()->load(['values', 'section']);

        $this->assertTrue($entry->asset->is($asset));
        $this->assertTrue($entry->section->is($group));
        $this->assertCount(2, $entry->values);
        $this->assertTrue($entry->values->contains($hostValue));

        // displayLabel() = wartość pola display_field_id (hostname).
        $this->assertSame('DC01', $entry->displayLabel());
    }

    public function test_display_label_falls_back_to_entry_id_when_no_display_field(): void
    {
        $category = AssetCategory::factory()->create();
        $group = AssetSection::factory()->forCategory($category)->repeatable()->create();
        $asset = Asset::factory()->forCategory($category)->create();

        $entry = AssetGroupEntry::factory()->forAsset($asset)->forSection($group)->create();
        $entry->load(['values', 'section']);

        $this->assertSame('#'.$entry->id, $entry->displayLabel());
    }

    public function test_nested_group_entries_relate_to_parent_entry(): void
    {
        $category = AssetCategory::factory()->create();
        $group = AssetSection::factory()->forCategory($category)->repeatable()->create();
        $asset = Asset::factory()->forCategory($category)->create();

        $parentEntry = AssetGroupEntry::factory()->forAsset($asset)->forSection($group)->create();
        $childEntry = AssetGroupEntry::factory()->forAsset($asset)->forSection($group)->create([
            'parent_entry_id' => $parentEntry->id,
        ]);

        $this->assertTrue($childEntry->parent->is($parentEntry));
        $this->assertTrue($parentEntry->children->contains($childEntry));
    }
}
