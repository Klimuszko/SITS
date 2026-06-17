<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetGroupEntry;
use App\Models\AssetSection;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetGroupEntry>
 */
class AssetGroupEntryFactory extends Factory
{
    protected $model = AssetGroupEntry::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'asset_section_id' => AssetSection::factory()->repeatable(),
            'parent_entry_id' => null,
            'order' => 0,
        ];
    }

    public function forAsset(Asset $asset): static
    {
        return $this->state(fn () => ['asset_id' => $asset->id]);
    }

    public function forSection(AssetSection $section): static
    {
        return $this->state(fn () => ['asset_section_id' => $section->id]);
    }
}
