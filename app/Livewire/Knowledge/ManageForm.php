<?php

namespace App\Livewire\Knowledge;

use App\Enums\AuditAction;
use App\Enums\PublicationStatus;
use App\Enums\Role;
use App\Models\KnowledgeArticle;
use App\Models\KnowledgeCategory;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class ManageForm extends Component
{
    public ?KnowledgeArticle $article = null;

    // Pola formularza artykułu.
    public string $title = '';
    public string $slug = '';
    public ?int $knowledge_category_id = null;
    public string $body = '';
    public string $status = 'draft';

    // Pola wiersza "dodaj regułę widoczności".
    public string $newVisibilityType = 'organization';
    public ?int $newOrganizationId = null;
    public ?string $newRole = null;
    public ?int $newUserId = null;

    public function mount(?KnowledgeArticle $article = null): void
    {
        $this->authorize($article && $article->exists ? 'update' : 'create', $article ?? KnowledgeArticle::class);

        if ($article && $article->exists) {
            $this->article = $article;
            $this->title = $article->title;
            $this->slug = $article->slug;
            $this->knowledge_category_id = $article->knowledge_category_id;
            $this->body = $article->body;
            $this->status = $article->status->value;
        }
    }

    /** @return array<string,mixed> */
    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('knowledge_articles', 'slug')->ignore($this->article?->id),
            ],
            'knowledge_category_id' => ['nullable', 'integer', 'exists:knowledge_categories,id'],
            'body' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(PublicationStatus::class)],
        ];
    }

    protected function messages(): array
    {
        return [
            'slug.unique' => 'Ten odnośnik (slug) jest już zajęty.',
        ];
    }

    public function save(): void
    {
        // Ponowna autoryzacja po stronie serwera (nie tylko w mount()).
        $this->authorize($this->article && $this->article->exists ? 'update' : 'create', $this->article ?? KnowledgeArticle::class);

        $data = $this->validate();

        DB::transaction(function () use ($data) {
            $isNew = ! ($this->article && $this->article->exists);
            $target = $this->article ?? new KnowledgeArticle();
            $old = $isNew ? null : $target->getOriginal();

            $target->title = $data['title'];

            // Slug: auto z tytułu, gdy puste; unikalny (ignorując samego siebie przy edycji).
            $slug = filled($data['slug']) ? Str::slug($data['slug']) : Str::slug($data['title']);
            $target->slug = $this->uniqueSlug($slug, $target->id);

            $target->knowledge_category_id = $data['knowledge_category_id'];

            // BEZPIECZEŃSTWO: treść ZAWSZE sanityzowana przed zapisem (XSS). Nigdy surowy HTML.
            $target->body = app(HtmlSanitizer::class)->clean($this->body);

            $target->status = $data['status'];

            if ($isNew) {
                $target->author_id = auth()->id();
            }

            // published_at ustawiamy przy przejściu na "opublikowany" — tylko gdy jeszcze nie ustawiony.
            if ($target->status === PublicationStatus::Published && $target->published_at === null) {
                $target->published_at = now();
            }

            $target->save();

            AuditLogger::log(
                $isNew ? AuditAction::ArticleCreated : AuditAction::ArticleUpdated,
                $target,
                $old,
                $target->getChanges(),
            );

            $this->article = $target;
        });

        session()->flash('status', 'Zapisano artykuł.');
        $this->redirectRoute('knowledge.show', ['article' => $this->article->id], navigate: true);
    }

    /**
     * Zapewnia unikalność sluga (kolizje rozwiązujemy sufiksem -2, -3, …),
     * ignorując bieżący artykuł przy edycji.
     */
    protected function uniqueSlug(string $base, ?int $ignoreId): string
    {
        $base = $base !== '' ? $base : 'artykul';
        $slug = $base;
        $i = 2;

        while (KnowledgeArticle::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /* ----------------------------- Widoczność ----------------------------- */

    public function addVisibility(): void
    {
        // Fail-closed: bez utrwalonego artykułu nie autoryzujemy (unika TypeError przy podrobionym wywołaniu).
        if (! $this->article) {
            return;
        }

        $this->authorize('changeVisibility', $this->article);

        $validated = $this->validate([
            // group celowo pominięty (DEFER) — tu tylko organizacja, rola, użytkownik.
            'newVisibilityType' => ['required', Rule::in(['organization', 'role', 'user'])],
            'newOrganizationId' => [
                Rule::requiredIf(fn () => $this->newVisibilityType === 'organization'),
                'nullable', 'integer', Rule::exists('organizations', 'id'),
            ],
            'newRole' => [
                Rule::requiredIf(fn () => $this->newVisibilityType === 'role'),
                'nullable', Rule::enum(Role::class),
            ],
            'newUserId' => [
                Rule::requiredIf(fn () => $this->newVisibilityType === 'user'),
                'nullable', 'integer', Rule::exists('users', 'id'),
            ],
        ], [
            'newOrganizationId.required' => 'Wybierz organizację.',
            'newRole.required' => 'Wybierz rolę.',
            'newUserId.required' => 'Wybierz użytkownika.',
        ]);

        $payload = [
            'visibility_type' => $validated['newVisibilityType'],
            'organization_id' => $validated['newVisibilityType'] === 'organization' ? $validated['newOrganizationId'] : null,
            'role' => $validated['newVisibilityType'] === 'role' ? $validated['newRole'] : null,
            'user_id' => $validated['newVisibilityType'] === 'user' ? $validated['newUserId'] : null,
        ];

        $rule = $this->article->visibilities()->create($payload);

        AuditLogger::log(AuditAction::ArticleVisibilityChanged, $this->article, null, $payload + ['op' => 'add']);

        $this->reset(['newVisibilityType', 'newOrganizationId', 'newRole', 'newUserId']);
        $this->newVisibilityType = 'organization';
        $this->article->refresh();
    }

    public function removeVisibility(int $visibilityId): void
    {
        // Fail-closed: bez utrwalonego artykułu nie autoryzujemy (unika TypeError przy podrobionym wywołaniu).
        if (! $this->article) {
            return;
        }

        $this->authorize('changeVisibility', $this->article);

        $rule = $this->article->visibilities()->whereKey($visibilityId)->first();
        if (! $rule) {
            return;
        }

        $payload = [
            'visibility_type' => $rule->visibility_type,
            'organization_id' => $rule->organization_id,
            'role' => $rule->role?->value,
            'user_id' => $rule->user_id,
        ];

        $rule->delete();

        AuditLogger::log(AuditAction::ArticleVisibilityChanged, $this->article, $payload + ['op' => 'remove'], null);

        $this->article->refresh();
    }

    public function render()
    {
        $visibilities = $this->article
            ? $this->article->visibilities()->with(['organization', 'user'])->get()
            : collect();

        return view('livewire.knowledge.manage-form', [
            'categories' => KnowledgeCategory::orderBy('name')->get(),
            'statuses' => PublicationStatus::options(),
            'roles' => Role::options(),
            'visibilities' => $visibilities,
            'organizations' => Organization::orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
        ]);
    }
}
