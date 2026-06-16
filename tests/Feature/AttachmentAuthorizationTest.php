<?php

namespace Tests\Feature;

use App\Enums\OrgRole;
use App\Models\Attachment;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SupportAssignment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Pobieranie załączników odbywa się wyłącznie przez kontroler z autoryzacją (§27, §30).
 * Użytkownik jednej organizacji nigdy nie pobierze pliku innej organizacji.
 */
class AttachmentAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function attachmentOnTicketOfOrg(Organization $organization, User $requester): Attachment
    {
        Storage::fake('local');

        $ticket = Ticket::factory()->forOrganization($organization)->create([
            'requester_id' => $requester->id,
        ]);

        $path = 'attachments/'.$ticket->id.'/plik.txt';
        Storage::disk('local')->put($path, 'zawartość');

        return Attachment::create([
            'organization_id' => $organization->id,
            'attachable_type' => $ticket->getMorphClass(),
            'attachable_id' => $ticket->id,
            'original_name' => 'plik.txt',
            'stored_name' => 'plik.txt',
            'path' => $path,
            'mime_type' => 'text/plain',
            'size' => 9,
            'uploaded_by' => $requester->id,
        ]);
    }

    public function test_owner_can_download_attachment(): void
    {
        $organization = Organization::factory()->create();
        $requester = User::factory()->create();
        $attachment = $this->attachmentOnTicketOfOrg($organization, $requester);

        $this->actingAs($requester)
            ->get(route('attachments.download', $attachment))
            ->assertOk();
    }

    public function test_user_of_another_org_is_denied(): void
    {
        $organization = Organization::factory()->create();
        $requester = User::factory()->create();
        $attachment = $this->attachmentOnTicketOfOrg($organization, $requester);

        $outsiderOrg = Organization::factory()->create();
        $outsider = User::factory()->create();
        OrganizationMembership::create([
            'user_id' => $outsider->id,
            'organization_id' => $outsiderOrg->id,
            'role' => OrgRole::User,
            'is_active' => true,
        ]);

        $this->actingAs($outsider)
            ->get(route('attachments.download', $attachment))
            ->assertForbidden();
    }

    public function test_assigned_support_can_download_attachment(): void
    {
        $organization = Organization::factory()->create();
        $requester = User::factory()->create();
        $attachment = $this->attachmentOnTicketOfOrg($organization, $requester);

        $support = User::factory()->support()->create();
        SupportAssignment::create([
            'support_user_id' => $support->id,
            'organization_id' => $organization->id,
            'is_primary' => true,
            'scope' => 'all',
            'is_active' => true,
        ]);

        $this->actingAs($support)
            ->get(route('attachments.download', $attachment))
            ->assertOk();
    }

    public function test_support_of_another_org_is_denied(): void
    {
        $organization = Organization::factory()->create();
        $requester = User::factory()->create();
        $attachment = $this->attachmentOnTicketOfOrg($organization, $requester);

        $otherOrg = Organization::factory()->create();
        $support = User::factory()->support()->create();
        SupportAssignment::create([
            'support_user_id' => $support->id,
            'organization_id' => $otherOrg->id,
            'is_primary' => true,
            'scope' => 'all',
            'is_active' => true,
        ]);

        $this->actingAs($support)
            ->get(route('attachments.download', $attachment))
            ->assertForbidden();
    }
}
