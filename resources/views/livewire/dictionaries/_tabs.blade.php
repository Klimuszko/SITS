{{-- Wspólna pod-nawigacja słowników (zakładki). --}}
<div class="toolbar" style="margin-bottom:18px">
    <a href="{{ route('dictionaries.ticket-categories') }}" wire:navigate
       class="btn btn--sm {{ request()->routeIs('dictionaries.ticket-categories') ? 'btn--primary' : 'btn--ghost' }}">
        Kategorie zgłoszeń
    </a>
    <a href="{{ route('dictionaries.ticket-priorities') }}" wire:navigate
       class="btn btn--sm {{ request()->routeIs('dictionaries.ticket-priorities') ? 'btn--primary' : 'btn--ghost' }}">
        Priorytety zgłoszeń
    </a>
    <a href="{{ route('dictionaries.knowledge-categories') }}" wire:navigate
       class="btn btn--sm {{ request()->routeIs('dictionaries.knowledge-categories') ? 'btn--primary' : 'btn--ghost' }}">
        Kategorie bazy wiedzy
    </a>
    <a href="{{ route('dictionaries.asset-categories') }}" wire:navigate
       class="btn btn--sm {{ request()->routeIs('dictionaries.asset-categories', 'dictionaries.asset-category-fields') ? 'btn--primary' : 'btn--ghost' }}">
        Kategorie zasobów
    </a>
</div>
