<?php

namespace Tests\Feature;

use App\Livewire\Dictionaries\TicketCategories;
use App\Models\TicketCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketCategoryManageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_creates_a_ticket_category(): void
    {
        Livewire::test(TicketCategories::class)
            ->set('name', 'Sprzęt')
            ->set('key', 'hardware')
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ticket_categories', [
            'name' => 'Sprzęt',
            'key' => 'hardware',
            'is_active' => true,
        ]);
    }

    public function test_key_is_auto_generated_from_name_when_not_provided(): void
    {
        // Klucz jest ukryty w UI — przy tworzeniu bez podania klucza generuje się z nazwy.
        Livewire::test(TicketCategories::class)
            ->set('name', 'Sieć i VPN')
            ->call('save')
            ->assertHasNoErrors();

        $category = TicketCategory::where('name', 'Sieć i VPN')->firstOrFail();
        $this->assertSame('siec-i-vpn', $category->key);
    }

    public function test_admin_edits_a_ticket_category(): void
    {
        $category = TicketCategory::factory()->create(['name' => 'Stara nazwa']);

        Livewire::test(TicketCategories::class)
            ->call('edit', $category->id)
            ->assertSet('name', 'Stara nazwa')
            ->set('name', 'Nowa nazwa')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Nowa nazwa', $category->fresh()->name);
    }

    public function test_deactivate_sets_inactive_and_preserves_row(): void
    {
        $category = TicketCategory::factory()->create(['is_active' => true]);

        Livewire::test(TicketCategories::class)
            ->call('deactivate', $category->id);

        // Wiersz zachowany (brak twardego usunięcia), tylko flaga zmieniona.
        $this->assertDatabaseHas('ticket_categories', [
            'id' => $category->id,
            'is_active' => false,
        ]);
        $this->assertNotNull(TicketCategory::find($category->id));
    }

    public function test_name_is_required(): void
    {
        Livewire::test(TicketCategories::class)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    }
}
