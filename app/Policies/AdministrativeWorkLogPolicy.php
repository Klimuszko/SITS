<?php

namespace App\Policies;

use App\Enums\Permission;
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
        if ($user->isStaff()) {
            return $user->hasPermission(Permission::WorkLogsView, $log)
                && $user->reachesOrganization($log->organization_id);
        }

        // Klient: musi mieć work_logs.view w organizacji; widoczność per rekord.
        if (! $user->hasPermission(Permission::WorkLogsView, $log)) {
            return false;
        }

        if ($user->isManagerOf($log->organization_id)) {
            return $log->visible_to_manager;
        }

        return $log->visible_to_user;
    }

    /** Tworzenie prac – personel z uprawnieniem. */
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::WorkLogsCreate);
    }

    public function update(User $user, AdministrativeWorkLog $log): bool
    {
        return $user->hasPermission(Permission::WorkLogsCreate)
            && $user->reachesOrganization($log->organization_id);
    }
}
