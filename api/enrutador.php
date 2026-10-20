<?php
// =====================================================================
//  API REST de Mala Fama Games — enrutador (el .htaccess de la raíz manda aquí /api/...)
//  Todas las rutas: /api/<recurso>[/<id>[/<subrecurso>]]
// =====================================================================
require_once __DIR__ . '/nucleo.php';

$rutas = [];
function ruta($metodo, $patron, callable $accion) {
    global $rutas;
    $rutas[] = [$metodo, '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $patron) . '$#', $accion];
}

foreach (glob(__DIR__ . '/recursos/api-*.php') as $archivo) require $archivo;

$metodo = $_SERVER['REQUEST_METHOD'];
$camino = trim($_GET['ruta'] ?? '', '/');
if ($camino === '' && preg_match('#/api/(.*?)(\?|$)#', $_SERVER['REQUEST_URI'], $m)) $camino = trim($m[1], '/');

verificarCsrfApi();

$metodoPermitido = false;
foreach ($rutas as [$m, $regex, $accion]) {
    if (preg_match($regex, $camino, $params)) {
        if ($m !== $metodo) { $metodoPermitido = true; continue; }
        $accion(array_filter($params, 'is_string', ARRAY_FILTER_USE_KEY));
        exit;
    }
}
if ($metodoPermitido) fallar(405, 'Método no permitido para esta ruta.');
fallar(404, 'La ruta de la API no existe.');
