<div>
    <div class="page-head">
        <div>
            <h1>{{ $asset ? 'Edycja zasobu' : 'Nowy zasób' }}</h1>
            <p>{{ $asset?->name ?? 'Dodaj zasób do ewidencji (CMDB).' }}</p>
        </div>
        <a href="{{ route('assets.index') }}" wire:navigate class="btn btn--ghost">← Powrót</a>
    </div>

    <form wire:submit="save">
        <div class="card">
            <div class="card__body">
                <div class="form-grid">
                    <div class="field">
                        <label for="organization_id">Organizacja *</label>
                        <select id="organization_id" class="select" wire:model.live="organization_id" @disabled($asset)>
                            <option value="">— wybierz —</option>
                            @foreach ($organizations as $org)
                                <option value="{{ $org->id }}">{{ $org->name }}</option>
                            @endforeach
                        </select>
                        @error('organization_id') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="asset_category_id">Kategoria *</label>
                        <select id="asset_category_id" class="select" wire:model.live="asset_category_id" @disabled(!$organization_id)>
                            <option value="">— wybierz —</option>
                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                        @error('asset_category_id') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field field--full">
                        <label for="name">Nazwa *</label>
                        <input id="name" class="input" wire:model="name" placeholder="Np. NAS Synology, Stacja robocza biuro 1">
                        @error('name') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="inventory_code">Kod inwentarzowy</label>
                        <input id="inventory_code" class="input" wire:model="inventory_code">
                        @error('inventory_code') <span class="error">{{ $message }}</span> @enderror
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
                        <label for="parent_asset_id">Zasób nadrzędny</label>
                        <select id="parent_asset_id" class="select" wire:model="parent_asset_id" @disabled(!$organization_id)>
                            <option value="">— brak —</option>
                            @foreach ($parents as $parent)
                                <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                            @endforeach
                        </select>
                        @error('parent_asset_id') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field field--full">
                        <label class="checkbox">
                            <input type="checkbox" wire:model="is_private">
                            <span>Zasób prywatny — widoczny tylko dla przypisanych użytkowników i personelu</span>
                        </label>
                        @error('is_private') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field field--full">
                        <label for="notes">Notatki</label>
                        <textarea id="notes" class="textarea" rows="4" wire:model="notes"></textarea>
                        @error('notes') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        {{-- ------------------------- Pola dynamiczne kategorii ------------------------- --}}
        @if ($asset_category_id)
            <div class="card" style="margin-top:18px">
                <div class="card__head">Pola kategorii</div>
                <div class="card__body">
                    @if ($fields->isEmpty())
                        <p class="muted" style="margin:0">Ta kategoria nie ma dodatkowych pól do wypełnienia.</p>
                    @else
                        <div class="form-grid">
                            @foreach ($fields as $field)
                                @php($key = 'fieldValues.'.$field->id)
                                <div class="field {{ in_array($field->type, [\App\Enums\AssetFieldType::Textarea], true) ? 'field--full' : '' }}">
                                    @if ($field->type === \App\Enums\AssetFieldType::Boolean)
                                        <label class="checkbox">
                                            <input type="checkbox" wire:model="fieldValues.{{ $field->id }}">
                                            <span>{{ $field->name }}</span>
                                        </label>
                                    @else
                                        <label for="field_{{ $field->id }}">
                                            {{ $field->name }}@if ($field->is_required) * @endif
                                        </label>

                                        @switch($field->type)
                                            @case(\App\Enums\AssetFieldType::Textarea)
                                                <textarea id="field_{{ $field->id }}" class="textarea" rows="3" wire:model="fieldValues.{{ $field->id }}"></textarea>
                                                @break

                                            @case(\App\Enums\AssetFieldType::Select)
                                                <select id="field_{{ $field->id }}" class="select" wire:model="fieldValues.{{ $field->id }}">
                                                    <option value="">— wybierz —</option>
                                                    @foreach ($field->options ?? [] as $option)
                                                        <option value="{{ $option }}">{{ $option }}</option>
                                                    @endforeach
                                                </select>
                                                @break

                                            @case(\App\Enums\AssetFieldType::Number)
                                                <input id="field_{{ $field->id }}" type="number" step="any" class="input" wire:model="fieldValues.{{ $field->id }}">
                                                @break

                                            @case(\App\Enums\AssetFieldType::Date)
                                                <input id="field_{{ $field->id }}" type="date" class="input" wire:model="fieldValues.{{ $field->id }}">
                                                @break

                                            @default
                                                <input id="field_{{ $field->id }}" class="input" wire:model="fieldValues.{{ $field->id }}">
                                        @endswitch
                                    @endif

                                    @error($key) <span class="error">{{ $message }}</span> @enderror
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($hasSkippedFields)
                        <p class="hint" style="margin-top:12px">
                            Niektóre pola (pliki / relacje) nie są jeszcze obsługiwane w tym widoku i zostały pominięte.
                        </p>
                    @endif
                </div>
            </div>
        @endif

        <div style="margin-top:18px;display:flex;gap:10px">
            <button type="submit" class="btn btn--primary" wire:loading.attr="disabled" wire:target="save">Zapisz</button>
            <a href="{{ route('assets.index') }}" wire:navigate class="btn btn--ghost">Anuluj</a>
        </div>
    </form>
</div>
