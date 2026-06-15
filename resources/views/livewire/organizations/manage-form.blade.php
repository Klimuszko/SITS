<div>
    <div class="page-head">
        <div>
            <h1>{{ $organization ? 'Edycja organizacji' : 'Nowa organizacja' }}</h1>
            <p>{{ $organization?->name ?? 'Utwórz nowego klienta w systemie.' }}</p>
        </div>
        <a href="{{ route('organizations.index') }}" wire:navigate class="btn btn--ghost">← Powrót</a>
    </div>

    <form wire:submit="save">
        <div class="card">
            <div class="card__body">
                <div class="form-grid">
                    <div class="field">
                        <label for="name">Nazwa *</label>
                        <input id="name" class="input" wire:model="name">
                        @error('name') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="type">Typ *</label>
                        <select id="type" class="select" wire:model="type">
                            @foreach ($types as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('type') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="parent_id">Organizacja nadrzędna</label>
                        <select id="parent_id" class="select" wire:model="parent_id">
                            <option value="">— brak (organizacja główna) —</option>
                            @foreach ($parents as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                            @endforeach
                        </select>
                        @error('parent_id') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="status">Status *</label>
                        <select id="status" class="select" wire:model.live="status">
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field field--full">
                        <label for="support">Domyślny (główny) support
                            <span class="hint">— wymagany dla organizacji aktywnej</span>
                        </label>
                        <select id="support" class="select" wire:model="default_support_user_id">
                            <option value="">— wybierz supporta —</option>
                            @foreach ($supportUsers as $support)
                                <option value="{{ $support->id }}">{{ $support->name }} ({{ $support->email }})</option>
                            @endforeach
                        </select>
                        @error('default_support_user_id') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="nip">NIP</label>
                        <input id="nip" class="input" wire:model="nip">
                        @error('nip') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="contact_phone">Telefon kontaktowy</label>
                        <input id="contact_phone" class="input" wire:model="contact_phone">
                        @error('contact_phone') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="contact_email">E-mail kontaktowy</label>
                        <input id="contact_email" type="email" class="input" wire:model="contact_email">
                        @error('contact_email') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="address">Adres</label>
                        <input id="address" class="input" wire:model="address">
                        @error('address') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field field--full">
                        <label for="internal_note">Notatka wewnętrzna (support/admin)</label>
                        <textarea id="internal_note" class="textarea" wire:model="internal_note"></textarea>
                        @error('internal_note') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:18px;display:flex;gap:10px">
            <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">Zapisz</button>
            <a href="{{ route('organizations.index') }}" wire:navigate class="btn btn--ghost">Anuluj</a>
        </div>
    </form>
</div>
