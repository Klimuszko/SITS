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

    <section style="margin-top:28px">
        <h2 style="margin:0 0 4px;font-size:18px">Oczekujące zaproszenia</h2>
        <p class="muted" style="margin:0 0 14px;font-size:13px">
            Konta z wysłanym zaproszeniem, które nie ustawiły jeszcze hasła ani nie zalogowały się przez SSO.
            Link wygasa po {{ $inviteExpiryDays }} dniach.
        </p>

        @if ($copiedLink !== null)
            <div class="alert" style="margin-bottom:14px;background:var(--c-amber-bg);color:var(--c-amber);border-color:#fde68a">
                <strong>Link „ustaw hasło”</strong> (świeży, jednorazowy — skopiuj i przekaż ręcznie):
                <input type="text" class="input" readonly value="{{ $copiedLink }}"
                       onclick="this.select()" style="margin-top:8px;width:100%;font-family:monospace;font-size:12px">
            </div>
        @endif

        <div class="card">
            <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">E-mail</th>
                        <th scope="col">Nazwa</th>
                        <th scope="col">Organizacja</th>
                        <th scope="col">Zaproszono</th>
                        <th scope="col">Status</th>
                        <th scope="col"></th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($pending as $p)
                    <tr>
                        <td class="muted">{{ $p->email }}</td>
                        <td><strong>{{ $p->name }}</strong></td>
                        <td class="muted">{{ $p->memberships->first()?->organization?->name ?? '—' }}</td>
                        <td class="muted">{{ $p->invited_at->diffForHumans() }}</td>
                        <td>
                            @if ($p->invited_at->lt(now()->subDays($inviteExpiryDays)))
                                <span class="badge badge--red">Wygasł</span>
                            @else
                                <span class="badge badge--amber">Oczekuje</span>
                            @endif
                        </td>
                        <td style="text-align:right;white-space:nowrap">
                            <button type="button" class="btn btn--ghost btn--sm"
                                    wire:click="copyInviteLink({{ $p->id }})"
                                    wire:loading.attr="disabled" wire:target="copyInviteLink({{ $p->id }})">
                                Kopiuj link
                            </button>
                            <button type="button" class="btn btn--ghost btn--sm"
                                    wire:click="resendInvitation({{ $p->id }})"
                                    wire:loading.attr="disabled" wire:target="resendInvitation({{ $p->id }})">
                                Wyślij ponownie
                            </button>
                            <button type="button" class="btn btn--danger btn--sm"
                                    wire:click="deleteInvitation({{ $p->id }})"
                                    wire:confirm="Usunąć to zaproszenie? Konto zostanie trwale usunięte, a e-mail zwolniony do ponownego zaproszenia.">
                                Usuń
                            </button>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="table__empty">Brak oczekujących zaproszeń.</td></tr>
                @endforelse
                </tbody>
            </table>
            </div>

            @if ($pending->hasPages())
                {{ $pending->links() }}
            @endif
        </div>
    </section>
</div>
