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
 * Komponent korzystający musi zaimplementować sortableColumns() (biała lista kluczy —
 * kolumny tabeli ORAZ klucze relacyjne/liczniki) oraz defaultSort() (= bieżący domyślny orderBy).
 *
 * Kolumny relacyjne/liczniki: komponent NADPISUJE sortExpression() z hardcodowanym
 * match($key) mapującym klucz na BEZPIECZNE korelowane podzapytanie (bez JOIN — nie
 * duplikuje wierszy, zgodne z paginate()/with(); działa na sqlite i pgsql). Klucz wchodzi
 * do match() tylko po przejściu białej listy (effectiveSortCol()), więc do SQL nigdy nie
 * trafia surowy input użytkownika.
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

    /**
     * Wyrażenie sortowania dla danego klucza. Domyślnie kolumna bezpośrednia (= klucz).
     * Komponent nadpisuje to dla kluczy relacyjnych/liczników, zwracając hardcodowane
     * korelowane podzapytanie. $key jest ZAWSZE z białej listy (effectiveSortCol()).
     *
     * @return mixed Kolumna (string) lub korelowane podzapytanie (Builder/Expression).
     */
    protected function sortExpression(string $key): mixed
    {
        return $key;
    }

    /** Dokłada bezpieczny orderBy do zapytania (tylko whitelista + asc|desc). */
    protected function applySort(Builder $q): Builder
    {
        return $q->orderBy($this->sortExpression($this->effectiveSortCol()), $this->effectiveSortDir());
    }

    /** Biała lista sortowalnych kluczy — kolumny tabeli oraz klucze relacyjne/liczniki. */
    abstract protected function sortableColumns(): array;

    /** Domyślne sortowanie [kolumna, kierunek], np. ['name', 'asc']. */
    abstract protected function defaultSort(): array;
}
