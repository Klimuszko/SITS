<div>
    <x-page-header>
        <h1 style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
            {{ $asset->name }}
            <span class="badge badge--{{ $asset->status->color() }}">{{ $asset->status->label() }}</span>
            @if ($asset->is_private)
                <span class="badge badge--slate">prywatny</span>
            @endif
        </h1>
        <p>{{ $asset->category?->name ?? '—' }} · {{ $asset->organization?->name }} · utworzono {{ $asset->created_at->format('Y-m-d H:i') }}</p>
        <x-slot:actions>
            <a href="{{ route('assets.index') }}" wire:navigate class="btn btn--ghost">← Lista</a>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="alert alert--success">{{ session('status') }}</div>
    @endif

    {{-- Górny pasek: tylko akcje. Status/Info przeniesione do menu kategorii. --}}
    @if ($canUpdate || $canArchive || $canForceDelete)
        <div class="asset-top">
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
        </div>
    @endif

    {{-- Układ 2-kolumnowy: lewe menu kategorii | treść wybranej kategorii.
         Domyślnie pierwsza kategoria struktury; gdy brak — Status/Info. --}}
    @php $defaultTab = $sectionTree->isNotEmpty() ? 'sec0' : 'status'; @endphp
    <div class="asset-layout" x-data="{ tab: '{{ $defaultTab }}' }">
        {{-- ------------------------- LEWO: menu kategorii ------------------------- --}}
        <nav class="card asset-menu" aria-label="Kategorie zasobu">
            @foreach ($sectionTree as $node)
                <button type="button" class="asset-cats__tab"
                        :class="{ 'is-active': tab === 'sec{{ $loop->index }}' }"
                        @click="tab = 'sec{{ $loop->index }}'">
                    @if (filled($node['section']->icon))
                        <span class="asset-cats__icon">{!! $node['section']->icon !!}</span>
                    @endif
                    <span>{{ $node['section']->name }}</span>
                </button>
            @endforeach

            {{-- Status/Info — przedostatnia pozycja (przed Historią), ikona „i". --}}
            <button type="button" class="asset-cats__tab"
                    :class="{ 'is-active': tab === 'status' }" @click="tab = 'status'">
                <span class="asset-cats__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                </span>
                <span>Status/Info</span>
            </button>

            {{-- Historia — zawsze na końcu (ikona zegara). --}}
            <button type="button" class="asset-cats__tab"
                    :class="{ 'is-active': tab === 'history' }" @click="tab = 'history'">
                <span class="asset-cats__icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>
                </span>
                <span>Historia</span>
            </button>
        </nav>

        {{-- ----------------------- ŚRODEK: treść kategorii ----------------------- --}}
        <div class="card">
            <div class="card__body">
                {{-- Kategorie ze struktury. Pierwsza widoczna domyślnie (bez x-cloak). --}}
                @foreach ($sectionTree as $node)
                    <div x-show="tab === 'sec{{ $loop->index }}'" @unless ($loop->first) x-cloak @endunless
                         style="font-size:14px">
                        <h2 class="asset-content__title">{{ $node['section']->name }}</h2>
                        @include('livewire.assets._section', ['node' => $node, 'depth' => 0])
                    </div>
                @endforeach

                {{-- Status/Info: status + dane podstawowe + notatki (scalone). --}}
                <div x-show="tab === 'status'" @if ($sectionTree->isNotEmpty()) x-cloak @endif
                     style="font-size:14px">
                    <h2 class="asset-content__title">Status / informacje</h2>
                    <div class="asset-defs">
                    <div class="list-row"><span class="muted">Status</span>
                        <span><span class="badge badge--{{ $asset->status->color() }}">{{ $asset->status->label() }}</span></span></div>
                    <div class="list-row"><span class="muted">Organizacja</span><span>{{ $asset->organization?->name ?? '—' }}</span></div>
                    <div class="list-row"><span class="muted">Kategoria</span><span>{{ $asset->category?->name ?? '—' }}</span></div>
                    @if ($asset->inventory_code)
                        <div class="list-row"><span class="muted">Kod inwentarzowy</span><span>{{ $asset->inventory_code }}</span></div>
                    @endif
                    @if ($asset->location)
                        <div class="list-row"><span class="muted">Lokalizacja</span><span>{{ $asset->location->name }}</span></div>
                    @endif
                    @if ($asset->parent)
                        <div class="list-row"><span class="muted">Zasób nadrzędny</span>
                            <span><a href="{{ route('assets.show', $asset->parent) }}" wire:navigate>{{ $asset->parent->name }}</a></span>
                        </div>
                    @endif
                    <div class="list-row"><span class="muted">Prywatny</span><span>{{ $asset->is_private ? 'Tak' : 'Nie' }}</span></div>
                    @if ($asset->createdBy)
                        <div class="list-row"><span class="muted">Utworzył</span><span>{{ $asset->createdBy->name }}</span></div>
                    @endif
                    <div class="list-row"><span class="muted">Utworzono</span><span>{{ $asset->created_at?->format('Y-m-d H:i') ?? '—' }}</span></div>
                    <div class="list-row"><span class="muted">Aktualizacja</span><span>{{ $asset->updated_at?->format('Y-m-d H:i') ?? '—' }}</span></div>
                    </div>{{-- /asset-defs --}}
                    @if ($asset->notes)
                        <div style="margin-top:14px">
                            <div class="muted" style="font-weight:600;margin-bottom:4px">Notatki</div>
                            <div>{!! nl2br(e($asset->notes)) !!}</div>
                        </div>
                    @endif
                </div>

                {{-- Historia jako kategoria. --}}
                <div x-show="tab === 'history'" x-cloak style="font-size:14px">
                    <h2 class="asset-content__title">Historia</h2>
                    <div class="stack" style="gap:10px">
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
                    </div>{{-- /stack --}}
                </div>
            </div>
        </div>

    </div>
</div>
