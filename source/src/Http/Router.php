<?php

declare(strict_types=1);

namespace Buscaminas\Http;

/**
 * Enrutador mínimo: asocia "MÉTODO /ruta" a un manejador.
 */
final class Router
{
    /** @var array<string, \Closure(Request): JsonResponse> */
    private array $routes = [];

    /** @param \Closure(Request): JsonResponse $handler */
    public function get(string $path, \Closure $handler): self
    {
        $this->routes["GET $path"] = $handler;

        return $this;
    }

    /** @param \Closure(Request): JsonResponse $handler */
    public function post(string $path, \Closure $handler): self
    {
        $this->routes["POST $path"] = $handler;

        return $this;
    }

    public function dispatch(Request $request): JsonResponse
    {
        $handler = $this->routes["{$request->method} {$request->path}"] ?? null;
        if ($handler === null) {
            return new JsonResponse(['error' => 'Ruta no encontrada'], 404);
        }

        try {
            return $handler($request);
        } catch (\InvalidArgumentException | \OutOfRangeException | \ValueError $e) {
            return new JsonResponse(['error' => $e->getMessage()], 422);
        }
    }
}
