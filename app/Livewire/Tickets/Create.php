<?php

namespace App\Livewire\Tickets;

use App\Models\Asset;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketCategory;
use App\Models\TicketPriority;
use App\Services\AttachmentService;
use App\Services\TicketService;
use Illuminate\Validation\Rule;
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
    public ?int $asset_id = null;
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
            'asset_id' => ['nullable', 'integer', Rule::exists('assets', 'id')->where('organization_id', $this->organization_id)],
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
        $this->asset_id = null;
    }

    public function save(TicketService $tickets, AttachmentService $attachments)
    {
        $data = $this->validate();

        $organization = Organization::findOrFail($data['organization_id']);
        $this->authorize('create', Ticket::class);

        $ticket = $tickets->create(auth()->user(), $organization, $data);

        foreach ($this->files as $file) {
            $attachments->store($file, $ticket, $organization->id, auth()->id());
        }

        session()->flash('status', 'Utworzono zgłoszenie '.$ticket->number.'.');

        return $this->redirectRoute('tickets.show', $ticket, navigate: true);
    }

    public function render()
    {
        $orgId = $this->organization_id;

        return view('livewire.tickets.create', [
            'organizations' => $this->availableOrganizations(),
            'locations' => $orgId
                ? Location::where('organization_id', $orgId)->orderBy('name')->get()
                : collect(),
            'assets' => $orgId
                ? Asset::where('organization_id', $orgId)->active()->where('is_private', false)->orderBy('name')->get()
                : collect(),
            'categories' => TicketCategory::active()->orderBy('name')->get(),
            'priorities' => TicketPriority::active()->orderBy('level')->get(),
        ]);
    }
}
