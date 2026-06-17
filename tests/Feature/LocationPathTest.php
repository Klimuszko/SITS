<?php

namespace Tests\Feature;

use App\Livewire\Tickets\Create;
use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LocationPathTest extends TestCase
{
    use RefreshDatabase;

    public function test_path_label_builds_full_chain_for_three_levels(): void
    {
        $org = Organization::factory()->create();
        $building = Location::factory()->forOrganization($org)->create(['name' => 'Budynek A']);
        $floor = Location::factory()->childOf($building)->create(['name' => 'Piętro 1']);
        $room = Location::factory()->childOf($floor)->create(['name' => 'Pomieszczenie 119']);

        $this->assertSame('Budynek A / Piętro 1 / Pomieszczenie 119', $room->pathLabel());
    }

    public function test_path_label_for_single_level_returns_just_the_name(): void
    {
        $org = Organization::factory()->create();
        $building = Location::factory()->forOrganization($org)->create(['name' => 'Budynek A']);

        $this->assertSame('Budynek A', $building->pathLabel());
    }

    public function test_path_label_supports_custom_separator(): void
    {
        $org = Organization::factory()->create();
        $building = Location::factory()->forOrganization($org)->create(['name' => 'Budynek A']);
        $floor = Location::factory()->childOf($building)->create(['name' => 'Piętro 1']);

        $this->assertSame('Budynek A > Piętro 1', $floor->pathLabel(' > '));
    }

    public function test_tree_for_organization_orders_children_under_parents(): void
    {
        $org = Organization::factory()->create();
        // Nazwy dobrane tak, by sortowanie alfabetyczne NIE dało kolejności drzewa,
        // jeśli helper nie układałby dzieci pod rodzicami.
        $building = Location::factory()->forOrganization($org)->create(['name' => 'Z-Budynek']);
        $floor = Location::factory()->childOf($building)->create(['name' => 'A-Piętro']);
        $room = Location::factory()->childOf($floor)->create(['name' => 'M-Pomieszczenie']);

        $tree = Location::treeForOrganization($org->id);

        $orderedIds = $tree->pluck('id')->all();
        $this->assertSame([$building->id, $floor->id, $room->id], $orderedIds);

        // Pełne ścieżki budują się bez dociągania rodziców z bazy (relacja z pamięci).
        $last = $tree->last();
        $this->assertSame('Z-Budynek / A-Piętro / M-Pomieszczenie', $last->pathLabel());
    }

    public function test_tree_for_organization_is_isolated_per_organization(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();
        Location::factory()->forOrganization($orgA)->create(['name' => 'A-root']);
        Location::factory()->forOrganization($orgB)->create(['name' => 'B-root']);

        $tree = Location::treeForOrganization($orgA->id);

        $this->assertSame(['A-root'], $tree->pluck('name')->all());
    }

    public function test_path_map_for_ids_resolves_full_paths_without_walking_per_row(): void
    {
        $org = Organization::factory()->create();
        $building = Location::factory()->forOrganization($org)->create(['name' => 'Budynek A']);
        $floor = Location::factory()->childOf($building)->create(['name' => 'Piętro 1']);
        $room = Location::factory()->childOf($floor)->create(['name' => 'Pomieszczenie 119']);

        $map = Location::pathMapForIds([$room->id, $building->id, null]);

        $this->assertSame('Budynek A / Piętro 1 / Pomieszczenie 119', $map[$room->id]);
        $this->assertSame('Budynek A', $map[$building->id]);
        $this->assertArrayNotHasKey(0, $map);
    }

    public function test_ticket_create_location_options_include_child_with_parent_path(): void
    {
        $org = Organization::factory()->create();
        $building = Location::factory()->forOrganization($org)->create(['name' => 'Budynek A']);
        $floor = Location::factory()->childOf($building)->create(['name' => 'Piętro 1']);
        $room = Location::factory()->childOf($floor)->create(['name' => 'Pomieszczenie 119']);

        $admin = User::factory()->superAdmin()->create();
        $this->actingAs($admin);

        Livewire::test(Create::class)
            ->set('organization_id', $org->id)
            ->assertSee('Budynek A / Piętro 1 / Pomieszczenie 119')
            ->assertSee('value="'.$room->id.'"', false);
    }
}
