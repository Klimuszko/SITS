<?php

namespace Tests\Feature;

use App\Livewire\Dictionaries\KnowledgeCategories;
use App\Models\KnowledgeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KnowledgeCategoryManageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_creates_category_with_auto_slug(): void
    {
        Livewire::test(KnowledgeCategories::class)
            ->set('name', 'Sieci komputerowe')
            ->set('slug', '') // pusty → auto z nazwy
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('knowledge_categories', [
            'name' => 'Sieci komputerowe',
            'slug' => 'sieci-komputerowe',
        ]);
    }

    public function test_slug_must_be_unique(): void
    {
        KnowledgeCategory::factory()->create(['slug' => 'zajety-slug']);

        Livewire::test(KnowledgeCategories::class)
            ->set('name', 'Druga kategoria')
            ->set('slug', 'zajety-slug')
            ->call('save')
            ->assertHasErrors(['slug' => 'unique']);

        $this->assertDatabaseMissing('knowledge_categories', ['name' => 'Druga kategoria']);
    }

    public function test_category_cannot_be_its_own_parent(): void
    {
        $category = KnowledgeCategory::factory()->create();

        Livewire::test(KnowledgeCategories::class)
            ->call('edit', $category->id)
            ->set('parent_id', $category->id)
            ->call('save')
            ->assertHasErrors(['parent_id']);

        $this->assertNull($category->fresh()->parent_id);
    }

    public function test_admin_can_set_a_valid_parent(): void
    {
        $parent = KnowledgeCategory::factory()->create();

        Livewire::test(KnowledgeCategories::class)
            ->set('name', 'Podkategoria')
            ->set('parent_id', $parent->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('knowledge_categories', [
            'name' => 'Podkategoria',
            'parent_id' => $parent->id,
        ]);
    }

    public function test_delete_soft_deletes_category(): void
    {
        $category = KnowledgeCategory::factory()->create();

        Livewire::test(KnowledgeCategories::class)
            ->call('delete', $category->id);

        $this->assertSoftDeleted('knowledge_categories', ['id' => $category->id]);
    }

    public function test_name_is_required(): void
    {
        Livewire::test(KnowledgeCategories::class)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    }
}
