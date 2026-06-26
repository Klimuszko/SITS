<?php

namespace App\Livewire\Knowledge;

use App\Enums\PublicationStatus;
use App\Livewire\Concerns\WithSorting;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeCategory;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
#[Title('Baza wiedzy')]
class Index extends Component
{
    use WithPagination;
    use WithSorting;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url]
    public string $category = '';

    #[Url]
    public string $status = '';

    public function updating($name): void
    {
        if (in_array($name, ['search', 'category', 'status'], true)) {
            $this->resetPage();
        }
    }

    /**
     * Zakres widoczności artykułów — lustrzane odbicie KnowledgeVisibilityService::canView
     * po stronie zapytania (BEZ wycieku szkiców i artykułów spoza reguł widoczności).
     */
    protected function scopedQuery(): Builder
    {
        $user = auth()->user();
        $query = KnowledgeArticle::query()->with(['category', 'author']);

        // Admin/Super Admin — pełny zakres (jak w canView: isAdminLevel() => true).
        if ($user->isAdminLevel()) {
            return $query;
        }

        $orgIds = $user->accessibleOrganizationIds()->all();
        $groupIds = $user->groups->pluck('id')->all();
        $roleValue = $user->role->value;
        $userId = $user->id;

        return $query->where(function (Builder $outer) use ($user, $orgIds, $groupIds, $roleValue, $userId) {
            // Własne artykuły (także szkice) — autor widzi swoje.
            $outer->where('author_id', $user->id)
                // LUB: opublikowany ORAZ pasuje co najmniej jedna reguła widoczności.
                ->orWhere(function (Builder $pub) use ($orgIds, $groupIds, $roleValue, $userId) {
                    $pub->published()
                        ->whereHas('visibilities', function (Builder $v) use ($orgIds, $groupIds, $roleValue, $userId) {
                            // Każdy predykat opakowany, żeby precedencja OR nie odsłoniła
                            // szkicu ani artykułu spoza reguł (puste zbiory NIE odpinają filtra).
                            $v->where(function (Builder $r) use ($orgIds) {
                                $r->where('visibility_type', 'organization');
                                if (! empty($orgIds)) {
                                    $r->whereIn('organization_id', $orgIds);
                                } else {
                                    $r->whereRaw('1 = 0');
                                }
                            })->orWhere(function (Builder $r) use ($groupIds) {
                                $r->where('visibility_type', 'group');
                                if (! empty($groupIds)) {
                                    $r->whereIn('group_id', $groupIds);
                                } else {
                                    $r->whereRaw('1 = 0');
                                }
                            })->orWhere(function (Builder $r) use ($roleValue) {
                                $r->where('visibility_type', 'role')
                                    ->where('role', $roleValue);
                            })->orWhere(function (Builder $r) use ($userId) {
                                $r->where('visibility_type', 'user')
                                    ->where('user_id', $userId);
                            });
                        });
                });
        });
    }

    public function render()
    {
        $user = auth()->user();
        $query = $this->scopedQuery();

        if ($this->category !== '') {
            $query->where('knowledge_category_id', $this->category);
        }

        // Filtr statusu dostępny tylko dla personelu (klient i tak widzi wyłącznie opublikowane/własne).
        if ($this->status !== '' && $user->isStaff()) {
            $query->where('status', $this->status);
        }

        if ($this->search !== '') {
            // Wyszukiwanie niewrażliwe na wielkość liter. Postgres ma case-sensitive LIKE,
            // więc LOWER(title) LIKE LOWER(?) — działa też na sqlite w testach (ILIKE by go wywaliło).
            $term = '%'.mb_strtolower($this->search).'%';
            $query->whereRaw('LOWER(title) LIKE ?', [$term]);
        }

        $articles = $this->applySort($query)->paginate(15);

        return view('livewire.knowledge.index', [
            'articles' => $articles,
            'categories' => KnowledgeCategory::orderBy('name')->get(),
            'statuses' => PublicationStatus::options(),
            'canCreate' => $user->can('create', KnowledgeArticle::class),
            'isStaff' => $user->isStaff(),
            'sortCol' => $this->effectiveSortCol(),
            'sortDir' => $this->effectiveSortDir(),
        ]);
    }

    protected function sortableColumns(): array
    {
        return ['title', 'status', 'published_at'];
    }

    protected function defaultSort(): array
    {
        return ['published_at', 'desc'];
    }
}
