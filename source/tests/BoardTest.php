<?php

declare(strict_types=1);

namespace Buscaminas\Tests;

use Buscaminas\Domain\Board;
use Buscaminas\Domain\BoardConfig;
use Buscaminas\Domain\CellState;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

final class BoardTest extends TestCase
{
    #[Test]
    public function calcula_las_minas_vecinas(): void
    {
        $board = Board::fromLayout(self::layout());

        self::assertSame(1, $board->cell(1, 0)->adjacentMines());
        self::assertSame(1, $board->cell(1, 1)->adjacentMines());
        self::assertSame(0, $board->cell(4, 4)->adjacentMines());
        self::assertSame(2, $board->cell(5, 7)->adjacentMines());
        self::assertSame(3, $board->cell(8, 9)->adjacentMines());
    }

    #[Test]
    public function el_primer_clic_nunca_es_mina(): void
    {
        // Tablero con el máximo de minas permitido: (9-1)·(9-1) = 64.
        $board = new Board(new BoardConfig(9, 9, 64));
        $board->placeMines(4, 4, new Randomizer(new Mt19937(123)));

        self::assertFalse($board->cell(4, 4)->hasMine());
        $mines = 0;
        for ($y = 0; $y < 9; $y++) {
            for ($x = 0; $x < 9; $x++) {
                $mines += $board->cell($x, $y)->hasMine() ? 1 : 0;
            }
        }
        self::assertSame(64, $mines);
    }

    #[Test]
    public function destapa_en_cascada_las_zonas_vacias(): void
    {
        $board = Board::fromLayout(self::layout());

        self::assertTrue($board->reveal(4, 4));
        self::assertTrue($board->cell(0, 8)->isRevealed());
        self::assertTrue($board->cell(8, 0)->isRevealed());
        self::assertFalse($board->cell(0, 0)->isRevealed(), 'La mina no se destapa');
        self::assertFalse($board->cell(8, 8)->isRevealed());
    }

    #[Test]
    public function la_cascada_no_destapa_banderas(): void
    {
        $board = Board::fromLayout(self::layout());
        $board->toggleMark(4, 0, true);

        $board->reveal(4, 4);

        self::assertSame(CellState::Flagged, $board->cell(4, 0)->state());
    }

    #[Test]
    public function destapar_una_mina_la_hace_explotar(): void
    {
        $board = Board::fromLayout(self::layout());

        self::assertFalse($board->reveal(0, 0));
        self::assertTrue($board->cell(0, 0)->isExploded());
    }

    #[Test]
    public function el_clic_derecho_cicla_bandera_interrogacion_y_nada(): void
    {
        $board = Board::fromLayout(self::layout());

        $board->toggleMark(2, 2, true);
        self::assertSame(CellState::Flagged, $board->cell(2, 2)->state());
        $board->toggleMark(2, 2, true);
        self::assertSame(CellState::Question, $board->cell(2, 2)->state());
        $board->toggleMark(2, 2, true);
        self::assertSame(CellState::Hidden, $board->cell(2, 2)->state());

        $board->toggleMark(2, 2, false);
        $board->toggleMark(2, 2, false);
        self::assertSame(CellState::Hidden, $board->cell(2, 2)->state(), 'Sin marcas (?) se salta la interrogación');
    }

    #[Test]
    public function el_acorde_destapa_las_vecinas_cuando_las_banderas_coinciden(): void
    {
        $board = Board::fromLayout(self::layout());
        $board->reveal(1, 1);                  // número 1
        $board->chord(1, 1);                   // sin bandera: no hace nada
        self::assertFalse($board->cell(2, 2)->isRevealed());

        $board->toggleMark(0, 0, true);        // bandera correcta
        self::assertTrue($board->chord(1, 1));
        self::assertTrue($board->cell(2, 2)->isRevealed());
        self::assertTrue($board->cell(5, 5)->isRevealed(), 'Una vecina vacía dispara la cascada');
    }

    #[Test]
    public function el_acorde_con_bandera_equivocada_pierde(): void
    {
        $board = Board::fromLayout(self::layout());
        $board->reveal(1, 1);
        $board->toggleMark(2, 0, true);        // bandera en una casilla sin mina

        self::assertFalse($board->chord(1, 1));
        self::assertTrue($board->cell(0, 0)->isExploded());
    }

    #[Test]
    public function detecta_cuando_todas_las_casillas_seguras_estan_destapadas(): void
    {
        $board = Board::fromLayout(self::layout());
        self::assertFalse($board->allSafeCellsRevealed());

        $safe = [];
        for ($y = 0; $y < $board->height(); $y++) {
            for ($x = 0; $x < $board->width(); $x++) {
                if (!$board->cell($x, $y)->hasMine()) {
                    $safe[] = [$x, $y];
                }
            }
        }
        $last = array_pop($safe);
        foreach ($safe as [$x, $y]) {
            $board->reveal($x, $y);
        }
        self::assertFalse($board->allSafeCellsRevealed());

        $board->reveal(...$last);
        self::assertTrue($board->allSafeCellsRevealed());
    }

    /** @return list<string> */
    private static function layout(): array
    {
        return [
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
        ];
    }
}
