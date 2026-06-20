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
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.app')]
class ManageForm extends Component
{
    use WithFileUploads;

    public ?KnowledgeArticle $article = null;

    // Wgrywany obraz artykułu (tylko po zapisaniu artykułu).
    public $image = null;

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
            // Unikalność sluga gwarantuje uniqueSlug() przy zapisie (auto-sufiks -2, -3, …),
            // więc tutaj NIE walidujemy unique — inaczej duplikat byłby odrzucany zamiast suffiksowany.
            'slug' => ['nullable', 'string', 'max:255'],
            'knowledge_category_id' => ['nullable', 'integer', 'exists:knowledge_categories,id'],
            'body' => ['nullable', 'string'],
            'status' => ['required', Rule::enum(PublicationStatus::class)],
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

        // Blokada duplikatów: ta sama reguła (np. ten sam artykuł → ta sama organizacja)
        // nie może być dodana dwa razy. where(kol, null) Eloquent tłumaczy na IS NULL.
        $already = $this->article->visibilities()
            ->where('visibility_type', $payload['visibility_type'])
            ->where('organization_id', $payload['organization_id'])
            ->where('role', $payload['role'])
            ->where('user_id', $payload['user_id'])
            ->exists();

        if ($already) {
            $field = match ($payload['visibility_type']) {
                'organization' => 'newOrganizationId',
                'role' => 'newRole',
                default => 'newUserId',
            };
            $this->addError($field, 'Ta reguła widoczności jest już dodana.');

            return;
        }

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

    /* ----------------------------- Obrazy artykułu ----------------------------- */

    public function uploadImage(): void
    {
        // Fail-closed: obraz musi przypiąć się do UTRWALONEGO artykułu.
        if (! $this->article) {
            return;
        }

        $this->authorize('update', $this->article);

        // RASTER ONLY — brak SVG (anty-XSS). Plik nigdy nie trafia na dysk publiczny.
        $this->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp,bmp', 'max:4096'],
        ]);

        $ext = strtolower($this->image->getClientOriginalExtension());
        // Losowa nazwa na dysku — bez nazwy klienta, więc brak path traversal.
        $storedName = Str::random(40).'.'.$ext;
        $path = $this->image->storeAs('kb-images/'.$this->article->id, $storedName, 'local');

        $attachment = $this->article->attachments()->create([
            'organization_id' => $this->article->organization_id ?? null,
            'original_name' => $this->image->getClientOriginalName(),
            'stored_name' => $storedName,
            'path' => $path,
            'mime_type' => $this->image->getMimeType(),
            'size' => $this->image->getSize(),
            'uploaded_by' => auth()->id(),
        ]);

        AuditLogger::log(AuditAction::AttachmentAdded, $attachment);

        $this->image = null;
        session()->flash('imageStatus', 'Wgrano obraz.');
    }

    public function removeImage(int $id): void
    {
        // Fail-closed: bez utrwalonego artykułu nie autoryzujemy.
        if (! $this->article) {
            return;
        }

        $this->authorize('update', $this->article);

        $att = $this->article->attachments()->whereKey($id)->first();
        if (! $att) {
            return;
        }

        Storage::disk('local')->delete($att->path);
        $att->forceDelete();

        session()->flash('imageStatus', 'Usunięto obraz.');
    }

    public function render()
    {
        $visibilities = $this->article
            ? $this->article->visibilities()->with(['organization', 'user'])->get()
            : collect();

        $images = $this->article
            ? $this->article->attachments()->latest()->get()
            : collect();

        return view('livewire.knowledge.manage-form', [
            'categories' => KnowledgeCategory::orderBy('name')->get(),
            'statuses' => PublicationStatus::options(),
            'roles' => Role::options(),
            'visibilities' => $visibilities,
            'images' => $images,
            'organizations' => Organization::orderBy('name')->get(),
            'users' => User::orderBy('name')->get(),
        ]);
    }
}
