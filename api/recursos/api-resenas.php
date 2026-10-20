<?php
// ---------- Reseñas de juegos ----------
ruta('GET', 'juegos/{id}/resenas', function ($p) {
    [$pagina, $limite, $offset] = paginacion(10, 50);
    $id = soloEntero($p['id']);
    $st = db()->prepare('SELECT COUNT(*) FROM resenas WHERE juego_id = ?');
    $st->execute([$id]);
    $total = (int)$st->fetchColumn();
    $st = db()->prepare("SELECT r.id, r.calificacion, r.comentario, r.creado, u.nombre FROM resenas r JOIN usuarios u ON u.id = r.usuario_id
                         WHERE r.juego_id = ? ORDER BY r.creado DESC LIMIT $limite OFFSET $offset");
    $st->execute([$id]);
    responderPagina($st->fetchAll(), $total, $pagina, $limite);
});

// Crea o actualiza la reseña del cliente (una por juego)
ruta('POST', 'juegos/{id}/resenas', function ($p) {
    $u = autenticado();
    $id = soloEntero($p['id']);
    $d = entrada();
    validar($d, ['calificacion' => ['requerido' => true, 'tipo' => 'entero', 'minimo' => 1, 'maximo' => 5, 'mensaje' => 'Elige de 1 a 5 estrellas.'],
                 'comentario' => ['max' => 1000]]);
    $st = db()->prepare('SELECT fecha_lanzamiento FROM juegos WHERE id = ? AND activo = 1');
    $st->execute([$id]);
    $fecha = $st->fetchColumn();
    if (!$fecha) fallar(404, 'El juego no existe.');
    if (esPreventa($fecha)) fallar(409, 'Podrás reseñar este juego cuando salga a la venta.');
    $st = db()->prepare('SELECT 1 FROM resenas WHERE juego_id = ? AND usuario_id = ?');
    $st->execute([$id, $u['id']]);
    $existia = (bool)$st->fetchColumn();
    db()->prepare('INSERT INTO resenas (juego_id, usuario_id, calificacion, comentario) VALUES (?, ?, ?, ?)
                   ON DUPLICATE KEY UPDATE calificacion = VALUES(calificacion), comentario = VALUES(comentario), creado = NOW()')
        ->execute([$id, $u['id'], (int)$d['calificacion'], trim($d['comentario'] ?? '') ?: null]);
    actualizarCalificacion($id);
    responder(null, $existia ? 200 : 201, $existia ? 'Actualizaste tu reseña.' : '¡Gracias por tu reseña!');
});
