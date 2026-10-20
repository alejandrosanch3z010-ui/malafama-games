<?php
// ---------- Catálogo de juegos ----------
function juegoApi($j) {
    $estado = estadoStock($j['fecha_lanzamiento'], $j['stock']);
    return [
        'id' => (int)$j['id'], 'slug' => $j['slug'], 'titulo' => $j['titulo'], 'genero' => $j['genero'],
        'genero_id' => (int)$j['genero_id'], 'desarrollador' => $j['desarrollador'],
        'precio_final' => (float)$j['precio_final'], 'precio_normal' => (float)$j['precio_normal'],
        'descuento' => descuento($j), 'stock' => (int)$j['stock'], 'estado_stock' => $estado,
        'formatos' => explode(',', $j['formatos']), 'calificacion' => (float)$j['calificacion'],
        'total_resenas' => (int)$j['total_resenas'], 'fecha_lanzamiento' => $j['fecha_lanzamiento'],
        'fecha_texto' => fechaLarga($j['fecha_lanzamiento']), 'preventa' => esPreventa($j['fecha_lanzamiento']),
        'portada' => $j['portada'], 'portada_cuadrada' => rutaPortada($j['portada']), 'tono' => ($j['id'] * 47) % 360, 'activo' => (bool)$j['activo'],
    ];
}

function reglasJuego() {
    return [
        'titulo' => ['requerido' => true, 'min' => 2, 'max' => 120],
        'genero_id' => ['requerido' => true, 'tipo' => 'entero'],
        'sinopsis' => ['requerido' => true, 'min' => 20, 'max' => 3000],
        'fecha_lanzamiento' => ['requerido' => true, 'tipo' => 'fecha'],
        'desarrollador' => ['requerido' => true, 'max' => 80],
        'clasificacion' => ['requerido' => true, 'en' => ['E', 'E10+', 'T', 'M', 'RP']],
        'voces' => ['requerido' => true, 'max' => 120],
        'subtitulos' => ['requerido' => true, 'max' => 120],
        'nota_internet' => ['max' => 120],
        'trailer_youtube' => ['max' => 20],
        'portada' => ['max' => 150],
    ];
}

// Valida las ediciones (físico/digital) con sus reglas de precio
function validarEdiciones($ediciones) {
    if (!is_array($ediciones) || !$ediciones) fallar(422, 'Agrega al menos un formato (físico o digital).', ['ediciones' => 'Agrega al menos un formato.']);
    $vistos = [];
    foreach ($ediciones as $i => $e) {
        $f = $e['formato'] ?? '';
        $v = trim($e['version'] ?? '') ?: 'Estándar';
        if (!in_array($f, ['fisico', 'digital'], true)) fallar(422, 'Formato no válido.', ["ediciones.$i.formato" => 'Debe ser físico o digital.']);
        if (mb_strlen($v) > 40) fallar(422, 'El nombre de la versión es muy largo.', ["ediciones.$i.version" => 'Máximo 40 caracteres.']);
        if (mb_strlen($e['incluye'] ?? '') > 255) fallar(422, 'La descripción del contenido es muy larga.', ["ediciones.$i.incluye" => 'Máximo 255 caracteres.']);
        $clave = mb_strtolower($v) . '|' . $f;
        if (isset($vistos[$clave])) fallar(422, 'Esa versión ya tiene ese formato.', ["ediciones.$i.formato" => "Ya existe $v en ese formato."]);
        $vistos[$clave] = true;
        if (!is_numeric($e['precio'] ?? null) || $e['precio'] <= 0) fallar(422, 'El precio debe ser mayor a 0.', ["ediciones.$i.precio" => 'Precio mayor a 0.']);
        $oferta = $e['precio_oferta'] ?? null;
        if ($oferta !== null && $oferta !== '' && (!is_numeric($oferta) || $oferta <= 0 || $oferta >= $e['precio']))
            fallar(422, 'El precio de oferta debe ser menor al precio normal.', ["ediciones.$i.precio_oferta" => 'Debe ser menor al precio normal.']);
        if (isset($e['stock']) && (filter_var($e['stock'], FILTER_VALIDATE_INT) === false || $e['stock'] < 0))
            fallar(422, 'La existencia no puede ser negativa.', ["ediciones.$i.stock" => 'Número entero de 0 en adelante.']);
    }
}

function slugUnico($titulo, $excluirId = 0) {
    $base = trim(preg_replace('/[^a-z0-9]+/', '-', strtolower(iconv('UTF-8', 'ASCII//TRANSLIT', $titulo))), '-') ?: 'juego';
    $slug = $base; $n = 2;
    $st = db()->prepare('SELECT 1 FROM juegos WHERE slug = ? AND id <> ?');
    while (true) { $st->execute([$slug, $excluirId]); if (!$st->fetchColumn()) return $slug; $slug = $base . '-' . $n++; }
}

function juegoCompleto($id, $incluirInactivos = false) {
    $st = db()->prepare('SELECT j.*, g.nombre AS genero FROM juegos j JOIN generos g ON g.id = j.genero_id WHERE j.id = ?' . ($incluirInactivos ? '' : ' AND j.activo = 1'));
    $st->execute([$id]);
    if (!$j = $st->fetch()) fallar(404, 'El juego no existe.');
    $st = db()->prepare("SELECT id, version, formato, precio, precio_oferta, stock, incluye FROM ediciones WHERE juego_id = ? ORDER BY version <> 'Estándar', version, formato = 'digital'");
    $st->execute([$id]);
    $j['ediciones'] = array_map(fn($e) => [
        'id' => (int)$e['id'], 'version' => $e['version'], 'formato' => $e['formato'], 'incluye' => $e['incluye'], 'precio' => (float)$e['precio'],
        'precio_oferta' => $e['precio_oferta'] === null ? null : (float)$e['precio_oferta'], 'stock' => (int)$e['stock'],
        'estado_stock' => estadoStock($j['fecha_lanzamiento'], $e['stock']),
    ], $st->fetchAll());
    $st = db()->prepare('SELECT ruta FROM capturas WHERE juego_id = ? ORDER BY orden');
    $st->execute([$id]);
    $j['capturas'] = $st->fetchAll(PDO::FETCH_COLUMN);
    foreach (['id', 'genero_id', 'total_resenas'] as $k) $j[$k] = (int)$j[$k];
    foreach (['requiere_internet', 'activo'] as $k) $j[$k] = (bool)$j[$k];
    $j['calificacion'] = (float)$j['calificacion'];
    $j['preventa'] = esPreventa($j['fecha_lanzamiento']);
    return $j;
}

// Guarda juego + ediciones (crear o actualizar) dentro de una transacción
function guardarJuego(array $d, $id = null) {
    validar($d, reglasJuego());
    validarEdiciones($d['ediciones'] ?? null);
    $st = db()->prepare('SELECT activo FROM generos WHERE id = ?');
    $st->execute([(int)$d['genero_id']]);
    $g = $st->fetch();
    if (!$g) fallar(422, 'El género no existe.', ['genero_id' => 'Elige un género válido.']);
    if (!$g['activo']) fallar(409, 'Ese género está dado de baja.', ['genero_id' => 'El género está inactivo.']);

    $u = usuarioActual();
    $campos = [trim($d['titulo']), (int)$d['genero_id'], trim($d['sinopsis']), $d['fecha_lanzamiento'], trim($d['desarrollador']),
               $d['clasificacion'], trim($d['voces']), trim($d['subtitulos']), empty($d['requiere_internet']) ? 0 : 1,
               trim($d['nota_internet'] ?? '') ?: null, trim($d['trailer_youtube'] ?? '') ?: null, trim($d['portada'] ?? '') ?: null];
    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id === null) {
            $pdo->prepare('INSERT INTO juegos (titulo, genero_id, sinopsis, fecha_lanzamiento, desarrollador, clasificacion, voces, subtitulos,
                           requiere_internet, nota_internet, trailer_youtube, portada, slug) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
                ->execute(array_merge($campos, [slugUnico($d['titulo'])]));
            $id = (int)$pdo->lastInsertId();
            $antes = null;
        } else {
            $antes = juegoCompleto($id, true);
            $pdo->prepare('UPDATE juegos SET titulo = ?, genero_id = ?, sinopsis = ?, fecha_lanzamiento = ?, desarrollador = ?, clasificacion = ?,
                           voces = ?, subtitulos = ?, requiere_internet = ?, nota_internet = ?, trailer_youtube = ?, portada = ?, slug = ? WHERE id = ?')
                ->execute(array_merge($campos, [slugUnico($d['titulo'], $id), $id]));
        }
        foreach ($d['ediciones'] as $e) {
            $oferta = ($e['precio_oferta'] ?? '') === '' ? null : (float)$e['precio_oferta'];
            $version = trim($e['version'] ?? '') ?: 'Estándar';
            $incluye = trim($e['incluye'] ?? '') ?: null;
            $st = $pdo->prepare('SELECT id, stock FROM ediciones WHERE juego_id = ? AND version = ? AND formato = ?');
            $st->execute([$id, $version, $e['formato']]);
            if ($ed = $st->fetch()) {
                $pdo->prepare('UPDATE ediciones SET precio = ?, precio_oferta = ?, incluye = ? WHERE id = ?')->execute([(float)$e['precio'], $oferta, $incluye, $ed['id']]);
            } else {
                $stock = (int)($e['stock'] ?? 0);
                $pdo->prepare('INSERT INTO ediciones (juego_id, version, formato, precio, precio_oferta, stock, incluye) VALUES (?, ?, ?, ?, ?, ?, ?)')
                    ->execute([$id, $version, $e['formato'], (float)$e['precio'], $oferta, $stock, $incluye]);
                if ($stock > 0) {
                    $pdo->prepare('INSERT INTO movimientos_inventario (edicion_id, usuario_id, tipo, cantidad, stock_resultante, nota) VALUES (?, ?, ?, ?, ?, ?)')
                        ->execute([(int)$pdo->lastInsertId(), $u['id'], 'ajuste', $stock, $stock, 'Existencia inicial']);
                }
            }
        }
        registrarHistorial($antes ? 'editar' : 'crear', 'juego', $id,
            $antes ? ['titulo' => $antes['titulo'], 'genero_id' => $antes['genero_id'], 'fecha' => $antes['fecha_lanzamiento']] : null,
            ['titulo' => trim($d['titulo']), 'genero_id' => (int)$d['genero_id'], 'fecha' => $d['fecha_lanzamiento']]);
        $pdo->commit();
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $ex;
    }
    return $id;
}

// GET /api/juegos — búsqueda, filtros, orden y paginación
ruta('GET', 'juegos', function () {
    [$pagina, $limite, $offset] = paginacion(12, 50);
    $f = array_intersect_key($_GET, array_flip(['q', 'genero', 'formato', 'precio_max', 'cal_min', 'estado', 'orden']));
    if (!empty($_GET['todos']) && puede('juegos.gestionar')) {
        $f['incluir_inactivos'] = true;
        if (isset($_GET['activo'])) $f['activo'] = $_GET['activo'];
    }
    $total = 0;
    $juegos = buscarJuegos($f, $limite, $offset, $total);
    responderPagina(array_map('juegoApi', $juegos), $total, $pagina, $limite);
});

ruta('GET', 'juegos/{id}', function ($p) {
    responder(juegoCompleto(soloEntero($p['id']), puede('juegos.gestionar')));
});

ruta('POST', 'juegos', function () {
    conPermiso('juegos.gestionar');
    $id = guardarJuego(entrada());
    responder(juegoCompleto($id, true), 201, 'Juego creado.');
});

ruta('PUT', 'juegos/{id}', function ($p) {
    conPermiso('juegos.gestionar');
    $id = soloEntero($p['id']);
    juegoCompleto($id, true);
    guardarJuego(entrada(), $id);
    responder(juegoCompleto($id, true), 200, 'Juego actualizado.');
});

// PATCH: cambios parciales (por ejemplo, reactivar un juego dado de baja)
ruta('PATCH', 'juegos/{id}', function ($p) {
    conPermiso('juegos.gestionar');
    $id = soloEntero($p['id']);
    $antes = juegoCompleto($id, true);
    $d = entrada();
    if (!array_key_exists('activo', $d)) fallar(400, 'Indica el campo a cambiar (activo).');
    $activo = filter_var($d['activo'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
    db()->prepare('UPDATE juegos SET activo = ? WHERE id = ?')->execute([$activo, $id]);
    registrarHistorial($activo ? 'reactivar' : 'baja', 'juego', $id, ['activo' => $antes['activo']], ['activo' => (bool)$activo]);
    responder(juegoCompleto($id, true), 200, $activo ? 'Juego reactivado.' : 'Juego dado de baja.');
});

// DELETE: baja lógica (no se borra porque tiene ventas y reseñas relacionadas)
ruta('DELETE', 'juegos/{id}', function ($p) {
    conPermiso('juegos.gestionar');
    $id = soloEntero($p['id']);
    $antes = juegoCompleto($id, true);
    if (!$antes['activo']) fallar(409, 'El juego ya estaba dado de baja.');
    db()->prepare('UPDATE juegos SET activo = 0 WHERE id = ?')->execute([$id]);
    registrarHistorial('baja', 'juego', $id, ['activo' => true], ['activo' => false]);
    responder(null, 200, 'Juego dado de baja. Ya no aparece en la tienda.');
});

// Ajuste de existencias y precios de una edición
ruta('PATCH', 'ediciones/{id}', function ($p) {
    $u = conPermiso('inventario.ajustar');
    $id = soloEntero($p['id']);
    $d = entrada();
    $st = db()->prepare('SELECT * FROM ediciones WHERE id = ?');
    $st->execute([$id]);
    if (!$ed = $st->fetch()) fallar(404, 'La edición no existe.');

    if (array_key_exists('precio', $d) || array_key_exists('precio_oferta', $d)) {
        if (!puede('juegos.gestionar', $u)) fallar(403, 'Solo el administrador puede cambiar precios.');
        $precio = $d['precio'] ?? $ed['precio'];
        $oferta = array_key_exists('precio_oferta', $d) ? $d['precio_oferta'] : $ed['precio_oferta'];
        validarEdiciones([['formato' => $ed['formato'], 'precio' => $precio, 'precio_oferta' => $oferta]]);
        db()->prepare('UPDATE ediciones SET precio = ?, precio_oferta = ? WHERE id = ?')->execute([(float)$precio, $oferta === '' || $oferta === null ? null : (float)$oferta, $id]);
        registrarHistorial('editar_precio', 'edicion', $id, ['precio' => (float)$ed['precio'], 'oferta' => $ed['precio_oferta']], ['precio' => (float)$precio, 'oferta' => $oferta]);
    }
    if (array_key_exists('stock', $d)) ajustarStock($id, $d['stock'], $u, $d['nota'] ?? '');
    responder(null, 200, 'Edición actualizada.');
});
