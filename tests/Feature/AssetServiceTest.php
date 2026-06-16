<?php

namespace Tests\Feature;

use App\Enums\AssetFieldType;
use App\Enums\AssetStatus;
use App\Enums\AuditAction;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\Organization;
use App\Models\User;
use App\Services\AssetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AssetService
    {
        return app(AssetService::class);
    }

    /** @return array{0:User,1:Organization,2:AssetCategory} */
    private function actorOrgCategory(): array
    {
        $actor = User::factory()->support()->create();
        $organization = Organization::factory()->create();
        $category = AssetCategory::factory()->create();

        return [$actor, $organization, $category];
    }

    public function test_create_persists_asset_field_value_history_and_audit(): void
    {
        [$actor, $organization, $category] = $this->actorOrgCategory();

        $field = AssetField::factory()->forCategory($category)->required()->create([
            'key' => 'serial',
            'name' => 'Numer seryjny',
            'type' => AssetFieldType::Text,
        ]);

        $asset = $this->service()->create($actor, [
            'organization_id' => $organization->id,
            'asset_category_id' => $category->id,
            'name' => 'NAS Synology',
            'status' => AssetStatus::Active->value,
        ], [
            $field->id => 'SN-123',
        ]);

        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'name' => 'NAS Synology',
            'organization_id' => $organization->id,
            'created_by' => $actor->id,
        ]);

        $this->assertDatabaseHas('asset_field_values', [
            'asset_id' => $asset->id,
            'asset_field_id' => $field->id,
            'value' => 'SN-123',
        ]);

        $this->assertDatabaseHas('asset_history', [
            'asset_id' => $asset->id,
            'action' => 'created',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::AssetCreated->value,
            'subject_type' => $asset->getMorphClass(),
            'subject_id' => $asset->id,
        ]);
    }

    public function test_create_stores_boolean_field_as_one_or_zero(): void
    {
        [$actor, $organization, $category] = $this->actorOrgCategory();

        $field = AssetField::factory()->forCategory($category)->type(AssetFieldType::Boolean)->create([
            'key' => 'warranty',
        ]);

        $asset = $this->service()->create($actor, [
            'organization_id' => $organization->id,
            'asset_category_id' => $category->id,
            'name' => 'Laptop',
        ], [
            $field->id => true,
        ]);

        $this->assertDatabaseHas('asset_field_values', [
            'asset_id' => $asset->id,
            'asset_field_id' => $field->id,
            'value' => '1',
        ]);
    }

    public function test_update_writes_history_for_changed_field_and_audit(): void
    {
        [$actor, $organization, $category] = $this->actorOrgCategory();

        $field = AssetField::factory()->forCategory($category)->create([
            'key' => 'serial',
            'type' => AssetFieldType::Text,
        ]);

        $asset = $this->service()->create($actor, [
            'organization_id' => $organization->id,
            'asset_category_id' => $category->id,
            'name' => 'Stary serwer',
        ], [
            $field->id => 'OLD',
        ]);

        $this->service()->update($asset, [
            'name' => 'Nowy serwer',
        ], [
            $field->id => 'NEW',
        ], $actor);

        $this->assertSame('Nowy serwer', $asset->fresh()->name);

        $this->assertDatabaseHas('asset_field_values', [
            'asset_id' => $asset->id,
            'asset_field_id' => $field->id,
            'value' => 'NEW',
        ]);

        // Wiersz historii dla zmienionego pola dynamicznego.
        $this->assertDatabaseHas('asset_history', [
            'asset_id' => $asset->id,
            'action' => 'field_updated',
            'field' => 'serial',
            'old_value' => 'OLD',
            'new_value' => 'NEW',
        ]);

        // Wiersz historii dla zmienionej kolumny rdzeniowej.
        $this->assertDatabaseHas('asset_history', [
            'asset_id' => $asset->id,
            'action' => 'field_updated',
            'field' => 'name',
            'old_value' => 'Stary serwer',
            'new_value' => 'Nowy serwer',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::AssetUpdated->value,
            'subject_type' => $asset->getMorphClass(),
            'subject_id' => $asset->id,
        ]);
    }

    public function test_update_without_changes_writes_no_audit(): void
    {
        [$actor, $organization, $category] = $this->actorOrgCategory();

        $asset = $this->service()->create($actor, [
            'organization_id' => $organization->id,
            'asset_category_id' => $category->id,
            'name' => 'Bez zmian',
        ], []);

        $this->service()->update($asset, ['name' => 'Bez zmian'], [], $actor);

        $this->assertSame(0, \App\Models\AuditLog::where('action', AuditAction::AssetUpdated->value)
            ->where('subject_id', $asset->id)
            ->count());
    }

    public function test_archive_sets_status_archived_and_audits(): void
    {
        [$actor, $organization, $category] = $this->actorOrgCategory();

        $asset = Asset::factory()
            ->forOrganization($organization)
            ->forCategory($category)
            ->status(AssetStatus::Active)
            ->create();

        $this->service()->archive($asset, $actor);

        $this->assertSame(AssetStatus::Archived, $asset->fresh()->status);

        $this->assertDatabaseHas('asset_history', [
            'asset_id' => $asset->id,
            'action' => 'field_updated',
            'field' => 'status',
            'new_value' => AssetStatus::Archived->value,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => AuditAction::AssetArchived->value,
            'subject_type' => $asset->getMorphClass(),
            'subject_id' => $asset->id,
        ]);
    }
}
