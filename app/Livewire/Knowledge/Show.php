<?php

namespace App\Livewire\Knowledge;

use App\Models\KnowledgeArticle;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Artykuł')]
class Show extends Component
{
    public KnowledgeArticle $article;

    public function mount(KnowledgeArticle $article): void
    {
        $this->authorize('view', $article);
        $this->article = $article;
    }

    public function render()
    {
        $user = auth()->user();
        $this->article->load(['category', 'author']);

        return view('livewire.knowledge.show', [
            'canUpdate' => $user->can('update', $this->article),
        ]);
    }
}
