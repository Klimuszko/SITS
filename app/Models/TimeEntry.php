<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class TimeEntry extends Model
{
    protected $fillable = [
        'user_id',
        'timeloggable_type',
        'timeloggable_id',
        'description',
        'minutes',
        'entry_date',
        'billable',
    ];

    protected function casts(): array
    {
        return [
            'minutes' => 'integer',
            'entry_date' => 'date',
            'billable' => 'boolean',
        ];
    }

    public function timeloggable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
