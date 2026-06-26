@props(['column', 'current' => '', 'dir' => 'asc', 'align' => 'left'])

{{-- Klikalny nagłówek kolumny sortowalnej.
     Użycie:
       <x-sort-th column="name" :current="$sortCol" :dir="$sortDir">Nazwa</x-sort-th>
     $current/$dir przekazane z render() jako effectiveSortCol()/effectiveSortDir().
     Wskaźnik: aktywna kolumna → ▲/▼ wg kierunku; nieaktywna → ↕ (wyciszony). --}}
@php($active = $current === $column)
<th scope="col"
    @if($align === 'right') style="text-align:right" @endif
    @if($active) aria-sort="{{ $dir === 'desc' ? 'descending' : 'ascending' }}" @endif>
    <button type="button"
            class="th-sort @if($active) th-sort--active @endif @if($align === 'right') th-sort--right @endif"
            wire:click="sortBy('{{ $column }}')">
        <span>{{ $slot }}</span>
        <span class="th-sort__ind" aria-hidden="true">@if($active){{ $dir === 'desc' ? '▼' : '▲' }}@else↕@endif</span>
    </button>
</th>
