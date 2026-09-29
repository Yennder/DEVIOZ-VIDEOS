<?php
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../includes/KnowledgeIndexManager.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$q = trim((string)($_GET['q'] ?? $_POST['q'] ?? ''));
$scopeRaw = (string)($_GET['scope'] ?? $_POST['scope'] ?? 'global');
$scope = in_array($scopeRaw, ['global','curso','serie','video'], true) ? $scopeRaw : 'global';
$scopeIdRaw = (string)($_GET['scope_id'] ?? $_POST['scope_id'] ?? '0');
$scopeId = ctype_digit($scopeIdRaw) ? (int)$scopeIdRaw : 0;
$topKRaw = (string)($_GET['top_k'] ?? $_POST['top_k'] ?? '14');
$topK = ctype_digit($topKRaw) ? max(1, min(20, (int)$topKRaw)) : 14;

if (mb_strlen($q) < 2) {
    echo json_encode(['ok'=>true,'results'=>[],'count'=>0], JSON_UNESCAPED_UNICODE);
    exit;
}
if ($scope !== 'global' && $scopeId <= 0) {
    $scope = 'global';
    $scopeId = 0;
}
if ($scope === 'curso' && !usuarioAutenticado()) {
    $scope = 'global';
    $scopeId = 0;
}

$started = microtime(true);
try {
    $manager = new KnowledgeIndexManager();
    $raw = $manager->search($q, $topK, $scope, $scopeId);
    $results = [];
    foreach ($raw as $item) {
        if ((float)($item['score'] ?? 0) >= 0.27) {
            $results[] = $item;
        }
    }
    echo json_encode([
        'ok'=>true,
        'results'=>$results,
        'count'=>count($results),
        'elapsed_ms'=>round((microtime(true)-$started)*1000, 1),
        'scope'=>$scope,
        'scope_id'=>$scopeId,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'ok'=>false,
        'results'=>[],
        'count'=>0,
        'error'=>'La búsqueda dentro de los videos no está disponible en este momento.'
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
