<?php

namespace Database\Seeders;

use App\Enums\OrgRole;
use App\Enums\Permission as P;
use App\Enums\Role;
use App\Models\AccessProfile;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Profile systemowe (1:1 z dotychczasowymi rolami) + backfill istniejących danych.
 * Idempotentny: updateOrCreate po kluczu, backfill tylko tam, gdzie access_profile_id
 * jeszcze nie ustawiony. Zestawy uprawnień odwzorowują obecne zachowanie Policy/Gate
 * (warstwa „CO"); zakres per organizacja pozostaje w Policy.
 */
class AccessProfileSeeder extends Seeder
{
    public function run(): void
    {
        $all = P::values();

        $support = $this->keys([
            P::TicketsView, P::TicketsComment, P::TicketsManage, P::TicketsInternalNote, P::TicketsClose,
            P::AssetsView, P::AssetsCreate, P::AssetsUpdate, P::AssetsArchive,
            P::LocationsView, P::LocationsManage,
            P::OrganizationsView,
            P::WorkLogsView, P::WorkLogsCreate, P::WorkLogsReport,
            P::KnowledgeView, P::KnowledgeCreate, P::KnowledgeManage,
        ]);

        $user = $this->keys([
            P::TicketsComment,
            P::AssetsView, P::LocationsView, P::OrganizationsView, P::WorkLogsView, P::KnowledgeView,
        ]);

        // Manager = user + podgląd wszystkich zgłoszeń organizacji.
        $manager = array_values(array_unique(array_merge($user, $this->keys([P::TicketsView]))));

        $profiles = [
            [AccessProfile::SUPER_ADMIN, 'Super Admin', AccessProfile::APPLIES_STAFF, $all],
            [AccessProfile::ADMIN, 'Administrator', AccessProfile::APPLIES_STAFF, $all],
            [AccessProfile::SUPPORT, 'Support', AccessProfile::APPLIES_STAFF, $support],
            [AccessProfile::MANAGER, 'Manager', AccessProfile::APPLIES_CLIENT, $manager],
            [AccessProfile::USER, 'Użytkownik', AccessProfile::APPLIES_CLIENT, $user],
        ];

        $byKey = [];
        foreach ($profiles as [$key, $name, $appliesTo, $permissions]) {
            $byKey[$key] = AccessProfile::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $name,
                    'applies_to' => $appliesTo,
                    'is_system' => true,
                    'is_active' => true,
                    'permissions' => $permissions,
                ],
            );
        }

        // Backfill personelu: globalny profil wg Role (klient czerpie z członkostwa).
        User::query()->whereNull('access_profile_id')->get()->each(function (User $u) use ($byKey) {
            $key = match ($u->role) {
                Role::SuperAdmin => AccessProfile::SUPER_ADMIN,
                Role::Admin => AccessProfile::ADMIN,
                Role::Support => AccessProfile::SUPPORT,
                default => null,
            };

            if ($key !== null) {
                $u->forceFill(['access_profile_id' => $byKey[$key]->id])->save();
            }
        });

        // Backfill członkostw: profil klienta wg OrgRole.
        OrganizationMembership::query()->whereNull('access_profile_id')->get()->each(function (OrganizationMembership $m) use ($byKey) {
            $key = $m->role === OrgRole::Manager ? AccessProfile::MANAGER : AccessProfile::USER;
            $m->forceFill(['access_profile_id' => $byKey[$key]->id])->save();
        });
    }

    /**
     * @param  list<P>  $permissions
     * @return list<string>
     */
    private function keys(array $permissions): array
    {
        return array_map(fn (P $p) => $p->value, $permissions);
    }
}
