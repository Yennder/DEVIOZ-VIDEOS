<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../controllers/NotificacionController.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!usuarioAutenticado()) {
    http_response_code(401);
    echo json_encode(['ok'=>false], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $idUsuario = (int)$_SESSION['id_usuario'];
    $controller = new NotificacionController();
    $controller->sincronizarUsuario($idUsuario);
    $items = $controller->noLeidas($idUsuario, 5);
    $salida = [];
    foreach ($items as $n) {
        $salida[] = [
            'id' => (int)$n['id_notificacion'],
            'tipo' => (string)$n['tipo'],
            'titulo' => (string)$n['titulo'],
            'mensaje' => (string)$n['mensaje'],
            'url' => (string)($n['url'] ?: '/DEVIOZ-VIDEOS/public/notificaciones.php'),
            'icono' => (string)($n['icono'] ?: '🔔'),
            'fecha' => date('d/m H:i', strtotime((string)$n['fecha_creacion'])),
        ];
    }
    echo json_encode([
        'ok' => true,
        'count' => $controller->contarNoLeidas($idUsuario),
        'items' => $salida,
        'csrf' => csrfToken(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false], JSON_UNESCAPED_UNICODE);
}
