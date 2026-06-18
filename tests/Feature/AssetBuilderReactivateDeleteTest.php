<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Livewire\AssetCategories\Builder;
use App\Livewire\AssetCategories\Index;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetFieldValue;
use App\Models\AssetGroupEntry;
use App\Models\AssetGroupEntryValue;
use App\Models\AssetSection;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetBuilderReactivateDeleteTest extends TestCase
{
    use RefreshDatabase;

    /* ============================ REACTIVATE ============================ */

    public function test_admin_can_reactivate_inactive_field(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $category = AssetCategory::factory()->create();
        $field = AssetField::factory()->forCategory($category)->inactive()->create();

        Livewire::test(Builder::class, ['assetCategory' => $category])
            ->call('reactivateField', $field->id);

        $this->assertTrue($field->fresh()->is_active);
    }

    public function test_admin_can_reactivate_inactive_section(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $category = AssetCategory::factory()->create();
        $section = AssetSection::factory()->forCategory($category)->inactive()->create();

        Livewire::test(Builder::class, ['assetCategory' => $category])
            ->call('reactivateSection', $section->id);

        $this->assertTrue($section->fresh()->is_active);
    }

    public function test_admin_can_reactivate_inactive_category(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $category = AssetCategory::factory()->create(['is_active' => false]);

        Livewire::test(Index::class)
            ->call('reactivate', $category->id);

        $this->assertTrue($category->fresh()->is_active);
    }

    /* ======================= SUPERADMIN-ONLY GATE ====================== */

    public function test_admin_cannot_force_delete_field(): void
    {
        // Admin (NOT super) — Gate::before nie zwalnia, więc force-delete musi odmówić.
        $this->actingAs(User::factory()->admin()->create());

        $category = AssetCategory::factory()->create();
        $field = AssetField::factory()->forCategory($category)->create();

        // Robust wobec Livewire 3 (akcja może zwrócić 403 LUB rzucić wyjątek):
        // łapiemy ewentualny throw, a dowodem odmowy jest przetrwanie wiersza
        // (force-delete autoryzuje PRZED usunięciem).
        try {
            Livewire::test(Builder::class, ['assetCategory' => $category])
                ->call('forceDeleteField', $field->id);
        } catch (AuthorizationException) {
            // oczekiwane — Admin (nie SuperAdmin) nie ma prawa force-delete
        }

        $this->assertDatabaseHas('asset_fields', ['id' => $field->id]);
    }

    public function test_admin_cannot_force_delete_section(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $category = AssetCategory::factory()->create();
        $section = AssetSection::factory()->forCategory($category)->create();

        try {
            Livewire::test(Builder::class, ['assetCategory' => $category])
                ->call('forceDeleteSection', $section->id);
        } catch (AuthorizationException) {
            // oczekiwane — Admin (nie SuperAdmin) nie ma prawa force-delete
        }

        $this->assertDatabaseHas('asset_sections', ['id' => $section->id]);
    }

    public function test_admin_cannot_force_delete_category(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $category = AssetCategory::factory()->create();

        try {
            Livewire::test(Index::class)
                ->call('forceDelete', $category->id);
        } catch (AuthorizationException) {
            // oczekiwane — Admin (nie SuperAdmin) nie ma prawa force-delete
        }

        $this->assertDatabaseHas('asset_categories', ['id' => $category->id]);
    }

    /* ====================== FIELD FORCE-DELETE ========================= */

    public function test_superadmin_force_delete_field_cascades_values_and_audits(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $category = AssetCategory::factory()->create();
        $field = AssetField::factory()->forCategory($category)->create();

        $asset = Asset::factory()->forCategory($category)->create();
        $flatValue = AssetFieldValue::create([
            'asset_id' => $asset->id,
            'asset_field_id' => $field->id,
            'value' => 'flat',
        ]);

        // Wartość pola w obrębie wpisu grupy powtarzalnej.
        $group = AssetSection::factory()->forCategory($category)->repeatable()->create();
        $entry = AssetGroupEntry::factory()->forAsset($asset)->forSection($group)->create();
        $entryValue = AssetGroupEntryValue::create([
            'asset_group_entry_id' => $entry->id,
            'asset_field_id' => $field->id,
            'value' => 'grouped',
        ]);

        Livewire::test(Builder::class, ['assetCategory' => $category])
            ->call('forceDeleteField', $field->id);

        $this->assertDatabaseMissing('asset_fields', ['id' => $field->id]);
        $this->assertDatabaseMissing('asset_field_values', ['id' => $flatValue->id]);
        $this->assertDatabaseMissing('asset_group_entry_values', ['id' => $entryValue->id]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::AssetFieldDeleted->value,
            'subject_id' => $field->id,
        ]);
    }

    /* ===================== SECTION FORCE-DELETE ======================== */

    public function test_superadmin_force_delete_section_removes_descendants_no_orphan(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $category = AssetCategory::factory()->create();

        $section = AssetSection::factory()->forCategory($category)->create();
        $childSection = AssetSection::factory()->subsectionOf($section)->create();

        $sectionField = AssetField::factory()->forCategory($category)->create([
            'asset_section_id' => $section->id,
        ]);
        $childField = AssetField::factory()->forCategory($category)->create([
            'asset_section_id' => $childSection->id,
        ]);

        Livewire::test(Builder::class, ['assetCategory' => $category])
            ->call('forceDeleteSection', $section->id);

        // Brak osieroconych potomków: węzeł, podsekcja i ich pola znikają.
        $this->assertDatabaseMissing('asset_sections', ['id' => $section->id]);
        $this->assertDatabaseMissing('asset_sections', ['id' => $childSection->id]);
        $this->assertDatabaseMissing('asset_fields', ['id' => $sectionField->id]);
        $this->assertDatabaseMissing('asset_fields', ['id' => $childField->id]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::AssetSectionDeleted->value,
            'subject_id' => $section->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::AssetSectionDeleted->value,
            'subject_id' => $childSection->id,
        ]);
    }

    /* ==================== CATEGORY FORCE-DELETE ======================== */

    public function test_superadmin_cannot_force_delete_category_in_use(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $category = AssetCategory::factory()->create();
        Asset::factory()->forCategory($category)->create();

        Livewire::test(Index::class)
            ->call('forceDelete', $category->id)
            ->assertHasNoErrors();

        // Zablokowane bez crasha na FK — wiersz nadal istnieje.
        $this->assertDatabaseHas('asset_categories', ['id' => $category->id]);
        $this->assertDatabaseMissing('audit_logs', [
            'action' => AuditAction::AssetCategoryDeleted->value,
            'subject_id' => $category->id,
        ]);
    }

    public function test_superadmin_force_delete_unused_category(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $category = AssetCategory::factory()->create();
        $section = AssetSection::factory()->forCategory($category)->create();
        $field = AssetField::factory()->forCategory($category)->create([
            'asset_section_id' => $section->id,
        ]);

        Livewire::test(Index::class)
            ->call('forceDelete', $category->id);

        $this->assertDatabaseMissing('asset_categories', ['id' => $category->id]);
        // Sekcje i pola znikają kaskadowo wraz z kategorią.
        $this->assertDatabaseMissing('asset_sections', ['id' => $section->id]);
        $this->assertDatabaseMissing('asset_fields', ['id' => $field->id]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::AssetCategoryDeleted->value,
            'subject_id' => $category->id,
        ]);
    }

    /* ====================== SHOW INACTIVE NODES ======================== */

    public function test_builder_shows_inactive_section_with_reactivate(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $category = AssetCategory::factory()->create();
        $section = AssetSection::factory()->forCategory($category)
            ->inactive()
            ->create(['name' => 'Stara sekcja']);

        Livewire::test(Builder::class, ['assetCategory' => $category])
            ->assertSee('Stara sekcja')
            ->assertSee('Reaktywuj');
    }

    public function test_builder_shows_inactive_field_with_reactivate(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $category = AssetCategory::factory()->create();
        AssetField::factory()->forCategory($category)
            ->inactive()
            ->create(['name' => 'Stare pole']);

        Livewire::test(Builder::class, ['assetCategory' => $category])
            ->assertSee('Stare pole')
            ->assertSee('Reaktywuj');
    }

    public function test_index_shows_inactive_category_with_reactivate(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        AssetCategory::factory()->create([
            'name' => 'Wycofana kategoria',
            'is_active' => false,
        ]);

        Livewire::test(Index::class)
            ->assertSee('Wycofana kategoria')
            ->assertSee('Reaktywuj');
    }

    /* ============ SUPERADMIN SEES FORCE-DELETE; ADMIN DOES NOT ========= */

    public function test_superadmin_sees_force_delete_control_in_builder(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        $category = AssetCategory::factory()->create();
        AssetField::factory()->forCategory($category)->create(['name' => 'Pole X']);

        Livewire::test(Builder::class, ['assetCategory' => $category])
            ->assertSee('Usuń trwale');
    }

    public function test_admin_does_not_see_force_delete_control_in_builder(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $category = AssetCategory::factory()->create();
        AssetField::factory()->forCategory($category)->create(['name' => 'Pole X']);

        Livewire::test(Builder::class, ['assetCategory' => $category])
            ->assertDontSee('Usuń trwale');
    }
}
