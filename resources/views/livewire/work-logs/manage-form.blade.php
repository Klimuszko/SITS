<div>
    <x-page-header :title="$log ? 'Edycja pracy administracyjnej' : 'Nowa praca administracyjna'" :description="$log?->title ?? 'Zarejestruj pracę wykonaną dla klienta.'">
        <x-slot:actions>
            <a href="{{ route('work-logs.index') }}" wire:navigate class="btn btn--ghost">← Powrót</a>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save">
        <x-section title="Szczegóły pracy" card>
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
                        <label for="work_type">Rodzaj pracy</label>
                        <input id="work_type" class="input" wire:model="work_type" placeholder="np. Przegląd, Backup, Konfiguracja">
                        @error('work_type') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field field--full">
                        <label for="title">Tytuł *</label>
                        <input id="title" class="input" wire:model="title" placeholder="Krótki opis wykonanej pracy">
                        @error('title') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field field--full">
                        <label for="description">Opis *</label>
                        <textarea id="description" class="textarea" rows="6" wire:model="description" placeholder="Co zostało zrobione, zakres, uwagi…"></textarea>
                        @error('description') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="location_id">Lokalizacja</label>
                        <select id="location_id" class="select" wire:model="location_id" @disabled(!$organization_id)>
                            <option value="">— brak —</option>
                            @foreach ($locations as $location)
                                <option value="{{ $location->id }}">{{ $location->name }}</option>
                            @endforeach
                        </select>
                        @error('location_id') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="asset_id">Powiązany zasób</label>
                        <select id="asset_id" class="select" wire:model="asset_id" @disabled(!$organization_id)>
                            <option value="">— brak —</option>
                            @foreach ($assets as $asset)
                                <option value="{{ $asset->id }}">{{ $asset->name }}</option>
                            @endforeach
                        </select>
                        @error('asset_id') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="performed_by">Wykonawca *</label>
                        <select id="performed_by" class="select" wire:model="performed_by">
                            <option value="">— wybierz —</option>
                            @foreach ($performers as $performer)
                                <option value="{{ $performer->id }}">{{ $performer->name }} — {{ $performer->role->label() }}</option>
                            @endforeach
                        </select>
                        @error('performed_by') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="performed_at">Data wykonania *</label>
                        <input id="performed_at" type="datetime-local" class="input" wire:model="performed_at">
                        @error('performed_at') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="duration_minutes">Czas pracy (minuty)</label>
                        <input id="duration_minutes" type="number" min="0" class="input" wire:model="duration_minutes" placeholder="np. 90">
                        @error('duration_minutes') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="status">Status *</label>
                        <select id="status" class="select" wire:model="status">
                            @foreach ($statuses as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('status') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field field--full">
                        <label class="checkbox">
                            <input type="checkbox" wire:model="visible_to_manager">
                            <span>Widoczna dla managera organizacji</span>
                        </label>
                        <label class="checkbox">
                            <input type="checkbox" wire:model="visible_to_user">
                            <span>Widoczna dla zwykłych użytkowników organizacji</span>
                        </label>
                        <span class="hint">Niewidoczne dla klienta prace pozostają dostępne wyłącznie dla personelu.</span>
                    </div>
                </div>
        </x-section>

        <div style="margin-top:18px;display:flex;gap:10px">
            <button type="submit" class="btn btn--primary" wire:loading.attr="disabled" wire:target="save">Zapisz</button>
            <a href="{{ route('work-logs.index') }}" wire:navigate class="btn btn--ghost">Anuluj</a>
        </div>
    </form>
</div>
