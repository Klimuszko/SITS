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

        // Asercje na dokładnym znaczniku etykiety nav (>Etykieta</span> — etykieta
        // pozycji jest owinięta w <span class="sidebar__text">), żeby nie łapać
        // ewentualnego tekstu treści pulpitu.
        $response->assertOk();
        $response->assertDontSee('>Organizacje</span>', false);
        $response->assertDontSee('>Lokalizacje</span>', false);
        $response->assertDontSee('>Prace administracyjne</span>', false);
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
        $response->assertSee('>Prace administracyjne</span>', false);
        $response->assertDontSee('>Organizacje</span>', false);
        $response->assertDontSee('>Lokalizacje</span>', false);
    }

    public function test_support_sees_all_three_tabs(): void
    {
        $support = User::factory()->support()->create();

        $response = $this->actingAs($support)->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee('>Organizacje</span>', false);
        $response->assertSee('>Lokalizacje</span>', false);
        $response->assertSee('>Prace administracyjne</span>', false);
    }
}
