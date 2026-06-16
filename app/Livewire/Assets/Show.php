<?php

namespace App\Livewire\Assets;

use App\Enums\AssetFieldType;
use App\Models\Asset;
use App\Models\AssetField;
use App\Services\AssetService;
use Illuminate\Support\Collection;
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
     * Pary [etykieta, wartość-do-wyświetlenia] dla aktywnych pól kategorii.
     * Pomija typy file/relation (nieobsługiwane w v1).
     *
     * @return Collection<int,array{label:string,value:string}>
     */
    protected function fieldDisplay(): Collection
    {
        $fields = AssetField::query()
            ->where('asset_category_id', $this->asset->asset_category_id)
            ->where('is_active', true)
            ->orderBy('order')
            ->get()
            ->reject(fn (AssetField $f) => in_array($f->type, [AssetFieldType::File, AssetFieldType::Relation], true));

        $values = $this->asset->fieldValues()->get()->keyBy('asset_field_id');

        return $fields->map(function (AssetField $field) use ($values) {
            $raw = $values->get($field->id)?->value;

            return [
                'label' => $field->name,
                'value' => $this->castForDisplay($field, $raw),
            ];
        })->values();
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
            'fields' => $this->fieldDisplay(),
            'history' => $this->asset->history()->with('user')->get(),
            'canUpdate' => $user->can('update', $this->asset),
            'canArchive' => $user->can('archive', $this->asset),
        ]);
    }
}
