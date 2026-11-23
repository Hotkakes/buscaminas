<?php

declare(strict_types=1);

namespace Buscaminas\Application;

use Buscaminas\Domain\BoardConfig;
use Buscaminas\Domain\Cell;
use Buscaminas\Domain\CellState;
use Buscaminas\Domain\Difficulty;
use Buscaminas\Domain\Game;
use Buscaminas\Domain\GameStatus;

/**
 * Casos de uso del juego. Orquesta dominio y persistencia y devuelve
 * la vista del tablero lista para serializar a JSON.
 */
final class GameService
{
    public const int DEFAULT_SECONDS = 999;

    /** @var \Closure(): float */
    private \Closure $clock;

    public function __construct(
        private readonly GameRepository $games,
        private readonly BestTimesRepository $bestTimes,
        ?\Closure $clock = null,
    ) {
        $this->clock = $clock ?? static fn (): float => microtime(true);
    }

    /** @return array<string, mixed> */
    public function current(): array
    {
        return $this->present($this->game());
    }

    /** @return array<string, mixed> */
    public function newGame(Difficulty $difficulty, ?int $width = null, ?int $height = null, ?int $mines = null): array
    {
        $custom = $difficulty === Difficulty::Custom
            ? BoardConfig::clamped($width ?? 9, $height ?? 9, $mines ?? 10)
            : null;

        $game = Game::start($difficulty, $custom);
        $this->games->save($game);

        return $this->present($game);
    }

    /** Reinicia con el mismo nivel (clic en la carita o F2). @return array<string, mixed> */
    public function restart(): array
    {
        $previous = $this->game();
        $config = $previous->board()->config;

        return $this->newGame($previous->difficulty, $config->width, $config->height, $config->mines);
    }

    /** @return array<string, mixed> */
    #[\NoDiscard]
    public function reveal(int $x, int $y): array
    {
        $game = $this->game();
        $game->reveal($x, $y, $this->now());
        $this->games->save($game);

        return $this->present($game);
    }

    /** @return array<string, mixed> */
    #[\NoDiscard]
    public function chord(int $x, int $y): array
    {
        $game = $this->game();
        $game->chord($x, $y, $this->now());
        $this->games->save($game);

        return $this->present($game);
    }

    /** @return array<string, mixed> */
    #[\NoDiscard]
    public function toggleMark(int $x, int $y): array
    {
        $game = $this->game();
        $game->toggleMark($x, $y, $this->games->questionMarksEnabled());
        $this->games->save($game);

        return $this->present($game);
    }

    /** @return array<string, mixed> */
    public function setQuestionMarks(bool $enabled): array
    {
        $this->games->setQuestionMarksEnabled($enabled);

        return $this->current();
    }

    /** @return array<string, array{label: string, seconds: int, name: string}> */
    public function bestTimes(): array
    {
        $stored = $this->bestTimes->all();
        $result = [];
        foreach (Difficulty::cases() as $difficulty) {
            if (!$difficulty->hasBestTimes()) {
                continue;
            }
            $result[$difficulty->value] = [
                'label' => $difficulty->label(),
                'seconds' => $stored[$difficulty->value]['seconds'] ?? self::DEFAULT_SECONDS,
                'name' => $stored[$difficulty->value]['name'] ?? 'Anónimo',
            ];
        }

        return $result;
    }

    /**
     * Guarda el tiempo de la partida ganada si mejora el récord.
     * El tiempo se toma del servidor, nunca del cliente.
     *
     * @return array<string, array{label: string, seconds: int, name: string}>
     */
    public function recordBestTime(string $name): array
    {
        $game = $this->game();
        if ($this->isNewRecord($game)) {
            $this->bestTimes->record($game->difficulty, $game->elapsedSeconds($this->now()), $name);
        }

        return $this->bestTimes();
    }

    /** @return array<string, array{label: string, seconds: int, name: string}> */
    public function resetBestTimes(): array
    {
        $this->bestTimes->reset();

        return $this->bestTimes();
    }

    private function isNewRecord(Game $game): bool
    {
        if ($game->status() !== GameStatus::Won || !$game->difficulty->hasBestTimes()) {
            return false;
        }

        return $game->elapsedSeconds($this->now()) < $this->bestTimes()[$game->difficulty->value]['seconds'];
    }

    private function game(): Game
    {
        $game = $this->games->load();
        if ($game === null) {
            $game = Game::start(Difficulty::Beginner);
            $this->games->save($game);
        }

        return $game;
    }

    private function now(): float
    {
        return ($this->clock)();
    }

    /**
     * Convierte la partida en un DTO para el cliente. Las minas solo se
     * envían cuando la partida ha terminado, para que no se pueda hacer trampa
     * inspeccionando la red.
     *
     * Códigos de casilla:
     *  h = oculta, f = bandera, q = interrogación, 0-8 = destapada,
     *  m = mina, x = mina que explotó, w = bandera equivocada.
     *
     * @return array<string, mixed>
     */
    private function present(Game $game): array
    {
        $board = $game->board();
        $status = $game->status();
        $rows = [];

        for ($y = 0; $y < $board->height(); $y++) {
            $row = '';
            for ($x = 0; $x < $board->width(); $x++) {
                $row .= self::cellCode($board->cell($x, $y), $status);
            }
            $rows[] = $row;
        }

        return [
            'difficulty' => $game->difficulty->value,
            'width' => $board->width(),
            'height' => $board->height(),
            'mines' => $board->config->mines,
            'minesLeft' => $game->minesLeft(),
            'status' => $status->value,
            'elapsed' => $game->elapsedSeconds($this->now()),
            'questionMarks' => $this->games->questionMarksEnabled(),
            'newRecord' => $this->isNewRecord($game),
            'rows' => $rows,
        ];
    }

    private static function cellCode(Cell $cell, GameStatus $status): string
    {
        if ($status === GameStatus::Lost) {
            if ($cell->isExploded()) {
                return 'x';
            }
            if ($cell->isFlagged() && !$cell->hasMine()) {
                return 'w';
            }
            if ($cell->hasMine() && !$cell->isFlagged()) {
                return 'm';
            }
        }

        return match ($cell->state()) {
            CellState::Hidden => 'h',
            CellState::Flagged => 'f',
            CellState::Question => 'q',
            CellState::Revealed => (string) $cell->adjacentMines(),
        };
    }
}
