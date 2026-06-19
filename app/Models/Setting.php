<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Ustawienia aplikacji jako prosta tabela klucz-wartość (key/value).
 *
 * Odczyt idzie przez mapę cache'owaną "na zawsze" (Cache::rememberForever), więc
 * get() nie uderza w bazę przy każdym wywołaniu w layoutach. set() robi upsert
 * i unieważnia cache, więc kolejny get() przeładuje świeżą mapę.
 *
 * get() działa POPRAWNIE zanim w tabeli istnieje jakikolwiek wiersz — wtedy mapa
 * jest pusta i zwracany jest $default (kluczowe dla pierwszego uruchomienia i CI).
 *
 * Klucze brandingu: branding_mode (name|name_logo|logo), logo_path, favicon_path,
 * default_theme (dark|light), branding_version (timestamp do cache-bustingu URL-i).
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Klucz cache dla mapy klucz→wartość wszystkich ustawień. */
    public const CACHE_KEY = 'app.settings';

    /**
     * Zwraca wartość ustawienia z mapy cache'owanej "na zawsze".
     * Gdy klucza brak (lub tabela pusta) — zwraca $default.
     */
    public static function get(string $key, $default = null)
    {
        $map = Cache::rememberForever(
            self::CACHE_KEY,
            fn () => static::query()->pluck('value', 'key')->all()
        );

        return array_key_exists($key, $map) ? $map[$key] : $default;
    }

    /** Upsert wartości i unieważnienie cache mapy. */
    public static function set(string $key, ?string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);

        Cache::forget(self::CACHE_KEY);
    }
}
