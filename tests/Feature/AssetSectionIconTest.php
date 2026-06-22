<?php

namespace Tests\Feature;

use App\Livewire\AssetCategories\Builder;
use App\Models\AssetCategory;
use App\Models\AssetSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Ikona SVG dla sekcji najwyższego poziomu (główna kategoria zasobu w bocznym menu).
 * Tylko top-level; sanityzowana przy zapisie (inline w widoku).
 */
class AssetSectionIconTest extends TestCase
{
    use RefreshDatabase;

    protected AssetCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
        $this->category = AssetCategory::factory()->create();
    }

    public function test_top_level_section_stores_sanitized_icon(): void
    {
        $dirty = '<svg viewBox="0 0 24 24"><script>alert(1)</script><path d="M4 4h16"/></svg>';

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('sectionKind', Builder::KIND_SECTION)
            ->set('sectionName', 'Hardware')
            ->set('sectionIcon', $dirty)
            ->call('saveSection')
            ->assertHasNoErrors();

        $section = AssetSection::where('asset_category_id', $this->category->id)
            ->where('name', 'Hardware')->firstOrFail();

        $this->assertNotNull($section->icon);
        $this->assertStringNotContainsStringIgnoringCase('<script', $section->icon);
        $this->assertStringContainsString('<path', $section->icon);
    }

    public function test_icon_ignored_for_subsection(): void
    {
        $parent = AssetSection::factory()->forCategory($this->category)->create();

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('sectionKind', Builder::KIND_SUBSECTION)
            ->set('sectionName', 'RAM')
            ->set('sectionParentId', $parent->id)
            ->set('sectionIcon', '<svg viewBox="0 0 24 24"><path d="M1 1"/></svg>')
            ->call('saveSection')
            ->assertHasNoErrors();

        $section = AssetSection::where('asset_category_id', $this->category->id)
            ->where('name', 'RAM')->firstOrFail();

        $this->assertNull($section->icon);  // ikona tylko dla najwyższego poziomu
    }

    public function test_invalid_icon_is_rejected(): void
    {
        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('sectionKind', Builder::KIND_SECTION)
            ->set('sectionName', 'Bad')
            ->set('sectionIcon', 'to nie jest svg')
            ->call('saveSection')
            ->assertHasErrors('sectionIcon');

        $this->assertDatabaseMissing('asset_sections', ['name' => 'Bad']);
    }
}
