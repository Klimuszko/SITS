@props(['title' => null, 'description' => null, 'card' => false])

{{-- Sekcja treści: logiczna grupa z jasnym tytułem, opcjonalnym opisem i spójnym
     układem akcji (slot „actions"). `card` opakowuje body w powierzchnię.
     Użycie:
       <x-section title="Aktywne zgłoszenia" description="Otwarte i w toku." card>
           <x-slot:actions><a class="btn btn--ghost btn--sm">Wszystkie</a></x-slot:actions>
           ...treść...
       </x-section> --}}
<section {{ $attributes->merge(['class' => 'section'.($card ? ' section--card' : '')]) }}>
    @if ($title || isset($actions) || $description)
        <div class="section__head">
            <div>
                @if ($title)
                    <h2 class="section__title">{{ $title }}</h2>
                @endif
                @if ($description)
                    <p class="section__desc">{{ $description }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="section__actions">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="section__body">{{ $slot }}</div>
</section>
