<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/sesion.php';
require_once __DIR__ . '/logica-carrito.php';
require_once __DIR__ . '/negocio.php';

// Escapa texto para mostrarlo en HTML de forma segura
function e($texto) {
    return htmlspecialchars((string)$texto, ENT_QUOTES, 'UTF-8');
}

function dinero($cantidad) {
    return '$' . number_format((float)$cantidad, 2);
}

function fechaLarga($fecha) {
    $meses = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
    $t = strtotime($fecha);
    return date('j', $t) . ' de ' . $meses[date('n', $t) - 1] . ' de ' . date('Y', $t);
}

function esPreventa($fecha) {
    return $fecha > date('Y-m-d');
}

// Estado del inventario: preventa, agotado, pocas unidades o disponible
function estadoStock($fecha, $stock) {
    if (esPreventa($fecha)) return ['clase' => 'preventa', 'texto' => 'Preventa'];
    if ($stock <= 0)        return ['clase' => 'agotado', 'texto' => 'Agotado'];
    if ($stock <= 5)        return ['clase' => 'pocas', 'texto' => '¡Últimas ' . $stock . '!'];
    return ['clase' => 'disponible', 'texto' => 'Disponible'];
}

function estrellas($calificacion) {
    $llenas = (int)round($calificacion);
    return str_repeat('★', $llenas) . str_repeat('☆', 5 - $llenas);
}

function nombreFormato($formato) {
    return $formato === 'fisico' ? 'Físico' : 'Digital';
}

// Portada del juego o, si todavía no tiene imagen, un arte generado con su título
// Cada portada existe en dos formas: vertical (caja del juego) y cuadrada (tarjetas).
// En la base se guarda la vertical: img/portadas/juego.webp  →  cuadrada: img/portadas/juego-cuadrada.webp
function rutaPortada($ruta, $forma = 'cuadrada') {
    if (!$ruta) return null;
    return $forma === 'cuadrada' ? preg_replace('/\.webp$/', '-cuadrada.webp', $ruta) : $ruta;
}

function portada($juego, $clase = '', $forma = 'cuadrada') {
    if (!empty($juego['portada'])) {
        return '<img class="portada ' . ($forma === 'vertical' ? 'vertical ' : '') . $clase . '" src="' . e(rutaPortada($juego['portada'], $forma)) . '" alt="' . e($juego['titulo']) . '" loading="lazy">';
    }
    $tono = ($juego['id'] * 47) % 360;
    return '<div class="portada portada-vacia ' . $clase . '" style="--tono:' . $tono . '" role="img" aria-label="' . e($juego['titulo']) . '"><span>' . e($juego['titulo']) . '</span></div>';
}

function descuento($j) {
    return $j['en_oferta'] ? (int)round(100 - $j['precio_final'] * 100 / $j['precio_normal']) : 0;
}

// Busca juegos aplicando los filtros del catálogo
function buscarJuegos($f = [], $limite = null, $offset = 0, &$total = null) {
    // Solo juegos y géneros activos (baja lógica), salvo que el panel pida también los inactivos
    $where = empty($f['incluir_inactivos']) ? ['j.activo = 1', 'g.activo = 1'] : [];
    $params = [];
    $filtroFormato = '';

    if (!empty($f['formato']) && in_array($f['formato'], ['fisico', 'digital'])) {
        $filtroFormato = 'WHERE formato = :formato';
        $params[':formato'] = $f['formato'];
    }
    if (!empty($f['q'])) {
        $where[] = '(j.titulo LIKE :q1 OR j.desarrollador LIKE :q2)';
        $params[':q1'] = $params[':q2'] = '%' . $f['q'] . '%';
    }
    if (!empty($f['deseos_de'])) {
        $where[] = 'j.id IN (SELECT juego_id FROM wishlist WHERE usuario_id = :uid)';
        $params[':uid'] = (int)$f['deseos_de'];
    }
    if (isset($f['activo']) && $f['activo'] !== '') {
        $where[] = 'j.activo = :activo';
        $params[':activo'] = (int)$f['activo'];
    }
    if (!empty($f['genero'])) {
        $where[] = 'j.genero_id = :genero';
        $params[':genero'] = (int)$f['genero'];
    }
    if (!empty($f['precio_max'])) {
        $where[] = 'e.precio_final <= :pmax';
        $params[':pmax'] = (float)$f['precio_max'];
    }
    if (!empty($f['cal_min'])) {
        $where[] = 'j.calificacion >= :cal';
        $params[':cal'] = (float)$f['cal_min'];
    }
    $estado = $f['estado'] ?? '';
    if ($estado === 'preventa')  $where[] = 'j.fecha_lanzamiento > CURDATE()';
    if ($estado === 'novedades') $where[] = 'j.fecha_lanzamiento BETWEEN DATE_SUB(CURDATE(), INTERVAL 1 YEAR) AND CURDATE()';
    if ($estado === 'ofertas')   $where[] = 'e.en_oferta = 1';

    $ordenes = [
        'novedades'    => 'j.fecha_lanzamiento DESC',
        'precio_asc'   => 'e.precio_final ASC',
        'precio_desc'  => 'e.precio_final DESC',
        'calificacion' => 'j.calificacion DESC, j.total_resenas DESC',
        'titulo'       => 'j.titulo ASC',
    ];
    $orden = $ordenes[$f['orden'] ?? ''] ?? $ordenes['novedades'];

    // Por cada juego: precio más bajo, si tiene oferta, stock y formatos disponibles
    $sql = "SELECT j.*, g.nombre AS genero, e.precio_final, e.precio_normal, e.en_oferta, e.stock, e.formatos
            FROM juegos j
            JOIN generos g ON g.id = j.genero_id
            JOIN (
                SELECT juego_id,
                       MIN(COALESCE(precio_oferta, precio)) AS precio_final,
                       MIN(precio) AS precio_normal,
                       MAX(precio_oferta IS NOT NULL) AS en_oferta,
                       SUM(stock) AS stock,
                       GROUP_CONCAT(DISTINCT formato ORDER BY formato) AS formatos,
                       COUNT(DISTINCT version) AS versiones
                FROM ediciones $filtroFormato
                GROUP BY juego_id
            ) e ON e.juego_id = j.id";
    if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);

    // Total de resultados (para la paginación)
    if (func_num_args() >= 4) {
        $st = db()->prepare("SELECT COUNT(*) FROM ($sql) t");
        $st->execute($params);
        $total = (int)$st->fetchColumn();
    }

    $sql .= " ORDER BY $orden, j.id";
    if ($limite) $sql .= ' LIMIT ' . (int)$limite . ' OFFSET ' . (int)$offset;

    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

// Tarjeta de juego estilo PlayStation Store: arte cuadrado con el precio encima
function tarjetaJuego($j, $clase = '') {
    $estado = estadoStock($j['fecha_lanzamiento'], $j['stock']);
    $desc = descuento($j);
    $url = 'juego.php?j=' . urlencode($j['slug']);
    ob_start(); ?>
    <article class="tj <?= $clase ?>">
        <a href="<?= $url ?>" class="tj-enlace">
            <div class="tj-arte"><?= portada($j) ?></div>
            <span class="insignia <?= $estado['clase'] ?>"><?= $estado['texto'] ?></span>
            <div class="tj-info">
                <p class="tj-meta"><span class="ps5">PS5</span><?php if ($j['total_resenas'] > 0): ?><span class="tj-cal">★ <?= $j['calificacion'] ?></span><?php endif; ?></p>
                <h3><?= e($j['titulo']) ?></h3>
                <?php if (esPreventa($j['fecha_lanzamiento'])): ?>
                    <p class="tj-extra">Sale el <?= fechaLarga($j['fecha_lanzamiento']) ?></p>
                <?php endif; ?>
                <p class="tj-precio">
                    <?php if ($desc): ?><span class="desc">-<?= $desc ?>%</span><?php endif; ?>
                    <strong><?= dinero($j['precio_final']) ?> MXN</strong>
                    <?php if ($desc): ?><del><?= dinero($j['precio_normal']) ?> MXN</del><?php endif; ?>
                </p>
            </div>
        </a>
    </article>
    <?php return ob_get_clean();
}

// Fila horizontal de juegos (como las de la PlayStation Store)
function filaJuegos($titulo, $juegos, $enlace = null) {
    if (!$juegos) return '';
    ob_start(); ?>
    <section class="fila contenedor">
        <div class="fila-cabecera">
            <h2><?= e($titulo) ?></h2>
            <?php if ($enlace): ?><a href="<?= $enlace ?>">Ver todo</a><?php endif; ?>
            <div class="fila-flechas" aria-hidden="true">
                <button type="button" class="flecha" data-dir="-1" tabindex="-1">‹</button>
                <button type="button" class="flecha" data-dir="1" tabindex="-1">›</button>
            </div>
        </div>
        <div class="fila-pista">
            <?php foreach ($juegos as $j) echo tarjetaJuego($j); ?>
        </div>
    </section>
    <?php return ob_get_clean();
}

// Letrero de neón para títulos de sección
function letreroNeon($texto, $color = 'rosa') {
    return '<div class="neon neon-' . $color . '"><span>' . e($texto) . '</span></div>';
}

// Selector de 1 a 5 estrellas (radios accesibles con teclado)
function selectorEstrellas($actual = 0) {
    $html = '<fieldset class="selector-estrellas"><legend>Tu calificación</legend><div class="estrellas-input">';
    for ($i = 5; $i >= 1; $i--) {
        $html .= '<input type="radio" id="cal' . $i . '" name="calificacion" value="' . $i . '"' . ($actual == $i ? ' checked' : '') . ' required>'
               . '<label for="cal' . $i . '" title="' . $i . ' de 5">★<span class="visually-hidden">' . $i . ' estrellas</span></label>';
    }
    return $html . '</div></fieldset>';
}

// Reseña de un cliente (juego o tienda)
function tarjetaResena($r) {
    $inicial = mb_strtoupper(mb_substr($r['nombre'], 0, 1));
    ob_start(); ?>
    <article class="resena">
        <div class="resena-cabecera">
            <span class="avatar" aria-hidden="true"><?= e($inicial) ?></span>
            <div>
                <b><?= e($r['nombre']) ?></b>
                <small><?= fechaLarga($r['creado']) ?></small>
            </div>
            <span class="estrellas" aria-label="<?= $r['calificacion'] ?> de 5 estrellas"><?= estrellas($r['calificacion']) ?></span>
        </div>
        <?php if ($r['comentario']): ?><p><?= nl2br(e($r['comentario'])) ?></p><?php endif; ?>
    </article>
    <?php return ob_get_clean();
}

// Logo en línea (SVG) para que GSAP pueda "dibujar" el neón
function logoSvg() {
    return '<svg class="logo-svg" viewBox="0 0 64 64" width="40" height="40" aria-hidden="true">
        <defs><filter id="brillo-nav" x="-40%" y="-40%" width="180%" height="180%"><feGaussianBlur stdDeviation="1.6" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
        <linearGradient id="fondo-nav" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#1a1244"/><stop offset="1" stop-color="#0a0a22"/></linearGradient></defs>
        <rect x="3" y="3" width="58" height="58" rx="15" fill="url(#fondo-nav)"/>
        <rect class="tubo" x="7" y="7" width="50" height="50" rx="12" fill="none" stroke="#ff3fa4" stroke-width="2.6" filter="url(#brillo-nav)"/>
        <path class="tubo" d="M19 47V25l13 13 13-13v22" fill="none" stroke="#45f3ff" stroke-width="4.6" stroke-linecap="round" stroke-linejoin="round" filter="url(#brillo-nav)"/>
        <g fill="#45f3ff" filter="url(#brillo-nav)"><path class="cuerno" d="M16.6 27C13.4 21.5 13 15.8 16.2 11.2C17.2 16.2 19.4 20.3 22.4 23.6Z"/><path class="cuerno" d="M47.4 27C50.6 21.5 51 15.8 47.8 11.2C46.8 16.2 44.6 20.3 41.6 23.6Z"/></g>
    </svg>';
}

// "Deluxe · Digital", o solo "Digital" si es la versión Estándar
function nombreEdicion($ed) {
    $v = $ed['version'] ?? 'Estándar';
    return ($v !== 'Estándar' ? $v . ' · ' : '') . nombreFormato($ed['formato']);
}
