<?php

namespace App\Enums;

enum OrganizationType: string
{
    case Company = 'company';
    case Branch = 'branch';
    case Department = 'department';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.organization_type.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $t) => [$t->value => $t->label()])
            ->all();
    }
}
