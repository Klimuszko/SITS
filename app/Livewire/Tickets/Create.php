<?php

namespace App\Livewire\Tickets;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetGroupEntry;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Services\AttachmentService;
use App\Services\TicketService;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Nowe zgłoszenie')]
class Create extends Component
{
    use WithFileUploads;

    public ?int $organization_id = null;
    public string $title = '';
    public string $description = '';
    public ?int $location_id = null;

    /**
     * Wybór w pickerze zasobu/pod-zasobu: 'a:{assetId}' (zasób) lub
     * 'e:{entryId}' (pod-zasób = wpis grupy ticket-linkable). Pusty = brak.
     * Rozwiązywany serwerowo w save() (anti-forge), nie ufamy mu wprost.
     */
    public string $assetSelection = '';

    public ?int $ticket_category_id = null;
    public ?int $ticket_priority_id = null;

    /** @var array<int,\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $files = [];

    public function mount(): void
    {
        $this->authorize('create', Ticket::class);

        $orgs = $this->availableOrganizations();
        if ($orgs->count() === 1) {
            $this->organization_id = $orgs->first()->id;
        }
    }

    /** Organizacje, w których bieżący użytkownik może utworzyć zgłoszenie. */
    protected function availableOrganizations()
    {
        $user = auth()->user();

        return match (true) {
            $user->isAdminLevel() => Organization::active()->orderBy('name')->get(),
            $user->isSupport() => $user->supportedOrganizations()->wherePivot('is_active', true)->orderBy('name')->get(),
            default => $user->organizations()->wherePivot('is_active', true)->orderBy('name')->get(),
        };
    }

    protected function rules(): array
    {
        $allowedOrgIds = $this->availableOrganizations()->pluck('id')->all();

        return [
            'organization_id' => ['required', 'integer', Rule::in($allowedOrgIds)],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'location_id' => ['nullable', 'integer', Rule::exists('locations', 'id')->where('organization_id', $this->organization_id)],
            'assetSelection' => ['nullable', 'string'],
            'ticket_category_id' => ['nullable', 'integer', 'exists:ticket_categories,id'],
            'ticket_priority_id' => ['nullable', 'integer', 'exists:ticket_priorities,id'],
            'files.*' => ['file', 'max:20480'], // 20 MB / plik
        ];
    }

    protected array $messages = [
        'organization_id.required' => 'Wybierz organizację zgłoszenia.',
        'title.required' => 'Tytuł jest wymagany.',
        'description.required' => 'Opis jest wymagany.',
    ];

    /** Reset zależnych pól po zmianie organizacji. */
    public function updatedOrganizationId(): void
    {
        $this->location_id = null;
        $this->assetSelection = '';
    }

    public function save(TicketService $tickets, AttachmentService $attachments)
    {
        $data = $this->validate();

        $organization = Organization::findOrFail($data['organization_id']);
        $this->authorize('create', Ticket::class);

        // Picker zwraca 'a:{id}'/'e:{id}'; rozwiązujemy je serwerowo i ponownie
        // walidujemy (anti-forge) zanim trafią do serwisu jako asset_id/entry_id.
        [$data['asset_id'], $data['asset_group_entry_id']] = $this->resolveAssetSelection($organization->id);

        $ticket = $tickets->create(auth()->user(), $organization, $data);

        foreach ($this->files as $file) {
            $attachments->store($file, $ticket, $organization->id, auth()->id());
        }

        session()->flash('status', 'Utworzono zgłoszenie '.$ticket->number.'.');

        return $this->redirectRoute('tickets.show', $ticket, navigate: true);
    }

    /**
     * Dostępne zasoby organizacji (aktywne, niepryw.) z dociążonymi wpisami grup,
     * ich wartościami i sekcjami (+ pole etykietujące) — tak by displayLabel()
     * rozwiązał się bez N+1 przy budowie pickera.
     *
     * @return Collection<int,Asset>
     */
    protected function pickerAssets(int $orgId): Collection
    {
        return Asset::where('organization_id', $orgId)
            ->active()
            ->where('is_private', false)
            ->with(['groupEntries.values', 'groupEntries.section.displayField'])
            ->orderBy('name')
            ->get();
    }

    /**
     * Opcje pickera: każdy zasób ('a:{id}') wraz z jego pod-zasobami
     * ('e:{id}') — wyłącznie wpisami grup, których sekcja is_ticket_linkable=true.
     * Wpisy niepowiązywalne (np. Dyski) są pomijane.
     *
     * @param  Collection<int,Asset>  $assets
     * @return array<int,array{label:string,value:string,subs:array<int,array{label:string,value:string}>}>
     */
    protected function pickerGroups(Collection $assets): array
    {
        return $assets->map(function (Asset $asset) {
            $subs = $asset->groupEntries
                ->filter(fn (AssetGroupEntry $entry) => (bool) $entry->section?->is_ticket_linkable)
                ->map(function (AssetGroupEntry $entry) use ($asset) {
                    $label = ($entry->section->ticket_label ?: $entry->section->name);

                    return [
                        'value' => 'e:'.$entry->id,
                        'label' => $asset->name.' → '.$label.': '.$entry->displayLabel(),
                    ];
                })
                ->values()
                ->all();

            return [
                'value' => 'a:'.$asset->id,
                'label' => $asset->name,
                'subs' => $subs,
            ];
        })->all();
    }

    /**
     * Rozwiązuje assetSelection na [asset_id, asset_group_entry_id] z serwerową
     * re-walidacją (anti-forge): wybrany zasób/wpis musi należeć do wskazanej
     * organizacji i być dostępny (zasób aktywny + niepryw.; wpis — sekcja
     * is_ticket_linkable=true i jego zasób aktywny + niepryw.). W innym wypadku
     * rzucamy ValidationException — ticket nie powstaje.
     *
     * @return array{0:?int,1:?int} [asset_id, asset_group_entry_id]
     */
    protected function resolveAssetSelection(int $orgId): array
    {
        $selection = trim($this->assetSelection);

        if ($selection === '') {
            return [null, null];
        }

        if (str_starts_with($selection, 'a:')) {
            $assetId = (int) substr($selection, 2);

            $exists = Asset::whereKey($assetId)
                ->where('organization_id', $orgId)
                ->active()
                ->where('is_private', false)
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([
                    'assetSelection' => 'Wybrany zasób jest niedostępny.',
                ]);
            }

            return [$assetId, null];
        }

        if (str_starts_with($selection, 'e:')) {
            $entryId = (int) substr($selection, 2);

            $entry = AssetGroupEntry::with(['asset', 'section'])->find($entryId);

            $linkable = $entry
                && $entry->asset
                && $entry->asset->organization_id === $orgId
                && $entry->asset->status === AssetStatus::Active
                && ! $entry->asset->is_private
                && (bool) $entry->section?->is_ticket_linkable;

            if (! $linkable) {
                throw ValidationException::withMessages([
                    'assetSelection' => 'Wybrany pod-zasób jest niedostępny.',
                ]);
            }

            // Powiązanie z zasobem-rodzicem tylko gdy sekcja tak stanowi.
            $assetId = $entry->section->link_parent_on_select ? $entry->asset_id : null;

            return [$assetId, $entry->id];
        }

        throw ValidationException::withMessages([
            'assetSelection' => 'Nieprawidłowy wybór zasobu.',
        ]);
    }

    public function render()
    {
        $orgId = $this->organization_id;

        $pickerAssets = $orgId ? $this->pickerAssets($orgId) : collect();

        return view('livewire.tickets.create', [
            'organizations' => $this->availableOrganizations(),
            'locations' => $orgId
                ? Location::treeForOrganization($orgId)
                : collect(),
            'assetGroups' => $this->pickerGroups($pickerAssets),
            'categories' => TicketCategory::active()->orderBy('name')->get(),
            'priorities' => TicketPriority::active()->orderBy('level')->get(),
        ]);
    }
}
