<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pobieranie plików archiwum audytu (audit-RRRR-MM.log) z dysku prywatnego.
 *
 * Pliki tworzy komenda audit:prune (archiwizacja przed usunięciem starych wpisów).
 * Dostęp jak do dziennika audytu (bramka view-audit = isAdminLevel). Nazwa pliku jest
 * ŚCIŚLE walidowana wzorcem audit-RRRR-MM.log — żaden inny plik ani path traversal
 * (../) nie przejdzie; serwujemy wyłącznie z katalogu audit-archive/.
 */
class AuditArchiveController extends Controller
{
    public function download(string $file): StreamedResponse
    {
        $this->authorize('view-audit');

        abort_unless((bool) preg_match('/^audit-\d{4}-\d{2}\.log$/', $file), 404);

        $path = 'audit-archive/'.$file;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $file);
    }
}
