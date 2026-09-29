<?php

require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../controllers/VideoController.php';
require_once __DIR__ . '/../includes/video_security.php';

$idVideo = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$expira = filter_input(INPUT_GET, 'exp', FILTER_VALIDATE_INT);
$token = (string)($_GET['token'] ?? '');

if (!$idVideo || !$expira || !deviozVideoStreamTokenValido((int)$idVideo, (int)$expira, $token)) {
    http_response_code(403);
    exit('Acceso de reproduccion no autorizado o vencido. Recarga la pagina del video.');
}

$controller = new VideoController();
$video = $controller->buscarDetalle((int)$idVideo);
if (!$video) {
    http_response_code(404);
    exit('Video no encontrado.');
}

$ruta = __DIR__ . '/../uploads/videos/' . basename((string)$video['archivo_video']);
$extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION));
$mime = match ($extension) {
    'webm' => 'video/webm',
    'mov' => 'video/quicktime',
    'm4v' => 'video/x-m4v',
    default => 'video/mp4',
};

session_write_close();
deviozStreamArchivo($ruta, $mime, 'stream-' . (int)$idVideo . '.' . ($extension ?: 'mp4'), false);
