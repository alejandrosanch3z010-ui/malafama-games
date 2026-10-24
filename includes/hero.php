<?php
// Datos del hero: juegos para el anillo, productos para la tienda de muestra y la preventa destacada
$anillo = buscarJuegos(['orden' => 'calificacion']);
$productosHero = buscarJuegos(['orden' => 'calificacion'], 4);
$preventaHero = buscarJuegos(['estado' => 'preventa', 'orden' => 'novedades'], 1)[0] ?? null;

$datosAnillo = array_map(fn($j) => [
    'titulo'   => $j['titulo'],
    'genero'   => $j['genero'],
    'precio'   => dinero($j['precio_final']),
    'normal'   => dinero($j['precio_normal']),
    'desc'     => $j['en_oferta'] ? round(100 - $j['precio_final'] * 100 / $j['precio_normal']) : 0,
    'preventa' => esPreventa($j['fecha_lanzamiento']),
    'fecha'    => fechaLarga($j['fecha_lanzamiento']),
    'portada'  => rutaPortada($j['portada'], 'vertical'),
    'tono'     => ($j['id'] * 47) % 360,
], $anillo);

$textoBadge = $preventaHero ? 'Preventas de PS5 ya abiertas' : 'Juegos de PS5 físicos y digitales';
?>
<section class="mf-hero" aria-label="Presentación de Mala Fama Games">
  <div class="canvas" id="mfCanvas">
    <div class="bg"></div>
    <div class="stars" id="stA"></div>
    <div class="stars" id="stB"></div>

    <!-- El nav va fuera de .stack para que no se mueva con el ajuste de alto en tablet -->
    <nav class="hnav" id="hnav" aria-label="Principal">
      <img class="mark" src="img/logo.svg" alt="">
      <a href="index.php" class="wm" aria-label="Mala Fama Games, inicio">
        <span class="kick">GAMES</span>
        <span class="name" id="wmName">MALA FAMA</span>
      </a>

      <button class="burger" id="burger" type="button" aria-label="Abrir menú" aria-expanded="false" aria-controls="navmenu">
        <span></span><span></span><span></span>
      </button>

      <div class="navmenu" id="navmenu">
        <div class="links" id="links">
          <a href="catalogo.php">Catálogo</a>
          <a href="catalogo.php?estado=novedades">Novedades</a>
          <a href="catalogo.php?estado=preventa">Preventas</a>
          <a href="catalogo.php?estado=ofertas">Ofertas</a>
          <a href="#colecciones">Colecciones</a>
        </div>
        <a href="catalogo.php" class="gbtn"><span id="ctaLabel">Ver catálogo</span></a>
      </div>
    </nav>

    <div class="stack">

      <div class="badge">
        <i><svg viewBox="5 1 14 22" preserveAspectRatio="none" aria-hidden="true"><path fill="rgba(16,112,152,.72)" stroke="rgba(190,236,255,.6)" stroke-width="1.6" stroke-linejoin="round" d="M13.9 1.6 5.5 13.6a.7.7 0 0 0 .6 1.1h4.2l-1 7.7a.7.7 0 0 0 1.25.55l8.3-12.1a.7.7 0 0 0-.6-1.1h-4.2l1-7.7a.7.7 0 0 0-1.25-.55Z"/></svg></i>
        <b id="badgeTxt"><?= e($textoBadge) ?></b>
      </div>

      <h1 class="h1-wrap">
        <span class="h1 l1" id="h1a">Juega sin mala fama</span>
        <span class="h1 l2" id="h1b">en PS5</span>
      </h1>
      <p class="sub s1" id="sub1"><b>Juegos de PS5 en físico <span class="nb">y digital</span></b>, preventas con contador,</p>
      <p class="sub s2" id="sub2">tarjetas PSN y puntos de recompensa en cada compra.</p>

      <a href="catalogo.php" class="gbtn cta2"><span id="vpLabel">Comprar</span></a>
    </div>

    <div class="showcase">
      <div class="ring" id="ring" aria-hidden="true"></div>

      <div class="browser" aria-label="Vista previa de la tienda">
        <div class="bar">
          <div class="dots"><i style="background:#ee5c62"></i><i style="background:#f6b719"></i><i style="background:#12c02f"></i></div>
          <div class="omni">
            <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.4"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.8-3.8"/></svg>
            <span>malafamacrew.com · Tienda PS5</span>
          </div>
          <div class="tools">
            <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M12 16V4m0 0L8 8m4-4 4 4"/><path d="M4 15v5h16v-5"/></svg>
            <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2.2"><path d="M12 5v14M5 12h14"/></svg>
            <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M12 3 3 8l9 5 9-5-9-5Z" fill="#fff" opacity=".95"/><path d="M3 13l9 5 9-5" opacity=".55"/></svg>
          </div>
        </div>

        <div class="page">
          <div class="ann"><u style="left:20px">&#8249;</u><span>Envío gratis en compras desde $999 · Paga en OXXO</span><u style="right:20px">&#8250;</u></div>
          <div class="shoplogo"><em>MALA FAMA</em><i>GAMES · PS5</i></div>
          <div class="shopicons">
            <svg viewBox="0 0 24 24" fill="none" stroke="#222" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="M20 20l-3.8-3.8"/></svg>
            <svg viewBox="0 0 24 24" fill="none" stroke="#222" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4.5 21c0-4.2 3.4-6.6 7.5-6.6s7.5 2.4 7.5 6.6"/></svg>
            <svg viewBox="0 0 24 24" fill="none" stroke="#222" stroke-width="2"><path d="M5.5 8h13l-1.2 12H6.7L5.5 8Z"/><path d="M9 8V6.2A3 3 0 0 1 15 6.2V8"/></svg>
          </div>

          <div class="pagebody">
            <?php if ($preventaHero): ?>
            <div class="pghero" style="--tono:<?= ($preventaHero['id'] * 47) % 360 ?>">
              <?php if ($preventaHero['portada']): ?><img src="<?= e($preventaHero['portada']) ?>" alt="<?= e($preventaHero['titulo']) ?>"><?php endif; ?>
              <div class="scrim"></div>
              <div class="copy">
                <u>Preventa · <?= fechaLarga($preventaHero['fecha_lanzamiento']) ?></u>
                <em><?= e($preventaHero['titulo']) ?><br>ya puedes apartarlo.</em>
                <i>APARTAR AHORA</i>
              </div>
            </div>
            <?php endif; ?>
            <div class="pgsec"><b>Mejor calificados</b><u>ver todos</u></div>
            <div class="pggrid">
              <?php foreach ($productosHero as $p): ?>
              <div class="pgcard">
                <div class="ph" style="--tono:<?= ($p['id'] * 47) % 360 ?>">
                  <?php if ($p['portada']): ?><img src="<?= e(rutaPortada($p['portada'])) ?>" alt="<?= e($p['titulo']) ?>"><?php else: ?><span class="ph-t"><?= e($p['titulo']) ?></span><?php endif; ?>
                  <?php if ($p['en_oferta']): ?><span class="tag">-<?= round(100 - $p['precio_final'] * 100 / $p['precio_normal']) ?>%</span><?php endif; ?>
                </div>
                <b><?= e($p['titulo']) ?></b>
                <i><?= e($p['genero']) ?> · <?= str_replace(['fisico', 'digital', ','], ['Físico', 'Digital', ' · '], $p['formatos']) ?></i>
                <s><?= dinero($p['precio_final']) ?><?php if ($p['en_oferta']): ?><span><?= dinero($p['precio_normal']) ?></span><?php endif; ?></s>
              </div>
              <?php endforeach; ?>
            </div>
            <div class="pgstrip">
              <span>Envío gratis desde $999</span>
              <span>OXXO, PayPal o tarjeta</span>
              <span>Códigos digitales al instante</span>
              <span>Puntos en cada compra</span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <a class="wa" href="#" aria-label="WhatsApp">
    <svg viewBox="0 0 32 32" aria-hidden="true"><path fill="#fff" d="M16 3C8.8 3 3 8.7 3 15.8c0 2.5.7 4.9 2 7L3.6 29l6.4-1.7c2 1.1 4.3 1.7 6.6 1.7 7.2 0 13-5.7 13-12.8S23.2 3 16 3Zm0 23.4c-2.1 0-4.1-.6-5.9-1.6l-.4-.2-3.8 1 1-3.7-.3-.4c-1.2-1.8-1.8-3.8-1.8-5.9C4.8 9.7 9.8 4.9 16 4.9s11.2 4.9 11.2 10.9S22.2 26.4 16 26.4Zm6.1-8.1c-.3-.2-2-1-2.3-1.1-.3-.1-.5-.2-.8.2-.2.3-.9 1.1-1.1 1.3-.2.2-.4.3-.7.1-.3-.2-1.4-.5-2.7-1.7-1-.9-1.7-2-1.9-2.3-.2-.3 0-.5.1-.7l.5-.6c.2-.2.2-.4.3-.6.1-.2 0-.5 0-.6l-1-2.5c-.3-.7-.6-.6-.8-.6h-.7c-.2 0-.6.1-.9.4-.3.3-1.2 1.1-1.2 2.8 0 1.6 1.2 3.2 1.4 3.4.2.2 2.4 3.6 5.8 5 .8.3 1.4.5 1.9.7.8.3 1.5.2 2.1.1.6-.1 2-.8 2.2-1.6.3-.8.3-1.5.2-1.6-.1-.1-.3-.2-.6-.4Z"/></svg>
  </a>

  <script>window.MF_ANILLO = <?= json_encode($datosAnillo, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;</script>
</section>
