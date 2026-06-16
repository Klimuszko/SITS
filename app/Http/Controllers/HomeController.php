<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Strona startowa "/" – przekierowanie zależne od stanu logowania.
 *
 * Kontroler (zamiast domknięcia w web.php), aby route:cache mógł działać w produkcji.
 */
class HomeController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        return redirect()->route(Auth::check() ? 'dashboard' : 'login');
    }
}
