<?php
// =====================================================================
//  Núcleo de la API REST: respuestas JSON, errores, autenticación,
//  permisos, validación y paginación.
// =====================================================================
require_once __DIR__ . '/../includes/funciones.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

// Respuesta correcta. Si el formulario pidió recargar la página, el mensaje se guarda como aviso.
function responder($datos = null, $status = 200, $mensaje = null, $extra = []) {
    if ($mensaje && !empty($_SERVER['HTTP_X_FLASH'])) flash($mensaje);
    http_response_code($status);
    $cuerpo = ['ok' => true] + ($mensaje ? ['mensaje' => $mensaje] : []) + $extra;
    if ($datos !== null) $cuerpo['datos'] = $datos;
    echo json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

function fallar($status, $mensaje, $campos = []) {
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $mensaje] + ($campos ? ['campos' => $campos] : []), JSON_UNESCAPED_UNICODE);
    exit;
}

// Cualquier error inesperado se responde con un mensaje entendible (nunca SQLSTATE ni rutas)
set_exception_handler(function (Throwable $e) {
    if ($e instanceof ErrorNegocio) fallar($e->status, $e->getMessage(), $e->campos);
    if ($e instanceof PDOException && ($e->errorInfo[1] ?? 0) == 1062) fallar(409, 'Ese registro ya existe.');
    error_log('API: ' . $e->getMessage() . ' en ' . $e->getFile() . ':' . $e->getLine());
    fallar(500, 'No fue posible completar la operación. Intenta de nuevo.');
});

// Cuerpo de la petición en JSON (o formulario como respaldo)
function entrada() {
    static $datos = null;
    if ($datos === null) {
        $json = json_decode(file_get_contents('php://input') ?: '', true);
        $datos = is_array($json) ? $json : $_POST;
    }
    return $datos;
}

// Las peticiones que cambian datos deben traer el token CSRF de la sesión
function verificarCsrfApi() {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') return;
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? (entrada()['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)$token)) {
        fallar(403, 'La sesión expiró o la petición no es válida. Recarga la página.');
    }
}

// Usuario autenticado → rol → permiso
function autenticado() {
    $u = usuarioActual();
    if (!$u) fallar(401, 'Inicia sesión para continuar.');
    return $u;
}
function conPermiso($permiso) {
    $u = autenticado();
    if (!puede($permiso, $u)) fallar(403, 'No tienes permiso para realizar esta operación.');
    return $u;
}

// ---------- Validación ----------
function validar(array $d, array $reglas) {
    $errores = [];
    foreach ($reglas as $campo => $r) {
        $v = $d[$campo] ?? null;
        $vacio = $v === null || (is_string($v) && trim($v) === '');
        if (!empty($r['requerido']) && $vacio) { $errores[$campo] = $r['mensaje'] ?? 'Este campo es obligatorio.'; continue; }
        if ($vacio) continue;
        if (isset($r['max']) && mb_strlen((string)$v) > $r['max']) $errores[$campo] = "Máximo {$r['max']} caracteres.";
        if (isset($r['min']) && mb_strlen(trim((string)$v)) < $r['min']) $errores[$campo] = "Mínimo {$r['min']} caracteres.";
        if (($r['tipo'] ?? '') === 'email' && !filter_var($v, FILTER_VALIDATE_EMAIL)) $errores[$campo] = 'Escribe un correo válido.';
        if (($r['tipo'] ?? '') === 'entero' && filter_var($v, FILTER_VALIDATE_INT) === false) $errores[$campo] = 'Debe ser un número entero.';
        if (($r['tipo'] ?? '') === 'numero' && !is_numeric($v)) $errores[$campo] = 'Debe ser un número.';
        if (($r['tipo'] ?? '') === 'fecha' && !(($f = DateTime::createFromFormat('Y-m-d', $v)) && $f->format('Y-m-d') === $v)) $errores[$campo] = 'Fecha inválida (AAAA-MM-DD).';
        if (isset($r['minimo']) && is_numeric($v) && $v < $r['minimo']) $errores[$campo] = "Debe ser mayor o igual a {$r['minimo']}.";
        if (isset($r['maximo']) && is_numeric($v) && $v > $r['maximo']) $errores[$campo] = "Debe ser menor o igual a {$r['maximo']}.";
        if (isset($r['en']) && !in_array($v, $r['en'], true)) $errores[$campo] = 'Valor no permitido.';
    }
    if ($errores) fallar(422, 'Revisa los datos marcados.', $errores);
}

// ---------- Paginación: ?page=2&limit=20 ----------
function paginacion($porDefecto = 20, $maximo = 100) {
    $pagina = max(1, (int)($_GET['page'] ?? 1));
    $limite = min($maximo, max(1, (int)($_GET['limit'] ?? $porDefecto)));
    return [$pagina, $limite, ($pagina - 1) * $limite];
}
function responderPagina(array $datos, $total, $pagina, $limite) {
    responder($datos, 200, null, [
        'pagina' => $pagina, 'limite' => $limite, 'total' => (int)$total,
        'totalPaginas' => max(1, (int)ceil($total / $limite)),
    ]);
}

// Rango de fechas para reportes (por defecto, el mes actual)
function rangoFechas() {
    $desde = $_GET['desde'] ?? date('Y-m-01');
    $hasta = $_GET['hasta'] ?? date('Y-m-d');
    validar(['desde' => $desde, 'hasta' => $hasta], ['desde' => ['tipo' => 'fecha'], 'hasta' => ['tipo' => 'fecha']]);
    if ($desde > $hasta) fallar(422, 'La fecha inicial no puede ser mayor que la final.', ['desde' => 'Debe ser menor o igual a la fecha final.']);
    if ((strtotime($hasta) - strtotime($desde)) / 86400 > 366) fallar(422, 'El rango máximo es de un año.', ['hasta' => 'Rango máximo de 366 días.']);
    return [$desde, $hasta];
}

function soloEntero($v) {
    if (filter_var($v, FILTER_VALIDATE_INT) === false) fallar(400, 'Identificador inválido.');
    return (int)$v;
}
