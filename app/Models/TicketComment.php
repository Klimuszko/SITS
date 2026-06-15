<?php

namespace App\Models;

use App\Enums\CommentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TicketComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'type',
        'body',
    ];

    protected function casts(): array
    {
        return [
            'type' => CommentType::class,
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    /** Komentarze widoczne dla klienta (bez notatek wewnętrznych). */
    public function scopeClientVisible(Builder $query): Builder
    {
        return $query->whereIn('type', [
            CommentType::Public->value,
            CommentType::CloseRequest->value,
        ]);
    }

    public function isInternal(): bool
    {
        return $this->type === CommentType::Internal;
    }
}
