<div>
    <div class="page-head">
        <div>
            <h1 style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                {{ $article->title }}
                <span class="badge badge--{{ $article->status->color() }}">{{ $article->status->label() }}</span>
            </h1>
            <p>
                {{ $article->category?->name ?? 'Bez kategorii' }}
                · Autor: {{ $article->author?->name ?? '—' }}
                @if ($article->published_at)
                    · Opublikowano {{ $article->published_at->format('Y-m-d') }}
                @endif
            </p>
        </div>
        <div style="display:flex;gap:10px">
            @if ($canUpdate)
                <a href="{{ route('knowledge.edit', $article) }}" wire:navigate class="btn btn--ghost">Edytuj</a>
            @endif
            <a href="{{ route('knowledge.index') }}" wire:navigate class="btn btn--ghost">← Lista</a>
        </div>
    </div>

    <div class="card">
        {{-- BEZPIECZEŃSTWO: $article->body jest sanityzowany przy zapisie (HtmlSanitizer), więc render bez ucieczki jest bezpieczny. --}}
        <div class="card__body article-body">{!! $article->body !!}</div>
    </div>
</div>
