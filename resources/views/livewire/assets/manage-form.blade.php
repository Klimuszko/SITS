<div>
    <x-page-header :title="$asset ? 'Edycja zasobu' : 'Nowy zasób'" :description="$asset?->name ?? 'Dodaj zasób do ewidencji (CMDB).'">
        <x-slot:actions>
            <a href="{{ route('assets.index') }}" wire:navigate class="btn btn--ghost">← Powrót</a>
        </x-slot:actions>
    </x-page-header>

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
            {{-- Pola bez sekcji (kategorie „płaskie” ze Step 3). --}}
            @if ($looseFields->isNotEmpty())
                <div class="card" style="margin-top:18px">
                    <div class="card__head">Pola kategorii</div>
                    <div class="card__body">
                        <div class="form-grid">
                            @foreach ($looseFields as $field)
                                @include('livewire.assets._field', [
                                    'field' => $field,
                                    'model' => 'values.'.$field->id,
                                    'key' => 'values.'.$field->id,
                                    'id' => 'field_'.$field->id,
                                ])
                            @endforeach
                        </div>
                    </div>
                </div>
            @endif

            {{-- Sekcje, podsekcje i grupy powtarzalne w kolejności struktury. --}}
            @foreach ($tree as $node)
                <div class="card" style="margin-top:18px" wire:key="section-{{ $node->id }}">
                    <div class="card__head">{{ $node->name }}</div>
                    <div class="card__body">
                        @include('livewire.assets._form-section', ['node' => $node, 'depth' => 0])
                    </div>
                </div>
            @endforeach

            @if ($tree->isEmpty() && $looseFields->isEmpty())
                <div class="card" style="margin-top:18px">
                    <div class="card__body">
                        <p class="muted" style="margin:0">Ta kategoria nie ma dodatkowych pól do wypełnienia.</p>
                    </div>
                </div>
            @endif

            @if ($hasSkippedFields)
                <p class="hint" style="margin-top:12px">
                    Niektóre pola (pliki / relacje) nie są jeszcze obsługiwane w tym widoku i zostały pominięte.
                </p>
            @endif
        @endif

        <div style="margin-top:18px;display:flex;gap:10px">
            <button type="submit" class="btn btn--primary" wire:loading.attr="disabled" wire:target="save">Zapisz</button>
            <a href="{{ route('assets.index') }}" wire:navigate class="btn btn--ghost">Anuluj</a>
        </div>
    </form>
</div>
