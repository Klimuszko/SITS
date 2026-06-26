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

    public function test_clicking_header_sorts_and_toggles_direction(): void
    {
        $this->actingAs(User::factory()->admin()->create(['name' => 'Mmm Admin']));

        User::factory()->create(['name' => 'Aaa Pierwszy']);
        User::factory()->create(['name' => 'Zzz Ostatni']);

        // Klik „name" → asc: Aaa przed Zzz.
        Livewire::test(UsersIndex::class)
            ->call('sortBy', 'name')
            ->assertSet('sortDir', 'asc')
            ->assertSeeInOrder(['Aaa Pierwszy', 'Zzz Ostatni']);

        // Drugi klik tej samej kolumny → desc: Zzz przed Aaa.
        Livewire::test(UsersIndex::class)
            ->call('sortBy', 'name')
            ->call('sortBy', 'name')
            ->assertSet('sortDir', 'desc')
            ->assertSeeInOrder(['Zzz Ostatni', 'Aaa Pierwszy']);
    }

    public function test_sort_by_other_column_resets_direction_to_asc(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(UsersIndex::class)
            ->call('sortBy', 'name')
            ->call('sortBy', 'name')   // name → desc
            ->assertSet('sortDir', 'desc')
            ->call('sortBy', 'email')  // inna kolumna → wraca do asc
            ->assertSet('sortCol', 'email')
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
