{{--
    Rekurencyjny węzeł drzewa struktury.
    Oczekuje: $node (AssetSection z relacją childNodes), $depth (int).
--}}
<div class="tree-node" style="margin-left:{{ $depth * 22 }}px;padding:8px 0;border-bottom:1px solid var(--border, #eee)">
    <div style="display:flex;align-items:center;gap:10px;justify-content:space-between">
        <div style="display:flex;align-items:center;gap:8px">
            <span class="muted">#{{ $node->order }}</span>
            <strong>{{ $node->name }}</strong>
            <span class="muted">({{ $node->key }})</span>

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

        <div style="display:flex;gap:6px">
            <button type="button" class="btn btn--ghost btn--sm" wire:click="editSection({{ $node->id }})">Edytuj</button>
            @if ($node->is_active)
                <button type="button" class="btn btn--ghost btn--sm"
                        wire:click="deactivateSection({{ $node->id }})"
                        wire:confirm="Dezaktywować ten węzeł?">
                    Dezaktywuj
                </button>
            @else
                <button type="button" class="btn btn--ghost btn--sm"
                        wire:click="reactivateSection({{ $node->id }})">
                    Reaktywuj
                </button>
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
        <div class="muted" style="font-size:.85em;margin-top:2px">
            Wpisy: {{ $node->min_entries ?? '0' }}–{{ $node->max_entries ?? '∞' }}
            @if ($node->displayField) · etykieta: {{ $node->displayField->name }} @endif
        </div>
    @endif

    @foreach ($node->childNodes as $child)
        @include('livewire.asset-categories._node', ['node' => $child, 'depth' => $depth + 1, 'canForceDelete' => $canForceDelete])
    @endforeach
</div>
