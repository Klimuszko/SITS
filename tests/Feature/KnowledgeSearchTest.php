<?php

namespace Tests\Feature;

use App\Enums\PublicationStatus;
use App\Livewire\Knowledge\Index;
use App\Models\KnowledgeArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Wyszukiwanie w bazie wiedzy musi być niewrażliwe na wielkość liter:
 * "vpn", "VPN", "Vpn" powinny znaleźć artykuł "Konfiguracja VPN".
 */
class KnowledgeSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_is_case_insensitive(): void
    {
        $admin = User::factory()->admin()->create();
        KnowledgeArticle::factory()->create([
            'title' => 'Konfiguracja VPN',
            'status' => PublicationStatus::Published->value,
            'published_at' => now(),
        ]);
        $this->actingAs($admin);

        // Małe litery muszą znaleźć tytuł z wielkimi (przy case-sensitive LIKE by nie znalazło).
        Livewire::test(Index::class)
            ->set('search', 'vpn')
            ->assertSee('Konfiguracja VPN');

        // Wielkie litery również.
        Livewire::test(Index::class)
            ->set('search', 'VPN')
            ->assertSee('Konfiguracja VPN');

        // Mieszane też.
        Livewire::test(Index::class)
            ->set('search', 'Vpn')
            ->assertSee('Konfiguracja VPN');
    }
}
