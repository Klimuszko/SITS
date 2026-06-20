<?php

namespace Tests\Feature;

use App\Enums\AssetFieldType;
use App\Livewire\AssetCategories\Builder;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Krok 14a — zachowanie zagnieżdżonego buildera struktury:
 * sekcje, podsekcje, grupy powtarzalne, konfiguracja pod-zasobu, pola.
 */
class AssetStructureBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected AssetCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
        $this->category = AssetCategory::factory()->create();
    }

    public function test_admin_adds_top_level_section(): void
    {
        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('sectionKind', Builder::KIND_SECTION)
            ->set('sectionName', 'Sprzęt')
            ->set('sectionKey', 'sprzet')
            ->set('sectionOrder', 0)
            ->call('saveSection')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asset_sections', [
            'asset_category_id' => $this->category->id,
            'key' => 'sprzet',
            'parent_id' => null,
            'is_group' => false,
            'is_repeatable' => false,
            'is_active' => true,
        ]);
    }

    public function test_section_key_is_auto_generated_from_name(): void
    {
        // Klucz ukryty w UI — generowany z nazwy. Bez set('sectionKey').
        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('sectionKind', Builder::KIND_SECTION)
            ->set('sectionName', 'Sieć LAN')
            ->call('saveSection')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asset_sections', [
            'asset_category_id' => $this->category->id,
            'name' => 'Sieć LAN',
            'key' => 'siec-lan',
        ]);
    }

    public function test_section_auto_key_gets_unique_suffix_on_collision(): void
    {
        AssetSection::factory()->forCategory($this->category)->create(['key' => 'siec']);

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('sectionKind', Builder::KIND_SECTION)
            ->set('sectionName', 'Sieć')
            ->call('saveSection')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asset_sections', [
            'asset_category_id' => $this->category->id,
            'name' => 'Sieć',
            'key' => 'siec-2',
        ]);
    }

    public function test_admin_adds_nested_repeatable_group_with_ticket_config(): void
    {
        $section = AssetSection::factory()->forCategory($this->category)->create();

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('sectionKind', Builder::KIND_GROUP)
            ->set('sectionName', 'Maszyny wirtualne')
            ->set('sectionKey', 'vm')
            ->set('sectionParentId', $section->id)
            ->set('sectionMinEntries', 1)
            ->set('sectionMaxEntries', 5)
            ->set('sectionIsTicketLinkable', true)
            ->set('sectionTicketLabel', 'VM')
            ->set('sectionLinkParentOnSelect', true)
            ->call('saveSection')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asset_sections', [
            'asset_category_id' => $this->category->id,
            'key' => 'vm',
            'parent_id' => $section->id,
            'is_group' => true,
            'is_repeatable' => true,
            'min_entries' => 1,
            'max_entries' => 5,
            'is_ticket_linkable' => true,
            'ticket_label' => 'VM',
            'link_parent_on_select' => true,
        ]);
    }

    public function test_subsection_requires_parent(): void
    {
        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('sectionKind', Builder::KIND_SUBSECTION)
            ->set('sectionName', 'Podsekcja')
            ->set('sectionKey', 'pod')
            ->set('sectionParentId', null)
            ->call('saveSection')
            ->assertHasErrors(['sectionParentId']);
    }

    public function test_parent_from_other_category_is_rejected(): void
    {
        $otherCategory = AssetCategory::factory()->create();
        $foreignParent = AssetSection::factory()->forCategory($otherCategory)->create();

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('sectionKind', Builder::KIND_SUBSECTION)
            ->set('sectionName', 'Podsekcja')
            ->set('sectionKey', 'pod')
            ->set('sectionParentId', $foreignParent->id)
            ->call('saveSection')
            ->assertHasErrors(['sectionParentId']);
    }

    public function test_node_cannot_be_its_own_parent(): void
    {
        $section = AssetSection::factory()->forCategory($this->category)->create();

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->call('editSection', $section->id)
            ->set('sectionKind', Builder::KIND_SUBSECTION)
            ->set('sectionParentId', $section->id)
            ->call('saveSection')
            ->assertHasErrors(['sectionParentId']);
    }

    public function test_node_cannot_be_child_of_its_descendant(): void
    {
        $parent = AssetSection::factory()->forCategory($this->category)->create();
        $child = AssetSection::factory()->subsectionOf($parent)->create();

        // Próba uczynienia rodzica potomkiem własnego dziecka = cykl.
        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->call('editSection', $parent->id)
            ->set('sectionKind', Builder::KIND_SUBSECTION)
            ->set('sectionParentId', $child->id)
            ->call('saveSection')
            ->assertHasErrors(['sectionParentId']);
    }

    public function test_min_greater_than_max_is_rejected(): void
    {
        $section = AssetSection::factory()->forCategory($this->category)->create();

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('sectionKind', Builder::KIND_GROUP)
            ->set('sectionName', 'Grupa')
            ->set('sectionKey', 'grupa')
            ->set('sectionParentId', $section->id)
            ->set('sectionMinEntries', 5)
            ->set('sectionMaxEntries', 2)
            ->call('saveSection')
            ->assertHasErrors(['sectionMaxEntries']);
    }

    public function test_section_key_unique_within_category(): void
    {
        AssetSection::factory()->forCategory($this->category)->create(['key' => 'sprzet']);

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('sectionKind', Builder::KIND_SECTION)
            ->set('sectionName', 'Sprzęt 2')
            ->set('sectionKey', 'sprzet')
            ->call('saveSection')
            ->assertHasErrors(['sectionKey' => 'unique']);
    }

    public function test_same_section_key_allowed_in_another_category(): void
    {
        $other = AssetCategory::factory()->create();
        AssetSection::factory()->forCategory($other)->create(['key' => 'sprzet']);

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('sectionKind', Builder::KIND_SECTION)
            ->set('sectionName', 'Sprzęt')
            ->set('sectionKey', 'sprzet')
            ->call('saveSection')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asset_sections', [
            'asset_category_id' => $this->category->id,
            'key' => 'sprzet',
        ]);
    }

    public function test_admin_adds_ip_field_to_group(): void
    {
        $group = AssetSection::factory()->forCategory($this->category)->repeatable()->create();

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('fieldName', 'Adres IP')
            ->set('fieldKey', 'ip')
            ->set('fieldType', AssetFieldType::Ip->value)
            ->set('fieldSectionId', $group->id)
            ->set('fieldPlaceholder', '10.0.0.1')
            ->set('fieldHelp', 'Adres w sieci LAN')
            ->call('saveField')
            ->assertHasNoErrors();

        $field = AssetField::where('asset_category_id', $this->category->id)
            ->where('key', 'ip')
            ->firstOrFail();

        $this->assertSame(AssetFieldType::Ip, $field->type);
        $this->assertSame($group->id, $field->asset_section_id);
        $this->assertSame('10.0.0.1', $field->placeholder);
        $this->assertSame('Adres w sieci LAN', $field->help);
    }

    public function test_display_field_must_belong_to_the_group(): void
    {
        $group = AssetSection::factory()->forCategory($this->category)->repeatable()->create();
        // Pole należące do INNEJ sekcji (nie tej grupy).
        $otherSection = AssetSection::factory()->forCategory($this->category)->create();
        $foreignField = AssetField::factory()->forCategory($this->category)->create([
            'asset_section_id' => $otherSection->id,
        ]);

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->call('editSection', $group->id)
            ->set('sectionIsTicketLinkable', true)
            ->set('sectionDisplayFieldId', $foreignField->id)
            ->call('saveSection')
            ->assertHasErrors(['sectionDisplayFieldId']);
    }

    public function test_deactivate_node_does_not_hard_delete(): void
    {
        $section = AssetSection::factory()->forCategory($this->category)->create(['is_active' => true]);

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->call('deactivateSection', $section->id);

        $this->assertDatabaseHas('asset_sections', [
            'id' => $section->id,
            'is_active' => false,
        ]);
    }
}
