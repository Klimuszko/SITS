<?php

namespace App\Models;

use App\Enums\SupportScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportAssignment extends Model
{
    protected $fillable = [
        'support_user_id',
        'organization_id',
        'is_primary',
        'scope',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
            'scope' => SupportScope::class,
        ];
    }

    public function support(): BelongsTo
    {
        return $this->belongsTo(User::class, 'support_user_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
