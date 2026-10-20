<?php
// ---------- Autenticación ----------
function usuarioPublico($u) {
    return ['id' => (int)$u['id'], 'nombre' => $u['nombre'], 'email' => $u['email'], 'rol' => $u['rol'],
            'rol_nombre' => nombreRol($u['rol']), 'puntos' => (int)$u['puntos']];
}

ruta('GET', 'auth/csrf', function () {
    responder(['csrf' => tokenCsrf()]);
});

ruta('GET', 'auth/yo', function () {
    responder(usuarioPublico(autenticado()));
});

ruta('POST', 'auth/login', function () {
    $d = entrada();
    validar($d, ['email' => ['requerido' => true, 'tipo' => 'email'], 'password' => ['requerido' => true]]);
    $st = db()->prepare('SELECT * FROM usuarios WHERE email = ?');
    $st->execute([trim($d['email'])]);
    $u = $st->fetch();
    if (!$u || !password_verify($d['password'], $u['password_hash'])) fallar(401, 'El correo o la contraseña no coinciden.');
    if (!$u['activo']) fallar(403, 'Tu cuenta está desactivada. Contacta a la tienda.');

    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $u['id'];
    $destino = in_array($u['rol'], ['admin', 'empleado'], true) ? 'admin.php' : volverSeguro($d['volver'] ?? '', 'index.php');
    responder(usuarioPublico($u), 200, '¡Qué onda, ' . explode(' ', $u['nombre'])[0] . '! Ya iniciaste sesión.', ['redirigir' => $destino]);
});

ruta('POST', 'auth/registro', function () {
    $d = entrada();
    validar($d, [
        'nombre' => ['requerido' => true, 'min' => 2, 'max' => 100],
        'email' => ['requerido' => true, 'tipo' => 'email', 'max' => 120],
        'password' => ['requerido' => true, 'min' => 8, 'max' => 72],
    ]);
    if (($d['password'] ?? '') !== ($d['password2'] ?? '')) fallar(422, 'Revisa los datos marcados.', ['password2' => 'Las contraseñas no coinciden.']);
    $st = db()->prepare('SELECT 1 FROM usuarios WHERE email = ?');
    $st->execute([trim($d['email'])]);
    if ($st->fetchColumn()) fallar(409, 'Ya existe una cuenta con ese correo.', ['email' => 'Ya existe una cuenta con ese correo.']);

    db()->prepare('INSERT INTO usuarios (nombre, email, password_hash) VALUES (?, ?, ?)')
        ->execute([trim($d['nombre']), trim($d['email']), password_hash($d['password'], PASSWORD_DEFAULT)]);
    $id = (int)db()->lastInsertId();
    registrarHistorial('crear', 'usuario', $id, null, ['email' => trim($d['email']), 'rol' => 'cliente'], $id);
    session_regenerate_id(true);
    $_SESSION['usuario_id'] = $id;
    responder(['id' => $id], 201, '¡Bienvenido a Mala Fama Games! Tu cuenta ya está lista.', ['redirigir' => volverSeguro($d['volver'] ?? '', 'index.php')]);
});

ruta('POST', 'auth/logout', function () {
    $_SESSION = [];
    session_regenerate_id(true);
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
    responder(null, 200, 'Cerraste sesión. ¡Vuelve pronto!', ['redirigir' => 'index.php']);
});

// ---------- Perfil del usuario con sesión ----------
ruta('PUT', 'perfil', function () {
    $u = autenticado();
    $d = entrada();
    validar($d, [
        'nombre' => ['requerido' => true, 'min' => 2, 'max' => 100],
        'email' => ['requerido' => true, 'tipo' => 'email', 'max' => 120],
        'direccion' => ['max' => 255],
    ]);
    $tel = preg_replace('/\D/', '', $d['telefono'] ?? '');
    if ($tel !== '' && strlen($tel) !== 10) fallar(422, 'Revisa los datos marcados.', ['telefono' => 'El teléfono debe tener 10 dígitos.']);
    $st = db()->prepare('SELECT 1 FROM usuarios WHERE email = ? AND id <> ?');
    $st->execute([trim($d['email']), $u['id']]);
    if ($st->fetchColumn()) fallar(409, 'Ese correo ya lo usa otra cuenta.', ['email' => 'Ese correo ya lo usa otra cuenta.']);
    db()->prepare('UPDATE usuarios SET nombre = ?, email = ?, telefono = ?, direccion = ? WHERE id = ?')
        ->execute([trim($d['nombre']), trim($d['email']), $tel ?: null, trim($d['direccion'] ?? '') ?: null, $u['id']]);
    responder(null, 200, 'Guardamos tus datos.');
});

ruta('PATCH', 'perfil/password', function () {
    $u = autenticado();
    $d = entrada();
    $st = db()->prepare('SELECT password_hash FROM usuarios WHERE id = ?');
    $st->execute([$u['id']]);
    $errores = [];
    if (!password_verify($d['actual'] ?? '', $st->fetchColumn())) $errores['actual'] = 'Tu contraseña actual no coincide.';
    if (strlen($d['nueva'] ?? '') < 8) $errores['nueva'] = 'La nueva contraseña debe tener al menos 8 caracteres.';
    if (($d['nueva'] ?? '') !== ($d['nueva2'] ?? '')) $errores['nueva2'] = 'Las contraseñas no coinciden.';
    if ($errores) fallar(422, 'Revisa los datos marcados.', $errores);
    db()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')->execute([password_hash($d['nueva'], PASSWORD_DEFAULT), $u['id']]);
    session_regenerate_id(true);
    responder(null, 200, 'Cambiaste tu contraseña.');
});
