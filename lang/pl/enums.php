<?php

return [

    'role' => [
        'super_admin' => 'Super Admin',
        'admin' => 'Administrator',
        'support' => 'Support',
        'manager' => 'Manager',
        'user' => 'Użytkownik',
    ],

    'org_role' => [
        'user' => 'Użytkownik',
        'manager' => 'Manager',
    ],

    'organization_type' => [
        'company' => 'Firma',
        'branch' => 'Oddział',
        'department' => 'Dział',
        'other' => 'Inny',
    ],

    'status' => [
        'active' => 'Aktywna',
        'inactive' => 'Nieaktywna',
        'archived' => 'Archiwalna',
    ],

    'location_type' => [
        'building' => 'Budynek',
        'floor' => 'Piętro',
        'room' => 'Pokój',
        'server_room' => 'Serwerownia',
        'rack' => 'Rack',
        'other' => 'Inne',
    ],

    'asset_field_type' => [
        'text' => 'Tekst',
        'number' => 'Liczba',
        'date' => 'Data',
        'boolean' => 'Tak/Nie',
        'select' => 'Lista wyboru',
        'textarea' => 'Tekst wieloliniowy',
        'file' => 'Plik',
        'relation' => 'Relacja',
    ],

    'asset_relation_type' => [
        'depends_on' => 'Zależy od',
        'runs_on' => 'Działa na',
        'installed_on' => 'Zainstalowane na',
        'connected_to' => 'Połączone z',
        'uses_license' => 'Używa licencji',
        'backed_up_by' => 'Backupowane przez',
        'related_to' => 'Powiązane z',
    ],

    'ticket_status' => [
        'new' => 'Nowy',
        'assigned' => 'Przypisany',
        'in_progress' => 'W trakcie',
        'waiting_user' => 'Oczekuje na użytkownika',
        'resolved' => 'Rozwiązany',
        'closed' => 'Zamknięty',
        'cancelled' => 'Anulowany',
    ],

    'comment_type' => [
        'public' => 'Publiczny',
        'internal' => 'Notatka wewnętrzna',
        'close_request' => 'Prośba o zamknięcie',
    ],

    'publication_status' => [
        'draft' => 'Szkic',
        'published' => 'Opublikowany',
        'archived' => 'Archiwalny',
    ],

    'support_scope' => [
        'tickets' => 'Tickety',
        'assets' => 'Zasoby',
        'knowledge' => 'Baza wiedzy',
        'all' => 'Wszystko',
    ],

    'manager_scope' => [
        'own_unit' => 'Własna jednostka',
        'own_unit_and_children' => 'Własna jednostka + podrzędne',
        'whole_company' => 'Cała firma',
    ],

    'audit_action' => [
        'organization.created' => 'Utworzono organizację',
        'organization.updated' => 'Zmieniono organizację',
        'organization.archived' => 'Zarchiwizowano organizację',
        'user.created' => 'Utworzono użytkownika',
        'user.updated' => 'Zmieniono użytkownika',
        'user.role_changed' => 'Zmieniono rolę użytkownika',
        'membership.granted' => 'Przyznano członkostwo w organizacji',
        'membership.revoked' => 'Cofnięto członkostwo w organizacji',
        'support.assigned' => 'Przypisano support',
        'ticket.created' => 'Utworzono ticket',
        'ticket.status_changed' => 'Zmieniono status ticketu',
        'ticket.assigned' => 'Przypisano ticket',
        'ticket.commented' => 'Dodano komentarz',
        'ticket.internal_note' => 'Dodano notatkę wewnętrzną',
        'ticket.close_requested' => 'Poproszono o zamknięcie ticketu',
        'asset.created' => 'Utworzono zasób',
        'asset.updated' => 'Zmieniono zasób',
        'asset.archived' => 'Zarchiwizowano zasób',
        'location.created' => 'Utworzono lokalizację',
        'location.updated' => 'Zmieniono lokalizację',
        'attachment.added' => 'Dodano załącznik',
        'article.visibility_changed' => 'Zmieniono widoczność artykułu',
        'work_log.created' => 'Utworzono pracę administracyjną',
    ],

];
