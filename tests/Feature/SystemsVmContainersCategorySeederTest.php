<?php

namespace Tests\Feature;

use App\Enums\AssetFieldType;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetSection;
use Database\Seeders\SystemsVmContainersCategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SystemsVmContainersCategorySeederTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'systemy-vm-kontenery-aplikacje';

    public function test_seeder_creates_full_category_tree(): void
    {
        $this->seed(SystemsVmContainersCategorySeeder::class);

        $category = AssetCategory::where('key', self::KEY)->firstOrFail();
        $this->assertTrue($category->is_active);

        // 9 sekcji najwyższego poziomu, 54 pola.
        $this->assertSame(9, $category->sections()->whereNull('parent_id')->count());
        $this->assertSame(54, $category->fields()->count());

        // Minimalny zestaw obowiązkowych = 11 pól.
        $this->assertSame(11, $category->fields()->where('is_required', true)->count());

        // Reszta opcjonalna.
        $this->assertSame(43, $category->fields()->where('is_required', false)->count());
    }

    public function test_field_types_and_options_are_correct(): void
    {
        $this->seed(SystemsVmContainersCategorySeeder::class);

        $category = AssetCategory::where('key', self::KEY)->firstOrFail();

        $typElementu = $category->fields()->where('name', 'Typ elementu')->firstOrFail();
        $this->assertSame(AssetFieldType::Select, $typElementu->type);
        $this->assertContains('Kontener', $typElementu->options);
        $this->assertContains('Maszyna wirtualna', $typElementu->options);
        $this->assertCount(10, $typElementu->options);

        $this->assertSame(AssetFieldType::Ip, $category->fields()->where('name', 'Adres IP')->value('type'));
        $this->assertSame(AssetFieldType::Url, $category->fields()->where('name', 'URL / adres usługi')->value('type'));
        $this->assertSame(AssetFieldType::Date, $category->fields()->where('name', 'Ostatni test odtworzenia')->value('type'));
        $this->assertSame(AssetFieldType::Textarea, $category->fields()->where('name', 'Opis / uwagi')->value('type'));

        // „Opis / uwagi” jest obowiązkowe.
        $this->assertTrue((bool) $category->fields()->where('name', 'Opis / uwagi')->value('is_required'));
    }

    public function test_field_keys_are_unique_within_category(): void
    {
        $this->seed(SystemsVmContainersCategorySeeder::class);

        $category = AssetCategory::where('key', self::KEY)->firstOrFail();
        $keys = $category->fields()->pluck('key');

        $this->assertSame($keys->count(), $keys->unique()->count());
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(SystemsVmContainersCategorySeeder::class);
        $this->seed(SystemsVmContainersCategorySeeder::class);

        $this->assertSame(1, AssetCategory::where('key', self::KEY)->count());
        $this->assertSame(9, AssetSection::whereIn(
            'asset_category_id',
            AssetCategory::where('key', self::KEY)->pluck('id')
        )->count());
        $this->assertSame(54, AssetField::whereIn(
            'asset_category_id',
            AssetCategory::where('key', self::KEY)->pluck('id')
        )->count());
    }
}
