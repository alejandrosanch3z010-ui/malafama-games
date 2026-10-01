<?php
// Sesión del cliente, protección de formularios (CSRF) y mensajes entre páginas
require_once __DIR__ . '/db.php';

// Las páginas cambian según tu sesión (carrito, cuenta): se pide al navegador no guardarlas en caché,
// así al usar "Atrás" o "Adelante" no enseña una versión vieja (por ejemplo, el carrito vacío).
if (!headers_sent()) header('Cache-Control: no-store, no-cache, must-revalidate');

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    session_start();
}
// Se prepara todo lo que se lee de la sesión y, en las páginas normales (GET), se libera enseguida.
// PHP bloquea la sesión mientras una página se está generando: si no se libera, abrir varias páginas
// seguidas hace que cada una espere a la anterior y el navegador se queda "cargando".
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$GLOBALS['flash_actual'] = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') session_write_close();

// Vuelve a abrir la sesión solo cuando hay que escribir en ella
function abrirSesion() {
    if (session_status() !== PHP_SESSION_ACTIVE) session_start();
}

// Cliente con sesión iniciada (o null)
function usuarioActual() {
    static $usuario = false;
    if ($usuario === false) {
        $usuario = null;
        if (!empty($_SESSION['usuario_id'])) {
            $st = db()->prepare('SELECT id, nombre, email, puntos, rol FROM usuarios WHERE id = ? AND activo = 1');
            $st->execute([$_SESSION['usuario_id']]);
            $usuario = $st->fetch() ?: null;
        }
    }
    return $usuario;
}

function redirigir($url) {
    header('Location: ' . $url);
    exit;
}

// Solo permite regresar a páginas de la propia tienda
function volverSeguro($url, $porDefecto = 'index.php') {
    $url = (string)$url;
    if ($url === '' || preg_match('~^(\w+:)?//|\\\\|[\r\n]~', $url)) return $porDefecto;
    return $url;
}

function requiereLogin() {
    if (!usuarioActual()) {
        flash('Inicia sesión para continuar.', 'info');
        redirigir('login.php?volver=' . urlencode($_SERVER['REQUEST_URI']));
    }
}

// Token CSRF: evita que otra página envíe formularios en nombre del cliente
function tokenCsrf() {
    return $_SESSION['csrf'];
}
function campoCsrf() {
    return '<input type="hidden" name="csrf" value="' . tokenCsrf() . '">';
}
function verificarCsrf() {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(400);
        exit('La sesión expiró. Regresa y vuelve a intentarlo.');
    }
}

// Mensajes de una sola vez (se muestran debajo de la barra)
function flash($texto, $tipo = 'ok') {
    abrirSesion();
    $_SESSION['flash'] = ['texto' => $texto, 'tipo' => $tipo];
}
function tomarFlash() {
    $f = $GLOBALS['flash_actual'];
    $GLOBALS['flash_actual'] = null;
    return $f;
}

// ---------- Lista de deseos ----------
function enDeseos($usuarioId, $juegoId) {
    $st = db()->prepare('SELECT 1 FROM wishlist WHERE usuario_id = ? AND juego_id = ?');
    $st->execute([$usuarioId, $juegoId]);
    return (bool)$st->fetchColumn();
}
function contarDeseos($usuarioId) {
    $st = db()->prepare('SELECT COUNT(*) FROM wishlist WHERE usuario_id = ?');
    $st->execute([$usuarioId]);
    return (int)$st->fetchColumn();
}

// ---------- Reseñas: recalcula promedio y total del juego ----------
function actualizarCalificacion($juegoId) {
    $st = db()->prepare('UPDATE juegos SET
            calificacion = COALESCE((SELECT ROUND(AVG(calificacion), 1) FROM resenas WHERE juego_id = :a), 0),
            total_resenas = (SELECT COUNT(*) FROM resenas WHERE juego_id = :b)
        WHERE id = :c');
    $st->execute([':a' => $juegoId, ':b' => $juegoId, ':c' => $juegoId]);
}
