<?php

namespace Tests\Feature;

use App\Enums\AssetFieldType;
use App\Livewire\Assets\Show;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetSection;
use App\Models\Organization;
use App\Models\User;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Widok zasobu pokazuje wyłącznie sekcje/kategorie z wypełnionymi danymi.
 * Puste (brak wartości pól, brak wpisów grupy) są ukrywane dla każdego viewera.
 */
class AssetShowEmptySectionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_section_without_values_is_hidden_but_filled_one_is_shown(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        $organization = Organization::factory()->create();
        $category = AssetCategory::factory()->create();

        // Sekcja z wypełnionym polem — ma być widoczna.
        $proc = AssetSection::factory()->forCategory($category)->create(['name' => 'Procesor']);
        $procField = AssetField::factory()->forCategory($category)->create([
            'asset_section_id' => $proc->id, 'name' => 'Taktowanie', 'type' => AssetFieldType::Text,
        ]);

        // Sekcja istnieje w strukturze, ale w tym zasobie pozostaje pusta — ma zniknąć.
        $ram = AssetSection::factory()->forCategory($category)->create(['name' => 'PustaSekcjaRAM']);
        AssetField::factory()->forCategory($category)->create([
            'asset_section_id' => $ram->id, 'name' => 'Pojemnosc', 'type' => AssetFieldType::Text,
        ]);

        $asset = app(AssetService::class)->create($admin, [
            'organization_id' => $organization->id,
            'asset_category_id' => $category->id,
            'name' => 'Serwer 1',
        ], [
            $procField->id => 'WARTOSC-PROC',
        ]);

        Livewire::test(Show::class, ['asset' => $asset])
            ->assertSee('Procesor')
            ->assertSee('WARTOSC-PROC')
            ->assertDontSee('PustaSekcjaRAM');
    }

    public function test_empty_repeatable_group_is_hidden(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        $organization = Organization::factory()->create();
        $category = AssetCategory::factory()->create();

        $group = AssetSection::factory()->forCategory($category)->repeatable()->create([
            'name' => 'PustaGrupaDyski',
        ]);
        AssetField::factory()->forCategory($category)->create([
            'asset_section_id' => $group->id, 'name' => 'Model', 'type' => AssetFieldType::Text,
        ]);

        // Zasób bez żadnych wpisów grupy.
        $asset = app(AssetService::class)->create($admin, [
            'organization_id' => $organization->id,
            'asset_category_id' => $category->id,
            'name' => 'Serwer 2',
        ]);

        Livewire::test(Show::class, ['asset' => $asset])
            ->assertDontSee('PustaGrupaDyski');
    }

    public function test_parent_section_stays_when_only_a_subsection_has_data(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        $organization = Organization::factory()->create();
        $category = AssetCategory::factory()->create();

        // Sekcja-rodzic bez własnych wypełnionych pól, ale z wypełnioną podsekcją.
        $parent = AssetSection::factory()->forCategory($category)->create(['name' => 'Sprzet']);
        $sub = AssetSection::factory()->subsectionOf($parent)->create(['name' => 'Podsekcja Plyta']);
        $subField = AssetField::factory()->forCategory($category)->create([
            'asset_section_id' => $sub->id, 'name' => 'Chipset', 'type' => AssetFieldType::Text,
        ]);

        $asset = app(AssetService::class)->create($admin, [
            'organization_id' => $organization->id,
            'asset_category_id' => $category->id,
            'name' => 'Serwer 3',
        ], [
            $subField->id => 'WARTOSC-CHIPSET',
        ]);

        Livewire::test(Show::class, ['asset' => $asset])
            ->assertSee('Sprzet')
            ->assertSee('Podsekcja Plyta')
            ->assertSee('WARTOSC-CHIPSET');
    }
}
