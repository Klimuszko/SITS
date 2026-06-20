<?php

namespace App\Livewire\Settings;

use App\Support\SvgSanitizer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Panel admina: podmiana ikon menu na własne (Krok: ikony przez panel).
 *
 * Domyślne ikony to pliki resources/icons/menu/*.svg (w repo). Admin może wgrać własny
 * SVG dla danej nazwy — trafia (po SANITYZACJI) na dysk prywatny pod menu-icons/{name}.svg
 * i nadpisuje domyślny (komponent <x-icon> bierze najpierw override, potem default).
 *
 * BEZPIECZEŃSTWO: ikona jest inline'owana na KAŻDEJ stronie, więc SVG musi być czysty —
 * dlatego każdy upload przechodzi przez App\Support\SvgSanitizer (usuwa script, foreignObject,
 * on-handlery itd.). Dostęp: wyłącznie isAdminLevel (bramka access-admin), sprawdzana w każdej akcji.
 */
#[Layout('layouts.app')]
#[Title('Ikony menu')]
class MenuIcons extends Component
{
    use WithFileUploads;

    /** Uploady per nazwa ikony: uploads['ticket'] => TemporaryUploadedFile. */
    public array $uploads = [];

    public function mount(): void
    {
        $this->authorize('access-admin');
    }

    public function save(string $name): void
    {
        $this->authorize('access-admin');

        if (! $this->validName($name) || ! isset($this->uploads[$name])) {
            return;
        }

        $this->validate(
            ["uploads.$name" => ['required', 'file', 'max:256']],
            [],
            ["uploads.$name" => 'ikona'],
        );

        // Sanityzacja jest właściwą walidacją „czy to bezpieczny SVG" — rzuca, gdy nie SVG.
        try {
            $clean = SvgSanitizer::clean((string) $this->uploads[$name]->get());
        } catch (InvalidArgumentException) {
            $this->addError("uploads.$name", 'Plik nie jest prawidłowym SVG.');

            return;
        }

        Storage::disk('local')->put('menu-icons/'.$name.'.svg', $clean);

        unset($this->uploads[$name]);
        session()->flash('status', 'Zapisano ikonę „'.$name.'".');
    }

    /** Przywraca domyślną ikonę (usuwa override). NIE nazywać reset() — koliduje z Livewire. */
    public function resetIcon(string $name): void
    {
        $this->authorize('access-admin');

        if (! $this->validName($name)) {
            return;
        }

        Storage::disk('local')->delete('menu-icons/'.$name.'.svg');
        session()->flash('status', 'Przywrócono domyślną ikonę „'.$name.'".');
    }

    protected function validName(string $name): bool
    {
        return (bool) preg_match('/^[a-z0-9-]+$/', $name);
    }

    /**
     * Lista ikon = pliki domyślne z repo + informacja, czy jest override i gdzie używana.
     *
     * @return list<array{name:string,custom:bool,used:string}>
     */
    protected function icons(): array
    {
        $used = [
            'dashboard' => 'Pulpit',
            'ticket' => 'Zgłoszenia',
            'book' => 'Baza wiedzy',
            'server' => 'Zasoby (pozycja + kategoria)',
            'map-pin' => 'Lokalizacje',
            'building' => 'Organizacje + kategoria Klienci',
            'users' => 'Użytkownicy',
            'clipboard' => 'Prace administracyjne + kategoria Praca',
            'sliders' => 'Słowniki',
            'shield' => 'Audyt',
            'settings' => 'Ustawienia + kategoria Administracja',
            'life-ring' => 'Kategoria Wsparcie',
            'chevron-left' => 'Przycisk zwijania menu',
        ];

        return collect(File::files(resource_path('icons/menu')))
            ->map(fn ($f) => $f->getFilenameWithoutExtension())
            ->filter(fn ($name) => $this->validName($name))
            ->sort()
            ->map(fn (string $name) => [
                'name' => $name,
                'custom' => Storage::disk('local')->exists('menu-icons/'.$name.'.svg'),
                'used' => $used[$name] ?? '—',
            ])
            ->values()
            ->all();
    }

    public function render()
    {
        return view('livewire.settings.menu-icons', ['icons' => $this->icons()]);
    }
}
