<?php

namespace App\Enums;

/**
 * Globalna klasyfikacja konta użytkownika.
 *
 * Role personelu systemu: super_admin, admin, support.
 * Role klienta: manager, user – ich autorytatywny zakres per organizacja
 * przechowuje tabela organization_memberships (patrz OrgRole).
 */
enum Role: string
{
    case SuperAdmin = 'super_admin';
    case Admin = 'admin';
    case Support = 'support';
    case Manager = 'manager';
    case User = 'user';

    public function label(): string
    {
        return __('enums.role.'.$this->value);
    }

    /** Personel właściciela systemu (nie jest członkiem organizacji klienta). */
    public function isStaff(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin, self::Support], true);
    }

    /** Użytkownik klienta (członek organizacji). */
    public function isClient(): bool
    {
        return in_array($this, [self::Manager, self::User], true);
    }

    public function isAdminLevel(): bool
    {
        return in_array($this, [self::SuperAdmin, self::Admin], true);
    }

    /** @return array<string,string> value => label – do selectów. */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $r) => [$r->value => $r->label()])
            ->all();
    }
}
