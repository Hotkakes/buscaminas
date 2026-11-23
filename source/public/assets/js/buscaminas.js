/*
 * Buscaminas — cliente.
 * Toda la lógica del juego vive en el servidor PHP; este script solo dibuja
 * el estado recibido y traduce los clics del ratón a llamadas a la API.
 */
'use strict';

(() => {
    // ---------------------------------------------------------------- Gráficos

    /** Segmentos a–g de un dígito de 7 segmentos (13×23). */
    const SEGMENTS = {
        a: '2,1 11,1 9,3 4,3',
        b: '12,2 12,11 10,10 10,4',
        c: '12,12 12,21 10,19 10,13',
        d: '2,22 11,22 9,20 4,20',
        e: '1,12 1,21 3,19 3,13',
        f: '1,2 1,11 3,10 3,4',
        g: '2,11.5 3.5,10 9.5,10 11,11.5 9.5,13 3.5,13',
    };
    const DIGITS = {
        0: 'abcdef', 1: 'bc', 2: 'abged', 3: 'abgcd', 4: 'fgbc',
        5: 'afgcd', 6: 'afgedc', 7: 'abc', 8: 'abcdefg', 9: 'abcdfg', '-': 'g',
    };

    const digitSvg = (char) => {
        const on = DIGITS[char] ?? '';
        const polys = Object.entries(SEGMENTS)
            .map(([seg, pts]) => `<polygon points="${pts}" fill="${on.includes(seg) ? '#ff0000' : '#2a0000'}"/>`)
            .join('');
        return `<svg viewBox="0 0 13 23" aria-hidden="true">${polys}</svg>`;
    };

    /** Formato del contador clásico: 3 dígitos, negativo hasta -99. */
    const ledText = (value) => {
        const v = Math.max(-99, Math.min(999, value));
        return v < 0 ? '-' + String(-v).padStart(2, '0') : String(v).padStart(3, '0');
    };

    const FACE_BASE = '<circle cx="8.5" cy="8.5" r="7.5" fill="#ffff00" stroke="#000" stroke-width="1"/>';
    const FACES = {
        smile: `${FACE_BASE}<rect x="5" y="5" width="2" height="2"/><rect x="10" y="5" width="2" height="2"/>
                <path d="M4.5 10.5 Q8.5 14.5 12.5 10.5" fill="none" stroke="#000" stroke-width="1.2"/>`,
        ooh: `${FACE_BASE}<rect x="5" y="5" width="2" height="2"/><rect x="10" y="5" width="2" height="2"/>
              <circle cx="8.5" cy="11.5" r="2" fill="none" stroke="#000" stroke-width="1.2"/>`,
        dead: `${FACE_BASE}<path d="M4.5 4.5l3 3m0-3l-3 3M9.5 4.5l3 3m0-3l-3 3" stroke="#000" stroke-width="1"/>
               <path d="M4.5 13 Q8.5 9 12.5 13" fill="none" stroke="#000" stroke-width="1.2"/>`,
        cool: `${FACE_BASE}<path d="M2 6h13" stroke="#000"/><path d="M3.5 5.5h4l-.5 2.5h-3zM9.5 5.5h4l-.5 2.5h-3z"/>
               <path d="M4.5 10.5 Q8.5 14.5 12.5 10.5" fill="none" stroke="#000" stroke-width="1.2"/>`,
    };
    const faceSvg = (name) => `<svg viewBox="0 0 17 17" aria-hidden="true">${FACES[name]}</svg>`;

    const MINE = `<svg viewBox="0 0 12 12" aria-hidden="true">
        <path d="M6 0v12M0 6h12M2 2l8 8M10 2l-8 8" stroke="#000" stroke-width="1.2"/>
        <circle cx="6" cy="6" r="3.6"/><rect x="4" y="4" width="1.6" height="1.6" fill="#fff"/></svg>`;
    const FLAG = `<svg viewBox="0 0 12 12" aria-hidden="true">
        <path d="M7 0.5v6L1.5 3.5z" fill="#ff0000"/><rect x="6.2" y="0.5" width="1.4" height="8.5"/>
        <rect x="3.5" y="8.5" width="6" height="1.3"/><rect x="1.5" y="9.8" width="10" height="1.7"/></svg>`;
    const WRONG = `<svg viewBox="0 0 12 12" aria-hidden="true">${MINE.replace(/<\/?svg[^>]*>/g, '')}
        <path d="M1 1l10 10M11 1L1 11" stroke="#ff0000" stroke-width="1.4"/></svg>`;

    // ---------------------------------------------------------------- Estado

    const $ = (sel) => document.querySelector(sel);
    const boardEl = $('#board');
    const faceEl = $('#face');
    const minesEl = $('#mines-counter');
    const timerEl = $('#timer');

    let game = null;
    let cells = [];          // HTMLElement[y][x]
    let mode = null;         // null | 'reveal' | 'chord'
    let leftHeld = false;
    let rightHeld = false;
    let pressed = [];        // casillas con efecto "presionado"
    let timerBase = 0;
    let timerStart = 0;
    let timerHandle = null;
    let queue = Promise.resolve();

    // ---------------------------------------------------------------- API

    const api = (path, body) => {
        const run = () => fetch(path, body === undefined
            ? { credentials: 'same-origin' }
            : { method: 'POST', credentials: 'same-origin', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
            .then(async (res) => {
                const data = await res.json();
                if (!res.ok) throw new Error(data.error ?? 'Error');
                return data;
            });
        // Las acciones se encadenan para que lleguen al servidor en orden.
        const p = queue.then(run, run);
        queue = p.catch(() => {});
        return p;
    };

    const act = (path, body) => api(path, body).then(applyState).catch((e) => console.error(e));

    // ---------------------------------------------------------------- Render

    function buildBoard(width, height) {
        boardEl.innerHTML = '';
        boardEl.style.gridTemplateColumns = `repeat(${width}, var(--cell))`;
        cells = [];
        for (let y = 0; y < height; y++) {
            const row = [];
            for (let x = 0; x < width; x++) {
                const el = document.createElement('div');
                el.dataset.x = x;
                el.dataset.y = y;
                boardEl.appendChild(el);
                row.push(el);
            }
            cells.push(row);
        }
    }

    function paintCell(el, code) {
        el.dataset.code = code;
        let cls = 'cell';
        let html = '';
        switch (code) {
            case 'h': cls += ' h'; break;
            case 'f': cls += ' f'; html = FLAG; break;
            case 'q': cls += ' q'; html = '?'; break;
            case 'm': cls += ' open'; html = MINE; break;
            case 'x': cls += ' open x'; html = MINE; break;
            case 'w': cls += ' open'; html = WRONG; break;
            default:
                cls += ' open n' + code;
                html = code === '0' ? '' : code;
        }
        el.className = cls;
        el.innerHTML = html;
    }

    function applyState(state) {
        const resized = !game || game.width !== state.width || game.height !== state.height;
        const previous = game;
        game = state;
        if (resized) buildBoard(state.width, state.height);

        state.rows.forEach((row, y) => {
            for (let x = 0; x < row.length; x++) {
                if (cells[y][x].dataset.code !== row[x]) paintCell(cells[y][x], row[x]);
            }
        });

        minesEl.innerHTML = [...ledText(state.minesLeft)].map(digitSvg).join('');
        setFace();
        syncTimer(state);
        syncMenu();

        const justWon = state.status === 'won' && previous && previous.status !== 'won';
        if (justWon && state.newRecord) setTimeout(askRecordName, 50);
    }

    function setFace(name) {
        const byStatus = { won: 'cool', lost: 'dead' };
        faceEl.innerHTML = faceSvg(name ?? byStatus[game?.status] ?? 'smile');
    }

    // ---------------------------------------------------------------- Cronómetro

    function renderTimer(value) {
        timerEl.innerHTML = [...ledText(value)].map(digitSvg).join('');
    }

    function syncTimer(state) {
        clearInterval(timerHandle);
        timerHandle = null;
        renderTimer(state.elapsed);
        if (state.status !== 'playing') return;

        timerBase = state.elapsed;
        timerStart = performance.now();
        timerHandle = setInterval(() => {
            const value = Math.min(999, timerBase + Math.floor((performance.now() - timerStart) / 1000));
            renderTimer(value);
        }, 250);
    }

    // ---------------------------------------------------------------- Ratón

    const isOver = () => game && (game.status === 'won' || game.status === 'lost');
    const cellAt = (target) => target?.closest?.('#board .cell') ?? null;
    const coords = (el) => ({ x: Number(el.dataset.x), y: Number(el.dataset.y) });
    const pressable = (el) => el && ['h', 'q'].includes(el.dataset.code);

    function clearPressed() {
        pressed.forEach((el) => {
            el.classList.remove('pressed');
            el.classList.add(el.dataset.code);
            if (el.dataset.code === 'q') el.innerHTML = '?';
        });
        pressed = [];
    }

    function pressCell(el) {
        if (!pressable(el)) return;
        el.classList.remove('h', 'q');
        el.classList.add('pressed');
        if (el.dataset.code === 'q') el.innerHTML = '?';
        pressed.push(el);
    }

    function updatePreview(target) {
        clearPressed();
        const el = cellAt(target);
        if (!el || !mode) return;
        if (mode === 'reveal') {
            pressCell(el);
            return;
        }
        // Acorde: presiona la casilla y sus 8 vecinas.
        const { x, y } = coords(el);
        for (let dy = -1; dy <= 1; dy++) {
            for (let dx = -1; dx <= 1; dx++) {
                pressCell(cells[y + dy]?.[x + dx]);
            }
        }
    }

    boardEl.addEventListener('contextmenu', (e) => e.preventDefault());

    boardEl.addEventListener('mousedown', (e) => {
        e.preventDefault();
        if (e.button === 0) leftHeld = true;
        if (e.button === 2) rightHeld = true;
        if (isOver()) return;

        const el = cellAt(e.target);
        if (!el) return;

        if (e.button === 1 || (leftHeld && rightHeld)) {
            mode = 'chord';
        } else if (e.button === 0) {
            mode = 'reveal';
        } else if (e.button === 2) {
            // Clic derecho: bandera → ? → nada (se aplica al pulsar, como en Windows).
            if (!el.classList.contains('open')) act('/api/game/mark', coords(el));
            return;
        }
        setFace('ooh');
        updatePreview(e.target);
    });

    document.addEventListener('mousemove', (e) => {
        if (mode) updatePreview(e.target);
    });

    document.addEventListener('mouseup', (e) => {
        if (e.button === 0) leftHeld = false;
        if (e.button === 2) rightHeld = false;
        if (!mode) return;

        const el = cellAt(e.target);
        const current = mode;
        mode = null;
        clearPressed();
        setFace();

        if (!el) return;
        if (current === 'chord') {
            act('/api/game/chord', coords(el));
        } else if (current === 'reveal' && e.button === 0 && pressable(el)) {
            act('/api/game/reveal', coords(el));
        }
    });

    // Al salir de la ventana con un botón pulsado se cancela la jugada.
    window.addEventListener('blur', () => {
        mode = null; leftHeld = rightHeld = false;
        clearPressed();
        if (game) setFace();
    });

    // ---------------------------------------------------------------- Carita

    faceEl.addEventListener('mousedown', (e) => {
        if (e.button !== 0) return;
        faceEl.classList.add('pressed');
        const up = (ev) => {
            faceEl.classList.remove('pressed');
            document.removeEventListener('mouseup', up);
            if (ev.target.closest('#face')) restart();
        };
        document.addEventListener('mouseup', up);
    });

    const restart = () => act('/api/game/restart', {});

    // ---------------------------------------------------------------- Menús

    const menus = [...document.querySelectorAll('[data-menu]')];
    const closeMenus = () => menus.forEach((m) => m.classList.remove('open'));

    menus.forEach((menu) => {
        const title = menu.querySelector('.menu__title');
        title.addEventListener('mousedown', (e) => {
            e.stopPropagation();
            const wasOpen = menu.classList.contains('open');
            closeMenus();
            if (!wasOpen) menu.classList.add('open');
        });
        title.addEventListener('mouseenter', () => {
            if (menus.some((m) => m.classList.contains('open'))) {
                closeMenus();
                menu.classList.add('open');
            }
        });
        menu.querySelector('.menu__list').addEventListener('mousedown', (e) => e.stopPropagation());
    });
    document.addEventListener('mousedown', closeMenus);

    document.querySelectorAll('[data-action]').forEach((item) => {
        item.addEventListener('click', () => {
            closeMenus();
            const action = item.dataset.action;
            if (action === 'restart') restart();
            if (action === 'level') act('/api/game/new', { difficulty: item.dataset.level });
            if (action === 'custom') openCustom();
            if (action === 'marks') act('/api/settings/question-marks', { enabled: !game.questionMarks });
            if (action === 'best') openBestTimes();
            if (action === 'help') openHelp();
            if (action === 'about') openAbout();
        });
    });

    function syncMenu() {
        document.querySelectorAll('[data-action="level"]').forEach((li) => {
            li.classList.toggle('checked', li.dataset.level === game.difficulty);
        });
        document.querySelector('[data-action="custom"]').classList.toggle('checked', game.difficulty === 'custom');
        document.querySelector('[data-action="marks"]').classList.toggle('checked', game.questionMarks);
    }

    // ---------------------------------------------------------------- Teclado

    document.addEventListener('keydown', (e) => {
        if (document.querySelector('dialog[open]')) return;
        if (e.key === 'F2') { e.preventDefault(); restart(); }
        if (e.key === 'F1') { e.preventDefault(); openHelp(); }
        if (e.key === 'Escape') closeMenus();
    });

    // ---------------------------------------------------------------- Diálogos

    function openCustom() {
        const dlg = $('#dlg-custom');
        const form = dlg.querySelector('form');
        form.height.value = game.height;
        form.width.value = game.width;
        form.mines.value = game.mines;
        dlg.returnValue = '';
        dlg.showModal();
        dlg.addEventListener('close', () => {
            if (dlg.returnValue !== 'ok') return;
            act('/api/game/new', {
                difficulty: 'custom',
                width: parseInt(form.width.value, 10) || 9,
                height: parseInt(form.height.value, 10) || 9,
                mines: parseInt(form.mines.value, 10) || 10,
            });
        }, { once: true });
    }

    function renderBestTimes(times) {
        const table = $('#best-table');
        table.innerHTML = '';
        Object.values(times).forEach((t) => {
            const tr = table.insertRow();
            tr.insertCell().textContent = `${t.label}:`;
            tr.insertCell().textContent = `${t.seconds} segundos`;
            tr.insertCell().textContent = t.name;
        });
    }

    function openBestTimes(times) {
        const dlg = $('#dlg-best');
        const show = (data) => { renderBestTimes(data); if (!dlg.open) dlg.showModal(); };
        times ? show(times) : api('/api/best-times').then(show);
    }

    $('#best-reset').addEventListener('click', () => {
        api('/api/best-times/reset', {}).then(renderBestTimes);
    });

    function askRecordName() {
        const dlg = $('#dlg-record');
        const labels = { beginner: 'principiante', intermediate: 'intermedio', expert: 'experto' };
        $('#record-text').innerHTML =
            `Ha conseguido el mejor tiempo<br>para el nivel ${labels[game.difficulty]}.<br>Por favor, escriba su nombre.`;
        const input = dlg.querySelector('input');
        dlg.showModal();
        input.select();
        dlg.addEventListener('close', () => {
            api('/api/best-times', { name: input.value }).then(openBestTimes);
        }, { once: true });
    }

    function info(title, html) {
        $('#info-title').textContent = title;
        $('#info-body').innerHTML = html;
        $('#dlg-info').showModal();
    }

    const openHelp = () => info('Cómo jugar', `
        <ul>
            <li><b>Clic izquierdo</b>: destapar una casilla.</li>
            <li><b>Clic derecho</b>: bandera → interrogación → nada.</li>
            <li><b>Ambos botones</b> o <b>botón central</b> sobre un número:
                destapa las vecinas si ya hay tantas banderas como indica.</li>
            <li><b>F2</b> o clic en la carita: nueva partida.</li>
        </ul>
        <p>Destapa todas las casillas sin mina para ganar. El primer clic nunca es una mina.</p>`);

    const openAbout = () => info('Acerca de Buscaminas', `
        <p><b>Buscaminas</b><br>Réplica del juego clásico de Windows.<br>
        Lógica en PHP 8.5 · Interfaz en HTML, CSS y JavaScript.</p>`);

    // ---------------------------------------------------------------- Inicio

    api('/api/game').then(applyState).catch((e) => console.error(e));
})();
