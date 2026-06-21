{{-- Pod-nawigacja sekcji Ustawienia (admin). --}}
<div class="toolbar" style="margin-bottom:18px">
    <a href="{{ route('settings.branding') }}" wire:navigate
       class="btn btn--sm {{ request()->routeIs('settings.branding') ? 'btn--primary' : 'btn--ghost' }}">Branding</a>
    <a href="{{ route('settings.sso') }}" wire:navigate
       class="btn btn--sm {{ request()->routeIs('settings.sso') ? 'btn--primary' : 'btn--ghost' }}">Logowanie (SSO)</a>
</div>
