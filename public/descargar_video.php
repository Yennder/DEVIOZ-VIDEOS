<?php

require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../controllers/DescargaController.php';
require_once __DIR__ . '/../includes/video_security.php';

verificarSesion();

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    exit('Metodo no permitido.');
}

verificarCsrf($_POST['csrf_token'] ?? null);

$idVideo = filter_input(INPUT_POST, 'id_video', FILTER_VALIDATE_INT);
$codigo = trim((string)($_POST['codigo_descarga'] ?? ''));
if (!$idVideo || $codigo === '') {
    http_response_code(422);
    exit('Debes indicar el video y el codigo de descarga.');
}

try {
    $controller = new DescargaController();
    if (!$controller->tablasDisponibles()) {
        throw new RuntimeException('El modulo de descargas aun no esta instalado.');
    }

    $registro = $controller->validarYConsumir(
        $codigo,
        (int)$_SESSION['id_usuario'],
        (int)$idVideo,
        (string)($_SERVER['REMOTE_ADDR'] ?? ''),
        (string)($_SERVER['HTTP_USER_AGENT'] ?? '')
    );

    $ruta = (string)$registro['ruta_archivo'];
    $extension = strtolower(pathinfo($ruta, PATHINFO_EXTENSION)) ?: 'mp4';
    $titulo = preg_replace('/[^\pL\pN _-]+/u', '', (string)$registro['titulo']);
    $titulo = trim((string)$titulo) ?: ('video-' . (int)$idVideo);
    $nombre = preg_replace('/\s+/', '-', $titulo) . '.' . $extension;

    $mime = match ($extension) {
        'webm' => 'video/webm',
        'mov' => 'video/quicktime',
        'm4v' => 'video/x-m4v',
        default => 'video/mp4',
    };

    session_write_close();
    deviozStreamArchivo($ruta, $mime, $nombre, true);
} catch (Throwable $e) {
    http_response_code(403);
    $mensaje = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    echo '<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Descarga no autorizada</title><style>body{font-family:Arial,sans-serif;background:#08131b;color:#eef8fb;display:grid;place-items:center;min-height:100vh;margin:0}.box{max-width:560px;background:#13232e;border:1px solid #285063;border-radius:18px;padding:28px}.box h1{margin-top:0;color:#38d1e7}.box a{color:#38d1e7}</style></head><body><div class="box"><h1>No se pudo autorizar la descarga</h1><p>' . $mensaje . '</p><p>Cierra esta ventana y solicita un nuevo codigo al administrador si es necesario.</p></div></body></html>';
}
