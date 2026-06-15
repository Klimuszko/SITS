<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ogranicza dostęp do tras wg globalnej roli użytkownika.
 * Użycie w trasie: ->middleware('role:super_admin,admin')
 */
class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403);
        }

        if (! empty($roles) && ! in_array($user->role->value, $roles, true)) {
            abort(403);
        }

        return $next($request);
    }
}
