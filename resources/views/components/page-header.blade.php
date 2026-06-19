@props(['title' => null, 'description' => null])

{{-- Nagłówek strony: tytuł + opcjonalny opis + spójny układ akcji (slot „actions").
     Użycie:
       <x-page-header title="Zgłoszenia" description="Lista zgłoszeń serwisowych.">
           <x-slot:actions><a href="..." class="btn btn--primary">Nowe</a></x-slot:actions>
       </x-page-header> --}}
<div class="page-head">
    <div>
        @if ($title)
            <h1>{{ $title }}</h1>
        @endif
        @if ($description)
            <p>{{ $description }}</p>
        @endif
        {{ $slot }}
    </div>

    @isset($actions)
        <div class="page-head__actions">{{ $actions }}</div>
    @endisset
</div>
