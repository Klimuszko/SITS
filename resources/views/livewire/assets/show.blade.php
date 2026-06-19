<div>
    <div class="page-head">
        <div>
            <h1 style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                {{ $asset->name }}
                <span class="badge badge--{{ $asset->status->color() }}">{{ $asset->status->label() }}</span>
                @if ($asset->is_private)
                    <span class="badge badge--slate">prywatny</span>
                @endif
            </h1>
            <p>{{ $asset->category?->name ?? '—' }} · {{ $asset->organization?->name }} · utworzono {{ $asset->created_at->format('Y-m-d H:i') }}</p>
        </div>
        <a href="{{ route('assets.index') }}" wire:navigate class="btn btn--ghost">← Lista</a>
    </div>

    @if (session('status'))
        <div class="alert alert--success">{{ session('status') }}</div>
    @endif

    <div style="display:grid;grid-template-columns:1fr 330px;gap:18px;align-items:start">
        {{-- ----------------------------- KOLUMNA GŁÓWNA ----------------------------- --}}
        <div class="stack" style="gap:18px">
            {{-- Dane podstawowe --}}
            <div class="card">
                <div class="card__head">Dane podstawowe</div>
                <div class="card__body stack" style="gap:8px;font-size:14px">
                    <div class="list-row"><span class="muted">Organizacja</span><span>{{ $asset->organization?->name ?? '—' }}</span></div>
                    <div class="list-row"><span class="muted">Kategoria</span><span>{{ $asset->category?->name ?? '—' }}</span></div>
                    <div class="list-row"><span class="muted">Kod inwentarzowy</span><span>{{ $asset->inventory_code ?? '—' }}</span></div>
                    <div class="list-row"><span class="muted">Lokalizacja</span><span>{{ $asset->location?->name ?? '—' }}</span></div>
                    <div class="list-row"><span class="muted">Zasób nadrzędny</span>
                        <span>
                            @if ($asset->parent)
                                <a href="{{ route('assets.show', $asset->parent) }}" wire:navigate>{{ $asset->parent->name }}</a>
                            @else — @endif
                        </span>
                    </div>
                    <div class="list-row"><span class="muted">Utworzył</span><span>{{ $asset->createdBy?->name ?? '—' }}</span></div>
                </div>
            </div>

            {{-- Pola kategorii (struktura: sekcje, podsekcje, grupy powtarzalne) --}}
            @forelse ($sectionTree as $node)
                <div class="card">
                    <div class="card__head">{{ $node['section']->name }}</div>
                    <div class="card__body stack" style="gap:8px;font-size:14px">
                        @include('livewire.assets._section', ['node' => $node, 'depth' => 0])
                    </div>
                </div>
            @empty
                <div class="card">
                    <div class="card__head">Pola kategorii</div>
                    <div class="card__body">
                        <p class="muted" style="margin:0">Brak dodatkowych pól dla tej kategorii.</p>
                    </div>
                </div>
            @endforelse

            {{-- Notatki --}}
            @if ($asset->notes)
                <div class="card">
                    <div class="card__head">Notatki</div>
                    <div class="card__body">{!! nl2br(e($asset->notes)) !!}</div>
                </div>
            @endif

            {{-- Historia zmian --}}
            <div class="card">
                <div class="card__head">Historia ({{ $history->count() }})</div>
                <div class="card__body stack" style="gap:10px;font-size:14px">
                    @forelse ($history as $entry)
                        <div class="list-row" style="align-items:flex-start">
                            <span>
                                @if ($entry->action === 'created')
                                    Utworzono zasób
                                @elseif ($entry->action === 'archived')
                                    Zarchiwizowano zasób
                                @elseif ($entry->action === 'field_updated')
                                    Zmiana pola <strong>{{ $entry->field }}</strong>:
                                    <span class="muted">{{ $entry->old_value ?? '—' }}</span> → <span>{{ $entry->new_value ?? '—' }}</span>
                                @else
                                    {{ $entry->action }}
                                @endif
                                <div class="muted" style="font-size:12px">{{ $entry->user?->name ?? 'system' }}</div>
                            </span>
                            <span class="muted" style="font-size:12px">{{ $entry->created_at?->format('Y-m-d H:i') }}</span>
                        </div>
                    @empty
                        <p class="muted" style="margin:0">Brak wpisów w historii.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ------------------------------- SIDEBAR -------------------------------- --}}
        <div class="stack" style="gap:18px">
            @if ($canUpdate || $canArchive)
                <div class="card">
                    <div class="card__head">Akcje</div>
                    <div class="card__body stack" style="gap:10px">
                        @if ($canUpdate)
                            <a href="{{ route('assets.edit', $asset) }}" wire:navigate class="btn btn--primary btn--sm">Edytuj</a>
                        @endif

                        @if ($canArchive && $asset->status !== \App\Enums\AssetStatus::Archived)
                            <button class="btn btn--ghost btn--sm" wire:click="archive"
                                wire:confirm="Zarchiwizować ten zasób?"
                                wire:loading.attr="disabled" wire:target="archive">Archiwizuj</button>
                        @endif
                    </div>
                </div>
            @endif

            @if ($canForceDelete)
                <div class="card">
                    <div class="card__head">Strefa niebezpieczna</div>
                    <div class="card__body stack" style="gap:10px">
                        <button type="button" class="btn btn--danger btn--sm" wire:click="forceDelete"
                            wire:confirm="Trwale usunie ten zasób WRAZ z wartościami pól, wpisami grup, relacjami, przypisaniami, historią i załącznikami. Powiązane zgłoszenia stracą link do zasobu (zostaną zachowane). Operacja jest nieodwracalna. Kontynuować?"
                            wire:loading.attr="disabled" wire:target="forceDelete">Usuń trwale</button>
                    </div>
                </div>
            @endif

            <div class="card">
                <div class="card__head">Status</div>
                <div class="card__body stack" style="gap:8px;font-size:14px">
                    <div class="list-row"><span class="muted">Status</span>
                        <span><span class="badge badge--{{ $asset->status->color() }}">{{ $asset->status->label() }}</span></span></div>
                    <div class="list-row"><span class="muted">Prywatny</span><span>{{ $asset->is_private ? 'Tak' : 'Nie' }}</span></div>
                    <div class="list-row"><span class="muted">Aktualizacja</span><span>{{ $asset->updated_at?->format('Y-m-d H:i') ?? '—' }}</span></div>
                </div>
            </div>
        </div>
    </div>
</div>
