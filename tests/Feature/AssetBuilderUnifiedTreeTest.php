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
 * Faza 3 — zunifikowane drzewo struktury i pól: kontekstowe formularze
 * („+ Pole", „+ Podsekcja", „+ Grupa") otwierane przy wybranym węźle,
 * naraz widoczny co najwyżej jeden formularz, chowany po zapisie / „Anuluj".
 */
class AssetBuilderUnifiedTreeTest extends TestCase
{
    use RefreshDatabase;

    protected AssetCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
        $this->category = AssetCategory::factory()->create();
    }

    public function test_add_field_opens_form_targeted_to_node_and_hides_after_save(): void
    {
        $section = AssetSection::factory()->forCategory($this->category)->create();

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->assertSet('showFieldForm', false)
            ->call('addField', $section->id)
            ->assertSet('showFieldForm', true)
            ->assertSet('showSectionForm', false)
            ->assertSet('fieldSectionId', $section->id)
            ->set('fieldName', 'Procesor')
            ->set('fieldType', AssetFieldType::Text->value)
            ->call('saveField')
            ->assertHasNoErrors()
            ->assertSet('showFieldForm', false);

        $this->assertDatabaseHas('asset_fields', [
            'asset_category_id' => $this->category->id,
            'name' => 'Procesor',
            'asset_section_id' => $section->id,
        ]);
    }

    public function test_add_subsection_presets_kind_and_parent(): void
    {
        $parent = AssetSection::factory()->forCategory($this->category)->create();

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->call('addSubsection', $parent->id)
            ->assertSet('showSectionForm', true)
            ->assertSet('showFieldForm', false)
            ->assertSet('sectionKind', Builder::KIND_SUBSECTION)
            ->assertSet('sectionParentId', $parent->id)
            ->set('sectionName', 'RAM')
            ->call('saveSection')
            ->assertHasNoErrors()
            ->assertSet('showSectionForm', false);

        $this->assertDatabaseHas('asset_sections', [
            'asset_category_id' => $this->category->id,
            'name' => 'RAM',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_add_group_presets_repeatable_kind_and_parent(): void
    {
        $parent = AssetSection::factory()->forCategory($this->category)->create();

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->call('addGroup', $parent->id)
            ->assertSet('sectionKind', Builder::KIND_GROUP)
            ->assertSet('sectionParentId', $parent->id)
            ->assertSet('showSectionForm', true)
            ->assertSet('showFieldForm', false);
    }

    public function test_only_one_context_form_visible_at_a_time(): void
    {
        $field = AssetField::factory()->forCategory($this->category)->create(['name' => 'IP']);

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->call('addTopSection')
            ->assertSet('showSectionForm', true)
            ->call('editField', $field->id)
            ->assertSet('showFieldForm', true)
            ->assertSet('showSectionForm', false);
    }

    public function test_cancel_hides_section_form(): void
    {
        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->call('addTopSection')
            ->assertSet('showSectionForm', true)
            ->call('resetSectionForm')
            ->assertSet('showSectionForm', false);
    }

    public function test_fields_render_under_their_node_in_tree(): void
    {
        $section = AssetSection::factory()->forCategory($this->category)->create(['name' => 'Sprzęt']);
        AssetField::factory()->forCategory($this->category)->create([
            'asset_section_id' => $section->id,
            'name' => 'Numer seryjny',
        ]);

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->assertSee('Sprzęt')
            ->assertSee('Numer seryjny');
    }
}
