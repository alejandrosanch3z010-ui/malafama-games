<?php
// ---------- Pedidos / ventas ----------
function pedidoDetalle($id, array $u) {
    $st = db()->prepare('SELECT p.*, u.nombre AS cliente, u.email FROM pedidos p JOIN usuarios u ON u.id = p.usuario_id WHERE p.id = ?');
    $st->execute([$id]);
    $p = $st->fetch();
    // Un cliente solo ve sus pedidos (se responde 404 para no revelar que existe)
    if (!$p || ($p['usuario_id'] != $u['id'] && !puede('pedidos.ver_todos', $u))) fallar(404, 'El pedido no existe.');

    $st = db()->prepare('SELECT d.id, d.cantidad, d.precio_unitario, e.formato, e.version, j.titulo, t.monto
                         FROM pedido_detalle d LEFT JOIN ediciones e ON e.id = d.edicion_id LEFT JOIN juegos j ON j.id = e.juego_id
                         LEFT JOIN tarjetas_psn t ON t.id = d.tarjeta_id WHERE d.pedido_id = ?');
    $st->execute([$id]);
    $items = $st->fetchAll();
    // Los códigos digitales solo los ven el dueño y el administrador
    $verCodigos = $p['usuario_id'] == $u['id'] || $u['rol'] === 'admin';
    foreach ($items as &$it) {
        $it['producto'] = $it['titulo'] ? $it['titulo'] . ' · ' . nombreEdicion($it) : 'Tarjeta PSN ' . dinero($it['monto']);
        $it['importe'] = $it['cantidad'] * $it['precio_unitario'];
        if ($verCodigos) {
            $s = db()->prepare('SELECT codigo, activo FROM codigos_digitales WHERE pedido_detalle_id = ?');
            $s->execute([$it['id']]);
            $it['codigos'] = $s->fetchAll();
        }
    }
    $p['items'] = $items;
    return $p;
}

// POST /api/pedidos — registra la venta (transacción completa)
ruta('POST', 'pedidos', function () {
    $u = autenticado();
    $id = crearPedido($u, entrada());
    $st = db()->prepare('SELECT folio, total FROM pedidos WHERE id = ?');
    $st->execute([$id]);
    $p = $st->fetch();
    responder(['id' => $id, 'folio' => $p['folio'], 'total' => (float)$p['total']], 201,
              '¡Pago aprobado! Tu pedido ' . $p['folio'] . ' está confirmado.', ['redirigir' => 'pedido.php?id=' . $id . '&nuevo=1']);
});

// GET /api/pedidos — el cliente ve los suyos; administrador y empleado ven todos con filtros
ruta('GET', 'pedidos', function () {
    $u = autenticado();
    [$pagina, $limite, $offset] = paginacion(15);
    $where = []; $params = [];
    if (!puede('pedidos.ver_todos', $u)) { $where[] = 'p.usuario_id = ?'; $params[] = $u['id']; }
    if (!empty($_GET['q'])) { $where[] = '(p.folio LIKE ? OR u.nombre LIKE ? OR u.email LIKE ?)'; array_push($params, ...array_fill(0, 3, '%' . $_GET['q'] . '%')); }
    if (!empty($_GET['estado'])) { $where[] = 'p.estado = ?'; $params[] = $_GET['estado']; }
    if (!empty($_GET['metodo'])) { $where[] = 'p.metodo_pago = ?'; $params[] = $_GET['metodo']; }
    if (!empty($_GET['desde']) || !empty($_GET['hasta'])) {
        [$desde, $hasta] = rangoFechas();
        $where[] = 'DATE(p.creado) BETWEEN ? AND ?'; array_push($params, $desde, $hasta);
    }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $ordenes = ['recientes' => 'p.creado DESC', 'antiguos' => 'p.creado ASC', 'total_desc' => 'p.total DESC', 'total_asc' => 'p.total ASC'];
    $orden = $ordenes[$_GET['orden'] ?? ''] ?? $ordenes['recientes'];

    $st = db()->prepare("SELECT COUNT(*) FROM pedidos p JOIN usuarios u ON u.id = p.usuario_id $w");
    $st->execute($params);
    $total = (int)$st->fetchColumn();
    $st = db()->prepare("SELECT p.id, p.folio, p.total, p.estado, p.metodo_pago, p.creado, (p.direccion_envio IS NOT NULL) AS con_envio, u.nombre AS cliente,
                                (SELECT SUM(cantidad) FROM pedido_detalle d WHERE d.pedido_id = p.id) AS piezas
                         FROM pedidos p JOIN usuarios u ON u.id = p.usuario_id $w ORDER BY $orden, p.id DESC LIMIT $limite OFFSET $offset");
    $st->execute($params);
    responderPagina($st->fetchAll(), $total, $pagina, $limite);
});

ruta('GET', 'pedidos/{id}', function ($p) {
    responder(pedidoDetalle(soloEntero($p['id']), autenticado()));
});

// PATCH /api/pedidos/{id}/estado — avanza el envío (administrador o empleado)
ruta('PATCH', 'pedidos/{id}/estado', function ($p) {
    $u = conPermiso('pedidos.cambiar_estado');
    $nuevo = avanzarPedido(soloEntero($p['id']), $u);
    responder(['estado' => $nuevo], 200, 'El pedido pasó a: ' . ucfirst($nuevo) . '.');
});

// POST /api/pedidos/{id}/cancelar — solo administrador (permiso especial)
ruta('POST', 'pedidos/{id}/cancelar', function ($p) {
    $u = conPermiso('pedidos.cancelar');
    cancelarPedido(soloEntero($p['id']), $u, entrada()['motivo'] ?? '');
    responder(null, 200, 'Pedido cancelado. Se regresó el inventario y se ajustaron los puntos.');
});

// Regla de negocio: una venta registrada nunca se elimina (se cancela para conservar el historial)
ruta('DELETE', 'pedidos/{id}', function ($p) {
    autenticado();
    fallar(409, 'Las ventas no se eliminan. Si es necesario, un administrador puede cancelarla.');
});
