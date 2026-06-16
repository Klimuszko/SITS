<?php

namespace Tests\Feature;

use App\Enums\AuditAction;
use App\Enums\CommentType;
use App\Enums\TicketStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\SupportAssignment;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TicketServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): TicketService
    {
        return app(TicketService::class);
    }

    /** @return array{0:User,1:Organization} */
    private function requesterAndOrg(): array
    {
        $organization = Organization::factory()->create();
        $requester = User::factory()->create();

        return [$requester, $organization];
    }

    public function test_create_assigns_primary_support_and_sets_status_assigned(): void
    {
        [$requester, $organization] = $this->requesterAndOrg();
        $support = User::factory()->support()->create();

        SupportAssignment::create([
            'support_user_id' => $support->id,
            'organization_id' => $organization->id,
            'is_primary' => true,
            'scope' => 'all',
            'is_active' => true,
        ]);

        $ticket = $this->service()->create($requester, $organization, [
            'title' => 'Drukarka nie działa',
            'description' => 'Brak wydruku.',
        ]);

        $this->assertSame($support->id, $ticket->assigned_support_id);
        $this->assertSame(TicketStatus::Assigned, $ticket->status);
    }

    public function test_create_uses_default_support_user_when_set(): void
    {
        $support = User::factory()->support()->create();
        $organization = Organization::factory()->create(['default_support_user_id' => $support->id]);
        $requester = User::factory()->create();

        $ticket = $this->service()->create($requester, $organization, [
            'title' => 'Problem z VPN',
            'description' => 'Nie łączy.',
        ]);

        $this->assertSame($support->id, $ticket->assigned_support_id);
        $this->assertSame(TicketStatus::Assigned, $ticket->status);
    }

    public function test_create_auto_assigns_to_super_admin_default_support(): void
    {
        $superAdmin = User::factory()->superAdmin()->create();
        $organization = Organization::factory()->create(['default_support_user_id' => $superAdmin->id]);
        $requester = User::factory()->create();

        $ticket = $this->service()->create($requester, $organization, [
            'title' => 'Awaria u właściciela-supporta',
            'description' => 'SuperAdmin jako domyślny support organizacji.',
        ]);

        $this->assertSame($superAdmin->id, $ticket->assigned_support_id);
        $this->assertSame(TicketStatus::Assigned, $ticket->status);
    }

    public function test_create_without_support_sets_status_new(): void
    {
        [$requester, $organization] = $this->requesterAndOrg();

        $ticket = $this->service()->create($requester, $organization, [
            'title' => 'Brak supportu',
            'description' => 'Organizacja bez przypisanego supportu.',
        ]);

        $this->assertNull($ticket->assigned_support_id);
        $this->assertSame(TicketStatus::New, $ticket->status);
    }

    public function test_create_generates_number_in_expected_format(): void
    {
        [$requester, $organization] = $this->requesterAndOrg();

        $ticket = $this->service()->create($requester, $organization, [
            'title' => 'Numeracja',
            'description' => 'Format numeru.',
        ]);

        $this->assertMatchesRegularExpression('/^T-\d{4}-\d{6}$/', $ticket->number);
    }

    public function test_create_writes_ticket_created_audit_row(): void
    {
        [$requester, $organization] = $this->requesterAndOrg();

        $ticket = $this->service()->create($requester, $organization, [
            'title' => 'Audyt',
            'description' => 'Sprawdzenie audytu.',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::TicketCreated->value,
            'subject_type' => $ticket->getMorphClass(),
            'subject_id' => $ticket->id,
        ]);
    }

    public function test_create_retries_on_duplicate_number_and_audits_once(): void
    {
        [$requester, $organization] = $this->requesterAndOrg();

        $year = now()->year;

        // Zajmujemy numer, na który nextNumber() trafi za pierwszym razem.
        Ticket::factory()->forOrganization($organization)->create([
            'number' => "T-{$year}-000005",
            'requester_id' => $requester->id,
        ]);

        // Partial mock wymusza dwie kolizje (000005) i wolny numer (000006).
        $service = \Mockery::mock(TicketService::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        $service->shouldReceive('nextNumber')->andReturn(
            "T-{$year}-000005",
            "T-{$year}-000005",
            "T-{$year}-000006",
        );

        $ticket = $service->create($requester, $organization, [
            'title' => 'Kolizja numeru',
            'description' => 'Ponowienie po duplikacie.',
        ]);

        // Usługa odzyskuje sprawność i zwraca kolejny wolny numer.
        $this->assertSame("T-{$year}-000006", $ticket->number);

        // Tylko zarodkowy + utworzony – brak osieroconych wierszy z nieudanych prób.
        $this->assertDatabaseCount('tickets', 2);

        // Audyt zapisany dokładnie raz (brak podwójnego zapisu przy ponowieniu).
        $this->assertSame(1, AuditLog::where('action', AuditAction::TicketCreated->value)
            ->where('subject_id', $ticket->id)
            ->count());
    }

    public function test_request_close_creates_close_request_comment_and_audit_row(): void
    {
        [$requester, $organization] = $this->requesterAndOrg();
        $ticket = Ticket::factory()->forOrganization($organization)->create([
            'requester_id' => $requester->id,
        ]);

        $comment = $this->service()->requestClose($ticket, $requester, 'Sprawa załatwiona.');

        $this->assertSame(CommentType::CloseRequest, $comment->type);
        $this->assertSame('Sprawa załatwiona.', $comment->body);
        $this->assertDatabaseHas('ticket_comments', [
            'id' => $comment->id,
            'ticket_id' => $ticket->id,
            'type' => CommentType::CloseRequest->value,
        ]);

        $audit = AuditLog::where('action', 'ticket.close_requested')
            ->where('subject_type', $ticket->getMorphClass())
            ->where('subject_id', $ticket->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('ticket.close_requested', AuditAction::TicketCloseRequested->value);
    }

    public function test_change_status_to_resolved_sets_resolved_at(): void
    {
        [$requester, $organization] = $this->requesterAndOrg();
        $ticket = Ticket::factory()->forOrganization($organization)->create([
            'requester_id' => $requester->id,
        ]);

        $this->assertNull($ticket->resolved_at);

        $this->service()->changeStatus($ticket, TicketStatus::Resolved);

        $this->assertSame(TicketStatus::Resolved, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->resolved_at);
    }

    public function test_change_status_to_closed_sets_closed_at(): void
    {
        [$requester, $organization] = $this->requesterAndOrg();
        $ticket = Ticket::factory()->forOrganization($organization)->create([
            'requester_id' => $requester->id,
        ]);

        $this->assertNull($ticket->closed_at);

        $this->service()->changeStatus($ticket, TicketStatus::Closed);

        $this->assertSame(TicketStatus::Closed, $ticket->fresh()->status);
        $this->assertNotNull($ticket->fresh()->closed_at);
    }
}
