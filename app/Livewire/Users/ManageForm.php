<?php

namespace App\Livewire\Users;

use App\Enums\AuditAction;
use App\Enums\ManagerScope;
use App\Enums\OrgRole;
use App\Enums\Role;
use App\Models\AccessProfile;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Notifications\AccountInvitationNotification;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
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

    // Globalny profil dostępu (personel). Klient czerpie profil z członkostwa.
    public ?int $access_profile_id = null;

    // Pola wiersza "dodaj członkostwo"
    public ?int $newOrganizationId = null;
    public string $newOrgRole = 'user';
    public ?string $newManagerScope = null;
    public bool $newMembershipActive = true;
    public ?int $newAccessProfileId = null;

    /** Profil per istniejące członkostwo (membership_id => access_profile_id) — edycja inline. */
    public array $membershipProfiles = [];

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
            $this->access_profile_id = $user->access_profile_id;
        }
    }

    /** Czy aktor edytuje samego siebie. */
    public function isEditingSelf(): bool
    {
        return $this->user && $this->user->exists && $this->user->id === auth()->id();
    }

    /** @return array<int,string> globalne role personelu (mają profil globalny). */
    protected function staffRoleValues(): array
    {
        return [Role::SuperAdmin->value, Role::Admin->value, Role::Support->value];
    }

    /** Czy wybrana rola to personel (wtedy ma sens globalny profil dostępu). */
    public function isStaffRole(): bool
    {
        return in_array($this->role, $this->staffRoleValues(), true);
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
            // Hasło zawsze opcjonalne: puste przy tworzeniu = konto z zaproszeniem
            // (użytkownik ustawi hasło z linku); puste przy edycji = bez zmiany.
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            // Globalny profil musi być profilem personelu (dla klienta i tak zerowany).
            'access_profile_id' => [
                'nullable', 'integer',
                Rule::exists('access_profiles', 'id')->where('applies_to', AccessProfile::APPLIES_STAFF),
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

        // Self-protection: edytując własne konto nie można zmienić roli, dezaktywować
        // się ani zmienić własnego profilu dostępu (ochrona przed odcięciem sobie dostępu).
        if ($this->isEditingSelf()) {
            $this->role = $this->user->role->value;
            $this->is_active = $this->user->is_active;
            $this->access_profile_id = $this->user->access_profile_id;
        }

        $data = $this->validate();

        $isNew = ! ($this->user && $this->user->exists);
        // Nowe konto bez hasła = zaproszenie e-mail (link „ustaw hasło").
        $wantsInvite = $isNew && blank($data['password']);

        DB::transaction(function () use ($data, $isNew) {
            $target = $this->user ?? new User();
            $oldRole = $isNew ? null : $target->role;

            $target->name = $data['name'];
            $target->email = $data['email'];
            $target->role = $data['role'];
            $target->phone = $data['phone'];
            $target->is_active = $data['is_active'];

            // Profil globalny tylko dla personelu; klient czerpie profil z członkostwa.
            $target->access_profile_id = in_array($data['role'], $this->staffRoleValues(), true)
                ? ($data['access_profile_id'] ?? null)
                : null;

            // Hasło: gdy podane — ustawiamy (cast 'hashed' zahashuje). Gdy puste przy
            // tworzeniu — losowy, NIEUŻYWALNY placeholder; prawdziwe hasło ustawi sam
            // użytkownik z linku zaproszenia (nic nie generujemy dla niego ani nie wysyłamy).
            if (filled($data['password'])) {
                $target->password = $data['password'];
            } elseif ($isNew) {
                $target->password = Str::password(40);
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

        // Zaproszenie wysyłamy PO commit transakcji (token w password_reset_tokens
        // + kolejkowany e-mail). Link „ustaw hasło" — bez hasła w treści.
        if ($wantsInvite) {
            $token = Password::broker('invitations')->createToken($this->user);
            $this->user->notify(new AccountInvitationNotification($token));
        }

        session()->flash('status', $wantsInvite
            ? 'Utworzono konto — wysłano e-mail z linkiem do ustawienia hasła.'
            : 'Zapisano użytkownika.');
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
            // Profil klienta (opcjonalny; puste = domyślne uprawnienia roli w organizacji).
            'newAccessProfileId' => [
                'nullable', 'integer',
                Rule::exists('access_profiles', 'id')->where('applies_to', AccessProfile::APPLIES_CLIENT),
            ],
        ], [
            'newOrganizationId.not_in' => 'Użytkownik jest już członkiem tej organizacji.',
            'newManagerScope.required' => 'Wybierz zakres managera.',
        ]);

        $scope = $this->newOrgRole === OrgRole::Manager->value ? $this->newManagerScope : null;

        $membership = $this->user->memberships()->create([
            'organization_id' => $this->newOrganizationId,
            'role' => $this->newOrgRole,
            'access_profile_id' => $this->newAccessProfileId,
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
        $this->reset(['newOrganizationId', 'newOrgRole', 'newManagerScope', 'newMembershipActive', 'newAccessProfileId']);
        $this->user->refresh();
    }

    /** Zmiana profilu dostępu istniejącego członkostwa (klient, per organizacja). */
    public function saveMembershipProfile(int $membershipId): void
    {
        $this->authorize('update', $this->user);

        $membership = $this->user->memberships()->whereKey($membershipId)->first();
        if (! $membership) {
            return;
        }

        $raw = $this->membershipProfiles[$membershipId] ?? null;
        $profileId = ($raw === null || $raw === '') ? null : (int) $raw;

        // Musi być profilem klienta (albo brak = domyślne uprawnienia roli).
        if ($profileId !== null
            && ! AccessProfile::where('id', $profileId)->where('applies_to', AccessProfile::APPLIES_CLIENT)->exists()) {
            return;
        }

        $old = $membership->access_profile_id;
        $membership->update(['access_profile_id' => $profileId]);

        AuditLogger::log(
            'membership.access_profile_changed',
            $membership->organization,
            ['access_profile_id' => $old],
            ['access_profile_id' => $profileId],
        );

        $this->user->refresh();
        session()->flash('status', 'Zapisano profil dostępu członkostwa.');
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

        // Wypełnij stan inline-edycji profilu dla każdego członkostwa (bez nadpisywania zmian użytkownika).
        foreach ($memberships as $membership) {
            $this->membershipProfiles[$membership->id] ??= $membership->access_profile_id;
        }

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
            'isStaffRole' => $this->isStaffRole(),
            'staffProfiles' => AccessProfile::where('applies_to', AccessProfile::APPLIES_STAFF)
                ->where('is_active', true)
                ->where('key', '!=', AccessProfile::SUPER_ADMIN)
                ->orderBy('name')->get(),
            'clientProfiles' => AccessProfile::where('applies_to', AccessProfile::APPLIES_CLIENT)
                ->where('is_active', true)
                ->orderBy('name')->get(),
        ]);
    }
}
