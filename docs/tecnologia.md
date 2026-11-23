# Tecnología

Este documento define el stack tecnológico del proyecto **Buscaminas**, una réplica del Buscaminas clásico de Windows.

## Lenguaje principal: PHP 8.5

El proyecto **debe ejecutarse con PHP 8.5 o superior**. Es un requisito obligatorio:

- `composer.json` declara `"php": ">=8.5"`, por lo que Composer no instalará el proyecto en versiones anteriores.
- `source/bootstrap.php` comprueba `PHP_VERSION_ID` al arrancar y detiene la aplicación con un mensaje claro si la versión es inferior a 8.5.

### Características de PHP que usa el proyecto

| Versión | Característica | Dónde se usa |
|---|---|---|
| **8.5** | Operador pipe `\|>` | `Http/Request.php`: normalización de la ruta y saneado de textos en una sola expresión. |
| **8.5** | Atributo `#[\NoDiscard]` | `Application/GameService.php`: avisa si se llama a una jugada (`reveal`, `chord`, `toggleMark`) sin usar el estado que devuelve. |
| 8.4 | `new` sin paréntesis | `public/index.php`: `new GameController($service)->register(...)`. |
| 8.4 | `array_any()` | `Domain/Board.php`: comprobar si queda alguna casilla segura por destapar. |
| 8.3 | Constantes de clase tipadas | `public const int MAX_WIDTH = 30;` |
| 8.2 | Clases `readonly` | `BoardConfig`, `Request`, `JsonResponse`, `GameController`. |
| 8.1 | Enums con valor | `Difficulty`, `GameStatus`, `CellState`. |
| 8.0 | `match`, argumentos con nombre, `throw` como expresión | En todo el dominio. |

También se usa la extensión **Random** (`Random\Randomizer`) para colocar las minas, lo que permite inyectar un motor con semilla fija en las pruebas.

### Extensiones de PHP necesarias

| Extensión | Para qué |
|---|---|
| `session` | Guardar la partida en curso de cada jugador. |
| `json` | API JSON y fichero de mejores tiempos (incluida siempre desde PHP 8.0). |
| `mbstring` | Recortar nombres con acentos en "Mejores tiempos". |
| `random` | Colocación aleatoria de minas (incluida siempre desde PHP 8.2). |

## Frontend

| Tecnología | Uso |
|---|---|
| HTML5 | Plantilla `templates/game.php` y diálogos nativos `<dialog>`. |
| CSS3 | Aspecto clásico de Windows: relieves 3D con bordes, colores originales de los números. Sin frameworks. |
| JavaScript (ES2020, sin dependencias) | Dibuja el estado recibido del servidor y gestiona el ratón (clic izquierdo, derecho, ambos botones y botón central). |
| SVG en línea | Contadores LED de 7 segmentos, carita, minas y banderas. No hay imágenes externas. |

El cliente **no contiene lógica de juego**: todas las reglas se ejecutan en PHP.

## Persistencia

| Dato | Almacenamiento |
|---|---|
| Partida en curso | Sesión PHP (`$_SESSION`), serializada con lista blanca de clases. |
| Preferencia "Marcas (?)" | Sesión PHP. |
| Mejores tiempos | Fichero `source/storage/best-times.json` con bloqueo `flock`. |

No se necesita base de datos.

## Herramientas de desarrollo

| Herramienta | Versión | Uso |
|---|---|---|
| Composer | 2.x | Autoload PSR-4 y dependencias de desarrollo (opcional para jugar). |
| PHPUnit | 12.4+ | Pruebas unitarias del dominio. |
| Servidor embebido de PHP | — | Desarrollo local: `php -S localhost:8000 -t public public/index.php`. |

## Servidores compatibles en producción

Cualquier servidor que ejecute PHP 8.5 con el directorio `source/public` como raíz web y todas las peticiones redirigidas a `index.php`:

- Apache con `mod_rewrite` o Nginx + PHP-FPM 8.5.
- Imagen Docker oficial `php:8.5-apache` o `php:8.5-fpm`.

## Navegadores soportados

Versiones actuales de Chrome, Edge, Firefox y Safari (se requiere soporte de `<dialog>`).
