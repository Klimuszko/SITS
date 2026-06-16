<?php

namespace App\Enums;

/**
 * Słownik akcji audytu (§28). Kolumna audit_logs.action jest typu string,
 * więc dopuszczamy też inne wartości, ale udokumentowane akcje trzymamy tutaj.
 */
enum AuditAction: string
{
    case OrganizationCreated = 'organization.created';
    case OrganizationUpdated = 'organization.updated';
    case OrganizationArchived = 'organization.archived';

    case UserCreated = 'user.created';
    case UserRoleChanged = 'user.role_changed';
    case SupportAssigned = 'support.assigned';

    case TicketCreated = 'ticket.created';
    case TicketStatusChanged = 'ticket.status_changed';
    case TicketAssigned = 'ticket.assigned';
    case TicketCommented = 'ticket.commented';
    case TicketInternalNote = 'ticket.internal_note';
    case TicketCloseRequested = 'ticket.close_requested';

    case AssetCreated = 'asset.created';
    case AssetUpdated = 'asset.updated';
    case AssetArchived = 'asset.archived';

    case LocationCreated = 'location.created';
    case LocationUpdated = 'location.updated';

    case AttachmentAdded = 'attachment.added';
    case ArticleVisibilityChanged = 'article.visibility_changed';
    case WorkLogCreated = 'work_log.created';

    public function label(): string
    {
        return __('enums.audit_action.'.$this->value);
    }
}
