<?php

namespace Tests\Feature;

use App\Livewire\AssetCategories\Builder;
use App\Livewire\AssetCategories\Index;
use App\Models\AssetCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetCategoryBuilderAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_index(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Index::class)->assertOk();
    }

    public function test_admin_can_open_builder(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $category = AssetCategory::factory()->create();

        Livewire::test(Builder::class, ['assetCategory' => $category])->assertOk();
    }

    public function test_support_is_forbidden_on_index(): void
    {
        $this->actingAs(User::factory()->support()->create());

        Livewire::test(Index::class)->assertForbidden();
    }

    public function test_support_is_forbidden_on_builder(): void
    {
        $this->actingAs(User::factory()->support()->create());

        $category = AssetCategory::factory()->create();

        Livewire::test(Builder::class, ['assetCategory' => $category])->assertForbidden();
    }

    public function test_client_is_forbidden_on_index(): void
    {
        // Domyślny użytkownik z fabryki = klient (rola User).
        $this->actingAs(User::factory()->create());

        Livewire::test(Index::class)->assertForbidden();
    }

    public function test_client_is_forbidden_on_builder(): void
    {
        $this->actingAs(User::factory()->create());

        $category = AssetCategory::factory()->create();

        Livewire::test(Builder::class, ['assetCategory' => $category])->assertForbidden();
    }
}
