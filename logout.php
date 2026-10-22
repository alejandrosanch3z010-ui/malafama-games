<?php
require_once 'includes/funciones.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $_SESSION = [];
    session_regenerate_id(true);
    flash('Cerraste sesión. ¡Vuelve pronto!', 'info');
}
redirigir('index.php');
