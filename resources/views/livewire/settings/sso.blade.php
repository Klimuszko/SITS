<div>
    <x-page-header title="Logowanie (SSO)"
        description="Konfiguracja logowania przez Microsoft i Google. Dane wpisujesz tutaj — client secret jest szyfrowany i można go rotować bez zmian w serwerze.">
        <x-slot:actions>
            <a href="{{ route('dashboard') }}" wire:navigate class="btn btn--ghost">← Powrót</a>
        </x-slot:actions>
    </x-page-header>

    @include('livewire.settings._nav')

    @if (session('status'))
        <div class="alert alert--success" style="margin-bottom:18px">{{ session('status') }}</div>
    @endif

    <form wire:submit="save" class="stack" style="gap:18px">
        {{-- Microsoft / Entra --}}
        <div class="card">
            <div class="card__head">Microsoft (Entra / Microsoft 365)</div>
            <div class="card__body">
                <div class="field field--full" style="margin-bottom:14px">
                    <label class="checkbox">
                        <input type="checkbox" wire:model="microsoftEnabled">
                        <span>Włącz logowanie przez Microsoft</span>
                    </label>
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="ms_client_id">Client ID (Application ID)</label>
                        <input id="ms_client_id" class="input" wire:model="microsoftClientId" autocomplete="off">
                        @error('microsoftClientId') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="ms_tenant">Tenant ID</label>
                        <input id="ms_tenant" class="input" wire:model="microsoftTenant" autocomplete="off" placeholder="np. common lub GUID dzierżawy">
                        @error('microsoftTenant') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field field--full">
                        <label for="ms_secret">Client secret
                            @if ($microsoftSecretSet)
                                <span class="badge badge--green">ustawiony</span>
                                <span class="hint">— zostaw puste, aby nie zmieniać</span>
                            @endif
                        </label>
                        <input id="ms_secret" type="password" class="input" wire:model="microsoftSecret" autocomplete="new-password" placeholder="{{ $microsoftSecretSet ? '••••••••' : '' }}">
                        @error('microsoftSecret') <span class="error">{{ $message }}</span> @enderror
                        @if ($microsoftSecretSet)
                            <button type="button" class="btn-link" style="color:var(--danger);align-self:flex-start" wire:click="clearMicrosoftSecret" wire:confirm="Usunąć zapisany client secret Microsoft?">Usuń zapisany sekret</button>
                        @endif
                    </div>
                    <div class="field field--full">
                        <label>Redirect URI (wklej w rejestracji aplikacji Azure)</label>
                        <input class="input" value="{{ $microsoftRedirect }}" readonly onclick="this.select()">
                    </div>
                </div>
            </div>
        </div>

        {{-- Google --}}
        <div class="card">
            <div class="card__head">Google</div>
            <div class="card__body">
                <div class="field field--full" style="margin-bottom:14px">
                    <label class="checkbox">
                        <input type="checkbox" wire:model="googleEnabled">
                        <span>Włącz logowanie przez Google</span>
                    </label>
                </div>

                <div class="form-grid">
                    <div class="field">
                        <label for="g_client_id">Client ID</label>
                        <input id="g_client_id" class="input" wire:model="googleClientId" autocomplete="off">
                        @error('googleClientId') <span class="error">{{ $message }}</span> @enderror
                    </div>
                    <div class="field">
                        <label for="g_secret">Client secret
                            @if ($googleSecretSet)
                                <span class="badge badge--green">ustawiony</span>
                                <span class="hint">— zostaw puste, aby nie zmieniać</span>
                            @endif
                        </label>
                        <input id="g_secret" type="password" class="input" wire:model="googleSecret" autocomplete="new-password" placeholder="{{ $googleSecretSet ? '••••••••' : '' }}">
                        @error('googleSecret') <span class="error">{{ $message }}</span> @enderror
                        @if ($googleSecretSet)
                            <button type="button" class="btn-link" style="color:var(--danger);align-self:flex-start" wire:click="clearGoogleSecret" wire:confirm="Usunąć zapisany client secret Google?">Usuń zapisany sekret</button>
                        @endif
                    </div>
                    <div class="field field--full">
                        <label>Redirect URI (wklej w Google Cloud → OAuth client)</label>
                        <input class="input" value="{{ $googleRedirect }}" readonly onclick="this.select()">
                    </div>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:10px">
            <button type="submit" class="btn btn--primary" wire:loading.attr="disabled" wire:target="save">Zapisz</button>
        </div>

        <p class="hint" style="margin:0">Samo logowanie przez te konta uruchomimy w kolejnym kroku — tu zapisujesz konfigurację. Konto musi już istnieć (zaproszenie); SSO dopasowuje po zweryfikowanym e-mailu.</p>
    </form>
</div>
