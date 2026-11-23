<?php declare(strict_types=1); ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buscaminas</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'><circle cx='8' cy='8' r='5' fill='black'/></svg>">
    <link rel="stylesheet" href="/assets/css/buscaminas.css">
</head>
<body>
<div class="window" id="window">
    <div class="titlebar">
        <span class="titlebar__icon" aria-hidden="true"></span>
        <span class="titlebar__text">Buscaminas</span>
        <span class="titlebar__buttons" aria-hidden="true">
            <span class="tb-btn">_</span><span class="tb-btn">□</span><span class="tb-btn">×</span>
        </span>
    </div>

    <nav class="menubar" role="menubar">
        <div class="menu" data-menu>
            <button class="menu__title" type="button"><u>J</u>uego</button>
            <ul class="menu__list" role="menu">
                <li role="menuitem" data-action="restart"><span>Nuevo</span><kbd>F2</kbd></li>
                <li class="sep"></li>
                <li role="menuitemradio" data-action="level" data-level="beginner"><span>Principiante</span></li>
                <li role="menuitemradio" data-action="level" data-level="intermediate"><span>Intermedio</span></li>
                <li role="menuitemradio" data-action="level" data-level="expert"><span>Experto</span></li>
                <li role="menuitemradio" data-action="custom"><span>Personalizado...</span></li>
                <li class="sep"></li>
                <li role="menuitemcheckbox" data-action="marks"><span>Marcas (?)</span></li>
                <li class="sep"></li>
                <li role="menuitem" data-action="best"><span>Mejores tiempos...</span></li>
            </ul>
        </div>
        <div class="menu" data-menu>
            <button class="menu__title" type="button"><u>A</u>yuda</button>
            <ul class="menu__list" role="menu">
                <li role="menuitem" data-action="help"><span>Cómo jugar</span><kbd>F1</kbd></li>
                <li class="sep"></li>
                <li role="menuitem" data-action="about"><span>Acerca de Buscaminas...</span></li>
            </ul>
        </div>
    </nav>

    <main class="game bevel-out">
        <header class="panel bevel-in">
            <div class="led" id="mines-counter" aria-label="Minas restantes"></div>
            <button class="face bevel-out" id="face" type="button" aria-label="Nueva partida"></button>
            <div class="led" id="timer" aria-label="Tiempo"></div>
        </header>
        <section class="board bevel-in" id="board" aria-label="Tablero"></section>
    </main>
</div>

<!-- Diálogos al estilo Windows -->
<dialog class="dlg" id="dlg-custom">
    <form method="dialog">
        <div class="dlg__title">Campo personalizado</div>
        <div class="dlg__body dlg__body--custom">
            <div class="fields">
                <label>Alto: <input type="number" name="height" min="9" max="24" required></label>
                <label>Ancho: <input type="number" name="width" min="9" max="30" required></label>
                <label>Minas: <input type="number" name="mines" min="10" max="667" required></label>
            </div>
            <div class="buttons-col">
                <button class="btn" value="ok">Aceptar</button>
                <button class="btn" value="cancel" formnovalidate>Cancelar</button>
            </div>
        </div>
    </form>
</dialog>

<dialog class="dlg" id="dlg-best">
    <form method="dialog">
        <div class="dlg__title">Mejores tiempos</div>
        <div class="dlg__body">
            <table class="best" id="best-table"></table>
            <div class="buttons-row">
                <button class="btn" value="reset" id="best-reset" type="button">Restablecer marcadores</button>
                <button class="btn" value="ok">Aceptar</button>
            </div>
        </div>
    </form>
</dialog>

<dialog class="dlg" id="dlg-record">
    <form method="dialog">
        <div class="dlg__title">Buscaminas</div>
        <div class="dlg__body dlg__body--center">
            <p id="record-text">Ha conseguido el mejor tiempo.<br>Por favor, escriba su nombre.</p>
            <input type="text" name="name" maxlength="32" value="Anónimo">
            <div class="buttons-row buttons-row--center"><button class="btn" value="ok">Aceptar</button></div>
        </div>
    </form>
</dialog>

<dialog class="dlg" id="dlg-info">
    <form method="dialog">
        <div class="dlg__title" id="info-title">Buscaminas</div>
        <div class="dlg__body">
            <div id="info-body"></div>
            <div class="buttons-row buttons-row--center"><button class="btn" value="ok">Aceptar</button></div>
        </div>
    </form>
</dialog>

<script src="/assets/js/buscaminas.js" defer></script>
</body>
</html>
