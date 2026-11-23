<?php

declare(strict_types=1);

namespace Buscaminas\Infrastructure;

use Buscaminas\Application\GameRepository;
use Buscaminas\Domain\Game;

/**
 * Guarda la partida de cada jugador en su sesión PHP.
 */
final class SessionGameRepository implements GameRepository
{
    private const string GAME_KEY = 'buscaminas.game';
    private const string MARKS_KEY = 'buscaminas.questionMarks';

    public function __construct()
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Strict',
                'use_strict_mode' => true,
            ]);
        }
    }

    public function load(): ?Game
    {
        $serialized = $_SESSION[self::GAME_KEY] ?? null;
        if (!is_string($serialized)) {
            return null;
        }

        $game = unserialize($serialized, ['allowed_classes' => [
            Game::class,
            \Buscaminas\Domain\Board::class,
            \Buscaminas\Domain\BoardConfig::class,
            \Buscaminas\Domain\Cell::class,
            \Buscaminas\Domain\CellState::class,
            \Buscaminas\Domain\Difficulty::class,
            \Buscaminas\Domain\GameStatus::class,
        ]]);

        return $game instanceof Game ? $game : null;
    }

    public function save(Game $game): void
    {
        $_SESSION[self::GAME_KEY] = serialize($game);
    }

    public function questionMarksEnabled(): bool
    {
        // En Windows las marcas (?) vienen activadas por defecto.
        return (bool) ($_SESSION[self::MARKS_KEY] ?? true);
    }

    public function setQuestionMarksEnabled(bool $enabled): void
    {
        $_SESSION[self::MARKS_KEY] = $enabled;
    }
}
