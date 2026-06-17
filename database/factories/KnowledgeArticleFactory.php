<?php

namespace Database\Factories;

use App\Enums\PublicationStatus;
use App\Models\KnowledgeArticle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<KnowledgeArticle>
 */
class KnowledgeArticleFactory extends Factory
{
    protected $model = KnowledgeArticle::class;

    /** @return array<string,mixed> */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 999999),
            'body' => '<p>'.fake()->paragraph().'</p>',
            'knowledge_category_id' => null,
            'status' => PublicationStatus::Draft,
            'author_id' => User::factory(),
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => PublicationStatus::Published,
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => PublicationStatus::Draft,
            'published_at' => null,
        ]);
    }

    public function authoredBy(User $user): static
    {
        return $this->state(fn () => ['author_id' => $user->id]);
    }
}
