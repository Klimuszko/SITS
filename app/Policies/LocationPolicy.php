<?php

namespace App\Policies;

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
        if ($user->isAdminLevel()) {
            return true;
        }

        if ($user->isSupport()) {
            return $user->supportsOrganization($location->organization_id);
        }

        return $user->isMemberOf($location->organization_id);
    }

    /** Tworzenie/edycja/archiwizacja lokalizacji – personel obsługujący organizację. */
    public function create(User $user): bool
    {
        return $user->isAdminLevel() || $user->isSupport();
    }

    public function update(User $user, Location $location): bool
    {
        return $user->isAdminLevel()
            || ($user->isSupport() && $user->supportsOrganization($location->organization_id));
    }

    public function archive(User $user, Location $location): bool
    {
        return $this->update($user, $location);
    }
}
