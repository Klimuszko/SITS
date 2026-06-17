<?php

// Angielski szkielet pod przyszłe tłumaczenie (i18n-ready). Start interfejsu: polski.
return [

    'role' => [
        'super_admin' => 'Super Admin',
        'admin' => 'Administrator',
        'support' => 'Support',
        'manager' => 'Manager',
        'user' => 'User',
    ],

    'org_role' => [
        'user' => 'User',
        'manager' => 'Manager',
    ],

    'organization_type' => [
        'company' => 'Company',
        'branch' => 'Branch',
        'department' => 'Department',
        'other' => 'Other',
    ],

    'status' => [
        'active' => 'Active',
        'inactive' => 'Inactive',
        'archived' => 'Archived',
    ],

    'location_type' => [
        'building' => 'Building',
        'floor' => 'Floor',
        'room' => 'Room',
        'server_room' => 'Server room',
        'rack' => 'Rack',
        'other' => 'Other',
    ],

    'asset_field_type' => [
        'text' => 'Text',
        'number' => 'Number',
        'date' => 'Date',
        'boolean' => 'Yes/No',
        'select' => 'Select',
        'textarea' => 'Text area',
        'ip' => 'IP address',
        'url' => 'URL',
        'email' => 'Email',
        'file' => 'File',
        'relation' => 'Relation',
    ],

    'asset_relation_type' => [
        'depends_on' => 'Depends on',
        'runs_on' => 'Runs on',
        'installed_on' => 'Installed on',
        'connected_to' => 'Connected to',
        'uses_license' => 'Uses license',
        'backed_up_by' => 'Backed up by',
        'related_to' => 'Related to',
    ],

    'ticket_status' => [
        'new' => 'New',
        'in_progress' => 'In progress',
        'waiting_user' => 'Waiting for user',
        'resolved' => 'Resolved',
        'closed' => 'Closed',
        'cancelled' => 'Cancelled',
    ],

    'comment_type' => [
        'public' => 'Public',
        'internal' => 'Internal note',
        'close_request' => 'Close request',
        'system' => 'System',
    ],

    'publication_status' => [
        'draft' => 'Draft',
        'published' => 'Published',
        'archived' => 'Archived',
    ],

    'support_scope' => [
        'tickets' => 'Tickets',
        'assets' => 'Assets',
        'knowledge' => 'Knowledge base',
        'all' => 'Everything',
    ],

    'manager_scope' => [
        'own_unit' => 'Own unit',
        'own_unit_and_children' => 'Own unit + children',
        'whole_company' => 'Whole company',
    ],

    'audit_action' => [
        'organization.created' => 'Organization created',
        'organization.updated' => 'Organization updated',
        'organization.archived' => 'Organization archived',
        'user.created' => 'User created',
        'user.updated' => 'User updated',
        'user.role_changed' => 'User role changed',
        'membership.granted' => 'Organization membership granted',
        'membership.revoked' => 'Organization membership revoked',
        'support.assigned' => 'Support assigned',
        'ticket.created' => 'Ticket created',
        'ticket.status_changed' => 'Ticket status changed',
        'ticket.assigned' => 'Ticket assigned',
        'ticket.commented' => 'Comment added',
        'ticket.internal_note' => 'Internal note added',
        'ticket.close_requested' => 'Ticket close requested',
        'asset.created' => 'Asset created',
        'asset.updated' => 'Asset updated',
        'asset.archived' => 'Asset archived',
        'location.created' => 'Location created',
        'location.updated' => 'Location updated',
        'attachment.added' => 'Attachment added',
        'article.created' => 'Article created',
        'article.updated' => 'Article updated',
        'article.visibility_changed' => 'Article visibility changed',
        'work_log.created' => 'Administrative work log created',
        'work_log.updated' => 'Administrative work log updated',
    ],

];
