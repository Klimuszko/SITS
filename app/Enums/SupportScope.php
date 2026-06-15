<?php

namespace App\Enums;

/** Zakres przypisania supportu do organizacji (support_assignments). */
enum SupportScope: string
{
    case Tickets = 'tickets';
    case Assets = 'assets';
    case Knowledge = 'knowledge';
    case All = 'all';

    public function label(): string
    {
        return __('enums.support_scope.'.$this->value);
    }

    /** Czy ten zakres obejmuje dany obszar działania. */
    public function covers(self $area): bool
    {
        return $this === self::All || $this === $area;
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->all();
    }
}
