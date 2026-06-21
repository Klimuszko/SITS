<?php

namespace Database\Seeders;

use App\Enums\OrgRole;
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
        // Profile systemowe z jednego źródła prawdy (AccessProfile::systemDefinitions).
        $byKey = [];
        foreach (AccessProfile::systemDefinitions() as $key => $def) {
            $byKey[$key] = AccessProfile::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $def['name'],
                    'applies_to' => $def['applies_to'],
                    'is_system' => true,
                    'is_active' => true,
                    'permissions' => $def['permissions'],
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
}
