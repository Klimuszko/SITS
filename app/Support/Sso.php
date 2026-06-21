<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Wspólna logika SSO: które providery są włączone+skonfigurowane (do pokazania
 * przycisków) oraz wstrzyknięcie konfiguracji Socialite W CZASIE DZIAŁANIA z
 * ustawień (client id/secret/redirect/tenant) — nie z .env, by dało się rotować
 * sekret z panelu. Sekret deszyfrowany na bieżąco (Setting::getEncrypted).
 */
final class Sso
{
    /** @var list<string> */
    public const PROVIDERS = ['microsoft', 'google'];

    public static function isValid(string $provider): bool
    {
        return in_array($provider, self::PROVIDERS, true);
    }

    /** Czy provider jest włączony i ma komplet danych (id + secret). */
    public static function isEnabled(string $provider): bool
    {
        if (! self::isValid($provider)) {
            return false;
        }

        return Setting::get("sso_{$provider}_enabled") === '1'
            && filled(Setting::get("sso_{$provider}_client_id"))
            && filled(Setting::getEncrypted("sso_{$provider}_client_secret"));
    }

    /**
     * Włączone providery do wyświetlenia na stronie logowania.
     *
     * @return list<array{key:string,label:string}>
     */
    public static function enabled(): array
    {
        $labels = ['microsoft' => 'Microsoft', 'google' => 'Google'];
        $out = [];

        foreach (self::PROVIDERS as $provider) {
            if (self::isEnabled($provider)) {
                $out[] = ['key' => $provider, 'label' => $labels[$provider]];
            }
        }

        return $out;
    }

    /** Wstrzykuje konfigurację providera do config('services.*') na czas żądania. */
    public static function configure(string $provider): void
    {
        $config = [
            'client_id' => Setting::get("sso_{$provider}_client_id"),
            'client_secret' => Setting::getEncrypted("sso_{$provider}_client_secret"),
            'redirect' => url("/auth/{$provider}/callback"),
        ];

        if ($provider === 'microsoft') {
            $config['tenant'] = Setting::get('sso_microsoft_tenant') ?: 'common';
        }

        config(["services.{$provider}" => $config]);
    }
}
