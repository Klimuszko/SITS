<?php

namespace Database\Factories;

use App\Models\AssetCategory;
use App\Models\AssetSection;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssetSection>
 */
class AssetSectionFactory extends Factory
{
    protected $model = AssetSection::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'asset_category_id' => AssetCategory::factory(),
            'name' => ucfirst($name),
            'key' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'order' => 0,
            'is_active' => true,
        ];
    }

    public function forCategory(AssetCategory $category): static
    {
        return $this->state(fn () => ['asset_category_id' => $category->id]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
