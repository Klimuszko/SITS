<?php

namespace Tests\Feature;

use App\Livewire\Users\Index;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Usuwanie kont przez admina (soft delete) + kolumna metody logowania na liście.
 */
class UserDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_soft_deletes_user(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $target = User::factory()->create();

        Livewire::test(Index::class)->call('deleteUser', $target->id);

        $this->assertSoftDeleted('users', ['id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.deleted',
            'subject_id' => $target->id,
        ]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        try {
            Livewire::test(Index::class)->call('deleteUser', $admin->id);
        } catch (AuthorizationException) {
            // oczekiwane — UserPolicy::delete blokuje usunięcie własnego konta.
        }

        $this->assertNotSoftDeleted($admin);
    }

    public function test_admin_cannot_delete_super_admin(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $superAdmin = User::factory()->superAdmin()->create();

        try {
            Livewire::test(Index::class)->call('deleteUser', $superAdmin->id);
        } catch (AuthorizationException) {
            // oczekiwane — Admin nie usuwa Super Admina.
        }

        $this->assertNotSoftDeleted($superAdmin);
    }

    public function test_login_method_column_reflects_provider(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        User::factory()->create(['name' => 'Konto Haslo']);
        User::factory()->create(['name' => 'Konto Google'])->forceFill(['oauth_provider' => 'google'])->save();
        User::factory()->create(['name' => 'Konto MS'])->forceFill(['oauth_provider' => 'microsoft'])->save();

        Livewire::test(Index::class)
            ->assertSee('Hasło')
            ->assertSee('Google')
            ->assertSee('Microsoft');
    }
}
