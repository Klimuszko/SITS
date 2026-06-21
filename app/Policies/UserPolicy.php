<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Autoryzacja modułu użytkowników (Step 5 — SECURITY-SENSITIVE).
 *
 * Uwaga: `Gate::before` (AuthServiceProvider) zwraca true dla Super Admina,
 * więc poniższe metody są de facto oceniane dla aktora z uprawnieniem users.manage
 * (domyślnie Admin). Guardy chronią Super Admina i konto własne.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::UsersManage);
    }

    public function view(User $user, User $target): bool
    {
        return $user->hasPermission(Permission::UsersManage);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::UsersManage);
    }

    /** Nie można edytować Super Admina (Super Admin przechodzi przez Gate::before). */
    public function update(User $user, User $target): bool
    {
        return $user->hasPermission(Permission::UsersManage) && ! $target->isSuperAdmin();
    }

    /** Nie można usunąć Super Admina ani samego siebie. */
    public function delete(User $user, User $target): bool
    {
        return $user->hasPermission(Permission::UsersManage)
            && ! $target->isSuperAdmin()
            && $user->id !== $target->id;
    }
}
