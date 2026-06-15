<?php

namespace App\Services;

use App\Enums\PublicationStatus;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeArticleVisibility;
use App\Models\User;

/**
 * Wyznacza widoczność artykułów bazy wiedzy (§22).
 * Widoczność jest elastyczna i wielowyborowa: organizacja, grupa, rola, użytkownik.
 */
class KnowledgeVisibilityService
{
    public function canView(User $user, KnowledgeArticle $article): bool
    {
        // Personel administracyjny i autor – pełny dostęp (także do szkiców).
        if ($user->isAdminLevel() || $article->author_id === $user->id) {
            return true;
        }

        // Support widzi artykuły kierowane do supportu lub do obsługiwanych organizacji.
        // Dla pozostałych wymagamy publikacji.
        if (! $article->isPublished()) {
            return false;
        }

        $orgIds = $user->accessibleOrganizationIds();
        $groupIds = $user->groups->pluck('id');

        foreach ($article->visibilities as $rule) {
            if ($this->matches($user, $rule, $orgIds->all(), $groupIds->all())) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int,int>  $orgIds
     * @param  array<int,int>  $groupIds
     */
    protected function matches(User $user, KnowledgeArticleVisibility $rule, array $orgIds, array $groupIds): bool
    {
        return match ($rule->visibility_type) {
            'organization' => in_array($rule->organization_id, $orgIds, true),
            'group' => in_array($rule->group_id, $groupIds, true),
            'role' => $rule->role === $user->role,
            'user' => $rule->user_id === $user->id,
            default => false,
        };
    }
}
