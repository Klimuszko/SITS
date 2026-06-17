<?php

namespace Tests\Feature;

use App\Enums\OrgRole;
use App\Enums\PublicationStatus;
use App\Enums\SupportScope;
use App\Livewire\WorkLogs\Report;
use App\Models\AdministrativeWorkLog;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkLogReportTest extends TestCase
{
    use RefreshDatabase;

    private function manager(Organization $org): User
    {
        $manager = User::factory()->manager()->create();
        $manager->memberships()->create([
            'organization_id' => $org->id,
            'role' => OrgRole::Manager->value,
            'manager_scope' => 'whole_company',
            'is_active' => true,
        ]);

        return $manager->fresh();
    }

    private function member(Organization $org): User
    {
        $user = User::factory()->create();
        $user->memberships()->create([
            'organization_id' => $org->id,
            'role' => OrgRole::User->value,
            'is_active' => true,
        ]);

        return $user->fresh();
    }

    private function supportFor(Organization $org): User
    {
        $support = User::factory()->support()->create();
        $support->supportAssignments()->create([
            'organization_id' => $org->id,
            'is_primary' => false,
            'scope' => SupportScope::All->value,
            'is_active' => true,
        ]);

        return $support->fresh();
    }

    public function test_support_total_sums_all_published_logs_in_month(): void
    {
        $org = Organization::factory()->create();
        $support = $this->supportFor($org);
        $this->actingAs($support);

        $month = now()->startOfMonth();

        AdministrativeWorkLog::factory()->forOrganization($org)
            ->performedAt($month->copy()->addDays(2))->durationMinutes(60)->create();
        AdministrativeWorkLog::factory()->forOrganization($org)
            ->performedAt($month->copy()->addDays(5))->durationMinutes(30)->create();

        // Poza miesiącem — nie liczona.
        AdministrativeWorkLog::factory()->forOrganization($org)
            ->performedAt($month->copy()->subMonth())->durationMinutes(120)->create();

        // Draft — nie liczona (tylko published).
        AdministrativeWorkLog::factory()->forOrganization($org)
            ->performedAt($month->copy()->addDays(3))->durationMinutes(45)
            ->status(PublicationStatus::Draft)->create();

        Livewire::test(Report::class)
            ->set('organization_id', $org->id)
            ->set('month', $month->format('Y-m'))
            ->assertSet('organization_id', $org->id)
            ->assertViewHas('count', 2)
            ->assertViewHas('totalMinutes', 90)
            ->assertViewHas('totalFormatted', '1h 30m');
    }

    public function test_manager_total_excludes_logs_hidden_for_manager(): void
    {
        $org = Organization::factory()->create();
        $manager = $this->manager($org);
        $this->actingAs($manager);

        $month = now()->startOfMonth();

        // Widoczna dla managera — liczona.
        AdministrativeWorkLog::factory()->forOrganization($org)
            ->visibleToManager(true)->durationMinutes(60)
            ->performedAt($month->copy()->addDays(1))->create();

        // Ukryta dla managera — NIE liczona.
        AdministrativeWorkLog::factory()->forOrganization($org)
            ->visibleToManager(false)->durationMinutes(999)
            ->performedAt($month->copy()->addDays(2))->create();

        Livewire::test(Report::class)
            ->set('organization_id', $org->id)
            ->set('month', $month->format('Y-m'))
            ->assertViewHas('count', 1)
            ->assertViewHas('totalMinutes', 60);
    }

    public function test_user_total_includes_only_visible_to_user_logs(): void
    {
        $org = Organization::factory()->create();
        $user = $this->member($org);
        $this->actingAs($user);

        $month = now()->startOfMonth();

        // Widoczna dla usera — liczona.
        AdministrativeWorkLog::factory()->forOrganization($org)
            ->visibleToUser(true)->durationMinutes(25)
            ->performedAt($month->copy()->addDays(1))->create();

        // Widoczna tylko dla managera — NIE liczona dla usera.
        AdministrativeWorkLog::factory()->forOrganization($org)
            ->visibleToManager(true)->visibleToUser(false)->durationMinutes(500)
            ->performedAt($month->copy()->addDays(2))->create();

        Livewire::test(Report::class)
            ->set('organization_id', $org->id)
            ->set('month', $month->format('Y-m'))
            ->assertViewHas('count', 1)
            ->assertViewHas('totalMinutes', 25);
    }

    public function test_client_picking_unrelated_org_gets_denied_and_no_data(): void
    {
        $homeOrg = Organization::factory()->create();
        $foreignOrg = Organization::factory()->create();

        $user = $this->member($homeOrg);
        $this->actingAs($user);

        $month = now()->startOfMonth();

        AdministrativeWorkLog::factory()->forOrganization($foreignOrg)
            ->visibleToUser(true)->durationMinutes(300)
            ->performedAt($month->copy()->addDays(1))->create();

        // Wymuszamy organizację, do której użytkownik nie należy.
        Livewire::test(Report::class)
            ->set('organization_id', $foreignOrg->id)
            ->set('month', $month->format('Y-m'))
            ->assertViewHas('denied', true)
            ->assertViewHas('count', 0)
            ->assertViewHas('totalMinutes', 0);
    }
}
