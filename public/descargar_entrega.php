<?php
/** Descarga autenticada de evidencias Watch & Build V5.2. */
require_once __DIR__ . '/../config/sesion.php';
verificarSesion();
require_once __DIR__ . '/../models/WatchBuild.php';
$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 0;
if (!$id) { http_response_code(404); exit('Archivo no encontrado.'); }
try {
    $e = (new WatchBuild())->entrega($id);
    if (!$e || (!$e['archivo_guardado'])) { http_response_code(404); exit('Archivo no encontrado.'); }
    if (!esAdmin() && (int)$e['id_usuario'] !== (int)$_SESSION['id_usuario']) {
        http_response_code(403); exit('Sin permiso para descargar esta evidencia.');
    }
    $file = (string)$e['archivo_guardado'];
    if (!preg_match('/\A[a-f0-9]{32}\.(?:pdf|png|jpe?g|webp|zip)\z/D', $file)) {
        http_response_code(404); exit('Archivo no encontrado.');
    }
    $base = realpath(WatchBuild::storagePath());
    $path = $base ? realpath($base . DIRECTORY_SEPARATOR . $file) : false;
    if (!$base || !$path || dirname($path) !== $base || !is_readable($path)) {
        http_response_code(404); exit('Archivo no encontrado.');
    }
    $nombre = (string)($e['archivo_original'] ?: 'evidencia');
    $nombre = preg_replace('/[\x00-\x1f\x7f\\\/]+/u', '_', $nombre) ?: 'evidencia';
    $nombre = function_exists('mb_substr') ? mb_substr($nombre, 0, 180, 'UTF-8') : substr($nombre, 0, 180);
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="evidencia"; filename*=UTF-8\'\''.rawurlencode($nombre));
    header('Content-Length: '.filesize($path));
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: private, no-store');
    readfile($path);
} catch (Throwable $e) {
    error_log('WatchBuild descargar: '.$e->getMessage());
    if (!headers_sent()) { http_response_code(500); }
}
exit;
