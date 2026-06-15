<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Kontrola dostępu do załączników (§27, §30).
 * Użytkownik jednej organizacji NIGDY nie pobierze pliku innej organizacji.
 * Pobieranie odbywa się wyłącznie przez kontroler z autoryzacją tej polityki.
 */
class AttachmentPolicy
{
    public function download(User $user, Attachment $attachment): bool
    {
        if ($user->isAdminLevel()) {
            return true;
        }

        // Jeśli załącznik jest powiązany z obiektem domenowym – deleguj do jego polityki.
        $parent = $attachment->attachable;

        if ($parent instanceof Model) {
            return $user->can('view', $parent);
        }

        // Załącznik bezpośrednio przy organizacji – sprawdź dostęp do organizacji.
        if ($attachment->organization_id) {
            if ($user->isSupport()) {
                return $user->supportsOrganization($attachment->organization_id);
            }

            return $user->isMemberOf($attachment->organization_id);
        }

        return false;
    }

    public function delete(User $user, Attachment $attachment): bool
    {
        return $user->isAdminLevel()
            || ($user->isStaff() && $this->download($user, $attachment))
            || $attachment->uploaded_by === $user->id;
    }
}
