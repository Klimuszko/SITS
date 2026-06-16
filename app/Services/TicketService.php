<?php

namespace App\Services;

use App\Enums\AuditAction;
use App\Enums\CommentType;
use App\Enums\TicketStatus;
use App\Models\Organization;
use App\Models\Ticket;
use App\Models\TicketComment;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Logika domenowa ticketów: numeracja, automatyczne przypisanie do
 * głównego supporta organizacji, zmiany statusu, prośba o zamknięcie.
 */
class TicketService
{
    /**
     * Tworzy ticket i automatycznie przypisuje go do głównego supporta organizacji.
     *
     * @param  array<string,mixed>  $data
     */
    public function create(User $requester, Organization $organization, array $data): Ticket
    {
        $primarySupportId = $organization->default_support_user_id
            ?? $organization->supportAssignments()
                ->where('is_primary', true)
                ->where('is_active', true)
                ->value('support_user_id');

        // tickets.number jest UNIQUE, a nextNumber() = count()+1 może się powtórzyć
        // przy współbieżnym tworzeniu. Pętla ponawiająca otacza CAŁĄ transakcję, więc
        // każda próba to świeża transakcja: kolizja (UniqueConstraintViolationException)
        // czysto wycofuje próbę, a kolejna re-BEGIN-uje i przelicza nextNumber().
        // Na PostgreSQL ponawianie WEWNĄTRZ transakcji nie zadziała – po 23505 cała
        // transakcja przechodzi w stan aborted (25P02). Działa na PostgreSQL i SQLite.
        $maxAttempts = 5;

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($requester, $organization, $data, $primarySupportId) {
                    $ticket = Ticket::create([
                        'number' => $this->nextNumber(),
                        'title' => $data['title'],
                        'description' => $data['description'],
                        'requester_id' => $requester->id,
                        'organization_id' => $organization->id,
                        'location_id' => $data['location_id'] ?? null,
                        'asset_id' => $data['asset_id'] ?? null,
                        'assigned_support_id' => $primarySupportId,
                        'status' => $primarySupportId ? TicketStatus::Assigned : TicketStatus::New,
                        'ticket_priority_id' => $data['ticket_priority_id'] ?? null,
                        'ticket_category_id' => $data['ticket_category_id'] ?? null,
                        'last_reply_at' => now(),
                    ]);

                    AuditLogger::log(AuditAction::TicketCreated, $ticket, null, [
                        'number' => $ticket->number,
                        'organization_id' => $organization->id,
                        'assigned_support_id' => $primarySupportId,
                    ]);

                    return $ticket;
                });
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }
                // Numer zajęty przez równoległy zapis – kolejna iteracja to świeża
                // transakcja, która przeliczy nextNumber() i ponowi INSERT.
            }
        }
    }

    /** Zmiana statusu z zapisem dat i audytem. */
    public function changeStatus(Ticket $ticket, TicketStatus $status, ?User $actor = null): Ticket
    {
        $old = $ticket->status;

        $ticket->status = $status;

        match ($status) {
            TicketStatus::Resolved => $ticket->resolved_at = now(),
            TicketStatus::Closed => $ticket->closed_at = now(),
            default => null,
        };

        $ticket->save();

        AuditLogger::log(AuditAction::TicketStatusChanged, $ticket,
            ['status' => $old->value],
            ['status' => $status->value],
        );

        return $ticket;
    }

    /** Prośba użytkownika o zamknięcie ticketu (obowiązkowy powód). */
    public function requestClose(Ticket $ticket, User $user, string $reason): TicketComment
    {
        $comment = $ticket->comments()->create([
            'user_id' => $user->id,
            'type' => CommentType::CloseRequest,
            'body' => $reason,
        ]);

        $ticket->forceFill(['last_reply_at' => now()])->save();

        AuditLogger::log(AuditAction::TicketCloseRequested, $ticket, null, ['reason' => $reason]);

        return $comment;
    }

    /** Generuje kolejny numer ticketu w formacie T-RRRR-NNNNNN. */
    protected function nextNumber(): string
    {
        $year = now()->year;
        $count = Ticket::withTrashed()
            ->whereYear('created_at', $year)
            ->count() + 1;

        return sprintf('T-%d-%06d', $year, $count);
    }
}
