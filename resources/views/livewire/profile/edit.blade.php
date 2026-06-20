<div>
    <x-page-header title="Mój profil" description="Zaktualizuj swoje dane kontaktowe i hasło.">
        <x-slot:actions>
            <a href="{{ route('dashboard') }}" wire:navigate class="btn btn--ghost">← Powrót</a>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save">
        <x-section title="Dane konta" card>
                <div class="form-grid">
                    <div class="field">
                        <label for="name">Imię i nazwisko *</label>
                        <input id="name" class="input" wire:model="name">
                        @error('name') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="email">E-mail *</label>
                        <input id="email" type="email" class="input" wire:model="email">
                        @error('email') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="phone">Telefon</label>
                        <input id="phone" class="input" wire:model="phone">
                        @error('phone') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>
        </x-section>

        <x-section title="Zmiana hasła" description="— zostaw puste, aby nie zmieniać" card style="margin-top:18px">
                <div class="form-grid">
                    <div class="field">
                        <label for="currentPassword">Aktualne hasło</label>
                        <input id="currentPassword" type="password" class="input" wire:model="currentPassword" autocomplete="current-password">
                        @error('currentPassword') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field"></div>

                    <div class="field">
                        <label for="newPassword">Nowe hasło</label>
                        <input id="newPassword" type="password" class="input" wire:model="newPassword" autocomplete="new-password">
                        @error('newPassword') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="newPassword_confirmation">Powtórz nowe hasło</label>
                        <input id="newPassword_confirmation" type="password" class="input" wire:model="newPassword_confirmation" autocomplete="new-password">
                    </div>
                </div>
        </x-section>

        <div style="margin-top:18px;display:flex;gap:10px">
            <button type="submit" class="btn btn--primary" wire:loading.attr="disabled" wire:target="save">Zapisz</button>
            <a href="{{ route('dashboard') }}" wire:navigate class="btn btn--ghost">Anuluj</a>
        </div>
    </form>

    {{-- Moje organizacje — tylko do odczytu (zakres autorytatywny per organizacja). --}}
    <div class="card" style="margin-top:24px">
        <div class="card__head">Moje organizacje ({{ $memberships->count() }})</div>
        <div class="card__body stack" style="gap:8px">
            @forelse ($memberships as $membership)
                <div class="list-row">
                    <span>
                        <strong>{{ $membership->organization?->name ?? '—' }}</strong>
                        — {{ $membership->role->label() }}
                        @if ($membership->isManager() && $membership->manager_scope)
                            · {{ $membership->manager_scope->label() }}
                        @endif
                        @if (! $membership->is_active)
                            <span class="badge badge--red">nieaktywne</span>
                        @endif
                    </span>
                </div>
            @empty
                <p class="muted" style="margin:0">Nie należysz do żadnej organizacji.</p>
            @endforelse
        </div>
    </div>
</div>
