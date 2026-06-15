<?php

namespace App\Enums;

/** Typ relacji/zależności między zasobami (asset_relations). */
enum AssetRelationType: string
{
    case DependsOn = 'depends_on';
    case RunsOn = 'runs_on';
    case InstalledOn = 'installed_on';
    case ConnectedTo = 'connected_to';
    case UsesLicense = 'uses_license';
    case BackedUpBy = 'backed_up_by';
    case RelatedTo = 'related_to';

    public function label(): string
    {
        return __('enums.asset_relation_type.'.$this->value);
    }

    /** @return array<string,string> */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $t) => [$t->value => $t->label()])
            ->all();
    }
}
