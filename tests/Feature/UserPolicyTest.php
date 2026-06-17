<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Autoryzacja modułu użytkowników (Step 5 — SECURITY-SENSITIVE).
 *
 * Uwaga: testy celowo używają AKTORA = Admin (nie Super Admin), bo `Gate::before`
 * przepuszcza Super Admina przez wszystkie uprawnienia — to UserPolicy musi być
 * faktycznie wykonana, żeby guardy (ochrona Super Admina i konta własnego) zadziałały.
 */
class UserPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_create_and_update_a_normal_user(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(); // rola domyślna = user

        $this->assertTrue($admin->can('viewAny', User::class));
        $this->assertTrue($admin->can('view', $target));
        $this->assertTrue($admin->can('create', User::class));
        $this->assertTrue($admin->can('update', $target));
        $this->assertTrue($admin->can('delete', $target));
    }

    public function test_admin_cannot_update_a_super_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->assertFalse($admin->can('update', $superAdmin));
    }

    public function test_admin_cannot_delete_a_super_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $superAdmin = User::factory()->superAdmin()->create();

        $this->assertFalse($admin->can('delete', $superAdmin));
    }

    public function test_admin_cannot_delete_self(): void
    {
        $admin = User::factory()->admin()->create();

        $this->assertFalse($admin->can('delete', $admin));
    }

    public function test_support_user_cannot_access_users_module(): void
    {
        $support = User::factory()->support()->create();
        $target = User::factory()->create();

        $this->assertFalse($support->can('viewAny', User::class));
        $this->assertFalse($support->can('create', User::class));
        $this->assertFalse($support->can('update', $target));
        $this->assertFalse($support->can('delete', $target));
    }

    public function test_client_user_cannot_access_users_module(): void
    {
        $client = User::factory()->create(); // rola domyślna = user (klient)
        $target = User::factory()->create();

        $this->assertFalse($client->can('viewAny', User::class));
        $this->assertFalse($client->can('update', $target));
    }

    public function test_super_admin_passes_all_via_gate_before(): void
    {
        // Pełna kontrola: Gate::before przepuszcza Super Admina nawet tam,
        // gdzie UserPolicy zwróciłaby false dla Admina.
        $superActor = User::factory()->superAdmin()->create();
        $otherSuperAdmin = User::factory()->superAdmin()->create();

        $this->assertTrue($superActor->can('update', $otherSuperAdmin));
        $this->assertTrue($superActor->can('viewAny', User::class));
    }
}
