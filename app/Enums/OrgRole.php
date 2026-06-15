<?php

namespace App\Enums;

/** Rola użytkownika w ramach konkretnej organizacji (membership). */
enum OrgRole: string
{
    case User = 'user';
    case Manager = 'manager';

    public function label(): string
    {
        return __('enums.org_role.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $r) => [$r->value => $r->label()])
            ->all();
    }
}
