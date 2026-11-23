<?php

declare(strict_types=1);

namespace Buscaminas\Domain;

/**
 * Niveles oficiales del Buscaminas de Windows.
 */
enum Difficulty: string
{
    case Beginner = 'beginner';
    case Intermediate = 'intermediate';
    case Expert = 'expert';
    case Custom = 'custom';

    public function label(): string
    {
        return match ($this) {
            self::Beginner => 'Principiante',
            self::Intermediate => 'Intermedio',
            self::Expert => 'Experto',
            self::Custom => 'Personalizado',
        };
    }

    /**
     * Configuración estándar del nivel (9×9/10, 16×16/40, 30×16/99).
     * El nivel personalizado no tiene configuración fija.
     */
    public function config(): ?BoardConfig
    {
        return match ($this) {
            self::Beginner => new BoardConfig(9, 9, 10),
            self::Intermediate => new BoardConfig(16, 16, 40),
            self::Expert => new BoardConfig(30, 16, 99),
            self::Custom => null,
        };
    }

    /** Solo los niveles estándar registran mejores tiempos. */
    public function hasBestTimes(): bool
    {
        return $this !== self::Custom;
    }
}
