<?php

namespace Tests\Feature;

use App\Enums\ManagerScope;
use App\Enums\OrgRole;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\User;
use App\Support\Navigation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NavigationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<array<string,mixed>>  $categories
     * @return list<string>
     */
    private function keys(array $categories): array
    {
        return array_column($categories, 'key');
    }

    /**
     * @param  list<array<string,mixed>>  $categories
     * @return list<string>
     */
    private function labelsIn(array $categories, string $key): array
    {
        foreach ($categories as $category) {
            if ($category['key'] === $key) {
                return array_column($category['items'], 'label');
            }
        }

        return [];
    }

    public function test_plain_user_sees_only_base_categories(): void
    {
        $user = User::factory()->create(['role' => Role::User]);

        $categories = Navigation::categoriesFor($user);
        $keys = $this->keys($categories);

        // Widoczne: pulpit, wsparcie, zasoby.
        $this->assertSame(['pulpit', 'wsparcie', 'zasoby'], $keys);

        // Kategorie wymagające personelu/uprawnień są nieobecne.
        $this->assertNotContains('klienci', $keys);
        $this->assertNotContains('praca', $keys);
        $this->assertNotContains('administracja', $keys);

        // Zasoby bez Lokalizacji (te tylko dla personelu).
        $this->assertSame(['Zasoby'], $this->labelsIn($categories, 'zasoby'));

        // Pulpit nie ma nagłówka (label === null).
        $this->assertNull($categories[0]['label']);
    }

    public function test_manager_sees_praca_but_not_klienci_or_administracja(): void
    {
        $manager = User::factory()->create(['role' => Role::User]);
        $org = Organization::factory()->create();
        $manager->memberships()->create([
            'organization_id' => $org->id,
            'role' => OrgRole::Manager->value,
            'manager_scope' => ManagerScope::OwnUnit->value,
            'is_active' => true,
        ]);

        $categories = Navigation::categoriesFor($manager->fresh());
        $keys = $this->keys($categories);

        $this->assertContains('praca', $keys);
        $this->assertSame(['Prace administracyjne'], $this->labelsIn($categories, 'praca'));

        // Manager klienta nie jest personelem ani adminem.
        $this->assertNotContains('klienci', $keys);
        $this->assertNotContains('administracja', $keys);
        $this->assertSame(['Zasoby'], $this->labelsIn($categories, 'zasoby'));
    }

    public function test_support_sees_klienci_organizacje_and_lokalizacje_but_not_admin(): void
    {
        $support = User::factory()->support()->create();

        $categories = Navigation::categoriesFor($support);
        $keys = $this->keys($categories);

        // Personel: zasoby z Lokalizacjami, kategoria Klienci z Organizacjami, Praca.
        $this->assertContains('zasoby', $keys);
        $this->assertSame(['Zasoby', 'Lokalizacje'], $this->labelsIn($categories, 'zasoby'));

        $this->assertContains('klienci', $keys);
        $this->assertSame(['Organizacje'], $this->labelsIn($categories, 'klienci'));

        $this->assertContains('praca', $keys);

        // Support nie ma uprawnień admina => brak Administracji i Użytkowników.
        $this->assertNotContains('administracja', $keys);
        $this->assertNotContains('Użytkownicy', $this->labelsIn($categories, 'klienci'));
    }

    public function test_admin_with_abilities_sees_all_categories_and_admin_items(): void
    {
        // Admin => isAdminLevel() => manage-users / manage-categories / view-audit.
        $admin = User::factory()->admin()->create();

        $categories = Navigation::categoriesFor($admin);
        $keys = $this->keys($categories);

        $this->assertSame(
            ['pulpit', 'wsparcie', 'zasoby', 'klienci', 'praca', 'administracja'],
            $keys
        );

        $this->assertSame(['Organizacje', 'Użytkownicy'], $this->labelsIn($categories, 'klienci'));
        $this->assertSame(['Słowniki', 'Audyt', 'Ustawienia'], $this->labelsIn($categories, 'administracja'));
    }
}
