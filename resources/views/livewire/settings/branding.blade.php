<div>
    <x-page-header title="Branding" description="Logo, favicon, sposób prezentacji marki i domyślny motyw aplikacji.">
        <x-slot:actions>
            <a href="{{ route('dashboard') }}" wire:navigate class="btn btn--ghost">← Powrót</a>
        </x-slot:actions>
    </x-page-header>

    @include('livewire.settings._nav')

    <form wire:submit="save" enctype="multipart/form-data">
        <x-section title="Marka i motyw" description="Jak prezentowana jest marka w pasku górnym oraz jaki motyw widzi nowy użytkownik." card>
            <div class="form-grid">
                <div class="field field--full">
                    <label for="appName">Nazwa strony / aplikacji</label>
                    <input id="appName" class="input" wire:model="appName" maxlength="255">
                    <span class="hint">Tytuł karty przeglądarki: „{{ $appName ?: 'Nazwa' }} – Sekcja"; również nazwa marki w pasku górnym.</span>
                    @error('appName') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="brandingMode">Tryb marki</label>
                    <select id="brandingMode" class="select" wire:model="brandingMode">
                        <option value="name">Tylko nazwa</option>
                        <option value="name_logo">Nazwa + logo</option>
                        <option value="logo">Tylko logo</option>
                    </select>
                    @error('brandingMode') <span class="error">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label for="defaultTheme">Domyślny motyw</label>
                    <select id="defaultTheme" class="select" wire:model="defaultTheme">
                        <option value="dark">Ciemny</option>
                        <option value="light">Jasny</option>
                    </select>
                    @error('defaultTheme') <span class="error">{{ $message }}</span> @enderror
                    <span class="hint">Indywidualny wybór użytkownika (zapisany w przeglądarce) ma pierwszeństwo.</span>
                </div>
            </div>
        </x-section>

        <x-section title="Logo" description="SVG, PNG lub JPG — maks. 1 MB. SVG jest sanityzowany po stronie serwera." card>
            <div class="field">
                @if ($logoUrl)
                    <div class="brand-preview">
                        <img src="{{ $logoUrl }}" alt="Aktualne logo" style="height:40px;width:auto;display:block">
                    </div>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="removeLogo"
                            wire:confirm="Usunąć logo?">Usuń logo</button>
                @else
                    <p class="muted" style="margin:0 0 8px">Brak logo.</p>
                @endif

                <label for="logo">Wgraj nowe logo</label>
                <input id="logo" type="file" class="input" wire:model="logo" accept=".svg,.png,.jpg,.jpeg">
                <div wire:loading wire:target="logo" class="hint">Wczytywanie…</div>
                @error('logo') <span class="error">{{ $message }}</span> @enderror
            </div>
        </x-section>

        <x-section title="Favicon" description="ICO, PNG lub SVG — maks. 256 KB." card>
            <div class="field">
                @if ($faviconUrl)
                    <div class="brand-preview">
                        <img src="{{ $faviconUrl }}" alt="Aktualny favicon" style="height:32px;width:auto;display:block">
                    </div>
                    <button type="button" class="btn btn--ghost btn--sm" wire:click="removeFavicon"
                            wire:confirm="Usunąć favicon?">Usuń favicon</button>
                @else
                    <p class="muted" style="margin:0 0 8px">Brak favikony.</p>
                @endif

                <label for="favicon">Wgraj nowy favicon</label>
                <input id="favicon" type="file" class="input" wire:model="favicon" accept=".ico,.png,.svg">
                <div wire:loading wire:target="favicon" class="hint">Wczytywanie…</div>
                @error('favicon') <span class="error">{{ $message }}</span> @enderror
            </div>
        </x-section>

        <div style="margin-top:18px;display:flex;gap:10px">
            <button type="submit" class="btn btn--primary" wire:loading.attr="disabled" wire:target="save">Zapisz</button>
            <a href="{{ route('dashboard') }}" wire:navigate class="btn btn--ghost">Anuluj</a>
        </div>
    </form>
</div>
