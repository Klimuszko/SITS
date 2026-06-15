<?php

namespace App\Enums;

/** Status publikacji – wspólny dla artykułów bazy wiedzy i prac administracyjnych. */
enum PublicationStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function label(): string
    {
        return __('enums.publication_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'amber',
            self::Published => 'green',
            self::Archived => 'slate',
        };
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $s) => [$s->value => $s->label()])
            ->all();
    }
}
