<?php

namespace App\Policies;

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
        if ($user->isAdminLevel()) {
            return true;
        }

        if ($user->isSupport()) {
            return $user->supportsOrganization($asset->organization_id);
        }

        // Manager zawsze widzi wszystkie zasoby swojej organizacji.
        if ($user->isManagerOf($asset->organization_id)) {
            return true;
        }

        // User: musi być członkiem organizacji.
        if (! $user->isMemberOf($asset->organization_id)) {
            return false;
        }

        // Zasób prywatny – tylko przypisani użytkownicy.
        if ($asset->is_private) {
            return $asset->assignedUsers->contains('id', $user->id);
        }

        return true;
    }

    /** Tworzenie/edycja/archiwizacja zasobów – personel obsługujący organizację. */
    public function create(User $user): bool
    {
        return $user->isAdminLevel() || $user->isSupport();
    }

    public function update(User $user, Asset $asset): bool
    {
        return $user->isAdminLevel()
            || ($user->isSupport() && $user->supportsOrganization($asset->organization_id));
    }

    public function archive(User $user, Asset $asset): bool
    {
        return $this->update($user, $asset);
    }
}
