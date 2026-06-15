<?php

namespace Database\Seeders;

use App\Enums\AssetRelationType;
use App\Enums\ManagerScope;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Enums\OrgRole;
use App\Enums\PublicationStatus;
use App\Enums\Role;
use App\Enums\SupportScope;
use App\Models\AdministrativeWorkLog;
use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetFieldValue;
use App\Models\AssetRelation;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeArticleVisibility;
use App\Models\Location;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\SupportAssignment;
use App\Models\Ticket;
use App\Models\User;
use App\Services\TicketService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        // ----------------------------- Użytkownicy -----------------------------
        $support = $this->user('support@serwisit.local', 'Tomasz Wiśniewski (Support)', Role::Support);
        $manager = $this->user('manager@pako.local', 'Anna Kowalska (Manager)', Role::Manager);
        $user = $this->user('user@pako.local', 'Jan Nowak (Użytkownik)', Role::User);

        // ----------------------------- Organizacje -----------------------------
        $pako = Organization::firstOrCreate(
            ['name' => 'PAKO Engineering'],
            [
                'type' => OrganizationType::Company,
                'status' => OrganizationStatus::Active,
                'nip' => '8511234567',
                'address' => 'ul. Przykładowa 1, 70-001 Szczecin',
                'contact_email' => 'kontakt@pako.local',
                'contact_phone' => '+48 91 000 00 00',
                'default_support_user_id' => $support->id,
            ],
        );

        $szczecin = Organization::firstOrCreate(
            ['name' => 'Oddział Szczecin'],
            [
                'type' => OrganizationType::Branch,
                'parent_id' => $pako->id,
                'status' => OrganizationStatus::Active,
                'default_support_user_id' => $support->id,
            ],
        );

        $warszawa = Organization::firstOrCreate(
            ['name' => 'Oddział Warszawa'],
            [
                'type' => OrganizationType::Branch,
                'parent_id' => $pako->id,
                'status' => OrganizationStatus::Active,
                'default_support_user_id' => $support->id,
            ],
        );

        // ------------------------ Przypisania supportu -------------------------
        foreach ([$pako, $szczecin, $warszawa] as $org) {
            SupportAssignment::updateOrCreate(
                ['support_user_id' => $support->id, 'organization_id' => $org->id],
                ['is_primary' => true, 'scope' => SupportScope::All->value, 'is_active' => true],
            );
        }

        // ------------------------- Członkostwa klientów ------------------------
        OrganizationMembership::firstOrCreate(
            ['user_id' => $manager->id, 'organization_id' => $pako->id],
            ['role' => OrgRole::Manager->value, 'manager_scope' => ManagerScope::OwnUnit->value, 'is_active' => true],
        );
        OrganizationMembership::firstOrCreate(
            ['user_id' => $user->id, 'organization_id' => $pako->id],
            ['role' => OrgRole::User->value, 'is_active' => true],
        );

        // ------------------------------ Lokalizacje ----------------------------
        $biuro = Location::firstOrCreate(
            ['organization_id' => $szczecin->id, 'name' => 'Biuro Szczecin', 'parent_id' => null],
            ['type' => 'building', 'status' => 'active'],
        );
        $serwerownia = Location::firstOrCreate(
            ['organization_id' => $szczecin->id, 'name' => 'Serwerownia', 'parent_id' => $biuro->id],
            ['type' => 'server_room', 'status' => 'active'],
        );

        // -------------------------------- Zasoby --------------------------------
        $nasCategory = AssetCategory::where('key', 'nas')->first();
        $vmCategory = AssetCategory::where('key', 'maszyna-wirtualna')->first();

        if ($nasCategory) {
            $nas = Asset::firstOrCreate(
                ['organization_id' => $pako->id, 'name' => 'Synology RS1221+'],
                [
                    'location_id' => $serwerownia->id,
                    'asset_category_id' => $nasCategory->id,
                    'status' => 'active',
                    'created_by' => $support->id,
                    'notes' => 'Główny NAS firmy PAKO Engineering.',
                ],
            );

            $this->setFieldValue($nas, 'producent', 'Synology');
            $this->setFieldValue($nas, 'model', 'RS1221+');
            $this->setFieldValue($nas, 'ip', '192.168.1.10');
            $this->setFieldValue($nas, 'liczba_dyskow', '4');
            $this->setFieldValue($nas, 'status_backupu', 'OK');

            if ($vmCategory) {
                $vm = Asset::firstOrCreate(
                    ['organization_id' => $pako->id, 'name' => 'Linux VM (Ansys)'],
                    [
                        'location_id' => $serwerownia->id,
                        'asset_category_id' => $vmCategory->id,
                        'parent_asset_id' => $nas->id,
                        'status' => 'active',
                        'created_by' => $support->id,
                    ],
                );

                AssetRelation::firstOrCreate(
                    ['asset_id' => $vm->id, 'related_asset_id' => $nas->id, 'type' => AssetRelationType::RunsOn->value],
                    ['note' => 'Maszyna wirtualna uruchomiona na NAS.'],
                );
            }
        }

        // -------------------------------- Ticket --------------------------------
        if (! Ticket::where('requester_id', $user->id)->exists()) {
            app(TicketService::class)->create($user, $pako, [
                'title' => 'Brak dostępu do zasobu sieciowego',
                'description' => 'Od rana nie mogę połączyć się z dyskiem sieciowym NAS. Proszę o pomoc.',
            ]);
        }

        // ----------------------------- Baza wiedzy ------------------------------
        $article = KnowledgeArticle::firstOrCreate(
            ['slug' => 'jak-zglosic-awarie'],
            [
                'title' => 'Jak zgłosić awarię?',
                'body' => '<h2>Zgłaszanie awarii</h2><p>Aby zgłosić awarię, kliknij <strong>Nowe zgłoszenie</strong> i opisz problem.</p><div class="warning">Pamiętaj o dołączeniu zrzutu ekranu.</div>',
                'status' => PublicationStatus::Published->value,
                'author_id' => $support->id,
                'published_at' => now(),
            ],
        );

        KnowledgeArticleVisibility::firstOrCreate(
            ['knowledge_article_id' => $article->id, 'visibility_type' => 'organization', 'organization_id' => $pako->id],
        );

        // ------------------------- Prace administracyjne -----------------------
        AdministrativeWorkLog::firstOrCreate(
            ['organization_id' => $pako->id, 'title' => 'Sprawdzono backup NAS'],
            [
                'description' => 'Wykonano kontrolę zadań backupu na NAS Synology. Status: OK.',
                'work_type' => 'Backup',
                'performed_by' => $support->id,
                'performed_at' => now()->subDays(2),
                'duration_minutes' => 30,
                'visible_to_manager' => true,
                'visible_to_user' => false,
                'status' => PublicationStatus::Published->value,
            ],
        );
    }

    protected function user(string $email, string $name, Role $role): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make('Haslo12345!'),
                'role' => $role,
                'is_active' => true,
                'email_verified_at' => now(),
            ],
        );
    }

    protected function setFieldValue(Asset $asset, string $fieldKey, string $value): void
    {
        $field = AssetField::where('asset_category_id', $asset->asset_category_id)
            ->where('key', $fieldKey)->first();

        if (! $field) {
            return;
        }

        AssetFieldValue::updateOrCreate(
            ['asset_id' => $asset->id, 'asset_field_id' => $field->id],
            ['value' => $value],
        );
    }
}
