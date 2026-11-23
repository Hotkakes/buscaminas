<?php

declare(strict_types=1);

namespace Buscaminas\Domain;

enum GameStatus: string
{
    /** Tablero nuevo: las minas aún no se han colocado. */
    case Ready = 'ready';
    case Playing = 'playing';
    case Won = 'won';
    case Lost = 'lost';

    public function isOver(): bool
    {
        return $this === self::Won || $this === self::Lost;
    }
}
