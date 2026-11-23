<?php

declare(strict_types=1);

namespace Buscaminas\Domain;

/**
 * Una casilla del tablero.
 */
final class Cell
{
    private bool $mine = false;
    private int $adjacentMines = 0;
    private CellState $state = CellState::Hidden;
    private bool $exploded = false;

    public function hasMine(): bool
    {
        return $this->mine;
    }

    public function plantMine(): void
    {
        $this->mine = true;
    }

    public function adjacentMines(): int
    {
        return $this->adjacentMines;
    }

    public function setAdjacentMines(int $count): void
    {
        if ($count < 0 || $count > 8) {
            throw new \InvalidArgumentException('Una casilla solo puede tener entre 0 y 8 minas vecinas.');
        }
        $this->adjacentMines = $count;
    }

    public function state(): CellState
    {
        return $this->state;
    }

    public function isHidden(): bool
    {
        return $this->state === CellState::Hidden;
    }

    public function isRevealed(): bool
    {
        return $this->state === CellState::Revealed;
    }

    public function isFlagged(): bool
    {
        return $this->state === CellState::Flagged;
    }

    /** Una casilla se puede destapar si está oculta o con interrogación (nunca con bandera). */
    public function canBeRevealed(): bool
    {
        return $this->state === CellState::Hidden || $this->state === CellState::Question;
    }

    public function reveal(): void
    {
        $this->state = CellState::Revealed;
    }

    public function explode(): void
    {
        $this->state = CellState::Revealed;
        $this->exploded = true;
    }

    public function isExploded(): bool
    {
        return $this->exploded;
    }

    public function cycleMark(bool $questionMarksEnabled): void
    {
        $this->state = $this->state->nextMark($questionMarksEnabled);
    }

    public function flag(): void
    {
        if (!$this->isRevealed()) {
            $this->state = CellState::Flagged;
        }
    }
}
