<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\AuditAction;
use App\Models\Asset;
use App\Models\AssetField;
use App\Models\AssetFieldValue;
use App\Models\AssetHistory;
use App\Models\User;
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
     * Tworzy zasób, zapisuje wartości pól, wpis historii (created) oraz audyt.
     *
     * @param  array<string,mixed>  $data         pola rdzeniowe zasobu
     * @param  array<int,mixed>     $fieldValues  [asset_field_id => surowa wartość]
     */
    public function create(User $actor, array $data, array $fieldValues = []): Asset
    {
        return DB::transaction(function () use ($actor, $data, $fieldValues) {
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
     * @param  array<int,mixed>     $fieldValues  [asset_field_id => surowa wartość]
     */
    public function update(Asset $asset, array $data, array $fieldValues, User $actor): Asset
    {
        return DB::transaction(function () use ($asset, $data, $fieldValues, $actor) {
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

    /** Aktywne pola należące do kategorii danego zasobu (po kluczu kategorii). */
    protected function categoryFields(Asset $asset): \Illuminate\Support\Collection
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
