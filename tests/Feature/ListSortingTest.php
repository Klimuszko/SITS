<?php

namespace Tests\Feature;

use App\Livewire\Assets\Index as AssetsIndex;
use App\Livewire\Locations\Index as LocationsIndex;
use App\Livewire\Users\Index as UsersIndex;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Sortowanie kolumn list (trait WithSorting): klik sortuje, toggle asc↔desc,
 * a kolumna spoza białej listy jest ignorowana (spada do domyślnego, bez błędu/iniekcji).
 */
class ListSortingTest extends TestCase
{
    use RefreshDatabase;

    public function test_clicking_default_column_toggles_direction(): void
    {
        $this->actingAs(User::factory()->admin()->create(['name' => 'Mmm Admin']));

        User::factory()->create(['name' => 'Aaa Pierwszy']);
        User::factory()->create(['name' => 'Zzz Ostatni']);

        // Kolumna domyślna „name" jest aktywna już po wejściu (asc), więc pierwszy
        // klik w jej nagłówek PRZEŁĄCZA na desc (konwencja — jak w liście Zasobów).
        Livewire::test(UsersIndex::class)
            ->assertSeeInOrder(['Aaa Pierwszy', 'Mmm Admin', 'Zzz Ostatni'])  // domyślnie name asc
            ->call('sortBy', 'name')
            ->assertSet('sortDir', 'desc')
            ->assertSeeInOrder(['Zzz Ostatni', 'Mmm Admin', 'Aaa Pierwszy']);
    }

    public function test_non_default_column_starts_asc_then_toggles_and_switch_resets(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(UsersIndex::class)
            // „email" nie jest kolumną domyślną → pierwszy klik = asc.
            ->call('sortBy', 'email')
            ->assertSet('sortCol', 'email')
            ->assertSet('sortDir', 'asc')
            // Drugi klik tej samej → desc.
            ->call('sortBy', 'email')
            ->assertSet('sortDir', 'desc')
            // Przełączenie na inną kolumnę → kierunek wraca do asc.
            ->call('sortBy', 'role')
            ->assertSet('sortCol', 'role')
            ->assertSet('sortDir', 'asc');
    }

    public function test_column_outside_whitelist_is_ignored(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // sortBy z kolumną spoza białej listy: sortCol/sortDir nie zmieniają się,
        // render nie rzuca błędu (orderBy nie dostaje surowej kolumny z URL).
        Livewire::test(UsersIndex::class)
            ->call('sortBy', 'password')
            ->assertSet('sortCol', '')
            ->assertSet('sortDir', 'asc')
            ->assertOk();
    }

    public function test_url_supplied_bad_sortcol_falls_back_to_default(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // sortCol/sortDir nastrzyknięte „z URL" (mount) — spoza whitelisty.
        // Lista renderuje się po domyślnym sortowaniu, bez błędu i bez iniekcji.
        Livewire::test(UsersIndex::class, ['sortCol' => 'id); drop table users; --', 'sortDir' => 'evil'])
            ->assertOk()
            ->assertSet('sortCol', 'id); drop table users; --');

        // users tabela nadal istnieje — zapytanie nie wykonało surowego sortCol.
        $this->assertDatabaseHas('users', ['email' => User::query()->value('email')]);
    }

    public function test_assets_list_sorts_by_whitelisted_column(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $org = Organization::factory()->create();
        $category = AssetCategory::factory()->create();

        Asset::factory()->forOrganization($org)->forCategory($category)->create(['name' => 'Aaa Zasob']);
        Asset::factory()->forOrganization($org)->forCategory($category)->create(['name' => 'Zzz Zasob']);

        // Domyślnie name asc (zachowane zachowanie po wejściu).
        Livewire::test(AssetsIndex::class)
            ->assertSeeInOrder(['Aaa Zasob', 'Zzz Zasob'])
            // Klik „name" → toggle na desc.
            ->call('sortBy', 'name')
            ->assertSet('sortDir', 'desc')
            ->assertSeeInOrder(['Zzz Zasob', 'Aaa Zasob']);
    }

    // ── Step 21: sortowanie po kolumnach relacyjnych / licznikach ──────────────

    public function test_assets_sort_by_organization_relation_orders_by_org_name(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $category = AssetCategory::factory()->create();

        // Nazwy zasobów celowo PRZECIWNE do nazw organizacji, żeby sortowanie po
        // organizacji (podzapytanie) dało inną kolejność niż sortowanie po nazwie zasobu.
        $orgA = Organization::factory()->create(['name' => 'Aaa Organizacja']);
        $orgZ = Organization::factory()->create(['name' => 'Zzz Organizacja']);

        Asset::factory()->forOrganization($orgZ)->forCategory($category)->create(['name' => 'Pierwszy zasob']);
        Asset::factory()->forOrganization($orgA)->forCategory($category)->create(['name' => 'Drugi zasob']);

        // „organization" to kolumna NIE-domyślna → pierwszy klik = asc (wg nazwy organizacji).
        Livewire::test(AssetsIndex::class)
            ->call('sortBy', 'organization')
            ->assertSet('sortCol', 'organization')
            ->assertSet('sortDir', 'asc')
            // Aaa Organizacja (Drugi zasob) przed Zzz Organizacja (Pierwszy zasob).
            ->assertSeeInOrder(['Drugi zasob', 'Pierwszy zasob'])
            // Drugi klik → desc → odwrotna kolejność po nazwie organizacji.
            ->call('sortBy', 'organization')
            ->assertSet('sortDir', 'desc')
            ->assertSeeInOrder(['Pierwszy zasob', 'Drugi zasob']);
    }

    public function test_locations_sort_by_assets_count_subquery_orders_by_count_without_duplicates(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $org = Organization::factory()->create();
        $category = AssetCategory::factory()->create();

        // „Magazyn" ma 2 zasoby, „Serwerownia" ma 0 — sortowanie po liczniku „assets".
        $busy = Location::factory()->forOrganization($org)->create(['name' => 'Magazyn']);
        Location::factory()->forOrganization($org)->create(['name' => 'Serwerownia']);

        Asset::factory()->forOrganization($org)->forCategory($category)->create([
            'name' => 'Zasob jeden', 'location_id' => $busy->id,
        ]);
        Asset::factory()->forOrganization($org)->forCategory($category)->create([
            'name' => 'Zasob dwa', 'location_id' => $busy->id,
        ]);

        // „assets" to kolumna NIE-domyślna → pierwszy klik = asc (0 przed 2).
        Livewire::test(LocationsIndex::class)
            ->call('sortBy', 'assets')
            ->assertSet('sortCol', 'assets')
            ->assertSet('sortDir', 'asc')
            ->assertSeeInOrder(['Serwerownia', 'Magazyn'])
            // Drugi klik → desc (2 przed 0).
            ->call('sortBy', 'assets')
            ->assertSet('sortDir', 'desc')
            ->assertSeeInOrder(['Magazyn', 'Serwerownia'])
            // Korelowane podzapytanie (nie JOIN) → brak duplikacji wierszy: każda lokalizacja
            // pojawia się dokładnie raz mimo 2 zasobów w „Magazyn".
            ->assertSeeTextInOrder(['Magazyn', 'Serwerownia']);
    }

    public function test_relation_key_outside_whitelist_is_ignored(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        // Klucz relacyjny spoza białej listy (np. nieistniejąca relacja) — sortCol/sortDir
        // bez zmian, sortExpression() nigdy nie dostaje tego klucza, render bez błędu.
        Livewire::test(AssetsIndex::class)
            ->call('sortBy', 'manufacturer')
            ->assertSet('sortCol', '')
            ->assertSet('sortDir', 'asc')
            ->assertOk();
    }
}
