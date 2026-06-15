<?php

namespace App\Policies;

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

    /** Tworzyć/edytować mogą admin i support (z uprawnieniem). */
    public function create(User $user): bool
    {
        return $user->isAdminLevel() || $user->isSupport();
    }

    public function update(User $user, KnowledgeArticle $article): bool
    {
        return $user->isAdminLevel()
            || ($user->isSupport() && $article->author_id === $user->id)
            || $user->isAdmin();
    }

    /** Zmiana widoczności artykułu (audytowane). */
    public function changeVisibility(User $user, KnowledgeArticle $article): bool
    {
        return $user->isAdminLevel() || $user->isSupport();
    }
}
