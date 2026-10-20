<?php
// ---------- Carrito (vive en la sesión; precios y existencias siempre salen de la BD) ----------
function carritoApi($usarPuntos = false) {
    $u = usuarioActual();
    $items = carritoItems();
    $tot = carritoTotales($items, $usarPuntos, $u['puntos'] ?? 0);
    $tot['iva'] = round($tot['total'] - $tot['total'] / (1 + IVA), 2);
    return ['items' => array_map(fn($it) => [
                'clave' => $it['clave'], 'titulo' => $it['titulo'], 'detalle' => $it['detalle'], 'cantidad' => $it['cantidad'],
                'precio' => $it['precio'], 'importe' => $it['precio'] * $it['cantidad'], 'stock' => $it['stock'], 'digital' => $it['digital'],
            ], $items),
            'piezas' => piezasEnCarrito(), 'totales' => $tot];
}

function existenciaProducto($clave) {
    $id = (int)substr($clave, 1);
    $sql = $clave[0] === 'e'
        ? 'SELECT e.stock FROM ediciones e JOIN juegos j ON j.id = e.juego_id WHERE e.id = ? AND j.activo = 1'
        : 'SELECT stock FROM tarjetas_psn WHERE id = ? AND activo = 1';
    $st = db()->prepare($sql);
    $st->execute([$id]);
    $v = $st->fetchColumn();
    if ($v === false) fallar(404, 'El producto no existe o ya no está disponible.');
    return (int)$v;
}

ruta('GET', 'carrito', function () {
    responder(carritoApi(!empty($_GET['usar_puntos'])));
});

ruta('POST', 'carrito', function () {
    $d = entrada();
    $clave = !empty($d['edicion']) ? 'e' . (int)$d['edicion'] : (!empty($d['tarjeta']) ? 't' . (int)$d['tarjeta'] : '');
    if ($clave === '') fallar(422, 'Elige un formato disponible.', ['edicion' => 'Elige un formato.']);
    $cantidad = filter_var($d['cantidad'] ?? 1, FILTER_VALIDATE_INT);
    if ($cantidad === false || $cantidad < 1) fallar(422, 'La cantidad debe ser mayor a 0.', ['cantidad' => 'Mayor a 0.']);

    // Reglas: no vender sin existencias y máximo MAX_POR_PRODUCTO piezas
    $stock = existenciaProducto($clave);
    if ($stock <= 0) fallar(409, 'Ese producto está agotado.');
    $c = carrito();
    $limite = min(MAX_POR_PRODUCTO, $stock);
    $nueva = ($c[$clave] ?? 0) + $cantidad;
    $c[$clave] = min($nueva, $limite);
    guardarCarrito($c);
    $mensaje = $nueva > $limite ? "Solo puedes llevar $limite de este producto." : 'Se agregó a tu carrito.';
    responder(carritoApi(), 200, $mensaje, !empty($d['comprar_ya']) ? ['redirigir' => 'carrito.php'] : []);
});

ruta('PATCH', 'carrito/{clave}', function ($p) {
    $clave = $p['clave'];
    $c = carrito();
    if (!preg_match('/^[et]\d+$/', $clave) || !isset($c[$clave])) fallar(404, 'Ese producto no está en tu carrito.');
    $cantidad = filter_var(entrada()['cantidad'] ?? null, FILTER_VALIDATE_INT);
    if ($cantidad === false || $cantidad < 1) fallar(422, 'La cantidad debe ser mayor a 0.', ['cantidad' => 'Mayor a 0.']);
    $limite = min(MAX_POR_PRODUCTO, existenciaProducto($clave));
    if ($cantidad > $limite) fallar(409, "Solo hay $limite disponibles de este producto.");
    $c[$clave] = $cantidad;
    guardarCarrito($c);
    responder(carritoApi(), 200, 'Cantidad actualizada.');
});

ruta('DELETE', 'carrito/{clave}', function ($p) {
    $c = carrito();
    if (!isset($c[$p['clave']])) fallar(404, 'Ese producto no está en tu carrito.');
    unset($c[$p['clave']]);
    guardarCarrito($c);
    responder(carritoApi(), 200, 'Quitaste el producto del carrito.');
});

ruta('DELETE', 'carrito', function () {
    guardarCarrito([]);
    responder(carritoApi(), 200, 'Vaciaste tu carrito.');
});
