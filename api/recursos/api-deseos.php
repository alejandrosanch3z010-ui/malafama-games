<?php
// ---------- Lista de deseos ----------
ruta('GET', 'deseos', function () {
    $u = autenticado();
    responder(array_map('juegoApi', buscarJuegos(['deseos_de' => $u['id']])));
});

ruta('POST', 'deseos', function () {
    $u = autenticado();
    $juegoId = filter_var(entrada()['juego_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$juegoId) fallar(422, 'Indica el juego.', ['juego_id' => 'Obligatorio.']);
    $st = db()->prepare('SELECT titulo FROM juegos WHERE id = ? AND activo = 1');
    $st->execute([$juegoId]);
    if (!$titulo = $st->fetchColumn()) fallar(404, 'El juego no existe.');
    if (enDeseos($u['id'], $juegoId)) fallar(409, "$titulo ya está en tu lista de deseos.");
    db()->prepare('INSERT INTO wishlist (usuario_id, juego_id) VALUES (?, ?)')->execute([$u['id'], $juegoId]);
    responder(['total' => contarDeseos($u['id'])], 201, "$titulo se guardó en tu lista de deseos. ♥");
});

ruta('DELETE', 'deseos/{juego_id}', function ($p) {
    $u = autenticado();
    $st = db()->prepare('DELETE FROM wishlist WHERE usuario_id = ? AND juego_id = ?');
    $st->execute([$u['id'], soloEntero($p['juego_id'])]);
    if (!$st->rowCount()) fallar(404, 'Ese juego no estaba en tu lista de deseos.');
    responder(['total' => contarDeseos($u['id'])], 200, 'El juego salió de tu lista de deseos.');
});
