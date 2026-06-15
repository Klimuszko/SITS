<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Organization extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'parent_id',
        'status',
        'nip',
        'address',
        'contact_email',
        'contact_phone',
        'internal_note',
        'default_support_user_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => OrganizationType::class,
            'status' => OrganizationStatus::class,
        ];
    }

    /* --------------------------- Drzewo --------------------------- */

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /* ------------------------- Powiązania ------------------------- */

    public function defaultSupport(): BelongsTo
    {
        return $this->belongsTo(User::class, 'default_support_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'organization_memberships')
            ->withPivot(['role', 'manager_scope', 'is_active'])
            ->withTimestamps();
    }

    public function supportAssignments(): HasMany
    {
        return $this->hasMany(SupportAssignment::class);
    }

    public function supporters(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'support_assignments', 'organization_id', 'support_user_id')
            ->withPivot(['is_primary', 'scope', 'is_active'])
            ->withTimestamps();
    }

    public function locations(): HasMany
    {
        return $this->hasMany(Location::class);
    }

    public function groups(): HasMany
    {
        return $this->hasMany(Group::class);
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function workLogs(): HasMany
    {
        return $this->hasMany(AdministrativeWorkLog::class);
    }

    /* -------------------------- Pomocnicze ------------------------ */

    public function isActive(): bool
    {
        return $this->status === OrganizationStatus::Active;
    }

    public function scopeActive($query)
    {
        return $query->where('status', OrganizationStatus::Active->value);
    }
}
