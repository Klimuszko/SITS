<?php

namespace App\Enums;

/** Typ dynamicznego pola zasobu (definicja w AssetField). */
enum AssetFieldType: string
{
    case Text = 'text';
    case Number = 'number';
    case Date = 'date';
    case Boolean = 'boolean';
    case Select = 'select';
    case Textarea = 'textarea';
    case Ip = 'ip';
    case Url = 'url';
    case Email = 'email';
    case File = 'file';
    case Relation = 'relation';

    public function label(): string
    {
        return __('enums.asset_field_type.'.$this->value);
    }

    /** Czy typ wymaga listy opcji (np. select). */
    public function hasOptions(): bool
    {
        return $this === self::Select;
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $t) => [$t->value => $t->label()])
            ->all();
    }
}
