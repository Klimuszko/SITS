<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Smart Solutions') }}</title>
    <link rel="icon" href="{{ \App\Models\Setting::get('favicon_path') ? route('branding.favicon').'?v='.\App\Models\Setting::get('branding_version') : asset('favicon.ico') }}" sizes="any">
    <script>
        // Domyślny motyw konfigurowalny przez admina; jawny wybór użytkownika w localStorage wygrywa.
        (function () {
            var d = @js(\App\Models\Setting::get('default_theme', 'dark'));
            try { document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || d); }
            catch (e) { document.documentElement.setAttribute('data-theme', d); }
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}?v={{ filemtime(public_path('css/app.css')) }}">
    @livewireStyles
</head>
<body>
    <div class="auth-wrap">
        {{ $slot }}
    </div>
    @livewireScripts
</body>
</html>
