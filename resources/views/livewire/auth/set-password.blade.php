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
    <h1>Ustaw hasło</h1>
    <p class="muted" style="margin-top:0">Ustaw hasło do swojego konta, aby dokończyć rejestrację.</p>

    <form wire:submit="save" class="stack" style="margin-top:18px">
        <div class="field">
            <label for="email">Adres e-mail</label>
            <input id="email" type="email" class="input" wire:model="email" autocomplete="username">
            @error('email') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label for="password">Nowe hasło</label>
            <input id="password" type="password" class="input" wire:model="password" autocomplete="new-password" autofocus>
            @error('password') <span class="error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label for="password_confirmation">Powtórz hasło</label>
            <input id="password_confirmation" type="password" class="input" wire:model="password_confirmation" autocomplete="new-password">
        </div>

        <button type="submit" class="btn btn--primary" style="justify-content:center" wire:loading.attr="disabled" wire:target="save">
            <span wire:loading.remove wire:target="save">Ustaw hasło</span>
            <span wire:loading wire:target="save">Zapisywanie…</span>
        </button>

        <a href="{{ route('login') }}" wire:navigate class="muted" style="text-align:center;font-size:13px">Wróć do logowania</a>
    </form>
</div>
