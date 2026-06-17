<?php

namespace Tests\Feature;

use App\Livewire\Dictionaries\TicketPriorities;
use App\Models\TicketPriority;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TicketPriorityManageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_admin_creates_a_priority_with_level_in_range(): void
    {
        Livewire::test(TicketPriorities::class)
            ->set('name', 'Wysoki')
            ->set('level', 3)
            ->set('color', 'orange')
            ->set('is_active', true)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('ticket_priorities', [
            'name' => 'Wysoki',
            'level' => 3,
            'color' => 'orange',
            'is_active' => true,
        ]);
    }

    public function test_level_above_range_is_rejected(): void
    {
        Livewire::test(TicketPriorities::class)
            ->set('name', 'Za wysoki')
            ->set('level', 5)
            ->call('save')
            ->assertHasErrors(['level']);

        $this->assertDatabaseMissing('ticket_priorities', ['name' => 'Za wysoki']);
    }

    public function test_level_below_range_is_rejected(): void
    {
        Livewire::test(TicketPriorities::class)
            ->set('name', 'Za niski')
            ->set('level', 0)
            ->call('save')
            ->assertHasErrors(['level']);

        $this->assertDatabaseMissing('ticket_priorities', ['name' => 'Za niski']);
    }

    public function test_invalid_color_is_rejected(): void
    {
        Livewire::test(TicketPriorities::class)
            ->set('name', 'Zły kolor')
            ->set('level', 2)
            ->set('color', 'fuchsia')
            ->call('save')
            ->assertHasErrors(['color']);

        $this->assertDatabaseMissing('ticket_priorities', ['name' => 'Zły kolor']);
    }

    public function test_deactivate_preserves_row(): void
    {
        $priority = TicketPriority::factory()->create(['is_active' => true]);

        Livewire::test(TicketPriorities::class)
            ->call('deactivate', $priority->id);

        $this->assertDatabaseHas('ticket_priorities', [
            'id' => $priority->id,
            'is_active' => false,
        ]);
    }
}
