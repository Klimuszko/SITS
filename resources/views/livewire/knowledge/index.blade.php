<div>
    <x-page-header title="Baza wiedzy" description="Artykuły i instrukcje w zakresie Twoich uprawnień.">
        @if ($canCreate)
            <x-slot:actions>
                <a href="{{ route('knowledge.create') }}" wire:navigate class="btn btn--primary">+ Nowy artykuł</a>
            </x-slot:actions>
        @endif
    </x-page-header>

    <div class="toolbar">
        <input type="search" class="input" aria-label="Szukaj artykułów" placeholder="Szukaj po tytule…" wire:model.live.debounce.300ms="search">
        <select class="select" aria-label="Filtruj wg kategorii" wire:model.live="category">
            <option value="">Każda kategoria</option>
            @foreach ($categories as $cat)
                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
            @endforeach
        </select>
        @if ($isStaff)
            <select class="select" aria-label="Filtruj wg statusu" wire:model.live="status">
                <option value="">Każdy status</option>
                @foreach ($statuses as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        @endif
    </div>

    <div class="card">
        <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <x-sort-th column="title" :current="$sortCol" :dir="$sortDir">Tytuł</x-sort-th>
                    <x-sort-th column="category" :current="$sortCol" :dir="$sortDir">Kategoria</x-sort-th>
                    <x-sort-th column="status" :current="$sortCol" :dir="$sortDir">Status</x-sort-th>
                    <x-sort-th column="author" :current="$sortCol" :dir="$sortDir">Autor</x-sort-th>
                    <x-sort-th column="published_at" :current="$sortCol" :dir="$sortDir">Opublikowano</x-sort-th>
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
        </div>

        @if ($articles->hasPages())
            {{ $articles->links() }}
        @endif
    </div>
</div>
