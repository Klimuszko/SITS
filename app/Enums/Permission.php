<?php

namespace App\Enums;

/**
 * Katalog uprawnień aplikacji (warstwa „CO" modelu dostępu). Definiowany w kodzie
 * (związany z funkcjami), nie w bazie — profile dostępu (AccessProfile) trzymają
 * tylko listę kluczy. Zakres „GDZIE" (per organizacja) rozstrzygają Policy/scope,
 * niezależnie od tego katalogu.
 *
 * Uprawnienia wyłącznie dla Super Admina (force-delete, manage-system) celowo NIE
 * są tutaj — Super Admin przechodzi przez Gate::before, więc nie są przypisywalne.
 */
enum Permission: string
{
    // Zgłoszenia
    case TicketsView = 'tickets.view';
    case TicketsComment = 'tickets.comment';
    case TicketsManage = 'tickets.manage';
    case TicketsInternalNote = 'tickets.internal_note';
    case TicketsClose = 'tickets.close';

    // Zasoby
    case AssetsView = 'assets.view';
    case AssetsCreate = 'assets.create';
    case AssetsUpdate = 'assets.update';
    case AssetsArchive = 'assets.archive';

    // Lokalizacje
    case LocationsView = 'locations.view';
    case LocationsManage = 'locations.manage';

    // Organizacje
    case OrganizationsView = 'organizations.view';
    case OrganizationsManage = 'organizations.manage';

    // Prace administracyjne
    case WorkLogsView = 'work_logs.view';
    case WorkLogsCreate = 'work_logs.create';
    case WorkLogsReport = 'work_logs.report';

    // Baza wiedzy
    case KnowledgeView = 'knowledge.view';
    case KnowledgeCreate = 'knowledge.create';
    case KnowledgeManage = 'knowledge.manage';

    // Administracja
    case UsersManage = 'users.manage';
    case CategoriesManage = 'categories.manage';
    case AuditView = 'audit.view';
    case SettingsManage = 'settings.manage';

    /** Sekcja, do której należy uprawnienie (nagłówek w macierzy UI). */
    public function group(): string
    {
        return match ($this) {
            self::TicketsView, self::TicketsComment, self::TicketsManage,
            self::TicketsInternalNote, self::TicketsClose => 'Zgłoszenia',
            self::AssetsView, self::AssetsCreate, self::AssetsUpdate, self::AssetsArchive => 'Zasoby',
            self::LocationsView, self::LocationsManage => 'Lokalizacje',
            self::OrganizationsView, self::OrganizationsManage => 'Organizacje',
            self::WorkLogsView, self::WorkLogsCreate, self::WorkLogsReport => 'Prace administracyjne',
            self::KnowledgeView, self::KnowledgeCreate, self::KnowledgeManage => 'Baza wiedzy',
            self::UsersManage => 'Użytkownicy',
            self::CategoriesManage => 'Słowniki',
            self::AuditView => 'Audyt',
            self::SettingsManage => 'Ustawienia',
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::TicketsView => 'Podgląd wszystkich zgłoszeń',
            self::TicketsComment => 'Komentowanie zgłoszeń',
            self::TicketsManage => 'Zarządzanie zgłoszeniami (status, przypisanie)',
            self::TicketsInternalNote => 'Notatki wewnętrzne',
            self::TicketsClose => 'Zamykanie zgłoszeń',
            self::AssetsView => 'Podgląd zasobów',
            self::AssetsCreate => 'Tworzenie zasobów',
            self::AssetsUpdate => 'Edycja zasobów',
            self::AssetsArchive => 'Archiwizacja zasobów',
            self::LocationsView => 'Podgląd lokalizacji',
            self::LocationsManage => 'Zarządzanie lokalizacjami',
            self::OrganizationsView => 'Podgląd organizacji',
            self::OrganizationsManage => 'Zarządzanie organizacjami',
            self::WorkLogsView => 'Podgląd prac administracyjnych',
            self::WorkLogsCreate => 'Tworzenie i edycja prac',
            self::WorkLogsReport => 'Raport prac',
            self::KnowledgeView => 'Podgląd bazy wiedzy',
            self::KnowledgeCreate => 'Tworzenie artykułów',
            self::KnowledgeManage => 'Zarządzanie artykułami i widocznością',
            self::UsersManage => 'Zarządzanie użytkownikami',
            self::CategoriesManage => 'Zarządzanie słownikami',
            self::AuditView => 'Podgląd audytu',
            self::SettingsManage => 'Ustawienia systemu',
        };
    }

    /** @return list<string> wszystkie klucze uprawnień */
    public static function values(): array
    {
        return array_map(fn (self $p) => $p->value, self::cases());
    }

    /**
     * Katalog pogrupowany do macierzy uprawnień w UI.
     *
     * @return array<string,list<self>> nazwa grupy => uprawnienia
     */
    public static function catalog(): array
    {
        $out = [];
        foreach (self::cases() as $permission) {
            $out[$permission->group()][] = $permission;
        }

        return $out;
    }
}
