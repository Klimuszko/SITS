<?php

namespace App\Support;

use App\Models\User;

/**
 * Pojedyncze źródło prawdy dla nawigacji bocznej.
 *
 * Zwraca uporządkowaną listę widocznych kategorii, już przefiltrowanych do
 * pozycji widocznych dla danego użytkownika. Bramki uprawnień (isStaff /
 * managesAnyOrganization / can) rozstrzygane są tutaj, więc widok pozostaje
 * czystym @foreach bez logiki dostępu. Kategoria bez widocznych pozycji jest
 * pomijana. "icon" to wolny ciąg rozwiązywany przez komponent <x-icon>.
 */
final class Navigation
{
    /**
     * @return list<array{key:string,label:?string,icon:?string,items:list<array{label:string,route:string,active:string,icon:string}>}>
     */
    public static function categoriesFor(User $user): array
    {
        $isStaff = $user->isStaff();

        $categories = [
            [
                'key' => 'pulpit',
                'label' => null,
                'icon' => null,
                'items' => [
                    ['label' => 'Pulpit', 'route' => 'dashboard', 'active' => 'dashboard', 'icon' => 'dashboard', 'visible' => true],
                ],
            ],
            [
                'key' => 'wsparcie',
                'label' => 'Wsparcie',
                'icon' => 'life-ring',
                'items' => [
                    ['label' => 'Zgłoszenia', 'route' => 'tickets.index', 'active' => 'tickets.*', 'icon' => 'ticket', 'visible' => true],
                    ['label' => 'Baza wiedzy', 'route' => 'knowledge.index', 'active' => 'knowledge.*', 'icon' => 'book', 'visible' => true],
                ],
            ],
            [
                'key' => 'zasoby',
                'label' => 'Zasoby',
                'icon' => 'server',
                'items' => [
                    ['label' => 'Zasoby', 'route' => 'assets.index', 'active' => 'assets.*', 'icon' => 'server', 'visible' => true],
                    ['label' => 'Lokalizacje', 'route' => 'locations.index', 'active' => 'locations.*', 'icon' => 'map-pin', 'visible' => $isStaff],
                ],
            ],
            [
                'key' => 'klienci',
                'label' => 'Klienci',
                'icon' => 'building',
                'items' => [
                    ['label' => 'Organizacje', 'route' => 'organizations.index', 'active' => 'organizations.*', 'icon' => 'building', 'visible' => $isStaff],
                    ['label' => 'Użytkownicy', 'route' => 'users.index', 'active' => 'users.*', 'icon' => 'users', 'visible' => $user->can('manage-users')],
                ],
            ],
            [
                'key' => 'praca',
                'label' => 'Praca',
                'icon' => 'clipboard',
                'items' => [
                    ['label' => 'Prace administracyjne', 'route' => 'work-logs.index', 'active' => 'work-logs.*', 'icon' => 'clipboard', 'visible' => $isStaff || $user->managesAnyOrganization()],
                ],
            ],
            [
                'key' => 'administracja',
                'label' => 'Administracja',
                'icon' => 'settings',
                'items' => [
                    ['label' => 'Słowniki', 'route' => 'dictionaries.ticket-categories', 'active' => 'dictionaries.*', 'icon' => 'sliders', 'visible' => $user->can('manage-categories')],
                    ['label' => 'Audyt', 'route' => 'audit.index', 'active' => 'audit.*', 'icon' => 'shield', 'visible' => $user->can('view-audit')],
                    ['label' => 'Ustawienia', 'route' => 'settings.branding', 'active' => 'settings.*', 'icon' => 'settings', 'visible' => $user->can('access-admin')],
                ],
            ],
        ];

        $result = [];

        foreach ($categories as $category) {
            $items = [];

            foreach ($category['items'] as $item) {
                if ($item['visible']) {
                    unset($item['visible']);
                    $items[] = $item;
                }
            }

            // Kategoria bez widocznych pozycji nie renderuje się wcale.
            if ($items === []) {
                continue;
            }

            $result[] = [
                'key' => $category['key'],
                'label' => $category['label'],
                'icon' => $category['icon'],
                'items' => $items,
            ];
        }

        return $result;
    }
}
