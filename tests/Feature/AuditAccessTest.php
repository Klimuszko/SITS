<?php

namespace Tests\Feature;

use App\Livewire\Audit\Index;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuditAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_open_audit_view(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(Index::class)->assertOk();
    }

    public function test_super_admin_can_open_audit_view(): void
    {
        $this->actingAs(User::factory()->superAdmin()->create());

        Livewire::test(Index::class)->assertOk();
    }

    public function test_support_user_is_forbidden(): void
    {
        $this->actingAs(User::factory()->support()->create());

        // Livewire 3 zamienia AuthorizationException z mount() na odpowiedź 403.
        Livewire::test(Index::class)->assertForbidden();
    }

    public function test_client_user_is_forbidden(): void
    {
        // Domyślny użytkownik z fabryki = klient (rola User).
        $this->actingAs(User::factory()->create());

        Livewire::test(Index::class)->assertForbidden();
    }
}
