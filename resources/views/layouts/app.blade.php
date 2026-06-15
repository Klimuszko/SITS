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
                <a href="{{ route('organizations.index') }}" wire:navigate class="{{ request()->routeIs('organizations.*') ? 'active' : '' }}">Organizacje</a>

                {{-- Moduły w przygotowaniu (schemat i policies gotowe) --}}
                <span class="nav__disabled">Tickety <small>· wkrótce</small></span>
                <span class="nav__disabled">Zasoby <small>· wkrótce</small></span>
                <span class="nav__disabled">Baza wiedzy <small>· wkrótce</small></span>
                <span class="nav__disabled">Prace adm. <small>· wkrótce</small></span>
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
