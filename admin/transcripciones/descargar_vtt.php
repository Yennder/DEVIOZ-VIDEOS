<?php

require_once '../../config/sesion.php';
verificarAdmin();
require_once '../../controllers/TranscripcionController.php';

$idVideo = filter_input(INPUT_GET, 'id_video', FILTER_VALIDATE_INT);
if (!$idVideo) {
    http_response_code(400);
    exit('Video invalido.');
}

try {
    $controller = new TranscripcionController();
    $transcripcion = $controller->obtenerPorVideo((int)$idVideo);
    if (!$transcripcion || $transcripcion['estado'] !== 'completada') {
        throw new RuntimeException('La transcripcion aun no esta completada.');
    }

    $archivo = $transcripcion['vtt_archivo'] ?: $controller->escribirVtt((int)$idVideo);
    $ruta = __DIR__ . '/../../uploads/subtitulos/' . basename($archivo);
    if (!is_file($ruta)) {
        $archivo = $controller->escribirVtt((int)$idVideo);
        $ruta = __DIR__ . '/../../uploads/subtitulos/' . basename($archivo);
    }

    header('Content-Type: text/vtt; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . basename($archivo) . '"');
    header('Content-Length: ' . filesize($ruta));
    readfile($ruta);
} catch (Throwable $e) {
    http_response_code(404);
    echo htmlspecialchars($e->getMessage());
}
