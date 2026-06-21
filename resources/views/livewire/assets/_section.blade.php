{{--
    Rekurencyjny węzeł prezentacji struktury zasobu.
    Oczekuje: $node (tablica: section, fields, group, children), $depth (int).
--}}
@php($section = $node['section'])
@php($isRoot = $depth === 0)

<div class="stack" style="gap:8px;{{ $depth > 0 ? 'margin-left:14px;border-left:2px solid var(--border,#eee);padding-left:12px' : '' }}">
    @if ($depth > 0)
        <div class="muted" style="font-weight:600;margin-top:4px">{{ $section->name }}</div>
    @endif

    @if ($node['group'])
        @include('livewire.assets._group-view', [
            'view' => $node['group'],
            'label' => $section->name,
            'depth' => 0,
        ])
    @else
        {{-- Sekcja / podsekcja: pola pojedyncze --}}
        @forelse ($node['fields'] as $field)
            <div class="list-row"><span class="muted">{{ $field['label'] }}</span><span>{{ $field['value'] }}</span></div>
        @empty
            @if ($node['children']->isEmpty())
                <p class="muted" style="margin:0">Brak pól.</p>
            @endif
        @endforelse
    @endif

    @foreach ($node['children'] as $child)
        @include('livewire.assets._section', ['node' => $child, 'depth' => $depth + 1])
    @endforeach
</div>
