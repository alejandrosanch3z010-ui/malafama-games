<?php
// =====================================================================
//  Capa de negocio (backend): permisos por rol, historial y operaciones
//  críticas (venta, cancelación, envío e inventario). La usan tanto la
//  API REST como las páginas, para que las reglas vivan en un solo lugar.
// =====================================================================

// Error controlado: lleva el código HTTP y, si aplica, los errores por campo
class ErrorNegocio extends Exception {
    public $status;
    public $campos;
    public function __construct($status, $mensaje, $campos = []) {
        parent::__construct($mensaje);
        $this->status = $status;
        $this->campos = $campos;
    }
}

// ---------- Roles y permisos (RBAC) ----------
const PERMISOS = [
    'panel.ver'              => ['admin', 'empleado'],
    'juegos.gestionar'       => ['admin'],
    'generos.gestionar'      => ['admin'],
    'usuarios.gestionar'     => ['admin'],
    'tarjetas.gestionar'     => ['admin'],
    'inventario.ajustar'     => ['admin', 'empleado'],
    'pedidos.ver_todos'      => ['admin', 'empleado'],
    'pedidos.cambiar_estado' => ['admin', 'empleado'],
    'pedidos.cancelar'       => ['admin'],
    'reportes.ver'           => ['admin', 'empleado'],
    'historial.ver'          => ['admin'],
];

function puede($permiso, $usuario = null) {
    $usuario = $usuario ?? usuarioActual();
    return $usuario && in_array($usuario['rol'], PERMISOS[$permiso] ?? [], true);
}

function nombreRol($rol) {
    return ['admin' => 'Administrador', 'empleado' => 'Empleado', 'cliente' => 'Cliente'][$rol] ?? $rol;
}

// ---------- Historial de operaciones ----------
function registrarHistorial($accion, $entidad, $entidadId = null, $antes = null, $despues = null, $usuarioId = null) {
    $usuarioId = $usuarioId ?? (usuarioActual()['id'] ?? null);
    db()->prepare('INSERT INTO historial (usuario_id, accion, entidad, entidad_id, antes, despues, ip) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([
            $usuarioId, $accion, $entidad, $entidadId,
            $antes === null ? null : json_encode($antes, JSON_UNESCAPED_UNICODE),
            $despues === null ? null : json_encode($despues, JSON_UNESCAPED_UNICODE),
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
}

// ---------- Validación de los datos de pago ----------
function validarDatosPago(array $d, $hayFisico) {
    $e = [];
    if (!in_array($d['metodo'] ?? '', ['tarjeta', 'paypal', 'mercadopago', 'oxxo'], true)) $e['metodo'] = 'Elige cómo quieres pagar.';
    if (empty($d['acepto'])) $e['acepto'] = 'Debes aceptar los Términos y la Política de Devoluciones.';

    if ($hayFisico) {
        if (mb_strlen(trim($d['recibe'] ?? '')) < 3) $e['recibe'] = 'Escribe quién recibe el paquete.';
        if (!preg_match('/^\d{10}$/', preg_replace('/\D/', '', $d['telefono'] ?? ''))) $e['telefono'] = 'El teléfono debe tener 10 dígitos.';
        if (mb_strlen(trim($d['direccion'] ?? '')) < 8) $e['direccion'] = 'Escribe calle, número y colonia.';
        if (!preg_match('/^\d{5}$/', trim($d['cp'] ?? ''))) $e['cp'] = 'El código postal tiene 5 dígitos.';
        if (mb_strlen(trim($d['ciudad'] ?? '')) < 2) $e['ciudad'] = 'Escribe la ciudad.';
        if (mb_strlen(trim($d['estado'] ?? '')) < 2) $e['estado'] = 'Escribe el estado.';
    }

    // Tarjeta simulada: se valida el formato y nunca se guarda
    if (($d['metodo'] ?? '') === 'tarjeta') {
        $numero = preg_replace('/\D/', '', $d['tarjeta_numero'] ?? '');
        if (!preg_match('/^\d{16}$/', $numero)) $e['tarjeta_numero'] = 'El número de tarjeta tiene 16 dígitos.';
        if (mb_strlen(trim($d['tarjeta_nombre'] ?? '')) < 3) $e['tarjeta_nombre'] = 'Escribe el nombre como aparece en la tarjeta.';
        $venc = trim($d['tarjeta_venc'] ?? '');
        if (!preg_match('/^(0[1-9]|1[0-2])\/(\d{2})$/', $venc, $m) || mktime(0, 0, 0, (int)$m[1] + 1, 1, 2000 + (int)$m[2]) <= time())
            $e['tarjeta_venc'] = 'Fecha inválida o vencida (MM/AA).';
        if (!preg_match('/^\d{3,4}$/', $d['tarjeta_cvv'] ?? '')) $e['tarjeta_cvv'] = 'El CVV tiene 3 o 4 dígitos.';
        if (!$e && substr($numero, -4) === '0002') $e['pago'] = 'El banco rechazó el pago (tarjeta de prueba 0002). Intenta con otra tarjeta.';
    }
    return $e;
}

// ---------- Venta (transacción) ----------
// Crear pedido + detalle + descontar inventario + registrar movimientos + puntos + historial.
// Si algo falla, ROLLBACK y no queda nada a medias.
function crearPedido(array $usuario, array $d) {
    $items = carritoItems();
    if (!$items) throw new ErrorNegocio(409, 'Tu carrito está vacío.');

    $hayFisico = (bool)array_filter($items, fn($it) => !$it['digital']);
    if ($errores = validarDatosPago($d, $hayFisico)) {
        throw new ErrorNegocio(422, $errores['pago'] ?? 'Revisa los datos marcados.', $errores);
    }

    $pdo = db();
    try {
        $pdo->beginTransaction();

        // Regla: no vender sin existencias (se bloquean las filas para evitar ventas dobles)
        foreach ($items as $it) {
            $sql = $it['tipo'] === 'juego' ? 'SELECT stock FROM ediciones WHERE id = ? FOR UPDATE' : 'SELECT stock FROM tarjetas_psn WHERE id = ? FOR UPDATE';
            $st = $pdo->prepare($sql);
            $st->execute([$it['edicion_id'] ?? $it['tarjeta_id']]);
            if ((int)$st->fetchColumn() < $it['cantidad']) {
                throw new ErrorNegocio(409, 'Ya no hay suficientes piezas de ' . $it['titulo'] . '.');
            }
        }

        $st = $pdo->prepare('SELECT puntos FROM usuarios WHERE id = ? FOR UPDATE');
        $st->execute([$usuario['id']]);
        $tot = carritoTotales($items, !empty($d['usar_puntos']), (int)$st->fetchColumn());
        $iva = round($tot['total'] - $tot['total'] / (1 + IVA), 2);

        $direccion = $hayFisico
            ? trim($d['recibe']) . ' · Tel. ' . preg_replace('/\D/', '', $d['telefono']) . ' · ' . trim($d['direccion']) . ', C.P. ' . trim($d['cp']) . ', ' . trim($d['ciudad']) . ', ' . trim($d['estado'])
            : null;

        $pdo->prepare('INSERT INTO pedidos (usuario_id, subtotal, descuento_puntos, total, iva, puntos_usados, puntos_ganados, metodo_pago, estado, direccion_envio)
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([$usuario['id'], $tot['subtotal'], $tot['descuento'], $tot['total'], $iva, $tot['puntos_usados'], $tot['puntos_ganados'],
                       $d['metodo'], $hayFisico ? 'pagado' : 'entregado', $direccion]);
        $pedidoId = (int)$pdo->lastInsertId();
        $folio = 'MF-' . date('Y') . '-' . str_pad($pedidoId, 6, '0', STR_PAD_LEFT);
        $pdo->prepare('UPDATE pedidos SET folio = ? WHERE id = ?')->execute([$folio, $pedidoId]);

        foreach ($items as $it) {
            $pdo->prepare('INSERT INTO pedido_detalle (pedido_id, edicion_id, tarjeta_id, cantidad, precio_unitario) VALUES (?, ?, ?, ?, ?)')
                ->execute([$pedidoId, $it['edicion_id'], $it['tarjeta_id'], $it['cantidad'], $it['precio']]);
            $detalleId = (int)$pdo->lastInsertId();

            // Inventario automático + movimiento
            $tabla = $it['tipo'] === 'juego' ? 'ediciones' : 'tarjetas_psn';
            $idProd = $it['edicion_id'] ?? $it['tarjeta_id'];
            $pdo->prepare("UPDATE $tabla SET stock = stock - ? WHERE id = ?")->execute([$it['cantidad'], $idProd]);
            $st = $pdo->prepare("SELECT stock FROM $tabla WHERE id = ?");
            $st->execute([$idProd]);
            $pdo->prepare('INSERT INTO movimientos_inventario (edicion_id, tarjeta_id, pedido_id, usuario_id, tipo, cantidad, stock_resultante) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$it['edicion_id'], $it['tarjeta_id'], $pedidoId, $usuario['id'], 'venta', -$it['cantidad'], (int)$st->fetchColumn()]);

            if ($it['digital']) {
                for ($i = 0; $i < $it['cantidad']; $i++) {
                    $pdo->prepare('INSERT INTO codigos_digitales (pedido_detalle_id, codigo) VALUES (?, ?)')->execute([$detalleId, generarCodigo()]);
                }
            }
        }

        // Programa de lealtad
        $pdo->prepare('UPDATE usuarios SET puntos = puntos - ? + ? WHERE id = ?')->execute([$tot['puntos_usados'], $tot['puntos_ganados'], $usuario['id']]);
        if ($tot['puntos_usados']) {
            $pdo->prepare('INSERT INTO puntos_movimientos (usuario_id, pedido_id, puntos, concepto) VALUES (?, ?, ?, ?)')
                ->execute([$usuario['id'], $pedidoId, -$tot['puntos_usados'], "Descuento en el pedido $folio"]);
        }
        if ($tot['puntos_ganados']) {
            $pdo->prepare('INSERT INTO puntos_movimientos (usuario_id, pedido_id, puntos, concepto) VALUES (?, ?, ?, ?)')
                ->execute([$usuario['id'], $pedidoId, $tot['puntos_ganados'], "Compra del pedido $folio"]);
        }

        if ($hayFisico && !empty($d['guardar_direccion'])) {
            $pdo->prepare('UPDATE usuarios SET telefono = ?, direccion = ? WHERE id = ?')
                ->execute([preg_replace('/\D/', '', $d['telefono']), trim($d['direccion']) . ', C.P. ' . trim($d['cp']) . ', ' . trim($d['ciudad']) . ', ' . trim($d['estado']), $usuario['id']]);
        }

        registrarHistorial('crear', 'pedido', $pedidoId, null, ['folio' => $folio, 'total' => $tot['total'], 'estado' => $hayFisico ? 'pagado' : 'entregado'], $usuario['id']);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($ex instanceof ErrorNegocio) throw $ex;
        error_log('crearPedido: ' . $ex->getMessage());
        throw new ErrorNegocio(500, 'No fue posible completar la compra. Intenta de nuevo.');
    }

    guardarCarrito([]);
    return $pedidoId;
}

// ---------- Cancelación (solo administrador) ----------
// Regresa inventario, desactiva códigos y revierte los puntos, todo en una transacción.
function cancelarPedido($pedidoId, array $admin, $motivo) {
    $motivo = trim((string)$motivo);
    if (mb_strlen($motivo) < 5) throw new ErrorNegocio(422, 'Escribe el motivo de la cancelación.', ['motivo' => 'Mínimo 5 caracteres.']);

    $pdo = db();
    try {
        $pdo->beginTransaction();
        $st = $pdo->prepare('SELECT * FROM pedidos WHERE id = ? FOR UPDATE');
        $st->execute([$pedidoId]);
        $p = $st->fetch();
        if (!$p) throw new ErrorNegocio(404, 'El pedido no existe.');
        if ($p['estado'] === 'cancelado') throw new ErrorNegocio(409, 'El pedido ya estaba cancelado.');
        // Regla: un pedido enviado o entregado ya no se cancela (los códigos digitales se entregan al instante)
        if (in_array($p['estado'], ['enviado', 'entregado'], true)) {
            throw new ErrorNegocio(409, 'No se puede cancelar un pedido que ya fue enviado o entregado.');
        }

        $st = $pdo->prepare('SELECT * FROM pedido_detalle WHERE pedido_id = ?');
        $st->execute([$pedidoId]);
        foreach ($st->fetchAll() as $d) {
            $tabla = $d['edicion_id'] ? 'ediciones' : 'tarjetas_psn';
            $idProd = $d['edicion_id'] ?? $d['tarjeta_id'];
            $pdo->prepare("UPDATE $tabla SET stock = stock + ? WHERE id = ?")->execute([$d['cantidad'], $idProd]);
            $s2 = $pdo->prepare("SELECT stock FROM $tabla WHERE id = ?");
            $s2->execute([$idProd]);
            $pdo->prepare('INSERT INTO movimientos_inventario (edicion_id, tarjeta_id, pedido_id, usuario_id, tipo, cantidad, stock_resultante, nota) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute([$d['edicion_id'], $d['tarjeta_id'], $pedidoId, $admin['id'], 'cancelacion', $d['cantidad'], (int)$s2->fetchColumn(), 'Cancelación: ' . $motivo]);
            $pdo->prepare('UPDATE codigos_digitales SET activo = 0 WHERE pedido_detalle_id = ?')->execute([$d['id']]);
        }

        // Puntos: se quitan los ganados y se regresan los usados (nunca queda en negativo)
        $pdo->prepare('UPDATE usuarios SET puntos = GREATEST(0, puntos - ? + ?) WHERE id = ?')->execute([$p['puntos_ganados'], $p['puntos_usados'], $p['usuario_id']]);
        if ($p['puntos_ganados']) {
            $pdo->prepare('INSERT INTO puntos_movimientos (usuario_id, pedido_id, puntos, concepto) VALUES (?, ?, ?, ?)')
                ->execute([$p['usuario_id'], $pedidoId, -$p['puntos_ganados'], 'Cancelación del pedido ' . $p['folio']]);
        }
        if ($p['puntos_usados']) {
            $pdo->prepare('INSERT INTO puntos_movimientos (usuario_id, pedido_id, puntos, concepto) VALUES (?, ?, ?, ?)')
                ->execute([$p['usuario_id'], $pedidoId, $p['puntos_usados'], 'Devolución de puntos del pedido ' . $p['folio']]);
        }

        $pdo->prepare('UPDATE pedidos SET estado = ?, motivo_cancelacion = ?, cancelado_por = ?, fecha_cancelacion = NOW() WHERE id = ?')
            ->execute(['cancelado', $motivo, $admin['id'], $pedidoId]);
        registrarHistorial('cancelar', 'pedido', $pedidoId, ['estado' => $p['estado']], ['estado' => 'cancelado', 'motivo' => $motivo], $admin['id']);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($ex instanceof ErrorNegocio) throw $ex;
        error_log('cancelarPedido: ' . $ex->getMessage());
        throw new ErrorNegocio(500, 'No fue posible cancelar el pedido.');
    }
}

// ---------- Envío: pagado → preparando → enviado → entregado ----------
function avanzarPedido($pedidoId, array $usuario) {
    $st = db()->prepare('SELECT * FROM pedidos WHERE id = ?');
    $st->execute([$pedidoId]);
    $p = $st->fetch();
    if (!$p) throw new ErrorNegocio(404, 'El pedido no existe.');
    if (!$p['direccion_envio']) throw new ErrorNegocio(409, 'Los pedidos digitales no tienen envío.');
    $siguiente = ['pagado' => 'preparando', 'preparando' => 'enviado', 'enviado' => 'entregado'][$p['estado']] ?? null;
    if (!$siguiente) throw new ErrorNegocio(409, 'Este pedido ya no puede avanzar (está ' . $p['estado'] . ').');

    $guia = $siguiente === 'enviado' ? 'MF' . random_int(1000000000, 9999999999) : $p['numero_guia'];
    db()->prepare('UPDATE pedidos SET estado = ?, numero_guia = ? WHERE id = ?')->execute([$siguiente, $guia, $pedidoId]);
    registrarHistorial('cambiar_estado', 'pedido', $pedidoId, ['estado' => $p['estado']], ['estado' => $siguiente, 'guia' => $guia], $usuario['id']);
    return $siguiente;
}

// ---------- Ajuste manual de inventario ----------
function ajustarStock($edicionId, $nuevo, array $usuario, $nota = '') {
    if (filter_var($nuevo, FILTER_VALIDATE_INT) === false || $nuevo < 0 || $nuevo > 9999) {
        throw new ErrorNegocio(422, 'La existencia debe ser un número entero entre 0 y 9999.', ['stock' => 'Número entero entre 0 y 9999.']);
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $st = $pdo->prepare('SELECT stock FROM ediciones WHERE id = ? FOR UPDATE');
        $st->execute([$edicionId]);
        $antes = $st->fetchColumn();
        if ($antes === false) throw new ErrorNegocio(404, 'La edición no existe.');
        $dif = (int)$nuevo - (int)$antes;
        if ($dif !== 0) {
            $pdo->prepare('UPDATE ediciones SET stock = ? WHERE id = ?')->execute([$nuevo, $edicionId]);
            $pdo->prepare('INSERT INTO movimientos_inventario (edicion_id, usuario_id, tipo, cantidad, stock_resultante, nota) VALUES (?, ?, ?, ?, ?, ?)')
                ->execute([$edicionId, $usuario['id'], 'ajuste', $dif, $nuevo, mb_substr(trim($nota) ?: 'Ajuste manual', 0, 160)]);
            registrarHistorial('ajuste_stock', 'edicion', $edicionId, ['stock' => (int)$antes], ['stock' => (int)$nuevo], $usuario['id']);
        }
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $ex instanceof ErrorNegocio ? $ex : new ErrorNegocio(500, 'No fue posible ajustar la existencia.');
    }
}
