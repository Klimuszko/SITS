<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Organization;
use App\Models\User;

class OrganizationPolicy
{
    /** Listy są zawsze dostępne – zakres ograniczamy zapytaniem (scoping). */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Organization $organization): bool
    {
        if ($user->isStaff()) {
            return $user->hasPermission(Permission::OrganizationsView, $organization)
                && $user->reachesOrganization($organization->id);
        }

        // Klient: członek z organizations.view.
        return $user->hasPermission(Permission::OrganizationsView, $organization);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::OrganizationsManage);
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->hasPermission(Permission::OrganizationsManage);
    }

    public function archive(User $user, Organization $organization): bool
    {
        return $user->hasPermission(Permission::OrganizationsManage);
    }

    /** Przypisywanie supportu do organizacji. */
    public function assignSupport(User $user, Organization $organization): bool
    {
        return $user->hasPermission(Permission::OrganizationsManage);
    }
}
