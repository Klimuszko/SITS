<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Ticket;
use App\Models\User;

/**
 * Capability („CO") rozstrzyga User::hasPermission (profile dostępu z fallbackiem
 * do domyślnych uprawnień roli). Zakres („GDZIE") i logika rekordu pozostają tutaj:
 * personel — reachesOrganization (admin = wszystkie, support = przypisane); klient —
 * profil członkostwa w organizacji + własność/obserwacja zgłoszenia.
 */
class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->isStaff()) {
            return $user->hasPermission(Permission::TicketsView, $ticket)
                && $user->reachesOrganization($ticket->organization_id);
        }

        // Manager: tickets.view w swojej organizacji → wszystkie zgłoszenia org.
        if ($user->hasPermission(Permission::TicketsView, $ticket)) {
            return true;
        }

        // User: własne zgłoszenie lub bycie obserwatorem.
        return $ticket->requester_id === $user->id
            || $ticket->observers->contains('id', $user->id);
    }

    /** Tworzyć tickety mogą zalogowani klienci (dla swojej organizacji) oraz personel. */
    public function create(User $user): bool
    {
        return $user->is_active;
    }

    /** Publiczna odpowiedź w tickecie. */
    public function comment(User $user, Ticket $ticket): bool
    {
        return $this->view($user, $ticket);
    }

    /** Notatka wewnętrzna – tylko personel obsługujący. */
    public function addInternalNote(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission(Permission::TicketsInternalNote)
            && $user->reachesOrganization($ticket->organization_id);
    }

    /** Zmiana statusu / przypisanie – personel obsługujący. */
    public function manage(User $user, Ticket $ticket): bool
    {
        return $user->hasPermission(Permission::TicketsManage)
            && $user->reachesOrganization($ticket->organization_id);
    }

    /** Ręczne zamknięcie – tylko personel (user NIE może zamknąć). */
    public function close(User $user, Ticket $ticket): bool
    {
        return $this->manage($user, $ticket);
    }

    /** Prośba o zamknięcie – zgłaszający klient lub manager organizacji. */
    public function requestClose(User $user, Ticket $ticket): bool
    {
        if (! $ticket->isOpen()) {
            return false;
        }

        return $ticket->requester_id === $user->id
            || $user->isManagerOf($ticket->organization_id);
    }
}
