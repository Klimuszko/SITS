<?php

namespace App\Models;

use App\Enums\Permission;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Profil dostępu = nazwany zestaw uprawnień (warstwa „CO"). Profile systemowe
 * (is_system) odwzorowują dotychczasowe role 1:1 i są nieusuwalne; admin może
 * tworzyć własne. Zakres „GDZIE" (per organizacja) rozstrzygają Policy/scope.
 */
class AccessProfile extends Model
{
    use HasFactory;

    /** Klucze profili systemowych (1:1 z App\Enums\Role / OrgRole). */
    public const SUPER_ADMIN = 'super_admin';
    public const ADMIN = 'admin';
    public const SUPPORT = 'support';
    public const MANAGER = 'manager';
    public const USER = 'user';

    /** Wartości applies_to. */
    public const APPLIES_STAFF = 'staff';
    public const APPLIES_CLIENT = 'client';

    protected $fillable = [
        'key',
        'name',
        'applies_to',
        'is_system',
        'is_active',
        'permissions',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'permissions' => 'array',
        ];
    }

    /** Użytkownicy (personel) z tym profilem globalnym. */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Członkostwa (klient) z tym profilem w obrębie organizacji. */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /** Czy profil nadaje dane uprawnienie. */
    public function grants(string|Permission $permission): bool
    {
        $key = $permission instanceof Permission ? $permission->value : $permission;

        return in_array($key, $this->permissions ?? [], true);
    }
}
