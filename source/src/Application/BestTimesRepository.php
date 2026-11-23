<?php

declare(strict_types=1);

namespace Buscaminas\Application;

use Buscaminas\Domain\Difficulty;

/**
 * Puerto de persistencia de los "Mejores tiempos".
 */
interface BestTimesRepository
{
    /** @return array<string, array{seconds: int, name: string}> indexado por Difficulty::value */
    public function all(): array;

    public function record(Difficulty $difficulty, int $seconds, string $name): void;

    public function reset(): void;
}
