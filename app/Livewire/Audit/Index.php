<?php

namespace App\Livewire\Audit;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Audyt')]
class Index extends Component
{
    use WithPagination;

    #[Url]
    public string $action = '';

    #[Url]
    public string $user = '';

    #[Url]
    public string $subjectType = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    /** Rozwinięty wiersz (szczegóły old/new values). Tylko stan UI — bez zapisu. */
    public ?int $expandedId = null;

    public function mount(): void
    {
        $this->authorize('view-audit');
    }

    public function updating($name): void
    {
        if (in_array($name, ['action', 'user', 'subjectType', 'dateFrom', 'dateTo'], true)) {
            $this->resetPage();
            $this->expandedId = null;
        }
    }

    /** Przełącz rozwinięcie szczegółów wiersza (read-only). */
    public function toggle(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    public function render()
    {
        $query = AuditLog::query()->with('user');

        if ($this->action !== '') {
            $query->where('action', $this->action);
        }

        if ($this->user !== '') {
            $query->where('user_id', $this->user);
        }

        if ($this->subjectType !== '') {
            $query->where('subject_type', $this->subjectType);
        }

        if ($this->dateFrom !== '') {
            $query->whereDate('created_at', '>=', $this->dateFrom);
        }

        if ($this->dateTo !== '') {
            $query->whereDate('created_at', '<=', $this->dateTo);
        }

        // simplePaginate NIE wykonuje SELECT count(*) — na dużej tabeli audytu pełny count
        // w Postgresie to skan, który potrafił zablokować wczytanie strony. Sortowanie po
        // created_at jest indeksowane, więc LIMIT 26 jest szybki niezależnie od rozmiaru.
        $logs = $query->latest('created_at')->latest('id')->simplePaginate(25);

        return view('livewire.audit.index', [
            'logs' => $logs,
            'actions' => $this->actionOptions(),
            'users' => User::orderBy('name')->get(['id', 'name']),
            'subjectTypes' => $this->subjectTypeOptions(),
        ]);
    }

    /** value⇒label dla wszystkich znanych akcji audytu. */
    protected function actionOptions(): array
    {
        $options = [];

        foreach (AuditAction::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /** Wyłącznie wartości subject_type faktycznie obecne w tabeli (distinct). */
    protected function subjectTypeOptions(): array
    {
        // DISTINCT po całej tabeli jest kosztowny przy każdym renderze — cache na 10 min.
        // Nowe typy obiektów pojawiają się rzadko, więc lekka nieaktualność filtra jest OK.
        return Cache::remember('audit.subject_types', now()->addMinutes(10), function () {
            return AuditLog::query()
                ->whereNotNull('subject_type')
                ->distinct()
                ->orderBy('subject_type')
                ->pluck('subject_type')
                ->all();
        });
    }
}
