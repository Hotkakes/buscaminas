<?php

declare(strict_types=1);

namespace Buscaminas\Domain;

use Random\Randomizer;

/**
 * Tablero del Buscaminas: colocación de minas, destape en cascada y "acorde".
 */
final class Board
{
    /** @var list<list<Cell>> Casillas indexadas como [$y][$x]. */
    private array $cells = [];

    private bool $minesPlaced = false;

    public function __construct(public readonly BoardConfig $config)
    {
        for ($y = 0; $y < $config->height; $y++) {
            $row = [];
            for ($x = 0; $x < $config->width; $x++) {
                $row[] = new Cell();
            }
            $this->cells[] = $row;
        }
    }

    /**
     * Crea un tablero a partir de un dibujo, útil en pruebas.
     * '*' = mina, cualquier otro carácter = casilla libre.
     *
     * @param list<string> $rows
     */
    public static function fromLayout(array $rows): self
    {
        $height = count($rows);
        $width = strlen($rows[0]);
        $mines = array_sum(array_map(static fn (string $r): int => substr_count($r, '*'), $rows));

        $board = new self(new BoardConfig($width, $height, $mines));
        foreach ($rows as $y => $row) {
            foreach (str_split($row) as $x => $char) {
                if ($char === '*') {
                    $board->cells[$y][$x]->plantMine();
                }
            }
        }
        $board->computeAdjacency();
        $board->minesPlaced = true;

        return $board;
    }

    public function width(): int
    {
        return $this->config->width;
    }

    public function height(): int
    {
        return $this->config->height;
    }

    public function minesPlaced(): bool
    {
        return $this->minesPlaced;
    }

    public function cell(int $x, int $y): Cell
    {
        if (!$this->contains($x, $y)) {
            throw new \OutOfRangeException("La casilla ($x, $y) está fuera del tablero.");
        }

        return $this->cells[$y][$x];
    }

    public function contains(int $x, int $y): bool
    {
        return $x >= 0 && $y >= 0 && $x < $this->config->width && $y < $this->config->height;
    }

    /**
     * Coloca las minas al azar evitando la primera casilla pulsada,
     * de modo que el primer clic nunca pierde (comportamiento de Windows).
     */
    public function placeMines(int $safeX, int $safeY, Randomizer $randomizer = new Randomizer()): void
    {
        if ($this->minesPlaced) {
            return;
        }

        $safeIndex = $safeY * $this->config->width + $safeX;
        $candidates = array_values(array_filter(
            range(0, $this->config->cellCount() - 1),
            static fn (int $i): bool => $i !== $safeIndex,
        ));

        foreach (array_slice($randomizer->shuffleArray($candidates), 0, $this->config->mines) as $index) {
            $this->cells[intdiv($index, $this->config->width)][$index % $this->config->width]->plantMine();
        }

        $this->computeAdjacency();
        $this->minesPlaced = true;
    }

    /**
     * Destapa una casilla. Si no tiene minas vecinas, destapa en cascada (flood fill).
     *
     * @return bool false si la casilla tenía una mina.
     */
    public function reveal(int $x, int $y): bool
    {
        $cell = $this->cell($x, $y);
        if (!$cell->canBeRevealed()) {
            return true;
        }

        if ($cell->hasMine()) {
            $cell->explode();
            return false;
        }

        // Cascada iterativa para no desbordar la pila en tableros grandes.
        $stack = [[$x, $y]];
        while ($stack !== []) {
            [$cx, $cy] = array_pop($stack);
            $current = $this->cells[$cy][$cx];
            if (!$current->canBeRevealed() || $current->hasMine()) {
                continue;
            }
            $current->reveal();
            if ($current->adjacentMines() === 0) {
                foreach ($this->neighbours($cx, $cy) as [$nx, $ny]) {
                    if ($this->cells[$ny][$nx]->canBeRevealed()) {
                        $stack[] = [$nx, $ny];
                    }
                }
            }
        }

        return true;
    }

    /**
     * "Acorde" (clic con ambos botones o botón central) sobre un número:
     * si hay tantas banderas alrededor como indica el número, destapa el resto de vecinas.
     *
     * @return bool false si se destapó una mina (bandera mal puesta).
     */
    public function chord(int $x, int $y): bool
    {
        $cell = $this->cell($x, $y);
        if (!$cell->isRevealed() || $cell->adjacentMines() === 0) {
            return true;
        }

        $neighbours = $this->neighbours($x, $y);
        $flags = count(array_filter(
            $neighbours,
            fn (array $p): bool => $this->cells[$p[1]][$p[0]]->isFlagged(),
        ));
        if ($flags !== $cell->adjacentMines()) {
            return true;
        }

        $safe = true;
        foreach ($neighbours as [$nx, $ny]) {
            if ($this->cells[$ny][$nx]->canBeRevealed()) {
                $safe = $this->reveal($nx, $ny) && $safe;
            }
        }

        return $safe;
    }

    public function toggleMark(int $x, int $y, bool $questionMarksEnabled): void
    {
        $this->cell($x, $y)->cycleMark($questionMarksEnabled);
    }

    /** Se gana al destapar todas las casillas sin mina (las banderas no son necesarias). */
    public function allSafeCellsRevealed(): bool
    {
        return !array_any(
            $this->allCells(),
            static fn (Cell $c): bool => !$c->hasMine() && !$c->isRevealed(),
        );
    }

    public function flagCount(): int
    {
        return count(array_filter($this->allCells(), static fn (Cell $c): bool => $c->isFlagged()));
    }

    /** Al ganar, Windows coloca automáticamente banderas en todas las minas. */
    public function flagAllMines(): void
    {
        foreach ($this->allCells() as $cell) {
            if ($cell->hasMine()) {
                $cell->flag();
            }
        }
    }

    /** @return list<Cell> */
    private function allCells(): array
    {
        return array_merge(...$this->cells);
    }

    /** @return list<array{int, int}> */
    public function neighbours(int $x, int $y): array
    {
        $result = [];
        for ($dy = -1; $dy <= 1; $dy++) {
            for ($dx = -1; $dx <= 1; $dx++) {
                if (($dx !== 0 || $dy !== 0) && $this->contains($x + $dx, $y + $dy)) {
                    $result[] = [$x + $dx, $y + $dy];
                }
            }
        }

        return $result;
    }

    private function computeAdjacency(): void
    {
        foreach ($this->cells as $y => $row) {
            foreach ($row as $x => $cell) {
                $count = 0;
                foreach ($this->neighbours($x, $y) as [$nx, $ny]) {
                    $count += $this->cells[$ny][$nx]->hasMine() ? 1 : 0;
                }
                $cell->setAdjacentMines($count);
            }
        }
    }
}
