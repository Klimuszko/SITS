<?php

namespace App\Models;

use App\Enums\OrgRole;
use App\Enums\Permission;
use App\Enums\Role;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Profil dostępu = nazwany zestaw uprawnień (warstwa „CO"). Profile systemowe
 * (is_system) odwzorowują dotychczasowe role 1:1 i są nieusuwalne; admin może
 * tworzyć własne. Zakres „GDZIE" (per organizacja) rozstrzygają Policy/scope.
 */
class AccessProfile extends Model
{
    use HasFactory;

    /** Klucze profili systemowych (1:1 z App\Enums\Role / OrgRole). */
    public const SUPER_ADMIN = 'super_admin';
    public const ADMIN = 'admin';
    public const SUPPORT = 'support';
    public const MANAGER = 'manager';
    public const USER = 'user';

    /** Wartości applies_to. */
    public const APPLIES_STAFF = 'staff';
    public const APPLIES_CLIENT = 'client';

    protected $fillable = [
        'key',
        'name',
        'applies_to',
        'is_system',
        'is_active',
        'permissions',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
            'permissions' => 'array',
        ];
    }

    /** Użytkownicy (personel) z tym profilem globalnym. */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /** Członkostwa (klient) z tym profilem w obrębie organizacji. */
    public function memberships(): HasMany
    {
        return $this->hasMany(OrganizationMembership::class);
    }

    /** Czy profil nadaje dane uprawnienie. */
    public function grants(string|Permission $permission): bool
    {
        $key = $permission instanceof Permission ? $permission->value : $permission;

        return in_array($key, $this->permissions ?? [], true);
    }

    /**
     * Definicje profili systemowych — JEDNO źródło prawdy. Używane przez seeder
     * (tworzenie profili) oraz fallback w User::hasPermission (gdy konto nie ma
     * jeszcze przypisanego profilu — wtedy obowiązuje domyślny zestaw wg roli).
     *
     * @return array<string,array{name:string,applies_to:string,permissions:list<string>}>
     */
    public static function systemDefinitions(): array
    {
        $all = Permission::values();

        $user = self::keys([
            Permission::TicketsComment,
            Permission::AssetsView, Permission::LocationsView, Permission::OrganizationsView,
            Permission::WorkLogsView, Permission::KnowledgeView,
        ]);

        // Manager = user + podgląd wszystkich zgłoszeń organizacji.
        $manager = array_values(array_unique(array_merge($user, [Permission::TicketsView->value])));

        $support = self::keys([
            Permission::TicketsView, Permission::TicketsComment, Permission::TicketsManage,
            Permission::TicketsInternalNote, Permission::TicketsClose,
            Permission::AssetsView, Permission::AssetsCreate, Permission::AssetsUpdate, Permission::AssetsArchive,
            Permission::LocationsView, Permission::LocationsManage,
            Permission::OrganizationsView,
            Permission::WorkLogsView, Permission::WorkLogsCreate, Permission::WorkLogsReport,
            Permission::KnowledgeView, Permission::KnowledgeCreate, Permission::KnowledgeManage,
        ]);

        return [
            self::SUPER_ADMIN => ['name' => 'Super Admin', 'applies_to' => self::APPLIES_STAFF, 'permissions' => $all],
            self::ADMIN => ['name' => 'Administrator', 'applies_to' => self::APPLIES_STAFF, 'permissions' => $all],
            self::SUPPORT => ['name' => 'Support', 'applies_to' => self::APPLIES_STAFF, 'permissions' => $support],
            self::MANAGER => ['name' => 'Manager', 'applies_to' => self::APPLIES_CLIENT, 'permissions' => $manager],
            self::USER => ['name' => 'Użytkownik', 'applies_to' => self::APPLIES_CLIENT, 'permissions' => $user],
        ];
    }

    /**
     * Domyślne uprawnienia globalne wg roli personelu (fallback bez profilu).
     *
     * @return list<string>
     */
    public static function defaultPermissionsForRole(Role $role): array
    {
        $key = match ($role) {
            Role::SuperAdmin => self::SUPER_ADMIN,
            Role::Admin => self::ADMIN,
            Role::Support => self::SUPPORT,
            default => null,
        };

        return $key === null ? [] : self::systemDefinitions()[$key]['permissions'];
    }

    /**
     * Domyślne uprawnienia klienta wg roli w organizacji (fallback bez profilu).
     *
     * @return list<string>
     */
    public static function defaultPermissionsForOrgRole(OrgRole $role): array
    {
        $key = $role === OrgRole::Manager ? self::MANAGER : self::USER;

        return self::systemDefinitions()[$key]['permissions'];
    }

    /**
     * @param  list<Permission>  $permissions
     * @return list<string>
     */
    private static function keys(array $permissions): array
    {
        return array_map(fn (Permission $p) => $p->value, $permissions);
    }
}
