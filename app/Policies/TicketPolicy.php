<?php

namespace App\Policies;

use App\Models\Ticket;
use App\Models\User;

class TicketPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        if ($user->isAdminLevel()) {
            return true;
        }

        if ($user->isSupport()) {
            return $user->supportsOrganization($ticket->organization_id);
        }

        // Manager widzi tickety swojej organizacji.
        if ($user->isManagerOf($ticket->organization_id)) {
            return true;
        }

        // User: własny ticket lub bycie obserwatorem.
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
        return $user->isAdminLevel()
            || ($user->isSupport() && $user->supportsOrganization($ticket->organization_id));
    }

    /** Zmiana statusu / przypisanie – personel obsługujący. */
    public function manage(User $user, Ticket $ticket): bool
    {
        return $user->isAdminLevel()
            || ($user->isSupport() && $user->supportsOrganization($ticket->organization_id));
    }

    /** Ręczne zamknięcie – tylko personel (user NIE może zamknąć). */
    public function close(User $user, Ticket $ticket): bool
    {
        return $this->manage($user, $ticket);
    }

    /** Prośba o zamknięcie – zgłaszający klient (obowiązkowy powód po stronie formularza). */
    public function requestClose(User $user, Ticket $ticket): bool
    {
        if (! $ticket->isOpen()) {
            return false;
        }

        return $ticket->requester_id === $user->id
            || $user->isManagerOf($ticket->organization_id);
    }
}
