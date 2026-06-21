<?php

namespace App\Models;

use App\Enums\ManagerScope;
use App\Enums\OrgRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrganizationMembership extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'organization_id',
        'role',
        'access_profile_id',
        'manager_scope',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'role' => OrgRole::class,
            'manager_scope' => ManagerScope::class,
            'is_active' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** Profil dostępu klienta w obrębie tej organizacji (warstwa „CO"). */
    public function accessProfile(): BelongsTo
    {
        return $this->belongsTo(AccessProfile::class);
    }

    public function isManager(): bool
    {
        return $this->role === OrgRole::Manager;
    }
}
