<div>
    <div class="page-head">
        <div>
            <h1 style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span class="muted" style="font-size:18px">{{ $ticket->number }}</span>
                {{ $ticket->title }}
                <span class="badge badge--{{ $ticket->status->color() }}">{{ $ticket->status->label() }}</span>
            </h1>
            <p>Zgłaszający: {{ $ticket->requester?->name }} · {{ $ticket->organization?->name }} · utworzono {{ $ticket->created_at->format('Y-m-d H:i') }}</p>
        </div>
        <a href="{{ route('tickets.index') }}" wire:navigate class="btn btn--ghost">← Lista</a>
    </div>

    @if (session('status'))
        <div class="alert alert--success">{{ session('status') }}</div>
    @endif

    <div style="display:grid;grid-template-columns:1fr 330px;gap:18px;align-items:start">
        {{-- ----------------------------- KOLUMNA GŁÓWNA ----------------------------- --}}
        <div class="stack" style="gap:18px">
            {{-- Opis --}}
            <div class="card">
                <div class="card__head">Opis zgłoszenia</div>
                <div class="card__body">{!! nl2br(e($ticket->description)) !!}</div>
            </div>

            {{-- Wątek --}}
            <div class="card">
                <div class="card__head">Konwersacja ({{ $comments->count() }})</div>
                <div class="card__body stack" style="gap:14px">
                    @forelse ($comments as $comment)
                        @php($t = $comment->type)
                        @if ($t === \App\Enums\CommentType::System)
                            {{-- Wpis systemowy: zdarzenie osi czasu (zmiana statusu, utworzenie, przypisanie). --}}
                            <div class="muted" style="text-align:center;font-size:12px;line-height:1.5">
                                {{ $comment->body }} · {{ $comment->created_at->format('Y-m-d H:i') }}
                            </div>
                        @else
                            <div style="border:1px solid var(--border);border-radius:10px;padding:12px 14px;
                                @if($t === \App\Enums\CommentType::Internal) background:var(--c-amber-bg);border-color:#fcd97e;
                                @elseif($t === \App\Enums\CommentType::CloseRequest) background:var(--c-orange-bg);border-color:#fdba74; @endif">
                                <div style="display:flex;justify-content:space-between;gap:10px;margin-bottom:6px">
                                    <strong>{{ $comment->author?->name }}</strong>
                                    <span class="muted" style="font-size:12px">{{ $comment->created_at->format('Y-m-d H:i') }}</span>
                                </div>
                                @if ($t === \App\Enums\CommentType::Internal)
                                    <span class="badge badge--amber" style="margin-bottom:6px;display:inline-block">Notatka wewnętrzna</span>
                                @elseif ($t === \App\Enums\CommentType::CloseRequest)
                                    <span class="badge badge--orange" style="margin-bottom:6px;display:inline-block">Prośba o zamknięcie</span>
                                @endif
                                <div>{!! nl2br(e($comment->body)) !!}</div>
                            </div>
                        @endif
                    @empty
                        <p class="muted">Brak odpowiedzi.</p>
                    @endforelse
                </div>
            </div>

            {{-- Odpowiedź publiczna --}}
            @can('comment', $ticket)
                <div class="card">
                    <div class="card__head">Odpowiedz</div>
                    <div class="card__body">
                        <form wire:submit="addComment" class="stack">
                            <textarea class="textarea" rows="4" wire:model="newComment" placeholder="Napisz odpowiedź…"></textarea>
                            @error('newComment') <span class="error">{{ $message }}</span> @enderror
                            <div><button class="btn btn--primary" wire:loading.attr="disabled" wire:target="addComment">Wyślij odpowiedź</button></div>
                        </form>
                    </div>
                </div>
            @endcan

            {{-- Notatka wewnętrzna (tylko personel) --}}
            @if ($canInternal)
                <div class="card">
                    <div class="card__head">Notatka wewnętrzna <span class="muted" style="font-weight:400">— widoczna tylko dla supportu/admina</span></div>
                    <div class="card__body">
                        <form wire:submit="addInternalNote" class="stack">
                            <textarea class="textarea" rows="3" wire:model="internalNote" placeholder="Notatka techniczna, niewidoczna dla klienta…"></textarea>
                            @error('internalNote') <span class="error">{{ $message }}</span> @enderror
                            <div><button class="btn btn--ghost" wire:loading.attr="disabled" wire:target="addInternalNote">Dodaj notatkę</button></div>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Prośba o zamknięcie (klient) --}}
            @if ($canRequestClose)
                <div class="card">
                    <div class="card__head">Poproś o zamknięcie</div>
                    <div class="card__body">
                        <form wire:submit="requestClose" class="stack">
                            <p class="muted" style="margin:0">Nie możesz zamknąć zgłoszenia samodzielnie — opiekun zrobi to po weryfikacji. Podaj powód:</p>
                            <textarea class="textarea" rows="3" wire:model="closeReason" placeholder="Np. problem rozwiązany, sprawa nieaktualna…"></textarea>
                            @error('closeReason') <span class="error">{{ $message }}</span> @enderror
                            <div><button class="btn btn--ghost" wire:loading.attr="disabled" wire:target="requestClose">Wyślij prośbę</button></div>
                        </form>
                    </div>
                </div>
            @endif
        </div>

        {{-- ------------------------------- SIDEBAR -------------------------------- --}}
        <div class="stack" style="gap:18px">
            {{-- Szczegóły --}}
            <div class="card">
                <div class="card__head">Szczegóły</div>
                <div class="card__body stack" style="gap:8px;font-size:14px">
                    <div class="list-row"><span class="muted">Priorytet</span>
                        <span>@if($ticket->priority)<span class="badge badge--{{ $ticket->priority->color }}">{{ $ticket->priority->name }}</span>@else — @endif</span></div>
                    <div class="list-row"><span class="muted">Kategoria</span><span>{{ $ticket->category?->name ?? '—' }}</span></div>
                    <div class="list-row"><span class="muted">Opiekun</span><span>{{ $ticket->assignedSupport?->name ?? '—' }}</span></div>
                    <div class="list-row"><span class="muted">Lokalizacja</span><span>{{ $ticket->location?->name ?? '—' }}</span></div>
                    <div class="list-row"><span class="muted">Zasób</span><span>{{ $ticket->asset?->name ?? '—' }}</span></div>
                    <div class="list-row"><span class="muted">Ostatnia odpowiedź</span><span>{{ $ticket->last_reply_at?->format('Y-m-d H:i') ?? '—' }}</span></div>
                    @if($ticket->closed_at)
                        <div class="list-row"><span class="muted">Zamknięto</span><span>{{ $ticket->closed_at->format('Y-m-d H:i') }}</span></div>
                    @endif
                </div>
            </div>

            {{-- Zarządzanie (personel) --}}
            @if ($canManage)
                <div class="card">
                    <div class="card__head">Zarządzanie</div>
                    <div class="card__body stack" style="gap:14px">
                        <form wire:submit="changeStatus" class="stack" style="gap:6px">
                            <label class="muted" style="font-size:13px">Status</label>
                            <select class="select" wire:model="selectedStatus">
                                @foreach ($statuses as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn--primary btn--sm" wire:loading.attr="disabled" wire:target="changeStatus">Zmień status</button>
                        </form>

                        <form wire:submit="assignSupport" class="stack" style="gap:6px">
                            <label class="muted" style="font-size:13px">Przypisany opiekun</label>
                            <select class="select" wire:model="selectedSupport">
                                <option value="">— nieprzypisany —</option>
                                @foreach ($supporters as $sup)
                                    <option value="{{ $sup->id }}">{{ $sup->name }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn--ghost btn--sm" wire:loading.attr="disabled" wire:target="assignSupport">Zapisz przypisanie</button>
                        </form>
                    </div>
                </div>
            @endif

            {{-- Obserwatorzy --}}
            <div class="card">
                <div class="card__head">Obserwatorzy ({{ $ticket->observers->count() }})</div>
                <div class="card__body stack" style="gap:8px">
                    @forelse ($ticket->observers as $observer)
                        <div class="list-row">
                            <span>{{ $observer->name }}</span>
                            @if ($canManage)
                                <button class="btn-link" style="color:var(--danger)" wire:click="removeObserver({{ $observer->id }})">usuń</button>
                            @endif
                        </div>
                    @empty
                        <p class="muted" style="margin:0">Brak obserwatorów.</p>
                    @endforelse

                    @if ($canManage && $observerCandidates->isNotEmpty())
                        <form wire:submit="addObserver" class="stack" style="gap:6px;margin-top:6px">
                            <select class="select" wire:model="selectedObserver">
                                <option value="">— dodaj obserwatora —</option>
                                @foreach ($observerCandidates as $cand)
                                    <option value="{{ $cand->id }}">{{ $cand->name }}</option>
                                @endforeach
                            </select>
                            <button class="btn btn--ghost btn--sm" wire:loading.attr="disabled" wire:target="addObserver">Dodaj</button>
                            @error('selectedObserver') <span class="error">{{ $message }}</span> @enderror
                        </form>
                    @endif
                </div>
            </div>

            {{-- Załączniki --}}
            <div class="card">
                <div class="card__head">Załączniki ({{ $ticket->attachments->count() }})</div>
                <div class="card__body stack" style="gap:8px">
                    @forelse ($ticket->attachments as $att)
                        <div class="list-row">
                            <a href="{{ route('attachments.download', $att) }}">{{ $att->original_name }}</a>
                            <span class="muted" style="font-size:12px">
                                {{ $att->humanSize() }}
                                @if ($isStaff || $att->uploaded_by === auth()->id())
                                    · <button class="btn-link" style="color:var(--danger)" wire:click="deleteAttachment({{ $att->id }})" wire:confirm="Usunąć załącznik?">usuń</button>
                                @endif
                            </span>
                        </div>
                    @empty
                        <p class="muted" style="margin:0">Brak załączników.</p>
                    @endforelse

                    @can('comment', $ticket)
                        <form wire:submit="uploadFiles" class="stack" style="gap:6px;margin-top:6px">
                            <input type="file" class="input" wire:model="files" multiple>
                            <div wire:loading wire:target="files" class="muted" style="font-size:13px">Wgrywanie…</div>
                            @error('files.*') <span class="error">{{ $message }}</span> @enderror
                            <button class="btn btn--ghost btn--sm" wire:loading.attr="disabled" wire:target="uploadFiles">Dodaj załączniki</button>
                        </form>
                    @endcan
                </div>
            </div>
        </div>
    </div>
</div>
