<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Smart Solutions') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
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

            <a href="{{ route('dashboard') }}" wire:navigate class="brand" aria-label="Smart Solutions — Portal IT">
                <span class="brand__mark">Smart</span><span class="brand__accent">Solutions</span>
            </a>

            <div class="topbar__spacer"></div>

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
            {{-- Pulpit --}}
            <div class="sidebar__group">
                <a href="{{ route('dashboard') }}" wire:navigate @click="nav = false"
                   class="sidebar__link {{ request()->routeIs('dashboard') ? 'active' : '' }}"
                   @if(request()->routeIs('dashboard')) aria-current="page" @endif>Pulpit</a>
            </div>

            {{-- Wsparcie --}}
            <div class="sidebar__group">
                <div class="sidebar__label">Wsparcie</div>
                <a href="{{ route('tickets.index') }}" wire:navigate @click="nav = false"
                   class="sidebar__link {{ request()->routeIs('tickets.*') ? 'active' : '' }}"
                   @if(request()->routeIs('tickets.*')) aria-current="page" @endif>Zgłoszenia</a>
                <a href="{{ route('knowledge.index') }}" wire:navigate @click="nav = false"
                   class="sidebar__link {{ request()->routeIs('knowledge.*') ? 'active' : '' }}"
                   @if(request()->routeIs('knowledge.*')) aria-current="page" @endif>Baza wiedzy</a>
            </div>

            {{-- Zasoby --}}
            <div class="sidebar__group">
                <div class="sidebar__label">Zasoby</div>
                <a href="{{ route('assets.index') }}" wire:navigate @click="nav = false"
                   class="sidebar__link {{ request()->routeIs('assets.*') ? 'active' : '' }}"
                   @if(request()->routeIs('assets.*')) aria-current="page" @endif>Zasoby</a>
                @if ($user->isStaff())
                    <a href="{{ route('locations.index') }}" wire:navigate @click="nav = false"
                       class="sidebar__link {{ request()->routeIs('locations.*') ? 'active' : '' }}"
                       @if(request()->routeIs('locations.*')) aria-current="page" @endif>Lokalizacje</a>
                @endif
            </div>

            {{-- Klienci --}}
            @if ($user->isStaff() || $user->can('manage-users'))
                <div class="sidebar__group">
                    <div class="sidebar__label">Klienci</div>
                    @if ($user->isStaff())
                        <a href="{{ route('organizations.index') }}" wire:navigate @click="nav = false"
                           class="sidebar__link {{ request()->routeIs('organizations.*') ? 'active' : '' }}"
                           @if(request()->routeIs('organizations.*')) aria-current="page" @endif>Organizacje</a>
                    @endif
                    @can('manage-users')
                        <a href="{{ route('users.index') }}" wire:navigate @click="nav = false"
                           class="sidebar__link {{ request()->routeIs('users.*') ? 'active' : '' }}"
                           @if(request()->routeIs('users.*')) aria-current="page" @endif>Użytkownicy</a>
                    @endcan
                </div>
            @endif

            {{-- Praca --}}
            @if ($user->isStaff() || $user->managesAnyOrganization())
                <div class="sidebar__group">
                    <div class="sidebar__label">Praca</div>
                    <a href="{{ route('work-logs.index') }}" wire:navigate @click="nav = false"
                       class="sidebar__link {{ request()->routeIs('work-logs.*') ? 'active' : '' }}"
                       @if(request()->routeIs('work-logs.*')) aria-current="page" @endif>Prace administracyjne</a>
                </div>
            @endif

            {{-- Administracja --}}
            @if ($user->can('manage-categories') || $user->can('view-audit'))
                <div class="sidebar__group">
                    <div class="sidebar__label">Administracja</div>
                    @can('manage-categories')
                        <a href="{{ route('dictionaries.ticket-categories') }}" wire:navigate @click="nav = false"
                           class="sidebar__link {{ request()->routeIs('dictionaries.*') ? 'active' : '' }}"
                           @if(request()->routeIs('dictionaries.*')) aria-current="page" @endif>Słowniki</a>
                    @endcan
                    @can('view-audit')
                        <a href="{{ route('audit.index') }}" wire:navigate @click="nav = false"
                           class="sidebar__link {{ request()->routeIs('audit.*') ? 'active' : '' }}"
                           @if(request()->routeIs('audit.*')) aria-current="page" @endif>Audyt</a>
                    @endcan
                </div>
            @endif
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
