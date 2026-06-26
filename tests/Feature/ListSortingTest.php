<?php

namespace Tests\Feature;

use App\Livewire\Assets\Index as AssetsIndex;
use App\Livewire\Users\Index as UsersIndex;
use App\Models\Asset;
use App\Models\AssetCategory;
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
}
