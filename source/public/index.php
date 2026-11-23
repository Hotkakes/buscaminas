<?php

declare(strict_types=1);

/*
 * Front controller: único punto de entrada de la aplicación.
 *   php -S localhost:8000 -t public public/index.php
 */

// Con el servidor embebido, deja que sirva directamente los ficheros estáticos.
if (PHP_SAPI === 'cli-server' && is_file(__DIR__ . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH))) {
    return false;
}

require dirname(__DIR__) . '/bootstrap.php';

use Buscaminas\Application\GameService;
use Buscaminas\Http\GameController;
use Buscaminas\Http\Request;
use Buscaminas\Http\Router;
use Buscaminas\Infrastructure\JsonBestTimesRepository;
use Buscaminas\Infrastructure\SessionGameRepository;

$request = Request::fromGlobals();

if (!str_starts_with($request->path, '/api/')) {
    header('Content-Type: text/html; charset=utf-8');
    require BUSCAMINAS_TEMPLATES . '/game.php';
    return;
}

$service = new GameService(
    new SessionGameRepository(),
    new JsonBestTimesRepository(BUSCAMINAS_STORAGE . '/best-times.json'),
);

new GameController($service)
    ->register(new Router())
    ->dispatch($request)
    ->send();
