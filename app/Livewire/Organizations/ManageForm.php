<?php

namespace App\Livewire\Organizations;

use App\Enums\AuditAction;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Enums\Role;
use App\Enums\SupportScope;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ManageForm extends Component
{
    public ?Organization $organization = null;

    // Pola formularza
    public string $name = '';
    public string $type = 'company';
    public ?int $parent_id = null;
    public string $status = 'active';
    public ?string $nip = null;
    public ?string $address = null;
    public ?string $contact_email = null;
    public ?string $contact_phone = null;
    public ?string $internal_note = null;
    public ?int $default_support_user_id = null;

    public function mount(?Organization $organization = null): void
    {
        $this->authorize($organization && $organization->exists ? 'update' : 'create', $organization ?? Organization::class);

        if ($organization && $organization->exists) {
            $this->organization = $organization;
            $this->fill($organization->only([
                'name', 'parent_id', 'nip', 'address',
                'contact_email', 'contact_phone', 'internal_note', 'default_support_user_id',
            ]));
            $this->type = $organization->type->value;
            $this->status = $organization->status->value;
        }
    }

    /** @return array<string,mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(OrganizationType::class)],
            'parent_id' => ['nullable', 'integer', 'exists:organizations,id', Rule::notIn([$this->organization?->id])],
            'status' => ['required', Rule::enum(OrganizationStatus::class)],
            'nip' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
            'internal_note' => ['nullable', 'string'],
            // Aktywna organizacja MUSI mieć domyślnego supporta (§6, §9).
            'default_support_user_id' => [
                Rule::requiredIf(fn () => $this->status === OrganizationStatus::Active->value),
                'nullable', 'integer', 'exists:users,id',
            ],
        ];
    }

    protected function messages(): array
    {
        return [
            'default_support_user_id.required' => 'Aktywna organizacja musi mieć przypisanego domyślnego supporta.',
            'parent_id.not_in' => 'Organizacja nie może być swoim własnym rodzicem.',
        ];
    }

    public function save(): void
    {
        $data = $this->validate();

        DB::transaction(function () use ($data) {
            $isNew = ! ($this->organization && $this->organization->exists);
            $org = $this->organization ?? new Organization();
            $old = $isNew ? null : $org->getOriginal();

            $org->fill($data)->save();

            // Synchronizacja głównego supporta jako wpisu w support_assignments.
            if ($org->default_support_user_id) {
                $this->syncPrimarySupport($org);
            }

            AuditLogger::log(
                $isNew ? AuditAction::OrganizationCreated : AuditAction::OrganizationUpdated,
                $org,
                $old,
                $org->getChanges(),
            );

            $this->organization = $org;
        });

        session()->flash('status', 'Zapisano organizację.');
        $this->redirectRoute('organizations.index', navigate: true);
    }

    /** Ustawia wybranego użytkownika jako jedynego głównego supporta organizacji. */
    protected function syncPrimarySupport(Organization $org): void
    {
        // Najpierw zdejmujemy flagę primary z pozostałych (partial unique index w bazie).
        $org->supportAssignments()
            ->where('support_user_id', '!=', $org->default_support_user_id)
            ->where('is_primary', true)
            ->update(['is_primary' => false]);

        $org->supportAssignments()->updateOrCreate(
            ['support_user_id' => $org->default_support_user_id],
            ['is_primary' => true, 'scope' => SupportScope::All->value, 'is_active' => true],
        );

        AuditLogger::log(AuditAction::SupportAssigned, $org, null, [
            'support_user_id' => $org->default_support_user_id,
            'is_primary' => true,
        ]);
    }

    public function render()
    {
        return view('livewire.organizations.manage-form', [
            'types' => OrganizationType::options(),
            'statuses' => OrganizationStatus::options(),
            'supportUsers' => User::where('role', Role::Support->value)->where('is_active', true)->orderBy('name')->get(),
            'parents' => Organization::query()
                ->when($this->organization, fn ($q) => $q->whereKeyNot($this->organization->id))
                ->orderBy('name')->get(),
        ]);
    }
}
