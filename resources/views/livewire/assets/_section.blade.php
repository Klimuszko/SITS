{{--
    Rekurencyjny węzeł prezentacji struktury zasobu.
    Oczekuje: $node (tablica: section, fields, group, children), $depth (int).

    depth 0  = korzeń kategorii: pola bezpośrednie jako lista definicji,
               a podsekcje / grupy jako wyraźne panele .asset-block.
    depth>0  = podsekcja / grupa renderowana jako panel z nagłówkiem.
--}}
@php($section = $node['section'])

@if ($depth === 0)
    <div class="asset-content">
        @if ($node['group'])
            @include('livewire.assets._group-view', ['view' => $node['group'], 'label' => $section->name, 'depth' => 0])
        @elseif ($node['fields']->isNotEmpty())
            @if (collect($node['fields'])->contains(fn ($f) => ($f['value'] ?? '—') !== '—'))
                <div class="asset-defs">
                    @foreach ($node['fields'] as $field)
                        @include('livewire.assets._def-row', ['field' => $field])
                    @endforeach
                </div>
            @endif
        @endif

        @foreach ($node['children'] as $child)
            @include('livewire.assets._section', ['node' => $child, 'depth' => 1])
        @endforeach
    </div>
@else
    <div class="asset-block">
        <div class="asset-block__head">
            <span class="asset-block__title">{{ $section->name }}</span>
            @if ($node['group'])
                <span class="asset-block__count">{{ $node['group']['rows']->count() }}</span>
            @endif
        </div>
        <div class="asset-block__body{{ ($node['group'] && ! $node['group']['hasChildren']) ? ' asset-block__body--flush' : '' }}">
            @if ($node['group'])
                @include('livewire.assets._group-view', ['view' => $node['group'], 'label' => $section->name, 'depth' => 0])
            @else
                @if (collect($node['fields'])->contains(fn ($f) => ($f['value'] ?? '—') !== '—'))
                    <div class="asset-defs">
                        @foreach ($node['fields'] as $field)
                            @include('livewire.assets._def-row', ['field' => $field])
                        @endforeach
                    </div>
                @endif

                @foreach ($node['children'] as $child)
                    @include('livewire.assets._section', ['node' => $child, 'depth' => $depth + 1])
                @endforeach
            @endif
        </div>
    </div>
@endif
