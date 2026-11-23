<?php

declare(strict_types=1);

/*
 * Arranque de la aplicación.
 * Usa el autoload de Composer si existe; si no, un autoloader PSR-4 mínimo,
 * para que el juego funcione sin instalar dependencias.
 */

if (PHP_VERSION_ID < 80500) {
    http_response_code(500);
    exit('Buscaminas requiere PHP 8.5 o superior. Versión actual: ' . PHP_VERSION);
}

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require __DIR__ . '/vendor/autoload.php';
} else {
    spl_autoload_register(static function (string $class): void {
        $prefix = 'Buscaminas\\';
        if (str_starts_with($class, $prefix)) {
            $file = __DIR__ . '/src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });
}

const BUSCAMINAS_STORAGE = __DIR__ . '/storage';
const BUSCAMINAS_TEMPLATES = __DIR__ . '/templates';
