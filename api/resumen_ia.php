<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../controllers/ResumenIAController.php';

function responderResumen(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!usuarioAutenticado()) {
    responderResumen(['ok' => false, 'mensaje' => 'Debes iniciar sesión.'], 401);
}

$controller = new ResumenIAController();

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        $tipo = (string)($_GET['tipo'] ?? '');
        $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: 0;
        responderResumen($controller->estado($tipo, (int)$id));
    }

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        responderResumen(['ok' => false, 'mensaje' => 'Método no permitido.'], 405);
    }

    $entrada = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($entrada)) {
        responderResumen(['ok' => false, 'mensaje' => 'Solicitud inválida.'], 400);
    }

    if (!csrfValido($entrada['csrf_token'] ?? null)) {
        responderResumen(['ok' => false, 'mensaje' => 'La sesión de seguridad expiró. Recarga la página.'], 419);
    }

    $tipo = (string)($entrada['tipo'] ?? '');
    $id = (int)($entrada['id'] ?? 0);
    $forzar = !empty($entrada['forzar']) && esAdmin();
    $resultado = $controller->generar($tipo, $id, (int)$_SESSION['id_usuario'], $forzar);
    responderResumen($resultado);
} catch (InvalidArgumentException $e) {
    responderResumen(['ok' => false, 'mensaje' => $e->getMessage()], 422);
} catch (RuntimeException $e) {
    responderResumen(['ok' => false, 'mensaje' => $e->getMessage()], 422);
} catch (Throwable $e) {
    error_log('TECHFLIX V4.4.1 - Resumen IA: ' . $e->getMessage());
    responderResumen(['ok' => false, 'mensaje' => 'No se pudo generar el resumen en este momento.'], 500);
}
