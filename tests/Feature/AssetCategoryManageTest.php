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

    public function test_admin_creates_category(): void
    {
        Livewire::test(Index::class)
            ->set('name', 'Serwery')
            ->set('key', 'serwery')
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

    public function test_key_must_be_unique(): void
    {
        AssetCategory::factory()->create(['key' => 'serwery']);

        Livewire::test(Index::class)
            ->set('name', 'Inne serwery')
            ->set('key', 'serwery')
            ->call('save')
            ->assertHasErrors(['key' => 'unique']);
    }

    public function test_name_and_key_are_required(): void
    {
        Livewire::test(Index::class)
            ->set('name', '')
            ->set('key', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required', 'key' => 'required']);
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
