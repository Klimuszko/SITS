<?php

namespace Tests\Feature;

use App\Enums\CommentType;
use App\Livewire\Tickets\Show;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Widok zgłoszenia (Krok 11): wpisy systemowe są widoczne dla klienta,
 * notatki wewnętrzne pozostają ukryte.
 */
class TicketShowSystemCommentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_client_sees_system_comments_but_not_internal_notes(): void
    {
        $organization = Organization::factory()->create();
        $requester = User::factory()->create(); // klient (non-staff), właściciel zgłoszenia
        $ticket = Ticket::factory()->forOrganization($organization)->create([
            'requester_id' => $requester->id,
        ]);

        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
            'type' => CommentType::System,
            'body' => 'Status: Nowy → W trakcie',
        ]);

        TicketComment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $requester->id,
            'type' => CommentType::Internal,
            'body' => 'Wewnętrzna notatka techniczna',
        ]);

        $this->actingAs($requester);

        Livewire::test(Show::class, ['ticket' => $ticket])
            ->assertSee('Status: Nowy → W trakcie')
            ->assertDontSee('Wewnętrzna notatka techniczna');
    }
}
