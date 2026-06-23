{{--
    Wiersz pojedynczego pola w drzewie struktury.
    Oczekuje: $field (AssetField), $canForceDelete (bool).
--}}
<div class="tree-field">
    <div class="tree-field__label">
        <span class="tree-field__icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16"/><path d="M4 12h10"/><path d="M4 17h7"/></svg>
        </span>
        <strong>{{ $field->name }}</strong>
        <span class="badge badge--slate">{{ $field->type->label() }}</span>
        @if ($field->is_required)
            <span class="badge badge--gray">wymagane</span>
        @endif
        @if (! $field->is_active)
            <span class="badge badge--gray">nieaktywne</span>
        @endif
    </div>

    <div class="tree-actions">
        <button type="button" class="btn btn--ghost btn--sm" wire:click="moveFieldUp({{ $field->id }})" title="Przenieś wyżej" aria-label="Przenieś wyżej">↑</button>
        <button type="button" class="btn btn--ghost btn--sm" wire:click="moveFieldDown({{ $field->id }})" title="Przenieś niżej" aria-label="Przenieś niżej">↓</button>
        <button type="button" class="btn btn--ghost btn--sm" wire:click="editField({{ $field->id }})">Edytuj</button>
        <button type="button" class="btn btn--ghost btn--sm" wire:click="duplicateField({{ $field->id }})">Kopiuj</button>
        @if ($field->is_active)
            <button type="button" class="btn btn--ghost btn--sm"
                    wire:click="deactivateField({{ $field->id }})"
                    wire:confirm="Dezaktywować to pole? Dotychczasowe wartości w zasobach zostaną zachowane.">
                Dezaktywuj
            </button>
        @else
            <button type="button" class="btn btn--ghost btn--sm" wire:click="reactivateField({{ $field->id }})">Reaktywuj</button>
        @endif
        @if ($canForceDelete)
            <button type="button" class="btn btn--danger btn--sm"
                    wire:click="forceDeleteField({{ $field->id }})"
                    wire:confirm="Trwale usunie pole i WSZYSTKIE jego zapisane wartości we wszystkich zasobach. Operacja jest nieodwracalna. Kontynuować?">
                Usuń trwale
            </button>
        @endif
    </div>
</div>
