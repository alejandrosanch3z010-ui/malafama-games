<?php
// ---------- Dashboard, reportes, inventario e historial ----------
const VENTA_VALIDA = "p.estado <> 'cancelado'";

ruta('GET', 'dashboard', function () {
    conPermiso('panel.ver');
    $db = db();
    $mes = $db->query("SELECT COUNT(*) AS pedidos, COALESCE(SUM(total), 0) AS ventas FROM pedidos p
                       WHERE " . VENTA_VALIDA . " AND p.creado >= DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetch();
    $anterior = $db->query("SELECT COALESCE(SUM(total), 0) FROM pedidos p WHERE " . VENTA_VALIDA . "
                            AND p.creado >= DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH, '%Y-%m-01') AND p.creado < DATE_FORMAT(CURDATE(), '%Y-%m-01')")->fetchColumn();
    $stockBajo = $db->query("SELECT e.id AS edicion_id, j.id AS juego_id, j.titulo, e.version, e.formato, e.stock FROM ediciones e JOIN juegos j ON j.id = e.juego_id
                             WHERE j.activo = 1 AND e.formato = 'fisico' AND e.stock <= " . STOCK_BAJO . " ORDER BY e.stock, j.titulo")->fetchAll();

    responder([
        'tarjetas' => [
            'ventas_mes' => (float)$mes['ventas'],
            'ventas_mes_anterior' => (float)$anterior,
            'pedidos_mes' => (int)$mes['pedidos'],
            'ticket_promedio' => $mes['pedidos'] ? round($mes['ventas'] / $mes['pedidos'], 2) : 0,
            'clientes' => (int)$db->query("SELECT COUNT(*) FROM usuarios WHERE rol = 'cliente' AND activo = 1")->fetchColumn(),
            'juegos_activos' => (int)$db->query('SELECT COUNT(*) FROM juegos WHERE activo = 1')->fetchColumn(),
            'por_enviar' => (int)$db->query("SELECT COUNT(*) FROM pedidos WHERE estado IN ('pagado', 'preparando')")->fetchColumn(),
            'stock_bajo' => count($stockBajo),
        ],
        'ventas_diarias' => $db->query("SELECT DATE(creado) AS fecha, COUNT(*) AS pedidos, SUM(total) AS total FROM pedidos p
                                        WHERE " . VENTA_VALIDA . " AND p.creado >= CURDATE() - INTERVAL 29 DAY GROUP BY DATE(creado) ORDER BY fecha")->fetchAll(),
        'ventas_por_genero' => $db->query("SELECT g.nombre AS genero, SUM(d.cantidad * d.precio_unitario) AS total, SUM(d.cantidad) AS piezas
                                           FROM pedido_detalle d JOIN pedidos p ON p.id = d.pedido_id JOIN ediciones e ON e.id = d.edicion_id
                                           JOIN juegos j ON j.id = e.juego_id JOIN generos g ON g.id = j.genero_id
                                           WHERE " . VENTA_VALIDA . " GROUP BY g.id ORDER BY total DESC")->fetchAll(),
        'metodos_pago' => $db->query("SELECT metodo_pago AS metodo, COUNT(*) AS pedidos FROM pedidos p WHERE " . VENTA_VALIDA . " GROUP BY metodo_pago ORDER BY pedidos DESC")->fetchAll(),
        'ultimos_pedidos' => $db->query("SELECT p.id, p.folio, p.total, p.estado, p.creado, u.nombre AS cliente FROM pedidos p
                                         JOIN usuarios u ON u.id = p.usuario_id ORDER BY p.creado DESC LIMIT 6")->fetchAll(),
        'stock_bajo' => $stockBajo,
    ]);
});

// Reporte 1: ventas por periodo (día o mes) con totales
ruta('GET', 'reportes/ventas', function () {
    conPermiso('reportes.ver');
    [$desde, $hasta] = rangoFechas();
    $grupo = ($_GET['agrupar'] ?? 'dia') === 'mes' ? "DATE_FORMAT(p.creado, '%Y-%m')" : 'DATE(p.creado)';
    $st = db()->prepare("SELECT $grupo AS periodo, COUNT(*) AS pedidos,
                                SUM((SELECT SUM(cantidad) FROM pedido_detalle d WHERE d.pedido_id = p.id)) AS piezas,
                                SUM(subtotal) AS subtotal, SUM(descuento_puntos) AS descuento, SUM(iva) AS iva, SUM(total) AS total
                         FROM pedidos p WHERE " . VENTA_VALIDA . " AND DATE(p.creado) BETWEEN ? AND ? GROUP BY periodo ORDER BY periodo");
    $st->execute([$desde, $hasta]);
    $filas = $st->fetchAll();
    $st = db()->prepare("SELECT COUNT(*) FROM pedidos p WHERE p.estado = 'cancelado' AND DATE(p.creado) BETWEEN ? AND ?");
    $st->execute([$desde, $hasta]);
    $totales = ['pedidos' => 0, 'piezas' => 0, 'subtotal' => 0, 'descuento' => 0, 'iva' => 0, 'total' => 0];
    foreach ($filas as $f) foreach ($totales as $k => $v) $totales[$k] += (float)$f[$k];
    $totales['pedidos'] = (int)$totales['pedidos'];
    $totales['piezas'] = (int)$totales['piezas'];
    $totales['cancelados'] = (int)$st->fetchColumn();
    $totales['ticket_promedio'] = $totales['pedidos'] ? round($totales['total'] / $totales['pedidos'], 2) : 0;
    responder(['desde' => $desde, 'hasta' => $hasta, 'filas' => $filas, 'totales' => $totales]);
});

// Reporte 2: juegos más vendidos en el periodo
ruta('GET', 'reportes/juegos-mas-vendidos', function () {
    conPermiso('reportes.ver');
    [$desde, $hasta] = rangoFechas();
    $limite = min(50, max(1, (int)($_GET['limite'] ?? 10)));
    $st = db()->prepare("SELECT j.id, j.titulo, g.nombre AS genero, SUM(d.cantidad) AS piezas, SUM(d.cantidad * d.precio_unitario) AS ingresos,
                                SUM(CASE WHEN e.formato = 'fisico' THEN d.cantidad ELSE 0 END) AS fisicos,
                                SUM(CASE WHEN e.formato = 'digital' THEN d.cantidad ELSE 0 END) AS digitales
                         FROM pedido_detalle d JOIN pedidos p ON p.id = d.pedido_id JOIN ediciones e ON e.id = d.edicion_id
                         JOIN juegos j ON j.id = e.juego_id JOIN generos g ON g.id = j.genero_id
                         WHERE " . VENTA_VALIDA . " AND DATE(p.creado) BETWEEN ? AND ?
                         GROUP BY j.id ORDER BY piezas DESC, ingresos DESC LIMIT $limite");
    $st->execute([$desde, $hasta]);
    responder(['desde' => $desde, 'hasta' => $hasta, 'filas' => $st->fetchAll()]);
});

// Reporte 3: inventario con alerta de existencias bajas y ventas de los últimos 30 días
ruta('GET', 'reportes/inventario', function () {
    conPermiso('reportes.ver');
    $soloBajo = !empty($_GET['solo_bajo']);
    $filas = db()->query("SELECT e.id AS edicion_id, j.titulo, e.version, e.formato, e.stock, COALESCE(e.precio_oferta, e.precio) AS precio,
                                 (SELECT COALESCE(SUM(-m.cantidad), 0) FROM movimientos_inventario m
                                  WHERE m.edicion_id = e.id AND m.tipo = 'venta' AND m.creado >= NOW() - INTERVAL 30 DAY) AS vendidos_30d
                          FROM ediciones e JOIN juegos j ON j.id = e.juego_id WHERE j.activo = 1"
                          . ($soloBajo ? " AND e.formato = 'fisico' AND e.stock <= " . STOCK_BAJO : '') . ' ORDER BY e.formato, e.stock, j.titulo, e.version')->fetchAll();
    foreach ($filas as &$f) $f['alerta'] = $f['formato'] === 'fisico' && $f['stock'] <= STOCK_BAJO;
    responder(['stock_bajo' => STOCK_BAJO, 'filas' => $filas]);
});

// Movimientos de inventario (entradas, salidas y ajustes)
ruta('GET', 'inventario/movimientos', function () {
    conPermiso('panel.ver');
    [$pagina, $limite, $offset] = paginacion(20);
    $where = []; $params = [];
    if (!empty($_GET['edicion_id'])) { $where[] = 'm.edicion_id = ?'; $params[] = (int)$_GET['edicion_id']; }
    if (!empty($_GET['tipo'])) { $where[] = 'm.tipo = ?'; $params[] = $_GET['tipo']; }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $st = db()->prepare("SELECT COUNT(*) FROM movimientos_inventario m $w");
    $st->execute($params);
    $total = (int)$st->fetchColumn();
    $st = db()->prepare("SELECT m.*, COALESCE(j.titulo, CONCAT('Tarjeta PSN $', t.monto)) AS producto, e.formato, e.version, u.nombre AS usuario, p.folio
                         FROM movimientos_inventario m LEFT JOIN ediciones e ON e.id = m.edicion_id LEFT JOIN juegos j ON j.id = e.juego_id
                         LEFT JOIN tarjetas_psn t ON t.id = m.tarjeta_id LEFT JOIN usuarios u ON u.id = m.usuario_id LEFT JOIN pedidos p ON p.id = m.pedido_id
                         $w ORDER BY m.creado DESC, m.id DESC LIMIT $limite OFFSET $offset");
    $st->execute($params);
    responderPagina($st->fetchAll(), $total, $pagina, $limite);
});

// Historial de operaciones (auditoría)
ruta('GET', 'historial', function () {
    conPermiso('historial.ver');
    [$pagina, $limite, $offset] = paginacion(20);
    $where = []; $params = [];
    foreach (['entidad', 'accion'] as $campo) if (!empty($_GET[$campo])) { $where[] = "h.$campo = ?"; $params[] = $_GET[$campo]; }
    if (!empty($_GET['q'])) { $where[] = '(u.nombre LIKE ? OR u.email LIKE ?)'; array_push($params, '%' . $_GET['q'] . '%', '%' . $_GET['q'] . '%'); }
    if (!empty($_GET['desde']) || !empty($_GET['hasta'])) { [$d, $h] = rangoFechas(); $where[] = 'DATE(h.creado) BETWEEN ? AND ?'; array_push($params, $d, $h); }
    $w = $where ? 'WHERE ' . implode(' AND ', $where) : '';
    $st = db()->prepare("SELECT COUNT(*) FROM historial h LEFT JOIN usuarios u ON u.id = h.usuario_id $w");
    $st->execute($params);
    $total = (int)$st->fetchColumn();
    $st = db()->prepare("SELECT h.*, u.nombre AS usuario, u.rol FROM historial h LEFT JOIN usuarios u ON u.id = h.usuario_id
                         $w ORDER BY h.creado DESC, h.id DESC LIMIT $limite OFFSET $offset");
    $st->execute($params);
    responderPagina($st->fetchAll(), $total, $pagina, $limite);
});
