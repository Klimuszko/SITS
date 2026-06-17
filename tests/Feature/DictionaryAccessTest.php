<?php

namespace Tests\Feature;

use App\Livewire\Dictionaries\KnowledgeCategories;
use App\Livewire\Dictionaries\TicketCategories;
use App\Livewire\Dictionaries\TicketPriorities;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DictionaryAccessTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string,array{0:class-string}> */
    public static function components(): array
    {
        return [
            'ticket categories' => [TicketCategories::class],
            'ticket priorities' => [TicketPriorities::class],
            'knowledge categories' => [KnowledgeCategories::class],
        ];
    }

    /** @param  class-string  $component */
    #[DataProvider('components')]
    public function test_admin_can_open_each_dictionary(string $component): void
    {
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test($component)->assertOk();
    }

    /** @param  class-string  $component */
    #[DataProvider('components')]
    public function test_support_user_is_forbidden(string $component): void
    {
        $this->actingAs(User::factory()->support()->create());

        Livewire::test($component)->assertForbidden();
    }

    /** @param  class-string  $component */
    #[DataProvider('components')]
    public function test_client_user_is_forbidden(string $component): void
    {
        // Domyślny użytkownik z fabryki = klient (rola User).
        $this->actingAs(User::factory()->create());

        Livewire::test($component)->assertForbidden();
    }
}
