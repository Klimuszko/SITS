<div>
    <x-page-header :title="$user ? 'Edycja użytkownika' : 'Nowy użytkownik'" :description="$user?->name ?? 'Utwórz nowe konto personelu lub klienta.'">
        <x-slot:actions>
            <a href="{{ route('users.index') }}" wire:navigate class="btn btn--ghost">← Powrót</a>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save">
        <div class="card">
            <div class="card__body">
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
                        <label for="role">Rola *
                            @if ($isSelf)<span class="hint">— nie możesz zmienić własnej roli</span>@endif
                        </label>
                        <select id="role" class="select" wire:model="role" @disabled($isSelf)>
                            @foreach ($roleOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('role') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="phone">Telefon</label>
                        <input id="phone" class="input" wire:model="phone">
                        @error('phone') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="password">Hasło {{ $user ? '' : '*' }}
                            @if ($user)<span class="hint">— zostaw puste, aby nie zmieniać</span>@endif
                        </label>
                        <input id="password" type="password" class="input" wire:model="password" autocomplete="new-password">
                        @error('password') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="password_confirmation">Powtórz hasło</label>
                        <input id="password_confirmation" type="password" class="input" wire:model="password_confirmation" autocomplete="new-password">
                    </div>

                    <div class="field field--full">
                        <label class="checkbox">
                            <input type="checkbox" wire:model="is_active" @disabled($isSelf)>
                            Konto aktywne
                            @if ($isSelf)<span class="hint">— nie możesz dezaktywować własnego konta</span>@endif
                        </label>
                        @error('is_active') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:18px;display:flex;gap:10px">
            <button type="submit" class="btn btn--primary" wire:loading.attr="disabled" wire:target="save">Zapisz</button>
            <a href="{{ route('users.index') }}" wire:navigate class="btn btn--ghost">Anuluj</a>
        </div>
    </form>

    {{-- Członkostwa w organizacjach — tylko po zapisaniu użytkownika --}}
    @if ($user)
        <div class="card" style="margin-top:24px">
            <div class="card__head">Członkostwa w organizacjach ({{ $memberships->count() }})</div>
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
                        <button type="button" class="btn-link" style="color:var(--danger)"
                            wire:click="removeMembership({{ $membership->id }})"
                            wire:confirm="Cofnąć członkostwo w tej organizacji?">usuń</button>
                    </div>
                @empty
                    <p class="muted" style="margin:0">Brak członkostw.</p>
                @endforelse

                @if ($organizations->isNotEmpty())
                    <form wire:submit="addMembership" class="form-grid" style="margin-top:10px">
                        <div class="field">
                            <label for="newOrganizationId">Organizacja</label>
                            <select id="newOrganizationId" class="select" wire:model="newOrganizationId">
                                <option value="">— wybierz organizację —</option>
                                @foreach ($organizations as $org)
                                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                                @endforeach
                            </select>
                            @error('newOrganizationId') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        <div class="field">
                            <label for="newOrgRole">Rola w organizacji</label>
                            <select id="newOrgRole" class="select" wire:model.live="newOrgRole">
                                @foreach ($orgRoles as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('newOrgRole') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        @if ($newOrgRole === 'manager')
                            <div class="field">
                                <label for="newManagerScope">Zakres managera</label>
                                <select id="newManagerScope" class="select" wire:model="newManagerScope">
                                    <option value="">— wybierz zakres —</option>
                                    @foreach ($managerScopes as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('newManagerScope') <span class="error">{{ $message }}</span> @enderror
                            </div>
                        @endif

                        <div class="field">
                            <label class="checkbox">
                                <input type="checkbox" wire:model="newMembershipActive">
                                Członkostwo aktywne
                            </label>
                        </div>

                        <div class="field field--full">
                            <button type="submit" class="btn btn--ghost btn--sm" wire:loading.attr="disabled" wire:target="addMembership">Dodaj członkostwo</button>
                        </div>
                    </form>
                @else
                    <p class="muted" style="margin:6px 0 0">Użytkownik należy już do wszystkich organizacji.</p>
                @endif
            </div>
        </div>
    @else
        <p class="muted" style="margin-top:18px">Członkostwa w organizacjach dodasz po utworzeniu konta.</p>
    @endif
</div>
