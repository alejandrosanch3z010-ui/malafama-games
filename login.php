<?php
require_once 'includes/funciones.php';
if (usuarioActual()) redirigir('index.php');

$volver = volverSeguro($_POST['volver'] ?? $_GET['volver'] ?? '');
$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificarCsrf();
    $email = trim($_POST['email'] ?? '');
    $st = db()->prepare('SELECT id, nombre, password_hash, rol, activo FROM usuarios WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();

    if ($u && password_verify($_POST['password'] ?? '', $u['password_hash']) && !$u['activo']) {
        $error = 'Tu cuenta está desactivada. Contacta a la tienda.';
    } elseif ($u && password_verify($_POST['password'] ?? '', $u['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['usuario_id'] = $u['id'];
        flash('¡Qué onda, ' . explode(' ', $u['nombre'])[0] . '! Ya iniciaste sesión.');
        redirigir(in_array($u['rol'], ['admin', 'empleado'], true) ? 'admin.php' : $volver);
    } else {
        $error = 'El correo o la contraseña no coinciden.';
    }
}

$titulo = 'Iniciar sesión';
require 'includes/header.php';
?>
<div class="contenedor auth">
    <form class="auth-caja" method="post" novalidate data-api="auth/login" data-metodo="POST">
        <img src="img/logo.svg" alt="" width="64" height="64" class="auth-logo">
        <h1>Inicia sesión</h1>
        <p class="auth-sub">Guarda juegos en tu lista de deseos y deja reseñas.</p>

        <?php if ($error): ?><p class="alerta" role="alert"><?= e($error) ?></p><?php endif; ?>

        <?= campoCsrf() ?>
        <input type="hidden" name="volver" value="<?= e($volver) ?>">
        <label>Correo electrónico
            <input type="email" name="email" value="<?= e($email) ?>" autocomplete="email" required autofocus>
        </label>
        <label>Contraseña
            <input type="password" name="password" autocomplete="current-password" required>
        </label>
        <button class="btn btn-grande" type="submit">Entrar</button>
        <p class="auth-cambio">¿No tienes cuenta? <a href="registro.php?volver=<?= urlencode($volver) ?>">Regístrate gratis</a></p>
    </form>
</div>
<?php require 'includes/footer.php'; ?>
