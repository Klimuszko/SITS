<?php

namespace Tests\Feature;

use App\Livewire\Audit\Index;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditIndexTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->admin()->create(['name' => 'Adam Admin']);
    }

    /** Tworzy wpis audytu z kontrolowanym created_at (created_at nie jest fillable). */
    private function makeLog(array $attributes, ?string $createdAt = null): AuditLog
    {
        $log = AuditLog::create($attributes);

        if ($createdAt !== null) {
            $log->forceFill(['created_at' => $createdAt])->saveQuietly();
        }

        return $log->refresh();
    }

    public function test_action_filter_shows_only_matching_rows(): void
    {
        $this->actingAs($this->admin());

        $this->makeLog([
            'action' => 'ticket.created',
            'subject_type' => 'App\Models\Ticket',
            'subject_id' => 11,
        ]);
        $this->makeLog([
            'action' => 'asset.created',
            'subject_type' => 'App\Models\Asset',
            'subject_id' => 22,
        ]);

        Livewire::test(Index::class)
            ->set('action', 'ticket.created')
            ->assertSee('Ticket #11')
            ->assertDontSee('Asset #22');
    }

    public function test_user_filter_shows_only_that_users_rows(): void
    {
        $this->actingAs($this->admin());

        $alice = User::factory()->support()->create(['name' => 'Alicja Support']);
        $bob = User::factory()->support()->create(['name' => 'Bartek Support']);

        $this->makeLog([
            'user_id' => $alice->id,
            'action' => 'ticket.created',
            'subject_type' => 'App\Models\Ticket',
            'subject_id' => 101,
        ]);
        $this->makeLog([
            'user_id' => $bob->id,
            'action' => 'ticket.created',
            'subject_type' => 'App\Models\Ticket',
            'subject_id' => 202,
        ]);

        Livewire::test(Index::class)
            ->set('user', (string) $alice->id)
            ->assertSee('Ticket #101')
            ->assertDontSee('Ticket #202');
    }

    public function test_subject_type_filter_narrows_results(): void
    {
        $this->actingAs($this->admin());

        $this->makeLog([
            'action' => 'ticket.created',
            'subject_type' => 'App\Models\Ticket',
            'subject_id' => 7,
        ]);
        $this->makeLog([
            'action' => 'asset.created',
            'subject_type' => 'App\Models\Asset',
            'subject_id' => 8,
        ]);

        Livewire::test(Index::class)
            ->set('subjectType', 'App\Models\Asset')
            ->assertSee('Asset #8')
            ->assertDontSee('Ticket #7');
    }

    public function test_date_range_excludes_out_of_range_rows(): void
    {
        $this->actingAs($this->admin());

        $this->makeLog([
            'action' => 'ticket.created',
            'subject_type' => 'App\Models\Ticket',
            'subject_id' => 555,
        ], createdAt: '2026-06-15 10:00:00');

        $this->makeLog([
            'action' => 'ticket.created',
            'subject_type' => 'App\Models\Ticket',
            'subject_id' => 999,
        ], createdAt: '2026-01-01 10:00:00');

        // Okno obejmujące tylko czerwcowy wpis.
        Livewire::test(Index::class)
            ->set('dateFrom', '2026-06-01')
            ->set('dateTo', '2026-06-30')
            ->assertSee('Ticket #555')
            ->assertDontSee('Ticket #999');
    }

    public function test_null_user_renders_system_label(): void
    {
        $this->actingAs($this->admin());

        $this->makeLog([
            'user_id' => null,
            'action' => 'ticket.created',
            'subject_type' => 'App\Models\Ticket',
            'subject_id' => 1,
        ]);

        Livewire::test(Index::class)
            ->assertSee('— (system)');
    }

    public function test_toggle_expands_and_collapses_detail(): void
    {
        $this->actingAs($this->admin());

        $log = $this->makeLog([
            'action' => 'ticket.status_changed',
            'subject_type' => 'App\Models\Ticket',
            'subject_id' => 3,
            'old_values' => ['status' => 'open'],
            'new_values' => ['status' => 'closed'],
        ]);

        $component = Livewire::test(Index::class)
            ->assertSet('expandedId', null)
            ->call('toggle', $log->id)
            ->assertSet('expandedId', $log->id)
            ->assertSee('Poprzednie wartości')
            ->assertSee('Nowe wartości');

        $component->call('toggle', $log->id)
            ->assertSet('expandedId', null);
    }

    public function test_component_does_not_mutate_audit_rows(): void
    {
        $this->actingAs($this->admin());

        $log = $this->makeLog([
            'action' => 'ticket.created',
            'subject_type' => 'App\Models\Ticket',
            'subject_id' => 42,
        ]);

        Livewire::test(Index::class)
            ->set('action', 'ticket.created')
            ->call('toggle', $log->id);

        // Read-only: liczba wierszy i sama treść nie ulegają zmianie.
        $this->assertSame(1, AuditLog::count());
        $this->assertDatabaseHas('audit_logs', [
            'id' => $log->id,
            'action' => 'ticket.created',
            'subject_id' => 42,
        ]);
    }
}
