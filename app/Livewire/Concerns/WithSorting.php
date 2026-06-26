<?php

namespace App\Livewire\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

/**
 * Klikalne sortowanie kolumn w listach (URL-shareable).
 *
 * BEZPIECZEŃSTWO: $sortCol/$sortDir pochodzą z #[Url] (sterowalne przez użytkownika).
 * Nigdy nie trafiają wprost do orderBy — applySort() sortuje WYŁĄCZNIE po kolumnie
 * z białej listy sortableColumns(), a kierunek jest klampowany do {asc,desc}.
 *
 * Komponent korzystający musi zaimplementować sortableColumns() (tylko BEZPOŚREDNIE
 * kolumny tabeli — bez relacji/JOIN-ów) oraz defaultSort() (= bieżący domyślny orderBy).
 */
trait WithSorting
{
    #[Url]
    public string $sortCol = '';

    #[Url]
    public string $sortDir = 'asc';

    /** Kliknięcie nagłówka: ta sama kolumna → toggle kierunku, inna → asc. */
    public function sortBy(string $col): void
    {
        if (! in_array($col, $this->sortableColumns(), true)) {
            return; // poza białą listą — ignoruj (brak iniekcji)
        }

        if ($this->effectiveSortCol() === $col) {
            $this->sortDir = $this->effectiveSortDir() === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortDir = 'asc';
        }

        $this->sortCol = $col;

        if (method_exists($this, 'resetPage')) {
            $this->resetPage();
        }
    }

    /** Aktualnie obowiązująca kolumna sortowania (z białej listy lub domyślna). */
    protected function effectiveSortCol(): string
    {
        return in_array($this->sortCol, $this->sortableColumns(), true)
            ? $this->sortCol
            : $this->defaultSort()[0];
    }

    /** Aktualnie obowiązujący kierunek (klamp do asc|desc; przy nieprawidłowej kolumnie — domyślny). */
    protected function effectiveSortDir(): string
    {
        if (! in_array($this->sortCol, $this->sortableColumns(), true)) {
            return $this->defaultSort()[1];
        }

        return $this->sortDir === 'desc' ? 'desc' : 'asc';
    }

    /** Dokłada bezpieczny orderBy do zapytania (tylko whitelista + asc|desc). */
    protected function applySort(Builder $q): Builder
    {
        return $q->orderBy($this->effectiveSortCol(), $this->effectiveSortDir());
    }

    /** Biała lista sortowalnych kolumn — TYLKO bezpośrednie kolumny tabeli. */
    abstract protected function sortableColumns(): array;

    /** Domyślne sortowanie [kolumna, kierunek], np. ['name', 'asc']. */
    abstract protected function defaultSort(): array;
}
