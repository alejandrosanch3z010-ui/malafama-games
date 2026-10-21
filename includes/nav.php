<?php
// Barra de la tienda (estilo PlayStation Store). En el inicio va debajo del hero; en las demás páginas, hasta arriba.
$paginaActual = basename($_SERVER['PHP_SELF']);
$estadoActual = $_GET['estado'] ?? '';
$usuario = usuarioActual();
$totalDeseos = $usuario ? contarDeseos($usuario['id']) : 0;
$aquiMismo = $_SERVER['REQUEST_URI'] ?? 'index.php';
if (!function_exists("tabActiva")) { function tabActiva($condicion) { return $condicion ? 'class="activo" aria-current="page"' : ''; } }
?>
<header class="tienda-nav" id="tienda-nav">
    <div class="contenedor tienda-nav-fila">
        <a href="index.php" class="logo" aria-label="Mala Fama Games, inicio">
            <?= logoSvg() ?>
            <span class="logo-texto"><b>MALA FAMA</b><small>GAMES</small></span>
        </a>

        <nav class="tabs" aria-label="Secciones de la tienda">
            <a href="index.php#tienda" <?= tabActiva($paginaActual === 'index.php') ?>>Más reciente</a>
            <a href="index.php#colecciones">Colecciones</a>
            <a href="catalogo.php?estado=ofertas" <?= tabActiva($paginaActual === 'catalogo.php' && $estadoActual === 'ofertas') ?>>Ofertas</a>
            <a href="catalogo.php?estado=preventa" <?= tabActiva($paginaActual === 'catalogo.php' && $estadoActual === 'preventa') ?>>Preventas</a>
            <a href="tarjetas-psn.php" <?= tabActiva($paginaActual === 'tarjetas-psn.php') ?>>Tarjetas PSN</a>
            <a href="catalogo.php" <?= tabActiva($paginaActual === 'catalogo.php' && $estadoActual === '') ?>>Explorar</a>
        </nav>

        <div class="iconos">
            <button class="icono" id="buscar-btn" aria-label="Buscar" aria-expanded="false" aria-controls="buscador-tienda">
                <svg viewBox="0 0 24 24"><circle cx="10.5" cy="10.5" r="6.5"/><path d="M20 20l-4.6-4.6"/></svg>
            </button>
            <a class="icono" href="deseos.php" aria-label="Lista de deseos<?= $totalDeseos ? " ($totalDeseos)" : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M12 20.5s-7.5-4.6-9.2-9.3C1.6 7.8 3.9 4.5 7.3 4.5c2 0 3.6 1.1 4.7 2.8 1.1-1.7 2.7-2.8 4.7-2.8 3.4 0 5.7 3.3 4.5 6.7-1.7 4.7-9.2 9.3-9.2 9.3Z" class="lleno"/></svg>
                <?php if ($totalDeseos): ?><span class="burbuja"><?= $totalDeseos ?></span><?php endif; ?>
            </a>
            <a class="icono" href="carrito.php" aria-label="Carrito<?= piezasEnCarrito() ? ' (' . piezasEnCarrito() . ')' : '' ?>">
                <svg viewBox="0 0 24 24"><path d="M3 4h2.2l2.3 11h10.8l2-8H7"/><circle cx="9.5" cy="19" r="1.4" class="lleno"/><circle cx="17" cy="19" r="1.4" class="lleno"/></svg>
                <?php if (piezasEnCarrito()): ?><span class="burbuja cian"><?= piezasEnCarrito() ?></span><?php endif; ?>
            </a>
            <?php if ($usuario): ?>
                <details class="cuenta">
                    <summary class="icono avatar-nav" aria-label="Mi cuenta"><?= e(mb_strtoupper(mb_substr($usuario['nombre'], 0, 1))) ?></summary>
                    <div class="cuenta-menu">
                        <p><b><?= e($usuario['nombre']) ?></b><small><?= e($usuario['email']) ?></small><small class="pts-menu">★ <?= $usuario['puntos'] ?> puntos</small></p>
                        <?php if (puede('panel.ver', $usuario)): ?><a href="admin.php" class="enlace-panel">⚙ Panel de <?= e(nombreRol($usuario['rol'])) ?></a><?php endif; ?>
                        <a href="cuenta.php">Mi cuenta</a>
                        <a href="cuenta.php?s=pedidos">Mis pedidos</a>
                        <a href="cuenta.php?s=codigos">Mis códigos</a>
                        <a href="deseos.php">Mi lista de deseos</a>
                        <a href="opiniones.php">Opiniones de la tienda</a>
                        <form method="post" action="logout.php" data-api="auth/logout" data-metodo="POST"><?= campoCsrf() ?><button type="submit">Cerrar sesión</button></form>
                    </div>
                </details>
            <?php else: ?>
                <a class="icono" href="login.php?volver=<?= urlencode($aquiMismo) ?>" aria-label="Iniciar sesión">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path d="M4.5 21c0-4.2 3.4-6.6 7.5-6.6s7.5 2.4 7.5 6.6"/></svg>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <form class="buscador-tienda contenedor" id="buscador-tienda" action="catalogo.php" method="get" role="search" hidden>
        <input type="search" name="q" placeholder="Busca juegos, desarrolladores…" aria-label="Buscar juegos" value="<?= e($_GET['q'] ?? '') ?>">
    </form>
</header>
<?php if ($aviso = tomarFlash()): ?>
    <div class="flash flash-<?= e($aviso['tipo']) ?>" role="status"><div class="contenedor"><?= e($aviso['texto']) ?></div></div>
<?php endif; ?>
