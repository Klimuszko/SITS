<?php

namespace Tests\Feature;

use App\Enums\OrgRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SupportAssignment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Izolacja organizacji w TicketPolicy::view (§ separacja per organizacja).
 */
class TicketPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_requester_can_view_own_ticket(): void
    {
        $organization = Organization::factory()->create();
        $requester = User::factory()->create();
        $ticket = Ticket::factory()->forOrganization($organization)->create([
            'requester_id' => $requester->id,
        ]);

        $this->assertTrue($requester->can('view', $ticket));
    }

    public function test_user_of_another_org_cannot_view_ticket(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $requesterA = User::factory()->create();
        $ticketA = Ticket::factory()->forOrganization($orgA)->create([
            'requester_id' => $requesterA->id,
        ]);

        // Użytkownik (klient) należący wyłącznie do organizacji B.
        $outsider = User::factory()->create();
        OrganizationMembership::create([
            'user_id' => $outsider->id,
            'organization_id' => $orgB->id,
            'role' => OrgRole::User,
            'is_active' => true,
        ]);

        $this->assertFalse($outsider->can('view', $ticketA));
    }

    public function test_assigned_support_can_view_ticket_of_their_org(): void
    {
        $organization = Organization::factory()->create();
        $requester = User::factory()->create();
        $ticket = Ticket::factory()->forOrganization($organization)->create([
            'requester_id' => $requester->id,
        ]);

        $support = User::factory()->support()->create();
        SupportAssignment::create([
            'support_user_id' => $support->id,
            'organization_id' => $organization->id,
            'is_primary' => true,
            'scope' => 'all',
            'is_active' => true,
        ]);

        $this->assertTrue($support->can('view', $ticket));
    }

    public function test_support_of_another_org_cannot_view_ticket(): void
    {
        $orgA = Organization::factory()->create();
        $orgB = Organization::factory()->create();

        $requester = User::factory()->create();
        $ticketA = Ticket::factory()->forOrganization($orgA)->create([
            'requester_id' => $requester->id,
        ]);

        $support = User::factory()->support()->create();
        SupportAssignment::create([
            'support_user_id' => $support->id,
            'organization_id' => $orgB->id,
            'is_primary' => true,
            'scope' => 'all',
            'is_active' => true,
        ]);

        $this->assertFalse($support->can('view', $ticketA));
    }

    public function test_manager_can_view_ticket_of_their_org(): void
    {
        $organization = Organization::factory()->create();
        $requester = User::factory()->create();
        $ticket = Ticket::factory()->forOrganization($organization)->create([
            'requester_id' => $requester->id,
        ]);

        $manager = User::factory()->manager()->create();
        OrganizationMembership::create([
            'user_id' => $manager->id,
            'organization_id' => $organization->id,
            'role' => OrgRole::Manager,
            'is_active' => true,
        ]);

        $this->assertTrue($manager->can('view', $ticket));
    }
}
