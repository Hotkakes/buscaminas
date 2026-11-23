<?php

declare(strict_types=1);

namespace Buscaminas\Domain;

use Random\Randomizer;

/**
 * Una partida: tablero + estado + cronómetro.
 *
 * El tiempo se recibe como parámetro ($now) para que la partida sea
 * serializable en sesión y determinista en las pruebas.
 */
final class Game
{
    public const int MAX_DISPLAY_SECONDS = 999;

    private GameStatus $status = GameStatus::Ready;
    private ?float $startedAt = null;
    private ?float $endedAt = null;

    public function __construct(
        public readonly Difficulty $difficulty,
        private readonly Board $board,
    ) {
    }

    public static function start(Difficulty $difficulty, ?BoardConfig $custom = null): self
    {
        $config = $difficulty->config() ?? $custom
            ?? throw new \InvalidArgumentException('El nivel personalizado necesita ancho, alto y minas.');

        return new self($difficulty, new Board($config));
    }

    public function board(): Board
    {
        return $this->board;
    }

    public function status(): GameStatus
    {
        return $this->status;
    }

    public function reveal(int $x, int $y, float $now, Randomizer $randomizer = new Randomizer()): void
    {
        if ($this->status->isOver() || !$this->board->cell($x, $y)->canBeRevealed()) {
            return;
        }

        if ($this->status === GameStatus::Ready) {
            $this->board->placeMines($x, $y, $randomizer);
            $this->status = GameStatus::Playing;
            $this->startedAt = $now;
        }

        $this->settle($this->board->reveal($x, $y), $now);
    }

    public function chord(int $x, int $y, float $now): void
    {
        if ($this->status !== GameStatus::Playing) {
            return;
        }

        $this->settle($this->board->chord($x, $y), $now);
    }

    /** Clic derecho. En Windows se puede marcar incluso antes del primer clic. */
    public function toggleMark(int $x, int $y, bool $questionMarksEnabled): void
    {
        if ($this->status->isOver()) {
            return;
        }

        $this->board->toggleMark($x, $y, $questionMarksEnabled);
    }

    /** Minas restantes = minas − banderas (puede ser negativo, como en el original). */
    public function minesLeft(): int
    {
        return $this->board->config->mines - $this->board->flagCount();
    }

    /**
     * Segundos mostrados en el contador. Windows empieza en 1 al primer clic
     * y se detiene en 999.
     */
    public function elapsedSeconds(float $now): int
    {
        if ($this->startedAt === null) {
            return 0;
        }

        $end = $this->endedAt ?? $now;

        return min(self::MAX_DISPLAY_SECONDS, (int) floor($end - $this->startedAt) + 1);
    }

    private function settle(bool $safe, float $now): void
    {
        if (!$safe) {
            $this->status = GameStatus::Lost;
            $this->endedAt = $now;
            return;
        }

        if ($this->board->allSafeCellsRevealed()) {
            $this->status = GameStatus::Won;
            $this->endedAt = $now;
            $this->board->flagAllMines();
        }
    }
}
