<?php

namespace App\Enums;

enum LocationType: string
{
    case Building = 'building';
    case Floor = 'floor';
    case Room = 'room';
    case ServerRoom = 'server_room';
    case Rack = 'rack';
    case Other = 'other';

    public function label(): string
    {
        return __('enums.location_type.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $t) => [$t->value => $t->label()])
            ->all();
    }
}
