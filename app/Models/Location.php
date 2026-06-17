<?php

namespace App\Models;

use App\Enums\LocationType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

class Location extends Model
{
    use HasFactory, SoftDeletes;

    /** Defensywny limit głębokości — zabezpiecza przed cyklem parent_id. */
    private const PATH_MAX_DEPTH = 50;

    protected $fillable = [
        'organization_id',
        'parent_id',
        'name',
        'type',
        'description',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => LocationType::class,
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * Pełna ścieżka lokalizacji, np. „Budynek A / Piętro 1 / Pomieszczenie 119”.
     *
     * Idzie po łańcuchu rodziców. Jeśli relacja `parent` jest już załadowana
     * (eager load / ręcznie ustawiona), korzysta z niej i NIE wykonuje zapytań —
     * dzięki temu wywołanie na elementach drzewa zbudowanego w pamięci jest bez N+1.
     * Zabezpieczone limitem głębokości na wypadek cyklu w `parent_id`.
     */
    public function pathLabel(string $sep = ' / '): string
    {
        $names = [];
        $node = $this;
        $depth = 0;

        while ($node !== null && $depth < self::PATH_MAX_DEPTH) {
            array_unshift($names, $node->name);

            // Korzeń — brak rodzica; przerwij bez dodatkowego zapytania.
            if ($node->parent_id === null) {
                break;
            }

            // Użyj już załadowanej relacji, jeśli istnieje (brak zapytania);
            // w przeciwnym razie dociągnij rodzica (akceptowalne dla pojedynczego rekordu).
            $node = $node->relationLoaded('parent') ? $node->getRelation('parent') : $node->parent;
            $depth++;
        }

        return implode($sep, $names);
    }

    /**
     * Lokalizacje organizacji uporządkowane jak drzewo (rodzic przed dziećmi,
     * przejście w głąb), każda z dowiązaną relacją `parent` z tej samej kolekcji —
     * wszystko z JEDNEGO zapytania. Idealne do listy rozwijanej (pełna ścieżka,
     * dziecko pod rodzicem) bez N+1.
     *
     * @return Collection<int,Location>
     */
    public static function treeForOrganization(int $organizationId): Collection
    {
        $all = static::query()
            ->where('organization_id', $organizationId)
            ->orderBy('name')
            ->get();

        return static::orderAsTree($all);
    }

    /**
     * Mapa „id lokalizacji → pełna ścieżka” dla zbioru identyfikatorów.
     *
     * Ładuje wszystkie lokalizacje organizacji, których dotyczą podane id, w JEDNYM
     * zapytaniu, dowiązuje rodziców w pamięci i rozwiązuje ścieżki w PHP — bez N+1.
     * Przeznaczone dla list (np. kolumna lokalizacji w wykazie zasobów).
     *
     * @param  iterable<int|null>  $locationIds
     * @return array<int,string>
     */
    public static function pathMapForIds(iterable $locationIds): array
    {
        $ids = collect($locationIds)
            ->filter(fn ($id) => $id !== null)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->all();

        if ($ids === []) {
            return [];
        }

        // Organizacje obecne na stronie — jednym zapytaniem pobierz ich pełne drzewa,
        // aby rodzice spoza widocznych id również byli dostępni do złożenia ścieżki.
        $orgIds = static::query()
            ->whereIn('id', $ids)
            ->distinct()
            ->pluck('organization_id')
            ->all();

        $locations = static::query()
            ->whereIn('organization_id', $orgIds)
            ->get();

        static::linkParents($locations);

        $map = [];
        foreach ($locations as $location) {
            if (in_array($location->id, $ids, true)) {
                $map[$location->id] = $location->pathLabel();
            }
        }

        return $map;
    }

    /**
     * Dowiąż relację `parent` każdego elementu do innego elementu tej samej kolekcji
     * (z pamięci, bez zapytań). Rodzice spoza kolekcji pozostają nieustawieni.
     *
     * @param  Collection<int,Location>  $locations
     */
    private static function linkParents(Collection $locations): void
    {
        $byId = $locations->keyBy('id');

        foreach ($locations as $location) {
            $location->setRelation('parent', $location->parent_id !== null
                ? $byId->get($location->parent_id)
                : null);
        }
    }

    /**
     * Uporządkuj kolekcję jako drzewo: rodzic przed dziećmi, przejście w głąb,
     * rodzeństwo w kolejności wejściowej. Dowiązuje relację `parent` w pamięci.
     *
     * @param  Collection<int,Location>  $locations
     * @return Collection<int,Location>
     */
    private static function orderAsTree(Collection $locations): Collection
    {
        static::linkParents($locations);

        $childrenByParent = [];
        foreach ($locations as $location) {
            $childrenByParent[$location->parent_id ?? 0][] = $location;
        }

        $existingIds = $locations->keyBy('id');
        $ordered = collect();
        $depthGuard = 0;

        $walk = function (int $parentKey) use (&$walk, &$ordered, $childrenByParent, &$depthGuard): void {
            if ($depthGuard++ > self::PATH_MAX_DEPTH) {
                return;
            }

            foreach ($childrenByParent[$parentKey] ?? [] as $node) {
                $ordered->push($node);
                $walk($node->id);
            }

            $depthGuard--;
        };

        // Korzenie: brak rodzica LUB rodzic spoza kolekcji (np. odfiltrowany) —
        // traktuj jako element najwyższego poziomu, żeby nie zgubić poddrzewa.
        foreach ($locations as $location) {
            $isRoot = $location->parent_id === null || $existingIds->get($location->parent_id) === null;
            if ($isRoot) {
                $ordered->push($location);
                $walk($location->id);
            }
        }

        return $ordered;
    }
}
