<div class="auth-card">
    @php ($logoUrl = \App\Models\Setting::get('logo_path') ? route('branding.logo').'?v='.\App\Models\Setting::get('branding_version') : null)
    @php ($appName = \App\Models\Setting::get('app_name', config('app.name', 'Smart Solutions')))
    <div class="auth-logo">
        @if ($logoUrl)
            <img src="{{ $logoUrl }}" alt="{{ $appName }}">
        @else
            @php ($brandParts = explode(' ', $appName, 2))
            <span class="brand"><span class="brand__mark">{{ $brandParts[0] }}</span>@if (isset($brandParts[1]))<span class="brand__accent">{{ $brandParts[1] }}</span>@endif</span>
        @endif
    </div>
    <h1>Logowanie</h1>
    <p class="muted" style="margin-top:0">Zaloguj się do portalu obsługi IT.</p>

    <form wire:submit="login" class="stack" style="margin-top:18px">
        <div class="field">
            <label for="email">Adres e-mail</label>
            <input id="email" type="email" class="input" wire:model="email" autocomplete="username" autofocus>
            @error('email') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label for="password">Hasło</label>
            <input id="password" type="password" class="input" wire:model="password" autocomplete="current-password">
            @error('password') <span class="error">{{ $message }}</span> @enderror
        </div>

        <label class="checkbox">
            <input type="checkbox" wire:model="remember"> Zapamiętaj mnie
        </label>

        <button type="submit" class="btn btn--primary" style="justify-content:center" wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="login">Zaloguj się</span>
            <span wire:loading wire:target="login">Logowanie…</span>
        </button>
    </form>

    @php ($ssoProviders = \App\Support\Sso::enabled())
    @if (! empty($ssoProviders))
        <div class="muted" style="text-align:center;margin:16px 0 8px;font-size:13px">lub zaloguj się przez</div>
        <div class="stack" style="gap:8px">
            @foreach ($ssoProviders as $provider)
                {{-- Pełne przekierowanie do dostawcy (bez wire:navigate). --}}
                <a href="{{ route('auth.redirect', $provider['key']) }}" class="btn btn--ghost" style="justify-content:center">
                    Zaloguj przez {{ $provider['label'] }}
                </a>
            @endforeach
        </div>
    @endif
</div>
