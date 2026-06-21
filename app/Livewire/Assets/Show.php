<?php

namespace App\Livewire\Assets;

use App\Enums\AssetFieldType;
use App\Enums\AuditAction;
use App\Models\Asset;
use App\Models\AssetField;
use App\Models\AssetSection;
use App\Services\AssetService;
use App\Services\AssetStructure;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Zasób')]
class Show extends Component
{
    public Asset $asset;

    public function mount(Asset $asset): void
    {
        $this->authorize('view', $asset);
        $this->asset = $asset;
    }

    public function archive(AssetService $assets): void
    {
        $this->authorize('archive', $this->asset);

        $assets->archive($this->asset, auth()->user());

        $this->asset->refresh();
        session()->flash('status', 'Zasób został zarchiwizowany.');
    }

    /**
     * TRWAŁE usunięcie zasobu — wyłącznie Super Admin (gate force-delete,
     * sprawdzany serwerowo; przycisk w widoku to tylko wygoda). Audyt PRZED delete.
     * Pliki załączników (polimorfizm BEZ kaskady FK) usuwamy jawnie z dysku i
     * kasujemy wiersze. Asset ma SoftDeletes → forceDelete() dla prawdziwego,
     * nieodwracalnego usunięcia. Kaskada bazy usuwa wartości pól, wpisy grup wraz
     * z ich wartościami, relacje, przypisania i historię.
     *
     * REFERENCE-SAFE (zgłoszenia przychodzące): FK tickets.asset_id oraz
     * tickets.asset_group_entry_id są nullOnDelete — usunięcie zasobu zeruje
     * powiązania w zgłoszeniach (zgłoszenia przeżywają, tracą tylko link do zasobu),
     * baza nie rzuca wyjątkiem. Operacja nieodwracalna.
     */
    public function forceDelete()
    {
        $this->authorize('force-delete');

        AuditLogger::log(AuditAction::AssetDeleted, $this->asset);

        foreach ($this->asset->attachments as $attachment) {
            if (Storage::disk('local')->exists($attachment->path)) {
                Storage::disk('local')->delete($attachment->path);
            }
            $attachment->forceDelete();
        }

        $this->asset->forceDelete();

        session()->flash('status', 'Zasób został trwale usunięty.');

        return $this->redirectRoute('assets.index', navigate: true);
    }

    protected function structure(): AssetStructure
    {
        return app(AssetStructure::class);
    }

    /**
     * Drzewo sekcji do prezentacji. Każdy węzeł:
     *  - 'section'  => AssetSection,
     *  - 'fields'   => [ ['label'=>, 'value'=>], ... ] (tylko sekcje niepowtarzalne),
     *  - 'group'    => null | ['columns'=>[AssetField], 'rows'=>[ ['label'=>, 'cells'=>[fieldId=>str]] ]],
     *  - 'children' => Collection (rekurencyjnie).
     *
     * @return Collection<int,array<string,mixed>>
     */
    protected function sectionTree(): Collection
    {
        $category = $this->asset->category;
        if (! $category) {
            return collect();
        }

        $tree = $this->structure()->tree($category);

        // Wartości pól pojedynczych zasobu.
        $singleValues = $this->asset->fieldValues()->get()->keyBy('asset_field_id');

        // Wszystkie wpisy grup zasobu (z wartościami) — indeks [section][parent ?? 0].
        $entryIndex = [];
        foreach ($this->asset->groupEntries()->with('values')->get() as $entry) {
            $entryIndex[$entry->asset_section_id][$entry->parent_entry_id ?? 0][] = $entry;
        }

        $map = function (AssetSection $node) use (&$map, $singleValues, $entryIndex) {
            if ($node->is_repeatable) {
                return [
                    'section' => $node,
                    'fields' => collect(),
                    // Zagnieżdżone grupy renderujemy WEWNĄTRZ wpisów (buildGroupView),
                    // więc childNodes nie powielamy jako rodzeństwo.
                    'group' => $this->buildGroupView($node, null, 1, $entryIndex),
                    'children' => collect(),
                ];
            }

            $fields = $node->activeFields->map(function (AssetField $field) use ($singleValues) {
                return [
                    'label' => $field->name,
                    'value' => $this->castForDisplay($field, $singleValues->get($field->id)?->value),
                ];
            })->values();

            return [
                'section' => $node,
                'fields' => $fields,
                'group' => null,
                'children' => $node->childNodes->map(fn (AssetSection $c) => $map($c))->values(),
            ];
        };

        $nodes = $tree->map(fn (AssetSection $n) => $map($n))->values();

        // Pola pojedyncze bez sekcji (kategorie „płaskie” ze Step 3) — wirtualna sekcja.
        $looseFields = $this->structure()->singleFields($category)
            ->filter(fn (AssetField $f) => $f->asset_section_id === null)
            ->map(function (AssetField $field) use ($singleValues) {
                return [
                    'label' => $field->name,
                    'value' => $this->castForDisplay($field, $singleValues->get($field->id)?->value),
                ];
            })->values();

        if ($looseFields->isNotEmpty()) {
            $loose = new AssetSection(['name' => 'Pola kategorii']);

            $nodes->push([
                'section' => $loose,
                'fields' => $looseFields,
                'group' => null,
                'children' => collect(),
            ]);
        }

        return $nodes;
    }

    /**
     * Buduje REKURENCYJNY widok grupy powtarzalnej dla danego rodzica:
     *  - 'columns'     => aktywne pola grupy,
     *  - 'rows'        => wpisy (sort order) z 'cells' oraz 'children' (zagnieżdżone widoki),
     *  - 'hasChildren' => czy grupa ma zagnieżdżone grupy powtarzalne.
     *
     * Wpisy dziecka filtrowane po parent_entry_id; rekursja do MAX_GROUP_DEPTH.
     * Numeracja „#" liczona po POZYCJI w bieżącej liście (w bladzie: $i+1), nie po id.
     *
     * @param  array<int,array<int,array<int,\App\Models\AssetGroupEntry>>>  $index
     * @return array{columns:Collection<int,AssetField>,rows:Collection<int,array<string,mixed>>,hasChildren:bool}
     */
    protected function buildGroupView(AssetSection $group, ?int $parentEntryId, int $level, array $index): array
    {
        $columns = $group->activeFields;

        $childGroups = $level < AssetStructure::MAX_GROUP_DEPTH
            ? $this->structure()->repeatableChildren($group)
            : collect();

        $entries = collect($index[$group->id][$parentEntryId ?? 0] ?? [])->sortBy('order')->values();

        $rows = $entries->map(function ($entry) use ($columns, $childGroups, $level, $index) {
            $valuesByField = $entry->values->keyBy('asset_field_id');

            $cells = [];
            foreach ($columns as $field) {
                $cells[$field->id] = $this->castForDisplay($field, $valuesByField->get($field->id)?->value);
            }

            $children = $childGroups->map(fn (AssetSection $child) => [
                'section' => $child,
                'label' => $child->ticket_label ?: $child->name,
                'view' => $this->buildGroupView($child, $entry->id, $level + 1, $index),
            ])->values();

            return ['cells' => $cells, 'children' => $children];
        });

        return ['columns' => $columns, 'rows' => $rows, 'hasChildren' => $childGroups->isNotEmpty()];
    }

    /** Rzutuje surową wartość na czytelny tekst wg typu pola. */
    protected function castForDisplay(AssetField $field, ?string $raw): string
    {
        if ($raw === null || $raw === '') {
            return '—';
        }

        return match ($field->type) {
            AssetFieldType::Boolean => $raw === '1' ? 'Tak' : 'Nie',
            default => $raw,
        };
    }

    public function render()
    {
        $user = auth()->user();
        $this->asset->load(['organization', 'category', 'location', 'parent', 'createdBy']);

        return view('livewire.assets.show', [
            'sectionTree' => $this->sectionTree(),
            'history' => $this->asset->history()->with('user')->get(),
            'canUpdate' => $user->can('update', $this->asset),
            'canArchive' => $user->can('archive', $this->asset),
            'canForceDelete' => $user->isSuperAdmin(),
        ]);
    }
}
