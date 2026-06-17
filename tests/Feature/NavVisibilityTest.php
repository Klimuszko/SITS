<?php

namespace Tests\Feature;

use App\Enums\ManagerScope;
use App\Enums\OrgRole;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_plain_user_does_not_see_staff_or_manager_nav_tabs(): void
    {
        $user = User::factory()->create(['role' => Role::User]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        // Asercje na dokładnym znaczniku linku nav (>Etykieta</a>), żeby nie łapać
        // ewentualnego tekstu treści pulpitu.
        $response->assertOk();
        $response->assertDontSee('>Organizacje</a>', false);
        $response->assertDontSee('>Lokalizacje</a>', false);
        $response->assertDontSee('>Prace adm.</a>', false);
    }

    public function test_manager_sees_prace_adm_but_not_organizacje_or_lokalizacje(): void
    {
        // Klient globalnie (Role::User) z aktywnym członkostwem managera w organizacji.
        $manager = User::factory()->create(['role' => Role::User]);
        $org = Organization::factory()->create();
        $manager->memberships()->create([
            'organization_id' => $org->id,
            'role' => OrgRole::Manager->value,
            'manager_scope' => ManagerScope::OwnUnit->value,
            'is_active' => true,
        ]);

        $response = $this->actingAs($manager)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('>Prace adm.</a>', false);
        $response->assertDontSee('>Organizacje</a>', false);
        $response->assertDontSee('>Lokalizacje</a>', false);
    }

    public function test_support_sees_all_three_tabs(): void
    {
        $support = User::factory()->support()->create();

        $response = $this->actingAs($support)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('>Organizacje</a>', false);
        $response->assertSee('>Lokalizacje</a>', false);
        $response->assertSee('>Prace adm.</a>', false);
    }
}
