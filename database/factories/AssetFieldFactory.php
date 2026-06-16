<?php

namespace Database\Factories;

use App\Enums\AssetFieldType;
use App\Models\AssetCategory;
use App\Models\AssetField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AssetField>
 */
class AssetFieldFactory extends Factory
{
    protected $model = AssetField::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'asset_category_id' => AssetCategory::factory(),
            'asset_section_id' => null,
            'name' => ucfirst($name),
            'key' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'type' => AssetFieldType::Text,
            'options' => null,
            'is_required' => false,
            'order' => 0,
            'is_active' => true,
        ];
    }

    public function forCategory(AssetCategory $category): static
    {
        return $this->state(fn () => ['asset_category_id' => $category->id]);
    }

    public function type(AssetFieldType $type): static
    {
        return $this->state(fn () => ['type' => $type]);
    }

    public function required(): static
    {
        return $this->state(fn () => ['is_required' => true]);
    }

    /** @param  array<int,string>  $options */
    public function select(array $options): static
    {
        return $this->state(fn () => [
            'type' => AssetFieldType::Select,
            'options' => $options,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
