<?php

namespace Tests\Feature;

use App\Livewire\AssetCategories\Builder;
use App\Livewire\AssetCategories\Index;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Faza 2 buildera — duplikowanie pola, węzła (z poddrzewem) i całej kategorii.
 * Kopia dostaje nowe, unikalne klucze; dowiązania (display_field_id, parent_id)
 * są przemapowane na skopiowane wiersze, oryginał pozostaje nietknięty.
 */
class AssetBuilderDuplicateTest extends TestCase
{
    use RefreshDatabase;

    protected AssetCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
        $this->category = AssetCategory::factory()->create();
    }

    public function test_duplicate_field_copies_within_same_section_with_new_key_on_top(): void
    {
        $section = AssetSection::factory()->forCategory($this->category)->create();
        $field = AssetField::factory()->forCategory($this->category)->create([
            'asset_section_id' => $section->id,
            'name' => 'Adres IP',
            'key' => 'ip',
            'order' => 0,
        ]);

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->call('duplicateField', $field->id)
            ->assertHasNoErrors();

        $copy = AssetField::where('asset_category_id', $this->category->id)
            ->where('name', 'Adres IP (kopia)')->firstOrFail();

        $this->assertSame($section->id, $copy->asset_section_id);
        $this->assertNotSame('ip', $copy->key);
        // Nowa kopia ląduje na górze swojego węzła (mniejszy order niż oryginał).
        $this->assertLessThan($field->order, $copy->order);
    }

    public function test_duplicate_section_deep_copies_subtree_fields_and_remaps_display_field(): void
    {
        // Grupa powtarzalna z polem etykietującym (display_field_id) + podsekcja z polem.
        $group = AssetSection::factory()->forCategory($this->category)->repeatable()
            ->create(['name' => 'Maszyny wirtualne', 'key' => 'vm']);

        $label = AssetField::factory()->forCategory($this->category)->create([
            'asset_section_id' => $group->id,
            'name' => 'Host',
            'key' => 'host',
        ]);
        $group->update(['display_field_id' => $label->id]);

        $sub = AssetSection::factory()->subsectionOf($group)->create(['name' => 'Dyski', 'key' => 'dyski']);
        AssetField::factory()->forCategory($this->category)->create([
            'asset_section_id' => $sub->id,
            'name' => 'Pojemność',
            'key' => 'pojemnosc',
        ]);

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->call('duplicateSection', $group->id)
            ->assertHasNoErrors();

        // Korzeń kopii: sufiks „(kopia)", ten sam poziom (parent null), nowy klucz.
        $copyRoot = AssetSection::where('asset_category_id', $this->category->id)
            ->where('name', 'Maszyny wirtualne (kopia)')->firstOrFail();
        $this->assertNull($copyRoot->parent_id);
        $this->assertNotSame('vm', $copyRoot->key);

        // Skopiowane pole etykietujące pod kopią + remap display_field_id na NIE (nie na oryginał).
        $copyLabel = AssetField::where('asset_section_id', $copyRoot->id)
            ->where('name', 'Host')->firstOrFail();
        $this->assertSame($copyLabel->id, $copyRoot->display_field_id);
        $this->assertNotSame($label->id, $copyRoot->display_field_id);

        // Skopiowana podsekcja pod kopią + jej pole.
        $copySub = AssetSection::where('asset_category_id', $this->category->id)
            ->where('parent_id', $copyRoot->id)->where('name', 'Dyski')->firstOrFail();
        $this->assertDatabaseHas('asset_fields', [
            'asset_section_id' => $copySub->id,
            'name' => 'Pojemność',
        ]);

        // Oryginał nietknięty.
        $this->assertDatabaseHas('asset_sections', [
            'id' => $group->id,
            'display_field_id' => $label->id,
        ]);
    }

    public function test_duplicate_category_clones_structure_fields_and_internal_links(): void
    {
        $group = AssetSection::factory()->forCategory($this->category)->repeatable()
            ->create(['name' => 'Serwery', 'key' => 'serwery']);
        $sub = AssetSection::factory()->subsectionOf($group)->create(['name' => 'Sieć', 'key' => 'siec']);

        $label = AssetField::factory()->forCategory($this->category)->create([
            'asset_section_id' => $group->id, 'name' => 'Nazwa hosta', 'key' => 'host',
        ]);
        $group->update(['display_field_id' => $label->id]);

        AssetField::factory()->forCategory($this->category)->create([
            'asset_section_id' => $sub->id, 'name' => 'VLAN', 'key' => 'vlan',
        ]);

        Livewire::test(Index::class)
            ->call('duplicateCategory', $this->category->id)
            ->assertHasNoErrors();

        $copy = AssetCategory::where('name', $this->category->name.' (kopia)')->firstOrFail();
        $this->assertNotSame($this->category->key, $copy->key);

        // Liczba sekcji i pól zgadza się z oryginałem.
        $this->assertSame(2, $copy->sections()->count());
        $this->assertSame(2, $copy->fields()->count());

        // Hierarchia zachowana: podsekcja wskazuje na skopiowaną grupę.
        $copyGroup = $copy->sections()->where('name', 'Serwery')->firstOrFail();
        $copySub = $copy->sections()->where('name', 'Sieć')->firstOrFail();
        $this->assertSame($copyGroup->id, $copySub->parent_id);

        // display_field_id przemapowane na skopiowane pole tej samej kategorii.
        $copyLabel = $copy->fields()->where('name', 'Nazwa hosta')->firstOrFail();
        $this->assertSame($copyLabel->id, $copyGroup->display_field_id);
        $this->assertSame($copy->id, $copyLabel->asset_category_id);

        // Oryginał nietknięty.
        $this->assertSame(2, $this->category->sections()->count());
    }
}
