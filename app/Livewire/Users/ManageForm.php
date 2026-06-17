<?php

namespace App\Livewire\Users;

use App\Enums\AuditAction;
use App\Enums\ManagerScope;
use App\Enums\OrgRole;
use App\Enums\Role;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ManageForm extends Component
{
    public ?User $user = null;

    // Pola formularza użytkownika
    public string $name = '';
    public string $email = '';
    public string $role = 'user';
    public ?string $phone = null;
    public bool $is_active = true;
    public ?string $password = null;
    public ?string $password_confirmation = null;

    // Pola wiersza "dodaj członkostwo"
    public ?int $newOrganizationId = null;
    public string $newOrgRole = 'user';
    public ?string $newManagerScope = null;
    public bool $newMembershipActive = true;

    public function mount(?User $user = null): void
    {
        $this->authorize($user && $user->exists ? 'update' : 'create', $user ?? User::class);

        if ($user && $user->exists) {
            $this->user = $user;
            $this->name = $user->name;
            $this->email = $user->email;
            $this->role = $user->role->value;
            $this->phone = $user->phone;
            $this->is_active = $user->is_active;
        }
    }

    /** Czy aktor edytuje samego siebie. */
    public function isEditingSelf(): bool
    {
        return $this->user && $this->user->exists && $this->user->id === auth()->id();
    }

    /**
     * Role dopuszczalne dla aktora. Super Admin może nadać każdą rolę;
     * Admin nie może utworzyć/ustawić Super Admina (blokada eskalacji uprawnień).
     *
     * @return array<int,string>
     */
    protected function allowedRoleValues(): array
    {
        $values = collect(Role::cases())->map(fn (Role $r) => $r->value);

        if (! auth()->user()->isSuperAdmin()) {
            $values = $values->reject(fn (string $v) => $v === Role::SuperAdmin->value);
        }

        return $values->values()->all();
    }

    /** @return array<string,string> Opcje roli do selecta (filtrowane wg aktora). */
    public function roleOptions(): array
    {
        $allowed = $this->allowedRoleValues();

        return collect(Role::options())
            ->only($allowed)
            ->all();
    }

    /** @return array<string,mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($this->user?->id),
            ],
            // Blokada eskalacji: dozwolone tylko role z allowedRoleValues() (Admin nie ustawi super_admin).
            'role' => ['required', Rule::in($this->allowedRoleValues())],
            'phone' => ['nullable', 'string', 'max:50'],
            'is_active' => ['boolean'],
            // Tworzenie: hasło wymagane; edycja: opcjonalne (puste = bez zmiany).
            'password' => [
                $this->user && $this->user->exists ? 'nullable' : 'required',
                'string', 'min:8', 'confirmed',
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'role.in' => 'Nie masz uprawnień do nadania tej roli.',
            'email.unique' => 'Ten adres e-mail jest już zajęty.',
        ];
    }

    public function save(): void
    {
        // Ponowna autoryzacja po stronie serwera (nie tylko w mount()).
        $this->authorize($this->user && $this->user->exists ? 'update' : 'create', $this->user ?? User::class);

        // Self-protection: edytując własne konto nie można zmienić roli ani dezaktywować się.
        if ($this->isEditingSelf()) {
            $this->role = $this->user->role->value;
            $this->is_active = $this->user->is_active;
        }

        $data = $this->validate();

        DB::transaction(function () use ($data) {
            $isNew = ! ($this->user && $this->user->exists);
            $target = $this->user ?? new User();
            $oldRole = $isNew ? null : $target->role;

            $target->name = $data['name'];
            $target->email = $data['email'];
            $target->role = $data['role'];
            $target->phone = $data['phone'];
            $target->is_active = $data['is_active'];

            // Hasło: ustawiamy tylko gdy podano (cast 'hashed' zahashuje automatycznie — bez podwójnego hashowania).
            if (filled($data['password'])) {
                $target->password = $data['password'];
            }

            if ($isNew) {
                $target->email_verified_at = now();
            }

            $target->save();

            AuditLogger::log(
                $isNew ? AuditAction::UserCreated : AuditAction::UserUpdated,
                $target,
                $isNew ? null : ['role' => $oldRole?->value],
                ['role' => $target->role->value],
            );

            // Osobny wpis audytu, gdy realnie zmieniła się rola.
            if (! $isNew && $oldRole !== $target->role) {
                AuditLogger::log(
                    AuditAction::UserRoleChanged,
                    $target,
                    ['role' => $oldRole?->value],
                    ['role' => $target->role->value],
                );
            }

            $this->user = $target;
        });

        session()->flash('status', 'Zapisano użytkownika.');
        $this->redirectRoute('users.edit', ['user' => $this->user->id], navigate: true);
    }

    /* ----------------------------- Członkostwa ---------------------------- */

    public function addMembership(): void
    {
        $this->authorize('update', $this->user);

        $existingOrgIds = $this->user->memberships()->pluck('organization_id')->all();

        $this->validate([
            // Organizacja musi istnieć i nie może być już przypisana (unique(user_id, organization_id)).
            'newOrganizationId' => [
                'required', 'integer',
                Rule::exists('organizations', 'id'),
                Rule::notIn($existingOrgIds),
            ],
            'newOrgRole' => ['required', Rule::enum(OrgRole::class)],
            // manager_scope wymagany tylko dla managera.
            'newManagerScope' => [
                Rule::requiredIf(fn () => $this->newOrgRole === OrgRole::Manager->value),
                'nullable',
                Rule::enum(ManagerScope::class),
            ],
            'newMembershipActive' => ['boolean'],
        ], [
            'newOrganizationId.not_in' => 'Użytkownik jest już członkiem tej organizacji.',
            'newManagerScope.required' => 'Wybierz zakres managera.',
        ]);

        $scope = $this->newOrgRole === OrgRole::Manager->value ? $this->newManagerScope : null;

        $membership = $this->user->memberships()->create([
            'organization_id' => $this->newOrganizationId,
            'role' => $this->newOrgRole,
            'manager_scope' => $scope,
            'is_active' => $this->newMembershipActive,
        ]);

        AuditLogger::log(
            AuditAction::MembershipGranted,
            $membership->organization,
            null,
            [
                'user_id' => $this->user->id,
                'organization_id' => $membership->organization_id,
                'role' => $membership->role->value,
                'manager_scope' => $scope,
            ],
        );

        // reset() przywraca zadeklarowane wartości domyślne (newOrgRole='user', newMembershipActive=true).
        $this->reset(['newOrganizationId', 'newOrgRole', 'newManagerScope', 'newMembershipActive']);
        $this->user->refresh();
    }

    public function removeMembership(int $membershipId): void
    {
        $this->authorize('update', $this->user);

        $membership = $this->user->memberships()->whereKey($membershipId)->first();
        if (! $membership) {
            return;
        }

        $payload = [
            'user_id' => $this->user->id,
            'organization_id' => $membership->organization_id,
            'role' => $membership->role->value,
        ];
        $organization = $membership->organization;

        $membership->delete();

        AuditLogger::log(AuditAction::MembershipRevoked, $organization, $payload, null);

        $this->user->refresh();
    }

    public function render()
    {
        $memberships = $this->user
            ? $this->user->memberships()->with('organization')->get()
            : collect();

        $assignedOrgIds = $this->user
            ? $this->user->memberships()->pluck('organization_id')->all()
            : [];

        return view('livewire.users.manage-form', [
            'roleOptions' => $this->roleOptions(),
            'orgRoles' => OrgRole::options(),
            'managerScopes' => ManagerScope::options(),
            'memberships' => $memberships,
            'organizations' => Organization::query()
                ->whereNotIn('id', $assignedOrgIds)
                ->orderBy('name')->get(),
            'isSelf' => $this->isEditingSelf(),
        ]);
    }
}
