<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Pojedynczy wpis grupy powtarzalnej = jedna instancja grupy = kandydat na
 * pod-zasób. Wartości pól tego wpisu żyją w asset_group_entry_values
 * (NIE w asset_field_values, które obsługuje pola sekcji niepowtarzalnych).
 */
class AssetGroupEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'asset_id',
        'asset_section_id',
        'parent_entry_id',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'order' => 'integer',
        ];
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(AssetSection::class, 'asset_section_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_entry_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_entry_id')->orderBy('order');
    }

    public function values(): HasMany
    {
        return $this->hasMany(AssetGroupEntryValue::class);
    }

    /**
     * Etykieta wpisu: wartość pola display_field_id sekcji, a w razie braku
     * — czytelny fallback z identyfikatorem wpisu.
     */
    public function displayLabel(): string
    {
        $displayFieldId = $this->section?->display_field_id;

        if ($displayFieldId) {
            $value = $this->values
                ->firstWhere('asset_field_id', $displayFieldId)?->value;

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return '#'.$this->id;
    }
}
