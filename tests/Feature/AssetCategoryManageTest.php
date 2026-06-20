<?php

namespace Tests\Feature;

use App\Livewire\AssetCategories\Index;
use App\Models\AssetCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetCategoryManageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_creates_category_with_auto_key(): void
    {
        // Klucz jest UKRYTY w UI i generuje się automatycznie z nazwy ('Serwery' → 'serwery').
        Livewire::test(Index::class)
            ->set('name', 'Serwery')
            ->set('icon', 'server')
            ->set('description', 'Zasoby serwerowe.')
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asset_categories', [
            'name' => 'Serwery',
            'key' => 'serwery',
            'icon' => 'server',
            'is_active' => true,
        ]);
    }

    public function test_key_auto_generates_unique_suffix(): void
    {
        // Istnieje już kategoria z kluczem 'serwery' — nowa o tej samej nazwie dostaje 'serwery-2'.
        AssetCategory::factory()->create(['key' => 'serwery', 'name' => 'Serwery']);

        Livewire::test(Index::class)
            ->set('name', 'Serwery')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asset_categories', ['name' => 'Serwery', 'key' => 'serwery-2']);
    }

    public function test_name_is_required(): void
    {
        // Klucz nie jest już polem użytkownika (auto-generowany), więc wymagana jest tylko nazwa.
        Livewire::test(Index::class)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_editing_keeps_own_key(): void
    {
        $category = AssetCategory::factory()->create(['key' => 'serwery', 'name' => 'Serwery']);

        Livewire::test(Index::class)
            ->call('edit', $category->id)
            ->set('name', 'Serwery i hosty')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asset_categories', [
            'id' => $category->id,
            'key' => 'serwery',
            'name' => 'Serwery i hosty',
        ]);
    }

    public function test_deactivate_toggles_is_active_without_hard_delete(): void
    {
        $category = AssetCategory::factory()->create(['is_active' => true]);

        Livewire::test(Index::class)->call('deactivate', $category->id);

        $this->assertDatabaseHas('asset_categories', [
            'id' => $category->id,
            'is_active' => false,
        ]);
        // Brak twardego usunięcia ani soft-delete podczas dezaktywacji.
        $this->assertNull($category->fresh()->deleted_at);
    }
}
