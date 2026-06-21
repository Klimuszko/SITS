<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Location;
use App\Models\User;

class LocationPolicy
{
    /** Listy są zawsze dostępne – zakres ograniczamy zapytaniem (scoping). */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Location $location): bool
    {
        if ($user->isStaff()) {
            return $user->hasPermission(Permission::LocationsView, $location)
                && $user->reachesOrganization($location->organization_id);
        }

        // Klient: członek z locations.view w tej organizacji.
        return $user->hasPermission(Permission::LocationsView, $location);
    }

    /** Tworzenie lokalizacji – personel z uprawnieniem. */
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::LocationsManage);
    }

    public function update(User $user, Location $location): bool
    {
        return $user->hasPermission(Permission::LocationsManage)
            && $user->reachesOrganization($location->organization_id);
    }

    public function archive(User $user, Location $location): bool
    {
        return $this->update($user, $location);
    }
}
