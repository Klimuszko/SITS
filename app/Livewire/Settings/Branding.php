<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use App\Support\SvgSanitizer;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Branding administratora: tryb marki (nazwa / nazwa+logo / logo), motyw domyślny,
 * logo i favicon. Wszystko trzymane w tabeli settings (Setting::get/set).
 *
 * BEZPIECZEŃSTWO UPLOADU:
 *   - dostęp tylko dla isAdminLevel (bramka access-admin) — w mount() i ponownie w save(),
 *   - walidacja typu po rozszerzeniu/MIME Laravela (mimes:...),
 *   - SVG jest SANITYZOWANY (SvgSanitizer) zanim trafi na dysk — usuwa skrypty/zdarzenia,
 *   - nazwy plików są STAŁE (logo.<ext> / favicon.<ext>) — brak path traversal z nazwy klienta,
 *   - pliki lądują na dysku PRYWATNYM 'local', serwowane wyłącznie przez BrandingController.
 */
#[Layout('layouts.app')]
#[Title('Branding')]
class Branding extends Component
{
    use WithFileUploads;

    public string $brandingMode = 'name';
    public string $defaultTheme = 'dark';
    public string $appName = '';

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $logo = null;

    /** @var \Livewire\Features\SupportFileUploads\TemporaryUploadedFile|null */
    public $favicon = null;

    public function mount(): void
    {
        $this->authorize('access-admin');

        $this->brandingMode = (string) Setting::get('branding_mode', 'name');
        $this->defaultTheme = (string) Setting::get('default_theme', 'dark');
        $this->appName = (string) Setting::get('app_name', config('app.name', 'Smart Solutions'));
    }

    protected function rules(): array
    {
        return [
            'appName' => ['required', 'string', 'max:255'],
            'brandingMode' => ['required', 'in:name,name_logo,logo'],
            'defaultTheme' => ['required', 'in:dark,light'],
            'logo' => ['nullable', 'file', 'mimes:svg,png,jpg,jpeg', 'max:1024'],
            'favicon' => ['nullable', 'file', 'mimes:ico,png,svg', 'max:256'],
        ];
    }

    protected array $messages = [
        'logo.mimes' => 'Logo musi być plikiem SVG, PNG lub JPG.',
        'favicon.mimes' => 'Favicon musi być plikiem ICO, PNG lub SVG.',
    ];

    public function save(): void
    {
        $this->authorize('access-admin');

        $this->validate();

        if ($this->logo) {
            $path = $this->storeUpload($this->logo, 'logo');
            Setting::set('logo_path', $path);
            $this->bumpVersion();
            $this->logo = null;
        }

        if ($this->favicon) {
            $path = $this->storeUpload($this->favicon, 'favicon');
            Setting::set('favicon_path', $path);
            $this->bumpVersion();
            $this->favicon = null;
        }

        Setting::set('app_name', $this->appName);
        Setting::set('branding_mode', $this->brandingMode);
        Setting::set('default_theme', $this->defaultTheme);

        session()->flash('status', 'Zapisano ustawienia brandingu.');
    }

    public function removeLogo(): void
    {
        $this->authorize('access-admin');

        $this->deleteCurrent('logo_path');
        Setting::set('logo_path', null);
        $this->bumpVersion();

        session()->flash('status', 'Usunięto logo.');
    }

    public function removeFavicon(): void
    {
        $this->authorize('access-admin');

        $this->deleteCurrent('favicon_path');
        Setting::set('favicon_path', null);
        $this->bumpVersion();

        session()->flash('status', 'Usunięto favicon.');
    }

    /**
     * Zapisuje upload pod STAŁĄ nazwą branding/<base>.<ext> na dysku 'local'.
     * SVG przepuszczamy przez sanityzator (usuwa aktywną zawartość) przed zapisem.
     * Rozszerzenie bierzemy z walidowanego pliku — nazwa pliku klienta nie trafia na dysk.
     */
    protected function storeUpload($file, string $base): string
    {
        $ext = strtolower($file->getClientOriginalExtension());
        if ($ext === 'jpeg') {
            $ext = 'jpg';
        }

        $path = 'branding/'.$base.'.'.$ext;

        // Usuwamy ewentualny stary plik o INNYM rozszerzeniu (np. było png, teraz svg),
        // żeby nie zostawić osieroconego pliku, do którego nikt już nie wskazuje.
        $old = Setting::get($base.'_path');
        if ($old && $old !== $path) {
            Storage::disk('local')->delete($old);
        }

        if ($ext === 'svg') {
            $clean = SvgSanitizer::clean((string) $file->get());
            Storage::disk('local')->put($path, $clean);
        } else {
            $file->storeAs('branding', $base.'.'.$ext, 'local');
        }

        return $path;
    }

    /** Kasuje aktualny plik z dysku (jeśli istnieje) dla danego klucza ścieżki. */
    protected function deleteCurrent(string $pathKey): void
    {
        $current = Setting::get($pathKey);
        if ($current) {
            Storage::disk('local')->delete($current);
        }
    }

    /** Bumpuje wersję brandingu (cache-busting URL-i). */
    protected function bumpVersion(): void
    {
        Setting::set('branding_version', (string) now()->timestamp);
    }

    public function render()
    {
        $version = Setting::get('branding_version');

        return view('livewire.settings.branding', [
            'logoUrl' => Setting::get('logo_path')
                ? route('branding.logo').'?v='.$version
                : null,
            'faviconUrl' => Setting::get('favicon_path')
                ? route('branding.favicon').'?v='.$version
                : null,
        ]);
    }
}
