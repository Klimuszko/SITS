<?php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'number',
        'title',
        'description',
        'requester_id',
        'organization_id',
        'location_id',
        'asset_id',
        'assigned_support_id',
        'status',
        'ticket_priority_id',
        'ticket_category_id',
        'first_response_at',
        'last_reply_at',
        'resolved_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'first_response_at' => 'datetime',
            'last_reply_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /* ------------------------- Powiązania ------------------------- */

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
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

    public function assignedSupport(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_support_id');
    }

    public function priority(): BelongsTo
    {
        return $this->belongsTo(TicketPriority::class, 'ticket_priority_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class, 'ticket_category_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->oldest();
    }

    public function observers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'ticket_observers')->withTimestamps();
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /* -------------------------- Pomocnicze ------------------------ */

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [
            TicketStatus::Closed->value,
            TicketStatus::Cancelled->value,
        ]);
    }

    public function scopeForOrganizations(Builder $query, array $organizationIds): Builder
    {
        return $query->whereIn('organization_id', $organizationIds);
    }
}
