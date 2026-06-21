<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\KnowledgeArticle;
use App\Models\User;
use App\Services\KnowledgeVisibilityService;

class KnowledgeArticlePolicy
{
    public function __construct(private KnowledgeVisibilityService $visibility) {}

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, KnowledgeArticle $article): bool
    {
        return $this->visibility->canView($user, $article);
    }

    /** Tworzyć artykuły mogą profile z knowledge.create (domyślnie admin i support). */
    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::KnowledgeCreate);
    }

    /**
     * Edycja: wymaga knowledge.manage. Admin edytuje dowolny artykuł; pozostali
     * (np. support) tylko własne (author_id) — rozróżnienie rekordu zostaje tutaj.
     */
    public function update(User $user, KnowledgeArticle $article): bool
    {
        if (! $user->hasPermission(Permission::KnowledgeManage)) {
            return false;
        }

        return $user->isAdminLevel() || $article->author_id === $user->id;
    }

    /** Zmiana widoczności artykułu (audytowane) – knowledge.manage. */
    public function changeVisibility(User $user, KnowledgeArticle $article): bool
    {
        return $user->hasPermission(Permission::KnowledgeManage);
    }
}
