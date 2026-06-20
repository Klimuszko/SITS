<div>
    <x-page-header title="Słowniki" description="Kategorie zgłoszeń — zarządzanie listą kategorii ticketów." />

    @include('livewire.dictionaries._tabs')

    @if (session('status'))
        <div class="alert alert--success" style="margin-bottom:18px">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert--error" style="margin-bottom:18px">{{ session('error') }}</div>
    @endif

    <form wire:submit="save">
        <div class="card">
            <div class="card__body">
                <div class="form-grid">
                    <div class="field">
                        <label for="name">Nazwa *</label>
                        <input id="name" class="input" wire:model="name">
                        @error('name') <span class="error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field field--full">
                        <label class="checkbox">
                            <input type="checkbox" wire:model="is_active">
                            <span>Aktywna</span>
                        </label>
                        @error('is_active') <span class="error">{{ $message }}</span> @enderror
                    </div>
                </div>

                <div style="margin-top:18px;display:flex;gap:10px">
                    <button type="submit" class="btn btn--primary" wire:loading.attr="disabled">
                        {{ $editingId ? 'Zapisz zmiany' : 'Dodaj kategorię' }}
                    </button>
                    @if ($editingId)
                        <button type="button" class="btn btn--ghost" wire:click="resetForm">Anuluj</button>
                    @endif
                </div>
            </div>
        </div>
    </form>

    <div class="card" style="margin-top:18px">
        <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Nazwa</th>
                    <th>Status</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            @forelse ($categories as $category)
                <tr>
                    <td><strong>{{ $category->name }}</strong></td>
                    <td>
                        @if ($category->is_active)
                            <span class="badge badge--green">Aktywna</span>
                        @else
                            <span class="badge badge--gray">Nieaktywna</span>
                        @endif
                    </td>
                    <td style="text-align:right">
                        <button type="button" class="btn btn--ghost btn--sm" wire:click="edit({{ $category->id }})">Edytuj</button>
                        @if ($category->is_active)
                            <button type="button" class="btn btn--ghost btn--sm"
                                    wire:click="deactivate({{ $category->id }})"
                                    wire:confirm="Dezaktywować tę kategorię? Powiązane zgłoszenia zostaną zachowane.">
                                Dezaktywuj
                            </button>
                        @else
                            <button type="button" class="btn btn--ghost btn--sm"
                                    wire:click="reactivate({{ $category->id }})">
                                Reaktywuj
                            </button>
                        @endif
                        @if ($canForceDelete)
                            <button type="button" class="btn btn--danger btn--sm"
                                    wire:click="forceDelete({{ $category->id }})"
                                    wire:confirm="Trwale usunie tę kategorię zgłoszeń. Dozwolone tylko, gdy żadne zgłoszenie jej nie używa. Operacja jest nieodwracalna. Kontynuować?">
                                Usuń trwale
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="table__empty">Brak kategorii zgłoszeń.</td></tr>
            @endforelse
            </tbody>
        </table>
        </div>
    </div>
</div>
