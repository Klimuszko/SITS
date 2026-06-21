<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Asset;
use App\Models\User;

class AssetPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Asset $asset): bool
    {
        if ($user->isStaff()) {
            return $user->hasPermission(Permission::AssetsView, $asset)
                && $user->reachesOrganization($asset->organization_id);
        }

        // Klient: musi mieć assets.view w swojej organizacji (manager/user).
        if (! $user->hasPermission(Permission::AssetsView, $asset)) {
            return false;
        }

        // Zasób prywatny – tylko manager organizacji lub przypisani użytkownicy.
        if ($asset->is_private) {
            return $user->isManagerOf($asset->organization_id)
                || $asset->assignedUsers->contains('id', $user->id);
        }

        return true;
    }

    /** Tworzenie zasobów – personel z uprawnieniem (bez kontekstu organizacji). */
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::AssetsCreate);
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->hasPermission(Permission::AssetsUpdate)
            && $user->reachesOrganization($asset->organization_id);
    }

    public function archive(User $user, Asset $asset): bool
    {
        return $this->update($user, $asset);
    }
}
