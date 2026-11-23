# Arquitectura

El Buscaminas sigue una **arquitectura en capas (hexagonal ligera)**: el dominio con las reglas del juego está en el centro y no depende de nada externo; alrededor están los casos de uso, la persistencia y la capa HTTP.

```
┌─────────────────────────────────────────────────────────────────┐
│ Navegador                                                       │
│  templates/game.php + assets/css + assets/js                    │
│  (dibuja el tablero y envía jugadas por fetch/JSON)             │
└───────────────────────────────┬─────────────────────────────────┘
                                │  HTTP  /api/...
┌───────────────────────────────▼─────────────────────────────────┐
│ Http          public/index.php → Router → GameController        │
├─────────────────────────────────────────────────────────────────┤
│ Application   GameService  (casos de uso + vista JSON)          │
│               GameRepository / BestTimesRepository (puertos)    │
├─────────────────────────────────────────────────────────────────┤
│ Domain        Game · Board · Cell · BoardConfig                 │
│               Difficulty · GameStatus · CellState (enums)       │
└─────────────────────────────────────────────────────────────────┘
        ▲ implementan los puertos
┌───────┴─────────────────────────────────────────────────────────┐
│ Infrastructure  SessionGameRepository · JsonBestTimesRepository │
└─────────────────────────────────────────────────────────────────┘
```

**Regla de dependencias:** cada capa solo conoce a las capas interiores. `Domain` no sabe que existe HTTP, la sesión ni JSON.

## Estructura de carpetas

```
buscaminas/
├── README.md
├── LICENSE
├── .gitignore
├── docs/
│   ├── tecnologia.md          Stack tecnológico (PHP 8.5)
│   └── Arquitectura.md        Este documento
└── source/                    Código fuente
    ├── bootstrap.php          Comprobación de versión y autoload
    ├── composer.json
    ├── phpunit.xml
    ├── public/                Raíz web (lo único expuesto)
    │   ├── index.php          Front controller
    │   └── assets/
    │       ├── css/buscaminas.css
    │       └── js/buscaminas.js
    ├── src/
    │   ├── Domain/            Reglas del juego (PHP puro)
    │   ├── Application/       Casos de uso y puertos
    │   ├── Infrastructure/    Sesión y fichero JSON
    │   └── Http/              Petición, respuesta, router y controlador
    ├── templates/
    │   └── game.php           Ventana, menús y diálogos
    ├── storage/               Mejores tiempos (escribible)
    └── tests/                 Pruebas PHPUnit
```

## Capas

### 1. Domain (`src/Domain`)

Contiene **todas las reglas** del Buscaminas de Windows. Es PHP puro, sin E/S, y se prueba sin servidor.

| Clase | Responsabilidad |
|---|---|
| `Difficulty` (enum) | Niveles Principiante 9×9/10, Intermedio 16×16/40, Experto 30×16/99 y Personalizado. |
| `BoardConfig` (readonly) | Ancho, alto y minas. Valida los límites de Windows (ancho 9–30, alto 9–24, minas 10 – (ancho−1)·(alto−1)) y `clamped()` los ajusta como hace el diálogo original. |
| `Cell` | Mina sí/no, minas vecinas, estado (`CellState`) y si explotó. |
| `CellState` (enum) | Oculta, bandera, interrogación, destapada. Define el ciclo del clic derecho. |
| `Board` | Matriz de casillas. Coloca minas evitando el primer clic, destapa en cascada (flood fill iterativo), "acorde" y detección de victoria. |
| `GameStatus` (enum) | Ready, Playing, Won, Lost. |
| `Game` | Agregado raíz: tablero + estado + cronómetro. El tiempo se recibe como parámetro (`$now`) para que la partida sea serializable y las pruebas deterministas. |

Reglas replicadas del original:

- **El primer clic nunca es mina**: las minas se colocan después del primer clic.
- **Cascada**: una casilla con 0 minas vecinas destapa automáticamente sus 8 vecinas.
- **Clic derecho**: oculta → bandera → `?` → oculta (el `?` se puede desactivar en *Juego › Marcas*).
- **Acorde** (ambos botones o botón central sobre un número): si el número de banderas alrededor coincide, destapa el resto; si una bandera estaba mal, se pierde.
- **Victoria**: al destapar todas las casillas sin mina; se ponen banderas en todas las minas.
- **Derrota**: la mina pulsada se muestra en rojo, se descubren las demás y se tachan las banderas incorrectas.
- **Contador de minas** = minas − banderas (puede ser negativo). **Cronómetro** empieza en 1 y se detiene en 999.

### 2. Application (`src/Application`)

| Elemento | Responsabilidad |
|---|---|
| `GameService` | Casos de uso: nueva partida, reiniciar, destapar, marcar, acorde, marcas (?), mejores tiempos. Carga la partida, ejecuta la jugada, la guarda y devuelve la **vista** para el cliente. |
| `GameRepository` (interfaz) | Puerto para guardar/cargar la partida y la preferencia de marcas. |
| `BestTimesRepository` (interfaz) | Puerto para los mejores tiempos. |

`GameService` recibe un reloj (`Closure`) inyectable, de modo que el tiempo también se puede controlar en pruebas.

**Anti‑trampas:** la vista solo incluye la posición de las minas cuando la partida ha terminado, y el tiempo de un récord se calcula en el servidor, nunca se acepta del cliente.

### 3. Infrastructure (`src/Infrastructure`)

| Clase | Implementa | Detalle |
|---|---|---|
| `SessionGameRepository` | `GameRepository` | Guarda la partida serializada en `$_SESSION`. `unserialize()` usa una lista blanca de clases. Cookie `HttpOnly` y `SameSite=Strict`. |
| `JsonBestTimesRepository` | `BestTimesRepository` | Lee/escribe `storage/best-times.json` con bloqueo exclusivo (`flock`). |

Cambiar a MySQL, Redis, etc. solo requiere una nueva clase que implemente el puerto; el dominio no cambia.

### 4. Http (`src/Http`)

| Clase | Responsabilidad |
|---|---|
| `Request` | Petición inmutable creada desde las superglobales; valida enteros y sanea textos. |
| `JsonResponse` | Respuesta JSON con `Cache-Control: no-store`. |
| `Router` | Asocia `MÉTODO /ruta` a un manejador y convierte errores de validación en HTTP 422. |
| `GameController` | Registra las rutas de la API y llama a `GameService`. |

`public/index.php` es el **front controller**: sirve la plantilla HTML para `/` y despacha `/api/*` al router.

## API REST

Todas las respuestas son JSON. Las jugadas devuelven el estado completo del tablero.

| Método | Ruta | Cuerpo | Descripción |
|---|---|---|---|
| GET | `/api/game` | — | Estado de la partida actual (crea una de Principiante si no hay). |
| POST | `/api/game/new` | `{difficulty, width?, height?, mines?}` | Nueva partida. `difficulty`: `beginner`, `intermediate`, `expert`, `custom`. |
| POST | `/api/game/restart` | — | Nueva partida con el mismo nivel (carita / F2). |
| POST | `/api/game/reveal` | `{x, y}` | Clic izquierdo. |
| POST | `/api/game/mark` | `{x, y}` | Clic derecho. |
| POST | `/api/game/chord` | `{x, y}` | Ambos botones / botón central. |
| POST | `/api/settings/question-marks` | `{enabled}` | Activa o desactiva las marcas (?). |
| GET | `/api/best-times` | — | Mejores tiempos por nivel. |
| POST | `/api/best-times` | `{name}` | Guarda el récord de la partida ganada (si lo es). |
| POST | `/api/best-times/reset` | — | Restablece los marcadores. |

### Estado devuelto

```json
{
  "difficulty": "beginner",
  "width": 9, "height": 9, "mines": 10,
  "minesLeft": 9,
  "status": "playing",
  "elapsed": 12,
  "questionMarks": true,
  "newRecord": false,
  "rows": ["f1hhhhhhh", "11hhhhhhh", "..."]
}
```

Cada fila es una cadena con un carácter por casilla:

| Código | Significado |
|---|---|
| `h` | Oculta |
| `f` | Bandera |
| `q` | Interrogación |
| `0`–`8` | Destapada con ese número de minas vecinas |
| `m` | Mina (solo al perder) |
| `x` | Mina que explotó (fondo rojo) |
| `w` | Bandera equivocada (mina tachada) |

## Flujo de una jugada

```
Usuario suelta el clic izquierdo sobre (3,5)
  └─ buscaminas.js → POST /api/game/reveal {x:3, y:5}
      └─ index.php → Request::fromGlobals() → Router::dispatch()
          └─ GameController → GameService::reveal(3, 5)
              ├─ SessionGameRepository::load()      ← partida desde la sesión
              ├─ Game::reveal(3, 5, now)
              │    ├─ (1.er clic) Board::placeMines() + inicia cronómetro
              │    ├─ Board::reveal()                ← cascada si hay 0
              │    └─ comprueba victoria / derrota
              ├─ SessionGameRepository::save()
              └─ present()                          → DTO JSON
  ←─ JsonResponse  ←─ buscaminas.js repinta solo las casillas que cambiaron
```

## Cliente (JavaScript)

`public/assets/js/buscaminas.js` es un módulo sin dependencias que:

- Pinta el tablero, los contadores LED (SVG de 7 segmentos) y la carita (normal, "oh" al pulsar, gafas al ganar, muerta al perder).
- Replica el comportamiento del ratón del original: la casilla se ve hundida mientras se mantiene pulsado y la jugada se ejecuta al **soltar**; el clic derecho actúa al **pulsar**; con ambos botones se hunden las 8 vecinas.
- Encadena las peticiones en una cola para que lleguen al servidor en orden.
- Anima el cronómetro localmente y lo resincroniza con el servidor en cada respuesta.
- Implementa el menú *Juego* / *Ayuda*, el atajo **F2** y los diálogos *Personalizado*, *Mejores tiempos* y *nuevo récord*.

## Pruebas

`source/tests` contiene pruebas PHPUnit del dominio: cálculo de vecinas, primer clic seguro, cascada, banderas, ciclo del clic derecho, acorde (correcto y con bandera errónea), victoria, derrota, cronómetro, límites del nivel personalizado y serialización en sesión. Los tableros de prueba se crean con `Board::fromLayout()` y la aleatoriedad con `Random\Engine\Mt19937` con semilla fija.

## Decisiones de diseño

| Decisión | Motivo |
|---|---|
| Lógica en el servidor, cliente "tonto" | Es un proyecto PHP; además evita trampas (las minas no viajan al navegador). |
| Sesión en lugar de base de datos | Una partida es pequeña y por jugador; despliegue sin dependencias. |
| Sin framework | El dominio es pequeño; un router de 40 líneas es suficiente y más fácil de entender. |
| Flood fill iterativo | Evita desbordar la pila en tableros de 30×24. |
| Tiempo inyectado | Partida serializable y pruebas deterministas. |
| Autoloader de respaldo | El juego funciona sin ejecutar `composer install`. |
