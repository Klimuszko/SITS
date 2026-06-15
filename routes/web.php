<?php

use App\Http\Controllers\AttachmentController;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Organizations\Index as OrganizationIndex;
use App\Livewire\Organizations\ManageForm as OrganizationForm;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(Auth::check() ? 'dashboard' : 'login');
});

/* --------------------------- Uwierzytelnianie --------------------------- */

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout')->middleware('auth');

/* ------------------------------ Aplikacja ------------------------------- */

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    // Bezpieczne pobieranie załączników (zawsze przez kontroler z autoryzacją).
    Route::get('/zalaczniki/{attachment}/pobierz', [AttachmentController::class, 'download'])
        ->name('attachments.download');

    // Organizacje – zarządzanie (admin) + podgląd (zakres wg policy/scoping).
    Route::get('/organizacje', OrganizationIndex::class)->name('organizations.index');

    Route::middleware('role:super_admin,admin')->group(function () {
        Route::get('/organizacje/nowa', OrganizationForm::class)->name('organizations.create');
        Route::get('/organizacje/{organization}/edycja', OrganizationForm::class)->name('organizations.edit');
    });
});
