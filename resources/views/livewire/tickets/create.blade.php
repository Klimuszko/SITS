<div>
    <div class="page-head">
        <div>
            <h1>Nowe zgłoszenie</h1>
            <p>Opisz problem — zgłoszenie trafi automatycznie do głównego opiekuna organizacji.</p>
        </div>
        <a href="{{ route('tickets.index') }}" wire:navigate class="btn btn--ghost">← Powrót</a>
    </div>

    <form wire:submit="save">
        <div class="card">
            <div class="card__body">
                <div class="form-grid">
                    <div class="field">
                        <label for="organization_id">Organizacja *</label>
                        <select id="organization_id" class="select" wire:model.live="organization_id">
                            <option value="">— wybierz —</option>
                            @foreach ($organizations as $org)
                                <option value="{{ $org->id }}">{{ $org->name }}</option>
                            @endforeach
                        </select>
                        @error('organization_id') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="ticket_category_id">Kategoria</label>
                        <select id="ticket_category_id" class="select" wire:model="ticket_category_id">
                            <option value="">— brak —</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field field--full">
                        <label for="title">Tytuł *</label>
                        <input id="title" class="input" wire:model="title" placeholder="Krótki opis problemu">
                        @error('title') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field field--full">
                        <label for="description">Opis *</label>
                        <textarea id="description" class="textarea" rows="6" wire:model="description" placeholder="Co się dzieje, od kiedy, czego dotyczy…"></textarea>
                        @error('description') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="location_id">Lokalizacja</label>
                        <select id="location_id" class="select" wire:model="location_id" @disabled(!$organization_id)>
                            <option value="">— brak —</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->pathLabel() }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="asset_id">Powiązany zasób</label>
                        <select id="asset_id" class="select" wire:model="asset_id" @disabled(!$organization_id)>
                            <option value="">— brak —</option>
                            @foreach ($assets as $asset)
                                <option value="{{ $asset->id }}">{{ $asset->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label for="ticket_priority_id">Priorytet</label>
                        <select id="ticket_priority_id" class="select" wire:model="ticket_priority_id">
                            <option value="">— domyślny —</option>
                            @foreach ($priorities as $priority)
                                <option value="{{ $priority->id }}">{{ $priority->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field field--full">
                        <label for="files">Załączniki</label>
                        <input id="files" type="file" class="input" wire:model="files" multiple>
                        <span class="hint">Maks. 20 MB na plik.</span>
                        @error('files.*') <span class="error">{{ $message }}</span> @enderror
                        <div wire:loading wire:target="files" class="muted" style="font-size:13px">Wgrywanie…</div>
                        @if ($files)
                            <ul class="muted" style="font-size:13px;margin:6px 0 0">
                                @foreach ($files as $file)
                                    <li>{{ $file->getClientOriginalName() }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:18px;display:flex;gap:10px">
            <button type="submit" class="btn btn--primary" wire:loading.attr="disabled" wire:target="save">Utwórz zgłoszenie</button>
            <a href="{{ route('tickets.index') }}" wire:navigate class="btn btn--ghost">Anuluj</a>
        </div>
    </form>
</div>
