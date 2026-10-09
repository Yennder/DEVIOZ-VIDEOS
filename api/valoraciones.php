<?php

require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../controllers/ValoracionController.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function responderValoracion(int $codigo, array $datos): void
{
    http_response_code($codigo);
    echo json_encode($datos, JSON_UNESCAPED_UNICODE);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    responderValoracion(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
}
if (!usuarioAutenticado()) {
    responderValoracion(401, ['ok' => false, 'mensaje' => 'Inicia sesión para calificar videos.']);
}

$entrada = json_decode(file_get_contents('php://input'), true);
if (!is_array($entrada)) {
    responderValoracion(400, ['ok' => false, 'mensaje' => 'Solicitud inválida.']);
}
if (!csrfValido($entrada['csrf_token'] ?? null)) {
    responderValoracion(419, ['ok' => false, 'mensaje' => 'Sesión de seguridad expirada. Actualiza la página.']);
}

// Se exige un JSON con enteros verdaderos, sin convertir cadenas ni decimales.
$idVideo = $entrada['id_video'] ?? null;
$estrellas = $entrada['estrellas'] ?? null;
if (!is_int($idVideo) || !is_int($estrellas) || $idVideo <= 0 || $estrellas < 1 || $estrellas > 5) {
    responderValoracion(422, ['ok' => false, 'mensaje' => 'Elige entre 1 y 5 estrellas.']);
}

try {
    $resultado = (new ValoracionController())->guardar((int)$_SESSION['id_usuario'], $idVideo, $estrellas);
    responderValoracion(200, ['ok' => true, 'mensaje' => 'Calificación guardada.'] + $resultado);
} catch (InvalidArgumentException $e) {
    responderValoracion(422, ['ok' => false, 'mensaje' => $e->getMessage()]);
} catch (Throwable $e) {
    error_log('DEVIOZ V4.5.3 - Valoraciones: ' . $e->getMessage());
    responderValoracion(500, ['ok' => false, 'mensaje' => 'No se pudo guardar la calificación. Inténtalo de nuevo.']);
}
