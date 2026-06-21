<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\AuditAction;
use App\Models\Asset;
use App\Models\AssetField;
use App\Models\AssetFieldValue;
use App\Models\AssetGroupEntry;
use App\Models\AssetGroupEntryValue;
use App\Models\AssetHistory;
use App\Models\AssetSection;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Logika domenowa zasobów (CMDB): tworzenie i edycja zasobu wraz z dynamicznymi
 * wartościami pól, zapisem historii (asset_history) i audytem (§28).
 *
 * Wartości pól przechowywane są jako surowy string w asset_field_values.value;
 * rzutowanie do typu odbywa się przy wyświetlaniu.
 */
class AssetService
{
    /**
     * Tworzy zasób, zapisuje wartości pól (pojedyncze + grupy powtarzalne),
     * wpis historii (created) oraz audyt.
     *
     * @param  array<string,mixed>  $data          pola rdzeniowe zasobu
     * @param  array<int,mixed>     $fieldValues   [asset_field_id => surowa wartość] (pola pojedyncze)
     * @param  array<int,array<int,array{id?:int|null,values:array<int,mixed>}>>  $groupData
     *         [asset_section_id => [ ['id'=>?entryId, 'values'=>[asset_field_id=>wartość]], ... ] ]
     */
    public function create(User $actor, array $data, array $fieldValues = [], array $groupData = []): Asset
    {
        return DB::transaction(function () use ($actor, $data, $fieldValues, $groupData) {
            $asset = Asset::create([
                'organization_id' => $data['organization_id'],
                'location_id' => $data['location_id'] ?? null,
                'asset_category_id' => $data['asset_category_id'],
                'parent_asset_id' => $data['parent_asset_id'] ?? null,
                'name' => $data['name'],
                'inventory_code' => $data['inventory_code'] ?? null,
                'status' => $data['status'] ?? AssetStatus::Active->value,
                'is_private' => (bool) ($data['is_private'] ?? false),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->persistFieldValues($asset, $fieldValues);
            $this->reconcileGroups($asset, $groupData);

            AssetHistory::create([
                'asset_id' => $asset->id,
                'user_id' => $actor->id,
                'action' => 'created',
                'created_at' => now(),
            ]);

            AuditLogger::log(AuditAction::AssetCreated, $asset, null, [
                'name' => $asset->name,
                'organization_id' => $asset->organization_id,
                'asset_category_id' => $asset->asset_category_id,
            ]);

            return $asset;
        });
    }

    /**
     * Aktualizuje zasób: diff pól rdzeniowych i wartości dynamicznych; dla każdej
     * zmiany zapisuje wiersz historii (field_updated) i pojedynczy audyt AssetUpdated.
     *
     * @param  array<string,mixed>  $data
     * @param  array<int,mixed>     $fieldValues  [asset_field_id => surowa wartość] (pola pojedyncze)
     * @param  array<int,array<int,array{id?:int|null,values:array<int,mixed>}>>  $groupData
     *         [asset_section_id => [ ['id'=>?entryId, 'values'=>[asset_field_id=>wartość]], ... ] ]
     */
    public function update(Asset $asset, array $data, array $fieldValues, User $actor, array $groupData = []): Asset
    {
        return DB::transaction(function () use ($asset, $data, $fieldValues, $actor, $groupData) {
            $changes = [];

            // --- Pola rdzeniowe ---
            $coreFields = [
                'organization_id', 'location_id', 'asset_category_id', 'parent_asset_id',
                'name', 'inventory_code', 'status', 'is_private', 'notes',
            ];

            foreach ($coreFields as $column) {
                if (! array_key_exists($column, $data)) {
                    continue;
                }

                $new = $column === 'is_private' ? (bool) $data[$column] : $data[$column];
                $old = $asset->getAttribute($column);

                // Normalizacja enuma statusu do wartości skalarnej do porównania.
                $oldScalar = $old instanceof \BackedEnum ? $old->value : $old;
                $newScalar = $new instanceof \BackedEnum ? $new->value : $new;

                if ($oldScalar == $newScalar) {
                    continue;
                }

                $asset->setAttribute($column, $new);

                $this->recordHistory($asset, $actor, $column, $oldScalar, $newScalar);
                $changes[$column] = ['old' => $oldScalar, 'new' => $newScalar];
            }

            $asset->save();

            // --- Wartości pól dynamicznych ---
            $fields = $this->categoryFields($asset);

            foreach ($fields as $field) {
                if (! array_key_exists($field->id, $fieldValues)) {
                    continue;
                }

                $raw = $this->normalizeValue($field, $fieldValues[$field->id]);
                $existing = $asset->fieldValues->firstWhere('asset_field_id', $field->id);
                $oldValue = $existing?->value;

                if ((string) $oldValue === (string) $raw) {
                    continue;
                }

                AssetFieldValue::updateOrCreate(
                    ['asset_id' => $asset->id, 'asset_field_id' => $field->id],
                    ['value' => (string) $raw],
                );

                $this->recordHistory($asset, $actor, $field->key, $oldValue, (string) $raw);
                $changes[$field->key] = ['old' => $oldValue, 'new' => (string) $raw];
            }

            // --- Grupy powtarzalne (reconcile: dodaj/aktualizuj/usuń wpisy) ---
            if ($this->reconcileGroups($asset, $groupData)) {
                $this->recordHistory($asset, $actor, 'groups', null, 'updated');
                $changes['groups'] = ['old' => null, 'new' => 'updated'];
            }

            if ($changes !== []) {
                AuditLogger::log(
                    AuditAction::AssetUpdated,
                    $asset,
                    array_map(fn ($c) => $c['old'], $changes),
                    array_map(fn ($c) => $c['new'], $changes),
                );
            }

            return $asset->refresh();
        });
    }

    /** Archiwizacja zasobu (bez twardego usuwania) z historią i audytem. */
    public function archive(Asset $asset, User $actor): Asset
    {
        return DB::transaction(function () use ($asset, $actor) {
            $old = $asset->status instanceof \BackedEnum ? $asset->status->value : $asset->status;

            if ($old === AssetStatus::Archived->value) {
                return $asset;
            }

            $asset->status = AssetStatus::Archived;
            $asset->save();

            $this->recordHistory($asset, $actor, 'status', $old, AssetStatus::Archived->value);

            AuditLogger::log(
                AuditAction::AssetArchived,
                $asset,
                ['status' => $old],
                ['status' => AssetStatus::Archived->value],
            );

            return $asset;
        });
    }

    /**
     * Zapisuje wartości pól dynamicznych dla zasobu. Tylko pola należące do
     * kategorii zasobu i aktywne. Boolean zapisywany jako '1'/'0'.
     *
     * @param  array<int,mixed>  $fieldValues  [asset_field_id => surowa wartość]
     */
    protected function persistFieldValues(Asset $asset, array $fieldValues): void
    {
        if ($fieldValues === []) {
            return;
        }

        foreach ($this->categoryFields($asset) as $field) {
            if (! array_key_exists($field->id, $fieldValues)) {
                continue;
            }

            $raw = $this->normalizeValue($field, $fieldValues[$field->id]);

            AssetFieldValue::updateOrCreate(
                ['asset_id' => $asset->id, 'asset_field_id' => $field->id],
                ['value' => (string) $raw],
            );
        }
    }

    /**
     * Uzgadnia wpisy grup powtarzalnych zasobu z przesłanym zestawem ($groupData),
     * REKURENCYJNIE dla grup zagnieżdżonych (do AssetStructure::MAX_GROUP_DEPTH):
     *  - nowe wiersze (bez id) → tworzy (z parent_entry_id dla zagnieżdżonych),
     *  - istniejące (z id) → aktualizuje po sprawdzeniu WŁASNOŚCI (asset_id,
     *    asset_section_id ORAZ parent_entry_id zgodne z bieżącym poziomem),
     *  - wpisy nieobecne w zestawie → usuwa wraz z poddrzewem (deleteEntryTree),
     *  - `order` z pozycji w tablicy.
     *
     * @param  array<int,array<int,array{id?:int|null,values:array<int,mixed>,children?:array<int,mixed>}>>  $groupData
     * @return bool  czy cokolwiek zmieniono
     */
    protected function reconcileGroups(Asset $asset, array $groupData): bool
    {
        $category = $asset->category;

        if ($category === null) {
            return false;
        }

        $structure = app(AssetStructure::class);
        $topGroups = $structure->topRepeatableGroups($category);

        if ($topGroups->isEmpty()) {
            return false;
        }

        return $this->reconcileGroupLevel($asset, $topGroups, null, $groupData, 1, $structure);
    }

    /**
     * Jeden poziom rekonsyliacji wpisów grup dla danego rodzica (parent_entry_id).
     *
     * @param  Collection<int,AssetSection>  $groups
     * @param  array<int,mixed>              $levelData
     */
    protected function reconcileGroupLevel(Asset $asset, Collection $groups, ?int $parentEntryId, array $levelData, int $level, AssetStructure $structure): bool
    {
        $changed = false;

        foreach ($groups as $section) {
            $rows = $levelData[$section->id] ?? [];
            $fields = $section->activeFields;

            // Istniejące wpisy tej grupy dla tego zasobu i tego rodzica (własność).
            $existing = AssetGroupEntry::query()
                ->where('asset_id', $asset->id)
                ->where('asset_section_id', $section->id)
                ->when(
                    $parentEntryId === null,
                    fn ($q) => $q->whereNull('parent_entry_id'),
                    fn ($q) => $q->where('parent_entry_id', $parentEntryId),
                )
                ->get()
                ->keyBy('id');

            $keptIds = [];

            foreach (array_values(is_array($rows) ? $rows : []) as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }

                $entryId = $row['id'] ?? null;
                $values = $row['values'] ?? [];
                $childData = $row['children'] ?? [];

                if ($entryId !== null) {
                    $entry = $existing->get((int) $entryId);
                    if ($entry === null) {
                        continue; // obce / nieistniejące / spoza tego rodzica — pomijamy
                    }

                    if ($entry->order !== $index) {
                        $entry->order = $index;
                        $entry->save();
                        $changed = true;
                    }
                } else {
                    $entry = AssetGroupEntry::create([
                        'asset_id' => $asset->id,
                        'asset_section_id' => $section->id,
                        'parent_entry_id' => $parentEntryId,
                        'order' => $index,
                    ]);
                    $changed = true;
                }

                $keptIds[] = $entry->id;

                if ($this->persistGroupEntryValues($entry, $fields, is_array($values) ? $values : [])) {
                    $changed = true;
                }

                // Zagnieżdżone grupy powtarzalne tego wpisu (do MAX_GROUP_DEPTH).
                if ($level < AssetStructure::MAX_GROUP_DEPTH) {
                    $childGroups = $structure->repeatableChildren($section);
                    if ($childGroups->isNotEmpty()
                        && $this->reconcileGroupLevel($asset, $childGroups, $entry->id, is_array($childData) ? $childData : [], $level + 1, $structure)) {
                        $changed = true;
                    }
                }
            }

            // Wpisy nieobecne w zestawie → usuwamy wraz z poddrzewem (parent_entry_id
            // ma nullOnDelete, więc dzieci kasujemy jawnie, by nie osierocić wpisów).
            foreach ($existing as $id => $entry) {
                if (! in_array($id, $keptIds, true)) {
                    $this->deleteEntryTree($entry);
                    $changed = true;
                }
            }
        }

        return $changed;
    }

    /** Rekurencyjnie usuwa wpis grupy: najpierw dzieci, potem wartości, na końcu wpis. */
    protected function deleteEntryTree(AssetGroupEntry $entry): void
    {
        foreach ($entry->children()->get() as $child) {
            $this->deleteEntryTree($child);
        }

        AssetGroupEntryValue::where('asset_group_entry_id', $entry->id)->delete();
        $entry->delete();
    }

    /**
     * Upsert wartości pojedynczego wpisu grupy. Tylko aktywne pola tej grupy.
     *
     * @param  Collection<int,AssetField>  $fields
     * @param  array<int,mixed>            $values  [asset_field_id => surowa wartość]
     * @return bool  czy jakakolwiek wartość uległa zmianie
     */
    protected function persistGroupEntryValues(AssetGroupEntry $entry, Collection $fields, array $values): bool
    {
        $changed = false;

        foreach ($fields as $field) {
            if (! array_key_exists($field->id, $values)) {
                continue;
            }

            $raw = $this->normalizeValue($field, $values[$field->id]);

            $existing = AssetGroupEntryValue::query()
                ->where('asset_group_entry_id', $entry->id)
                ->where('asset_field_id', $field->id)
                ->first();

            if ($existing && (string) $existing->value === (string) $raw) {
                continue;
            }

            AssetGroupEntryValue::updateOrCreate(
                ['asset_group_entry_id' => $entry->id, 'asset_field_id' => $field->id],
                ['value' => (string) $raw],
            );

            $changed = true;
        }

        return $changed;
    }

    /** Aktywne pola należące do kategorii danego zasobu (po kluczu kategorii). */
    protected function categoryFields(Asset $asset): Collection
    {
        return AssetField::query()
            ->where('asset_category_id', $asset->asset_category_id)
            ->where('is_active', true)
            ->orderBy('order')
            ->get();
    }

    /** Normalizuje surową wartość do stringa zgodnie z typem pola. */
    protected function normalizeValue(AssetField $field, mixed $raw): string
    {
        if ($field->type === \App\Enums\AssetFieldType::Boolean) {
            return $raw ? '1' : '0';
        }

        return $raw === null ? '' : (string) $raw;
    }

    protected function recordHistory(Asset $asset, User $actor, string $field, ?string $old, ?string $new): void
    {
        AssetHistory::create([
            'asset_id' => $asset->id,
            'user_id' => $actor->id,
            'action' => 'field_updated',
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
            'created_at' => now(),
        ]);
    }
}
