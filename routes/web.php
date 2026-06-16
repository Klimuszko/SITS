<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\HomeController;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Organizations\Index as OrganizationIndex;
use App\Livewire\Organizations\ManageForm as OrganizationForm;
use App\Livewire\Tickets\Create as TicketCreate;
use App\Livewire\Tickets\Index as TicketIndex;
use App\Livewire\Tickets\Show as TicketShow;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class);

/* --------------------------- Uwierzytelnianie --------------------------- */

Route::middleware('guest')->group(function () {
    Route::get('/login', Login::class)->name('login');
});

Route::post('/logout', LogoutController::class)->name('logout')->middleware('auth');

/* ------------------------------ Aplikacja ------------------------------- */

Route::middleware('auth')->group(function () {
    Route::get('/dashboard', Dashboard::class)->name('dashboard');

    // Bezpieczne pobieranie załączników (zawsze przez kontroler z autoryzacją).
    Route::get('/zalaczniki/{attachment}/pobierz', [AttachmentController::class, 'download'])
        ->name('attachments.download');

    // Zgłoszenia (tickety).
    Route::get('/zgloszenia', TicketIndex::class)->name('tickets.index');
    Route::get('/zgloszenia/nowe', TicketCreate::class)->name('tickets.create');
    Route::get('/zgloszenia/{ticket}', TicketShow::class)->name('tickets.show');

    // Organizacje – zarządzanie (admin) + podgląd (zakres wg policy/scoping).
    Route::get('/organizacje', OrganizationIndex::class)->name('organizations.index');

    Route::middleware('role:super_admin,admin')->group(function () {
        Route::get('/organizacje/nowa', OrganizationForm::class)->name('organizations.create');
        Route::get('/organizacje/{organization}/edycja', OrganizationForm::class)->name('organizations.edit');
    });
});
