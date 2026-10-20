<?php
// =====================================================================
//  Panel de administración. La página solo arma la estructura: todos los
//  datos se consultan y se guardan a través de la API REST (js/admin.js).
//  Los permisos se revisan aquí y otra vez en cada endpoint de la API.
// =====================================================================
require_once 'includes/funciones.php';
requiereLogin();
$u = usuarioActual();
if (!puede('panel.ver', $u)) {
    http_response_code(403);
    $titulo = 'Sin permiso';
    require 'includes/header.php';
    echo '<div class="contenedor vacio"><h1>No tienes acceso al panel</h1><p>Esta sección es solo para el personal de la tienda.</p><a class="btn" href="index.php">Volver a la tienda</a></div>';
    require 'includes/footer.php';
    exit;
}

$secciones = [
    'dashboard'  => ['Dashboard', 'panel.ver', '▦'],
    'pedidos'    => ['Pedidos', 'pedidos.ver_todos', '🧾'],
    'juegos'     => ['Juegos', 'juegos.gestionar', '🎮'],
    'generos'    => ['Géneros', 'generos.gestionar', '🏷'],
    'inventario' => ['Inventario', 'inventario.ajustar', '📦'],
    'usuarios'   => ['Usuarios', 'usuarios.gestionar', '👥'],
    'tarjetas'   => ['Tarjetas PSN', 'tarjetas.gestionar', '💳'],
    'reportes'   => ['Reportes', 'reportes.ver', '📈'],
    'historial'  => ['Historial', 'historial.ver', '🕘'],
];
$permitidas = array_filter($secciones, fn($s) => puede($s[1], $u));
$s = isset($permitidas[$_GET['s'] ?? '']) ? $_GET['s'] : 'dashboard';
if (isset($secciones[$_GET['s'] ?? '']) && !isset($permitidas[$_GET['s']])) {
    flash('Tu rol no tiene acceso a esa sección.', 'error');
}

$permisos = [];
foreach (array_keys(PERMISOS) as $p) $permisos[$p] = puede($p, $u);

$titulo = $secciones[$s][0] . ' · Panel';
$scriptsExtra = ['js/vendor/chart.umd.js', 'js/admin.js?v=1'];
require 'includes/header.php';
?>
<div class="panel">
    <aside class="panel-menu" aria-label="Secciones del panel">
        <div class="panel-rol">
            <span class="avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($u['nombre'], 0, 1))) ?></span>
            <div><b><?= e($u['nombre']) ?></b><small><?= e(nombreRol($u['rol'])) ?></small></div>
        </div>
        <nav>
            <?php foreach ($permitidas as $clave => [$nombre, , $icono]): ?>
                <a href="admin.php?s=<?= $clave ?>" <?= $clave === $s ? 'class="activo" aria-current="page"' : '' ?>><span aria-hidden="true"><?= $icono ?></span><?= $nombre ?></a>
            <?php endforeach; ?>
        </nav>
    </aside>

    <section class="panel-contenido" id="panel" data-seccion="<?= $s ?>" aria-live="polite">
        <h1><?= e($secciones[$s][0]) ?></h1>
        <div id="panel-cuerpo"><div class="estado-carga"><div class="spinner" aria-hidden="true"></div><p>Cargando…</p></div></div>
    </section>
</div>

<!-- Ventana para formularios (crear/editar) -->
<dialog class="modal" id="modal" aria-labelledby="modal-titulo">
    <form method="dialog" class="modal-caja" id="modal-form" novalidate>
        <header><h2 id="modal-titulo">Editar</h2><button type="button" class="modal-cerrar" data-cerrar-modal aria-label="Cerrar">✕</button></header>
        <div class="modal-cuerpo" id="modal-cuerpo"></div>
        <footer><button type="button" class="btn btn-secundario" data-cerrar-modal>Cancelar</button><button type="submit" class="btn" id="modal-guardar">Guardar</button></footer>
    </form>
</dialog>

<script>window.PANEL = <?= json_encode(['rol' => $u['rol'], 'id' => (int)$u['id'], 'permisos' => $permisos, 'stockBajo' => STOCK_BAJO], JSON_UNESCAPED_UNICODE) ?>;</script>
<?php require 'includes/footer.php'; ?>
