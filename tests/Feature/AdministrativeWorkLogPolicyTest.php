<?php

namespace Tests\Feature;

use App\Enums\OrgRole;
use App\Enums\PublicationStatus;
use App\Enums\SupportScope;
use App\Models\AdministrativeWorkLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Autoryzacja prac administracyjnych (Step 6 — SECURITY-SENSITIVE).
 *
 * Sedno modułu: widoczność per rola + flagi visible_to_manager / visible_to_user.
 * Testy używają aktora innego niż Super Admin, bo Gate::before przepuszcza Super Admina
 * przez wszystko — to AdministrativeWorkLogPolicy musi faktycznie zadziałać.
 */
class AdministrativeWorkLogPolicyTest extends TestCase
{
    use RefreshDatabase;

    /** Dodaje aktywne członkostwo (klient) i zwraca świeżego użytkownika z załadowanymi relacjami. */
    private function withMembership(User $user, Organization $org, OrgRole $role): User
    {
        $user->memberships()->create([
            'organization_id' => $org->id,
            'role' => $role->value,
            'manager_scope' => $role === OrgRole::Manager ? 'whole_company' : null,
            'is_active' => true,
        ]);

        return $user->fresh();
    }

    /** Przypisuje supporta do organizacji i zwraca świeżego użytkownika. */
    private function withSupportAssignment(User $support, Organization $org): User
    {
        $support->supportAssignments()->create([
            'organization_id' => $org->id,
            'is_primary' => false,
            'scope' => SupportScope::All->value,
            'is_active' => true,
        ]);

        return $support->fresh();
    }

    public function test_admin_sees_any_log(): void
    {
        $admin = User::factory()->admin()->create();
        $log = AdministrativeWorkLog::factory()
            ->visibleToManager(false)
            ->visibleToUser(false)
            ->create();

        $this->assertTrue($admin->can('view', $log));
    }

    public function test_manager_sees_visible_to_manager_log_but_not_hidden_one(): void
    {
        $org = Organization::factory()->create();
        $manager = User::factory()->manager()->create();
        $manager = $this->withMembership($manager, $org, OrgRole::Manager);

        $visible = AdministrativeWorkLog::factory()
            ->forOrganization($org)
            ->visibleToManager(true)
            ->create();

        $hidden = AdministrativeWorkLog::factory()
            ->forOrganization($org)
            ->visibleToManager(false)
            ->create();

        $this->assertTrue($manager->can('view', $visible));
        $this->assertFalse($manager->can('view', $hidden));
    }

    public function test_plain_user_sees_only_visible_to_user_logs(): void
    {
        $org = Organization::factory()->create();
        $user = User::factory()->create(); // rola globalna = user (klient)
        $user = $this->withMembership($user, $org, OrgRole::User);

        $forUser = AdministrativeWorkLog::factory()
            ->forOrganization($org)
            ->visibleToUser(true)
            ->create();

        // Widoczna dla managera, ale NIE dla usera → user nie widzi.
        $forManagerOnly = AdministrativeWorkLog::factory()
            ->forOrganization($org)
            ->visibleToManager(true)
            ->visibleToUser(false)
            ->create();

        $this->assertTrue($user->can('view', $forUser));
        $this->assertFalse($user->can('view', $forManagerOnly));
    }

    public function test_support_sees_only_logs_of_supported_orgs(): void
    {
        $supportedOrg = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();

        $support = User::factory()->support()->create();
        $support = $this->withSupportAssignment($support, $supportedOrg);

        $supportedLog = AdministrativeWorkLog::factory()->forOrganization($supportedOrg)->create();
        $otherLog = AdministrativeWorkLog::factory()->forOrganization($otherOrg)->create();

        $this->assertTrue($support->can('view', $supportedLog));
        $this->assertFalse($support->can('view', $otherLog));
    }

    public function test_client_cannot_create(): void
    {
        $client = User::factory()->create(); // klient
        $this->assertFalse($client->can('create', AdministrativeWorkLog::class));

        $manager = User::factory()->manager()->create();
        $this->assertFalse($manager->can('create', AdministrativeWorkLog::class));
    }

    public function test_staff_can_create(): void
    {
        $admin = User::factory()->admin()->create();
        $support = User::factory()->support()->create();

        $this->assertTrue($admin->can('create', AdministrativeWorkLog::class));
        $this->assertTrue($support->can('create', AdministrativeWorkLog::class));
    }

    public function test_non_member_user_cannot_view_published_log(): void
    {
        $org = Organization::factory()->create();
        $stranger = User::factory()->create(); // brak członkostwa w org

        $log = AdministrativeWorkLog::factory()
            ->forOrganization($org)
            ->visibleToUser(true)
            ->status(PublicationStatus::Published)
            ->create();

        $this->assertFalse($stranger->can('view', $log));
    }
}
