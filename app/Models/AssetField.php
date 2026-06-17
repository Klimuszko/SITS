<?php

namespace App\Models;

use App\Enums\AssetFieldType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetField extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_category_id',
        'asset_section_id',
        'name',
        'key',
        'type',
        'options',
        'placeholder',
        'default_value',
        'help',
        'is_required',
        'order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'type' => AssetFieldType::class,
            'options' => 'array',
            'is_required' => 'boolean',
            'is_active' => 'boolean',
            'order' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(AssetSection::class, 'asset_section_id');
    }
}
