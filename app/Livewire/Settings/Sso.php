<?php

namespace App\Livewire\Settings;

use App\Models\Setting;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Ustawienia logowania SSO (Microsoft/Google) — zarządzane z panelu, nie z .env,
 * by client secret dało się rotować bez redeployu (Azure rotuje go cyklicznie).
 *
 * BEZPIECZEŃSTWO: client secret szyfrowany w spoczynku (Setting::setEncrypted,
 * APP_KEY) i NIGDY nie jest ładowany z powrotem do formularza — pokazujemy tylko,
 * czy jest ustawiony. Puste pole sekretu przy zapisie = bez zmian.
 *
 * Tu jest tylko konfiguracja; sam przepływ logowania (Socialite) konsumuje te
 * wartości w osobnym kroku (faza 3b).
 */
#[Layout('layouts.app')]
#[Title('Logowanie (SSO)')]
class Sso extends Component
{
    public bool $microsoftEnabled = false;
    public string $microsoftClientId = '';
    public string $microsoftTenant = '';
    public string $microsoftSecret = '';   // wpis; puste = bez zmian

    public bool $googleEnabled = false;
    public string $googleClientId = '';
    public string $googleSecret = '';

    public function mount(): void
    {
        $this->authorize('access-admin');

        $this->microsoftEnabled = Setting::get('sso_microsoft_enabled') === '1';
        $this->microsoftClientId = (string) Setting::get('sso_microsoft_client_id', '');
        $this->microsoftTenant = (string) Setting::get('sso_microsoft_tenant', '');

        $this->googleEnabled = Setting::get('sso_google_enabled') === '1';
        $this->googleClientId = (string) Setting::get('sso_google_client_id', '');
        // Sekretów NIE wczytujemy do formularza.
    }

    public function save(): void
    {
        $this->authorize('access-admin');

        $this->validate([
            'microsoftClientId' => ['nullable', 'string', 'max:255'],
            'microsoftTenant' => ['nullable', 'string', 'max:255'],
            'microsoftSecret' => ['nullable', 'string', 'max:1000'],
            'googleClientId' => ['nullable', 'string', 'max:255'],
            'googleSecret' => ['nullable', 'string', 'max:1000'],
        ]);

        Setting::set('sso_microsoft_enabled', $this->microsoftEnabled ? '1' : '0');
        Setting::set('sso_microsoft_client_id', $this->microsoftClientId ?: null);
        Setting::set('sso_microsoft_tenant', $this->microsoftTenant ?: null);
        if (filled($this->microsoftSecret)) {
            Setting::setEncrypted('sso_microsoft_client_secret', $this->microsoftSecret);
        }

        Setting::set('sso_google_enabled', $this->googleEnabled ? '1' : '0');
        Setting::set('sso_google_client_id', $this->googleClientId ?: null);
        if (filled($this->googleSecret)) {
            Setting::setEncrypted('sso_google_client_secret', $this->googleSecret);
        }

        $this->microsoftSecret = '';
        $this->googleSecret = '';

        session()->flash('status', 'Zapisano ustawienia logowania SSO.');
    }

    /** Usuwa zapisany client secret Microsoft (np. przed wpisaniem nowego). */
    public function clearMicrosoftSecret(): void
    {
        $this->authorize('access-admin');
        Setting::set('sso_microsoft_client_secret', null);
        session()->flash('status', 'Usunięto client secret Microsoft.');
    }

    public function clearGoogleSecret(): void
    {
        $this->authorize('access-admin');
        Setting::set('sso_google_client_secret', null);
        session()->flash('status', 'Usunięto client secret Google.');
    }

    public function render()
    {
        return view('livewire.settings.sso', [
            'microsoftSecretSet' => Setting::getEncrypted('sso_microsoft_client_secret') !== null,
            'googleSecretSet' => Setting::getEncrypted('sso_google_client_secret') !== null,
            'microsoftRedirect' => url('/auth/microsoft/callback'),
            'googleRedirect' => url('/auth/google/callback'),
        ]);
    }
}
