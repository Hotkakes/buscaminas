<?php

declare(strict_types=1);

namespace Buscaminas\Http;

use Buscaminas\Application\GameService;
use Buscaminas\Domain\Difficulty;

/**
 * Traduce las peticiones HTTP de la API a casos de uso de GameService.
 */
final readonly class GameController
{
    public function __construct(private GameService $service)
    {
    }

    public function register(Router $router): Router
    {
        return $router
            ->get('/api/game', fn (): JsonResponse => new JsonResponse($this->service->current()))
            ->post('/api/game/new', $this->newGame(...))
            ->post('/api/game/restart', fn (): JsonResponse => new JsonResponse($this->service->restart()))
            ->post('/api/game/reveal', fn (Request $r): JsonResponse => new JsonResponse(
                $this->service->reveal($r->int('x'), $r->int('y')),
            ))
            ->post('/api/game/chord', fn (Request $r): JsonResponse => new JsonResponse(
                $this->service->chord($r->int('x'), $r->int('y')),
            ))
            ->post('/api/game/mark', fn (Request $r): JsonResponse => new JsonResponse(
                $this->service->toggleMark($r->int('x'), $r->int('y')),
            ))
            ->post('/api/settings/question-marks', fn (Request $r): JsonResponse => new JsonResponse(
                $this->service->setQuestionMarks($r->bool('enabled')),
            ))
            ->get('/api/best-times', fn (): JsonResponse => new JsonResponse($this->service->bestTimes()))
            ->post('/api/best-times', fn (Request $r): JsonResponse => new JsonResponse(
                $this->service->recordBestTime($r->string('name', 32) ?: 'Anónimo'),
            ))
            ->post('/api/best-times/reset', fn (): JsonResponse => new JsonResponse($this->service->resetBestTimes()));
    }

    private function newGame(Request $request): JsonResponse
    {
        $difficulty = Difficulty::tryFrom($request->string('difficulty', 20))
            ?? throw new \InvalidArgumentException('Nivel de dificultad desconocido.');

        return new JsonResponse($this->service->newGame(
            $difficulty,
            $request->optionalInt('width'),
            $request->optionalInt('height'),
            $request->optionalInt('mines'),
        ));
    }
}
