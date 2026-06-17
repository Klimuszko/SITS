<?php

namespace App\Policies;

use App\Models\User;

/**
 * Autoryzacja modułu użytkowników (Step 5 — SECURITY-SENSITIVE).
 *
 * Uwaga: `Gate::before` (AuthServiceProvider) zwraca true dla Super Admina,
 * więc poniższe metody są de facto oceniane dla aktora będącego Adminem.
 * Guardy chronią Super Admina i konto własne przed Adminem.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdminLevel();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isAdminLevel();
    }

    public function create(User $user): bool
    {
        return $user->isAdminLevel();
    }

    /** Admin NIE może edytować Super Admina (Super Admin przechodzi przez Gate::before). */
    public function update(User $user, User $target): bool
    {
        return $user->isAdminLevel() && ! $target->isSuperAdmin();
    }

    /** Admin NIE może usunąć Super Admina ani samego siebie. */
    public function delete(User $user, User $target): bool
    {
        return $user->isAdminLevel()
            && ! $target->isSuperAdmin()
            && $user->id !== $target->id;
    }
}
