<?php

namespace App\Livewire\Settings;

use App\Enums\AuditAction;
use App\Enums\Permission;
use App\Models\AccessProfile;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Panel „Profile dostępu" (Krok B1). Tworzenie/edycja profili i przypisywanie
 * uprawnień przez macierz. Bramka access-admin. Zabezpieczenia:
 *  - profil Super Admin jest zablokowany (pełny dostęp przez Gate::before),
 *  - profile systemowe: edycja tylko uprawnień + aktywności (nazwa/typ zablokowane),
 *    bez usuwania,
 *  - aktor steruje wyłącznie uprawnieniami, które sam posiada (brak eskalacji);
 *    uprawnienia spoza jego zasięgu w profilu są zachowywane bez zmian.
 */
#[Layout('layouts.app')]
#[Title('Profile dostępu')]
class AccessProfiles extends Component
{
    public ?int $editingId = null;

    public string $name = '';
    public string $applies_to = AccessProfile::APPLIES_STAFF;
    public string $description = '';
    public bool $is_active = true;

    /** @var list<string> wybrane klucze uprawnień */
    public array $permissions = [];

    public function mount(): void
    {
        $this->authorize('access-admin');
    }

    /** @return array<string,mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'applies_to' => ['required', Rule::in([AccessProfile::APPLIES_STAFF, AccessProfile::APPLIES_CLIENT])],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(Permission::values())],
        ];
    }

    public function edit(int $id): void
    {
        $this->authorize('access-admin');

        $profile = AccessProfile::findOrFail($id);

        if ($this->isLocked($profile)) {
            session()->flash('error', 'Profil Super Admin jest zablokowany — ma pełny dostęp z definicji.');

            return;
        }

        $this->editingId = $profile->id;
        $this->name = $profile->name;
        $this->applies_to = $profile->applies_to;
        $this->description = $profile->description ?? '';
        $this->is_active = $profile->is_active;
        $this->permissions = $profile->permissions ?? [];
    }

    public function save(): void
    {
        $this->authorize('access-admin');

        $editing = $this->editingId ? AccessProfile::find($this->editingId) : null;

        if ($editing && $this->isLocked($editing)) {
            session()->flash('error', 'Profil Super Admin jest zablokowany.');

            return;
        }

        $data = $this->validate();

        // Guardrail przeciw eskalacji: aktor steruje tylko uprawnieniami, które
        // sam posiada; uprawnienia spoza jego zasięgu zostają w profilu nietknięte.
        $actorPerms = $this->actorPermissions();
        $submitted = array_values(array_intersect($this->permissions, Permission::values()));
        $existing = $editing?->permissions ?? [];

        $finalPermissions = array_values(array_unique(array_merge(
            array_intersect($submitted, $actorPerms),
            array_diff($existing, $actorPerms),
        )));

        $isCreate = $editing === null;

        if ($editing && $editing->is_system) {
            // Profil systemowy: tylko uprawnienia + aktywność (nazwa/typ zablokowane).
            $editing->update([
                'permissions' => $finalPermissions,
                'is_active' => $data['is_active'],
            ]);
            $saved = $editing;
        } else {
            // Klucz techniczny generowany z nazwy i ukryty (jak w słownikach).
            $key = $editing?->key ?? $this->uniqueKey(Str::slug($data['name']));

            $saved = AccessProfile::updateOrCreate(
                ['id' => $this->editingId],
                [
                    'key' => $key,
                    'name' => $data['name'],
                    'applies_to' => $data['applies_to'],
                    'description' => $data['description'] ?: null,
                    'is_active' => $data['is_active'],
                    'is_system' => false,
                    'permissions' => $finalPermissions,
                ],
            );
        }

        AuditLogger::log(
            $isCreate ? AuditAction::AccessProfileCreated : AuditAction::AccessProfileUpdated,
            $saved,
        );

        $this->resetForm();
        session()->flash('status', 'Zapisano profil dostępu.');
    }

    /**
     * Usunięcie profilu własnego. FK access_profile_id ma nullOnDelete — przypisani
     * użytkownicy/członkostwa wracają do domyślnych uprawnień swojej roli.
     */
    public function delete(int $id): void
    {
        $this->authorize('access-admin');

        $profile = AccessProfile::find($id);

        if ($profile === null) {
            return;
        }

        if ($profile->is_system) {
            session()->flash('error', 'Profil systemowy nie może zostać usunięty.');

            return;
        }

        // Odpinamy profil jawnie (nie polegamy na kaskadzie FK — sqlite w testach
        // bywa bez pragm); przypisani wracają do domyślnych uprawnień roli.
        User::where('access_profile_id', $profile->id)->update(['access_profile_id' => null]);
        OrganizationMembership::where('access_profile_id', $profile->id)->update(['access_profile_id' => null]);

        AuditLogger::log(AuditAction::AccessProfileDeleted, $profile);
        $profile->delete();

        if ($this->editingId === $id) {
            $this->resetForm();
        }

        session()->flash('status', 'Profil usunięty. Przypisani wrócili do domyślnych uprawnień roli.');
    }

    public function resetForm(): void
    {
        $this->reset(['editingId', 'name', 'description', 'permissions']);
        $this->applies_to = AccessProfile::APPLIES_STAFF;
        $this->is_active = true;
        $this->resetValidation();
    }

    /** Profil Super Admin jest w pełni zablokowany (anty-lockout). */
    protected function isLocked(AccessProfile $profile): bool
    {
        return $profile->key === AccessProfile::SUPER_ADMIN;
    }

    /**
     * Uprawnienia, którymi aktor może dysponować = te, które sam posiada.
     *
     * @return list<string>
     */
    protected function actorPermissions(): array
    {
        $user = auth()->user();

        return collect(Permission::cases())
            ->filter(fn (Permission $p) => $user->hasPermission($p))
            ->map(fn (Permission $p) => $p->value)
            ->values()
            ->all();
    }

    /** Unikalny klucz profilu (sufiks -2, -3, ...), ignorując bieżąco edytowany. */
    protected function uniqueKey(string $base): string
    {
        $base = $base !== '' ? $base : 'profil';
        $key = $base;
        $i = 2;

        while (AccessProfile::where('key', $key)
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))
            ->exists()) {
            $key = $base.'-'.$i;
            $i++;
        }

        return $key;
    }

    public function render()
    {
        return view('livewire.settings.access-profiles', [
            'profiles' => AccessProfile::orderByDesc('is_system')->orderBy('applies_to')->orderBy('name')->get(),
            'catalog' => Permission::catalog(),
            'assignable' => $this->actorPermissions(),
            'editingProfile' => $this->editingId ? AccessProfile::find($this->editingId) : null,
            'appliesToOptions' => [
                AccessProfile::APPLIES_STAFF => 'Personel (uprawnienia globalne)',
                AccessProfile::APPLIES_CLIENT => 'Klient (per organizacja)',
            ],
        ]);
    }
}
