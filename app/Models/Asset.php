<?php

namespace App\Models;

use App\Enums\AssetStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Asset extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'location_id',
        'asset_category_id',
        'parent_asset_id',
        'name',
        'inventory_code',
        'status',
        'is_private',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => AssetStatus::class,
            'is_private' => 'boolean',
        ];
    }

    /* ------------------------- Powiązania ------------------------- */

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /* ---------------------- Drzewo techniczne --------------------- */

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_asset_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_asset_id');
    }

    /* ------------------------- Wartości pól ----------------------- */

    public function fieldValues(): HasMany
    {
        return $this->hasMany(AssetFieldValue::class);
    }

    /** Wpisy grup powtarzalnych (kandydaci na pod-zasoby) — wartości w asset_group_entry_values. */
    public function groupEntries(): HasMany
    {
        return $this->hasMany(AssetGroupEntry::class)->orderBy('order');
    }

    /* --------------------------- Relacje -------------------------- */

    /** Relacje wychodzące (asset -> related_asset). */
    public function relations(): HasMany
    {
        return $this->hasMany(AssetRelation::class, 'asset_id');
    }

    /** Relacje przychodzące (inne zasoby wskazujące na ten). */
    public function inverseRelations(): HasMany
    {
        return $this->hasMany(AssetRelation::class, 'related_asset_id');
    }

    /* ------------------- Przypisani użytkownicy ------------------- */

    public function assignedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'asset_user')->withTimestamps();
    }

    /* --------------------------- Historia ------------------------- */

    public function history(): HasMany
    {
        return $this->hasMany(AssetHistory::class)->latest('created_at');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /* -------------------------- Pomocnicze ------------------------ */

    public function scopeActive($query)
    {
        return $query->where('status', AssetStatus::Active->value);
    }
}
