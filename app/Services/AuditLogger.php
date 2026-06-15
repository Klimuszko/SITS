<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

/**
 * Centralny zapis zdarzeń audytu (§28).
 */
class AuditLogger
{
    public static function log(
        AuditAction|string $action,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
    ): AuditLog {
        return AuditLog::create([
            'user_id' => Auth::id(),
            'action' => $action instanceof AuditAction ? $action->value : $action,
            'subject_type' => $subject ? $subject->getMorphClass() : null,
            'subject_id' => $subject?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => Request::ip(),
            'user_agent' => substr((string) Request::userAgent(), 0, 1000),
        ]);
    }
}
