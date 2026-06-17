<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'SerwisIT') }}</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    @livewireStyles
</head>
<body>
@php($user = auth()->user())
<div class="app">
    <header class="topbar">
        <div class="topbar__inner">
            <div class="brand">Serwis<span>IT</span></div>

            <nav class="nav">
                <a href="{{ route('dashboard') }}" wire:navigate class="{{ request()->routeIs('dashboard') ? 'active' : '' }}">Pulpit</a>
                <a href="{{ route('tickets.index') }}" wire:navigate class="{{ request()->routeIs('tickets.*') ? 'active' : '' }}">Zgłoszenia</a>
                <a href="{{ route('organizations.index') }}" wire:navigate class="{{ request()->routeIs('organizations.*') ? 'active' : '' }}">Organizacje</a>
                @can('manage-users')
                    <a href="{{ route('users.index') }}" wire:navigate class="{{ request()->routeIs('users.*') ? 'active' : '' }}">Użytkownicy</a>
                @endcan
                <a href="{{ route('assets.index') }}" wire:navigate class="{{ request()->routeIs('assets.*') ? 'active' : '' }}">Zasoby</a>
                <a href="{{ route('locations.index') }}" wire:navigate class="{{ request()->routeIs('locations.*') ? 'active' : '' }}">Lokalizacje</a>
                <a href="{{ route('work-logs.index') }}" wire:navigate class="{{ request()->routeIs('work-logs.*') ? 'active' : '' }}">Prace adm.</a>
                <a href="{{ route('knowledge.index') }}" wire:navigate class="{{ request()->routeIs('knowledge.*') ? 'active' : '' }}">Baza wiedzy</a>
            </nav>

            <div class="usermenu">
                <div style="text-align:right">
                    <div class="usermenu__name">{{ $user->name }}</div>
                    <div class="usermenu__role">{{ $user->role->label() }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="btn btn--ghost btn--sm">Wyloguj</button>
                </form>
            </div>
        </div>
    </header>

    <main class="content">
        @if (session('status'))
            <div class="alert alert--success">{{ session('status') }}</div>
        @endif
        @if (session('error'))
            <div class="alert alert--error">{{ session('error') }}</div>
        @endif

        {{ $slot }}
    </main>
</div>
@livewireScripts
</body>
</html>
