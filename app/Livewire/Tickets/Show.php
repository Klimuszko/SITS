<?php

namespace App\Livewire\Tickets;

use App\Enums\AuditAction;
use App\Enums\CommentType;
use App\Enums\TicketStatus;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Services\AttachmentService;
use App\Services\AuditLogger;
use App\Services\TicketService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
#[Title('Zgłoszenie')]
class Show extends Component
{
    use WithFileUploads;

    public Ticket $ticket;

    public string $newComment = '';
    public string $internalNote = '';
    public string $closeReason = '';

    public ?string $selectedStatus = null;
    public ?int $selectedSupport = null;
    public ?int $selectedObserver = null;

    /** @var array<int,\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $files = [];

    public function mount(Ticket $ticket): void
    {
        $this->authorize('view', $ticket);
        $this->ticket = $ticket;
        $this->selectedStatus = $ticket->status->value;
        $this->selectedSupport = $ticket->assigned_support_id;
    }

    protected function isStaff(): bool
    {
        return auth()->user()->isStaff();
    }

    /* ------------------------------- Komentarze ------------------------------ */

    public function addComment(TicketService $tickets): void
    {
        $this->authorize('comment', $this->ticket);
        $this->validate(['newComment' => ['required', 'string']]);

        $this->ticket->comments()->create([
            'user_id' => auth()->id(),
            'type' => CommentType::Public,
            'body' => $this->newComment,
        ]);

        $this->touchReply(staffReply: $this->isStaff());

        // Odpowiedź klienta zdejmuje status "Oczekuje na użytkownika".
        if (! $this->isStaff() && $this->ticket->status === TicketStatus::WaitingUser) {
            $tickets->changeStatus($this->ticket, TicketStatus::InProgress, auth()->user());
        }

        AuditLogger::log(AuditAction::TicketCommented, $this->ticket);

        $this->reset('newComment');
        $this->ticket->refresh();
    }

    public function addInternalNote(): void
    {
        $this->authorize('addInternalNote', $this->ticket);
        $this->validate(['internalNote' => ['required', 'string']]);

        $this->ticket->comments()->create([
            'user_id' => auth()->id(),
            'type' => CommentType::Internal,
            'body' => $this->internalNote,
        ]);

        AuditLogger::log(AuditAction::TicketInternalNote, $this->ticket);

        $this->reset('internalNote');
        $this->ticket->refresh();
    }

    public function requestClose(TicketService $tickets): void
    {
        $this->authorize('requestClose', $this->ticket);
        $this->validate(
            ['closeReason' => ['required', 'string', 'min:3']],
            ['closeReason.required' => 'Podaj powód prośby o zamknięcie (pole obowiązkowe).'],
        );

        $tickets->requestClose($this->ticket, auth()->user(), $this->closeReason);

        $this->reset('closeReason');
        $this->ticket->refresh();
        session()->flash('status', 'Wysłano prośbę o zamknięcie zgłoszenia.');
    }

    /* ------------------------------- Zarządzanie ----------------------------- */

    public function changeStatus(TicketService $tickets): void
    {
        $this->authorize('manage', $this->ticket);
        $this->validate(['selectedStatus' => ['required', Rule::enum(TicketStatus::class)]]);

        $tickets->changeStatus($this->ticket, TicketStatus::from($this->selectedStatus), auth()->user());
        $this->ticket->refresh();
        session()->flash('status', 'Zmieniono status zgłoszenia.');
    }

    public function assignSupport(TicketService $tickets): void
    {
        $this->authorize('manage', $this->ticket);

        $supporters = $this->ticket->organization->supporters()->get();
        $this->validate([
            'selectedSupport' => ['nullable', 'integer', Rule::in($supporters->pluck('id')->all())],
        ]);

        $old = $this->ticket->assigned_support_id;
        $new = $this->selectedSupport;

        // Brak realnej zmiany przypisania – nic nie zapisujemy (bez pustego wpisu systemowego).
        if ($old === $new) {
            session()->flash('status', 'Zaktualizowano przypisanie zgłoszenia.');

            return;
        }

        $this->ticket->assigned_support_id = $new;
        $this->ticket->save();

        AuditLogger::log(AuditAction::TicketAssigned, $this->ticket,
            ['assigned_support_id' => $old],
            ['assigned_support_id' => $new]);

        if ($new) {
            $name = $supporters->firstWhere('id', $new)?->name;
            $tickets->systemComment($this->ticket, 'Przypisano do '.$name, auth()->id());
        } else {
            $tickets->systemComment($this->ticket, 'Zdjęto przypisanie', auth()->id());
        }

        $this->ticket->refresh();
        session()->flash('status', 'Zaktualizowano przypisanie zgłoszenia.');
    }

    /* ------------------------------- Obserwatorzy ---------------------------- */

    public function addObserver(): void
    {
        $this->authorize('manage', $this->ticket);

        $candidateIds = $this->observerCandidates()->pluck('id')->all();
        $this->validate(['selectedObserver' => ['required', 'integer', Rule::in($candidateIds)]]);

        $this->ticket->observers()->syncWithoutDetaching([$this->selectedObserver]);
        $this->reset('selectedObserver');
        $this->ticket->refresh();
    }

    public function removeObserver(int $userId): void
    {
        $this->authorize('manage', $this->ticket);
        $this->ticket->observers()->detach($userId);
        $this->ticket->refresh();
    }

    /* -------------------------------- Załączniki ----------------------------- */

    public function uploadFiles(AttachmentService $attachments): void
    {
        $this->authorize('comment', $this->ticket);
        $this->validate(['files.*' => ['file', 'max:20480']]);

        foreach ($this->files as $file) {
            $attachments->store($file, $this->ticket, $this->ticket->organization_id, auth()->id());
        }

        $this->reset('files');
        $this->ticket->refresh();
        session()->flash('status', 'Dodano załączniki.');
    }

    public function deleteAttachment(int $attachmentId): void
    {
        $attachment = Attachment::findOrFail($attachmentId);
        $this->authorize('delete', $attachment);

        if (Storage::disk('local')->exists($attachment->path)) {
            Storage::disk('local')->delete($attachment->path);
        }
        $attachment->delete();
        $this->ticket->refresh();
    }

    /* -------------------------------- Pomocnicze ----------------------------- */

    protected function touchReply(bool $staffReply): void
    {
        $this->ticket->last_reply_at = now();
        if ($staffReply && ! $this->ticket->first_response_at) {
            $this->ticket->first_response_at = now();
        }
        $this->ticket->save();
    }

    /** Kandydaci na obserwatorów: członkowie organizacji + przypisany support. */
    protected function observerCandidates()
    {
        $members = $this->ticket->organization->members()->wherePivot('is_active', true)->get();
        $supporters = $this->ticket->organization->supporters()->wherePivot('is_active', true)->get();

        return $members->merge($supporters)
            ->unique('id')
            ->reject(fn ($u) => $this->ticket->observers->contains('id', $u->id))
            ->values();
    }

    public function render()
    {
        $user = auth()->user();
        $this->ticket->load(['organization', 'requester', 'location', 'asset', 'assignedSupport', 'priority', 'category', 'observers', 'attachments.uploader', 'assetGroupEntry.asset', 'assetGroupEntry.values', 'assetGroupEntry.section.displayField']);

        $comments = $this->ticket->comments()->with('author')->get();
        if (! $this->isStaff()) {
            $comments = $comments->reject(fn ($c) => $c->isInternal())->values();
        }

        return view('livewire.tickets.show', [
            'comments' => $comments,
            'statuses' => TicketStatus::options(),
            'supporters' => $this->ticket->organization->supporters()->wherePivot('is_active', true)->orderBy('name')->get(),
            'observerCandidates' => $this->isStaff() ? $this->observerCandidates() : collect(),
            'canManage' => $user->can('manage', $this->ticket),
            'canInternal' => $user->can('addInternalNote', $this->ticket),
            'canRequestClose' => $user->can('requestClose', $this->ticket),
            'isStaff' => $this->isStaff(),
        ]);
    }
}
