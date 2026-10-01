<?php
// Configuración: las credenciales se leen del archivo .env (que no se sube a Git).
// Si falta alguna variable se usa el valor por defecto de XAMPP.
function cargarEnv($ruta) {
    if (!is_readable($ruta)) return;
    foreach (file($ruta, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $linea) {
        $linea = trim($linea);
        if ($linea === '' || $linea[0] === '#' || !str_contains($linea, '=')) continue;
        [$clave, $valor] = array_map('trim', explode('=', $linea, 2));
        $_ENV[$clave] = trim($valor, "\"'");
    }
}
cargarEnv(dirname(__DIR__) . '/.env');

function env($clave, $porDefecto = null) {
    return $_ENV[$clave] ?? $porDefecto;
}

define('DB_HOST', env('DB_HOST', '127.0.0.1'));
define('DB_PORT', env('DB_PORT', '3306'));
define('DB_NAME', env('DB_NAME', 'malafama_games'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASSWORD', ''));
define('APP_ENV', env('APP_ENV', 'production'));

date_default_timezone_set(env('APP_ZONA_HORARIA', 'America/Mexico_City'));
// Nunca se muestran errores internos en pantalla (SQLSTATE, rutas, etc.): van al registro
ini_set('display_errors', '0');
