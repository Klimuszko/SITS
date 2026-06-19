<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Smart Solutions') }}</title>
    <link rel="icon" href="{{ \App\Models\Setting::get('favicon_path') ? route('branding.favicon').'?v='.\App\Models\Setting::get('branding_version') : asset('favicon.ico') }}" sizes="any">
    <script>
        // Motyw trzymany w localStorage. Domyślny motyw aplikacji jest konfigurowalny
        // przez admina (Setting::default_theme); jawny wybór użytkownika w localStorage
        // ZAWSZE ma pierwszeństwo. applyTheme() wołane od razu (pierwszy paint -> brak FOUC)
        // ORAZ po każdej nawigacji Livewire SPA: wire:navigate podmienia stronę na serwerowy
        // HTML BEZ data-theme, więc bez tego nasłuchu motyw resetował się po kliknięciu w link.
        function applyTheme() {
            var d = @js(\App\Models\Setting::get('default_theme', 'dark'));
            var t;
            try { t = localStorage.getItem('theme') || d; } catch (e) { t = d; }
            document.documentElement.setAttribute('data-theme', t);
        }
        applyTheme();
        document.addEventListener('livewire:navigated', applyTheme);
        function toggleTheme() {
            var next = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            try { localStorage.setItem('theme', next); } catch (e) {}
        }

        // Zwinięcie sidebaru (desktop) trzymane w localStorage — ten sam wzorzec co motyw:
        // applySidebar() na pierwszy paint ORAZ po nawigacji Livewire (serwerowy HTML nie
        // niesie data-sidebar). Zwinięcie to tryb tylko-ikony; mobile ma własny drawer (CSS).
        function applySidebar() {
            var s;
            try { s = localStorage.getItem('sidebar') || 'expanded'; } catch (e) { s = 'expanded'; }
            document.documentElement.setAttribute('data-sidebar', s);
        }
        applySidebar();
        document.addEventListener('livewire:navigated', applySidebar);
        function toggleSidebar() {
            var next = document.documentElement.getAttribute('data-sidebar') === 'collapsed' ? 'expanded' : 'collapsed';
            document.documentElement.setAttribute('data-sidebar', next);
            try { localStorage.setItem('sidebar', next); } catch (e) {}
        }
    </script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @livewireStyles
</head>
<body>
@php($user = auth()->user())
<a href="#content" class="skip-link">Przejdź do treści</a>

<div class="app" x-data="{ nav: false }" @keydown.escape.window="nav = false">
    <header class="topbar">
        <div class="topbar__inner">
            <button type="button" class="topbar__hamburger" @click="nav = !nav"
                    :aria-expanded="nav.toString()" aria-controls="sidebar" aria-label="Przełącz menu">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                    <line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>
                </svg>
            </button>

            @php
                $brandingMode = \App\Models\Setting::get('branding_mode', 'name');
                $logoUrl = \App\Models\Setting::get('logo_path')
                    ? route('branding.logo').'?v='.\App\Models\Setting::get('branding_version')
                    : null;
            @endphp
            <a href="{{ route('dashboard') }}" wire:navigate class="brand" aria-label="Smart Solutions — Portal IT">
                @if ($brandingMode === 'logo' && $logoUrl)
                    <img class="brand__logo brand__logo--solo" src="{{ $logoUrl }}" alt="Logo">
                @else
                    <span class="brand__mark">Smart</span><span class="brand__accent">Solutions</span>
                    @if ($brandingMode === 'name_logo' && $logoUrl)
                        <img class="brand__logo" src="{{ $logoUrl }}" alt="Logo">
                    @endif
                @endif
            </a>

            <div class="topbar__spacer"></div>

            <button type="button" class="topbar__theme" onclick="toggleTheme()" aria-label="Przełącz motyw jasny/ciemny" title="Przełącz motyw">
                <svg class="theme-icon-dark" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
                </svg>
                <svg class="theme-icon-light" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="12" r="4"/>
                    <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
                </svg>
            </button>

            <div class="usermenu">
                <div class="usermenu__id">
                    <a href="{{ route('profile.edit') }}" wire:navigate class="usermenu__name">{{ $user->name }}</a>
                    <div class="usermenu__role">{{ $user->role->label() }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn--ghost btn--sm">Wyloguj</button>
                </form>
            </div>
        </div>
    </header>

    <div class="layout">
        {{-- Tło zamykające drawer na mobile --}}
        <div class="sidebar__backdrop" x-show="nav" x-transition.opacity @click="nav = false" style="display:none"></div>

        <nav id="sidebar" class="sidebar" :class="{ 'is-open': nav }" aria-label="Nawigacja główna">
            {{-- Nawigacja z pojedynczego źródła prawdy: \App\Support\Navigation.
                 Bramki widoczności rozstrzygane są tam; tu jest czyste renderowanie. --}}
            <div class="sidebar__nav">
                @foreach (\App\Support\Navigation::categoriesFor($user) as $category)
                    <div class="sidebar__group">
                        @if ($category['label'])
                            <div class="sidebar__label">
                                @if ($category['icon'])
                                    <x-icon :name="$category['icon']" class="sidebar__label-icon" />
                                @endif
                                <span class="sidebar__text">{{ $category['label'] }}</span>
                            </div>
                        @endif
                        @foreach ($category['items'] as $item)
                            @php($isActive = request()->routeIs($item['active']))
                            <a href="{{ route($item['route']) }}" wire:navigate @click="nav = false"
                               class="sidebar__link {{ $isActive ? 'active' : '' }}"
                               title="{{ $item['label'] }}" aria-label="{{ $item['label'] }}"
                               @if($isActive) aria-current="page" @endif><x-icon :name="$item['icon']" /><span class="sidebar__text">{{ $item['label'] }}</span></a>
                        @endforeach
                    </div>
                @endforeach
            </div>

            {{-- Zwijanie sidebaru — desktop only (CSS chowa go na mobile, gdzie działa drawer). --}}
            <button type="button" class="sidebar__collapse" onclick="toggleSidebar()" aria-label="Zwiń lub rozwiń menu" title="Zwiń lub rozwiń menu">
                <x-icon name="chevron-left" class="sidebar__collapse-icon" />
                <span class="sidebar__text">Zwiń menu</span>
            </button>
        </nav>

        <main id="content" class="content" tabindex="-1">
            @if (session('status'))
                <div class="alert alert--success">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="alert alert--error">{{ session('error') }}</div>
            @endif

            {{ $slot }}
        </main>
    </div>
</div>
@livewireScripts
</body>
</html>
