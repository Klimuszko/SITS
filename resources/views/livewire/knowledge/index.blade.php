<div>
    <div class="page-head">
        <div>
            <h1>Baza wiedzy</h1>
            <p>Artykuły i instrukcje w zakresie Twoich uprawnień.</p>
        </div>
        @if ($canCreate)
            <a href="{{ route('knowledge.create') }}" wire:navigate class="btn btn--primary">+ Nowy artykuł</a>
        @endif
    </div>

    <div class="toolbar">
        <input type="search" class="input" placeholder="Szukaj po tytule…" wire:model.live.debounce.300ms="search">
        <select class="select" wire:model.live="category">
            <option value="">Każda kategoria</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
            @endforeach
        </select>
        @if ($isStaff)
            <select class="select" wire:model.live="status">
                <option value="">Każdy status</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        @endif
    </div>

    <div class="card">
        <table class="table">
            <thead>
                <tr>
                    <th>Tytuł</th>
                    <th>Kategoria</th>
                    <th>Status</th>
                    <th>Autor</th>
                    <th>Opublikowano</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($articles as $article)
                <tr>
                    <td><a href="{{ route('knowledge.show', $article) }}" wire:navigate><strong>{{ $article->title }}</strong></a></td>
                    <td class="muted">{{ $article->category?->name ?? '—' }}</td>
                    <td><span class="badge badge--{{ $article->status->color() }}">{{ $article->status->label() }}</span></td>
                    <td class="muted">{{ $article->author?->name ?? '—' }}</td>
                    <td class="muted">{{ $article->published_at?->format('Y-m-d') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="table__empty">Brak artykułów.</td></tr>
            @endforelse
            </tbody>
        </table>

        @if ($articles->hasPages())
            {{ $articles->links() }}
        @endif
    </div>
</div>
