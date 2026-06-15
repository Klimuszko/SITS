@if ($paginator->hasPages())
    <nav class="pagination-wrap" style="display:flex;gap:6px;align-items:center;justify-content:flex-end;flex-wrap:wrap">
        <span class="muted" style="margin-right:auto;font-size:13px">
            {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} z {{ $paginator->total() }}
        </span>

        @if ($paginator->onFirstPage())
            <span class="btn btn--ghost btn--sm" style="opacity:.5">‹</span>
        @else
            <button type="button" class="btn btn--ghost btn--sm" wire:click="previousPage" wire:loading.attr="disabled">‹</button>
        @endif

        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="btn btn--ghost btn--sm" style="opacity:.5">{{ $element }}</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="btn btn--primary btn--sm">{{ $page }}</span>
                    @else
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="gotoPage({{ $page }})">{{ $page }}</button>
                    @endif
                @endforeach
            @endif
        @endforeach

        @if ($paginator->hasMorePages())
            <button type="button" class="btn btn--ghost btn--sm" wire:click="nextPage" wire:loading.attr="disabled">›</button>
        @else
            <span class="btn btn--ghost btn--sm" style="opacity:.5">›</span>
        @endif
    </nav>
@endif
