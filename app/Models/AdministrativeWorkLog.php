<?php

namespace App\Models;

use App\Enums\PublicationStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AdministrativeWorkLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'organization_id',
        'location_id',
        'asset_id',
        'title',
        'description',
        'work_type',
        'performed_by',
        'performed_at',
        'duration_minutes',
        'visible_to_manager',
        'visible_to_user',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'performed_at' => 'datetime',
            'duration_minutes' => 'integer',
            'visible_to_manager' => 'boolean',
            'visible_to_user' => 'boolean',
            'status' => PublicationStatus::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function timeEntries(): MorphMany
    {
        return $this->morphMany(TimeEntry::class, 'timeloggable');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', PublicationStatus::Published->value);
    }

    public function scopeVisibleToManager(Builder $query): Builder
    {
        return $query->where('visible_to_manager', true);
    }

    public function scopeVisibleToUser(Builder $query): Builder
    {
        return $query->where('visible_to_user', true);
    }
}
