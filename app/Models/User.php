<?php

namespace App\Models;

use App\Enums\OrgRole;
use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'access_profile_id',
        'phone',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
        ];
    }

    /* ----------------------------------------------------------------
     | Relacje
     | ---------------------------------------------------------------- */

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /** Globalny profil dostępu (personel). Klient czerpie profil z członkostwa. */
    public function accessProfile(): BelongsTo
    {
        return $this->belongsTo(AccessProfile::class);
    }

    /** Organizacje, których użytkownik jest członkiem (klient). */
    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'organization_memberships')
            ->withPivot(['role', 'manager_scope', 'is_active'])
            ->withTimestamps();
    }

    public function supportAssignments(): HasMany
    {
        return $this->hasMany(SupportAssignment::class, 'support_user_id');
    }

    /** Organizacje obsługiwane przez tego supporta. */
    public function supportedOrganizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class, 'support_assignments', 'support_user_id', 'organization_id')
            ->withPivot(['is_primary', 'scope', 'is_active'])
            ->withTimestamps();
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(Group::class, 'group_user')->withTimestamps();
    }

    public function requestedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_support_id');
    }

    public function observedTickets(): BelongsToMany
    {
        return $this->belongsToMany(Ticket::class, 'ticket_observers')->withTimestamps();
    }

    /** Zasoby prywatne przypisane bezpośrednio do tego użytkownika. */
    public function privateAssets(): BelongsToMany
    {
        return $this->belongsToMany(Asset::class, 'asset_user')->withTimestamps();
    }

    /* ----------------------------------------------------------------
     | Role i dostęp
     | ---------------------------------------------------------------- */

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /** Admin lub Super Admin. */
    public function isAdminLevel(): bool
    {
        return $this->role->isAdminLevel();
    }

    public function isSupport(): bool
    {
        return $this->role === Role::Support;
    }

    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    /**
     * Personel systemu (super_admin/admin/support) – kandydaci na supporta organizacji.
     *
     * @param  Builder<User>  $query
     */
    public function scopeStaff(Builder $query): void
    {
        $query->whereIn('role', [
            Role::SuperAdmin->value,
            Role::Admin->value,
            Role::Support->value,
        ]);
    }

    /** Aktywne członkostwo w danej organizacji (lub null). */
    public function membershipFor(int $organizationId): ?OrganizationMembership
    {
        return $this->memberships
            ->firstWhere(fn (OrganizationMembership $m) => $m->organization_id === $organizationId && $m->is_active);
    }

    public function isMemberOf(int $organizationId): bool
    {
        return $this->membershipFor($organizationId) !== null;
    }

    public function isManagerOf(int $organizationId): bool
    {
        return $this->membershipFor($organizationId)?->role === OrgRole::Manager;
    }

    /** Czy użytkownik jest aktywnym managerem w jakiejkolwiek organizacji. */
    public function managesAnyOrganization(): bool
    {
        return $this->memberships
            ->where('is_active', true)
            ->where('role', OrgRole::Manager)
            ->isNotEmpty();
    }

    /**
     * Czy użytkownik ma dane uprawnienie (warstwa „CO"). Zakres „GDZIE" (per
     * organizacja) rozstrzygają Policy/scope — tu sprawdzamy samą zdolność:
     *  - Super Admin → zawsze true (spójnie z Gate::before),
     *  - personel → uprawnienia z globalnego profilu (users.access_profile_id),
     *  - klient → uprawnienia z profilu członkostwa w organizacji $context;
     *    bez kontekstu organizacji klient nie ma żadnej globalnej zdolności.
     *
     * $context: null | int (id organizacji) | Organization | model z organization_id.
     */
    public function hasPermission(string|Permission $permission, mixed $context = null): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $key = $permission instanceof Permission ? $permission->value : $permission;

        if ($this->isStaff()) {
            if ($this->accessProfile !== null) {
                return $this->accessProfile->grants($key);
            }

            // Fallback: brak przypisanego profilu → domyślny zestaw wg roli.
            return in_array($key, AccessProfile::defaultPermissionsForRole($this->role), true);
        }

        $organizationId = $this->resolveOrganizationId($context);
        if ($organizationId === null) {
            return false;
        }

        $membership = $this->membershipFor($organizationId);
        if ($membership === null) {
            return false;
        }

        if ($membership->accessProfile !== null) {
            return $membership->accessProfile->grants($key);
        }

        // Fallback: członkostwo bez profilu → domyślny zestaw wg roli w organizacji.
        return in_array($key, AccessProfile::defaultPermissionsForOrgRole($membership->role), true);
    }

    /** Wyłuskuje id organizacji z kontekstu uprawnienia (lub null). */
    protected function resolveOrganizationId(mixed $context): ?int
    {
        return match (true) {
            $context === null => null,
            is_int($context) => $context,
            $context instanceof Organization => $context->id,
            is_object($context) && isset($context->organization_id) => (int) $context->organization_id,
            default => null,
        };
    }

    /** Czy support jest przypisany (aktywnie) do organizacji. */
    public function supportsOrganization(int $organizationId): bool
    {
        return $this->supportAssignments
            ->contains(fn (SupportAssignment $a) => $a->organization_id === $organizationId && $a->is_active);
    }

    /**
     * Identyfikatory organizacji widocznych dla użytkownika
     * (bez pełnego dostępu admina – ten jest obsługiwany w policy).
     *
     * @return Collection<int,int>
     */
    public function accessibleOrganizationIds(): Collection
    {
        if ($this->isStaff() && ! $this->isSupport()) {
            // Admin/Super Admin – pełny zakres (policy zwraca true wcześniej).
            return Organization::query()->pluck('id');
        }

        if ($this->isSupport()) {
            return $this->supportAssignments
                ->where('is_active', true)
                ->pluck('organization_id')
                ->unique()
                ->values();
        }

        // Klient (manager/user) – organizacje z aktywnych członkostw.
        return $this->memberships
            ->where('is_active', true)
            ->pluck('organization_id')
            ->unique()
            ->values();
    }
}
