<?php

declare(strict_types=1);

namespace Buscaminas\Http;

/**
 * Petición HTTP mínima e inmutable.
 */
final readonly class Request
{
    /** @param array<string, mixed> $body */
    public function __construct(
        public string $method,
        public string $path,
        public array $body = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        // Operador pipe de PHP 8.5: normaliza la ruta en una sola expresión.
        $path = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)
            |> rawurldecode(...)
            |> (static fn (string $p): string => rtrim($p, '/') ?: '/');

        $raw = (string) file_get_contents('php://input');
        $body = $raw === '' ? [] : json_decode($raw, true);

        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path,
            is_array($body) ? $body : [],
        );
    }

    public function int(string $key): int
    {
        $value = $this->body[$key] ?? null;
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new \InvalidArgumentException("El campo '$key' debe ser un entero.");
        }

        return (int) $value;
    }

    public function optionalInt(string $key): ?int
    {
        return isset($this->body[$key]) ? $this->int($key) : null;
    }

    public function bool(string $key): bool
    {
        return filter_var($this->body[$key] ?? false, FILTER_VALIDATE_BOOL);
    }

    public function string(string $key, int $maxLength = 255): string
    {
        return (string) ($this->body[$key] ?? '')
            |> strip_tags(...)
            |> trim(...)
            |> (static fn (string $s): string => mb_substr($s, 0, $maxLength));
    }
}
