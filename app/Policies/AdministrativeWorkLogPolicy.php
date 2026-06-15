<?php

namespace App\Policies;

use App\Models\AdministrativeWorkLog;
use App\Models\User;

class AdministrativeWorkLogPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AdministrativeWorkLog $log): bool
    {
        if ($user->isAdminLevel()) {
            return true;
        }

        if ($user->isSupport()) {
            return $user->supportsOrganization($log->organization_id);
        }

        if ($user->isManagerOf($log->organization_id)) {
            return $log->visible_to_manager;
        }

        // User – tylko prace oznaczone jako widoczne dla userów w jego organizacji.
        return $user->isMemberOf($log->organization_id) && $log->visible_to_user;
    }

    /** Tworzenie/edycja prac – personel obsługujący organizację. */
    public function create(User $user): bool
    {
        return $user->isAdminLevel() || $user->isSupport();
    }

    public function update(User $user, AdministrativeWorkLog $log): bool
    {
        return $user->isAdminLevel()
            || ($user->isSupport() && $user->supportsOrganization($log->organization_id));
    }
}
