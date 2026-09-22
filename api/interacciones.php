<?php

header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../controllers/InteraccionController.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'mensaje' => 'Método no permitido.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!usuarioAutenticado()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'mensaje' => 'Debes iniciar sesión.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$datos = json_decode(file_get_contents('php://input'), true);
if (!is_array($datos)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'Solicitud inválida.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!csrfValido($datos['csrf_token'] ?? null)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'mensaje' => 'La sesión de seguridad expiró. Recarga la página.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$accion = trim((string)($datos['accion'] ?? ''));
$idUsuario = (int)$_SESSION['id_usuario'];
$controller = new InteraccionController();

try {
    switch ($accion) {
        case 'toggle_like':
            $video = (int)($datos['id_video'] ?? 0);
            if ($video <= 0) { throw new InvalidArgumentException('Video inválido.'); }
            $resultado = $controller->toggleLike($idUsuario, $video);
            echo json_encode(['ok' => true] + $resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'toggle_favorito':
            $video = (int)($datos['id_video'] ?? 0);
            if ($video <= 0) { throw new InvalidArgumentException('Video inválido.'); }
            $resultado = $controller->toggleFavorito($idUsuario, $video);
            echo json_encode(['ok' => true] + $resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'guardar_progreso':
            $video = (int)($datos['id_video'] ?? 0);
            if ($video <= 0) { throw new InvalidArgumentException('Video inválido.'); }
            $resultado = $controller->guardarProgreso(
                $idUsuario,
                $video,
                (int)($datos['posicion'] ?? 0),
                (int)($datos['duracion'] ?? 0)
            );

            $learningAsignacion = (int)($datos['learning_asignacion'] ?? 0);
            if ($learningAsignacion > 0) {
                require_once __DIR__ . '/../controllers/LearningController.php';
                $learning = new LearningController();
                // detalleAsignacion valida que la asignación pertenezca al usuario
                // y sincroniza sus lecciones con el progreso real del reproductor.
                $learning->detalleAsignacion($learningAsignacion, $idUsuario);
            }

            echo json_encode($resultado, JSON_UNESCAPED_UNICODE);
            break;

        case 'crear_playlist':
            $id = $controller->crearPlaylist(
                $idUsuario,
                (string)($datos['nombre'] ?? ''),
                (string)($datos['descripcion'] ?? '')
            );
            if (!$id) { throw new InvalidArgumentException('No se pudo crear la playlist. Revisa el nombre o evita duplicados.'); }
            echo json_encode(['ok' => true, 'id_playlist' => $id], JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar_playlist':
            $ok = $controller->agregarVideoPlaylist(
                $idUsuario,
                (int)($datos['id_playlist'] ?? 0),
                (int)($datos['id_video'] ?? 0)
            );
            if (!$ok) { throw new InvalidArgumentException('No se pudo agregar el video a la playlist.'); }
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            break;

        case 'quitar_playlist':
            $ok = $controller->quitarVideoPlaylist(
                $idUsuario,
                (int)($datos['id_playlist'] ?? 0),
                (int)($datos['id_video'] ?? 0)
            );
            echo json_encode(['ok' => (bool)$ok], JSON_UNESCAPED_UNICODE);
            break;

        case 'eliminar_playlist':
            $ok = $controller->eliminarPlaylist($idUsuario, (int)($datos['id_playlist'] ?? 0));
            echo json_encode(['ok' => (bool)$ok], JSON_UNESCAPED_UNICODE);
            break;

        case 'agregar_comentario':
            $ok = $controller->agregarComentario(
                $idUsuario,
                (int)($datos['id_video'] ?? 0),
                (string)($datos['contenido'] ?? '')
            );
            if (!$ok) { throw new InvalidArgumentException('No se pudo publicar. Revisa el comentario o espera unos segundos antes de volver a comentar.'); }
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            break;

        case 'responder_comentario':
            $ok = $controller->agregarRespuestaComentario(
                $idUsuario,
                (int)($datos['id_video'] ?? 0),
                (int)($datos['id_comentario_padre'] ?? 0),
                (string)($datos['contenido'] ?? '')
            );
            if (!$ok) { throw new InvalidArgumentException('No se pudo publicar la respuesta. Revisa el comentario o espera unos segundos.'); }
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            break;

        case 'editar_comentario':
            $ok = $controller->editarComentario(
                $idUsuario,
                (int)($datos['id_comentario'] ?? 0),
                (string)($datos['contenido'] ?? '')
            );
            if (!$ok) { throw new InvalidArgumentException('No se pudo editar el comentario.'); }
            echo json_encode(['ok' => true], JSON_UNESCAPED_UNICODE);
            break;

        case 'eliminar_comentario':
            $ok = $controller->eliminarComentario(
                $idUsuario,
                (int)($datos['id_comentario'] ?? 0),
                esAdmin()
            );
            echo json_encode(['ok' => (bool)$ok], JSON_UNESCAPED_UNICODE);
            break;

        default:
            throw new InvalidArgumentException('Acción no válida.');
    }
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'mensaje' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('DEVIOZ INTERACCIONES - ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok' => false, 'mensaje' => 'No se pudo completar la operación.'], JSON_UNESCAPED_UNICODE);
}
