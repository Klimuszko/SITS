<?php

namespace App\Policies;

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
        if ($user->isAdminLevel()) {
            return true;
        }

        if ($user->isSupport()) {
            return $user->supportsOrganization($organization->id);
        }

        return $user->isMemberOf($organization->id);
    }

    public function create(User $user): bool
    {
        return $user->isAdminLevel();
    }

    public function update(User $user, Organization $organization): bool
    {
        return $user->isAdminLevel();
    }

    public function archive(User $user, Organization $organization): bool
    {
        return $user->isAdminLevel();
    }

    /** Przypisywanie supportu do organizacji. */
    public function assignSupport(User $user, Organization $organization): bool
    {
        return $user->isAdminLevel();
    }
}
