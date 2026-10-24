<?php
$titulo = 'Inicio';
$conHero = true;
require 'includes/header.php';

$preventas = buscarJuegos(['estado' => 'preventa', 'orden' => 'novedades']);
$destacado = $preventas[0] ?? null;
$top10     = buscarJuegos(['orden' => 'calificacion'], 10);
$recientes = array_values(array_filter(buscarJuegos(['orden' => 'novedades']), fn($j) => !esPreventa($j['fecha_lanzamiento'])));
$novedades = array_slice($recientes, 0, 6);
$ofertas   = buscarJuegos(['estado' => 'ofertas', 'orden' => 'precio_asc']);
$todos     = buscarJuegos(['orden' => 'titulo']);
$generos   = db()->query('SELECT g.*, COUNT(j.id) AS total FROM generos g JOIN juegos j ON j.genero_id = g.id GROUP BY g.id ORDER BY total DESC, g.nombre')->fetchAll();
?>

<?php require 'includes/hero.php'; ?>
<script src="js/hero.js?v=2"></script>

<?php require 'includes/nav.php'; ?>

<div class="tienda" id="tienda">

    <?php if ($destacado): ?>
    <section class="contenedor destacado-sec">
        <p class="etiqueta-fila">Destacado</p>
        <a class="banner" href="juego.php?j=<?= urlencode($destacado['slug']) ?>" style="--tono:<?= ($destacado['id'] * 47) % 360 ?>">
            <div class="banner-arte"><?= portada($destacado, '', 'vertical') ?></div>
            <div class="banner-texto">
                <span class="chip-negro">Preventa</span>
                <h2><?= e($destacado['titulo']) ?></h2>
                <p>Sale el <?= fechaLarga($destacado['fecha_lanzamiento']) ?> · <?= dinero($destacado['precio_final']) ?> MXN</p>
                <div class="contador" data-fecha="<?= e($destacado['fecha_lanzamiento']) ?>"></div>
                <span class="btn-pastilla">Resérvalo ya</span>
            </div>
        </a>
    </section>
    <?php endif; ?>

    <div class="separador-neon contenedor"><?= letreroNeon('Top 10', 'cian') ?></div>
    <?= filaJuegos('Los 10 mejores juegos de Mala Fama', $top10, 'catalogo.php?orden=calificacion') ?>

    <?php if ($novedades): ?>
    <section class="contenedor fila">
        <div class="fila-cabecera"><h2>Novedades</h2><a href="catalogo.php?orden=novedades">Ver todo</a></div>
        <div class="mosaico">
            <?php foreach ($novedades as $i => $j) echo tarjetaJuego($j, in_array($i, [0, 3]) ? 'tj-grande' : ''); ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($preventas): ?>
    <div class="separador-neon contenedor"><?= letreroNeon('Próximamente', 'morado') ?></div>
    <?= filaJuegos('Preventas abiertas', $preventas, 'catalogo.php?estado=preventa') ?>
    <?php endif; ?>

    <?php if ($ofertas): ?>
    <div class="separador-neon contenedor"><?= letreroNeon('Ofertas', 'rosa') ?></div>
    <?= filaJuegos('Ofertas que podrían gustarte', $ofertas, 'catalogo.php?estado=ofertas') ?>
    <?php endif; ?>

    <!-- Muro de portadas en movimiento (tipo "ticker"): columnas infinitas inclinadas + títulos en marquesina -->
    <section class="muro" aria-labelledby="muro-titulo">
        <div class="muro-marquesinas" aria-hidden="true">
            <?php $cinta = implode(' ✦ ', array_column($todos, 'titulo')) . ' ✦ '; ?>
            <div class="marquesina"><span><?= e($cinta) ?></span><span><?= e($cinta) ?></span></div>
            <div class="marquesina inversa"><span><?= e($cinta) ?></span><span><?= e($cinta) ?></span></div>
        </div>

        <div class="muro-columnas">
            <?php for ($c = 0; $c < 6; $c++):
                $columna = array_values(array_filter($todos, fn($j, $i) => $i % 6 === $c, ARRAY_FILTER_USE_BOTH)); ?>
                <div class="columna <?= $c % 2 ? 'baja' : '' ?>" style="--vel:<?= 38 + $c * 7 ?>s">
                    <div class="columna-pista">
                        <?php for ($copia = 0; $copia < 2; $copia++): // la segunda copia hace que el ciclo no tenga cortes ?>
                            <?php foreach ($columna as $j): ?>
                                <a href="juego.php?j=<?= urlencode($j['slug']) ?>" class="muro-portada" <?= $copia ? 'aria-hidden="true" tabindex="-1"' : '' ?>>
                                    <?= portada($j, '', 'vertical') ?>
                                </a>
                            <?php endforeach; ?>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>

        <div class="muro-cta contenedor">
            <?= letreroNeon('Explora', 'cian') ?>
            <h2 id="muro-titulo"><?= count($todos) ?> juegos de PS5 en físico y digital</h2>
            <p>Pasa el mouse para detener el muro o toca una portada para ver el juego.</p>
            <a href="catalogo.php" class="btn">Ver todo el catálogo</a>
        </div>
    </section>

    <section class="contenedor fila" id="colecciones">
        <div class="separador-neon"><?= letreroNeon('Colecciones', 'amarillo') ?></div>
        <div class="fila-cabecera"><h2>Colecciones por género</h2></div>
        <div class="colecciones">
            <?php foreach ($generos as $i => $g):
                $st = db()->prepare('SELECT id, titulo, portada FROM juegos WHERE genero_id = ? ORDER BY calificacion DESC LIMIT 3');
                $st->execute([$g['id']]);
                $portadas = $st->fetchAll(); ?>
                <a class="coleccion <?= $i === 0 ? 'coleccion-grande' : '' ?>" href="catalogo.php?genero=<?= $g['id'] ?>" style="--tono:<?= ($g['id'] * 61 + 200) % 360 ?>">
                    <span class="coleccion-descubre">Descubre</span>
                    <div class="coleccion-portadas">
                        <?php foreach ($portadas as $p) echo portada($p, '', 'vertical'); ?>
                    </div>
                    <span class="coleccion-nombre"><?= e($g['nombre']) ?></span>
                    <span class="coleccion-total"><?= $g['total'] ?> <?= $g['total'] == 1 ? 'juego' : 'juegos' ?></span>
                </a>
            <?php endforeach; ?>
            <a class="coleccion coleccion-todas" href="catalogo.php">
                <span class="cuadros" aria-hidden="true"><i style="background:#e5202f"></i><i style="background:#f6c945"></i><i style="background:#2dd4d4"></i><i style="background:#2f7cf6"></i></span>
                <b>Todas las colecciones</b>
            </a>
        </div>
    </section>

    <section class="contenedor categorias" aria-label="Accesos rápidos">
        <a href="catalogo.php" class="categoria"><span class="ps5 grande">PS5</span><b>Todos los juegos</b></a>
        <a href="catalogo.php?estado=ofertas" class="categoria">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M3 12.5V4h8.5L21 13.5 12.5 22 3 12.5Z"/><circle cx="7.5" cy="8.5" r="1.6"/></svg><b>Descuentos</b>
        </a>
        <a href="catalogo.php?estado=preventa" class="categoria">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/></svg><b>Preventas</b>
        </a>
        <a href="catalogo.php?orden=calificacion" class="categoria">
            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m12 3 2.7 5.6 6.1.8-4.5 4.2 1.1 6-5.4-3-5.4 3 1.1-6-4.5-4.2 6.1-.8L12 3Z"/></svg><b>Mejor calificados</b>
        </a>
    </section>
</div>

<?php require 'includes/footer.php'; ?>
