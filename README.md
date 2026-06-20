# SerwisIT

Self-hosted portal IT dla firmy obsługującej wielu klientów: **helpdesk + CMDB + baza wiedzy + raportowanie prac administracyjnych**. Coś w stylu GLPI, ale prostsze, nowocześniejsze i dopasowane do obsługi klientów małej firmy IT.

Stack: **Laravel 12 + Livewire 3 + PostgreSQL 16 + Redis 7 + Docker Compose**. Interfejs po polsku, architektura przygotowana pod tłumaczenie (i18n).

---

## Szybki start

Wymagania: **Docker** + **Docker Compose** (wtyczka `docker compose`).

```bash
# 1. Utwórz plik środowiskowy (.env jest w .gitignore – nie ma go w repo)
cp .env.example .env        # wymagane – uzupełnij wartości w razie potrzeby

# 2. Zbuduj i uruchom całość
docker compose up -d --build
```

Po chwili (build obrazu PHP + `composer install` + migracje + seed) portal jest dostępny pod:

```
http://localhost:7564
```

lub z innego komputera w sieci:

```
http://ADRES_SERWERA:7564
```

> Pierwsze uruchomienie trwa dłużej – budowany jest obraz PHP, a kontener `app` przy starcie pobiera zależności Composer do katalogu `vendor/` (wymaga dostępu do internetu).
> Kontener `app` w `entrypoint.sh` automatycznie: instaluje zależności Composer, generuje `APP_KEY`, czyści cache, wykonuje migracje, seeduje dane i tworzy `storage:link`. Kontenery `queue` i `scheduler` czekają, aż `vendor/` będzie gotowy.

### Logowanie (dane developerskie)

| Rola        | E-mail                  | Hasło          |
|-------------|-------------------------|----------------|
| Super Admin | `admin@serwisit.local`  | `Admin12345!`  |
| Support     | `support@serwisit.local`| `Haslo12345!`  |
| Manager     | `manager@pako.local`    | `Haslo12345!`  |
| Użytkownik  | `user@pako.local`       | `Haslo12345!`  |

Dane Super Admina pochodzą z `.env` (`SUPERADMIN_EMAIL`, `SUPERADMIN_PASSWORD`). **Zmień je przed wdrożeniem produkcyjnym.**

---

## Architektura kontenerów

| Kontener    | Rola                                   | Port hosta |
|-------------|----------------------------------------|------------|
| `nginx`     | serwer WWW (jedyny wystawiony na host) | **7564** → 80 |
| `app`       | PHP-FPM 8.3 (kod Laravel)              | —          |
| `postgres`  | baza danych PostgreSQL 16              | — (tylko sieć wewnętrzna) |
| `redis`     | cache / kolejki / sesje                | —          |
| `queue`     | `php artisan queue:work` (uśpiony)     | —          |
| `scheduler` | `php artisan schedule:work` (uśpiony)  | —          |

> **Kontenery `queue` i `scheduler` są obecnie uśpione z założenia (dormant).** Aplikacja nie definiuje jeszcze żadnych zadań w kolejce ani harmonogramie, więc workery działają „na sucho”. Kontenery zostają w stacku, aby aktywować je bez zmian infrastruktury, gdy dojdą funkcje, które ich wymagają: zakładanie ticketów z e-maila (email→ticket), miesięczne raporty prac administracyjnych oraz automatyczne zamykanie rozwiązanych ticketów.

**Dlaczego nginx + php-fpm, a nie `artisan serve`?** To stabilny, produkcyjny model: oddzielny worker kolejek i scheduler współdzielą ten sam obraz, a nginx poprawnie obsługuje pliki statyczne i nagłówki. Zgodnie z MVP **bez Traefik, reverse proxy, domeny i HTTPS** – mapujemy bezpośrednio `7564:80`.

Wolumeny: `postgres_data`, `redis_data`, `app_storage` (załączniki i dane aplikacji w `storage/app`).

---

## Zmienne środowiskowe (`.env`)

Najważniejsze: `APP_URL=http://localhost:7564`, `DB_*`, `REDIS_HOST`, `MAIL_*`, `FILESYSTEM_DISK=local`, `QUEUE_CONNECTION=redis`, `SESSION_DRIVER=redis`, `CACHE_STORE=redis`. Sekrety **nie są** hardkodowane w kodzie – wszystko z `.env`.

---

## Role i bezpieczeństwo

5 sztywnych ról (bez kreatora ról w MVP):

- **Super Admin** – widzi i może wszystko (`Gate::before`).
- **Admin** – prawie wszystko poza krytycznymi ustawieniami i zarządzaniem Super Adminami.
- **Support** – tylko organizacje, do których jest przypisany (`support_assignments`). Nie jest członkiem organizacji klienta.
- **Manager** – członek organizacji klienta; widzi swoją jednostkę (tickety, zasoby, prace, raport miesięczny). Nie widzi notatek wewnętrznych.
- **User** – tworzy i widzi swoje tickety, odpowiada jako obserwator, widzi zasoby organizacji (poza prywatnymi nieprzypisanymi do niego), prosi o zamknięcie ticketu (obowiązkowy powód, ale nie zamyka sam).

Model ról jest **dwupoziomowy**:
- `users.role` (`App\Enums\Role`) – globalna klasyfikacja konta.
- `organization_memberships.role` (`App\Enums\OrgRole`) – autorytatywna rola klienta *per organizacja* (ta sama osoba może być managerem w jednej firmie, a userem w innej).

Bezpieczeństwo (§30): separacja danych per organizacja w Policies (`app/Policies`), pobieranie załączników **wyłącznie** przez kontroler z autoryzacją (`AttachmentController` + `AttachmentPolicy`), sanityzacja HTML bazy wiedzy (własny `App\Services\HtmlSanitizer` — bogaty HTML, usuwane skrypty/handlery; SVG przez `App\Support\SvgSanitizer`), CSRF, rate limiting logowania, walidacja formularzy.

---

## Mapa modułów i status

| Obszar | Schemat (migracje) | Modele | Policies | UI |
|---|---|---|---|---|
| Organizacje, drzewo, support | ✅ | ✅ | ✅ | ✅ lista + formularz (Livewire) |
| Lokalizacje (hierarchia) | ✅ | ✅ | — | 🔜 |
| Użytkownicy, członkostwa, grupy | ✅ | ✅ | ✅ (gate) | 🔜 |
| Zasoby + kategorie + dynamiczne pola + sekcje + relacje + historia | ✅ | ✅ | ✅ | 🔜 |
| Tickety + komentarze + obserwatorzy | ✅ | ✅ | ✅ | 🔜 (logika w `TicketService`) |
| Załączniki (polimorficzne, chronione) | ✅ | ✅ | ✅ | controller pobierania ✅ |
| Baza wiedzy + wielowyborowa widoczność | ✅ | ✅ | ✅ (`KnowledgeVisibilityService`) | 🔜 |
| Prace administracyjne + rejestr czasu | ✅ | ✅ | ✅ | 🔜 |
| Dashboardy (user/manager/support/admin) | — | — | — | ✅ |
| Audyt (`AuditLogger`) | ✅ | ✅ | gate | 🔜 widok |

✅ = gotowe w tej wersji, 🔜 = fundament (schemat + reguły dostępu) gotowy, UI w kolejnych iteracjach.

---

## Decyzje projektowe

- **Statusy ticketów = PHP enum** (`App\Enums\TicketStatus`) – mają sztywną logikę biznesową (zamknięcie tylko ręczne, „oczekuje na użytkownika” itd.).
- **Priorytety i kategorie ticketów/zasobów = tabele** (admin-zarządzalne; priorytety przygotowane pod przyszłe SLA).
- **Bez Node/Vite** – styl w `public/css/app.css` (lekki, biznesowy), aby obraz był chudy i samowystarczalny. Interaktywność dostarcza Livewire.
- **Soft deletes / status archiwalny / nieaktywny** zamiast twardego usuwania (§29).
- **i18n-ready** – etykiety enumów i komunikaty w `lang/pl` (oraz szkielet `lang/en`).

---

## Przydatne komendy

```bash
docker compose ps                              # status kontenerów
docker compose logs -f app                     # logi aplikacji
docker compose exec app php artisan migrate:fresh --seed   # reset bazy + dane demo
docker compose exec app php artisan tinker     # konsola
docker compose exec app php artisan test       # testy (sqlite :memory:)
docker compose down                            # zatrzymanie (wolumeny zostają)
docker compose down -v                         # zatrzymanie + usunięcie danych
```

> **Bramka testów w CI:** workflow `.github/workflows/publish.yml` ma job `test` (PHP 8.3 + sqlite `:memory:`, `php artisan test`), od którego zależy publikacja obrazów (`needs: test`). Czerwone testy blokują publikację do GHCR.

---

## Struktura kodu

```
app/
  Enums/        # Role, TicketStatus, AssetFieldType, ... (etykiety przez i18n)
  Models/       # 24 modele domenowe z relacjami i castami
  Policies/     # separacja danych per organizacja
  Services/     # TicketService, AuditLogger, KnowledgeVisibilityService
  Http/         # Middleware (role), AttachmentController
  Livewire/     # Auth\Login, Dashboard, Organizations\{Index, ManageForm}
  Providers/    # AppServiceProvider, AuthServiceProvider (policies + gates)
config/         # app, database, cache, queue, session, filesystems, auth...
database/
  migrations/   # pełny schemat (§32)
  seeders/      # Super Admin (z .env), słowniki, dane demo PAKO Engineering (PL)
resources/views/{layouts, livewire, components, pagination}
resources/icons/menu/  # ikony SVG menu bocznego (podmienialne — patrz niżej)
lang/{pl, en}/  # tłumaczenia (start: pl)
docker/{php, nginx}/
```

### Ikony menu (podmiana na własne)

Ikony menu zmienia się **z panelu admina: Ustawienia → Ikony menu** (upload SVG per ikona;
plik jest sanityzowany i zapisywany jako trwały override na dysku prywatnym
`storage/app/private/menu-icons/{name}.svg`, więc przeżywa redeploy; „Domyślna" usuwa override).
Domyślne ikony to pliki SVG w **`resources/icons/menu/`** (jeden plik na ikonę), inline'owane
przez `<x-icon name="...">` (dziedziczą `currentColor` → motyw jasny/ciemny). `<x-icon>` bierze
najpierw override z panelu, potem domyślny plik z repo. Brak obu = pozycja menu bez ikony (zero błędów).

Nazwa pliku = wartość `'icon'` przypisana pozycji/kategorii w `app/Support/Navigation.php`.
Mapowanie:

| Sekcja / pozycja menu | Plik SVG |
|---|---|
| Pulpit | `dashboard.svg` |
| Zgłoszenia · Baza wiedzy | `ticket.svg` · `book.svg` |
| Zasoby · Lokalizacje | `server.svg` · `map-pin.svg` |
| Organizacje · Użytkownicy | `building.svg` · `users.svg` |
| Prace administracyjne | `clipboard.svg` |
| Słowniki · Audyt · Ustawienia | `sliders.svg` · `shield.svg` · `settings.svg` |
| Nagłówki kategorii (Wsparcie/Zasoby/Klienci/Praca/Administracja) | `life-ring/server/building/clipboard/settings.svg` |
| Przycisk zwijania menu | `chevron-left.svg` |

Zalecenie: SVG 24×24, `fill="none" stroke="currentColor"` (styl outline), bez wpisanych
kolorów — wtedy ikona dopasuje się do motywu. Uploady z panelu są **sanityzowane**
(`App\Support\SvgSanitizer` — usuwa script/foreignObject/on*/itd.), bo ikona jest inline'owana
na każdej stronie. Domyślne pliki w repo są zaufane (commit przez dewelopera).

---

## Wdrożenie na zdalny serwer (z obrazu w GHCR)

Idea: obrazy buduje **GitHub Actions** i publikuje do **GitHub Container Registry** (`ghcr.io`). Na serwerze nie ma kodu źródłowego — pobiera on gotowe obrazy. Tryb deweloperski (`docker-compose.yml`) buduje obraz lokalnie i montuje kod; tryb produkcyjny (`docker-compose.prod.yml`) **pobiera** gotowe obrazy z rejestru.

Publikowane są dwa obrazy:
- `ghcr.io/<owner>/<repo>-app` — PHP-FPM z zaszytym kodem i `vendor/`,
- `ghcr.io/<owner>/<repo>-nginx` — nginx z katalogiem `public/`.

### Krok 1 — wypchnij repo na GitHub (jednorazowo)

```bash
git remote add origin https://github.com/<owner>/<repo>.git
git push -u origin main
```

Po push workflow [.github/workflows/publish.yml](.github/workflows/publish.yml) automatycznie zbuduje i wypchnie obrazy (zakładka **Actions**). Tagi obrazów: `latest` (gałąź main), `sha-xxxxxxx`, oraz `v1.2.3` / `1.2` przy tagach wersji.

### Krok 2 — ustaw obrazy jako publiczne (jednorazowo)

W GitHub: **profil/organizacja → Packages → `<repo>-app`** → *Package settings* → *Change visibility* → **Public**. To samo dla `<repo>-nginx`. Dzięki temu serwer pobiera obrazy bez logowania.

> Obraz prywatny? Pomiń krok 2 i zaloguj się na serwerze:
> `echo <TOKEN_read:packages> | docker login ghcr.io -u <owner> --password-stdin`

### Krok 3 — instalacja na serwerze

Na serwerze (z Dockerem) wystarczą **dwa pliki**: `docker-compose.prod.yml` i `.env`.

```bash
# 1. Skopiuj docker-compose.prod.yml na serwer, obok niego utwórz .env:
cp .env.prod.example .env      # i uzupełnij wartości (APP_IMAGE, NGINX_IMAGE, hasła)

# 2. Wygeneruj klucz aplikacji i wklej go do .env jako APP_KEY=
docker compose -f docker-compose.prod.yml run --rm app php artisan key:generate --show

# 3. Pobierz obrazy i uruchom (migracje wykona jednorazowy serwis "migrate")
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d

# 4. Utwórz konto Super Admina (dane z .env: SUPERADMIN_*)
docker compose -f docker-compose.prod.yml run --rm app php artisan db:seed --class=SuperAdminSeeder --force
```

Portal: `http://ADRES_SERWERA:7564`. Aktualizacja wersji: `docker compose -f docker-compose.prod.yml pull && docker compose -f docker-compose.prod.yml up -d` (serwis `migrate` zastosuje nowe migracje).

> W produkcji **nie** uruchamiamy pełnego `db:seed` (zawiera dane demo). Seedujemy tylko `SuperAdminSeeder`. `APP_DEBUG=false`, `APP_KEY` ustawione, silne hasła w `.env`.

---

## Roadmapa (kolejne iteracje)

UI modułów: Tickety (z `TicketService`), Zasoby z dynamicznymi polami i relacjami, Baza wiedzy (edytor WYSIWYG + sanityzacja), Prace administracyjne + raport miesięczny, panel Audytu, zarządzanie użytkownikami/lokalizacjami/grupami, pełne testy feature reguł dostępu.

Zaprojektowane pod późniejsze dodanie (nie w MVP): e-mail→ticket, Microsoft 365 / Entra ID, Teams webhook, LDAP/AD, SSO, eksport PDF/CSV, SLA, reverse proxy + HTTPS.

---

## Licencja

Oprogramowanie **zastrzeżone (proprietary), wszelkie prawa zastrzeżone** — patrz [LICENSE](LICENSE).
Kod jest upubliczniony wyłącznie do wglądu. **Bez pisemnej zgody właściciela zabronione jest** używanie, kopiowanie, modyfikowanie, rozpowszechnianie, sprzedaż oraz jakiekolwiek czerpanie korzyści. To restrykcja prawna, nie techniczna — publiczny kod można technicznie podejrzeć, ale jego użycie bez zgody narusza prawo autorskie.
