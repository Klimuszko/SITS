<?php

namespace App\Livewire\WorkLogs;

use App\Enums\AuditAction;
use App\Enums\PublicationStatus;
use App\Enums\Role;
use App\Models\AdministrativeWorkLog;
use App\Models\Asset;
use App\Models\Location;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Praca administracyjna')]
class ManageForm extends Component
{
    public ?AdministrativeWorkLog $log = null;

    // Pola formularza
    public ?int $organization_id = null;
    public ?int $location_id = null;
    public ?int $asset_id = null;
    public string $title = '';
    public string $description = '';
    public ?string $work_type = null;
    public ?int $performed_by = null;
    public ?string $performed_at = null;
    public ?int $duration_minutes = null;
    public bool $visible_to_manager = true;
    public bool $visible_to_user = false;
    public string $status = 'published';

    public function mount(?AdministrativeWorkLog $administrativeWorkLog = null): void
    {
        $log = $administrativeWorkLog;

        if ($log && $log->exists) {
            $this->authorize('update', $log);

            $this->log = $log;
            $this->organization_id = $log->organization_id;
            $this->location_id = $log->location_id;
            $this->asset_id = $log->asset_id;
            $this->title = $log->title;
            $this->description = $log->description;
            $this->work_type = $log->work_type;
            $this->performed_by = $log->performed_by;
            $this->performed_at = $log->performed_at?->format('Y-m-d\TH:i');
            $this->duration_minutes = $log->duration_minutes;
            $this->visible_to_manager = $log->visible_to_manager;
            $this->visible_to_user = $log->visible_to_user;
            $this->status = $log->status->value;

            return;
        }

        $this->authorize('create', AdministrativeWorkLog::class);

        // Domyślne wartości dla nowej pracy.
        $this->performed_by = auth()->id();
        $this->performed_at = now()->format('Y-m-d\TH:i');

        $orgs = $this->availableOrganizations();
        if ($orgs->count() === 1) {
            $this->organization_id = $orgs->first()->id;
        }
    }

    /** Organizacje, w których bieżący członek personelu może zarejestrować pracę. */
    protected function availableOrganizations()
    {
        $user = auth()->user();

        return match (true) {
            $user->isAdminLevel() => Organization::query()->orderBy('name')->get(),
            $user->isSupport() => $user->supportedOrganizations()->wherePivot('is_active', true)->orderBy('name')->get(),
            default => collect(),
        };
    }

    /** @return array<string,mixed> */
    protected function rules(): array
    {
        $allowedOrgIds = $this->availableOrganizations()->pluck('id')->all();

        return [
            'organization_id' => ['required', 'integer', Rule::in($allowedOrgIds)],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('organization_id', $this->organization_id)],
            'asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->where('organization_id', $this->organization_id)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'work_type' => ['nullable', 'string', 'max:255'],
            // Wykonawca musi być aktywnym członkiem personelu (super_admin/admin/support).
            'performed_by' => [
                'required', 'integer',
                Rule::exists('users', 'id')->where(fn ($q) => $q
                    ->where('is_active', true)
                    ->whereIn('role', [
                        Role::SuperAdmin->value,
                        Role::Admin->value,
                        Role::Support->value,
                    ])),
            ],
            'performed_at' => ['required', 'date'],
            'duration_minutes' => ['nullable', 'integer', 'min:0'],
            'visible_to_manager' => ['boolean'],
            'visible_to_user' => ['boolean'],
            'status' => ['required', Rule::enum(PublicationStatus::class)],
        ];
    }

    protected function messages(): array
    {
        return [
            'organization_id.required' => 'Wybierz organizację.',
            'organization_id.in' => 'Nie możesz rejestrować prac dla tej organizacji.',
            'performed_by.required' => 'Wskaż wykonawcę pracy.',
            'performed_by.exists' => 'Wykonawcą może być wyłącznie aktywny członek personelu.',
            'title.required' => 'Tytuł jest wymagany.',
            'description.required' => 'Opis jest wymagany.',
        ];
    }

    /** Reset pól zależnych po zmianie organizacji. */
    public function updatedOrganizationId(): void
    {
        $this->location_id = null;
        $this->asset_id = null;
    }

    public function save(): void
    {
        // Ponowna autoryzacja (mount mogła być create; chronimy też przed manipulacją).
        if ($this->log && $this->log->exists) {
            $this->authorize('update', $this->log);
        } else {
            $this->authorize('create', AdministrativeWorkLog::class);
        }

        $data = $this->validate();

        DB::transaction(function () use ($data) {
            $isNew = ! ($this->log && $this->log->exists);
            $log = $this->log ?? new AdministrativeWorkLog();
            $old = $isNew ? null : $log->getOriginal();

            $log->fill($data)->save();

            AuditLogger::log(
                $isNew ? AuditAction::WorkLogCreated : AuditAction::WorkLogUpdated,
                $log,
                $old,
                $log->getChanges(),
            );

            $this->log = $log;
        });

        session()->flash('status', 'Zapisano pracę administracyjną.');
        $this->redirectRoute('work-logs.index', navigate: true);
    }

    public function render()
    {
        $orgId = $this->organization_id;

        return view('livewire.work-logs.manage-form', [
            'organizations' => $this->availableOrganizations(),
            'locations' => $orgId
                ? Location::where('organization_id', $orgId)->orderBy('name')->get()
                : collect(),
            'assets' => $orgId
                ? Asset::where('organization_id', $orgId)->orderBy('name')->get()
                : collect(),
            'performers' => User::staff()->where('is_active', true)->orderBy('name')->get(),
            'statuses' => PublicationStatus::options(),
        ]);
    }
}
