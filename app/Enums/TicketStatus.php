<?php

namespace App\Enums;

/**
 * Statusy ticketów. Implementowane jako enum, ponieważ mają sztywną
 * logikę biznesową (np. zamknięcie tylko ręczne, user nie zamyka ticketu).
 */
enum TicketStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case WaitingUser = 'waiting_user';
    case Resolved = 'resolved';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('enums.ticket_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::New => 'blue',
            self::InProgress => 'amber',
            self::WaitingUser => 'orange',
            self::Resolved => 'teal',
            self::Closed => 'gray',
            self::Cancelled => 'slate',
        };
    }

    /** Ticket otwarty (wymaga jeszcze działań). */
    public function isOpen(): bool
    {
        return ! $this->isTerminal();
    }

    /** Stan końcowy – ticket zamknięty/anulowany. */
    public function isTerminal(): bool
    {
        return in_array($this, [self::Closed, self::Cancelled], true);
    }

    /** Statusy, do których ticket może przejść z bieżącego (uproszczony workflow). */
    public function isOpenStatuses(): bool
    {
        return $this->isOpen();
    }

    /** @return array<int,self> Statusy uznawane za "otwarte". */
    public static function openCases(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $s) => $s->isOpen()
        ));
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->all();
    }
}
