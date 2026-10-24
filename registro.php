<?php
require_once 'includes/funciones.php';
if (usuarioActual()) redirigir('index.php');

$volver = volverSeguro($_POST['volver'] ?? $_GET['volver'] ?? '');
$datos = ['nombre' => '', 'email' => ''];
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $datos['nombre'] = trim($_POST['nombre'] ?? '');
    $datos['email'] = trim($_POST['email'] ?? '');
    $pass = $_POST['password'] ?? '';

    if (mb_strlen($datos['nombre']) < 2 || mb_strlen($datos['nombre']) > 100) $errores['nombre'] = 'Escribe tu nombre (de 2 a 100 letras).';
    if (!filter_var($datos['email'], FILTER_VALIDATE_EMAIL)) $errores['email'] = 'Escribe un correo válido.';
    if (strlen($pass) < 8) $errores['password'] = 'La contraseña debe tener al menos 8 caracteres.';
    if ($pass !== ($_POST['password2'] ?? '')) $errores['password2'] = 'Las contraseñas no coinciden.';

    if (!isset($errores['email'])) {
        $st = db()->prepare('SELECT 1 FROM usuarios WHERE email = ?');
        $st->execute([$datos['email']]);
        if ($st->fetchColumn()) $errores['email'] = 'Ya existe una cuenta con ese correo.';
    }

    if (!$errores) {
        $st = db()->prepare('INSERT INTO usuarios (nombre, email, password_hash) VALUES (?, ?, ?)');
        $st->execute([$datos['nombre'], $datos['email'], password_hash($pass, PASSWORD_DEFAULT)]);
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = (int)db()->lastInsertId();
        flash('¡Bienvenido a Mala Fama Games! Tu cuenta ya está lista.');
        redirigir($volver);
    }
}

function errorCampo($errores, $campo) {
    return isset($errores[$campo]) ? '<small class="error-campo">' . e($errores[$campo]) . '</small>' : '';
}

$titulo = 'Crear cuenta';
require 'includes/header.php';
?>
<div class="contenedor auth">
    <form class="auth-caja" method="post" novalidate data-api="auth/registro" data-metodo="POST">
        <img src="img/logo.svg" alt="" width="64" height="64" class="auth-logo">
        <h1>Crea tu cuenta</h1>
        <p class="auth-sub">Es gratis y solo toma un minuto.</p>

        <?= campoCsrf() ?>
        <input type="hidden" name="volver" value="<?= e($volver) ?>">
        <label>Nombre
            <input type="text" name="nombre" value="<?= e($datos['nombre']) ?>" autocomplete="name" maxlength="100" required>
            <?= errorCampo($errores, 'nombre') ?>
        </label>
        <label>Correo electrónico
            <input type="email" name="email" value="<?= e($datos['email']) ?>" autocomplete="email" maxlength="120" required>
            <?= errorCampo($errores, 'email') ?>
        </label>
        <label>Contraseña
            <input type="password" name="password" autocomplete="new-password" minlength="8" required>
            <?= errorCampo($errores, 'password') ?: '<small class="ayuda">Mínimo 8 caracteres.</small>' ?>
        </label>
        <label>Repite la contraseña
            <input type="password" name="password2" autocomplete="new-password" required>
            <?= errorCampo($errores, 'password2') ?>
        </label>
        <button class="btn btn-grande" type="submit">Crear cuenta</button>
        <p class="auth-cambio">¿Ya tienes cuenta? <a href="login.php?volver=<?= urlencode($volver) ?>">Inicia sesión</a></p>
    </form>
</div>
<?php require 'includes/footer.php'; ?>
