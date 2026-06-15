<?php

namespace App\Providers;

use App\Models\AdministrativeWorkLog;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\KnowledgeArticle;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\AdministrativeWorkLogPolicy;
use App\Policies\AssetPolicy;
use App\Policies\AttachmentPolicy;
use App\Policies\KnowledgeArticlePolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\TicketPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string,class-string> */
    protected array $policies = [
        Organization::class => OrganizationPolicy::class,
        Ticket::class => TicketPolicy::class,
        Asset::class => AssetPolicy::class,
        AdministrativeWorkLog::class => AdministrativeWorkLogPolicy::class,
        KnowledgeArticle::class => KnowledgeArticlePolicy::class,
        Attachment::class => AttachmentPolicy::class,
    ];

    public function boot(): void
    {
        foreach ($this->policies as $model => $policy) {
            Gate::policy($model, $policy);
        }

        // Super Admin może wszystko.
        Gate::before(function (User $user, string $ability) {
            return $user->isSuperAdmin() ? true : null;
        });

        // Bramki administracyjne.
        Gate::define('access-admin', fn (User $user) => $user->isAdminLevel());
        Gate::define('manage-system', fn (User $user) => $user->isSuperAdmin());
        Gate::define('manage-users', fn (User $user) => $user->isAdminLevel());
        Gate::define('manage-categories', fn (User $user) => $user->isAdminLevel());
        Gate::define('view-audit', fn (User $user) => $user->isAdminLevel());
        Gate::define('access-staff', fn (User $user) => $user->isStaff());
    }
}
