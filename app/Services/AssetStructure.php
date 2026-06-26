<?php

namespace App\Services;

use App\Enums\AssetFieldType;
use App\Models\AssetCategory;
use App\Models\AssetField;
use App\Models\AssetSection;
use Illuminate\Support\Collection;

/**
 * Rozwiązuje strukturę kategorii zasobu (14a) do uporządkowanego drzewa AKTYWNYCH
 * sekcji wraz z ich AKTYWNYMI polami. Współdzielone przez formularz (ManageForm),
 * widok szczegółów (Show) i — później — pod-zasoby w zgłoszeniach (14c).
 *
 * Każdy węzeł drzewa to AssetSection z dociążoną relacją `childNodes` (kolekcja
 * węzłów potomnych) oraz `activeFields` (kolekcja aktywnych, renderowalnych pól).
 *
 * Rozróżnienie:
 *  - Sekcja / Podsekcja (`is_repeatable = false`) → pola jako pojedyncze inputy,
 *    wartości w `asset_field_values`.
 *  - Grupa powtarzalna (`is_repeatable = true`) → wiele wpisów (asset_group_entries),
 *    wartości w `asset_group_entry_values`.
 *
 * Pola typu file/relation są pomijane (nieobsługiwane w 14b — patrz Known Gaps).
 */
class AssetStructure
{
    /** Typy pól nieobsługiwane w formularzu/widoku (v1). „relation" obsługiwany od Step 19. */
    public const SKIPPED_TYPES = [AssetFieldType::File];

    /**
     * Maksymalna głębokość zagnieżdżenia grup powtarzalnych (grupa w grupie).
     * Poziom 1 = grupa najwyższego poziomu; głębsze niż MAX są celowo ignorowane
     * w formularzu, walidacji, zapisie i widoku (twardy bezpiecznik).
     */
    public const MAX_GROUP_DEPTH = 3;

    /**
     * Drzewo aktywnych sekcji kategorii (korzenie wg `order`), każdy węzeł
     * z relacjami `childNodes` i `activeFields`.
     *
     * @return Collection<int,AssetSection>
     */
    public function tree(AssetCategory $category): Collection
    {
        $sections = $category->sections()
            ->where('is_active', true)
            ->orderBy('order')
            ->get();

        $fieldsBySection = $this->renderableFields($category)->groupBy('asset_section_id');
        $byParent = $sections->groupBy('parent_id');

        $attach = function (AssetSection $node) use (&$attach, $byParent, $fieldsBySection) {
            $node->setRelation('activeFields', $fieldsBySection->get($node->id, collect())->values());
            $node->setRelation(
                'childNodes',
                $byParent->get($node->id, collect())->map(fn (AssetSection $c) => $attach($c))->values()
            );

            return $node;
        };

        return $byParent->get(null, collect())
            ->map(fn (AssetSection $n) => $attach($n))
            ->values();
    }

    /**
     * Grupy powtarzalne NAJWYŻSZEGO poziomu (bez powtarzalnego przodka) — korzenie
     * rekurencji wpisów grup. Sekcje niepowtarzalne są „przezroczyste": schodzimy
     * przez nie, ale pierwsza napotkana grupa powtarzalna staje się korzeniem
     * (jej powtarzalne potomstwo to jej grupy-dzieci). Każdy węzeł zachowuje
     * relacje childNodes + activeFields z tree().
     *
     * @return Collection<int,AssetSection>
     */
    public function topRepeatableGroups(AssetCategory $category): Collection
    {
        $collect = function (Collection $nodes) use (&$collect): Collection {
            $out = collect();

            foreach ($nodes as $node) {
                if ($node->is_repeatable) {
                    $out->push($node);  // korzeń — nie schodzimy głębiej (dzieci ogarnia rekurencja wpisów)

                    continue;
                }

                $out = $out->merge($collect($node->childNodes));
            }

            return $out;
        };

        return $collect($this->tree($category))->values();
    }

    /**
     * Bezpośrednie powtarzalne dzieci węzła = grupy zagnieżdżone w grupie.
     * Sekcje niepowtarzalne wewnątrz grupy powtarzalnej są poza zakresem v1.
     *
     * @return Collection<int,AssetSection>
     */
    public function repeatableChildren(AssetSection $node): Collection
    {
        return $node->childNodes->where('is_repeatable', true)->values();
    }

    /**
     * Aktywne, renderowalne pola kategorii NIENALEŻĄCE do żadnej grupy powtarzalnej
     * — wartości tych pól żyją w `asset_field_values`.
     *
     * @return Collection<int,AssetField>
     */
    public function singleFields(AssetCategory $category): Collection
    {
        $repeatableSectionIds = $this->repeatableSectionIds($category);

        return $this->renderableFields($category)->reject(
            fn (AssetField $f) => $f->asset_section_id !== null
                && $repeatableSectionIds->contains($f->asset_section_id)
        )->values();
    }

    /**
     * Aktywne grupy powtarzalne kategorii (sekcje is_repeatable = true), każda
     * z dociążoną relacją `activeFields` (renderowalne pola tej grupy).
     *
     * @return Collection<int,AssetSection>
     */
    public function repeatableGroups(AssetCategory $category): Collection
    {
        $fieldsBySection = $this->renderableFields($category)->groupBy('asset_section_id');

        return $category->sections()
            ->where('is_active', true)
            ->where('is_repeatable', true)
            ->orderBy('order')
            ->get()
            ->map(function (AssetSection $section) use ($fieldsBySection) {
                $section->setRelation('activeFields', $fieldsBySection->get($section->id, collect())->values());

                return $section;
            })
            ->values();
    }

    /**
     * Aktywne, renderowalne pola całej kategorii (z pominięciem file/relation),
     * wg `order`.
     *
     * @return Collection<int,AssetField>
     */
    public function renderableFields(AssetCategory $category): Collection
    {
        return AssetField::query()
            ->where('asset_category_id', $category->id)
            ->where('is_active', true)
            ->orderBy('order')
            ->get()
            ->reject(fn (AssetField $f) => in_array($f->type, self::SKIPPED_TYPES, true))
            ->values();
    }

    /**
     * Identyfikatory aktywnych sekcji powtarzalnych tej kategorii.
     *
     * @return Collection<int,int>
     */
    protected function repeatableSectionIds(AssetCategory $category): Collection
    {
        return $category->sections()
            ->where('is_active', true)
            ->where('is_repeatable', true)
            ->pluck('id');
    }
}
