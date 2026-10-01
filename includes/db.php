<?php
require_once __DIR__ . '/config.php';

function db() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                 PDO::ATTR_TIMEOUT => 5, PDO::ATTR_EMULATE_PREPARES => false] // si MySQL no responde en 5 s, se muestra el aviso en vez de quedarse cargando
            );
        } catch (PDOException $e) {
            // Mensaje claro en vez del error técnico (que además muestra rutas del servidor)
            error_log('Mala Fama Games - error de conexión: ' . $e->getMessage());
            http_response_code(503);
            // Si la petición es a la API, se responde en JSON
            if (str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')) {
                header('Content-Type: application/json; charset=utf-8');
                exit(json_encode(['ok' => false, 'error' => 'No hay conexión con la base de datos.']));
            }
            exit('<!DOCTYPE html><html lang="es"><meta charset="UTF-8"><title>Sin conexión a la base de datos</title>'
               . '<body style="font-family:Arial,sans-serif;background:#0b0d26;color:#f3f5ff;display:grid;place-items:center;min-height:100vh;margin:0;text-align:center">'
               . '<div><h1>No se pudo conectar a la base de datos</h1>'
               . '<p>Revisa que <b>MySQL</b> esté encendido (en verde) en XAMPP y vuelve a cargar la página.</p><p>Si ya está encendido, dale Stop y Start a MySQL y a Apache.</p></div></body></html>');
        }
    }
    return $pdo;
}
