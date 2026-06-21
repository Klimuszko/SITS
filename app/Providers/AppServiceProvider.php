<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use SocialiteProviders\Manager\SocialiteWasCalled;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Za reverse proxy / w kontenerze wymuszamy schemat z APP_URL,
        // aby linki i assety były generowane poprawnie.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        // Własny, lekki widok paginacji (bez Tailwinda).
        Paginator::defaultView('pagination.default');
        Paginator::defaultSimpleView('pagination.default');

        // Rejestracja dostawcy Microsoft/Entra dla Socialite (Google jest wbudowany).
        Event::listen(SocialiteWasCalled::class, function (SocialiteWasCalled $event) {
            $event->extendSocialite('microsoft', \SocialiteProviders\Microsoft\Provider::class);
        });
    }
}
