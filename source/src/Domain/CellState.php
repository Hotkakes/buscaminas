<?php

declare(strict_types=1);

namespace Buscaminas\Domain;

/**
 * Estado visible de una casilla, igual que en el Buscaminas clásico.
 */
enum CellState: string
{
    case Hidden = 'hidden';
    case Flagged = 'flagged';
    case Question = 'question';
    case Revealed = 'revealed';

    /**
     * Ciclo del clic derecho: oculta → bandera → interrogación (si está activa) → oculta.
     */
    public function nextMark(bool $questionMarksEnabled): self
    {
        return match ($this) {
            self::Hidden => self::Flagged,
            self::Flagged => $questionMarksEnabled ? self::Question : self::Hidden,
            self::Question => self::Hidden,
            self::Revealed => self::Revealed,
        };
    }
}
