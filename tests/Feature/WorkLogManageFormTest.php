<?php

namespace Tests\Feature;

use App\Enums\PublicationStatus;
use App\Enums\SupportScope;
use App\Livewire\WorkLogs\ManageForm;
use App\Models\Asset;
use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WorkLogManageFormTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_support_creates_log_in_supported_org_with_audit(): void
    {
        $org = Organization::factory()->create();
        $support = $this->supportFor($org);
        $this->actingAs($support);

        Livewire::test(ManageForm::class)
            ->set('organization_id', $org->id)
            ->set('title', 'Przegląd serwera')
            ->set('description', 'Aktualizacja systemu i kontrola kopii zapasowych.')
            ->set('work_type', 'Przegląd')
            ->set('performed_by', $support->id)
            ->set('performed_at', now()->format('Y-m-d\TH:i'))
            ->set('duration_minutes', 90)
            ->set('visible_to_manager', true)
            ->set('visible_to_user', false)
            ->set('status', PublicationStatus::Published->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('administrative_work_logs', [
            'organization_id' => $org->id,
            'title' => 'Przegląd serwera',
            'performed_by' => $support->id,
            'duration_minutes' => 90,
            'visible_to_manager' => true,
            'visible_to_user' => false,
            'status' => PublicationStatus::Published->value,
        ]);

        $log = \App\Models\AdministrativeWorkLog::where('title', 'Przegląd serwera')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'work_log.created',
            'subject_type' => $log->getMorphClass(),
            'subject_id' => $log->id,
        ]);
    }

    public function test_client_cannot_open_create_form(): void
    {
        $client = User::factory()->create(); // klient
        $this->actingAs($client);

        Livewire::test(ManageForm::class)
            ->assertForbidden();
    }

    public function test_support_cannot_create_for_unsupported_org(): void
    {
        $supportedOrg = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $support = $this->supportFor($supportedOrg);
        $this->actingAs($support);

        Livewire::test(ManageForm::class)
            ->set('organization_id', $otherOrg->id) // organizacja spoza zakresu supporta
            ->set('title', 'Próba')
            ->set('description', 'Opis')
            ->set('performed_by', $support->id)
            ->set('performed_at', now()->format('Y-m-d\TH:i'))
            ->set('status', PublicationStatus::Published->value)
            ->call('save')
            ->assertHasErrors('organization_id');

        $this->assertDatabaseMissing('administrative_work_logs', ['title' => 'Próba']);
    }

    public function test_location_from_another_org_is_rejected(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $support = $this->supportFor($org);
        $this->actingAs($support);

        $foreignLocation = Location::factory()->forOrganization($otherOrg)->create();

        Livewire::test(ManageForm::class)
            ->set('organization_id', $org->id)
            ->set('location_id', $foreignLocation->id)
            ->set('title', 'Praca z obcą lokalizacją')
            ->set('description', 'Opis')
            ->set('performed_by', $support->id)
            ->set('performed_at', now()->format('Y-m-d\TH:i'))
            ->set('status', PublicationStatus::Published->value)
            ->call('save')
            ->assertHasErrors('location_id');

        $this->assertDatabaseMissing('administrative_work_logs', ['title' => 'Praca z obcą lokalizacją']);
    }

    public function test_asset_from_another_org_is_rejected(): void
    {
        $org = Organization::factory()->create();
        $otherOrg = Organization::factory()->create();
        $support = $this->supportFor($org);
        $this->actingAs($support);

        $foreignAsset = Asset::factory()->forOrganization($otherOrg)->create();

        Livewire::test(ManageForm::class)
            ->set('organization_id', $org->id)
            ->set('asset_id', $foreignAsset->id)
            ->set('title', 'Praca z obcym zasobem')
            ->set('description', 'Opis')
            ->set('performed_by', $support->id)
            ->set('performed_at', now()->format('Y-m-d\TH:i'))
            ->set('status', PublicationStatus::Published->value)
            ->call('save')
            ->assertHasErrors('asset_id');

        $this->assertDatabaseMissing('administrative_work_logs', ['title' => 'Praca z obcym zasobem']);
    }

    public function test_performer_must_be_active_staff(): void
    {
        $org = Organization::factory()->create();
        $support = $this->supportFor($org);
        $this->actingAs($support);

        // Klient nie może być wykonawcą pracy administracyjnej.
        $client = User::factory()->create();

        Livewire::test(ManageForm::class)
            ->set('organization_id', $org->id)
            ->set('title', 'Praca z klientem jako wykonawcą')
            ->set('description', 'Opis')
            ->set('performed_by', $client->id)
            ->set('performed_at', now()->format('Y-m-d\TH:i'))
            ->set('status', PublicationStatus::Published->value)
            ->call('save')
            ->assertHasErrors('performed_by');

        $this->assertDatabaseMissing('administrative_work_logs', ['title' => 'Praca z klientem jako wykonawcą']);
    }

    public function test_support_updates_log_and_writes_updated_audit(): void
    {
        $org = Organization::factory()->create();
        $support = $this->supportFor($org);
        $this->actingAs($support);

        $log = \App\Models\AdministrativeWorkLog::factory()
            ->forOrganization($org)
            ->performedBy($support)
            ->create(['title' => 'Stary tytuł']);

        Livewire::test(ManageForm::class, ['administrativeWorkLog' => $log])
            ->set('title', 'Nowy tytuł')
            ->call('save')
            ->assertHasNoErrors();

        $log->refresh();
        $this->assertSame('Nowy tytuł', $log->title);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'work_log.updated',
            'subject_type' => $log->getMorphClass(),
            'subject_id' => $log->id,
        ]);
    }
}
