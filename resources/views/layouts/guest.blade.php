<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? config('app.name', 'Smart Solutions') }}</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    <script>
        (function () {
            try { document.documentElement.setAttribute('data-theme', localStorage.getItem('theme') || 'dark'); }
            catch (e) { document.documentElement.setAttribute('data-theme', 'dark'); }
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
