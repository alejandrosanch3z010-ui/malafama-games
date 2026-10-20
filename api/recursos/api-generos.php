<?php
// ---------- Géneros (CRUD) ----------
function generoPorId($id) {
    $st = db()->prepare('SELECT g.*, (SELECT COUNT(*) FROM juegos j WHERE j.genero_id = g.id AND j.activo = 1) AS juegos_activos FROM generos g WHERE g.id = ?');
    $st->execute([$id]);
    if (!$g = $st->fetch()) fallar(404, 'El género no existe.');
    return ['id' => (int)$g['id'], 'nombre' => $g['nombre'], 'activo' => (bool)$g['activo'], 'juegos_activos' => (int)$g['juegos_activos']];
}

ruta('GET', 'generos', function () {
    $todos = !empty($_GET['todos']) && puede('generos.gestionar');
    $orden = ($_GET['orden'] ?? '') === 'juegos' ? 'juegos_activos DESC, g.nombre' : 'g.nombre';
    $filas = db()->query('SELECT g.*, (SELECT COUNT(*) FROM juegos j WHERE j.genero_id = g.id AND j.activo = 1) AS juegos_activos
                          FROM generos g ' . ($todos ? '' : 'WHERE g.activo = 1 ') . "ORDER BY $orden")->fetchAll();
    responder(array_map(fn($g) => ['id' => (int)$g['id'], 'nombre' => $g['nombre'], 'activo' => (bool)$g['activo'], 'juegos_activos' => (int)$g['juegos_activos']], $filas));
});

ruta('GET', 'generos/{id}', function ($p) {
    responder(generoPorId(soloEntero($p['id'])));
});

ruta('POST', 'generos', function () {
    conPermiso('generos.gestionar');
    $d = entrada();
    validar($d, ['nombre' => ['requerido' => true, 'min' => 3, 'max' => 40]]);
    $st = db()->prepare('SELECT 1 FROM generos WHERE nombre = ?');
    $st->execute([trim($d['nombre'])]);
    if ($st->fetchColumn()) fallar(409, 'Ya existe un género con ese nombre.', ['nombre' => 'Ya existe.']);
    db()->prepare('INSERT INTO generos (nombre) VALUES (?)')->execute([trim($d['nombre'])]);
    $id = (int)db()->lastInsertId();
    registrarHistorial('crear', 'genero', $id, null, ['nombre' => trim($d['nombre'])]);
    responder(generoPorId($id), 201, 'Género creado.');
});

ruta('PUT', 'generos/{id}', function ($p) {
    conPermiso('generos.gestionar');
    $id = soloEntero($p['id']);
    $antes = generoPorId($id);
    $d = entrada();
    validar($d, ['nombre' => ['requerido' => true, 'min' => 3, 'max' => 40]]);
    $st = db()->prepare('SELECT 1 FROM generos WHERE nombre = ? AND id <> ?');
    $st->execute([trim($d['nombre']), $id]);
    if ($st->fetchColumn()) fallar(409, 'Ya existe un género con ese nombre.', ['nombre' => 'Ya existe.']);
    $activo = array_key_exists('activo', $d) ? (filter_var($d['activo'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0) : (int)$antes['activo'];
    db()->prepare('UPDATE generos SET nombre = ?, activo = ? WHERE id = ?')->execute([trim($d['nombre']), $activo, $id]);
    registrarHistorial('editar', 'genero', $id, ['nombre' => $antes['nombre'], 'activo' => $antes['activo']], ['nombre' => trim($d['nombre']), 'activo' => (bool)$activo]);
    responder(generoPorId($id), 200, 'Género actualizado.');
});

// Regla: no se puede dar de baja un género que tiene juegos activos
ruta('DELETE', 'generos/{id}', function ($p) {
    conPermiso('generos.gestionar');
    $g = generoPorId(soloEntero($p['id']));
    if (!$g['activo']) fallar(409, 'El género ya estaba dado de baja.');
    if ($g['juegos_activos'] > 0) fallar(409, "No se puede dar de baja: tiene {$g['juegos_activos']} juegos activos. Muévelos o dalos de baja primero.");
    db()->prepare('UPDATE generos SET activo = 0 WHERE id = ?')->execute([$g['id']]);
    registrarHistorial('baja', 'genero', $g['id'], ['activo' => true], ['activo' => false]);
    responder(null, 200, 'Género dado de baja.');
});
