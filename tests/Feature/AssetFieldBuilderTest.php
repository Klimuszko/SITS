<?php

namespace Tests\Feature;

use App\Enums\AssetFieldType;
use App\Livewire\AssetCategories\Builder;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetFieldValue;
use App\Models\AssetSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssetFieldBuilderTest extends TestCase
{
    use RefreshDatabase;

    protected AssetCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
        $this->category = AssetCategory::factory()->create();
    }

    public function test_admin_adds_select_field_with_options_required_and_section(): void
    {
        $section = AssetSection::factory()->forCategory($this->category)->create();

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('fieldName', 'System operacyjny')
            ->set('fieldKey', 'os')
            ->set('fieldType', AssetFieldType::Select->value)
            ->set('fieldOptions', "Linux\nWindows\nmacOS")
            ->set('fieldIsRequired', true)
            ->set('fieldSectionId', $section->id)
            ->set('fieldOrder', 2)
            ->call('saveField')
            ->assertHasNoErrors();

        $field = AssetField::where('asset_category_id', $this->category->id)
            ->where('key', 'os')
            ->firstOrFail();

        $this->assertSame(AssetFieldType::Select, $field->type);
        $this->assertSame(['Linux', 'Windows', 'macOS'], $field->options);
        $this->assertTrue($field->is_required);
        $this->assertSame($section->id, $field->asset_section_id);
        $this->assertSame(2, $field->order);
        $this->assertTrue($field->is_active);
    }

    public function test_field_key_is_auto_generated_from_name(): void
    {
        // Klucz pola ukryty w UI — generowany z nazwy. Bez set('fieldKey').
        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('fieldName', 'Adres IP')
            ->set('fieldType', AssetFieldType::Text->value)
            ->call('saveField')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asset_fields', [
            'asset_category_id' => $this->category->id,
            'name' => 'Adres IP',
            'key' => 'adres-ip',
        ]);
    }

    public function test_field_auto_key_gets_unique_suffix_on_collision(): void
    {
        AssetField::factory()->forCategory($this->category)->create(['key' => 'adres-ip']);

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('fieldName', 'Adres IP')
            ->set('fieldType', AssetFieldType::Text->value)
            ->call('saveField')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asset_fields', [
            'asset_category_id' => $this->category->id,
            'key' => 'adres-ip-2',
        ]);
    }

    public function test_select_requires_options(): void
    {
        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('fieldName', 'System operacyjny')
            ->set('fieldKey', 'os')
            ->set('fieldType', AssetFieldType::Select->value)
            ->set('fieldOptions', '')
            ->call('saveField')
            ->assertHasErrors(['fieldOptions']);
    }

    public function test_select_with_blank_only_options_is_rejected(): void
    {
        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('fieldName', 'System operacyjny')
            ->set('fieldKey', 'os')
            ->set('fieldType', AssetFieldType::Select->value)
            ->set('fieldOptions', " , , \n ")
            ->call('saveField')
            ->assertHasErrors(['fieldOptions']);
    }

    public function test_key_must_be_unique_within_category(): void
    {
        AssetField::factory()->forCategory($this->category)->create(['key' => 'os']);

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('fieldName', 'Inny OS')
            ->set('fieldKey', 'os')
            ->set('fieldType', AssetFieldType::Text->value)
            ->call('saveField')
            ->assertHasErrors(['fieldKey' => 'unique']);
    }

    public function test_same_key_allowed_in_different_category(): void
    {
        $other = AssetCategory::factory()->create();
        AssetField::factory()->forCategory($other)->create(['key' => 'os']);

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('fieldName', 'System operacyjny')
            ->set('fieldKey', 'os')
            ->set('fieldType', AssetFieldType::Text->value)
            ->call('saveField')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asset_fields', [
            'asset_category_id' => $this->category->id,
            'key' => 'os',
        ]);
    }

    public function test_file_type_is_rejected(): void
    {
        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('fieldName', 'Załącznik')
            ->set('fieldKey', 'zalacznik')
            ->set('fieldType', AssetFieldType::File->value)
            ->call('saveField')
            ->assertHasErrors(['fieldType']);

        $this->assertDatabaseMissing('asset_fields', [
            'asset_category_id' => $this->category->id,
            'key' => 'zalacznik',
        ]);
    }

    public function test_relation_type_is_rejected(): void
    {
        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('fieldName', 'Powiązanie')
            ->set('fieldKey', 'powiazanie')
            ->set('fieldType', AssetFieldType::Relation->value)
            ->call('saveField')
            ->assertHasErrors(['fieldType']);
    }

    /**
     * KRYTYCZNE: dezaktywacja pola NIE może kasować jego wartości.
     * asset_field_values.asset_field_id ma cascadeOnDelete — twarde usunięcie
     * pola skasowałoby wszystkie wartości. Dezaktywacja musi je zachować.
     */
    public function test_deactivating_field_preserves_its_values(): void
    {
        $field = AssetField::factory()->forCategory($this->category)->create([
            'type' => AssetFieldType::Text,
            'is_active' => true,
        ]);

        $asset = Asset::factory()->forCategory($this->category)->create();

        $value = AssetFieldValue::create([
            'asset_id' => $asset->id,
            'asset_field_id' => $field->id,
            'value' => 'Wartość testowa',
        ]);

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->call('deactivateField', $field->id);

        // Pole zostało zdezaktywowane, ale NIE usunięte.
        $this->assertDatabaseHas('asset_fields', [
            'id' => $field->id,
            'is_active' => false,
        ]);

        // Wartość pola nadal istnieje (brak cascade delete).
        $this->assertDatabaseHas('asset_field_values', [
            'id' => $value->id,
            'asset_id' => $asset->id,
            'asset_field_id' => $field->id,
            'value' => 'Wartość testowa',
        ]);
    }

    public function test_section_key_must_be_unique_within_category(): void
    {
        AssetSection::factory()->forCategory($this->category)->create(['key' => 'sprzet']);

        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('sectionName', 'Sprzęt 2')
            ->set('sectionKey', 'sprzet')
            ->call('saveSection')
            ->assertHasErrors(['sectionKey' => 'unique']);
    }

    public function test_admin_adds_section(): void
    {
        Livewire::test(Builder::class, ['assetCategory' => $this->category])
            ->set('sectionName', 'Sieć')
            ->set('sectionKey', 'siec')
            ->set('sectionOrder', 1)
            ->call('saveSection')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('asset_sections', [
            'asset_category_id' => $this->category->id,
            'key' => 'siec',
            'order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_deactivating_section_does_not_hard_delete(): void
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
