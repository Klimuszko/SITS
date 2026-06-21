{{--
    Rekurencyjny widok grupy powtarzalnej (prezentacja zasobu).
    Oczekuje:
      $view  — ['columns'=>Collection<AssetField>, 'rows'=>Collection, 'hasChildren'=>bool],
               gdzie wiersz = ['cells'=>[fieldId=>str], 'children'=>[ ['section','label','view'], ... ]],
      $label — etykieta pojedynczego wpisu (np. nazwa grupy),
      $depth — int (głębokość wizualna).

    Grupa-liść (bez zagnieżdżonych grup) → zwarta tabela.
    Grupa z pod-grupami → każdy wpis jako blok: pola + rekurencyjne pod-grupy.
--}}
@if ($view['rows']->isEmpty())
    <p class="muted" style="margin:0">Brak wpisów.</p>
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
                            <td>{{ $row['cells'][$col->id] ?? '—' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@else
    <div class="stack" style="gap:12px">
        @foreach ($view['rows'] as $i => $row)
            <div class="card" style="background:transparent">
                <div class="card__body">
                    <strong class="muted">{{ $label }} #{{ $i + 1 }}</strong>

                    @if ($view['columns']->isNotEmpty())
                        <div style="margin-top:6px">
                            @foreach ($view['columns'] as $col)
                                <div class="list-row"><span class="muted">{{ $col->name }}</span><span>{{ $row['cells'][$col->id] ?? '—' }}</span></div>
                            @endforeach
                        </div>
                    @endif

                    @foreach ($row['children'] as $child)
                        <div style="margin-top:10px;margin-left:14px;border-left:2px solid var(--border,#eee);padding-left:12px">
                            <div class="muted" style="font-weight:600;margin-bottom:6px">{{ $child['section']->name }}</div>
                            @include('livewire.assets._group-view', [
                                'view' => $child['view'],
                                'label' => $child['label'],
                                'depth' => $depth + 1,
                            ])
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
@endif
