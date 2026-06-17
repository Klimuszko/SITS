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
    case UserUpdated = 'user.updated';
    case UserRoleChanged = 'user.role_changed';
    case MembershipGranted = 'membership.granted';
    case MembershipRevoked = 'membership.revoked';
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
    case ArticleCreated = 'article.created';
    case ArticleUpdated = 'article.updated';
    case ArticleVisibilityChanged = 'article.visibility_changed';
    case WorkLogCreated = 'work_log.created';
    case WorkLogUpdated = 'work_log.updated';

    public function label(): string
    {
        return __('enums.audit_action.'.$this->value);
    }
}
