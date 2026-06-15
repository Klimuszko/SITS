<?php

namespace App\Models;

use App\Enums\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KnowledgeArticleVisibility extends Model
{
    protected $table = 'knowledge_article_visibility';

    protected $fillable = [
        'knowledge_article_id',
        'visibility_type',  // organization | group | role | user
        'organization_id',
        'group_id',
        'user_id',
        'role',
    ];

    protected function casts(): array
    {
        return [
            'role' => Role::class,
        ];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(KnowledgeArticle::class, 'knowledge_article_id');
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
