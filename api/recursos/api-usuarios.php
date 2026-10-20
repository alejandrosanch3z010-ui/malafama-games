<?php
// ---------- Usuarios (CRUD, solo administrador) ----------
function usuarioAdmin($id) {
    $st = db()->prepare("SELECT u.id, u.nombre, u.email, u.rol, u.telefono, u.direccion, u.puntos, u.activo, u.creado,
                                (SELECT COUNT(*) FROM pedidos p WHERE p.usuario_id = u.id AND p.estado <> 'cancelado') AS pedidos,
                                (SELECT COALESCE(SUM(total), 0) FROM pedidos p WHERE p.usuario_id = u.id AND p.estado <> 'cancelado') AS gastado
                         FROM usuarios u WHERE u.id = ?");
    $st->execute([$id]);
    if (!$u = $st->fetch()) fallar(404, 'El usuario no existe.');
    return formatoUsuario($u);
}
function formatoUsuario($u) {
    return ['id' => (int)$u['id'], 'nombre' => $u['nombre'], 'email' => $u['email'], 'rol' => $u['rol'], 'rol_nombre' => nombreRol($u['rol']),
            'telefono' => $u['telefono'], 'direccion' => $u['direccion'], 'puntos' => (int)$u['puntos'], 'activo' => (bool)$u['activo'],
            'creado' => $u['creado'], 'pedidos' => (int)($u['pedidos'] ?? 0), 'gastado' => (float)($u['gastado'] ?? 0)];
}

ruta('GET', 'usuarios', function () {
    conPermiso('usuarios.gestionar');
    [$pagina, $limite, $offset] = paginacion(15);
    $where = []; $params = [];
    if (!empty($_GET['q'])) { $where[] = '(u.nombre LIKE ? OR u.email LIKE ?)'; $params[] = '%' . $_GET['q'] . '%'; $params[] = '%' . $_GET['q'] . '%'; }
    if (!empty($_GET['rol'])) { $where[] = 'u.rol = ?'; $params[] = $_GET['rol']; }
    if (isset($_GET['activo']) && $_GET['activo'] !== '') { $where[] = 'u.activo = ?'; $params[] = (int)$_GET['activo']; }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $ordenes = ['nombre' => 'u.nombre ASC', 'nombre_desc' => 'u.nombre DESC', 'recientes' => 'u.creado DESC, u.id DESC', 'gastado' => 'gastado DESC'];
    $orden = $ordenes[$_GET['orden'] ?? ''] ?? $ordenes['nombre'];

    $st = db()->prepare("SELECT COUNT(*) FROM usuarios u $w");
    $st->execute($params);
    $total = (int)$st->fetchColumn();
    $st = db()->prepare("SELECT u.*, (SELECT COUNT(*) FROM pedidos p WHERE p.usuario_id = u.id AND p.estado <> 'cancelado') AS pedidos,
                                (SELECT COALESCE(SUM(total), 0) FROM pedidos p WHERE p.usuario_id = u.id AND p.estado <> 'cancelado') AS gastado
                         FROM usuarios u $w ORDER BY $orden LIMIT $limite OFFSET $offset");
    $st->execute($params);
    responderPagina(array_map('formatoUsuario', $st->fetchAll()), $total, $pagina, $limite);
});

ruta('GET', 'usuarios/{id}', function ($p) {
    conPermiso('usuarios.gestionar');
    responder(usuarioAdmin(soloEntero($p['id'])));
});

ruta('POST', 'usuarios', function () {
    conPermiso('usuarios.gestionar');
    $d = entrada();
    validar($d, [
        'nombre' => ['requerido' => true, 'min' => 2, 'max' => 100],
        'email' => ['requerido' => true, 'tipo' => 'email', 'max' => 120],
        'password' => ['requerido' => true, 'min' => 8, 'max' => 72],
        'rol' => ['requerido' => true, 'en' => ['admin', 'empleado', 'cliente']],
    ]);
    $st = db()->prepare('SELECT 1 FROM usuarios WHERE email = ?');
    $st->execute([trim($d['email'])]);
    if ($st->fetchColumn()) fallar(409, 'Ya existe una cuenta con ese correo.', ['email' => 'Ya existe una cuenta con ese correo.']);
    db()->prepare('INSERT INTO usuarios (nombre, email, password_hash, rol) VALUES (?, ?, ?, ?)')
        ->execute([trim($d['nombre']), trim($d['email']), password_hash($d['password'], PASSWORD_DEFAULT), $d['rol']]);
    $id = (int)db()->lastInsertId();
    registrarHistorial('crear', 'usuario', $id, null, ['email' => trim($d['email']), 'rol' => $d['rol']]);
    responder(usuarioAdmin($id), 201, 'Usuario creado.');
});

ruta('PUT', 'usuarios/{id}', function ($p) {
    $admin = conPermiso('usuarios.gestionar');
    $id = soloEntero($p['id']);
    $antes = usuarioAdmin($id);
    $d = entrada();
    validar($d, [
        'nombre' => ['requerido' => true, 'min' => 2, 'max' => 100],
        'email' => ['requerido' => true, 'tipo' => 'email', 'max' => 120],
        'rol' => ['requerido' => true, 'en' => ['admin', 'empleado', 'cliente']],
        'direccion' => ['max' => 255],
    ]);
    if ($id === $admin['id'] && $d['rol'] !== 'admin') fallar(409, 'No puedes quitarte a ti mismo el rol de administrador.');
    $st = db()->prepare('SELECT 1 FROM usuarios WHERE email = ? AND id <> ?');
    $st->execute([trim($d['email']), $id]);
    if ($st->fetchColumn()) fallar(409, 'Ese correo ya lo usa otra cuenta.', ['email' => 'Ya lo usa otra cuenta.']);
    $tel = preg_replace('/\D/', '', $d['telefono'] ?? '');
    if ($tel !== '' && strlen($tel) !== 10) fallar(422, 'Revisa los datos marcados.', ['telefono' => 'El teléfono debe tener 10 dígitos.']);
    db()->prepare('UPDATE usuarios SET nombre = ?, email = ?, rol = ?, telefono = ?, direccion = ? WHERE id = ?')
        ->execute([trim($d['nombre']), trim($d['email']), $d['rol'], $tel ?: null, trim($d['direccion'] ?? '') ?: null, $id]);
    registrarHistorial('editar', 'usuario', $id, ['nombre' => $antes['nombre'], 'email' => $antes['email'], 'rol' => $antes['rol']],
                       ['nombre' => trim($d['nombre']), 'email' => trim($d['email']), 'rol' => $d['rol']]);
    responder(usuarioAdmin($id), 200, 'Usuario actualizado.');
});

// PATCH: cambios parciales de rol, estado o contraseña
ruta('PATCH', 'usuarios/{id}', function ($p) {
    $admin = conPermiso('usuarios.gestionar');
    $id = soloEntero($p['id']);
    $antes = usuarioAdmin($id);
    $d = entrada();
    $cambios = [];
    if (array_key_exists('rol', $d)) {
        validar($d, ['rol' => ['en' => ['admin', 'empleado', 'cliente']]]);
        if ($id === $admin['id'] && $d['rol'] !== 'admin') fallar(409, 'No puedes quitarte a ti mismo el rol de administrador.');
        db()->prepare('UPDATE usuarios SET rol = ? WHERE id = ?')->execute([$d['rol'], $id]);
        $cambios['rol'] = $d['rol'];
    }
    if (array_key_exists('activo', $d)) {
        $activo = filter_var($d['activo'], FILTER_VALIDATE_BOOLEAN);
        if ($id === $admin['id'] && !$activo) fallar(409, 'No puedes desactivar tu propia cuenta.');
        db()->prepare('UPDATE usuarios SET activo = ? WHERE id = ?')->execute([$activo ? 1 : 0, $id]);
        $cambios['activo'] = $activo;
    }
    if (array_key_exists('password', $d)) {
        if (strlen($d['password']) < 8) fallar(422, 'Revisa los datos marcados.', ['password' => 'Mínimo 8 caracteres.']);
        db()->prepare('UPDATE usuarios SET password_hash = ? WHERE id = ?')->execute([password_hash($d['password'], PASSWORD_DEFAULT), $id]);
        $cambios['password'] = 'restablecida';
    }
    if (!$cambios) fallar(400, 'No se indicó ningún cambio (rol, activo o password).');
    registrarHistorial('editar', 'usuario', $id, array_intersect_key(['rol' => $antes['rol'], 'activo' => $antes['activo']], $cambios), $cambios);
    responder(usuarioAdmin($id), 200, 'Usuario actualizado.');
});

// DELETE: baja lógica (los usuarios con pedidos no deben desaparecer)
ruta('DELETE', 'usuarios/{id}', function ($p) {
    $admin = conPermiso('usuarios.gestionar');
    $id = soloEntero($p['id']);
    $u = usuarioAdmin($id);
    if ($id === $admin['id']) fallar(409, 'No puedes dar de baja tu propia cuenta.');
    if (!$u['activo']) fallar(409, 'El usuario ya estaba dado de baja.');
    db()->prepare('UPDATE usuarios SET activo = 0 WHERE id = ?')->execute([$id]);
    registrarHistorial('baja', 'usuario', $id, ['activo' => true], ['activo' => false]);
    responder(null, 200, 'Usuario dado de baja. Sus pedidos se conservan.');
});
