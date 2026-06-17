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
            'parent_id' => null,
            'name' => ucfirst($name),
            'key' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'is_group' => false,
            'is_repeatable' => false,
            'min_entries' => null,
            'max_entries' => null,
            'is_ticket_linkable' => false,
            'display_field_id' => null,
            'link_parent_on_select' => false,
            'ticket_label' => null,
            'order' => 0,
            'is_active' => true,
        ];
    }

    public function forCategory(AssetCategory $category): static
    {
        return $this->state(fn () => ['asset_category_id' => $category->id]);
    }

    /** Węzeł-kontener (grupa). */
    public function group(): static
    {
        return $this->state(fn () => ['is_group' => true]);
    }

    /** Grupa powtarzalna (wiele wpisów = kandydaci na pod-zasoby). */
    public function repeatable(): static
    {
        return $this->state(fn () => [
            'is_group' => true,
            'is_repeatable' => true,
        ]);
    }

    /** Podsekcja zagnieżdżona pod wskazanym węzłem (ta sama kategoria). */
    public function subsectionOf(AssetSection $parent): static
    {
        return $this->state(fn () => [
            'asset_category_id' => $parent->asset_category_id,
            'parent_id' => $parent->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
