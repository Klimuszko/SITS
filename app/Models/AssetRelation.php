<?php

namespace App\Models;

use App\Enums\AssetRelationType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetRelation extends Model
{
    protected $fillable = [
        'asset_id',
        'related_asset_id',
        'type',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'type' => AssetRelationType::class,
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'asset_id');
    }

    public function relatedAsset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'related_asset_id');
    }
}
