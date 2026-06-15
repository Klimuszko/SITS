<?php

namespace App\Enums;

/**
 * Zakres widoczności managera w organizacji.
 * Na start używamy OwnUnit; architektura przewiduje rozszerzenie.
 */
enum ManagerScope: string
{
    case OwnUnit = 'own_unit';
    case OwnUnitAndChildren = 'own_unit_and_children';
    case WholeCompany = 'whole_company';

    public function label(): string
    {
        return __('enums.manager_scope.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->all();
    }
}
