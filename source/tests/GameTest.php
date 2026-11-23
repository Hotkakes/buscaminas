<?php

declare(strict_types=1);

namespace Buscaminas\Tests;

use Buscaminas\Domain\Board;
use Buscaminas\Domain\BoardConfig;
use Buscaminas\Domain\Difficulty;
use Buscaminas\Domain\Game;
use Buscaminas\Domain\GameStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GameTest extends TestCase
{
    #[Test]
    public function los_niveles_estandar_tienen_las_medidas_de_windows(): void
    {
        self::assertEquals(new BoardConfig(9, 9, 10), Difficulty::Beginner->config());
        self::assertEquals(new BoardConfig(16, 16, 40), Difficulty::Intermediate->config());
        self::assertEquals(new BoardConfig(30, 16, 99), Difficulty::Expert->config());
    }

    #[Test]
    public function el_nivel_personalizado_ajusta_valores_fuera_de_rango(): void
    {
        $config = BoardConfig::clamped(100, 2, 5000);

        self::assertSame(30, $config->width);
        self::assertSame(9, $config->height);
        self::assertSame(29 * 8, $config->mines);
    }

    #[Test]
    public function el_primer_clic_empieza_la_partida_y_el_cronometro(): void
    {
        $game = Game::start(Difficulty::Beginner);
        self::assertSame(GameStatus::Ready, $game->status());
        self::assertSame(0, $game->elapsedSeconds(100.0));

        $game->reveal(0, 0, 100.0);

        self::assertNotSame(GameStatus::Lost, $game->status());
        self::assertSame(1, $game->elapsedSeconds(100.0));
        self::assertSame(11, $game->elapsedSeconds(110.5));
        self::assertSame(999, $game->elapsedSeconds(5000.0));
    }

    #[Test]
    public function perder_detiene_el_cronometro(): void
    {
        $game = $this->gameFromLayout();
        $game->reveal(4, 4, 10.0);
        $game->reveal(0, 0, 15.0);

        self::assertSame(GameStatus::Lost, $game->status());
        self::assertSame(6, $game->elapsedSeconds(60.0));
    }

    #[Test]
    public function ganar_pone_banderas_en_todas_las_minas(): void
    {
        $game = $this->gameFromLayout();
        $board = $game->board();
        for ($y = 0; $y < $board->height(); $y++) {
            for ($x = 0; $x < $board->width(); $x++) {
                if (!$board->cell($x, $y)->hasMine()) {
                    $game->reveal($x, $y, 1.0);
                }
            }
        }

        self::assertSame(GameStatus::Won, $game->status());
        self::assertSame(0, $game->minesLeft());
    }

    #[Test]
    public function las_banderas_reducen_el_contador_y_puede_ser_negativo(): void
    {
        $game = Game::start(Difficulty::Beginner);
        for ($x = 0; $x < 9; $x++) {
            $game->toggleMark($x, 0, true);
            $game->toggleMark($x, 1, true);
        }

        self::assertSame(10 - 18, $game->minesLeft());
    }

    #[Test]
    public function la_partida_se_puede_serializar_en_sesion(): void
    {
        $game = $this->gameFromLayout();
        $game->reveal(4, 4, 1.0);

        $copy = unserialize(serialize($game));

        self::assertInstanceOf(Game::class, $copy);
        self::assertSame(GameStatus::Playing, $copy->status());
        self::assertTrue($copy->board()->cell(4, 4)->isRevealed());
    }

    private function gameFromLayout(): Game
    {
        return new Game(Difficulty::Custom, Board::fromLayout([
            '*........',
            '.........',
            '.........',
            '.........',
            '.........',
            '.........',
            '.........',
            '......***',
            '......***',
            '.....***.', // (8, 9) queda aislada entre minas
        ]));
    }
}
