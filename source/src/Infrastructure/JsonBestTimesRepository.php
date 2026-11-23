<?php

declare(strict_types=1);

namespace Buscaminas\Infrastructure;

use Buscaminas\Application\BestTimesRepository;
use Buscaminas\Domain\Difficulty;

/**
 * Guarda los mejores tiempos en un fichero JSON (storage/best-times.json).
 * Usa un bloqueo exclusivo para evitar escrituras simultáneas.
 */
final readonly class JsonBestTimesRepository implements BestTimesRepository
{
    public function __construct(private string $file)
    {
    }

    public function all(): array
    {
        if (!is_file($this->file)) {
            return [];
        }

        $data = json_decode((string) file_get_contents($this->file), true);

        return is_array($data) ? $data : [];
    }

    public function record(Difficulty $difficulty, int $seconds, string $name): void
    {
        $this->mutate(static function (array $data) use ($difficulty, $seconds, $name): array {
            $current = $data[$difficulty->value]['seconds'] ?? PHP_INT_MAX;
            if ($seconds < $current) {
                $data[$difficulty->value] = ['seconds' => $seconds, 'name' => $name];
            }

            return $data;
        });
    }

    public function reset(): void
    {
        $this->mutate(static fn (array $data): array => []);
    }

    /** @param callable(array): array $change */
    private function mutate(callable $change): void
    {
        $dir = dirname($this->file);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $handle = fopen($this->file, 'c+');
        if ($handle === false) {
            throw new \RuntimeException("No se pudo abrir {$this->file}");
        }

        try {
            flock($handle, LOCK_EX);
            $raw = stream_get_contents($handle);
            $data = json_decode($raw === false || $raw === '' ? '[]' : $raw, true);
            $data = $change(is_array($data) ? $data : []);

            ftruncate($handle, 0);
            rewind($handle);
            fwrite($handle, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
