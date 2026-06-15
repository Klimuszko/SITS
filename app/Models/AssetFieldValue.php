<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetFieldValue extends Model
{
    protected $fillable = [
        'asset_id',
        'asset_field_id',
        'value',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(AssetField::class, 'asset_field_id');
    }
}
