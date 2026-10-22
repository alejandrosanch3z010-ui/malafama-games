</main>

<footer class="pie">
    <div class="contenedor pie-grid">
        <div>
            <a href="index.php" class="logo" aria-label="Mala Fama Games, inicio">
                <img src="img/logo.svg" alt="" width="40" height="40">
                <span class="logo-texto"><b>MALA FAMA</b><small>GAMES</small></span>
            </a>
            <p>Tu tienda de juegos de PlayStation 5, en físico y digital.</p>
        </div>
        <div>
            <h4>Pagos seguros</h4>
            <p class="metodos"><span>Tarjeta</span><span>PayPal</span><span>Mercado Pago</span><span>OXXO Pay</span></p>
            <p><a class="enlace-pie" href="opiniones.php">★ Opiniones de nuestros clientes</a></p>
        </div>
        <div>
            <h4>Ayuda y políticas</h4>
            <ul class="enlaces-pie">
                <li><a href="terminos.php">Términos y Condiciones</a></li>
                <li><a href="devoluciones.php">Política de Devoluciones</a></li>
                <li><a href="privacidad.php">Aviso de Privacidad</a></li>
                <li><a href="cuenta.php?s=pedidos">Seguimiento de pedidos</a></li>
            </ul>
            <p class="sitio-seguro">🔒 Sitio seguro con conexión cifrada (HTTPS)</p>
        </div>
    </div>
    <div class="contenedor pie-legal">
        <span>© <?= date('Y') ?> Mala Fama Games · Proyecto escolar del equipo Mala Fama Crew (ITESI)</span>
        <span>PlayStation y PS5 son marcas de Sony Interactive Entertainment. Los juegos pertenecen a sus respectivos dueños.</span>
    </div>
</footer>

<div class="aviso-flotante" id="aviso" role="status" hidden></div>
<script src="js/api.js?v=2"></script>
<script src="js/vendor/gsap.min.js"></script>
<script src="js/vendor/Flip.min.js"></script>
<script src="js/vendor/DrawSVGPlugin.min.js"></script>
<script src="js/vendor/ScrambleTextPlugin.min.js"></script>
<script src="js/animaciones.js?v=1"></script>
<?php foreach ($scriptsExtra ?? [] as $js) echo '<script src="' . e($js) . '"></script>' . PHP_EOL; ?>
<script src="js/main.js?v=8"></script>
</body>
</html>
