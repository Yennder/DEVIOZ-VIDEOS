<?php
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../controllers/EvaluacionIAController.php';

function responderEvaluacionIA(array $data, int $status=200): void
{
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!usuarioAutenticado()) responderEvaluacionIA(['ok'=>false,'mensaje'=>'Debes iniciar sesión.'],401);
if (!esAdmin()) responderEvaluacionIA(['ok'=>false,'mensaje'=>'Solo un administrador puede generar evaluaciones con IA.'],403);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') responderEvaluacionIA(['ok'=>false,'mensaje'=>'Método no permitido.'],405);

$entrada = json_decode((string)file_get_contents('php://input'),true);
if (!is_array($entrada)) responderEvaluacionIA(['ok'=>false,'mensaje'=>'Solicitud inválida.'],400);
if (!csrfValido($entrada['csrf_token'] ?? null)) responderEvaluacionIA(['ok'=>false,'mensaje'=>'La sesión de seguridad expiró. Recarga la página.'],419);

try {
    $curso = (int)($entrada['id_curso'] ?? 0);
    if ($curso <= 0) throw new InvalidArgumentException('Curso inválido.');
    $controller = new EvaluacionIAController();
    $accion = (string)($entrada['accion'] ?? 'generar');
    if ($accion !== 'generar') throw new InvalidArgumentException('Acción no válida.');
    $resultado = $controller->generar($curso,$entrada,(int)$_SESSION['id_usuario']);
    responderEvaluacionIA($resultado);
} catch (InvalidArgumentException $e) {
    responderEvaluacionIA(['ok'=>false,'mensaje'=>$e->getMessage()],422);
} catch (RuntimeException $e) {
    responderEvaluacionIA(['ok'=>false,'mensaje'=>$e->getMessage()],422);
} catch (Throwable $e) {
    error_log('TECHFLIX V4.4.2 - Evaluacion IA: '.$e->getMessage());
    responderEvaluacionIA(['ok'=>false,'mensaje'=>'No se pudo generar la evaluación con IA en este momento.'],500);
}
