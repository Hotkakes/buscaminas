<?php

declare(strict_types=1);

namespace Buscaminas\Domain;

/**
 * Dimensiones y número de minas de un tablero.
 *
 * Los límites replican los del Buscaminas de Windows:
 * ancho 9–30, alto 9–24, minas 10 – (ancho-1)·(alto-1).
 */
final readonly class BoardConfig
{
    public const int MIN_WIDTH = 9;
    public const int MAX_WIDTH = 30;
    public const int MIN_HEIGHT = 9;
    public const int MAX_HEIGHT = 24;
    public const int MIN_MINES = 10;

    public function __construct(
        public int $width,
        public int $height,
        public int $mines,
    ) {
        if ($width < self::MIN_WIDTH || $width > self::MAX_WIDTH) {
            throw new \InvalidArgumentException('El ancho debe estar entre 9 y 30.');
        }
        if ($height < self::MIN_HEIGHT || $height > self::MAX_HEIGHT) {
            throw new \InvalidArgumentException('El alto debe estar entre 9 y 24.');
        }
        if ($mines < self::MIN_MINES || $mines > self::maxMines($width, $height)) {
            throw new \InvalidArgumentException('Número de minas fuera de rango.');
        }
    }

    /**
     * Igual que Windows: los valores fuera de rango se ajustan en lugar de dar error.
     */
    public static function clamped(int $width, int $height, int $mines): self
    {
        $width = max(self::MIN_WIDTH, min(self::MAX_WIDTH, $width));
        $height = max(self::MIN_HEIGHT, min(self::MAX_HEIGHT, $height));
        $mines = max(self::MIN_MINES, min(self::maxMines($width, $height), $mines));

        return new self($width, $height, $mines);
    }

    public static function maxMines(int $width, int $height): int
    {
        return ($width - 1) * ($height - 1);
    }

    public function cellCount(): int
    {
        return $this->width * $this->height;
    }
}
