<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../controllers/BusquedaController.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$q = trim((string)($_GET['q'] ?? ''));
if (mb_strlen($q) < 2) {
    echo json_encode(['ok'=>true,'items'=>[]], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $controller = new BusquedaController();
    $items = $controller->sugerencias($q, usuarioAutenticado(), 7);
    echo json_encode(['ok'=>true,'items'=>$items], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'items'=>[]], JSON_UNESCAPED_UNICODE);
}
