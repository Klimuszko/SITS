{{--
    Rekurencyjny widok grupy powtarzalnej (prezentacja zasobu).
    Oczekuje:
      $view  — ['columns'=>Collection<AssetField>, 'rows'=>Collection, 'hasChildren'=>bool],
               gdzie wiersz = ['cells'=>[fieldId=>str], 'children'=>[ ['section','label','view'], ... ]],
      $label — etykieta pojedynczego wpisu (np. nazwa grupy),
      $depth — int (głębokość wizualna).

    Grupa-liść (bez zagnieżdżonych grup) → zwarta tabela (na całej szerokości panelu).
    Grupa z pod-grupami → każdy wpis jako blok: pola + rekurencyjne pod-grupy.
--}}
@if ($view['rows']->isEmpty())
    <p class="muted" style="margin:0;padding:12px 14px">Brak wpisów.</p>
@elseif (! $view['hasChildren'])
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">#</th>
                    @foreach ($view['columns'] as $col)
                        <th scope="col">{{ $col->name }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($view['rows'] as $i => $row)
                    <tr>
                        <td class="muted">#{{ $i + 1 }}</td>
                        @foreach ($view['columns'] as $col)
                            @php($v = $row['cells'][$col->id] ?? '—')
                            @php($href = $row['cellHrefs'][$col->id] ?? null)
                            <td>
                                @if ($href !== null)
                                    <a href="{{ $href }}" wire:navigate>{{ $v }}</a>
                                @elseif ($col->type->value === 'url' && $v !== '—' && \Illuminate\Support\Str::startsWith($v, ['http://', 'https://']))
                                    <a href="{{ $v }}" target="_blank" rel="noopener noreferrer">{{ $v }}</a>
                                @else
                                    {{ $v }}
                                @endif
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    @foreach ($view['rows'] as $i => $row)
        <div class="asset-entry">
            <div class="asset-entry__head">
                <span class="asset-block__count">#{{ $i + 1 }}</span>
                <span>{{ $label }}</span>
            </div>

            @if (collect($view['columns'])->contains(fn ($c) => (($row['cells'][$c->id] ?? '—') !== '—')))
                <div class="asset-defs">
                    @foreach ($view['columns'] as $col)
                        @include('livewire.assets._def-row', ['field' => [
                            'label' => $col->name,
                            'value' => $row['cells'][$col->id] ?? '—',
                            'type' => $col->type->value,
                            'href' => $row['cellHrefs'][$col->id] ?? null,
                        ]])
                    @endforeach
                </div>
            @endif

            @foreach ($row['children'] as $child)
                <div class="asset-block" style="margin-top:12px">
                    <div class="asset-block__head">
                        <span class="asset-block__title">{{ $child['section']->name }}</span>
                        <span class="asset-block__count">{{ $child['view']['rows']->count() }}</span>
                    </div>
                    <div class="asset-block__body{{ ! $child['view']['hasChildren'] ? ' asset-block__body--flush' : '' }}">
                        @include('livewire.assets._group-view', [
                            'view' => $child['view'],
                            'label' => $child['label'],
                            'depth' => $depth + 1,
                        ])
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
@endif
