{{--
    Rekurencyjny węzeł drzewa struktury wraz z polami i akcjami kontekstowymi.
    Oczekuje: $node (AssetSection z relacjami childNodes + fields), $depth (int),
    $canForceDelete (bool).
--}}
<div class="tree-node" style="margin-left:{{ $depth * 22 }}px">
    <div class="tree-node__row">
        <div class="tree-node__label">
            <span class="tree-node__icon" aria-hidden="true">
                @if (filled($node->icon))
                    {!! $node->icon !!}
                @else
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                @endif
            </span>
            <strong>{{ $node->name }}</strong>

            @if ($node->is_repeatable)
                <span class="badge badge--blue">Grupa powtarzalna</span>
                @if ($node->is_ticket_linkable)
                    <span class="badge badge--blue">Pod-zasób w zgłoszeniach</span>
                @endif
            @elseif ($node->parent_id)
                <span class="badge badge--gray">Podsekcja</span>
            @else
                <span class="badge badge--gray">Sekcja</span>
            @endif

            @if (! $node->is_active)
                <span class="badge badge--gray">Nieaktywna</span>
            @endif
        </div>

        <div class="tree-actions">
            <button type="button" class="btn btn--ghost btn--sm" wire:click="moveSectionUp({{ $node->id }})" title="Przenieś wyżej" aria-label="Przenieś wyżej">↑</button>
            <button type="button" class="btn btn--ghost btn--sm" wire:click="moveSectionDown({{ $node->id }})" title="Przenieś niżej" aria-label="Przenieś niżej">↓</button>
            <button type="button" class="btn btn--ghost btn--sm" wire:click="editSection({{ $node->id }})">Edytuj</button>
            <button type="button" class="btn btn--ghost btn--sm" wire:click="duplicateSection({{ $node->id }})">Kopiuj</button>
            @if ($node->is_active)
                <button type="button" class="btn btn--ghost btn--sm"
                        wire:click="deactivateSection({{ $node->id }})"
                        wire:confirm="Dezaktywować ten węzeł?">
                    Dezaktywuj
                </button>
            @else
                <button type="button" class="btn btn--ghost btn--sm" wire:click="reactivateSection({{ $node->id }})">Reaktywuj</button>
            @endif
            @if ($canForceDelete)
                <button type="button" class="btn btn--danger btn--sm"
                        wire:click="forceDeleteSection({{ $node->id }})"
                        wire:confirm="Trwale usunie ten węzeł WRAZ z jego pod-sekcjami, polami i wszystkimi ich zapisanymi wartościami w zasobach. Operacja jest nieodwracalna. Kontynuować?">
                    Usuń trwale
                </button>
            @endif
        </div>
    </div>

    @if ($node->is_repeatable && ($node->min_entries !== null || $node->max_entries !== null))
        <div class="muted tree-node__meta">
            Wpisy: {{ $node->min_entries ?? '0' }}–{{ $node->max_entries ?? '∞' }}
            @if ($node->displayField) · etykieta: {{ $node->displayField->name }} @endif
        </div>
    @endif

    <div class="tree-children">
        {{-- Pola tego węzła. --}}
        @foreach ($node->fields as $field)
            @include('livewire.asset-categories._field-row', ['field' => $field, 'canForceDelete' => $canForceDelete])
        @endforeach

        {{-- Potomne węzły. --}}
        @foreach ($node->childNodes as $child)
            @include('livewire.asset-categories._node', ['node' => $child, 'depth' => $depth + 1, 'canForceDelete' => $canForceDelete])
        @endforeach
    </div>
</div>
