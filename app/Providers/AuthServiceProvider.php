<?php

namespace App\Providers;

use App\Models\AdministrativeWorkLog;
use App\Models\Asset;
use App\Models\Attachment;
use App\Models\KnowledgeArticle;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\AdministrativeWorkLogPolicy;
use App\Policies\AssetPolicy;
use App\Policies\AttachmentPolicy;
use App\Policies\KnowledgeArticlePolicy;
use App\Policies\LocationPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\TicketPolicy;
use App\Policies\UserPolicy;
use App\Enums\Permission;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /** @var array<class-string,class-string> */
    protected array $policies = [
        User::class => UserPolicy::class,
        Organization::class => OrganizationPolicy::class,
        Ticket::class => TicketPolicy::class,
        Asset::class => AssetPolicy::class,
        Location::class => LocationPolicy::class,
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

        // Bramki uprawnień (warstwa „CO") — przez profile dostępu (z fallbackiem
        // do domyślnych uprawnień roli, gdy konto nie ma jeszcze profilu).
        Gate::define('access-admin', fn (User $user) => $user->hasPermission(Permission::SettingsManage));
        Gate::define('manage-users', fn (User $user) => $user->hasPermission(Permission::UsersManage));
        Gate::define('manage-categories', fn (User $user) => $user->hasPermission(Permission::CategoriesManage));
        Gate::define('view-audit', fn (User $user) => $user->hasPermission(Permission::AuditView));

        // Bramki klasy konta / wyłącznie Super Admin — to NIE są przypisywalne
        // uprawnienia (manage-system, force-delete przechodzą przez Gate::before).
        Gate::define('manage-system', fn (User $user) => $user->isSuperAdmin());
        Gate::define('force-delete', fn (User $user) => $user->isSuperAdmin());
        Gate::define('access-staff', fn (User $user) => $user->isStaff());
    }
}
