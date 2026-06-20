<div>
    <x-page-header title="Ikony menu" description="Wgraj własne ikony SVG dla pozycji menu. Domyślna pozostaje, dopóki nie wgrasz własnej.">
        <x-slot:actions>
            <a href="{{ route('dashboard') }}" wire:navigate class="btn btn--ghost">← Powrót</a>
        </x-slot:actions>
    </x-page-header>

    @include('livewire.settings._nav')

    @if (session('status'))
        <div class="alert alert--success">{{ session('status') }}</div>
    @endif

    <x-section title="Ikony" description="Najlepiej SVG 24×24, outline, stroke='currentColor' — wtedy ikona dopasuje się do motywu. Maks. 256 KB. SVG jest sanityzowany przy zapisie." card>
        <div class="stack" style="gap:6px">
            @foreach ($icons as $icon)
                <div class="list-row" style="align-items:center;flex-wrap:wrap;gap:12px">
                    <div style="display:flex;align-items:center;gap:14px;min-width:220px">
                        <span style="display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;color:var(--text)">
                            <x-icon :name="$icon['name']" />
                        </span>
                        <div>
                            <div style="font-weight:600">
                                {{ $icon['name'] }}
                                @if ($icon['custom'])
                                    <span class="badge badge--green" style="margin-left:6px">własna</span>
                                @endif
                            </div>
                            <div class="muted" style="font-size:13px">{{ $icon['used'] }}</div>
                        </div>
                    </div>

                    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                        <input type="file" wire:model="uploads.{{ $icon['name'] }}" accept=".svg" style="max-width:200px">
                        <button type="button" class="btn btn--ghost btn--sm"
                                wire:click="save('{{ $icon['name'] }}')"
                                wire:loading.attr="disabled" wire:target="uploads.{{ $icon['name'] }}, save">Wgraj</button>
                        @if ($icon['custom'])
                            <button type="button" class="btn btn--ghost btn--sm"
                                    wire:click="resetIcon('{{ $icon['name'] }}')"
                                    wire:confirm="Przywrócić domyślną ikonę „{{ $icon['name'] }}"?">Domyślna</button>
                        @endif
                    </div>

                    @error('uploads.'.$icon['name'])
                        <span class="error" style="flex-basis:100%">{{ $message }}</span>
                    @enderror
                </div>
            @endforeach
        </div>
    </x-section>
</div>
