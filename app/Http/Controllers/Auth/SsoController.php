<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Sso;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

/**
 * Logowanie przez SSO (Microsoft/Google). Model „invite-only": SSO NIE tworzy
 * kont — loguje wyłącznie na ISTNIEJĄCE, aktywne konto dopasowane po ZWERYFIKOWANYM
 * adresie e-mail dostawcy. Nieznany e-mail / niezweryfikowany / nieaktywny → odmowa.
 * Konfiguracja providerów czytana z ustawień w czasie żądania (Sso::configure).
 */
class SsoController extends Controller
{
    /** Przekierowanie do dostawcy. */
    public function redirect(string $provider): SymfonyRedirect
    {
        abort_unless(Sso::isEnabled($provider), 404);

        Sso::configure($provider);

        return Socialite::driver($provider)->redirect();
    }

    /** Powrót od dostawcy: dopasuj konto i zaloguj (albo odmów). */
    public function callback(string $provider): RedirectResponse
    {
        abort_unless(Sso::isEnabled($provider), 404);

        Sso::configure($provider);

        try {
            $oauthUser = Socialite::driver($provider)->user();
        } catch (\Throwable $e) {
            // Diagnostyka: prawdziwa przyczyna (np. AADSTS przy złym/wygasłym sekrecie)
            // trafia do logów — komunikat dla użytkownika pozostaje ogólny.
            Log::warning('SSO callback failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return $this->deny('Logowanie przez dostawcę nie powiodło się. Spróbuj ponownie.');
        }

        $email = mb_strtolower(trim((string) $oauthUser->getEmail()));

        if ($email === '' || ! $this->emailVerified($provider, $oauthUser)) {
            return $this->deny('Dostawca nie potwierdził adresu e-mail.');
        }

        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        if ($user === null) {
            return $this->deny('Nie ma konta dla tego adresu e-mail. Poproś administratora o utworzenie konta.');
        }

        if (! $user->is_active) {
            return $this->deny('Konto jest nieaktywne. Skontaktuj się z administratorem.');
        }

        // Powiązanie z dostawcą (przy pierwszym logowaniu / aktualizacja).
        $user->forceFill([
            'oauth_provider' => $provider,
            'oauth_id' => (string) $oauthUser->getId(),
            'invited_at' => null, // zaproszony zalogował się przez SSO = konto aktywne
        ])->save();

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Czy e-mail dostawcy jest zweryfikowany. Google podaje flagę email_verified;
     * Microsoft/Entra zwraca konto uwierzytelnione w dzierżawie — traktujemy jako
     * zweryfikowane (e-mail należy do zalogowanego konta firmowego).
     */
    protected function emailVerified(string $provider, $oauthUser): bool
    {
        if ($provider === 'google') {
            return (bool) ($oauthUser->user['email_verified'] ?? false);
        }

        return true;
    }

    protected function deny(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
