<div>
    <div class="page-head">
        <div>
            <h1>{{ $article ? 'Edycja artykułu' : 'Nowy artykuł' }}</h1>
            <p>{{ $article?->title ?? 'Utwórz artykuł bazy wiedzy.' }}</p>
        </div>
        <a href="{{ route('knowledge.index') }}" wire:navigate class="btn btn--ghost">← Powrót</a>
    </div>

    <form wire:submit="save">
        <div class="card">
            <div class="card__body">
                <div class="form-grid">
                    <div class="field field--full">
                        <label for="title">Tytuł *</label>
                        <input id="title" class="input" wire:model="title">
                        @error('title') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="slug">Odnośnik (slug)
                            <span class="hint">— zostaw puste, aby wygenerować z tytułu</span>
                        </label>
                        <input id="slug" class="input" wire:model="slug">
                        @error('slug') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="knowledge_category_id">Kategoria</label>
                        <select id="knowledge_category_id" class="select" wire:model="knowledge_category_id">
                            <option value="">— bez kategorii —</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        @error('knowledge_category_id') <span class="error">{{ $message }}</span> @enderror
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
                        <label for="body">Treść (HTML)
                            <span class="hint">— HTML zostanie automatycznie oczyszczony przy zapisie</span>
                        </label>
                        {{-- wire:ignore: Livewire NIGDY nie może przerysować/zniszczyć kontenera edytora,
                             bo TinyMCE trzyma własny DOM nad textareą. Treść synchronizujemy ręcznie. --}}
                        <div wire:ignore>
                            {{-- TinyMCE czyta treść POCZĄTKOWĄ z textarea (renderowaną serwerowo: {{ $body }}).
                                 Sync do Livewire idzie RĘCZNIE: @this.set('body', ...) w setup edytora oraz
                                 przed zapisem. Pod wire:ignore wire:model byłby bezczynny (i nie zhydratyzowałby
                                 treści przy edycji), dlatego go NIE używamy. --}}
                            <textarea id="body" class="textarea" rows="14">{{ $body }}</textarea>
                        </div>
                        @error('body') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>
            </div>
        </div>

        <div style="margin-top:18px;display:flex;gap:10px">
            {{-- Przed wysłaniem formularza wpisujemy AKTUALNĄ treść edytora do propy body
                 (bez round-tripu — false), żeby save() po stronie serwera dostał świeży HTML. --}}
            <button type="submit" class="btn btn--primary"
                x-on:click="window.tinymce && tinymce.get('body') && $wire.set('body', tinymce.get('body').getContent(), false)"
                wire:loading.attr="disabled" wire:target="save">Zapisz</button>
            <a href="{{ route('knowledge.index') }}" wire:navigate class="btn btn--ghost">Anuluj</a>
        </div>
    </form>

    {{-- Reguły widoczności — tylko po zapisaniu artykułu --}}
    @if ($article)
        <div class="card" style="margin-top:24px">
            <div class="card__head">Widoczność ({{ $visibilities->count() }})</div>
            <div class="card__body stack" style="gap:8px">
                <p class="muted" style="margin:0">
                    Artykuł opublikowany jest widoczny dla użytkowników pasujących do co najmniej jednej reguły.
                    Brak reguł = widoczny tylko dla personelu i autora.
                </p>

                @forelse ($visibilities as $rule)
                    <div class="list-row">
                        <span>
                            @if ($rule->visibility_type === 'organization')
                                <strong>Organizacja</strong> — {{ $rule->organization?->name ?? '—' }}
                            @elseif ($rule->visibility_type === 'role')
                                <strong>Rola</strong> — {{ $rule->role?->label() ?? '—' }}
                            @elseif ($rule->visibility_type === 'user')
                                <strong>Użytkownik</strong> — {{ $rule->user?->name ?? '—' }}
                            @else
                                <strong>{{ $rule->visibility_type }}</strong>
                            @endif
                        </span>
                        <button type="button" class="btn-link" style="color:var(--danger)"
                            wire:click="removeVisibility({{ $rule->id }})"
                            wire:confirm="Usunąć tę regułę widoczności?">usuń</button>
                    </div>
                @empty
                    <p class="muted" style="margin:0">Brak reguł widoczności.</p>
                @endforelse

                <form wire:submit="addVisibility" class="form-grid" style="margin-top:10px">
                    <div class="field">
                        <label for="newVisibilityType">Typ reguły</label>
                        <select id="newVisibilityType" class="select" wire:model.live="newVisibilityType">
                            <option value="organization">Organizacja</option>
                            <option value="role">Rola</option>
                            <option value="user">Użytkownik</option>
                        </select>
                        @error('newVisibilityType') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    @if ($newVisibilityType === 'organization')
                        <div class="field">
                            <label for="newOrganizationId">Organizacja</label>
                            <select id="newOrganizationId" class="select" wire:model="newOrganizationId">
                                <option value="">— wybierz organizację —</option>
                                @foreach ($organizations as $org)
                                    <option value="{{ $org->id }}">{{ $org->name }}</option>
                                @endforeach
                            </select>
                            @error('newOrganizationId') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    @elseif ($newVisibilityType === 'role')
                        <div class="field">
                            <label for="newRole">Rola</label>
                            <select id="newRole" class="select" wire:model="newRole">
                                <option value="">— wybierz rolę —</option>
                                @foreach ($roles as $value => $label)
                                    <option value="{{ $value }}">{{ $label }}</option>
                                @endforeach
                            </select>
                            @error('newRole') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    @elseif ($newVisibilityType === 'user')
                        <div class="field">
                            <label for="newUserId">Użytkownik</label>
                            <select id="newUserId" class="select" wire:model="newUserId">
                                <option value="">— wybierz użytkownika —</option>
                                @foreach ($users as $u)
                                    <option value="{{ $u->id }}">{{ $u->name }} ({{ $u->email }})</option>
                                @endforeach
                            </select>
                            @error('newUserId') <span class="error">{{ $message }}</span> @enderror
                        </div>
                    @endif

                    <div class="field field--full">
                        <button type="submit" class="btn btn--ghost btn--sm" wire:loading.attr="disabled" wire:target="addVisibility">Dodaj regułę</button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Obrazy artykułu — tylko po zapisaniu artykułu (obraz przypina się do artykułu) --}}
        <div class="card" style="margin-top:24px">
            <div class="card__head">Obrazy artykułu ({{ $images->count() }})</div>
            <div class="card__body stack" style="gap:12px">
                <p class="muted" style="margin:0">
                    Wgraj własne obrazy (zrzuty ekranu), a następnie skopiuj snippet i wklej w treść artykułu (HTML).
                    Dozwolone: JPG, PNG, GIF, WEBP, BMP (max 4 MB).
                </p>

                @if (session('imageStatus'))
                    <p class="muted" style="margin:0;color:var(--success,#16a34a)">{{ session('imageStatus') }}</p>
                @endif

                <div class="field field--full">
                    <input type="file" wire:model="image" accept=".jpg,.jpeg,.png,.gif,.webp,.bmp">
                    <div wire:loading wire:target="image" class="hint">Wczytywanie pliku…</div>
                    @error('image') <span class="error">{{ $message }}</span> @enderror
                    <div style="margin-top:8px">
                        <button type="button" class="btn btn--ghost btn--sm"
                            wire:click="uploadImage"
                            wire:loading.attr="disabled" wire:target="uploadImage,image">Wgraj</button>
                    </div>
                </div>

                @forelse ($images as $img)
                    <div class="list-row" x-data="{ snippet: '<img src=\'{{ route('knowledge.image', $img) }}\' alt=\'\'>' }">
                        <div style="display:flex;align-items:center;gap:12px;flex:1;min-width:0">
                            <img src="{{ route('knowledge.image', $img) }}" alt="" style="max-height:60px;border:1px solid var(--border,#e5e7eb);border-radius:4px">
                            <input type="text" class="input" readonly
                                value="&lt;img src=&quot;{{ route('knowledge.image', $img) }}&quot; alt=&quot;&quot;&gt;"
                                style="flex:1;min-width:0;font-family:monospace;font-size:12px"
                                onclick="this.select()">
                        </div>
                        <div style="display:flex;gap:8px">
                            <button type="button" class="btn btn--ghost btn--sm"
                                x-on:click="navigator.clipboard.writeText(snippet)">Kopiuj</button>
                            <button type="button" class="btn-link" style="color:var(--danger)"
                                wire:click="removeImage({{ $img->id }})"
                                wire:confirm="Usunąć ten obraz? Jeśli jest użyty w treści, przestanie się wyświetlać.">usuń</button>
                        </div>
                    </div>
                @empty
                    <p class="muted" style="margin:0">Brak wgranych obrazów.</p>
                @endforelse
            </div>
        </div>
    @else
        <p class="muted" style="margin-top:18px">Reguły widoczności i obrazy dodasz po zapisaniu artykułu.</p>
    @endif

    {{-- ===================== Edytor WYSIWYG (Krok 2b) ===================== --}}
    {{-- TinyMCE 7 community (GPL, bez klucza API) z CDN. Ładowany TYLKO na tej stronie.
         Bezpieczeństwo treści (XSS) jest po stronie serwera (HtmlSanitizer przy zapisie) —
         edytor NIE jest granicą bezpieczeństwa, dlatego celowo nie okrawamy HTML-a (valid_elements:'*[*]',
         verify_html:false), żeby wklejony HTML z GLPI (flex/grid/inline-style) przetrwał. --}}
    <script src="https://cdn.jsdelivr.net/npm/tinymce@7/tinymce.min.js" referrerpolicy="origin"></script>
    <script>
        function initKbEditor() {
            // TinyMCE może jeszcze nie być załadowany (CDN) — wtedy próbujemy ponownie po onload skryptu.
            if (typeof tinymce === 'undefined') {
                return;
            }

            // Usuwamy ewentualną poprzednią instancję (np. po wire:navigate), żeby nie podwoić edytora.
            tinymce.remove('#body');

            tinymce.init({
                selector: '#body',
                license_key: 'gpl',
                promotion: false,
                branding: false,
                menubar: false,
                height: 520,
                plugins: 'code link lists table autolink image',
                toolbar: 'undo redo | blocks | bold italic underline | bullist numlist | link image table | code',
                // CRUCIAL: nie przepisuj/okrawaj HTML — wklejony HTML z GLPI musi przetrwać 1:1.
                // Granica bezpieczeństwa jest serwerowa (HtmlSanitizer przy zapisie artykułu).
                valid_elements: '*[*]',
                extended_valid_elements: '*[*]',
                verify_html: false,
                convert_urls: false,
                entity_encoding: 'raw',
                @if ($article)
                // Upload obrazu działa tylko dla zapisanego artykułu (endpoint potrzebuje {article}).
                images_upload_handler: function (blobInfo) {
                    return new Promise(function (resolve, reject) {
                        var fd = new FormData();
                        fd.append('file', blobInfo.blob(), blobInfo.filename());
                        fetch('{{ route('knowledge.image.upload', $article) }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                                'Accept': 'application/json',
                            },
                            body: fd,
                        })
                            .then(function (r) { return r.json(); })
                            .then(function (d) { d.location ? resolve(d.location) : reject('Upload failed'); })
                            .catch(function () { reject('Upload failed'); });
                    });
                },
                @endif
                setup: function (editor) {
                    // Synchronizacja treści edytora do propy Livewire BEZ round-tripu (false).
                    editor.on('Change KeyUp Undo Redo', function () {
                        @this.set('body', editor.getContent(), false);
                    });
                },
            });
        }

        // Inicjalizacja: po pełnym załadowaniu strony oraz po nawigacji Livewire (wire:navigate).
        document.addEventListener('livewire:navigated', initKbEditor);
        if (typeof tinymce !== 'undefined') {
            initKbEditor();
        } else {
            // Skrypt CDN mógł się jeszcze nie wykonać — odpal init po jego załadowaniu.
            document.addEventListener('DOMContentLoaded', initKbEditor);
            window.addEventListener('load', initKbEditor);
        }
    </script>
</div>
