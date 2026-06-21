<div>
    <x-page-header title="Zaproś użytkowników" description="Wklej wiele adresów e-mail naraz. Każda osoba dostanie konto i e-mail z linkiem do ustawienia hasła (lub może zalogować się przez Microsoft/Google).">
        <x-slot:actions>
            <a href="{{ route('users.index') }}" wire:navigate class="btn btn--ghost">← Powrót</a>
        </x-slot:actions>
    </x-page-header>

    @if (session('status'))
        <div class="alert alert--success" style="margin-bottom:18px">{{ session('status') }}</div>
    @endif

    <form wire:submit="invite">
        <div class="card">
            <div class="card__body stack" style="gap:16px">
                <div class="field">
                    <label for="emails">Adresy e-mail *</label>
                    <textarea id="emails" class="textarea" rows="6" wire:model="emails"
                              placeholder="jan@firma.pl, anna@firma.pl&#10;piotr@firma.pl"></textarea>
                    <span class="hint">Oddziel przecinkami, średnikami lub nowymi liniami. Duplikaty i istniejące konta są pomijane.</span>
                    @error('emails') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div>
                    <strong style="display:block;margin-bottom:4px">Przypisanie do organizacji (opcjonalne)</strong>
                    <p class="muted" style="margin:0 0 10px;font-size:13px">Zostaw puste, by zaprosić bez przypisania — wtedy konta przypiszesz później.</p>

                    <div class="form-grid">
                        <div class="field">
                            <label for="organization_id">Organizacja</label>
                            <select id="organization_id" class="select" wire:model.live="organization_id">
                                <option value="">— bez przypisania —</option>
                                @foreach ($organizations as $org)
                                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                                @endforeach
                            </select>
                            @error('organization_id') <span class="error">{{ $message }}</span> @enderror
                        </div>

                        @if ($organization_id)
                            <div class="field">
                                <label for="org_role">Rola w organizacji</label>
                                <select id="org_role" class="select" wire:model.live="org_role">
                                    @foreach ($orgRoles as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                @error('org_role') <span class="error">{{ $message }}</span> @enderror
                            </div>

                            @if ($org_role === 'manager')
                                <div class="field">
                                    <label for="manager_scope">Zakres managera</label>
                                    <select id="manager_scope" class="select" wire:model="manager_scope">
                                        <option value="">— wybierz zakres —</option>
                                        @foreach ($managerScopes as $value => $label)
                                            <option value="{{ $value }}">{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('manager_scope') <span class="error">{{ $message }}</span> @enderror
                                </div>
                            @endif

                            <div class="field">
                                <label for="access_profile_id">Profil dostępu</label>
                                <select id="access_profile_id" class="select" wire:model="access_profile_id">
                                    <option value="">— domyślny dla roli —</option>
                                    @foreach ($clientProfiles as $profile)
                                        <option value="{{ $profile->id }}">{{ $profile->name }}</option>
                                    @endforeach
                                </select>
                                @error('access_profile_id') <span class="error">{{ $message }}</span> @enderror
                            </div>

                            <div class="field">
                                <label class="checkbox">
                                    <input type="checkbox" wire:model="membership_active">
                                    Członkostwo aktywne
                                </label>
                            </div>
                        @endif
                    </div>
                </div>

                <div style="display:flex;gap:10px">
                    <button type="submit" class="btn btn--primary" wire:loading.attr="disabled" wire:target="invite">
                        <span wire:loading.remove wire:target="invite">Wyślij zaproszenia</span>
                        <span wire:loading wire:target="invite">Wysyłanie…</span>
                    </button>
                    <a href="{{ route('users.index') }}" wire:navigate class="btn btn--ghost">Anuluj</a>
                </div>
            </div>
        </div>
    </form>
</div>
