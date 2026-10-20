<?php
// ---------- Tarjetas PSN ----------
function tarjetaApi($t) {
    return ['id' => (int)$t['id'], 'monto' => (float)$t['monto'], 'precio' => (float)$t['precio'], 'stock' => (int)$t['stock'], 'activo' => (bool)$t['activo']];
}

ruta('GET', 'tarjetas', function () {
    $todos = !empty($_GET['todos']) && puede('tarjetas.gestionar');
    responder(array_map('tarjetaApi', db()->query('SELECT * FROM tarjetas_psn ' . ($todos ? '' : 'WHERE activo = 1 ') . 'ORDER BY monto')->fetchAll()));
});

ruta('PUT', 'tarjetas/{id}', function ($p) {
    conPermiso('tarjetas.gestionar');
    $id = soloEntero($p['id']);
    $st = db()->prepare('SELECT * FROM tarjetas_psn WHERE id = ?');
    $st->execute([$id]);
    if (!$antes = $st->fetch()) fallar(404, 'La tarjeta no existe.');
    $d = entrada();
    validar($d, ['precio' => ['requerido' => true, 'tipo' => 'numero', 'minimo' => 1], 'stock' => ['requerido' => true, 'tipo' => 'entero', 'minimo' => 0, 'maximo' => 9999]]);
    $activo = filter_var($d['activo'] ?? true, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    db()->prepare('UPDATE tarjetas_psn SET precio = ?, stock = ?, activo = ? WHERE id = ?')->execute([(float)$d['precio'], (int)$d['stock'], $activo, $id]);
    registrarHistorial('editar', 'tarjeta', $id, tarjetaApi($antes), ['precio' => (float)$d['precio'], 'stock' => (int)$d['stock'], 'activo' => (bool)$activo]);
    $st->execute([$id]);
    responder(tarjetaApi($st->fetch()), 200, 'Tarjeta actualizada.');
});
