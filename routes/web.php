<?php

use App\Http\Controllers\AttachmentController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\HomeController;
use App\Livewire\AssetCategories\Builder as AssetCategoryBuilder;
use App\Livewire\AssetCategories\Index as AssetCategoryIndex;
use App\Livewire\Assets\Index as AssetIndex;
use App\Livewire\Assets\ManageForm as AssetForm;
use App\Livewire\Assets\Show as AssetShow;
use App\Livewire\Audit\Index as AuditIndex;
use App\Livewire\Auth\Login;
use App\Livewire\Dashboard;
use App\Livewire\Dictionaries\KnowledgeCategories as DictionaryKnowledgeCategories;
use App\Livewire\Dictionaries\TicketCategories as DictionaryTicketCategories;
use App\Livewire\Dictionaries\TicketPriorities as DictionaryTicketPriorities;
use App\Livewire\Knowledge\Index as KnowledgeIndex;
use App\Livewire\Knowledge\ManageForm as KnowledgeForm;
use App\Livewire\Knowledge\Show as KnowledgeShow;
use App\Livewire\Locations\Index as LocationIndex;
use App\Livewire\Locations\ManageForm as LocationForm;
use App\Livewire\Organizations\Index as OrganizationIndex;
use App\Livewire\Organizations\ManageForm as OrganizationForm;
use App\Livewire\Profile\Edit as ProfileEdit;
use App\Livewire\Tickets\Create as TicketCreate;
use App\Livewire\Tickets\Index as TicketIndex;
use App\Livewire\Tickets\Show as TicketShow;
use App\Livewire\Users\Index as UserIndex;
use App\Livewire\Users\ManageForm as UserForm;
use App\Livewire\WorkLogs\Index as WorkLogIndex;
use App\Livewire\WorkLogs\ManageForm as WorkLogForm;
use App\Livewire\WorkLogs\Report as WorkLogReport;
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

    // Profil użytkownika (samoobsługa). Edytuje wyłącznie auth()->user() — komponent
    // nie przyjmuje użytkownika z trasy, więc nie da się edytować cudzego konta.
    Route::get('/profil', ProfileEdit::class)->name('profile.edit');

    // Bezpieczne pobieranie załączników (zawsze przez kontroler z autoryzacją).
    Route::get('/zalaczniki/{attachment}/pobierz', [AttachmentController::class, 'download'])
        ->name('attachments.download');

    // Zgłoszenia (tickety).
    Route::get('/zgloszenia', TicketIndex::class)->name('tickets.index');
    Route::get('/zgloszenia/nowe', TicketCreate::class)->name('tickets.create');
    Route::get('/zgloszenia/{ticket}', TicketShow::class)->name('tickets.show');

    // Zasoby (CMDB). Autoryzacja w mount() komponentów (policy AssetPolicy);
    // celowo BEZ middleware role: — support musi mieć dostęp.
    Route::get('/zasoby', AssetIndex::class)->name('assets.index');
    Route::get('/zasoby/nowy', AssetForm::class)->name('assets.create');
    Route::get('/zasoby/{asset}', AssetShow::class)->name('assets.show');
    Route::get('/zasoby/{asset}/edycja', AssetForm::class)->name('assets.edit');

    // Lokalizacje. Autoryzacja w mount() komponentów (policy LocationPolicy);
    // celowo BEZ middleware role: — support musi mieć dostęp.
    Route::get('/lokalizacje', LocationIndex::class)->name('locations.index');
    Route::get('/lokalizacje/nowa', LocationForm::class)->name('locations.create');
    Route::get('/lokalizacje/{location}/edycja', LocationForm::class)->name('locations.edit');

    // Organizacje – zarządzanie (admin) + podgląd (zakres wg policy/scoping).
    Route::get('/organizacje', OrganizationIndex::class)->name('organizations.index');

    Route::middleware('role:super_admin,admin')->group(function () {
        Route::get('/organizacje/nowa', OrganizationForm::class)->name('organizations.create');
        Route::get('/organizacje/{organization}/edycja', OrganizationForm::class)->name('organizations.edit');
    });

    // Prace administracyjne. Autoryzacja w mount() komponentów (AdministrativeWorkLogPolicy);
    // celowo BEZ middleware role: — widoczność zależy od roli + flag, nie od samej roli globalnej.
    // Trasy statyczne (/nowa, /raport) PRZED /{administrativeWorkLog}, żeby nie zostały przechwycone.
    Route::get('/prace', WorkLogIndex::class)->name('work-logs.index');
    Route::get('/prace/nowa', WorkLogForm::class)->name('work-logs.create');
    Route::get('/prace/raport', WorkLogReport::class)->name('work-logs.report');
    Route::get('/prace/{administrativeWorkLog}/edycja', WorkLogForm::class)->name('work-logs.edit');

    // Użytkownicy i członkostwa (admin). Autoryzacja w mount() przez UserPolicy
    // (ochrona Super Admina i konta własnego po stronie komponentu/policy).
    // /nowy przed /{user}, żeby nie został przechwycony jako parametr.
    Route::get('/uzytkownicy', UserIndex::class)->name('users.index');
    Route::get('/uzytkownicy/nowy', UserForm::class)->name('users.create');
    Route::get('/uzytkownicy/{user}/edycja', UserForm::class)->name('users.edit');

    // Słowniki administracyjne (kategorie/priorytety). Autoryzacja w mount()
    // każdego komponentu przez bramkę manage-categories (admin/super_admin).
    // Route::redirect (a nie domknięcie) — kompatybilne z route:cache w produkcji.
    Route::redirect('/slowniki', '/slowniki/kategorie-zgloszen')->name('dictionaries.index');
    Route::get('/slowniki/kategorie-zgloszen', DictionaryTicketCategories::class)->name('dictionaries.ticket-categories');
    Route::get('/slowniki/priorytety', DictionaryTicketPriorities::class)->name('dictionaries.ticket-priorities');
    Route::get('/slowniki/kategorie-wiedzy', DictionaryKnowledgeCategories::class)->name('dictionaries.knowledge-categories');

    // Kategorie zasobów (builder typów zasobów + dynamiczne pola/sekcje). Autoryzacja
    // w mount() przez bramkę manage-categories. Segment statyczny PRZED parametrem
    // {assetCategory}, żeby nie został przechwycony. Powiązanie modelu po konwencji nazwy.
    Route::get('/slowniki/kategorie-zasobow', AssetCategoryIndex::class)->name('dictionaries.asset-categories');
    Route::get('/slowniki/kategorie-zasobow/{assetCategory}/pola', AssetCategoryBuilder::class)
        ->name('dictionaries.asset-category-fields');

    // Baza wiedzy. Autoryzacja w mount() komponentów (KnowledgeArticlePolicy);
    // celowo BEZ middleware role: — widoczność zależy od reguł, nie od samej roli globalnej.
    // Trasa statyczna (/nowy) PRZED /{article}, żeby nie została przechwycona jako parametr.
    Route::get('/baza-wiedzy', KnowledgeIndex::class)->name('knowledge.index');
    Route::get('/baza-wiedzy/nowy', KnowledgeForm::class)->name('knowledge.create');
    Route::get('/baza-wiedzy/{article}', KnowledgeShow::class)->name('knowledge.show');
    Route::get('/baza-wiedzy/{article}/edycja', KnowledgeForm::class)->name('knowledge.edit');

    // Audyt (tylko do odczytu, admin). Autoryzacja w mount() przez bramkę view-audit.
    Route::get('/audyt', AuditIndex::class)->name('audit.index');
});
