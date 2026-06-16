<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\Attachment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;

/**
 * Zapis załączników na dysk prywatny + rekord w bazie z kontrolą dostępu
 * (organization_id). Pobieranie odbywa się przez AttachmentController + AttachmentPolicy.
 */
class AttachmentService
{
    public function store(
        UploadedFile $file,
        Model $attachable,
        ?int $organizationId,
        ?int $userId,
    ): Attachment {
        // Katalog per organizacja na dysku prywatnym (storage/app/private/attachments/...).
        $dir = 'attachments/'.($organizationId ?? 'common');
        $path = $file->store($dir, 'local');

        $attachment = Attachment::create([
            'organization_id' => $organizationId,
            'attachable_type' => $attachable->getMorphClass(),
            'attachable_id' => $attachable->getKey(),
            'original_name' => $file->getClientOriginalName(),
            'stored_name' => basename($path),
            'path' => $path,
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'uploaded_by' => $userId,
        ]);

        AuditLogger::log(AuditAction::AttachmentAdded, $attachment, null, [
            'original_name' => $attachment->original_name,
            'attachable' => $attachable->getMorphClass().'#'.$attachable->getKey(),
        ]);

        return $attachment;
    }
}
