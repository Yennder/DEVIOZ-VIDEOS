<?php
/** DEVIOZ V4.4.4 - Guardado de reflexiones sobre escenas publicadas. */
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../models/EscenaAprendizaje.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

function escenaResponder(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    escenaResponder(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
}
if (!usuarioAutenticado()) {
    escenaResponder(401, ['ok' => false, 'mensaje' => 'Inicia sesión para guardar tu aprendizaje.']);
}
$raw = file_get_contents('php://input', false, null, 0, 8193);
if (!is_string($raw) || strlen($raw) > 8192) {
    escenaResponder(413, ['ok' => false, 'mensaje' => 'La solicitud es demasiado grande.']);
}
$datos = json_decode($raw, true);
if (!is_array($datos)) {
    escenaResponder(400, ['ok' => false, 'mensaje' => 'Solicitud JSON inválida.']);
}
if (!csrfValido($datos['csrf_token'] ?? null)) {
    escenaResponder(419, ['ok' => false, 'mensaje' => 'La sesión ha expirado. Recarga la página.']);
}
$idVideo = $datos['id_video'] ?? null;
$idCapitulo = $datos['id_capitulo'] ?? null;
$nota = $datos['nota'] ?? null;
$completada = $datos['completada'] ?? null;
if (!is_int($idVideo) || $idVideo <= 0 || !is_int($idCapitulo) || $idCapitulo <= 0
    || !is_string($nota) || !is_bool($completada)) {
    escenaResponder(422, ['ok' => false, 'mensaje' => 'Completa la reflexión con datos válidos.']);
}

try {
    $modelo = new EscenaAprendizaje();
    if (!$modelo->instalado()) {
        escenaResponder(503, ['ok' => false, 'mensaje' => 'Falta instalar la migración V4.4.4.']);
    }
    $guardado = $modelo->guardar((int)$_SESSION['id_usuario'], $idVideo, $idCapitulo, $nota, $completada);
    escenaResponder(200, ['ok' => true, 'mensaje' => 'Tu avance se guardó correctamente.'] + $guardado);
} catch (InvalidArgumentException $e) {
    escenaResponder(422, ['ok' => false, 'mensaje' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('DEVIOZ V4.4.4 - Estudio por escenas: ' . $e->getMessage());
    escenaResponder(500, ['ok' => false, 'mensaje' => 'No fue posible guardar la escena. Vuelve a intentarlo.']);
}
