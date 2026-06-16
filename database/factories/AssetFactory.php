<?php

namespace Database\Factories;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    protected $model = Asset::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'location_id' => null,
            'asset_category_id' => AssetCategory::factory(),
            'parent_asset_id' => null,
            'name' => ucfirst(fake()->words(2, true)),
            'inventory_code' => null,
            'status' => AssetStatus::Active,
            'is_private' => false,
            'notes' => null,
            'created_by' => null,
        ];
    }

    public function forOrganization(Organization $organization): static
    {
        return $this->state(fn () => ['organization_id' => $organization->id]);
    }

    public function forCategory(AssetCategory $category): static
    {
        return $this->state(fn () => ['asset_category_id' => $category->id]);
    }

    public function status(AssetStatus $status): static
    {
        return $this->state(fn () => ['status' => $status]);
    }

    public function private(): static
    {
        return $this->state(fn () => ['is_private' => true]);
    }
}
