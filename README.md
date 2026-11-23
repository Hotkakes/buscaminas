# 💣 Buscaminas en PHP 8.5

Réplica del **Buscaminas clásico de Windows** hecha con PHP 8.5. Toda la lógica del juego se ejecuta en el servidor; el navegador solo dibuja el tablero con HTML, CSS y JavaScript sin dependencias.

![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)
![License](https://img.shields.io/badge/licencia-MIT-green)
![Dependencias](https://img.shields.io/badge/dependencias-0-blue)

## Características

- Los tres niveles originales: **Principiante** (9×9, 10 minas), **Intermedio** (16×16, 40) y **Experto** (30×16, 99).
- **Campo personalizado** con los límites de Windows (alto 9–24, ancho 9–30, minas 10 hasta (ancho−1)·(alto−1)).
- **El primer clic nunca es una mina.**
- Destape en **cascada** de las zonas vacías.
- **Clic derecho**: bandera → `?` → nada. Las marcas `?` se pueden desactivar.
- **Acorde**: clic con ambos botones o con el botón central sobre un número.
- Contador de minas y cronómetro con **pantallas LED de 7 segmentos**, y la **carita** con sus cuatro expresiones.
- **Mejores tiempos** por nivel, guardados en el servidor.
- Aspecto clásico de Windows: ventana, menú *Juego / Ayuda*, relieves 3D y los colores originales de los números.
- Atajos: **F2** nueva partida, **F1** ayuda.
- Sin trampas: la posición de las minas no se envía al navegador hasta que acaba la partida.

## Requisitos

- **PHP 8.5** o superior, con las extensiones `session`, `json` y `mbstring`.
- Composer 2 (opcional, solo para ejecutar las pruebas).
- Un navegador actual (Chrome, Edge, Firefox o Safari).

## Instalación y uso

```bash
git clone https://github.com/<tu-usuario>/buscaminas.git
cd buscaminas/source

# Opcional: autoload de Composer y PHPUnit
composer install

# Iniciar el servidor de desarrollo
php -S localhost:8000 -t public public/index.php
# o bien
composer serve
```

Abre **http://localhost:8000** y a jugar.

> El juego funciona también sin `composer install`: `bootstrap.php` incluye un autoloader PSR-4 de respaldo.

### Producción

Configura `source/public` como raíz web y redirige todas las peticiones a `index.php`. La carpeta `source/storage` debe poder escribirse (allí se guardan los mejores tiempos).

**Nginx + PHP-FPM**

```nginx
root /var/www/buscaminas/source/public;
location / {
    try_files $uri /index.php$is_args$args;
}
location ~ \.php$ {
    include fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    fastcgi_pass unix:/run/php/php8.5-fpm.sock;
}
```

**Apache**: el fichero `source/public/.htaccess` ya incluye la regla de reescritura (requiere `mod_rewrite`).

## Cómo jugar

| Acción | Control |
|---|---|
| Destapar una casilla | Clic izquierdo |
| Poner bandera / `?` / quitar | Clic derecho |
| Destapar las vecinas de un número | Ambos botones o botón central |
| Nueva partida | Clic en la carita o **F2** |
| Cambiar de nivel, marcas, récords | Menú **Juego** |

Gana quien destapa todas las casillas sin mina. Cada número indica cuántas minas hay en las 8 casillas que lo rodean.

## Pruebas

```bash
cd source
composer install
composer test
```

Las pruebas cubren las reglas del dominio: primer clic seguro, cascada, banderas, acorde, victoria, derrota, cronómetro y límites del nivel personalizado.

## Estructura del proyecto

```
buscaminas/
├── docs/
│   ├── tecnologia.md      Stack tecnológico (PHP 8.5)
│   └── Arquitectura.md    Arquitectura, API y decisiones de diseño
└── source/
    ├── public/            Raíz web: index.php y assets (CSS, JS)
    ├── src/
    │   ├── Domain/        Reglas del juego
    │   ├── Application/   Casos de uso
    │   ├── Infrastructure/ Sesión y almacenamiento JSON
    │   └── Http/          Router, controlador, petición y respuesta
    ├── templates/         Plantilla HTML
    ├── storage/           Mejores tiempos
    └── tests/             Pruebas PHPUnit
```

Más detalle en [`docs/Arquitectura.md`](docs/Arquitectura.md) y [`docs/tecnologia.md`](docs/tecnologia.md).

## API

El cliente se comunica con el servidor mediante una pequeña API JSON (`/api/game`, `/api/game/reveal`, `/api/game/mark`, `/api/game/chord`, `/api/best-times`…). La referencia completa está en [`docs/Arquitectura.md`](docs/Arquitectura.md#api-rest).

## Contribuir

1. Haz un fork del repositorio.
2. Crea una rama: `git checkout -b mi-mejora`.
3. Asegúrate de que `composer test` pasa.
4. Abre un Pull Request.

## Licencia

Distribuido bajo la licencia MIT. Consulta [`LICENSE`](LICENSE).

*Buscaminas es un proyecto educativo inspirado en el juego clásico; no está afiliado a Microsoft.*
