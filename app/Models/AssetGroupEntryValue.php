<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Wartość pojedynczego pola w obrębie jednego wpisu grupy powtarzalnej. */
class AssetGroupEntryValue extends Model
{
    protected $fillable = [
        'asset_group_entry_id',
        'asset_field_id',
        'value',
    ];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(AssetGroupEntry::class, 'asset_group_entry_id');
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(AssetField::class, 'asset_field_id');
    }
}
