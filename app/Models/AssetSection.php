<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Węzeł struktury kategorii zasobu. Może być:
 *  - Sekcją (parent_id = null, is_group = false),
 *  - Podsekcją (parent_id ustawione, is_repeatable = false),
 *  - Grupą powtarzalną (is_group = true, is_repeatable = true).
 */
class AssetSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_category_id',
        'parent_id',
        'name',
        'icon',
        'key',
        'is_group',
        'is_repeatable',
        'min_entries',
        'max_entries',
        'is_ticket_linkable',
        'display_field_id',
        'link_parent_on_select',
        'ticket_label',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_group' => 'boolean',
            'is_repeatable' => 'boolean',
            'is_ticket_linkable' => 'boolean',
            'link_parent_on_select' => 'boolean',
            'is_active' => 'boolean',
            'min_entries' => 'integer',
            'max_entries' => 'integer',
            'order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('order');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(AssetField::class)->orderBy('order');
    }

    /** Pole etykietujące pojedynczy wpis grupy (pod-zasób). */
    public function displayField(): BelongsTo
    {
        return $this->belongsTo(AssetField::class, 'display_field_id');
    }
}
