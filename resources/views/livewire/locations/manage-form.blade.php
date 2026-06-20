<div>
    <x-page-header :title="$location ? 'Edycja lokalizacji' : 'Nowa lokalizacja'" :description="$location?->name ?? 'Utwórz lokalizację w wybranej organizacji.'">
        <x-slot:actions>
            <a href="{{ route('locations.index') }}" wire:navigate class="btn btn--ghost">← Powrót</a>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save">
        <x-section title="Dane lokalizacji" card>
                <div class="form-grid">
                    <div class="field">
                        <label for="organization_id">Organizacja *</label>
                        <select id="organization_id" class="select" wire:model.live="organization_id">
                            <option value="">— wybierz organizację —</option>
                            @foreach ($organizations as $org)
                                <option value="{{ $org->id }}">{{ $org->name }}</option>
                            @endforeach
                        </select>
                        @error('organization_id') <span class="error">{{ $message }}</span> @enderror
                    </div>

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
                        <label for="parent_id">Lokalizacja nadrzędna</label>
                        <select id="parent_id" class="select" wire:model="parent_id">
                            <option value="">— brak (lokalizacja główna) —</option>
                            @foreach ($parents as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                            @endforeach
                        </select>
                        @error('parent_id') <span class="error">{{ $message }}</span> @enderror
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
                        <label for="description">Opis</label>
                        <textarea id="description" class="textarea" wire:model="description"></textarea>
                        @error('description') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>
        </x-section>

        <div style="margin-top:18px;display:flex;gap:10px">
            <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">Zapisz</button>
            <a href="{{ route('locations.index') }}" wire:navigate class="btn btn--ghost">Anuluj</a>
        </div>
    </form>
</div>
