<?php

namespace App\Livewire\Users;

use App\Enums\AuditAction;
use App\Enums\ManagerScope;
use App\Enums\OrgRole;
use App\Enums\Role;
use App\Models\AccessProfile;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\AccountInvitationNotification;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Masowe zapraszanie użytkowników (faza 4–5 onboardingu). Admin wkleja wiele
 * adresów e-mail; dla każdego (nieistniejącego) tworzymy konto klienta bez
 * używalnego hasła i wysyłamy e-mail z linkiem „ustaw hasło". Opcjonalnie od razu
 * przypisujemy wszystkich do wybranej organizacji + profilu (to admin przypisuje).
 */
#[Layout('layouts.app')]
#[Title('Zaproś użytkowników')]
class Invite extends Component
{
    use WithPagination;

    public string $emails = '';

    /** Świeży link „ustaw hasło" wygenerowany na żądanie (Kopiuj link) — pokazywany inline. */
    public ?string $copiedLink = null;
    public ?int $copiedFor = null;

    // Opcjonalne przypisanie do organizacji przy zaproszeniu.
    public ?int $organization_id = null;
    public string $org_role = 'user';
    public ?string $manager_scope = null;
    public ?int $access_profile_id = null;   // profil klienta (gdy organizacja)
    public bool $membership_active = true;

    public function mount(): void
    {
        $this->authorize('create', User::class);
    }

    public function updatedOrgRole(): void
    {
        if ($this->org_role !== OrgRole::Manager->value) {
            $this->manager_scope = null;
        }
    }

    public function invite(): void
    {
        $this->authorize('create', User::class);

        $this->validate([
            'emails' => ['required', 'string'],
            'organization_id' => ['nullable', 'integer', Rule::exists('organizations', 'id')],
            'org_role' => ['required', Rule::enum(OrgRole::class)],
            'manager_scope' => [
                Rule::requiredIf(fn () => $this->organization_id !== null && $this->org_role === OrgRole::Manager->value),
                'nullable', Rule::enum(ManagerScope::class),
            ],
            'access_profile_id' => [
                'nullable', 'integer',
                Rule::exists('access_profiles', 'id')->where('applies_to', AccessProfile::APPLIES_CLIENT),
            ],
            'membership_active' => ['boolean'],
        ], [
            'emails.required' => 'Podaj co najmniej jeden adres e-mail.',
            'manager_scope.required' => 'Wybierz zakres managera.',
        ]);

        $parsed = $this->parseEmails($this->emails);

        if ($parsed['valid'] === []) {
            $this->addError('emails', 'Nie znaleziono prawidłowych adresów e-mail.');

            return;
        }

        // Istniejące konta (po znormalizowanym e-mailu) — pomijamy.
        $existing = User::whereIn('email', $parsed['valid'])
            ->pluck('email')
            ->map(fn ($e) => mb_strtolower($e))
            ->all();

        $created = 0;
        $skipped = 0;

        foreach ($parsed['valid'] as $email) {
            if (in_array($email, $existing, true)) {
                $skipped++;

                continue;
            }

            $user = new User();
            $user->name = $this->nameFromEmail($email);
            $user->email = $email;
            $user->role = Role::User;
            $user->is_active = true;
            $user->email_verified_at = now();
            // Placeholder hasła — nieużywalny; prawdziwe ustawi użytkownik z linku.
            $user->password = Str::password(40);
            // Stan „oczekujące zaproszenie" — czyszczony przy aktywacji (hasło/SSO).
            $user->invited_at = now();
            $user->save();

            AuditLogger::log(AuditAction::UserCreated, $user, null, [
                'role' => $user->role->value,
                'invited' => true,
            ]);

            if ($this->organization_id !== null) {
                $scope = $this->org_role === OrgRole::Manager->value ? $this->manager_scope : null;

                $membership = $user->memberships()->create([
                    'organization_id' => $this->organization_id,
                    'role' => $this->org_role,
                    'access_profile_id' => $this->access_profile_id,
                    'manager_scope' => $scope,
                    'is_active' => $this->membership_active,
                ]);

                AuditLogger::log(AuditAction::MembershipGranted, $membership->organization, null, [
                    'user_id' => $user->id,
                    'organization_id' => $membership->organization_id,
                    'role' => $membership->role->value,
                ]);
            }

            $token = Password::broker('invitations')->createToken($user);
            $user->notify(new AccountInvitationNotification($token));

            $created++;
        }

        $this->reset(['emails']);

        $summary = "Zaproszono: {$created}. Pominięto (już istnieją): {$skipped}.";
        if ($parsed['invalid'] > 0) {
            $summary .= " Niepoprawne adresy: {$parsed['invalid']}.";
        }

        session()->flash('status', $summary);
    }

    /**
     * Pobiera konto z OCZEKUJĄCYM zaproszeniem (`invited_at IS NOT NULL`) lub 404.
     * Twardy guard: żadna akcja zarządzania zaproszeniami nie może dotknąć zwykłego,
     * aktywowanego konta — tylko oczekujące zaproszenia (z `invited_at`).
     */
    protected function pendingInviteOrFail(int $id): User
    {
        return User::whereNotNull('invited_at')->findOrFail($id);
    }

    /**
     * Usuń oczekujące zaproszenie — twarde usunięcie konta (User ma SoftDeletes →
     * forceDelete), zwalnia e-mail do ponownego zaproszenia. Tylko dla oczekujących.
     */
    public function deleteInvitation(int $id): void
    {
        $user = $this->pendingInviteOrFail($id);

        // Policy 'delete' wymaga instancji (blokuje też Super Admina / własne konto).
        $this->authorize('delete', $user);

        AuditLogger::log(AuditAction::UserDeleted, $user, [
            'email' => $user->email,
            'role' => $user->role->value,
            'invitation' => true,
        ], null);

        $user->forceDelete();

        if ($this->copiedFor === $id) {
            $this->copiedLink = null;
            $this->copiedFor = null;
        }

        session()->flash('status', 'Zaproszenie zostało usunięte. E-mail jest znów wolny.');
    }

    /**
     * Wyślij zaproszenie ponownie — świeży token brokera „invitations”, ponowna
     * notyfikacja i odświeżenie znacznika `invited_at`. Tylko dla oczekujących.
     */
    public function resendInvitation(int $id): void
    {
        $this->authorize('create', User::class);

        $user = $this->pendingInviteOrFail($id);

        $token = Password::broker('invitations')->createToken($user);
        $user->notify(new AccountInvitationNotification($token));

        $user->forceFill(['invited_at' => now()])->save();

        session()->flash('status', 'Zaproszenie wysłano ponownie.');
    }

    /**
     * Wygeneruj ŚWIEŻY link „ustaw hasło” do ręcznego skopiowania (gdy mail leży).
     * URL budowany identycznie jak w AccountInvitationNotification (trasa
     * `password.set` + token + email), świeży token za każdym razem — NIGDY nie
     * ujawniamy zahashowanego tokenu z DB. Tylko admin, tylko dla oczekujących.
     */
    public function copyInviteLink(int $id): void
    {
        $this->authorize('create', User::class);

        $user = $this->pendingInviteOrFail($id);

        $token = Password::broker('invitations')->createToken($user);

        $this->copiedLink = route('password.set', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ]);
        $this->copiedFor = $id;
    }

    /**
     * Rozbija wklejony tekst na adresy (po przecinku/średniku/białych znakach),
     * normalizuje do lowercase, deduplikuje, oddziela niepoprawne.
     *
     * @return array{valid:list<string>,invalid:int}
     */
    protected function parseEmails(string $raw): array
    {
        $tokens = preg_split('/[\s,;]+/', trim($raw)) ?: [];

        $valid = [];
        $invalid = 0;

        foreach ($tokens as $token) {
            $token = mb_strtolower(trim($token));

            if ($token === '') {
                continue;
            }

            if (filter_var($token, FILTER_VALIDATE_EMAIL)) {
                $valid[$token] = true;   // klucz = deduplikacja
            } else {
                $invalid++;
            }
        }

        return ['valid' => array_keys($valid), 'invalid' => $invalid];
    }

    /** Czytelna nazwa z części lokalnej e-maila (fallback: cały e-mail). */
    protected function nameFromEmail(string $email): string
    {
        $name = Str::of($email)->before('@')->replace(['.', '_', '-'], ' ')->squish()->title()->toString();

        return $name !== '' ? $name : $email;
    }

    public function render()
    {
        $pending = User::whereNotNull('invited_at')
            ->with('memberships.organization')
            ->orderByDesc('invited_at')
            ->paginate(15);

        return view('livewire.users.invite', [
            'organizations' => Organization::orderBy('name')->get(),
            'orgRoles' => OrgRole::options(),
            'managerScopes' => ManagerScope::options(),
            'clientProfiles' => AccessProfile::where('applies_to', AccessProfile::APPLIES_CLIENT)
                ->where('is_active', true)
                ->orderBy('name')->get(),
            'pending' => $pending,
            'inviteExpiryDays' => 7,
        ]);
    }
}
