<div>
    <x-page-header title="Profile dostępu"
        description="Nazwane zestawy uprawnień. Profile systemowe odwzorowują role; twórz własne i przypisuj im konkretne uprawnienia." />

    @if (session('status'))
        <div class="alert alert--success" style="margin-bottom:18px">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert--error" style="margin-bottom:18px">{{ session('error') }}</div>
    @endif

    @php($systemLocked = $editingProfile && $editingProfile->is_system)

    <form wire:submit="save">
        <div class="card">
            <div class="card__body">
                <div class="form-grid">
                    <div class="field">
                        <label for="name">Nazwa *</label>
                        <input id="name" class="input" wire:model="name" @disabled($systemLocked)>
                        @error('name') <span class="error">{{ $message }}</span> @enderror
                        @if ($systemLocked)
                            <span class="hint">Profil systemowy — nazwa i typ są zablokowane; edytujesz tylko uprawnienia.</span>
                        @endif
                    </div>

                    <div class="field">
                        <label for="applies_to">Typ profilu *</label>
                        <select id="applies_to" class="select" wire:model="applies_to" @disabled($systemLocked)>
                            @foreach ($appliesToOptions as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('applies_to') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field field--full">
                        <label for="description">Opis</label>
                        <input id="description" class="input" wire:model="description"
                               placeholder="Do czego służy ten profil (opcjonalnie)">
                        @error('description') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field field--full">
                        <label class="checkbox">
                            <input type="checkbox" wire:model="is_active">
                            <span>Aktywny</span>
                        </label>
                    </div>
                </div>

                {{-- Macierz uprawnień pogrupowana po sekcjach. --}}
                <h2 style="font-size:15px;margin:20px 0 4px">Uprawnienia</h2>
                <p class="muted" style="margin:0 0 14px;font-size:13px">
                    Zaznacz, do czego profil ma dostęp. Uprawnienia, których sam nie posiadasz, są wyłączone.
                </p>

                <div class="grid grid--2">
                    @foreach ($catalog as $group => $perms)
                        <div class="card" style="background:transparent">
                            <div class="card__body">
                                <strong style="display:block;margin-bottom:8px">{{ $group }}</strong>
                                <div class="stack" style="gap:6px">
                                    @foreach ($perms as $perm)
                                        @php($allowed = in_array($perm->value, $assignable, true))
                                        <label class="checkbox" @if (! $allowed) title="Nie posiadasz tego uprawnienia" style="opacity:.55" @endif>
                                            <input type="checkbox" wire:model="permissions"
                                                   value="{{ $perm->value }}" @disabled(! $allowed)>
                                            <span>{{ $perm->label() }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div style="margin-top:18px;display:flex;gap:10px">
                    <button type="submit" class="btn btn--primary" wire:loading.attr="disabled" wire:target="save">
                        {{ $editingId ? 'Zapisz zmiany' : 'Utwórz profil' }}
                    </button>
                    @if ($editingId)
                        <button type="button" class="btn btn--ghost" wire:click="resetForm">Anuluj</button>
                    @endif
                </div>
            </div>
        </div>
    </form>

    <div class="card" style="margin-top:18px">
        <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th scope="col">Profil</th>
                    <th scope="col">Typ</th>
                    <th scope="col">Uprawnienia</th>
                    <th scope="col">Status</th>
                    <th scope="col"></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($profiles as $profile)
                <tr>
                    <td>
                        <strong>{{ $profile->name }}</strong>
                        @if ($profile->is_system)
                            <span class="badge badge--slate" style="margin-left:6px">systemowy</span>
                        @endif
                        @if ($profile->description)
                            <div class="muted" style="font-size:12px">{{ $profile->description }}</div>
                        @endif
                    </td>
                    <td class="muted">{{ $profile->applies_to === \App\Models\AccessProfile::APPLIES_STAFF ? 'Personel' : 'Klient' }}</td>
                    <td class="muted">{{ count($profile->permissions ?? []) }}</td>
                    <td>
                        @if ($profile->is_active)
                            <span class="badge badge--green">Aktywny</span>
                        @else
                            <span class="badge badge--gray">Nieaktywny</span>
                        @endif
                    </td>
                    <td style="text-align:right">
                        @if ($profile->key === \App\Models\AccessProfile::SUPER_ADMIN)
                            <span class="muted" style="font-size:12px">pełny dostęp</span>
                        @else
                            <button type="button" class="btn btn--ghost btn--sm" wire:click="edit({{ $profile->id }})">Edytuj</button>
                            @unless ($profile->is_system)
                                <button type="button" class="btn btn--danger btn--sm"
                                        wire:click="delete({{ $profile->id }})"
                                        wire:confirm="Usunąć ten profil? Przypisani użytkownicy wrócą do domyślnych uprawnień swojej roli.">
                                    Usuń
                                </button>
                            @endunless
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="table__empty">Brak profili.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
